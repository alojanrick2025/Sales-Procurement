<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/two_factor.php';

if (isLoggedIn()) {
    header('Location: /admin/index.php');
    exit();
}

// Back to the login page, discarding the half-finished login
function tfaAbort($message = '') {
    unset($_SESSION['tfa_pending'], $_SESSION['tfa_enroll_secret']);
    if ($message !== '') {
        $_SESSION['login_error'] = $message;
    }
    header('Location: /auth/login.php');
    exit();
}

$pending = $_SESSION['tfa_pending'] ?? null;
if (!$pending) {
    tfaAbort();
}
if (time() - $pending['started'] > TFA_PENDING_TTL) {
    tfaAbort('Verification timed out. Please log in again.');
}
if (isset($_GET['cancel'])) {
    tfaAbort();
}

$conn = getDBConnection();
tfaEnsureSchema($conn);
$stored = tfaGetUserSecret($conn, $pending['id']);
$hasSecret = !empty($stored['totp_secret']);

// First login since 2FA was turned on: set up the authenticator app now
if (!$hasSecret && empty($_SESSION['tfa_enroll_secret'])) {
    $_SESSION['tfa_enroll_secret'] = tfaGenerateSecret();
}
$secret = $hasSecret ? $stored['totp_secret'] : $_SESSION['tfa_enroll_secret'];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Session expired. Please try again.';
    } else {
        $step = tfaVerifyCode($secret, $_POST['code'] ?? '', $hasSecret ? $stored['totp_last_step'] : null);
        if ($step !== false) {
            if ($hasSecret) {
                tfaMarkStepUsed($conn, $pending['id'], $step);
            } else {
                tfaSaveUserSecret($conn, $pending['id'], $secret, $step);
            }
            $conn->close();
            completeLogin($pending);
        }

        $_SESSION['tfa_pending']['attempts']++;
        if ($_SESSION['tfa_pending']['attempts'] >= TFA_MAX_ATTEMPTS) {
            $conn->close();
            tfaAbort('Too many incorrect codes. Please log in again.');
        }
        $remaining = TFA_MAX_ATTEMPTS - $_SESSION['tfa_pending']['attempts'];
        $error = 'Incorrect code. ' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' left.';
    }
}
$conn->close();

$otpUri = tfaProvisioningUri($secret, $pending['username'], tfaIssuerName());
$systemInfo = getSystemInfo();
$companyName = !empty($systemInfo['company_name']) ? $systemInfo['company_name'] : 'JUSTLY ELECTRICAL SUPPLIES AND SERVICES';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Verification - <?php echo htmlspecialchars($companyName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css"/>
    <style>
        * { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body {
            background: #121C17;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: radial-gradient(circle at 50% 0%, rgba(215, 255, 224, 0.08) 0%, transparent 60%);
            padding: 24px 0;
        }
        .login-card {
            background: #16231D;
            border: 1px solid rgba(215, 255, 224, 0.12);
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.4);
            border-radius: 20px;
            color: white;
        }
        .tfa-icon {
            width: 64px; height: 64px;
            background: rgba(215, 255, 224, 0.08);
            color: #D7FFE0;
            border: 1px solid rgba(215, 255, 224, 0.25);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px auto;
            font-size: 2rem;
        }
        .muted { color: rgba(255, 255, 255, 0.65); font-size: 0.88rem; }
        .code-input {
            background: #1B2A22; border: 1px solid #263B30; color: #fff;
            font-size: 1.6rem; letter-spacing: 0.5em; text-align: center; padding: 10px;
        }
        .code-input:focus {
            background: #1B2A22; color: #fff; border-color: #D7FFE0;
            box-shadow: 0 0 0 0.25rem rgba(215, 255, 224, 0.15);
        }
        .btn-login {
            background: #D7FFE0; color: #16231D; font-weight: 600; border: none;
            padding: 12px; border-radius: 10px;
        }
        .btn-login:hover { background: #fff; color: #16231D; }
        .qr-box { background: #fff; border-radius: 12px; padding: 12px; display: inline-block; }
        .secret-key {
            background: #1B2A22; border: 1px dashed #263B30; border-radius: 8px;
            padding: 8px; font-family: monospace; font-size: 0.85rem; color: #D7FFE0; word-break: break-all;
        }
        .cancel-link { color: rgba(255, 255, 255, 0.55); font-size: 0.85rem; text-decoration: none; }
        .cancel-link:hover { color: #D7FFE0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-3">
                            <div class="tfa-icon"><i class="ph-bold ph-shield-check"></i></div>
                            <h3 class="fw-bold mb-1" style="color: #D7FFE0; font-size: 1.2rem;">Two-Factor Verification</h3>
                            <?php if ($hasSecret): ?>
                                <p class="muted mb-0">Enter the 6-digit code from your authenticator app.</p>
                            <?php else: ?>
                                <p class="muted mb-0">Two-factor authentication is required. Set up your authenticator app to continue.</p>
                            <?php endif; ?>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2" role="alert" style="background: rgba(220, 53, 69, 0.15); border-color: rgba(220, 53, 69, 0.4); color: #ff8787;">
                                <i class="ph-bold ph-warning-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$hasSecret): ?>
                            <ol class="muted ps-3 mb-3">
                                <li>Install <strong>Google Authenticator</strong> or <strong>Microsoft Authenticator</strong> on your phone.</li>
                                <li>Scan this QR code in the app.</li>
                                <li>Enter the 6-digit code it shows.</li>
                            </ol>
                            <div class="text-center mb-2">
                                <div class="qr-box"><div id="tfa_qr"></div></div>
                            </div>
                            <p class="muted small text-center mb-1">Can't scan? Enter this key manually:</p>
                            <div class="secret-key text-center mb-3"><?php echo htmlspecialchars(trim(chunk_split($secret, 4, ' '))); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" autocomplete="off">
                            <?php echo csrfField(); ?>
                            <input type="text" class="form-control code-input mb-3" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required autofocus placeholder="000000" autocomplete="one-time-code">
                            <button type="submit" class="btn btn-login w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="ph-bold ph-check-circle"></i> Verify
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="?cancel=1" class="cancel-link"><i class="ph-bold ph-arrow-left me-1"></i>Back to login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$hasSecret): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // QR is drawn in the browser so the secret is never sent to a third-party service
        new QRCode(document.getElementById('tfa_qr'), {
            text: <?php echo json_encode($otpUri, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES); ?>,
            width: 180,
            height: 180,
            correctLevel: QRCode.CorrectLevel.M
        });
    </script>
    <?php endif; ?>
</body>
</html>
