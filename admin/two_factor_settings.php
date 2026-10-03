<?php
/**
 * Turns system-wide email Two-Factor Authentication on or off (System Information page).
 * The admin must enter a code emailed to them first, which proves email delivery
 * works - so turning 2FA on can never lock everyone out.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/two_factor.php';
requireAdminLogin();
requirePostWithCsrf();

function tfaSettingsDone($type, $message, $openPanel = false) {
    $_SESSION['tfa_flash'] = ['type' => $type, 'message' => $message, 'open' => $openPanel];
    header('Location: /admin/system_info.php#two-factor');
    exit();
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'send') {
    $conn = getDBConnection();
    $email = tfaGetUserEmail($conn, $userId);
    $conn->close();
    [$ok, $error] = tfaSendCode('settings', $userId, $email, $_SESSION['user_name'] ?? '');
    if (!$ok) {
        tfaSettingsDone('danger', $error, true);
    }
    tfaSettingsDone('success', 'Code sent to ' . tfaMaskEmail($email) . '. It expires in 2 minutes.', true);
}

if ($action === 'enable' || $action === 'disable') {
    $result = tfaCheckCode('settings', $userId, $_POST['code'] ?? '');
    $messages = [
        'invalid' => 'Incorrect code. Please try again.',
        'expired' => 'The code has expired. Click "Send Code" for a new one.',
        'locked'  => 'Too many incorrect codes. Click "Send Code" for a new one.',
        'none'    => 'Click "Send Code" first.',
    ];
    if ($result !== 'ok') {
        if ($result === 'locked') {
            unset($_SESSION['tfa_otp']['settings']);
        }
        tfaSettingsDone('danger', $messages[$result] ?? 'Verification failed.', true);
    }

    $conn = getDBConnection();
    tfaSetEnabled($conn, $action === 'enable');
    $conn->close();
    tfaSettingsDone('success', $action === 'enable'
        ? 'Two-factor authentication is now ON. Users will receive a code by email each time they log in.'
        : 'Two-factor authentication is now OFF.');
}

tfaSettingsDone('danger', 'Unknown action.');
