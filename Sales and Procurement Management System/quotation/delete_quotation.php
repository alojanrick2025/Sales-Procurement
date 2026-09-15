<?php
require_once __DIR__ . '/../config.php';
requireLogin();
requirePostWithCsrf();

$quoteId = intval($_POST['id'] ?? 0);

if ($quoteId > 0) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM quotations WHERE id = ?");
    $stmt->bind_param("i", $quoteId);
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: /quotation/quotation.php?success=' . urlencode('Quotation deleted successfully.'));
        exit();
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        header('Location: /quotation/quotation.php?error=' . urlencode('Error deleting quotation: ' . $error));
        exit();
    }
}

header('Location: /quotation/quotation.php');
exit();
