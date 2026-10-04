<?php
$pageTitle = 'Create Quotation';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/quotation_scan.php';
requireLogin();

$conn = getDBConnection();
$error = '';

// Generate next auto-increment Quotation Number (QT-YYYY-XXXX)
$year = date('Y');
$numQry = $conn->query("SELECT quotation_number FROM quotations WHERE quotation_number LIKE 'QT-$year-%'");
$maxSeq = 0;
if ($numQry) {
    while ($row = $numQry->fetch_assoc()) {
        $parts = explode('-', $row['quotation_number']);
        $val = intval(end($parts));
        if ($val > $maxSeq) {
            $maxSeq = $val;
        }
    }
}
$quotationNumber = sprintf('QT-%s-%04d', $year, $maxSeq + 1);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Quotation number is strictly auto-incremented
    $quoteNum = $quotationNumber;
    $clientId = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $clientName = trim($_POST['client_name'] ?? '');
    $clientEmail = trim($_POST['client_email'] ?? '');
    $clientPhone = trim($_POST['client_phone'] ?? '');
    $clientAddress = trim($_POST['client_address'] ?? '');

    $destination = '';
    $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $status = $_POST['status'] ?? 'draft';
    $notes = trim($_POST['notes'] ?? '');
    $taxRate = 0;
    $sopAmount = floatval($_POST['sop_amount'] ?? 0);

    $itemIds = $_POST['item_id'] ?? [];
    $itemNames = $_POST['item_name'] ?? [];
    $itemDescs = $_POST['item_desc'] ?? [];
    $itemUnits = $_POST['item_unit'] ?? [];
    $itemQtys = $_POST['item_qty'] ?? [];
    $itemPrices = $_POST['item_price'] ?? [];
    $itemMarkups = $_POST['item_markup'] ?? [];

    if (empty($clientName)) {
        $error = 'Customer name is required.';
    } elseif (empty($itemNames) || count($itemNames) === 0) {
        $error = 'At least one item must be included in the quotation.';
    } else {
        // Calculate totals
        $subtotal = 0;
        $validItems = [];

        for ($i = 0; $i < count($itemNames); $i++) {
            $name = trim($itemNames[$i] ?? '');
            if (empty($name))
                continue;

            $qty = max(1, floatval($itemQtys[$i] ?? 1));
            $price = max(0, floatval($itemPrices[$i] ?? 0));
            $itemMarkup = max(0, floatval($itemMarkups[$i] ?? 0));
            $lineTotal = $qty * $price * (1 + $itemMarkup / 100);
            $subtotal += $lineTotal;

            $validItems[] = [
                'item_id' => !empty($itemIds[$i]) ? intval($itemIds[$i]) : null,
                'name' => $name,
                'desc' => trim($itemDescs[$i] ?? ''),
                'unit' => trim($itemUnits[$i] ?? 'PCS'),
                'qty' => $qty,
                'price' => $price,
                'markup' => $itemMarkup,
                'total' => $lineTotal
            ];
        }

        if (count($validItems) === 0) {
            $error = 'Please enter at least one valid line item with name and price.';
        } else {
            $taxAmount = 0;
            $markupAmount = 0; // markup is per-item
            $grandTotal = $subtotal + $sopAmount;
            $createdBy = $_SESSION['user_id'] ?? null;

            $conn->begin_transaction();
            try {
                $zeroMarkupRate = 0.0;
                $zeroMarkupAmount = 0.0;
                $stmt = $conn->prepare("INSERT INTO quotations (quotation_number, client_id, client_name, client_email, client_phone, client_address, total_amount, tax_rate, tax_amount, markup_rate, markup_amount, sop_amount, grand_total, valid_until, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sissssdddddddsssi", $quoteNum, $clientId, $clientName, $clientEmail, $clientPhone, $clientAddress, $subtotal, $taxRate, $taxAmount, $zeroMarkupRate, $zeroMarkupAmount, $sopAmount, $grandTotal, $validUntil, $status, $notes, $createdBy);

                if (!$stmt->execute()) {
                    throw new Exception("Error saving quotation header: " . $stmt->error);
                }
                $quoteId = $conn->insert_id;
                $stmt->close();

                // Insert quotation items
                $itemStmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, item_id, item_name, description, unit, quantity, unit_price, markup_rate, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($validItems as $it) {
                    $itemStmt->bind_param("iisssdddd", $quoteId, $it['item_id'], $it['name'], $it['desc'], $it['unit'], $it['qty'], $it['price'], $it['markup'], $it['total']);
                    if (!$itemStmt->execute()) {
                        throw new Exception("Error saving line item: " . $itemStmt->error);
                    }
                }
                $itemStmt->close();

                $conn->commit();
                header("Location: /quotation/view_quotation.php?id=$quoteId&created=1");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Fetch customers only (excluding suppliers)
$clientsRes = $conn->query("SELECT id, name, address, city, phone, email FROM clients WHERE partner_type = 'customer' ORDER BY name ASC");
$clients = [];
while ($row = $clientsRes->fetch_assoc()) {
    $clients[] = $row;
}

// Fetch item catalogue from item_list
$catalogRes = $conn->query("SELECT id, name, description, unit, price FROM item_list WHERE status = 1 ORDER BY name ASC");
$catalog = [];
while ($row = $catalogRes->fetch_assoc()) {
    $catalog[] = $row;
}



require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-file-plus"></i> Create Quotation</h2>
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

<?php if (isQuotationScanEnabled()): ?>
<!-- Scan a quotation image: fills in the form below -->
<div class="card shadow-sm mb-4" id="scanCard">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1">
            <h6 class="mb-1"><i class="ph-bold ph-scan"></i> Scan a quotation</h6>
            <div class="small text-muted">Upload a photo or screenshot of a quotation to fill in the customer and items. Check everything before saving.</div>
        </div>
        <input type="file" id="scanFileInput" accept="image/jpeg,image/png,image/webp,image/gif" class="d-none">
        <button type="button" class="btn btn-outline-primary" id="scanBtn">
            <i class="ph-bold ph-upload-simple"></i> Scan Quotation Image
        </button>
        <div id="scanStatus" class="w-100" aria-live="polite"></div>
    </div>
</div>
<?php endif; ?>

<form method="POST" action="" id="quotationForm">
    <!-- Header Details Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="ph-bold ph-info"></i> Quotation Information</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Quotation #</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="ph-bold ph-hash"></i></span>
                        <input type="text" class="form-control bg-light fw-bold text-primary" name="quotation_number"
                            value="<?php echo htmlspecialchars($quotationNumber); ?>" readonly tabindex="-1"
                            style="cursor: not-allowed;">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Status</label>
                    <select class="form-select" name="status">
                        <option value="draft">Draft</option>
                        <option value="sent" selected>Sent</option>
                        <option value="accepted">Accepted</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Valid Until</label>
                    <input type="date" class="form-control" name="valid_until"
                        value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                </div>

                <!-- Company Select (Customers) -->
                <div class="col-md-12">
                    <label class="form-label fw-bold">Select Company <span class="text-danger">*</span></label>
                    <select class="form-select" id="clientSelect" required>
                        <option value="">-- Select Company --</option>
                        <?php foreach ($clients as $cl): ?>
                            <option value="<?php echo $cl['id']; ?>"
                                data-name="<?php echo htmlspecialchars($cl['name']); ?>"
                                data-email="<?php echo htmlspecialchars($cl['email'] ?? ''); ?>"
                                data-phone="<?php echo htmlspecialchars($cl['phone'] ?? ''); ?>"
                                data-address="<?php echo htmlspecialchars(strip_tags($cl['address'] ?? '')); ?>"
                                data-city="<?php echo htmlspecialchars($cl['city'] ?? ''); ?>">
                                <?php echo htmlspecialchars($cl['name']); ?>
                                <?php echo !empty($cl['city']) ? "({$cl['city']})" : ""; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="client_id" id="clientId">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Company Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-light" name="client_name" id="clientName" value=""
                        readonly tabindex="-1" style="cursor: not-allowed;" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Company Phone</label>
                    <input type="text" class="form-control bg-light" name="client_phone" id="clientPhone" value=""
                        readonly tabindex="-1" style="cursor: not-allowed;">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Company Email</label>
                    <input type="email" class="form-control" name="client_email" id="clientEmail">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Company Address</label>
                    <textarea class="form-control" name="client_address" id="clientAddress" rows="2"></textarea>
                </div>


            </div>
        </div>
    </div>

    <!-- Items Section Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="ph-bold ph-shopping-cart"></i> Quoted Items & Pricing</h5>
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
                            <th style="width: 14%;">Unit Price (₱)</th>
                            <th style="width: 10%;">
                                <div class="d-flex flex-column gap-1">
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="text-nowrap">Mark-up %</span>
                                        <input type="number" step="5" min="0" max="999" id="globalMarkupInput"
                                            class="form-control form-control-sm text-center" style="width:58px;"
                                            value="0" placeholder="%">
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button type="button"
                                            class="btn btn-outline-secondary btn-sm py-0 px-1 global-markup-preset"
                                            data-val="15">15%</button>
                                        <button type="button"
                                            class="btn btn-outline-secondary btn-sm py-0 px-1 global-markup-preset"
                                            data-val="25">25%</button>
                                        <button type="button"
                                            class="btn btn-outline-secondary btn-sm py-0 px-1 global-markup-preset"
                                            data-val="0">None</button>
                                    </div>
                                </div>
                            </th>
                            <th style="width: 14%;">Line Total (₱)</th>
                            <th style="width: 7%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- Initial Row -->
                        <tr class="item-row">
                            <td>
                                <select class="form-select item-catalog-select" required>
                                    <option value="">-- Choose Item from Dropdown --</option>
                                    <?php foreach ($catalog as $catItem):
                                        $cleanCatalogName = html_entity_decode(str_ireplace('&quot;', '"', $catItem['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        ?>
                                        <option value="<?php echo $catItem['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($cleanCatalogName); ?>"
                                            data-desc="<?php echo htmlspecialchars($catItem['description']); ?>"
                                            data-unit="<?php echo htmlspecialchars($catItem['unit']); ?>"
                                            data-price="<?php echo htmlspecialchars($catItem['price']); ?>">
                                            <?php echo htmlspecialchars($cleanCatalogName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="item_id[]" class="item-id-input">
                                <input type="hidden" name="item_name[]" class="item-name-input" value="" required>
                                <input type="hidden" name="item_desc[]" class="item-desc-input" value="">
                            </td>
                            <td>
                                <input type="text" name="item_unit[]" class="form-control item-unit-input bg-light"
                                    value="" readonly tabindex="-1" style="cursor: not-allowed;">
                            </td>
                            <td>
                                <input type="number" step="any" min="1" name="item_qty[]"
                                    class="form-control item-qty-input" value="1" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="item_price[]"
                                    class="form-control item-price-input bg-light" value="0.00" readonly tabindex="-1"
                                    style="cursor: not-allowed;" required>
                            </td>
                            <td>
                                <input type="number" step="5" min="0" max="999" name="item_markup[]"
                                    class="form-control item-markup-input text-center" value="0" placeholder="%">
                            </td>
                            <td>
                                <input type="text" class="form-control bg-light fw-bold item-total-display" readonly
                                    value="₱0.00">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn"
                                    title="Remove Row">
                                    <i class="ph-bold ph-trash"></i>
                                </button>
                            </td>
                        </tr>
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
                            <strong id="subtotalDisplay">₱0.00</strong>
                        </div>

                        <!-- S.O.P. -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>S.O.P. (₱):</span>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end"
                                style="width:120px;" name="sop_amount" id="sopAmountInput" value="0" placeholder="0.00">
                        </div>

                        <hr class="my-2">
                        <div class="d-flex justify-content-between fs-5 text-primary">
                            <strong>Grand Total:</strong>
                            <strong id="grandTotalDisplay">₱0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <label class="form-label fw-bold">Terms &amp; Notes</label>
                <textarea class="form-control" name="notes" rows="3"
                    placeholder="e.g. Quotation is valid for 30 days. Payment terms: 50% downpayment, 50% upon delivery. Delivery lead time: 3-5 business days."></textarea>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2 p-3">
            <a href="/quotation/quotation.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="ph-bold ph-floppy-disk"></i> Save &amp; Generate Quotation
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Client selection autofill
        const clientSelect = document.getElementById('clientSelect');
        clientSelect.addEventListener('change', function () {
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

        // Row calculations
        function calculateTotals() {
            let subtotal = 0;
            document.querySelectorAll('.item-row').forEach(function (row) {
                const qty = parseFloat(row.querySelector('.item-qty-input').value) || 0;
                const price = parseFloat(row.querySelector('.item-price-input').value) || 0;
                const markup = parseFloat(row.querySelector('.item-markup-input').value) || 0;
                const lineTotal = qty * price * (1 + markup / 100);
                row.querySelector('.item-total-display').value = '₱' + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                subtotal += lineTotal;
            });

            const sopAmount = parseFloat(document.getElementById('sopAmountInput').value) || 0;
            const grandTotal = subtotal + sopAmount;

            document.getElementById('subtotalDisplay').textContent = '₱' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('grandTotalDisplay').textContent = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                    // A scanned row keeps the unit cost read from the quotation image
                    const price = row.dataset.scannedPrice !== undefined ? row.dataset.scannedPrice : (opt.getAttribute('data-price') || 0);
                    row.querySelector('.item-price-input').value = parseFloat(price).toFixed(2);
                    row.classList.remove('table-warning');
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

            row.querySelector('.item-qty-input').addEventListener('input', calculateTotals);
            row.querySelector('.item-price-input').addEventListener('input', calculateTotals);
            row.querySelector('.item-markup-input').addEventListener('input', calculateTotals);

            row.querySelector('.remove-row-btn').addEventListener('click', function () {
                if (document.querySelectorAll('.item-row').length > 1) {
                    row.remove();
                    calculateTotals();
                } else {
                    alert('A quotation must have at least one line item.');
                }
            });
        }

        // Bind initial row
        document.querySelectorAll('.item-row').forEach(bindRowEvents);
        document.getElementById('sopAmountInput').addEventListener('input', calculateTotals);

        // Global markup — applies to ALL rows
        function applyGlobalMarkup(val) {
            document.querySelectorAll('.item-markup-input').forEach(function (input) {
                input.value = val;
            });
            calculateTotals();
        }

        document.getElementById('globalMarkupInput').addEventListener('input', function () {
            applyGlobalMarkup(this.value);
        });

        document.querySelectorAll('.global-markup-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('globalMarkupInput').value = this.dataset.val;
                applyGlobalMarkup(this.dataset.val);
            });
        });

        // Add new row button
        function addRow() {
            const tbody = document.getElementById('itemsBody');
            const firstRow = tbody.querySelector('.item-row');
            const newRow = firstRow.cloneNode(true);

            // Reset values to blank
            newRow.classList.remove('table-warning');
            delete newRow.dataset.scannedPrice;
            newRow.querySelectorAll('.scan-hint').forEach(function (el) { el.remove(); });
            newRow.querySelector('.item-catalog-select').selectedIndex = 0;
            newRow.querySelector('.item-id-input').value = '';
            newRow.querySelector('.item-name-input').value = '';
            newRow.querySelector('.item-desc-input').value = '';
            newRow.querySelector('.item-unit-input').value = '';
            newRow.querySelector('.item-qty-input').value = '1';
            newRow.querySelector('.item-price-input').value = '0.00';
            // Inherit current global markup
            const globalVal = document.getElementById('globalMarkupInput').value || '0';
            newRow.querySelector('.item-markup-input').value = globalVal;
            newRow.querySelector('.item-total-display').value = '₱0.00';

            tbody.appendChild(newRow);
            bindRowEvents(newRow);
            calculateTotals();
            return newRow;
        }
        document.getElementById('addRowBtn').addEventListener('click', addRow);

        calculateTotals();

        // ---- Scan a quotation image ----
        const scanBtn = document.getElementById('scanBtn');
        if (!scanBtn) {
            return;
        }
        const scanInput = document.getElementById('scanFileInput');
        const scanStatus = document.getElementById('scanStatus');
        const peso = function (n) {
            return '₱' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        function showScanStatus(type, lines) {
            scanStatus.innerHTML = '';
            const box = document.createElement('div');
            box.className = 'alert alert-' + type + ' mb-0 mt-2 small';
            lines.forEach(function (line, i) {
                const div = document.createElement('div');
                if (i > 0) div.className = 'mt-1';
                div.textContent = line;
                box.appendChild(div);
            });
            scanStatus.appendChild(box);
        }

        // Shrink large photos before upload: the model reads at most 2576 px on the long
        // edge, and the API accepts images up to 5 MB
        async function prepareImage(file) {
            try {
                const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                const scale = Math.min(1, 2576 / Math.max(bitmap.width, bitmap.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(bitmap.width * scale);
                canvas.height = Math.round(bitmap.height * scale);
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                for (const quality of [0.9, 0.75, 0.6]) {
                    const blob = await new Promise(function (resolve) { canvas.toBlob(resolve, 'image/jpeg', quality); });
                    if (blob && blob.size <= 5 * 1024 * 1024) {
                        return blob;
                    }
                }
            } catch (e) {
                // Browser could not decode it here; let the server check the original file
            }
            return file;
        }

        function rowHasItem(row) {
            return row.querySelector('.item-catalog-select').value !== '';
        }

        function fillFromScan(result) {
            const scan = result.extraction || {};
            const match = result.match || { client_id: null, items: [] };
            const notes = [];
            let warn = false;

            // Customer
            if (match.client_id) {
                clientSelect.value = String(match.client_id);
                clientSelect.dispatchEvent(new Event('change'));
            } else if (scan.customer) {
                notes.push('Customer "' + scan.customer + '" is not in Business Partners. Select the company yourself (or add it under Business Partners first).');
                warn = true;
            } else {
                notes.push('No customer could be read. Select the company yourself.');
                warn = true;
            }
            if (scan.address) {
                document.getElementById('clientAddress').value = scan.address;
            }

            // Items: replace the current rows with the scanned ones
            const items = Array.isArray(scan.items) ? scan.items : [];
            const tbody = document.getElementById('itemsBody');
            const rows = tbody.querySelectorAll('.item-row');
            rows.forEach(function (row, i) {
                if (i > 0) row.remove();
            });
            const firstRow = tbody.querySelector('.item-row');
            const firstSelect = firstRow.querySelector('.item-catalog-select');
            firstSelect.value = '';
            firstSelect.dispatchEvent(new Event('change'));
            firstRow.classList.remove('table-warning');
            delete firstRow.dataset.scannedPrice;
            firstRow.querySelectorAll('.scan-hint').forEach(function (el) { el.remove(); });

            let unmatched = 0;
            items.forEach(function (item, i) {
                const row = i === 0 ? firstRow : addRow();
                if (item.unit_cost !== null && item.unit_cost !== undefined) {
                    row.dataset.scannedPrice = String(item.unit_cost);
                }
                const select = row.querySelector('.item-catalog-select');
                const catalogId = match.items ? match.items[i] : null;
                if (catalogId) {
                    select.value = String(catalogId);
                    select.dispatchEvent(new Event('change'));
                } else {
                    unmatched++;
                    row.classList.add('table-warning');
                    const hint = document.createElement('div');
                    hint.className = 'scan-hint small text-danger mt-1';
                    hint.textContent = 'Scanned: ' + (item.item_description || '(no description)') + ' - choose the matching item';
                    select.parentNode.insertBefore(hint, select.nextSibling);
                }
                row.querySelector('.item-qty-input').value = item.quantity || 1;
                row.querySelector('.item-markup-input').value = 0;
            });
            document.getElementById('globalMarkupInput').value = 0;
            calculateTotals();

            if (items.length === 0) {
                notes.push('No item rows could be read from the image.');
                warn = true;
            } else {
                notes.push(items.length + ' item' + (items.length === 1 ? '' : 's') + ' read from the image.');
            }
            if (unmatched > 0) {
                notes.push(unmatched + ' item' + (unmatched === 1 ? ' is' : 's are') + ' not in Inventory (highlighted). Choose the matching item for each; the scanned quantity and unit cost are kept.');
                warn = true;
            }

            // Compare the form's total with the total printed on the quotation
            const grandText = document.getElementById('grandTotalDisplay').textContent.replace(/[^0-9.]/g, '');
            const grand = parseFloat(grandText) || 0;
            if (scan.total !== null && scan.total !== undefined && unmatched === 0) {
                if (Math.abs(grand - scan.total) < 0.01) {
                    notes.push('Total matches the quotation: ' + peso(scan.total) + '.');
                } else {
                    notes.push('The quotation shows a total of ' + peso(scan.total) + ' but the items add up to ' + peso(grand) + '. Check the quantities and prices.');
                    warn = true;
                }
            }
            if (scan.quote_number || scan.date) {
                notes.push('Scanned quotation ' + (scan.quote_number || '') + (scan.date ? ' dated ' + scan.date : '') + '. This quotation gets its own number when saved.');
            }
            showScanStatus(warn ? 'warning' : 'success', notes);
        }

        scanBtn.addEventListener('click', function () {
            const hasItems = Array.from(document.querySelectorAll('.item-row')).some(rowHasItem);
            if (hasItems && !confirm('Scanning replaces the items you have entered. Continue?')) {
                return;
            }
            scanInput.value = '';
            scanInput.click();
        });

        scanInput.addEventListener('change', async function () {
            const file = scanInput.files[0];
            if (!file) return;
            scanBtn.disabled = true;
            showScanStatus('info', ['Reading the quotation... this can take up to a minute.']);
            try {
                const image = await prepareImage(file);
                const body = new FormData();
                body.append('image', image, 'quotation.jpg');
                body.append('csrf_token', <?php echo json_encode(getCsrfToken()); ?>);
                const response = await fetch('/quotation/scan_quotation.php', { method: 'POST', body: body, credentials: 'same-origin' });
                let result;
                try {
                    result = await response.json();
                } catch (e) {
                    throw new Error('The server returned an unexpected response.');
                }
                if (!result.ok) {
                    throw new Error(result.error || 'The quotation could not be scanned.');
                }
                fillFromScan(result);
            } catch (e) {
                showScanStatus('danger', [e.message || 'The quotation could not be scanned.']);
            } finally {
                scanBtn.disabled = false;
            }
        });
    });
</script>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>