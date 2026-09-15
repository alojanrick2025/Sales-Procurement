<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$error = '';
$success = '';

// Get current user data
$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: /auth/login.php');
    exit();
}

$user_id = $currentUser['id'];

// Get full user data
$stmt = $conn->prepare("SELECT id, username, email, password, full_name, phone, user_type, status, avatar, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $changePassword = isset($_POST['change_password']) && $_POST['change_password'] === '1';
    $currentPassword = $_POST['current_password'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validate required fields
    if (empty($username) || empty($email) || empty($full_name)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif ($changePassword) {
        if (empty($currentPassword)) {
            $error = 'Please enter your current password';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = 'Current password is incorrect';
        } elseif (empty($password) || strlen($password) < 6) {
            $error = 'New password must be at least 6 characters long';
        } elseif ($password !== $confirmPassword) {
            $error = 'New password and confirmation do not match';
        }
    } elseif (!empty($phone) && !preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = 'Phone number must be exactly 11 digits and start with 09 (e.g., 09171234567)';
    } else {
        // Check if username already exists (excluding current user)
        $checkUsernameStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkUsernameStmt->bind_param("si", $username, $user_id);
        $checkUsernameStmt->execute();
        $usernameResult = $checkUsernameStmt->get_result();
        
        // Check if email already exists (excluding current user)
        $checkEmailStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmailStmt->bind_param("si", $email, $user_id);
        $checkEmailStmt->execute();
        $emailResult = $checkEmailStmt->get_result();
        
        if ($usernameResult->num_rows > 0) {
            $error = 'Username already exists';
        } elseif ($emailResult->num_rows > 0) {
            $error = 'Email already exists';
        } else {
            $avatarPath = null;
            
            // Handle avatar upload
            if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1') {
                if (!empty($user['avatar'])) {
                    $uploadDir = __DIR__ . '/../uploads/avatars/';
                    $oldFilename = basename($user['avatar']);
                    $oldFile = $uploadDir . $oldFilename;
                    if (!empty($oldFilename) && file_exists($oldFile) && is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }
                $avatarPath = '';
            } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                // 1. Validate file size
                if ($file['size'] > $maxSize) {
                    $error = 'Avatar file size must not exceed 2MB.';
                }
                // 2. Validate extension whitelist
                elseif (!in_array($extension, $allowedExtensions, true)) {
                    $error = 'Invalid avatar file format. Allowed extensions: JPG, JPEG, PNG, GIF, WEBP.';
                } else {
                    // 3. Server-side MIME verification using Fileinfo
                    $detectedMime = '';
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $detectedMime = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);
                    } elseif (function_exists('mime_content_type')) {
                        $detectedMime = mime_content_type($file['tmp_name']);
                    }
                    
                    // 4. Verify image dimensions and header integrity
                    $imageInfo = @getimagesize($file['tmp_name']);
                    
                    if (!in_array($detectedMime, $allowedMimeTypes, true) || $imageInfo === false) {
                        $error = 'Invalid image file. The uploaded file content does not match allowed image formats.';
                    } else {
                        $uploadDir = __DIR__ . '/../uploads/avatars/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $filename = 'user_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                        $targetPath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            // Delete old avatar if exists (sanitized to prevent directory traversal)
                            if (!empty($user['avatar'])) {
                                $oldFilename = basename($user['avatar']);
                                $oldFile = $uploadDir . $oldFilename;
                                if (!empty($oldFilename) && file_exists($oldFile) && is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }
                            $avatarPath = '/uploads/avatars/' . $filename;
                        } else {
                            $error = 'Failed to upload avatar. Please check directory permissions.';
                        }
                    }
                }
            }
            
            // Update user
            if (empty($error)) {
                if ($changePassword && !empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    if ($avatarPath !== null) {
                        $updateStmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, phone = ?, avatar = ? WHERE id = ?");
                        $updateStmt->bind_param("ssssssi", $username, $email, $hashedPassword, $full_name, $phone, $avatarPath, $user_id);
                    } else {
                        $updateStmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, phone = ? WHERE id = ?");
                        $updateStmt->bind_param("sssssi", $username, $email, $hashedPassword, $full_name, $phone, $user_id);
                    }
                } else {
                    if ($avatarPath !== null) {
                        $updateStmt = $conn->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, avatar = ? WHERE id = ?");
                        $updateStmt->bind_param("sssssi", $username, $email, $full_name, $phone, $avatarPath, $user_id);
                    } else {
                        $updateStmt = $conn->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ? WHERE id = ?");
                        $updateStmt->bind_param("ssssi", $username, $email, $full_name, $phone, $user_id);
                    }
                }
                
                if (isset($updateStmt)) {
                    if ($updateStmt->execute()) {
                        $success = 'Profile updated successfully!';
                        // Refresh user data
                        $stmt = $conn->prepare("SELECT id, username, email, password, full_name, phone, user_type, status, avatar, created_at FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                        $stmt->close();
                        // Update session user data
                        $currentUser = getCurrentUser();
                    } else {
                        $error = 'Error updating profile: ' . $conn->error;
                    }
                    $updateStmt->close();
                }
            }
        }
        
        $checkUsernameStmt->close();
        $checkEmailStmt->close();
    }
}

