<?php
$pageTitle = 'System Information';
require_once __DIR__ . '/../config.php';
requireAdminLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$error = '';
$success = '';

// Get all system_info data
$result = $conn->query("SELECT meta_field, meta_value FROM system_info");
$systemInfo = [];
while ($row = $result->fetch_assoc()) {
    $systemInfo[$row['meta_field']] = $row['meta_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $user_name = trim($_POST['user_name'] ?? '');
    $company_phone = $_POST['company_phone'] ?? '';
    $company_email = $_POST['company_email'] ?? '';
    $company_address = $_POST['company_address'] ?? '';
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $logoFile = $_FILES['logo'];
        $logoExt = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($logoExt, $allowedExts, true)) {
            $error = 'Invalid logo file format. Allowed: JPG, PNG, GIF, WEBP';
        } elseif ($logoFile['size'] > 2097152) {
            $error = 'Logo file size must be less than 2MB';
        } else {
            $detectedMime = '';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_file($finfo, $logoFile['tmp_name']);
                finfo_close($finfo);
            } elseif (function_exists('mime_content_type')) {
                $detectedMime = mime_content_type($logoFile['tmp_name']);
            }
            
            $imageInfo = @getimagesize($logoFile['tmp_name']);
            if (!in_array($detectedMime, $allowedMimes, true) || $imageInfo === false) {
                $error = 'Invalid logo file. The uploaded file content is not a valid image.';
            } else {
                $logoName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $logoExt;
                $logoPath = 'uploads/' . $logoName;
                $targetPath = $uploadDir . $logoName;
                
                if (move_uploaded_file($logoFile['tmp_name'], $targetPath)) {
                    // Delete old logo if exists (and not default jess logo)
                    if (!empty($systemInfo['logo']) && strpos($systemInfo['logo'], 'logo_jess.png') === false) {
                        $oldLogoBasename = basename($systemInfo['logo']);
                        $oldLogoPath = $uploadDir . $oldLogoBasename;
                        if (!empty($oldLogoBasename) && file_exists($oldLogoPath) && is_file($oldLogoPath)) {
                            @unlink($oldLogoPath);
                        }
                    }
                    $systemInfo['logo'] = $logoPath;
                } else {
                    $error = 'Error uploading logo file.';
                }
            }
        }
    }
    
    if (!empty($company_phone) && !preg_match('/^09[0-9]{9}$/', $company_phone)) {
        $error = 'Company phone number must be exactly 11 digits and start with 09 (e.g., 09171234567)';
    }

    // Handle change password
    $changePassword = isset($_POST['change_password']) && $_POST['change_password'] === '1';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $shouldUpdatePassword = false;
    $adminUserId = $_SESSION['user_id'] ?? null;

    if ($changePassword || !empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
        if (!$adminUserId) {
            $error = 'Admin user session not found.';
        } else {
            $userCheckStmt = $conn->prepare("SELECT id, username, password FROM users WHERE id = ?");
            $userCheckStmt->bind_param("i", $adminUserId);
            $userCheckStmt->execute();
            $userCheckRes = $userCheckStmt->get_result();
            $adminUser = $userCheckRes->fetch_assoc();
            $userCheckStmt->close();

            if (!$adminUser) {
                $error = 'Administrator account not found in database.';
            } elseif (empty($currentPassword)) {
                $error = 'Please enter your current password to change password.';
            } elseif (!password_verify($currentPassword, $adminUser['password'])) {
                $error = 'Current password is incorrect.';
            } elseif (empty($newPassword) || strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New password and confirmation do not match.';
            } else {
                $shouldUpdatePassword = true;
            }
        }
    }

    // Update or insert system info
    if (empty($error)) {
        $fields = [
            'name' => $name,
            'short_name' => $company_name,
            'company_name' => $company_name,
            'user_name' => $user_name,
            'company_phone' => $company_phone,
            'company_email' => $company_email,
            'company_address' => $company_address
        ];
        
        // Add logo if uploaded
        if (isset($systemInfo['logo'])) {
            $fields['logo'] = $systemInfo['logo'];
        }
        
        $conn->begin_transaction();
        $allSuccess = true;
        
        foreach ($fields as $meta_field => $meta_value) {
            // Check if exists
            $checkStmt = $conn->prepare("SELECT id FROM system_info WHERE meta_field = ?");
            $checkStmt->bind_param("s", $meta_field);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkStmt->close();
            
            if ($checkResult->num_rows > 0) {
                // Update
                $updateStmt = $conn->prepare("UPDATE system_info SET meta_value = ? WHERE meta_field = ?");
                $updateStmt->bind_param("ss", $meta_value, $meta_field);
                if (!$updateStmt->execute()) {
                    $allSuccess = false;
                    $error = 'Error updating ' . $meta_field . ': ' . $conn->error;
                    break;
                }
                $updateStmt->close();
            } else {
                // Insert
                $insertStmt = $conn->prepare("INSERT INTO system_info (meta_field, meta_value) VALUES (?, ?)");
                $insertStmt->bind_param("ss", $meta_field, $meta_value);
                if (!$insertStmt->execute()) {
                    $allSuccess = false;
                    $error = 'Error inserting ' . $meta_field . ': ' . $conn->error;
                    break;
                }
                $insertStmt->close();
            }
        }
        
        if ($allSuccess) {
            // Sync admin user's full_name to User Name and avatar to logo
            if (!empty($user_name)) {
                $syncStmt = $conn->prepare("UPDATE users SET full_name = ? WHERE user_type = 'admin'");
                $syncStmt->bind_param("s", $user_name);
                $syncStmt->execute();
                $syncStmt->close();
            }
            if (!empty($systemInfo['logo'])) {
                $avatarSync = $conn->prepare("UPDATE users SET avatar = ? WHERE user_type = 'admin'");
                $avatarSync->bind_param("s", $systemInfo['logo']);
                $avatarSync->execute();
                $avatarSync->close();
            }

            // Update admin password in users table and log to password_change_logs table
            if ($shouldUpdatePassword && $adminUserId) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $pwdStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $pwdStmt->bind_param("si", $hashedPassword, $adminUserId);
                $pwdStmt->execute();
                $pwdStmt->close();

                // Audit log to password_change_logs table
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $clientAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $logStmt = $conn->prepare("INSERT INTO password_change_logs (user_id, changed_by, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                $logStmt->bind_param("iiss", $adminUserId, $adminUserId, $clientIp, $clientAgent);
                $logStmt->execute();
                $logStmt->close();

                // Save last_password_change in system_info
                $nowTimestamp = date('Y-m-d H:i:s');
                $timeStmt = $conn->prepare("INSERT INTO system_info (meta_field, meta_value) VALUES ('last_password_change', ?) ON DUPLICATE KEY UPDATE meta_value = ?");
                $timeStmt->bind_param("ss", $nowTimestamp, $nowTimestamp);
                $timeStmt->execute();
                $timeStmt->close();
            }
            
            $conn->commit();
            $success = $shouldUpdatePassword ? 'System information and administrator password updated successfully!' : 'System information updated successfully!';
            
            // Refresh system info data
            $result = $conn->query("SELECT meta_field, meta_value FROM system_info");
            $systemInfo = [];
            while ($row = $result->fetch_assoc()) {
                $systemInfo[$row['meta_field']] = $row['meta_value'];
            }
        } else {
            $conn->rollback();
        }
    }
}

