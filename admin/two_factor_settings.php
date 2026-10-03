<?php
/**
 * Turns system-wide email Two-Factor Authentication on or off (System Information page).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/two_factor.php';
requireAdminLogin();

function tfaSettingsDone($type, $message) {
    $_SESSION['tfa_flash'] = ['type' => $type, 'message' => $message];
    header('Location: /admin/system_info.php#two-factor');
    exit();
}

// A stale page (e.g. opened before a redeploy reset sessions) has an old token:
// send the admin back to a fresh page instead of a bare error
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken()) {
    tfaSettingsDone('danger', 'Your session was refreshed. Please try again.');
}

$enable = ($_POST['two_factor_enabled'] ?? '0') === '1';
$conn = getDBConnection();

// Refuse to turn on 2FA when codes could not be delivered - it would lock everyone out
if ($enable) {
    if (!isMailConfigured()) {
        $conn->close();
        tfaSettingsDone('danger', 'Email sending is not set up, so 2FA was not turned on. See GMAIL-OTP-SETUP.md.');
    }
    if (tfaGetUserEmail($conn, (int) $_SESSION['user_id']) === '') {
        $conn->close();
        tfaSettingsDone('danger', 'Your account has no email address, so 2FA was not turned on. Add one in My Profile first.');
    }
}

tfaSetEnabled($conn, $enable);
$conn->close();
tfaSettingsDone('success', $enable
    ? 'Two-factor authentication is now ON. Users will receive a code by email each time they log in.'
    : 'Two-factor authentication is now OFF.');
