<?php
/**
 * Two-Factor Authentication by email OTP.
 * A 6-digit code is emailed to the user's account email and expires after 2 minutes.
 * While a code is still valid no new email is sent - a new code can be requested
 * only after it expires. Codes live in the `two_factor_codes` table, so this holds
 * across browsers, repeated logins and server restarts.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/mailer.php';

define('TFA_CODE_TTL', 120);       // seconds a code stays valid (also the resend wait)
define('TFA_MAX_ATTEMPTS', 5);     // wrong codes allowed per issued code
define('TFA_PENDING_TTL', 600);    // seconds to finish the whole verification step

// Create the codes table on first use (no manual migration needed)
function tfaEnsureSchema($conn) {
    static $done = false;
    if (!$done) {
        $conn->query("CREATE TABLE IF NOT EXISTS two_factor_codes (
            user_id INT NOT NULL,
            purpose VARCHAR(20) NOT NULL,
            code_hash CHAR(64) NOT NULL,
            expires_at BIGINT NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id, purpose)
        )");
        $done = true;
    }
}

// System-wide switch stored in system_info
function tfaIsEnabled($conn) {
    $stmt = $conn->prepare("SELECT meta_value FROM system_info WHERE meta_field = 'two_factor_enabled'");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row['meta_value'] ?? '0') === '1';
}

function tfaSetEnabled($conn, $enabled) {
    $value = $enabled ? '1' : '0';
    $field = 'two_factor_enabled';
    $stmt = $conn->prepare("SELECT id FROM system_info WHERE meta_field = ?");
    $stmt->bind_param("s", $field);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    $sql = $exists
        ? "UPDATE system_info SET meta_value = ? WHERE meta_field = ?"
        : "INSERT INTO system_info (meta_value, meta_field) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $value, $field);
    $stmt->execute();
    $stmt->close();
}

function tfaGetUserEmail($conn, $userId) {
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['email'] ?? '';
}

// j******t@gmail.com - shown on screen so users know where the code went
function tfaMaskEmail($email) {
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $visible = strlen($local) <= 2 ? substr($local, 0, 1) : $local[0] . str_repeat('*', strlen($local) - 2) . substr($local, -1);
    return $visible . '@' . $domain;
}

// 95 -> "1:35"
function tfaFormatWait($seconds) {
    return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
}

function tfaHashCode($userId, $purpose, $code) {
    return hash('sha256', $userId . '|' . $purpose . '|' . $code);
}

/**
 * The current, unexpired code for this user and purpose, or null.
 * Returns ['expires_at' => int, 'attempts' => int, 'seconds_left' => int].
 */
function tfaCodeInfo($purpose, $userId) {
    $conn = getDBConnection();
    tfaEnsureSchema($conn);
    $stmt = $conn->prepare("SELECT expires_at, attempts FROM two_factor_codes WHERE user_id = ? AND purpose = ?");
    $stmt->bind_param("is", $userId, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$row || (int) $row['expires_at'] <= time()) {
        return null;
    }
    return [
        'expires_at'   => (int) $row['expires_at'],
        'attempts'     => (int) $row['attempts'],
        'seconds_left' => (int) $row['expires_at'] - time(),
    ];
}

/**
 * Email a new code for $purpose ('login' or 'settings') - unless one is still valid.
 * Returns ['status' => 'sent'|'already_sent'|'error', 'message' => string].
 */
