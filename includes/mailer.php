<?php
/**
 * Minimal email sender (no Composer needed).
 *
 * Transports, chosen by environment variables:
 *  - Gmail relay (Google Apps Script web app): GMAIL_SCRIPT_URL + GMAIL_SCRIPT_SECRET
 *    Works on Render's free plan, which blocks outbound SMTP ports.
 *  - Gmail SMTP with an App Password: SMTP_USER + SMTP_PASS (+ SMTP_HOST, SMTP_PORT)
 * See GMAIL-OTP-SETUP.md.
 */

require_once __DIR__ . '/../config.php';

function mailEnv($name, $default = '') {
    $value = getenv($name);
    return $value === false || trim($value) === '' ? $default : trim($value);
}

function mailTransport() {
    if (PHP_SAPI === 'cli-server' && mailEnv('MAIL_TRANSPORT') === 'log') {
        return 'log'; // local development only (PHP built-in server), never on Apache
    }
    if (mailEnv('GMAIL_SCRIPT_URL') !== '' && mailEnv('GMAIL_SCRIPT_SECRET') !== '') {
        return 'gmail_script';
    }
    if (mailEnv('SMTP_USER') !== '' && mailEnv('SMTP_PASS') !== '') {
        return 'smtp';
    }
    return '';
}

function isMailConfigured() {
    return mailTransport() !== '';
}

function mailFromName() {
    $info = getSystemInfo();
    return mailEnv('MAIL_FROM_NAME', !empty($info['short_name']) ? $info['short_name'] : 'Sales and Procurement');
}

/**
 * Send an email. Returns [true, ''] on success or [false, 'reason'].
 */
function sendMail($to, $subject, $textBody, $htmlBody) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Invalid recipient email address'];
    }
    switch (mailTransport()) {
        case 'gmail_script':
            return mailSendViaScript($to, $subject, $textBody, $htmlBody);
        case 'smtp':
            return mailSendViaSmtp($to, $subject, $textBody, $htmlBody);
        case 'log':
            error_log("[MAIL to $to] $subject\n$textBody");
            return [true, ''];
        default:
            return [false, 'Email sending is not configured'];
    }
}

function mailSendViaScript($to, $subject, $textBody, $htmlBody) {
    if (!function_exists('curl_init')) {
        return [false, 'PHP curl extension is missing'];
    }
    $ch = curl_init(mailEnv('GMAIL_SCRIPT_URL'));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'secret'   => mailEnv('GMAIL_SCRIPT_SECRET'),
            'to'       => $to,
            'subject'  => $subject,
            'text'     => $textBody,
            'html'     => $htmlBody,
            'fromName' => mailFromName(),
        ]),
        // No JSON Content-Type header: curl re-sends custom headers on the redirect
        // to googleusercontent.com, which then answers 404. The script reads the raw body anyway.
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true, // Apps Script answers via a redirect
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [false, 'Could not reach the Gmail relay: ' . $curlError];
    }
    $result = json_decode($response, true);
    if (empty($result['ok'])) {
        error_log('Gmail relay failed: HTTP ' . $httpCode . ' ' . substr($response, 0, 500));
        if (isset($result['error'])) {
            return [false, 'Gmail relay error: ' . $result['error']];
        }
        // Google returned a web page instead of JSON (sign-in page, script error, ...)
        $title = preg_match('/<title>(.*?)<\/title>/is', $response, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : '';
        $hint = stripos($response, 'accounts.google.com') !== false || stripos($title, 'sign in') !== false
            ? 'set "Who has access" to Anyone and redeploy'
            : ($title !== '' ? $title : 'unexpected response');
        return [false, 'Gmail relay error (HTTP ' . $httpCode . '): ' . $hint];
    }
    return [true, ''];
}

function mailSendViaSmtp($to, $subject, $textBody, $htmlBody) {
    $host = mailEnv('SMTP_HOST', 'smtp.gmail.com');
    $port = (int) mailEnv('SMTP_PORT', '587');
    $user = mailEnv('SMTP_USER');
    $pass = str_replace(' ', '', mailEnv('SMTP_PASS')); // Google shows App Passwords in groups of 4

    $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$socket) {
        return [false, "Could not connect to $host:$port ($errstr)"];
    }
    stream_set_timeout($socket, 15);

    // Read a (possibly multi-line) reply and check its status code
    $expect = function ($code) use ($socket) {
        $reply = '';
        while (($line = fgets($socket, 515)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        if ((int) substr($reply, 0, 3) !== $code) {
            throw new RuntimeException(trim($reply) ?: 'No response from mail server');
        }
    };
    $send = function ($command, $code) use ($socket, $expect) {
        fwrite($socket, $command . "\r\n");
        $expect($code);
    };

    try {
        $expect(220);
        $send('EHLO localhost', 250);
        if ($port !== 465) {
            $send('STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS negotiation failed');
            }
            $send('EHLO localhost', 250);
        }
        $send('AUTH LOGIN', 334);
        $send(base64_encode($user), 334);
        $send(base64_encode($pass), 235);
        $send('MAIL FROM:<' . $user . '>', 250);
        $send('RCPT TO:<' . $to . '>', 250);
        $send('DATA', 354);

        $boundary = 'b' . bin2hex(random_bytes(12));
        $fromName = '=?UTF-8?B?' . base64_encode(mailFromName()) . '?=';
        $headers = [
            'From: ' . $fromName . ' <' . $user . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . substr(strrchr($user, '@'), 1) . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($textBody))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($htmlBody))
            . "--$boundary--";
        // base64 bodies never start a line with ".", so no dot-stuffing is needed
        $send(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.", 250);
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return [true, ''];
    } catch (RuntimeException $e) {
        fclose($socket);
        error_log('SMTP send failed: ' . $e->getMessage());
        return [false, 'Mail server error: ' . $e->getMessage()];
    }
}
