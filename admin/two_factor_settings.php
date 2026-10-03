<?php
/**
 * Turns system-wide email Two-Factor Authentication on or off (System Information page).
 * The admin must enter a code emailed to them first, which proves email delivery
 * works - so turning 2FA on can never lock everyone out.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/two_factor.php';
requireAdminLogin();

function tfaSettingsDone($type, $message, $openPanel = false) {
    $_SESSION['tfa_flash'] = ['type' => $type, 'message' => $message, 'open' => $openPanel];
    header('Location: /admin/system_info.php#two-factor');
    exit();
}

// A stale page (e.g. opened before a redeploy reset sessions) has an old token:
// send the admin back to a fresh page instead of a bare error
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken()) {
    tfaSettingsDone('danger', 'Your session was refreshed. Please try again.', true);
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'send') {
    $conn = getDBConnection();
    $email = tfaGetUserEmail($conn, $userId);
    $conn->close();
    $result = tfaSendCode('settings', $userId, $email, $_SESSION['user_name'] ?? '');
    $type = ['sent' => 'success', 'already_sent' => 'info'][$result['status']] ?? 'danger';
    tfaSettingsDone($type, $result['message'], true);
}

if ($action === 'enable' || $action === 'disable') {
    $result = tfaCheckCode('settings', $userId, $_POST['code'] ?? '');
    $messages = [
        'invalid' => 'Incorrect code. Please try again.',
        'expired' => 'The code has expired. Click "Send Code" for a new one.',
        'locked'  => 'Too many incorrect codes. Send a new code once this one expires.',
        'none'    => 'Click "Send Code" first.',
    ];
    if ($result !== 'ok') {
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
