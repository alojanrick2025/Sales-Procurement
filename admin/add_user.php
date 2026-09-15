<?php
$pageTitle = 'Create New User';
require_once __DIR__ . '/../config.php';
requireAdminLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate required fields
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and confirmation do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (!empty($phone) && !preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = 'Phone number must be exactly 11 digits and start with 09 (e.g., 09171234567)';
    } else {
        // Check if username already exists
        $checkUsernameStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkUsernameStmt->bind_param("s", $username);
        $checkUsernameStmt->execute();
        $usernameResult = $checkUsernameStmt->get_result();
        
        // Check if email already exists
        $checkEmailStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmailStmt->bind_param("s", $email);
        $checkEmailStmt->execute();
        $emailResult = $checkEmailStmt->get_result();
        
        if ($usernameResult->num_rows > 0) {
            $error = 'Username already exists';
        } elseif ($emailResult->num_rows > 0) {
            $error = 'Email already exists';
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Get user type
            $user_type = $_POST['user_type'] ?? 'admin';
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, user_type, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $email, $hashedPassword, $full_name, $phone, $user_type, $status);
            
            if ($stmt->execute()) {
                $success = 'User added successfully!';
                $_POST = [];
                $username = $email = $full_name = $phone = '';
                $status = 'active';
                $user_type = 'admin';
            } else {
                $error = 'Error adding user: ' . $conn->error;
            }
            $stmt->close();
        }
        
        $checkUsernameStmt->close();
        $checkEmailStmt->close();
    }
}

$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-plus-circle"></i> Create New User</h2>
    <a href="/admin/user_list.php" class="btn btn-secondary">
        <i class="ph-bold ph-arrow-left"></i> Back to List
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" id="userForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    <small class="text-muted">Unique username for login</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="password" id="password" required minlength="6">
                    <small class="text-muted">Minimum 6 characters</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" required minlength="6">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-control" name="phone" id="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="09171234567 (optional)" maxlength="11" pattern="09[0-9]{9}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                    <div class="form-text text-muted small">Must be 11 digits starting with 09 (e.g. 09171234567)</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">User Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="user_type" required>
                        <option value="admin" <?php echo ($_POST['user_type'] ?? 'admin') == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="active" <?php echo ($_POST['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($_POST['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="/admin/user_list.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check-circle"></i> Create User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('confirm_password').addEventListener('input', function() {
    var password = document.getElementById('password').value;
    var confirmPassword = this.value;
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('password').addEventListener('input', function() {
    var confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        confirmPassword.dispatchEvent(new Event('input'));
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
