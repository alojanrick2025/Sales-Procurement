<?php
require_once __DIR__ . '/../config.php';
requireAdminLogin();
requirePostWithCsrf();

$conn = getDBConnection();

$id = intval($_POST['id'] ?? 0);

if ($id > 0) {
    // Prevent deleting yourself
    if ($id == $_SESSION['user_id']) {
        $_SESSION['error'] = 'You cannot delete your own account';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'User deleted successfully';
        } else {
            $_SESSION['error'] = 'Error deleting user: ' . $conn->error;
        }
        
        $stmt->close();
    }
}

$conn->close();
header('Location: /admin/user_list.php');
exit();