$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person-circle"></i> My Profile</h2>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php
                    $avatarPath = '';
                    if (!empty($user['avatar'])) {
                        // Handle different avatar path formats
                        $avatarPath = $user['avatar'];
                        if (strpos($avatarPath, '/') !== 0) {
                            $avatarPath = '/' . $avatarPath;
                        }
                        // Check if file exists (try both with and without leading slash)
                        $fullPath1 = __DIR__ . '/..' . $avatarPath;
                        $fullPath2 = __DIR__ . '/../' . ltrim($user['avatar'], '/');
                        if (file_exists($fullPath1) || file_exists($fullPath2)) {
                            echo '<img src="' . htmlspecialchars($avatarPath) . '" alt="Avatar" class="rounded-circle" style="width: 150px; height: 150px; object-fit: cover; border: 4px solid #667eea;">';
                        } else {
                            $initials = getUserInitials($user['full_name']);
                            echo '<div class="mx-auto" style="width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 48px; border: 4px solid #667eea;">' . htmlspecialchars($initials) . '</div>';
                        }
                    } else {
                        $initials = getUserInitials($user['full_name']);
                        echo '<div class="mx-auto" style="width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 48px; border: 4px solid #667eea;">' . htmlspecialchars($initials) . '</div>';
                    }
                    ?>
                </div>
                <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                <span class="badge bg-<?php echo $user['user_type'] === 'admin' ? 'primary' : 'info'; ?> mb-2">
                    <?php echo getUserTypeDisplay($user['user_type']); ?>
                </span>
                <div class="mt-3">
                    <small class="text-muted d-block">Member since</small>
                    <strong><?php echo date('M d, Y', strtotime($user['created_at'])); ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Form -->
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                    <!-- Avatar Upload Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <label class="form-label">Avatar</label>
                            <div class="d-flex align-items-center">
                                <?php
                                $avatarDisplayPath = '';
                                $avatarExists = false;
                                if (!empty($user['avatar'])) {
                                    // Handle different avatar path formats
                                    $avatarDisplayPath = $user['avatar'];
                                    if (strpos($avatarDisplayPath, '/') !== 0) {
                                        $avatarDisplayPath = '/' . $avatarDisplayPath;
                                    }
                                    // Check if file exists (try both with and without leading slash)
                                    $fullPath1 = __DIR__ . '/..' . $avatarDisplayPath;
                                    $fullPath2 = __DIR__ . '/../' . ltrim($user['avatar'], '/');
                                    $avatarExists = file_exists($fullPath1) || file_exists($fullPath2);
                                }
                                
                                if ($avatarExists) {
                                    echo '<img src="' . htmlspecialchars($avatarDisplayPath) . '" alt="Avatar" class="rounded-circle me-3" style="width: 80px; height: 80px; object-fit: cover;">';
                                } else {
                                    $initials = getUserInitials($user['full_name']);
                                    echo '<div class="user-avatar-large me-3" style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 24px;">' . htmlspecialchars($initials) . '</div>';
                                }
                                ?>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control mb-2" name="avatar" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                    <small class="text-muted d-block mb-2">Upload a new avatar (JPG, PNG, GIF, WEBP, max 2MB). Leave blank to keep current avatar.</small>
                                    <?php if ($avatarExists): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="removeAvatar">
                                            <label class="form-check-label text-danger" for="removeAvatar">
                                                Remove current avatar
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="09171234567 (optional)" maxlength="11" pattern="09[0-9]{9}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                            <div class="form-text text-muted small">Must be 11 digits starting with 09 (e.g. 09171234567)</div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Change Password Section -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="change_password" value="1" id="changePassword" onchange="togglePasswordFields()">
                            <label class="form-check-label" for="changePassword">
                                Change Password
                            </label>
                        </div>
                    </div>
                    
                    <div id="passwordFields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="current_password" id="current_password">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" id="password" minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordFields() {
    const checkbox = document.getElementById('changePassword');
    const passwordFields = document.getElementById('passwordFields');
    const currentPassword = document.getElementById('current_password');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (checkbox.checked) {
        passwordFields.style.display = 'block';
        currentPassword.required = true;
        password.required = true;
        confirmPassword.required = true;
    } else {
        passwordFields.style.display = 'none';
        currentPassword.required = false;
        password.required = false;
        confirmPassword.required = false;
        currentPassword.value = '';
        password.value = '';
        confirmPassword.value = '';
    }
}

