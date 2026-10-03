<?php
/**
 * Two-Factor Authentication by email OTP.
 * A 6-digit code is emailed to the user's account email and expires after 2 minutes.
 * Codes are kept (hashed) in the server-side session, so no database changes are needed.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/mailer.php';

define('TFA_CODE_TTL', 120);       // seconds a code stays valid
define('TFA_RESEND_COOLDOWN', 30); // seconds between "resend code" requests
define('TFA_MAX_SENDS', 5);        // codes per login attempt
define('TFA_MAX_ATTEMPTS', 5);     // wrong codes per issued code
define('TFA_PENDING_TTL', 600);    // seconds to finish the whole verification step

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

/**
 * Generate a code for $purpose ('login' or 'settings'), email it, and remember its hash.
 * Returns [true, ''] or [false, 'reason'].
 */
function tfaSendCode($purpose, $userId, $email, $fullName) {
    $existing = $_SESSION['tfa_otp'][$purpose] ?? null;
    $sameUser = $existing && $existing['user_id'] === $userId;
    if ($sameUser && time() - $existing['sent_at'] < TFA_RESEND_COOLDOWN) {
        $wait = TFA_RESEND_COOLDOWN - (time() - $existing['sent_at']);
        return [false, "Please wait $wait seconds before requesting a new code."];
    }
    $sends = $sameUser ? $existing['sends'] : 0;
    if ($sends >= TFA_MAX_SENDS) {
        return [false, 'Too many codes requested. Please log in again.'];
    }
    if ($email === '') {
        return [false, 'Your account has no email address. Please contact the administrator.'];
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
        return [false, 'Could not send the code (' . $error . ').'];
    }

    $_SESSION['tfa_otp'][$purpose] = [
        'user_id'  => $userId,
        'hash'     => hash('sha256', $code),
        'expires'  => time() + TFA_CODE_TTL,
        'sent_at'  => time(),
        'sends'    => $sends + 1,
        'attempts' => 0,
        'email'    => $email,
    ];
    return [true, ''];
}

/**
 * Check a code. Returns 'ok', 'invalid', 'expired', 'locked' or 'none'.
 * A code works only once.
 */
function tfaCheckCode($purpose, $userId, $code) {
    $otp = $_SESSION['tfa_otp'][$purpose] ?? null;
    if (!$otp || $otp['user_id'] !== $userId) {
        return 'none';
    }
    if ($otp['attempts'] >= TFA_MAX_ATTEMPTS) {
        return 'locked';
    }
    if (time() > $otp['expires']) {
        return 'expired';
    }
    $code = preg_replace('/\D/', '', (string) $code);
    if (strlen($code) === 6 && hash_equals($otp['hash'], hash('sha256', $code))) {
        unset($_SESSION['tfa_otp'][$purpose]);
        return 'ok';
    }
    $_SESSION['tfa_otp'][$purpose]['attempts']++;
    return $_SESSION['tfa_otp'][$purpose]['attempts'] >= TFA_MAX_ATTEMPTS ? 'locked' : 'invalid';
}

function tfaCodeInfo($purpose) {
    return $_SESSION['tfa_otp'][$purpose] ?? null;
}

// Final step of any login: create the authenticated session
function completeLogin($user) {
    unset($_SESSION['tfa_pending'], $_SESSION['tfa_otp']['login']);
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
 * Logs in directly, or emails a code and asks for it when 2FA is on.
 */
function beginLogin($user) {
    $conn = getDBConnection();
    $enabled = tfaIsEnabled($conn);
    $email = $enabled ? tfaGetUserEmail($conn, $user['id']) : '';
    $conn->close();

    if (!$enabled) {
        completeLogin($user);
    }

    session_regenerate_id(true);
    unset($_SESSION['tfa_otp']['login']);
    $_SESSION['tfa_pending'] = [
        'id'        => (int) $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'user_type' => $user['user_type'],
        'email'     => $email,
        'started'   => time(),
    ];

    [$ok, $error] = tfaSendCode('login', (int) $user['id'], $email, $user['full_name']);
    if (!$ok) {
        unset($_SESSION['tfa_pending']);
        $_SESSION['login_error'] = $error;
        header('Location: /auth/login.php');
        exit();
    }
    header('Location: /auth/two_factor.php');
    exit();
}
