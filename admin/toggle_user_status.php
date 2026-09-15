<?php
require_once __DIR__ . '/../config.php';
requireAdminLogin();
requirePostWithCsrf();

$conn = getDBConnection();

$user_id = intval($_POST['id'] ?? 0);
$new_status = $_POST['status'] ?? '';

// Validate status
if (!in_array($new_status, ['active', 'inactive'])) {
    $_SESSION['error'] = 'Invalid status value';
    header('Location: /admin/user_list.php');
    exit();
}

// Validate user ID
if ($user_id <= 0) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: /admin/user_list.php');
    exit();
}

// Check if user exists
$checkStmt = $conn->prepare("SELECT id, username, status FROM users WHERE id = ?");
$checkStmt->bind_param("i", $user_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    $_SESSION['error'] = 'User not found';
    $checkStmt->close();
    $conn->close();
    header('Location: /admin/user_list.php');
    exit();
}

$user = $checkResult->fetch_assoc();
$checkStmt->close();

// Prevent deactivating yourself
if ($user_id == $_SESSION['user_id'] && $new_status === 'inactive') {
    $_SESSION['error'] = 'You cannot deactivate your own account';
    $conn->close();
    header('Location: /admin/user_list.php');
    exit();
}

// Update user status
$stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
$stmt->bind_param("si", $new_status, $user_id);

if ($stmt->execute()) {
    $_SESSION['success'] = 'User status updated successfully';
} else {
    $_SESSION['error'] = 'Error updating user status: ' . $conn->error;
}

$stmt->close();
$conn->close();

header('Location: /admin/user_list.php');
exit();
?>



