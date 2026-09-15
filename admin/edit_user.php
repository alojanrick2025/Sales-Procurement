<?php
$pageTitle = 'Edit User';
require_once __DIR__ . '/../config.php';
requireAdminLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$error = '';
$success = '';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: /admin/user_list.php');
    exit();
}

// Get user data
$stmt = $conn->prepare("SELECT id, username, email, password, full_name, phone, user_type, status, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Location: /admin/user_list.php');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $changePassword = isset($_POST['change_password']) && $_POST['change_password'] === '1';
    $currentPassword = $_POST['current_password'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $user_type = $_POST['user_type'] ?? 'admin';
    $status = $_POST['status'] ?? 'active';
    
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
        }
    } elseif (!empty($phone) && !preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = 'Phone number must be exactly 11 digits and start with 09 (e.g., 09171234567)';
    }
    
    if (empty($error)) {
        // Check if username already exists (excluding current user)
        $checkUsernameStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkUsernameStmt->bind_param("si", $username, $id);
        $checkUsernameStmt->execute();
        $usernameResult = $checkUsernameStmt->get_result();
        
        // Check if email already exists (excluding current user)
        $checkEmailStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmailStmt->bind_param("si", $email, $id);
        $checkEmailStmt->execute();
        $emailResult = $checkEmailStmt->get_result();
        
        if ($usernameResult->num_rows > 0) {
            $error = 'Username already exists';
        } elseif ($emailResult->num_rows > 0) {
            $error = 'Email already exists';
        } else {
            // Handle avatar removal
            $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';
            
            // Handle avatar upload
            $avatarPath = $user['avatar'] ?? null;
            
            if ($removeAvatar) {
                if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . ltrim($user['avatar'], '/'))) {
                    @unlink(__DIR__ . '/../' . ltrim($user['avatar'], '/'));
                }
                $avatarPath = null;
            } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/avatars/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $avatarFile = $_FILES['avatar'];
                $avatarExt = strtolower(pathinfo($avatarFile['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($avatarExt, $allowedExts, true)) {
                    $error = 'Invalid avatar file format. Allowed: JPG, PNG, GIF, WEBP';
                } elseif ($avatarFile['size'] > 2097152) {
                    $error = 'Avatar file size must be less than 2MB';
                } else {
                    $detectedMime = '';
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $detectedMime = finfo_file($finfo, $avatarFile['tmp_name']);
                        finfo_close($finfo);
                    } elseif (function_exists('mime_content_type')) {
                        $detectedMime = mime_content_type($avatarFile['tmp_name']);
                    }
                    
                    $imageInfo = @getimagesize($avatarFile['tmp_name']);
                    if (!in_array($detectedMime, $allowedMimeTypes, true) || $imageInfo === false) {
                        $error = 'Invalid image file. The uploaded file content is not a valid image.';
                    } else {
                        $avatarName = 'user_' . $id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $avatarExt;
                        $targetPath = $uploadDir . $avatarName;
                        $dbAvatarPath = 'uploads/avatars/' . $avatarName;
                        
                        if (move_uploaded_file($avatarFile['tmp_name'], $targetPath)) {
                            if (!empty($user['avatar'])) {
                                $oldFilename = basename($user['avatar']);
                                $oldFile = $uploadDir . $oldFilename;
                                if (!empty($oldFilename) && file_exists($oldFile) && is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }
                            $avatarPath = $dbAvatarPath;
                        } else {
                            $error = 'Error uploading avatar file.';
                        }
                    }
                }
            }
            
            // Update user
            if (empty($error)) {
                if ($changePassword && !empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    if ($avatarPath !== null) {
                        $updateStmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, phone = ?, user_type = ?, status = ?, avatar = ? WHERE id = ?");
                        $updateStmt->bind_param("ssssssssi", $username, $email, $hashedPassword, $full_name, $phone, $user_type, $status, $avatarPath, $id);
                    } else {
                        $updateStmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, phone = ?, user_type = ?, status = ? WHERE id = ?");
                        $updateStmt->bind_param("sssssssi", $username, $email, $hashedPassword, $full_name, $phone, $user_type, $status, $id);
                    }
                } else {
                    if ($avatarPath !== null) {
                        $updateStmt = $conn->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, user_type = ?, status = ?, avatar = ? WHERE id = ?");
                        $updateStmt->bind_param("sssssssi", $username, $email, $full_name, $phone, $user_type, $status, $avatarPath, $id);
                    } else {
                        $updateStmt = $conn->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, user_type = ?, status = ? WHERE id = ?");
                        $updateStmt->bind_param("ssssssi", $username, $email, $full_name, $phone, $user_type, $status, $id);
                    }
                }
                
                if (isset($updateStmt)) {
                    if ($updateStmt->execute()) {
                        $success = 'User updated successfully!';
                        // Refresh user data
                        $stmt = $conn->prepare("SELECT id, username, email, password, full_name, phone, user_type, status, avatar FROM users WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $user = $result->fetch_assoc();
                        $stmt->close();
                    } else {
                        $error = 'Error updating user: ' . $conn->error;
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
    <h2><i class="ph-bold ph-pencil"></i> Edit User</h2>
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
        <form method="POST" action="" enctype="multipart/form-data">
            <!-- Avatar Upload Section -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <label class="form-label">Avatar</label>
                    <div class="d-flex align-items-center">
                        <?php
                        if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . ltrim($user['avatar'], '/'))) {
                            echo '<img src="/' . ltrim(htmlspecialchars($user['avatar']), '/') . '" alt="Avatar" class="rounded-circle me-3" style="width: 80px; height: 80px; object-fit: cover;">';
                        } else {
                            $initials = strtoupper(substr($user['full_name'], 0, 2));
                            echo '<div class="user-avatar-large me-3" style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--zero-black) 0%, #333 100%); color: var(--ghost-green); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 24px;">' . htmlspecialchars($initials) . '</div>';
                        }
                        ?>
                        <div class="flex-grow-1">
                            <input type="file" class="form-control mb-2" name="avatar" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                            <small class="text-muted d-block mb-2">Upload a new avatar (JPG, PNG, GIF, WEBP, max 2MB). Leave blank to keep current avatar.</small>
                            <?php if (!empty($user['avatar'])): ?>
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
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="changePassword" name="change_password" value="1">
                        <label class="form-check-label" for="changePassword">
                            Change Password
                        </label>
                    </div>
                    <div id="passwordField" style="display: none;">
                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control mb-3" name="current_password" id="current_password">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" id="password" minlength="6">
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-control" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="09171234567 (optional)" maxlength="11" pattern="09[0-9]{9}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                    <div class="form-text text-muted small">Must be 11 digits starting with 09 (e.g. 09171234567)</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">User Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="user_type" required>
                        <option value="admin" <?php echo $user['user_type'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="/admin/user_list.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check-circle"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('changePassword').addEventListener('change', function() {
    const passwordField = document.getElementById('passwordField');
    const currentPasswordInput = document.getElementById('current_password');
    const passwordInput = document.getElementById('password');
    
    if (this.checked) {
        passwordField.style.display = 'block';
        currentPasswordInput.required = true;
        passwordInput.required = true;
    } else {
        passwordField.style.display = 'none';
        currentPasswordInput.required = false;
        passwordInput.required = false;
        currentPasswordInput.value = '';
        passwordInput.value = '';
    }
});

document.getElementById('phone').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length > 11) {
        this.value = this.value.substring(0, 11);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
