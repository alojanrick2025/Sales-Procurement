<?php
$pageTitle = 'Add New Item';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $unit = strtoupper(trim($_POST['unit'] ?? ''));
    $price = isset($_POST['price']) && $_POST['price'] !== '' ? floatval($_POST['price']) : 100.00;
    $stocks = floatval($_POST['stocks'] ?? 0);
    $status = intval($_POST['status'] ?? 1);

    $allowedUnits = ['PCS', 'SET', 'ASS', 'FEET', 'MTR'];

    // Validate required fields
    if (empty($description) || empty($name) || empty($unit)) {
        $error = 'Please fill in all required fields (Code, Name, and Unit)';
    } elseif (!in_array($unit, $allowedUnits)) {
        $error = 'Invalid unit. Allowed units are: PCS, SET, ASS, FEET, MTR';
    } else {
        // Check if description (code) already exists
        $checkStmt = $conn->prepare("SELECT id FROM item_list WHERE description = ?");
        $checkStmt->bind_param("s", $description);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $error = 'Item code already exists. Please use a different code.';
        } else {
            $stmt = $conn->prepare("INSERT INTO item_list (description, name, unit, price, stocks, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssddi", $description, $name, $unit, $price, $stocks, $status);

            if ($stmt->execute()) {
                $success = 'Item added successfully!';
                // Clear form
                $_POST = [];
            } else {
                $error = 'Error adding item: ' . $conn->error;
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
}

$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-plus-circle"></i> Add New Item</h2>
    <a href="/items/items.php" class="btn btn-secondary">
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
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Item Code (Description) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="description"
                        value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>" required maxlength="250"
                        placeholder="e.g., ANE8, BOLTM588">
                    <small class="text-muted">Unique item identifier/code</small>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Item Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name"
                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required
                        placeholder="e.g., BOLT, MACHINE 5/8&quot; X 8&quot;">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Unit <span class="text-danger">*</span></label>
                    <select class="form-select" name="unit" required>
                        <option value="">-- Select Unit --</option>
                        <option value="PCS" <?php echo (($_POST['unit'] ?? 'PCS') === 'PCS') ? 'selected' : ''; ?>>PCS
                        </option>
                        <option value="SET" <?php echo (($_POST['unit'] ?? '') === 'SET') ? 'selected' : ''; ?>>SET
                        </option>
                        <option value="ASS" <?php echo (($_POST['unit'] ?? '') === 'ASS') ? 'selected' : ''; ?>>ASS
                        </option>
                        <option value="FEET" <?php echo (($_POST['unit'] ?? '') === 'FEET') ? 'selected' : ''; ?>>FEET
                        </option>
                        <option value="MTR" <?php echo (($_POST['unit'] ?? '') === 'MTR') ? 'selected' : ''; ?>>MTR
                        </option>
                    </select>
                    <small class="text-muted">Allowed: PCS, SET, ASS, FEET, MTR</small>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Price (₱)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" class="form-control" name="price"
                            value="<?php echo htmlspecialchars($_POST['price'] ?? '100.00'); ?>" min="0">
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Stocks</label>
                    <input type="number" step="0.01" class="form-control" name="stocks"
                        value="<?php echo htmlspecialchars($_POST['stocks'] ?? '0'); ?>" min="0">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="1" <?php echo ($_POST['status'] ?? '1') == '1' ? 'selected' : ''; ?>>Active
                        </option>
                        <option value="0" <?php echo ($_POST['status'] ?? '') == '0' ? 'selected' : ''; ?>>Inactive
                        </option>
                    </select>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="/items/items.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-plus-circle"></i> Add Item
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>