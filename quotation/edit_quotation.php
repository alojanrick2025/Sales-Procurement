<?php
$pageTitle = 'Edit Quotation';
require_once __DIR__ . '/../config.php';
requireLogin();

$conn = getDBConnection();
$error = '';
$quoteId = intval($_GET['id'] ?? 0);

if ($quoteId <= 0) {
    header('Location: /quotation/quotation.php');
    exit();
}

// Fetch existing quotation
$stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ?");
$stmt->bind_param("i", $quoteId);
$stmt->execute();
$quoteRes = $stmt->get_result();

if ($quoteRes->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Location: /quotation/quotation.php');
    exit();
}

$quote = $quoteRes->fetch_assoc();
$stmt->close();

// Fetch items
$itStmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC");
$itStmt->bind_param("i", $quoteId);
$itStmt->execute();
$itRes = $itStmt->get_result();
$existingItems = [];
while ($row = $itRes->fetch_assoc()) {
    $existingItems[] = $row;
}
$itStmt->close();

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientName = trim($_POST['client_name'] ?? '');
    $clientEmail = trim($_POST['client_email'] ?? '');
    $clientPhone = trim($_POST['client_phone'] ?? '');
    $clientAddress = trim($_POST['client_address'] ?? '');

    $destination = '';
    $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $status = $_POST['status'] ?? 'draft';
    $notes = trim($_POST['notes'] ?? '');
    $taxRate = 0; // Tax removed

    $itemIds = $_POST['item_id'] ?? [];
    $itemNames = $_POST['item_name'] ?? [];
    $itemDescs = $_POST['item_desc'] ?? [];
    $itemUnits = $_POST['item_unit'] ?? [];
    $itemQtys = $_POST['item_qty'] ?? [];
    $itemPrices = $_POST['item_price'] ?? [];

    if (empty($clientName)) {
        $error = 'Client name is required.';
    } elseif (empty($itemNames) || count($itemNames) === 0) {
        $error = 'At least one line item is required.';
    } else {
        $subtotal = 0;
        $validItems = [];

        for ($i = 0; $i < count($itemNames); $i++) {
            $name = trim($itemNames[$i] ?? '');
            if (empty($name)) continue;

            $qty = max(1, floatval($itemQtys[$i] ?? 1));
            $price = max(0, floatval($itemPrices[$i] ?? 0));
            $lineTotal = $qty * $price;
            $subtotal += $lineTotal;

            $validItems[] = [
                'item_id' => !empty($itemIds[$i]) ? intval($itemIds[$i]) : null,
                'name' => $name,
                'desc' => trim($itemDescs[$i] ?? ''),
                'unit' => trim($itemUnits[$i] ?? 'PCS'),
                'qty' => $qty,
                'price' => $price,
                'total' => $lineTotal
            ];
        }

        if (count($validItems) === 0) {
            $error = 'At least one valid item is required.';
        } else {
            $taxAmount = 0;
            $grandTotal = $subtotal;

            $conn->begin_transaction();
            try {
                $upStmt = $conn->prepare("UPDATE quotations SET client_name = ?, client_email = ?, client_phone = ?, client_address = ?, total_amount = ?, tax_rate = ?, tax_amount = ?, grand_total = ?, valid_until = ?, status = ?, notes = ? WHERE id = ?");
                $upStmt->bind_param("ssssddddsssi", $clientName, $clientEmail, $clientPhone, $clientAddress, $subtotal, $taxRate, $taxAmount, $grandTotal, $validUntil, $status, $notes, $quoteId);
                if (!$upStmt->execute()) {
                    throw new Exception("Error updating quotation: " . $upStmt->error);
                }
                $upStmt->close();

                // Delete old items and insert fresh
                $conn->query("DELETE FROM quotation_items WHERE quotation_id = $quoteId");
                $insItem = $conn->prepare("INSERT INTO quotation_items (quotation_id, item_id, item_name, description, unit, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($validItems as $it) {
                    $insItem->bind_param("iisssddd", $quoteId, $it['item_id'], $it['name'], $it['desc'], $it['unit'], $it['qty'], $it['price'], $it['total']);
                    if (!$insItem->execute()) {
                        throw new Exception("Error updating line item: " . $insItem->error);
                    }
                }
                $insItem->close();

                $conn->commit();
                header("Location: /quotation/view_quotation.php?id=$quoteId");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Catalog for dropdown
$catalogRes = $conn->query("SELECT id, name, description, unit, price FROM item_list WHERE status = 1 ORDER BY name ASC");
$catalog = [];
while ($row = $catalogRes->fetch_assoc()) {
    $catalog[] = $row;
}

// Fetch customers only for dropdown
$clientsRes = $conn->query("SELECT id, name, address, city, phone, email FROM clients WHERE partner_type = 'customer' ORDER BY name ASC");
$clients = [];
while ($row = $clientsRes->fetch_assoc()) {
    $clients[] = $row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-pencil-simple"></i> Edit Quotation (<?php echo htmlspecialchars($quote['quotation_number']); ?>)</h2>
    <a href="/quotation/quotation.php" class="btn btn-secondary">
        <i class="ph-bold ph-arrow-left"></i> Back to Quotations
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="" id="editQuotationForm">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="ph-bold ph-info"></i> Quotation Details</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Quotation # <small class="text-muted fw-normal">(Locked)</small></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="ph-bold ph-hash"></i></span>
                        <input type="text" class="form-control bg-light fw-bold text-primary" readonly value="<?php echo htmlspecialchars($quote['quotation_number']); ?>" style="cursor: not-allowed;">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Status</label>
                    <select class="form-select" name="status">
                        <option value="draft" <?php echo $quote['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="sent" <?php echo $quote['status'] === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="accepted" <?php echo $quote['status'] === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="rejected" <?php echo $quote['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="expired" <?php echo $quote['status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Valid Until</label>
                    <input type="date" class="form-control" name="valid_until" value="<?php echo htmlspecialchars($quote['valid_until'] ?? ''); ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-bold">Select Company <span class="text-danger">*</span></label>
                    <select class="form-select" id="clientSelect">
                        <option value="">-- Select Company --</option>
                        <?php foreach ($clients as $cl): ?>
                            <option value="<?php echo $cl['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($cl['name']); ?>"
                                    data-email="<?php echo htmlspecialchars($cl['email'] ?? ''); ?>"
                                    data-phone="<?php echo htmlspecialchars($cl['phone'] ?? ''); ?>"
                                    data-address="<?php echo htmlspecialchars(strip_tags($cl['address'] ?? '')); ?>"
                                    data-city="<?php echo htmlspecialchars($cl['city'] ?? ''); ?>"
                                    <?php echo ($quote['client_name'] == $cl['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cl['name']); ?> <?php echo !empty($cl['city']) ? "({$cl['city']})" : ""; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="client_id" id="clientId" value="<?php echo htmlspecialchars($quote['client_id'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Company Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" name="client_name" id="clientName" value="<?php echo htmlspecialchars($quote['client_name']); ?>" readonly tabindex="-1" style="cursor: not-allowed;" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Company Phone</label>
                    <input type="text" class="form-control bg-light" name="client_phone" id="clientPhone" value="<?php echo htmlspecialchars($quote['client_phone'] ?? ''); ?>" readonly tabindex="-1" style="cursor: not-allowed;">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Company Email</label>
                    <input type="email" class="form-control" name="client_email" id="clientEmail" value="<?php echo htmlspecialchars($quote['client_email'] ?? ''); ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Company Address</label>
                    <textarea class="form-control" name="client_address" id="clientAddress" rows="2"><?php echo htmlspecialchars($quote['client_address'] ?? ''); ?></textarea>
                </div>

            </div>
        </div>
    </div>

    <!-- Items Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="ph-bold ph-shopping-cart"></i> Quoted Items</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn">
                <i class="ph-bold ph-plus"></i> Add Custom Row
            </button>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-bordered align-middle" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 30%;">Item / Description</th>
                            <th style="width: 12%;">Unit</th>
                            <th style="width: 15%;">Qty</th>
                            <th style="width: 18%;">Unit Price (₱)</th>
                            <th style="width: 18%;">Line Total (₱)</th>
                            <th style="width: 7%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php foreach ($existingItems as $it): ?>
                        <tr class="item-row">
                            <td>
                                <select class="form-select item-catalog-select mb-2">
                                    <option value="">-- Choose Item from Dropdown --</option>
                                    <?php foreach ($catalog as $catItem): 
                                        $cleanCatalogName = html_entity_decode(str_ireplace('&quot;', '"', $catItem['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    ?>
                                        <option value="<?php echo $catItem['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($cleanCatalogName); ?>"
                                                data-desc="<?php echo htmlspecialchars($catItem['description']); ?>"
                                                data-unit="<?php echo htmlspecialchars($catItem['unit']); ?>"
                                                data-price="<?php echo htmlspecialchars($catItem['price']); ?>"
                                                <?php echo (!empty($it['item_id']) && $it['item_id'] == $catItem['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cleanCatalogName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="item_id[]" class="item-id-input" value="<?php echo htmlspecialchars($it['item_id'] ?? ''); ?>">
                                <input type="text" name="item_name[]" class="form-control item-name-input bg-light" value="<?php echo htmlspecialchars(html_entity_decode(str_ireplace('&quot;', '"', $it['item_name']), ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>" readonly tabindex="-1" style="cursor: not-allowed;" required>
                                <input type="hidden" name="item_desc[]" class="item-desc-input" value="">
                            </td>
                            <td>
                                <input type="text" name="item_unit[]" class="form-control item-unit-input bg-light" value="<?php echo htmlspecialchars($it['unit'] ?? 'PCS'); ?>" readonly tabindex="-1" style="cursor: not-allowed;">
                            </td>
                            <td>
                                <input type="number" step="any" min="1" name="item_qty[]" class="form-control item-qty-input" value="<?php echo htmlspecialchars($it['quantity']); ?>" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="item_price[]" class="form-control item-price-input" value="<?php echo htmlspecialchars($it['unit_price']); ?>" required>
                            </td>
                            <td>
                                <input type="text" class="form-control bg-light fw-bold item-total-display" readonly value="₱<?php echo number_format($it['total_price'], 2); ?>">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn" title="Remove Row">
                                    <i class="ph-bold ph-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Financial Summary Box -->
            <div class="row justify-content-end mt-4">
                <div class="col-md-5">
                    <div class="bg-light p-3 rounded border">
                        <input type="hidden" name="tax_rate" value="0">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <strong id="subtotalDisplay">₱<?php echo number_format($quote['total_amount'], 2); ?></strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fs-5 text-primary">
                            <strong>Grand Total:</strong>
                            <strong id="grandTotalDisplay">₱<?php echo number_format($quote['grand_total'], 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <label class="form-label fw-bold">Terms &amp; Notes</label>
                <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($quote['notes'] ?? ''); ?></textarea>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2 p-3">
            <a href="/quotation/view_quotation.php?id=<?php echo $quote['id']; ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="ph-bold ph-floppy-disk"></i> Save Changes
            </button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Client selection autofill
    const clientSelect = document.getElementById('clientSelect');
    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (opt && opt.value) {
                document.getElementById('clientId').value = opt.value;
                document.getElementById('clientName').value = opt.getAttribute('data-name') || '';
                document.getElementById('clientEmail').value = opt.getAttribute('data-email') || '';
                document.getElementById('clientPhone').value = opt.getAttribute('data-phone') || '';
                document.getElementById('clientAddress').value = opt.getAttribute('data-address') || '';
            } else {
                document.getElementById('clientId').value = '';
                document.getElementById('clientName').value = '';
                document.getElementById('clientEmail').value = '';
                document.getElementById('clientPhone').value = '';
                document.getElementById('clientAddress').value = '';
            }
        });
    }

    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(function(row) {
            const qty = parseFloat(row.querySelector('.item-qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.item-price-input').value) || 0;
            const lineTotal = qty * price;
            row.querySelector('.item-total-display').value = '₱' + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            subtotal += lineTotal;
        });

        const grandTotal = subtotal;

        document.getElementById('subtotalDisplay').textContent = '₱' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('grandTotalDisplay').textContent = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function bindRowEvents(row) {
        const select = row.querySelector('.item-catalog-select');
        if (select) {
            select.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                if (opt && opt.value) {
                    row.querySelector('.item-id-input').value = opt.value;
                    row.querySelector('.item-name-input').value = opt.getAttribute('data-name') || '';
                    row.querySelector('.item-desc-input').value = opt.getAttribute('data-desc') || '';
                    row.querySelector('.item-unit-input').value = opt.getAttribute('data-unit') || '';
                    row.querySelector('.item-price-input').value = parseFloat(opt.getAttribute('data-price') || 0).toFixed(2);
                    calculateTotals();
                } else {
                    row.querySelector('.item-id-input').value = '';
                    row.querySelector('.item-name-input').value = '';
                    row.querySelector('.item-desc-input').value = '';
                    row.querySelector('.item-unit-input').value = '';
                    row.querySelector('.item-price-input').value = '0.00';
                    calculateTotals();
                }
            });
        }

        row.querySelector('.item-qty-input').addEventListener('input', calculateTotals);
        row.querySelector('.item-price-input').addEventListener('input', calculateTotals);
        row.querySelector('.remove-row-btn').addEventListener('click', function() {
            if (document.querySelectorAll('.item-row').length > 1) {
                row.remove();
                calculateTotals();
            } else {
                alert('A quotation must have at least one item.');
            }
        });
    }

    document.querySelectorAll('.item-row').forEach(bindRowEvents);
    // Tax removed — no taxRateInput listener needed

    document.getElementById('addRowBtn').addEventListener('click', function() {
        const tbody = document.getElementById('itemsBody');
        const firstRow = tbody.querySelector('.item-row');
        const newRow = firstRow.cloneNode(true);

        // Reset values to blank
        newRow.querySelector('.item-catalog-select').selectedIndex = 0;
        newRow.querySelector('.item-id-input').value = '';
        newRow.querySelector('.item-name-input').value = '';
        newRow.querySelector('.item-desc-input').value = '';
        newRow.querySelector('.item-unit-input').value = '';
        newRow.querySelector('.item-qty-input').value = '1';
        newRow.querySelector('.item-price-input').value = '0.00';
        newRow.querySelector('.item-total-display').value = '₱0.00';

        tbody.appendChild(newRow);
        bindRowEvents(newRow);
        calculateTotals();
    });

    calculateTotals();
});
</script>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>
