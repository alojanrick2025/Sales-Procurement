<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/two_factor.php';

if (isLoggedIn()) {
    header('Location: /admin/index.php');
    exit();
}

// Back to the login page, discarding the half-finished login
function tfaAbort($message = '') {
    unset($_SESSION['tfa_pending'], $_SESSION['tfa_notice']);
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

$error = '';
// Set by beginLogin() when a still-valid code was reused instead of sending another email
$notice = $_SESSION['tfa_notice'] ?? '';
unset($_SESSION['tfa_notice']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notice = '';
    if (!validateCsrfToken()) {
        $error = 'Session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'resend') {
        $result = tfaSendCode('login', $pending['id'], $pending['email'], $pending['full_name']);
        if ($result['status'] === 'sent') {
            $notice = 'A new code has been sent.';
        } elseif ($result['status'] === 'already_sent') {
            $notice = $result['message'];
        } else {
            $error = $result['message'];
        }
    } else {
        switch (tfaCheckCode('login', $pending['id'], $_POST['code'] ?? '')) {
            case 'ok':
                completeLogin($pending);
                // no break: completeLogin() exits
            case 'expired':
                $error = 'This code has expired. Click "Resend code" to get a new one.';
                break;
            case 'locked':
                tfaAbort('Too many incorrect codes. Please log in again.');
                // no break: tfaAbort() exits
            case 'none':
                $error = 'No active code. Click "Resend code" to get one.';
                break;
            default:
                $left = TFA_MAX_ATTEMPTS - (tfaCodeInfo('login', $pending['id'])['attempts'] ?? 0);
                $error = 'Incorrect code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.';
        }
    }
}

// A new code can be requested only after the current one expires
$codeInfo = tfaCodeInfo('login', $pending['id']);
$secondsLeft = $codeInfo ? $codeInfo['seconds_left'] : 0;
$resendWait = $secondsLeft;
$systemInfo = getSystemInfo();
$companyName = !empty($systemInfo['company_name']) ? $systemInfo['company_name'] : 'JUSTLY ELECTRICAL SUPPLIES AND SERVICES';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo htmlspecialchars($companyName); ?></title>
    <?php echo faviconTag(); ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php echo iconFontTags(); ?>
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
        .email-highlight { color: #D7FFE0; font-weight: 600; }
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
        .btn-resend {
            background: transparent; border: 1px solid #263B30; color: rgba(255, 255, 255, 0.8);
            border-radius: 10px; font-size: 0.88rem; padding: 8px 14px;
        }
        .btn-resend:hover:not(:disabled) { border-color: #D7FFE0; color: #D7FFE0; }
        .btn-resend:disabled { opacity: 0.5; }
        .timer { font-size: 0.88rem; color: #D7FFE0; }
        .timer.expired { color: #ff8787; }
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
                            <div class="tfa-icon"><i class="ph-bold ph-envelope-simple-open"></i></div>
                            <h3 class="fw-bold mb-1" style="color: #D7FFE0; font-size: 1.2rem;">Check Your Email</h3>
                            <p class="muted mb-0">We sent a 6-digit code to<br><span class="email-highlight"><?php echo htmlspecialchars(tfaMaskEmail($pending['email'])); ?></span></p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2" role="alert" style="background: rgba(220, 53, 69, 0.15); border-color: rgba(220, 53, 69, 0.4); color: #ff8787;">
                                <i class="ph-bold ph-warning-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($notice): ?>
                            <div class="alert alert-success py-2" role="alert" style="background: rgba(25, 135, 84, 0.15); border-color: rgba(25, 135, 84, 0.4); color: #8ce0b0;">
                                <i class="ph-bold ph-check-circle me-1"></i> <?php echo htmlspecialchars($notice); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" autocomplete="off">
                            <?php echo csrfField(); ?>
                            <input type="text" class="form-control code-input mb-2" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required autofocus placeholder="000000" autocomplete="one-time-code">
                            <div class="text-center mb-3">
                                <span class="timer" id="tfa_timer" data-seconds="<?php echo (int) $secondsLeft; ?>"></span>
                            </div>
                            <button type="submit" class="btn btn-login w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="ph-bold ph-check-circle"></i> Verify
                            </button>
                        </form>

                        <form method="POST" action="" class="text-center mt-3">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="resend">
                            <span class="muted d-block mb-2">Didn't get it? Check your Spam folder.</span>
                            <button type="submit" class="btn btn-resend" id="tfa_resend" data-wait="<?php echo (int) $resendWait; ?>">
                                <i class="ph-bold ph-arrow-clockwise me-1"></i><span>Resend code</span>
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

    <script>
        // Countdown until the code expires, and cooldown on the resend button
        (function () {
            const timer = document.getElementById('tfa_timer');
            const resend = document.getElementById('tfa_resend');
            const resendLabel = resend.querySelector('span');
            let left = parseInt(timer.dataset.seconds, 10);
            let wait = parseInt(resend.dataset.wait, 10);
            function tick() {
                if (left > 0) {
                    const m = Math.floor(left / 60), s = String(left % 60).padStart(2, '0');
                    timer.textContent = 'Code expires in ' + m + ':' + s;
                } else {
                    timer.textContent = 'Code expired. Request a new one below.';
                    timer.classList.add('expired');
                }
                resend.disabled = wait > 0;
                resendLabel.textContent = wait > 0
                    ? 'Resend code in ' + Math.floor(wait / 60) + ':' + String(wait % 60).padStart(2, '0')
                    : 'Resend code';
                left--; wait--;
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
