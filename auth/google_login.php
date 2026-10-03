<?php
require_once __DIR__ . '/../config.php';

// Already logged in - go straight to the dashboard
if (isLoggedIn()) {
    header('Location: /admin/index.php');
    exit();
}

if (!isGoogleLoginEnabled()) {
    $_SESSION['login_error'] = 'Google Sign-In is not configured.';
    header('Location: /auth/login.php');
    exit();
}

// Random state ties Google's reply to this browser session (prevents CSRF)
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => getGoogleRedirectUri(),
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit();
