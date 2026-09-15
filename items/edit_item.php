<?php
$pageTitle = 'Edit Item';
require_once __DIR__ . '/../config.php';
requireLogin();

$conn = getDBConnection();
$error = '';
$success = '';

$id = intval($_GET['id'] ?? 0);

// Get item details
$stmt = $conn->prepare("SELECT * FROM item_list WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    header('Location: /items/items.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    // If description is empty, retain original code since field is read-only
    if (empty($description)) {
        $description = $item['description'];
    }
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
        // Check if description (code) already exists (excluding current record)
        $checkStmt = $conn->prepare("SELECT id FROM item_list WHERE description = ? AND id != ?");
        $checkStmt->bind_param("si", $description, $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $error = 'Item code already exists. Please use a different code.';
        } else {
            $stmt = $conn->prepare("UPDATE item_list SET description = ?, name = ?, unit = ?, price = ?, stocks = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssddii", $description, $name, $unit, $price, $stocks, $status, $id);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Item updated successfully!';
                $stmt->close();
                $checkStmt->close();
                $conn->close();
                if (!headers_sent()) {
                    header('Location: /items/items.php');
                    exit();
                } else {
                    echo "<script>window.location.href='/items/items.php';</script>";
                    exit();
                }
            } else {
                $error = 'Error updating item: ' . $conn->error;
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-pencil-simple"></i> Edit Item</h2>
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
        <form method="POST" action="" id="editItemForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="itemCodeInput">Item Code (Description) <span
                            class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="ph-bold ph-lock-key"></i></span>
                        <input type="text" class="form-control" name="description" id="itemCodeInput" value=""
                            data-code="<?php echo htmlspecialchars($item['description']); ?>" readonly
                            autocomplete="off" style="background-color: #f8faf9; cursor: pointer;"
                            title="Read-only. Click or select to display item code.">
                    </div>
                    <small class="text-muted"><i class="ph-bold ph-info"></i> Read-only. Click field or select an item
                        to display value.</small>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label" for="itemNameInput">Item Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" id="itemNameInput"
                        value="<?php echo htmlspecialchars(html_entity_decode(str_ireplace('&quot;', '"', $item['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>"
                        required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Unit <span class="text-danger">*</span></label>
                    <select class="form-select" name="unit" required>
                        <?php
                        $currentUnit = strtoupper(trim($item['unit']));
                        $unitOptions = ['PCS', 'SET', 'ASS', 'FEET', 'MTR'];
                        foreach ($unitOptions as $u):
                            ?>
                            <option value="<?php echo $u; ?>" <?php echo ($currentUnit === $u) ? 'selected' : ''; ?>>
                                <?php echo $u; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Allowed: PCS, SET, ASS, FEET, MTR</small>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Price (₱)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" class="form-control" name="price"
                            value="<?php echo htmlspecialchars($item['price']); ?>" min="0">
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Stocks</label>
                    <input type="number" step="0.01" class="form-control" name="stocks"
                        value="<?php echo htmlspecialchars($item['stocks']); ?>" min="0">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="1" <?php echo $item['status'] == 1 ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $item['status'] == 0 ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="/items/items.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check"></i> Update Item
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const itemCodeInput = document.getElementById('itemCodeInput');
        const form = document.getElementById('editItemForm');
        if (!itemCodeInput) return;

        const actualCode = itemCodeInput.getAttribute('data-code') || '';

        function revealCode() {
            if (!itemCodeInput.value && actualCode) {
                itemCodeInput.value = actualCode;
            }
        }

        // 1. Reveal when user selects / clicks the item code field
        itemCodeInput.addEventListener('focus', revealCode);
        itemCodeInput.addEventListener('click', revealCode);

        // 2. Reveal when user selects an item (Item Name or any other input/select in the form)
        if (form) {
            const formFields = form.querySelectorAll('input:not(#itemCodeInput), select, textarea');
            formFields.forEach(function (field) {
                field.addEventListener('focus', revealCode);
                field.addEventListener('click', revealCode);
                field.addEventListener('change', revealCode);
            });

            // Ensure value is present before submission
            form.addEventListener('submit', function () {
                if (!itemCodeInput.value) {
                    itemCodeInput.value = actualCode;
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>