// Fetch recent password change logs from database table `password_change_logs`
$recentPwdLogs = [];
$logRes = $conn->query("
    SELECT l.id, l.changed_at, l.ip_address, u.username as account_username, c.username as changed_by_username
    FROM password_change_logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN users c ON l.changed_by = c.id
    ORDER BY l.changed_at DESC LIMIT 5
");
if ($logRes) {
    while ($row = $logRes->fetch_assoc()) {
        $recentPwdLogs[] = $row;
    }
}

$conn->close();
$logoDisplayPath = !empty($systemInfo['logo']) ? '/' . ltrim($systemInfo['logo'], '/') : '';
?>

<style>
.logo-preview-container {
    position: relative;
    display: inline-block;
    cursor: pointer;
    border-radius: 50%;
    overflow: hidden;
}
.logo-hover-zoom {
    transition: transform 0.25s ease, filter 0.25s ease;
}
.logo-preview-container:hover .logo-hover-zoom {
    transform: scale(1.06);
    filter: brightness(0.92);
}
.logo-zoom-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(22, 35, 29, 0.45);
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
}
.logo-preview-container:hover .logo-zoom-overlay {
    opacity: 1;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-gear-six"></i> System Information</h2>
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

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">System Name</label>
                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($systemInfo['name'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Company Name</label>
                    <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($systemInfo['company_name'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">User Name</label>
                    <input type="text" class="form-control" name="user_name" value="<?php echo htmlspecialchars($systemInfo['user_name'] ?? 'System Administrator'); ?>" placeholder="Enter user name" required>
                    <div class="form-text small text-muted">Displays in the header navigation profile area.</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Company Phone</label>
                    <input type="tel" class="form-control" name="company_phone" id="company_phone" value="<?php echo htmlspecialchars($systemInfo['company_phone'] ?? ''); ?>" placeholder="09171234567" maxlength="11" pattern="09[0-9]{9}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                    <div class="form-text text-muted small">Must be 11 digits starting with 09 (e.g. 09171234567)</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-semibold">Company Email</label>
                    <input type="email" class="form-control" name="company_email" value="<?php echo htmlspecialchars($systemInfo['company_email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-4">
                    <label class="form-label fw-semibold">Company Address</label>
                    <textarea class="form-control" name="company_address" rows="3"><?php echo htmlspecialchars($systemInfo['company_address'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">System Logo</label>
                    <div class="input-group mb-1">
                        <input type="text" class="form-control" id="logo_display_name" 
                               value="<?php echo !empty($systemInfo['logo']) ? htmlspecialchars(basename($systemInfo['logo'])) : 'No file selected'; ?>" 
                               readonly 
                               onclick="document.getElementById('logo_file_input').click()" 
                               style="cursor: pointer; background-color: #ffffff;" 
                               title="Click Browse to select a new logo">
                        <button class="btn btn-outline-secondary d-flex align-items-center gap-1" type="button" onclick="document.getElementById('logo_file_input').click()">
                            <i class="ph-bold ph-folder-open"></i> Browse
                        </button>
                    </div>
                    <input type="file" id="logo_file_input" name="logo" accept="image/*" style="display: none;" onchange="handleLogoFileSelect(this)">
                    <div class="form-text small text-muted mb-3">
                        <i class="ph-bold ph-info me-1"></i>Current logo: <strong><?php echo !empty($systemInfo['logo']) ? htmlspecialchars(basename($systemInfo['logo'])) : 'None'; ?></strong>. Click Browse to replace image.
                    </div>
                    
                    <?php if (!empty($logoDisplayPath)): ?>
                        <div class="p-3 rounded-3 text-center d-inline-block border" style="background-color: #F7F9F8; border-color: #E3E8E5 !important;">
                            <div class="logo-preview-container shadow-sm" data-bs-toggle="modal" data-bs-target="#logoPreviewModal" title="Click for bigger preview">
                                <img id="logo_preview_img" src="<?php echo htmlspecialchars($logoDisplayPath); ?>" alt="System Logo" class="rounded-circle logo-hover-zoom" style="width: 140px; height: 140px; object-fit: cover; border: 3px solid #E3E8E5;">
                                <div class="logo-zoom-overlay rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="ph-bold ph-magnifying-glass-plus text-white fs-3"></i>
                                </div>
                            </div>
                            <p class="text-muted small mt-2 mb-0 fw-medium">
                                <a href="javascript:void(0)" class="text-decoration-none text-muted" data-bs-toggle="modal" data-bs-target="#logoPreviewModal">
                                    <i class="ph-bold ph-arrows-out-simple me-1"></i>Click image for bigger preview
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Change Password Section -->
            <div class="mt-4 pt-4 border-top" style="border-color: #E3E8E5 !important;">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="fw-bold mb-1 d-flex align-items-center gap-2" style="color: #16231D;">
                            <i class="ph-bold ph-lock-key" style="color: #2F6147;"></i> Change Administrator Password
                        </h5>

                    </div>
                    <div class="form-check form-switch mt-2 mt-sm-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="toggle_password_fields" name="change_password" value="1" style="cursor: pointer; width: 2.6rem; height: 1.35rem;">
                        <label class="form-check-label fw-semibold small ms-2" for="toggle_password_fields" style="cursor: pointer; color: #16231D;">Change Password</label>
                    </div>
                </div>

                <div id="password_fields_container" style="display: none;">
                    <div class="p-3 p-md-4 rounded-3 mb-3 border" style="background-color: #F8FAF9; border-color: #E3E8E5 !important;">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Current Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="current_password" id="sys_current_password" placeholder="Enter current password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('sys_current_password', this)" title="Show/Hide">
                                        <i class="ph-bold ph-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="new_password" id="sys_new_password" placeholder="Min. 6 characters">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('sys_new_password', this)" title="Show/Hide">
                                        <i class="ph-bold ph-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text small text-muted">Minimum 6 characters</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirm_password" id="sys_confirm_password" placeholder="Confirm new password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('sys_confirm_password', this)" title="Show/Hide">
                                        <i class="ph-bold ph-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($recentPwdLogs)): ?>
                <div class="mt-4 pt-3 border-top" style="border-color: #E3E8E5 !important;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-semibold text-muted text-uppercase d-flex align-items-center gap-1">
                            <i class="ph-bold ph-clock-counter-clockwise"></i> Password Change History
                        </span>
                        <?php if (!empty($systemInfo['last_password_change'])): ?>
                            <span class="badge bg-light text-dark border">
                                Last updated: <?php echo date('M d, Y h:i A', strtotime($systemInfo['last_password_change'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive rounded-2 border">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-2 px-3">Date &amp; Time</th>
                                    <th class="py-2 px-3">Account</th>
                                    <th class="py-2 px-3">Changed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPwdLogs as $pLog): ?>
                                <tr>
                                    <td class="py-2 px-3"><?php echo date('M d, Y h:i A', strtotime($pLog['changed_at'])); ?></td>
                                    <td class="py-2 px-3 fw-medium"><?php echo htmlspecialchars($pLog['account_username'] ?? 'admin'); ?></td>
                                    <td class="py-2 px-3"><?php echo htmlspecialchars($pLog['changed_by_username'] ?? 'System'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4 pt-3 border-top" style="border-color: #E3E8E5 !important;">
                <button type="submit" class="btn btn-primary px-4 py-2">
                    <i class="ph-bold ph-check"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for Bigger Logo Preview -->
<?php if (!empty($logoDisplayPath)): ?>
<div class="modal fade" id="logoPreviewModal" tabindex="-1" aria-labelledby="logoPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 py-3 px-4" style="background: #16231D; color: #ffffff;">
                <h5 class="modal-title d-flex align-items-center gap-2" id="logoPreviewModalLabel">
                    <i class="ph-bold ph-image" style="color: #D7FFE0;"></i> System Logo Preview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4 p-md-5" style="background: #F7F9F8;">
                <div class="p-3 d-inline-block rounded-circle bg-white shadow-sm border" style="border-color: #E3E8E5 !important;">
                    <img id="modal_logo_img" src="<?php echo htmlspecialchars($logoDisplayPath); ?>" alt="Bigger Logo Preview" class="rounded-circle img-fluid" style="max-width: 440px; max-height: 440px; width: 100%; object-fit: cover; border: 6px solid #16231D; box-shadow: 0 16px 40px rgba(22, 35, 29, 0.2);">
                </div>
                <div class="mt-4">
                    <h4 class="fw-bold mb-1" style="color: #16231D;"><?php echo htmlspecialchars($systemInfo['company_name'] ?? ''); ?></h4>
                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($systemInfo['name'] ?? ''); ?></p>
                </div>
            </div>
            <div class="modal-footer border-0 py-3 px-4" style="background: #ffffff; border-top: 1px solid #E3E8E5 !important;">
                <a href="<?php echo htmlspecialchars($logoDisplayPath); ?>" target="_blank" class="btn btn-outline-secondary d-flex align-items-center gap-1" id="open_original_btn">
                    <i class="ph-bold ph-arrow-square-out"></i> Open Original Image
                </a>
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function handleLogoFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('logo_display_name').value = file.name;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('logo_preview_img');
            const modalImg = document.getElementById('modal_logo_img');
            const openOrigBtn = document.getElementById('open_original_btn');
            
            if (previewImg) previewImg.src = e.target.result;
            if (modalImg) modalImg.src = e.target.result;
            if (openOrigBtn) openOrigBtn.href = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}
function togglePasswordVisibility(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('ph-eye');
            icon.classList.add('ph-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('ph-eye-slash');
            icon.classList.add('ph-eye');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const toggleSwitch = document.getElementById('toggle_password_fields');
    const container = document.getElementById('password_fields_container');
    if (toggleSwitch && container) {
        toggleSwitch.addEventListener('change', function() {
            container.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                const cur = document.getElementById('sys_current_password');
                const nxt = document.getElementById('sys_new_password');
                const cnf = document.getElementById('sys_confirm_password');
                if (cur) cur.value = '';
                if (nxt) nxt.value = '';
                if (cnf) cnf.value = '';
            } else {
                const cur = document.getElementById('sys_current_password');
                if (cur) cur.focus();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
