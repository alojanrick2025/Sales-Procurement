<?php
require_once __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $conn = getDBConnection();
        
        // Check user in unified users table
        $stmt = $conn->prepare("SELECT id, username, password, full_name, user_type, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is inactive
            if ($user['status'] === 'inactive') {
                $error = 'Your account is inactive. Please contact administrator.';
            } elseif (password_verify($password, $user['password'])) {
                // Set unified session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Redirect to dashboard
                header('Location: /admin/index.php');
                $stmt->close();
                $conn->close();
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        
        $stmt->close();
        $conn->close();
    } else {
        $error = 'Please fill in all fields';
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /admin/index.php');
    exit();
}

$systemInfo = getSystemInfo();
$sysLogo = !empty($systemInfo['logo']) ? '/' . ltrim($systemInfo['logo'], '/') : '';
$companyName = !empty($systemInfo['company_name']) ? $systemInfo['company_name'] : 'JUSTLY ELECTRICAL SUPPLIES AND SERVICES';
$sysName = !empty($systemInfo['name']) ? $systemInfo['name'] : 'Sales and Procurement Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($companyName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Phosphor Icons -->
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css"/>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css"/>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --forest-charcoal: #16231D;
            --forest-dark: #121C17;
            --forest-input: #1B2A22;
            --ghost-green: #D7FFE0;
            --ghost-green-glow: rgba(215, 255, 224, 0.2);
        }
        * {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background: #121C17;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: radial-gradient(circle at 50% 0%, rgba(215, 255, 224, 0.08) 0%, transparent 60%);
        }
        .login-card {
            background: #16231D;
            border: 1px solid rgba(215, 255, 224, 0.12);
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.4), 0 0 30px rgba(215, 255, 224, 0.04);
            border-radius: 20px;
            color: white;
        }
        .login-logo-wrapper {
            margin: 0 auto 18px auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-logo-img {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(215, 255, 224, 0.4);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45), 0 0 25px rgba(215, 255, 224, 0.2);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .login-logo-img:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5), 0 0 30px rgba(215, 255, 224, 0.35);
        }
        .login-brand-icon {
            width: 72px;
            height: 72px;
            background: rgba(215, 255, 224, 0.08);
            color: var(--ghost-green);
            border: 1px solid rgba(215, 255, 224, 0.25);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px auto;
            font-size: 2.2rem;
            box-shadow: 0 8px 24px var(--ghost-green-glow);
        }
        .form-label {
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.88rem;
            font-weight: 500;
        }
        .input-group-text {
            background: #1B2A22;
            border-color: #263B30;
            color: var(--ghost-green);
            font-size: 1.15rem;
        }
        .form-control {
            background: #1B2A22;
            border-color: #263B30;
            color: #ffffff;
            padding: 12px 14px;
        }
        .form-control:focus {
            background: #1B2A22;
            border-color: var(--ghost-green);
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(215, 255, 224, 0.15);
        }
        .btn-login {
            background: var(--ghost-green);
            color: #16231D;
            font-weight: 600;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        .btn-login:hover {
            background: #ffffff;
            color: #16231D;
            box-shadow: 0 6px 20px rgba(215, 255, 224, 0.3);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card login-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <?php if (!empty($sysLogo) && (file_exists(__DIR__ . '/..' . $sysLogo) || file_exists(__DIR__ . '/../' . ltrim($sysLogo, '/')))): ?>
                                <div class="login-logo-wrapper">
                                    <img src="<?php echo htmlspecialchars($sysLogo); ?>" alt="Logo" class="login-logo-img">
                                </div>
                            <?php else: ?>
                                <div class="login-brand-icon">
                                    <i class="ph-bold ph-package"></i>
                                </div>
                            <?php endif; ?>
                            <h3 class="fw-bold mb-1" style="color: var(--ghost-green); letter-spacing: -0.3px; font-size: 1.2rem; line-height: 1.35;"><?php echo htmlspecialchars($companyName); ?></h3>
                            <p class="small mb-0 mt-1" style="color: rgba(255, 255, 255, 0.65);"><?php echo htmlspecialchars($sysName); ?></p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert" style="background: rgba(220, 53, 69, 0.15); border-color: rgba(220, 53, 69, 0.4); color: #ff8787;">
                                <i class="ph-bold ph-warning-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ph-bold ph-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required autofocus placeholder="Enter your username">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ph-bold ph-lock-key"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-login w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="ph-bold ph-sign-in"></i> Login to System
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

