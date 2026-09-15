<?php
$pageTitle = 'Edit Stock Level';
require_once __DIR__ . '/../config.php';
requireLogin();

$conn = getDBConnection();
$error = '';
$success = '';

$itemId = intval($_GET['id'] ?? 0);
if ($itemId <= 0) {
    header('Location: /stocks/stocks.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM item_list WHERE id = ?");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Location: /stocks/stocks.php');
    exit();
}

$item = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStock = floatval($_POST['stocks'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($newStock < 0) {
        $error = 'Stock cannot be negative.';
    } else {
        $updateStmt = $conn->prepare("UPDATE item_list SET stocks = ? WHERE id = ?");
        $updateStmt->bind_param("di", $newStock, $itemId);
        if ($updateStmt->execute()) {
            $success = "Stock level for " . htmlspecialchars($item['name']) . " has been updated to " . number_format($newStock, 0) . " " . htmlspecialchars($item['unit']) . ".";
            $item['stocks'] = $newStock;
        } else {
            $error = 'Error updating stock: ' . $conn->error;
        }
        $updateStmt->close();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-pencil-simple"></i> Edit Stock Level</h2>
    <a href="/stocks/stocks.php" class="btn btn-secondary">
        <i class="ph-bold ph-arrow-left"></i> Back to Stock List
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label text-muted">Item Code</label>
                    <input type="text" class="form-control bg-light" readonly
                        value="<?php echo htmlspecialchars($item['description']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted">Item Name</label>
                    <input type="text" class="form-control bg-light" readonly
                        value="<?php echo htmlspecialchars($item['name']); ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label text-muted">Unit of Measurement</label>
                    <input type="text" class="form-control bg-light" readonly
                        value="<?php echo htmlspecialchars($item['unit']); ?>">
                </div>
                <div class="col-md-6">
                    <label for="stocks" class="form-label fw-bold">Stock Quantity <span
                            class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" step="any" min="0"
                            class="form-control form-control-lg text-primary fw-bold" id="stocks" name="stocks"
                            value="<?php echo htmlspecialchars($item['stocks']); ?>" required>
                        <span class="input-group-text"><?php echo htmlspecialchars($item['unit']); ?></span>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label for="remarks" class="form-label">Adjustment Reason / Notes</label>
                <textarea class="form-control" id="remarks" name="remarks" rows="3"
                    placeholder="e.g. Physical inventory count discrepancy adjustment"></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="/stocks/stocks.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check"></i> Update Stock Count
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>