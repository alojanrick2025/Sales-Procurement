<?php
/**
 * Turns system-wide Two-Factor Authentication on or off (System Information page).
 * The admin must prove they have a working authenticator before either change,
 * so turning it on can never lock them out.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/two_factor.php';
requireAdminLogin();
requirePostWithCsrf();

function tfaSettingsDone($type, $message) {
    $_SESSION['tfa_flash'] = ['type' => $type, 'message' => $message];
    header('Location: /admin/system_info.php#two-factor');
    exit();
}

$conn = getDBConnection();
tfaEnsureSchema($conn);
$userId = (int) $_SESSION['user_id'];
$stored = tfaGetUserSecret($conn, $userId);
$hasSecret = !empty($stored['totp_secret']);
$action = $_POST['action'] ?? '';
$code = $_POST['code'] ?? '';

if ($action === 'enable') {
    // Use the admin's existing authenticator, or the new one shown on the page
    $secret = $hasSecret ? $stored['totp_secret'] : ($_SESSION['tfa_setup_secret'] ?? '');
    if ($secret === '') {
        $conn->close();
        tfaSettingsDone('danger', 'Setup expired. Please scan the QR code again.');
    }
    $step = tfaVerifyCode($secret, $code, $hasSecret ? $stored['totp_last_step'] : null);
    if ($step === false) {
        $conn->close();
        tfaSettingsDone('danger', 'Incorrect code. Two-factor authentication was not turned on.');
    }
    tfaSaveUserSecret($conn, $userId, $secret, $step);
    tfaSetEnabled($conn, true);
    unset($_SESSION['tfa_setup_secret']);
    $conn->close();
    tfaSettingsDone('success', 'Two-factor authentication is now ON. All users will need an authenticator code to log in.');
}

if ($action === 'disable') {
    if ($hasSecret) {
        $step = tfaVerifyCode($stored['totp_secret'], $code, $stored['totp_last_step']);
        if ($step === false) {
            $conn->close();
            tfaSettingsDone('danger', 'Incorrect code. Two-factor authentication is still on.');
        }
        tfaMarkStepUsed($conn, $userId, $step);
    }
    tfaSetEnabled($conn, false);
    $conn->close();
    tfaSettingsDone('success', 'Two-factor authentication is now OFF.');
}

$conn->close();
tfaSettingsDone('danger', 'Unknown action.');
