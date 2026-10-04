<?php
$pageTitle = 'Add / Receive Stock';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/inventory.php';
requireLogin();

$conn = getDBConnection();
$error = '';
$success = '';

// Pre-selected item if passed via GET
$selectedItemId = intval($_GET['item_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = intval($_POST['item_id'] ?? 0);
    $quantity = floatval($_POST['quantity'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if (!validateCsrfToken()) {
        $error = 'Invalid security token. Please reload the page and try again.';
    } elseif ($itemId <= 0 || $quantity <= 0) {
        $error = 'Please select a valid item and enter a quantity greater than 0.';
    } else {
        // Recorded in the stock history with the remarks as its note (includes/inventory.php)
        $result = adjustStock($conn, $itemId, 'add', $quantity, $remarks !== '' ? $remarks : 'Stock received');
        if ($result['ok']) {
            $unitStmt = $conn->prepare("SELECT unit FROM item_list WHERE id = ?");
            $unitStmt->bind_param("i", $itemId);
            $unitStmt->execute();
            $unit = $unitStmt->get_result()->fetch_assoc()['unit'] ?? '';
            $unitStmt->close();
            $name = html_entity_decode(str_ireplace('&quot;', '"', $result['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $success = "Successfully added " . number_format($quantity, 0) . " " . $unit . " to " . $name . ". New total: " . number_format($result['balance'], 0) . " " . $unit . ".";
        } else {
            $error = $result['error'];
        }
    }
}

// Fetch all active items for selection
$itemsResult = $conn->query("SELECT id, name, description, unit, stocks FROM item_list ORDER BY name ASC");
$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-plus-circle"></i> Receive / Add Stock</h2>
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
            <?php echo csrfField(); ?>
            <div class="mb-3">
                <label for="item_id" class="form-label">Select Item <span class="text-danger">*</span></label>
                <select class="form-select" name="item_id" id="item_id" required>
                    <option value="">-- Choose Item --</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?php echo $item['id']; ?>" 
                                data-stocks="<?php echo $item['stocks']; ?>"
                                data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                <?php echo ($selectedItemId === intval($item['id'])) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['name']); ?> (<?php echo htmlspecialchars($item['description']); ?>) - Current: <?php echo number_format($item['stocks'], 0); ?> <?php echo htmlspecialchars($item['unit']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="quantity" class="form-label">Quantity to Add <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" step="any" min="0.01" class="form-control" id="quantity" name="quantity" required placeholder="e.g. 50">
                        <span class="input-group-text" id="unitDisplay">Units</span>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Current Stock Level</label>
                    <input type="text" class="form-control bg-light" id="currentStockDisplay" readonly value="Select an item above">
                </div>
            </div>

            <div class="mb-4">
                <label for="remarks" class="form-label">Notes / Remarks (Optional)</label>
                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="e.g. Received from Supplier / Delivery batch #1234"></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="/stocks/stocks.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-download-simple"></i> Confirm Stock Addition
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemSelect = document.getElementById('item_id');
    const unitDisplay = document.getElementById('unitDisplay');
    const currentStockDisplay = document.getElementById('currentStockDisplay');

    function updateItemDetails() {
        const selected = itemSelect.options[itemSelect.selectedIndex];
        if (selected && selected.value) {
            const stocks = selected.getAttribute('data-stocks') || '0';
            const unit = selected.getAttribute('data-unit') || 'Units';
            unitDisplay.textContent = unit;
            currentStockDisplay.value = stocks + ' ' + unit;
        } else {
            unitDisplay.textContent = 'Units';
            currentStockDisplay.value = 'Select an item above';
        }
    }

    itemSelect.addEventListener('change', updateItemDetails);
    updateItemDetails();
});
</script>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>