function tfaSendCode($purpose, $userId, $email, $fullName) {
    $current = tfaCodeInfo($purpose, $userId);
    if ($current) {
        $wait = tfaFormatWait($current['seconds_left']);
        if ($current['attempts'] >= TFA_MAX_ATTEMPTS) {
            return ['status' => 'error', 'message' => "Too many incorrect codes. Please wait $wait before trying again."];
        }
        return ['status' => 'already_sent', 'message' => "A code was already sent to " . tfaMaskEmail($email) . ". You can request a new code in $wait."];
    }
    if ($email === '') {
        return ['status' => 'error', 'message' => 'Your account has no email address. Please contact the administrator.'];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $minutes = intdiv(TFA_CODE_TTL, 60);
    $company = getSystemInfo()['company_name'] ?? 'Sales and Procurement Management System';
    $subject = "Your verification code: $code";
    $text = "Hi $fullName,\n\nYour verification code is: $code\n\nIt expires in $minutes minutes. "
        . "If you did not try to log in, change your password immediately.\n\n$company";
    $html = '<div style="font-family:Arial,sans-serif;max-width:420px;margin:auto;padding:24px;border:1px solid #e3e8e5;border-radius:12px">'
        . '<h2 style="color:#16231D;margin:0 0 8px">Verification Code</h2>'
        . '<p style="color:#444">Hi ' . htmlspecialchars($fullName) . ', use this code to finish logging in:</p>'
        . '<div style="font-size:32px;font-weight:bold;letter-spacing:8px;text-align:center;background:#F0FFF3;color:#16231D;padding:16px;border-radius:8px">' . $code . '</div>'
        . '<p style="color:#666;font-size:13px">This code expires in <b>' . $minutes . ' minutes</b>. If you did not try to log in, change your password immediately.</p>'
        . '<p style="color:#999;font-size:12px;margin-top:24px">' . htmlspecialchars($company) . '</p></div>';

    [$ok, $error] = sendMail($email, $subject, $text, $html);
    if (!$ok) {
        return ['status' => 'error', 'message' => 'Could not send the code (' . $error . ').'];
    }

    $conn = getDBConnection();
    tfaEnsureSchema($conn);
    $hash = tfaHashCode($userId, $purpose, $code);
    $expires = time() + TFA_CODE_TTL;
    $stmt = $conn->prepare("REPLACE INTO two_factor_codes (user_id, purpose, code_hash, expires_at, attempts) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("issi", $userId, $purpose, $hash, $expires);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    return ['status' => 'sent', 'message' => 'Code sent to ' . tfaMaskEmail($email) . '. It expires in ' . $minutes . ' minutes.'];
}

/**
 * Check a code. Returns 'ok', 'invalid', 'expired', 'locked' or 'none'.
 * A code works only once.
 */
function tfaCheckCode($purpose, $userId, $code) {
    $conn = getDBConnection();
    tfaEnsureSchema($conn);
    $stmt = $conn->prepare("SELECT code_hash, expires_at, attempts FROM two_factor_codes WHERE user_id = ? AND purpose = ?");
    $stmt->bind_param("is", $userId, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $result = 'none';
    if ($row) {
        $code = preg_replace('/\D/', '', (string) $code);
        if ((int) $row['attempts'] >= TFA_MAX_ATTEMPTS) {
            $result = 'locked';
        } elseif ((int) $row['expires_at'] <= time()) {
            $result = 'expired';
        } elseif (strlen($code) === 6 && hash_equals($row['code_hash'], tfaHashCode($userId, $purpose, $code))) {
            $stmt = $conn->prepare("DELETE FROM two_factor_codes WHERE user_id = ? AND purpose = ?");
            $stmt->bind_param("is", $userId, $purpose);
            $stmt->execute();
            $stmt->close();
            $result = 'ok';
        } else {
            $stmt = $conn->prepare("UPDATE two_factor_codes SET attempts = attempts + 1 WHERE user_id = ? AND purpose = ?");
            $stmt->bind_param("is", $userId, $purpose);
            $stmt->execute();
            $stmt->close();
            $result = (int) $row['attempts'] + 1 >= TFA_MAX_ATTEMPTS ? 'locked' : 'invalid';
        }
    }
    $conn->close();
    return $result;
}

// Final step of any login: create the authenticated session
function completeLogin($user) {
    unset($_SESSION['tfa_pending'], $_SESSION['tfa_notice']);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_type'] = $user['user_type'];
    header('Location: /admin/index.php');
    exit();
}

/**
 * Called after the password (or Google) check succeeds.
 * Logs in directly, or emails a code (if none is still valid) and asks for it when 2FA is on.
 */
function beginLogin($user) {
    $conn = getDBConnection();
    $enabled = tfaIsEnabled($conn);
    $email = $enabled ? tfaGetUserEmail($conn, $user['id']) : '';
    $conn->close();

    if (!$enabled) {
        completeLogin($user);
    }

    $result = tfaSendCode('login', (int) $user['id'], $email, $user['full_name']);
    if ($result['status'] === 'error') {
        $_SESSION['login_error'] = $result['message'];
        header('Location: /auth/login.php');
        exit();
    }

    session_regenerate_id(true);
    $_SESSION['tfa_pending'] = [
        'id'        => (int) $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'user_type' => $user['user_type'],
        'email'     => $email,
        'started'   => time(),
    ];
    $_SESSION['tfa_notice'] = $result['status'] === 'already_sent' ? $result['message'] : '';
    header('Location: /auth/two_factor.php');
    exit();
}
