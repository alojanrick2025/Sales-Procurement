<?php
$pageTitle = 'Create Supplier Purchase Order';
require_once __DIR__ . '/../config.php';
requireLogin();

$conn = getDBConnection();
$error = '';
$success = '';

// Generate next PO number
$poQuery = $conn->query("SELECT MAX(id) as max_id FROM supplier_orders");
$nextId = ($poQuery->fetch_assoc()['max_id'] ?? 0) + 1;
$nextPoNum = 'SPO-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

// Fetch suppliers from business partners (clients table)
$suppliersRes = $conn->query("SELECT id, name FROM clients WHERE partner_type = 'supplier' ORDER BY name ASC");
$suppliers = [];
while ($s = $suppliersRes->fetch_assoc())
    $suppliers[] = $s;

// Fetch item catalog
$catalogRes = $conn->query("SELECT id, name, description, unit, price FROM item_list WHERE status = 1 ORDER BY name ASC");
$catalog = [];
while ($row = $catalogRes->fetch_assoc())
    $catalog[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_number = trim($_POST['po_number'] ?? $nextPoNum);
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
    $order_date = $_POST['order_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'pending';
    $notes = trim($_POST['notes'] ?? '');

    // Items
    $itemIds = $_POST['item_id'] ?? [];
    $itemNames = $_POST['item_name'] ?? [];
    $itemDescs = $_POST['item_desc'] ?? [];
    $itemUnits = $_POST['item_unit'] ?? [];
    $itemQtys = $_POST['item_qty'] ?? [];
    $itemPrices = $_POST['item_price'] ?? [];
    $itemMarkdowns = $_POST['item_markdown'] ?? [];

    $validItems = [];
    $subtotal = 0;
    foreach ($itemNames as $i => $name) {
        $name = trim($name);
        $qty = floatval($itemQtys[$i] ?? 1);
        $price = floatval($itemPrices[$i] ?? 0);
        $markdown = max(0, floatval($itemMarkdowns[$i] ?? 0));
        
        if ($name !== '' && $qty > 0) {
            $lineTotal = $qty * $price * (1 - $markdown / 100);
            $subtotal += $lineTotal;
            $validItems[] = [
                'item_id' => !empty($itemIds[$i]) ? intval($itemIds[$i]) : null,
                'item_name' => $name,
                'description' => trim($itemDescs[$i] ?? ''),
                'unit' => trim($itemUnits[$i] ?? ''),
                'quantity' => $qty,
                'unit_price' => $price,
                'markdown' => $markdown,
                'total_price' => $lineTotal,
            ];
        }
    }

    $grand_total = $subtotal;

    if (empty($po_number) || empty($supplier_name)) {
        $error = 'PO Number and Supplier are required.';
    } elseif (count($validItems) === 0) {
        $error = 'Please add at least one item.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO supplier_orders (po_number, supplier_id, supplier_name, order_date, total_amount, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisdsss", $po_number, $supplier_id, $supplier_name, $order_date, $grand_total, $status, $notes);
            if (!$stmt->execute())
                throw new Exception("Error saving PO: " . $stmt->error);
            $newId = $conn->insert_id;
            $stmt->close();

            $iStmt = $conn->prepare("INSERT INTO supplier_order_items (supplier_order_id, item_id, item_name, description, unit, quantity, unit_price, markdown_rate, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($validItems as $item) {
                $iStmt->bind_param("iisssdddd", $newId, $item['item_id'], $item['item_name'], $item['description'], $item['unit'], $item['quantity'], $item['unit_price'], $item['markdown'], $item['total_price']);
                if (!$iStmt->execute())
                    throw new Exception("Error saving item: " . $iStmt->error);
            }
            $iStmt->close();

            $conn->commit();
            $success = "Supplier Purchase Order <strong>$po_number</strong> created successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-plus-circle"></i> Create Supplier Purchase Order</h2>
    <a href="/orders/supplier_po.php" class="btn btn-secondary">
        <i class="ph-bold ph-arrow-left"></i> Back to PO List
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-warning-circle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <!-- PO Header Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="ph-bold ph-clipboard-text"></i> PO Details</h5>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">PO Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="po_number" required
                        value="<?php echo htmlspecialchars($nextPoNum); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Order Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="order_date" required
                        value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select class="form-select" name="supplier_id" id="supplierSelect" required>
                        <option value="">-- Select Supplier --</option>
                        <?php foreach ($suppliers as $sp): ?>
                            <option value="<?php echo $sp['id']; ?>"
                                data-name="<?php echo htmlspecialchars($sp['name']); ?>">
                                <?php echo htmlspecialchars($sp['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="supplier_name" id="supplierNameInput" value="">
                    <?php if (empty($suppliers)): ?>
                        <div class="form-text text-warning"><i class="ph-bold ph-warning"></i> No suppliers found in
                            Business Partners. <a href="/clients/add_client.php">Add a supplier partner</a> first.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="pending" selected>Pending</option>
                        <option value="approved">Approved</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Section Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="ph-bold ph-shopping-cart"></i> PO Items & Pricing</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn">
                <i class="ph-bold ph-plus"></i> Add Item
            </button>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-bordered align-middle" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 30%;">Item / Description</th>
                            <th style="width: 10%;">Unit</th>
                            <th style="width: 10%;">Qty</th>
                            <th style="width: 13%;">Unit Price (₱)</th>
                            <th style="width: 14%;">
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="text-nowrap">Markdown %</span>
                                        <input type="number" step="5" min="0" max="999" id="globalMarkdownInput"
                                            class="form-control form-control-sm text-center" style="width:58px;"
                                            value="0" placeholder="%">
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button type="button"
                                            class="btn btn-outline-secondary btn-sm py-0 px-1 global-markdown-preset"
                                            data-val="10">10</button>
                                        <button type="button"
                                            class="btn btn-outline-secondary btn-sm py-0 px-1 global-markdown-preset"
                                            data-val="20">20</button>
                                        <button type="button"
                                            class="btn btn-outline-secondary btn-sm py-0 px-1 global-markdown-preset"
                                            data-val="30">30</button>
                                    </div>
                                </div>
                            </th>
                            <th style="width: 16%;">Line Total (₱)</th>
                            <th style="width: 7%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- Initial row -->
                        <tr class="item-row">
                            <td>
                                <select class="form-select form-select-sm item-catalog-select">
                                    <option value="">-- Choose Item from Dropdown --</option>
                                    <?php foreach ($catalog as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                            data-desc="<?php echo htmlspecialchars($cat['description'] ?? ''); ?>"
                                            data-unit="<?php echo htmlspecialchars($cat['unit'] ?? ''); ?>"
                                            data-price="<?php echo $cat['price']; ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="item_id[]" class="item-id-input" value="">
                                <input type="hidden" class="item-name-input"
                                    name="item_name[]" required>
                                <input type="hidden" class="item-desc-input"
                                    name="item_desc[]">
                            </td>
                            <td><input type="text" class="form-control form-control-sm item-unit-input"
                                    name="item_unit[]" placeholder="PCS"></td>
                            <td><input type="number" class="form-control form-control-sm item-qty-input"
                                    name="item_qty[]" value="1" min="0.01" step="0.01"></td>
                            <td><input type="number" class="form-control form-control-sm item-price-input"
                                    name="item_price[]" value="0.00" min="0" step="0.01"></td>
                            <td><input type="number" step="5" min="0" max="999" name="item_markdown[]"
                                    class="form-control form-control-sm item-markdown-input text-center" value="0" placeholder="%"></td>
                            <td><input type="text" class="form-control form-control-sm item-total-display bg-light"
                                    readonly value="₱0.00"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i
                                        class="ph-bold ph-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Financial Summary -->
            <div class="row justify-content-end mt-3">
                <div class="col-md-4">
                    <div class="bg-light p-3 rounded border">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <strong id="subtotalDisplay">₱0.00</strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fs-5 text-primary">
                            <strong>Grand Total:</strong>
                            <strong id="grandTotalDisplay">₱0.00</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes & Actions -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <label class="form-label fw-bold">Notes / Procurement Specifications</label>
            <textarea class="form-control" name="notes" rows="3"
                placeholder="Enter procurement items, specifications, or supplier instructions..."></textarea>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2 p-3">
            <a href="/orders/supplier_po.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="ph-bold ph-floppy-disk"></i> Create Supplier PO
            </button>
        </div>
    </div>
</form>

<script>
    // Catalog data for JS
    const catalogData = <?php echo json_encode($catalog); ?>;

    document.getElementById('supplierSelect').addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        document.getElementById('supplierNameInput').value = selected.getAttribute('data-name') || '';
    });

    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(function (row) {
            const qty = parseFloat(row.querySelector('.item-qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.item-price-input').value) || 0;
            const markdown = parseFloat(row.querySelector('.item-markdown-input').value) || 0;
            const lineTotal = qty * price * (1 - markdown / 100);
            row.querySelector('.item-total-display').value = '₱' + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            subtotal += lineTotal;
        });
        const fmt = v => '₱' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
        document.getElementById('grandTotalDisplay').textContent = fmt(subtotal);
    }

    function getRowTemplate() {
        const options = catalogData.map(c =>
            `<option value="${c.id}" data-name="${c.name}" data-desc="${c.description || ''}" data-unit="${c.unit || ''}" data-price="${c.price}">${c.name}</option>`
        ).join('');
        return `<tr class="item-row">
        <td>
            <select class="form-select form-select-sm item-catalog-select">
                <option value="">-- Choose Item from Dropdown --</option>${options}
            </select>
            <input type="hidden" name="item_id[]" class="item-id-input" value="">
            <input type="hidden" class="item-name-input" name="item_name[]" required>
            <input type="hidden" class="item-desc-input" name="item_desc[]">
        </td>
        <td><input type="text" class="form-control form-control-sm item-unit-input" name="item_unit[]" placeholder="PCS"></td>
        <td><input type="number" class="form-control form-control-sm item-qty-input" name="item_qty[]" value="1" min="0.01" step="0.01"></td>
        <td><input type="number" class="form-control form-control-sm item-price-input" name="item_price[]" value="0.00" min="0" step="0.01"></td>
        <td><input type="number" step="5" min="0" max="999" name="item_markdown[]" class="form-control form-control-sm item-markdown-input text-center" value="0" placeholder="%"></td>
        <td><input type="text" class="form-control form-control-sm item-total-display bg-light" readonly value="₱0.00"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="ph-bold ph-trash"></i></button></td>
    </tr>`;
    }

    function bindRowEvents(row) {
        const select = row.querySelector('.item-catalog-select');
        select.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (opt && opt.value) {
                row.querySelector('.item-id-input').value = opt.value;
                row.querySelector('.item-name-input').value = opt.getAttribute('data-name') || '';
                row.querySelector('.item-desc-input').value = opt.getAttribute('data-desc') || '';
                row.querySelector('.item-unit-input').value = opt.getAttribute('data-unit') || '';
                row.querySelector('.item-price-input').value = opt.getAttribute('data-price') || '0';
            } else {
                row.querySelector('.item-id-input').value = '';
            }
            calculateTotals();
        });
        row.querySelector('.item-qty-input').addEventListener('input', calculateTotals);
        row.querySelector('.item-price-input').addEventListener('input', calculateTotals);
        row.querySelector('.item-markdown-input').addEventListener('input', calculateTotals);
        row.querySelector('.remove-row-btn').addEventListener('click', function () {
            if (document.querySelectorAll('.item-row').length > 1) {
                row.remove();
                calculateTotals();
            }
        });
    }

    document.querySelectorAll('.item-row').forEach(bindRowEvents);

    // Global markdown logic
    function applyGlobalMarkdown(val) {
        document.querySelectorAll('.item-markdown-input').forEach(function (input) {
            input.value = val;
        });
        calculateTotals();
    }

    document.getElementById('globalMarkdownInput').addEventListener('input', function () {
        applyGlobalMarkdown(this.value);
    });

    document.querySelectorAll('.global-markdown-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('globalMarkdownInput').value = this.dataset.val;
            applyGlobalMarkdown(this.dataset.val);
        });
    });

    document.getElementById('addRowBtn').addEventListener('click', function () {
        const tbody = document.getElementById('itemsBody');
        const temp = document.createElement('tbody');
        temp.innerHTML = getRowTemplate();
        const newRow = temp.querySelector('tr');
        
        // Inherit current global markdown
        const globalVal = document.getElementById('globalMarkdownInput').value || '0';
        newRow.querySelector('.item-markdown-input').value = globalVal;
        
        tbody.appendChild(newRow);
        bindRowEvents(newRow);
        calculateTotals();
    });
</script>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>