// Password confirmation validation
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const password = document.getElementById('password');
    const confirmPassword = this;
    
    if (password.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity('Passwords do not match');
    } else {
        confirmPassword.setCustomValidity('');
    }
});

document.getElementById('password')?.addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        confirmPassword.dispatchEvent(new Event('input'));
    }
});

// Phone number validation
document.getElementById('phone')?.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length > 11) {
        this.value = this.value.substring(0, 11);
    }
});

// Form validation before submit
document.getElementById('profileForm')?.addEventListener('submit', function(e) {
    const phoneInput = document.getElementById('phone');
    const phoneValue = phoneInput.value.trim();
    const changePasswordCheckbox = document.getElementById('changePassword');
    const passwordInput = document.getElementById('password');
    
    // Phone is optional, but if provided, must be up to 11 digits and numbers only
    if (phoneValue !== '' && (phoneValue.length > 11 || !/^\d+$/.test(phoneValue))) {
        e.preventDefault();
        alert('Phone number is optional. If provided, it must be up to 11 digits (numbers only)');
        phoneInput.focus();
        return false;
    }
    
    // Validate password if change password is checked
    if (changePasswordCheckbox.checked) {
        const currentPasswordInput = document.getElementById('current_password');
        
        if (currentPasswordInput.value.trim() === '') {
            e.preventDefault();
            alert('Please enter your current password');
            currentPasswordInput.focus();
            return false;
        }
        
        if (passwordInput.value.trim() === '') {
            e.preventDefault();
            alert('Please enter a new password');
            passwordInput.focus();
            return false;
        }
        
        if (passwordInput.value.length < 6) {
            e.preventDefault();
            alert('New password must be at least 6 characters long');
            passwordInput.focus();
            return false;
        }
        
        const confirmPasswordInput = document.getElementById('confirm_password');
        if (passwordInput.value !== confirmPasswordInput.value) {
            e.preventDefault();
            alert('New password and confirmation do not match');
            confirmPasswordInput.focus();
            return false;
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

