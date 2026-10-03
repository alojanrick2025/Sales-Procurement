<?php
/**
 * Two-Factor Authentication (TOTP, RFC 6238)
 * Works with Google Authenticator, Microsoft Authenticator, Authy, etc.
 */

require_once __DIR__ . '/../config.php';

define('TFA_PERIOD', 30);          // seconds per code
define('TFA_DIGITS', 6);
define('TFA_PENDING_TTL', 300);    // seconds allowed to enter the code after password login
define('TFA_MAX_ATTEMPTS', 5);

// Add the 2FA columns to `users` on first use (no manual migration needed)
function tfaEnsureSchema($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'totp_secret'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL, ADD COLUMN totp_last_step BIGINT NULL");
    }
    $done = true;
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

function tfaGenerateSecret() {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bytes = random_bytes(20);
    $bits = '';
    foreach (str_split($bytes) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $secret = '';
    foreach (str_split($bits, 5) as $chunk) {
        $secret .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $secret;
}

function tfaBase32Decode($secret) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret));
    $bits = '';
    foreach (str_split($secret) as $char) {
        $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $bytes .= chr(bindec($chunk));
        }
    }
    return $bytes;
}

function tfaCodeAt($secret, $step) {
    $key = tfaBase32Decode($secret);
    $counter = pack('N2', 0, $step);
    $hash = hash_hmac('sha1', $counter, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $value = ((ord($hash[$offset]) & 0x7F) << 24)
        | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8)
        | ord($hash[$offset + 3]);
    return str_pad((string) ($value % (10 ** TFA_DIGITS)), TFA_DIGITS, '0', STR_PAD_LEFT);
}

/**
 * Check a code, allowing one step of clock drift either way.
 * Returns the matched time step, or false. Steps at or before $lastStep are
 * rejected so a code cannot be reused.
 */
function tfaVerifyCode($secret, $code, $lastStep = null) {
    $code = preg_replace('/\s+/', '', (string) $code);
    if ($secret === '' || !preg_match('/^\d{' . TFA_DIGITS . '}$/', $code)) {
        return false;
    }
    $current = intdiv(time(), TFA_PERIOD);
    for ($drift = -1; $drift <= 1; $drift++) {
        $step = $current + $drift;
        if ($lastStep !== null && $step <= $lastStep) {
            continue;
        }
        if (hash_equals(tfaCodeAt($secret, $step), $code)) {
            return $step;
        }
    }
    return false;
}

// otpauth:// link encoded in the QR code that authenticator apps scan
function tfaProvisioningUri($secret, $accountName, $issuer) {
    $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);
    return 'otpauth://totp/' . $label . '?' . http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'period' => TFA_PERIOD,
        'digits' => TFA_DIGITS,
    ]);
}

function tfaIssuerName() {
    $info = getSystemInfo();
    return !empty($info['short_name']) ? $info['short_name'] : 'Sales and Procurement';
}

function tfaGetUserSecret($conn, $userId) {
    $stmt = $conn->prepare("SELECT totp_secret, totp_last_step FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: ['totp_secret' => null, 'totp_last_step' => null];
}

function tfaSaveUserSecret($conn, $userId, $secret, $step) {
    $stmt = $conn->prepare("UPDATE users SET totp_secret = ?, totp_last_step = ? WHERE id = ?");
    $stmt->bind_param("sii", $secret, $step, $userId);
    $stmt->execute();
    $stmt->close();
}

function tfaMarkStepUsed($conn, $userId, $step) {
    $stmt = $conn->prepare("UPDATE users SET totp_last_step = ? WHERE id = ?");
    $stmt->bind_param("ii", $step, $userId);
    $stmt->execute();
    $stmt->close();
}

// Final step of any login: create the authenticated session
function completeLogin($user) {
    unset($_SESSION['tfa_pending'], $_SESSION['tfa_enroll_secret']);
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
 * Logs in directly, or hands off to the code prompt when 2FA is on.
 */
function beginLogin($user) {
    $conn = getDBConnection();
    tfaEnsureSchema($conn);
    $enabled = tfaIsEnabled($conn);
    $conn->close();

    if (!$enabled) {
        completeLogin($user);
    }

    session_regenerate_id(true);
    $_SESSION['tfa_pending'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'user_type' => $user['user_type'],
        'started'   => time(),
        'attempts'  => 0,
    ];
    header('Location: /auth/two_factor.php');
    exit();
}
