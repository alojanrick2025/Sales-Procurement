<?php
require_once __DIR__ . '/../config.php';

// Send the user back to the login page with a message
function googleLoginFail($message) {
    $_SESSION['login_error'] = $message;
    header('Location: /auth/login.php');
    exit();
}

if (!isGoogleLoginEnabled()) {
    googleLoginFail('Google Sign-In is not configured.');
}

// User cancelled on Google's screen, or Google returned an error
if (isset($_GET['error'])) {
    googleLoginFail('Google Sign-In was cancelled.');
}

// Verify the state we generated in google_login.php (single use)
$expectedState = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);
$state = $_GET['state'] ?? '';
if ($expectedState === '' || !is_string($state) || !hash_equals($expectedState, $state)) {
    googleLoginFail('Google Sign-In session expired. Please try again.');
}

$code = $_GET['code'] ?? '';
if (!is_string($code) || $code === '') {
    googleLoginFail('Google Sign-In failed. Please try again.');
}

if (!function_exists('curl_init')) {
    googleLoginFail('Google Sign-In requires the PHP curl extension.');
}

// Exchange the authorization code for tokens (server-to-server, uses the client secret)
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => getGoogleRedirectUri(),
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokens = $response ? json_decode($response, true) : null;
if ($httpCode !== 200 || empty($tokens['id_token'])) {
    error_log('Google token exchange failed: HTTP ' . $httpCode . ' ' . $response);
    googleLoginFail('Google Sign-In failed. Please try again.');
}

// The ID token came directly from Google over TLS, so its signature need not be
// re-verified, but the claims must still be checked.
$parts = explode('.', $tokens['id_token']);
$claims = count($parts) === 3
    ? json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true)
    : null;

$validIssuer = in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true);
if (!$claims || !$validIssuer || ($claims['aud'] ?? '') !== GOOGLE_CLIENT_ID || ($claims['exp'] ?? 0) < time()) {
    googleLoginFail('Google Sign-In failed. Please try again.');
}

$email = $claims['email'] ?? '';
if ($email === '' || empty($claims['email_verified'])) {
    googleLoginFail('Your Google account email is not verified.');
}

// Only existing users may sign in - match on email, never auto-create accounts
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id, username, full_name, user_type, status FROM users WHERE LOWER(email) = LOWER(?)");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->num_rows === 1 ? $result->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$user) {
    googleLoginFail('No account is registered for ' . $email . '. Please contact the administrator.');
}

if ($user['status'] === 'inactive') {
    googleLoginFail('Your account is inactive. Please contact administrator.');
}

// Same session variables as the username/password login
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_username'] = $user['username'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['user_type'] = $user['user_type'];

header('Location: /admin/index.php');
exit();
