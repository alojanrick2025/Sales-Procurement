<?php
require_once __DIR__ . '/../config.php';
requireLogin();
requirePostWithCsrf();

$conn = getDBConnection();

$id = intval($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM item_list WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Item deleted successfully';
    } else {
        $_SESSION['error_message'] = 'Error deleting item';
    }
    
    $stmt->close();
}

$conn->close();
header('Location: /items/items.php');
exit();
?>

