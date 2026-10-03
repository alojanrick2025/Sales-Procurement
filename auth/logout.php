<?php
require_once __DIR__ . '/../config.php';

// Only the Logout button (POST + token) logs out. A plain visit - e.g. a browser
// preloading the link, or a link on another website - just goes back.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken()) {
    header('Location: ' . (isLoggedIn() ? '/admin/index.php' : '/auth/login.php'));
    exit();
}

$_SESSION = [];
session_destroy();
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires'  => time() - 3600,
    'path'     => $params['path'],
    'secure'   => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'],
]);
header('Location: /auth/login.php');
exit();
