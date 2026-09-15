<?php
$pageTitle = 'Stock Management';
require_once __DIR__ . '/../config.php';
requireLogin();

$conn = getDBConnection();

$success = '';
$error = '';

// Handle quick stock adjustment from modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    $itemId = intval($_POST['item_id'] ?? 0);
    $adjustmentType = $_POST['adjustment_type'] ?? 'add'; // 'add', 'subtract', 'set'
    $quantity = floatval($_POST['quantity'] ?? 0);

    if ($itemId > 0 && $quantity >= 0) {
        $itemCheck = $conn->prepare("SELECT id, name, stocks FROM item_list WHERE id = ?");
        $itemCheck->bind_param("i", $itemId);
        $itemCheck->execute();
        $itemRes = $itemCheck->get_result();

        if ($itemRes->num_rows > 0) {
            $itemData = $itemRes->fetch_assoc();
            $currentStock = floatval($itemData['stocks']);
            $newStock = $currentStock;

            if ($adjustmentType === 'add') {
                $newStock = $currentStock + $quantity;
            } elseif ($adjustmentType === 'subtract') {
                $newStock = max(0, $currentStock - $quantity);
            } elseif ($adjustmentType === 'set') {
                $newStock = max(0, $quantity);
            }

            $updateStmt = $conn->prepare("UPDATE item_list SET stocks = ? WHERE id = ?");
            $updateStmt->bind_param("di", $newStock, $itemId);
            if ($updateStmt->execute()) {
                $success = "Stock for " . htmlspecialchars($itemData['name']) . " updated to " . number_format($newStock, 0) . ".";
            } else {
                $error = "Failed to update stock: " . $conn->error;
            }
            $updateStmt->close();
        } else {
            $error = "Item not found.";
        }
        $itemCheck->close();
    } else {
        $error = "Invalid item or quantity.";
    }
}

// Search and filter
$search = trim($_GET['search'] ?? '');
$stockStatus = $_GET['stock_status'] ?? '';
$unitFilter = trim($_GET['unit'] ?? '');
$priceSort = $_GET['price_sort'] ?? '';
$stockSort = $_GET['stock_sort'] ?? '';

$allowedUnits = ['PCS', 'SET', 'ASS', 'FEET', 'MTR'];

// KPI statistics
$totalItemsQuery = $conn->query("SELECT COUNT(*) as total FROM item_list");
$totalItems = $totalItemsQuery->fetch_assoc()['total'] ?? 0;

$inStockQuery = $conn->query("SELECT COUNT(*) as total FROM item_list WHERE stocks > 10");
$inStockCount = $inStockQuery->fetch_assoc()['total'] ?? 0;

$lowStockQuery = $conn->query("SELECT COUNT(*) as total FROM item_list WHERE stocks > 0 AND stocks <= 10");
$lowStockCount = $lowStockQuery->fetch_assoc()['total'] ?? 0;

$outOfStockQuery = $conn->query("SELECT COUNT(*) as total FROM item_list WHERE stocks <= 0");
$outOfStockCount = $outOfStockQuery->fetch_assoc()['total'] ?? 0;

// Build query with chosen sort
if ($stockSort === 'asc') {
    $query = "SELECT * FROM item_list ORDER BY stocks ASC, TRIM(name) ASC";
} elseif ($stockSort === 'desc') {
    $query = "SELECT * FROM item_list ORDER BY stocks DESC, TRIM(name) ASC";
} elseif ($priceSort === 'asc') {
    $query = "SELECT * FROM item_list ORDER BY price ASC, TRIM(name) ASC";
} elseif ($priceSort === 'desc') {
    $query = "SELECT * FROM item_list ORDER BY price DESC, TRIM(name) ASC";
} else {
    $query = "SELECT * FROM item_list ORDER BY TRIM(name) ASC";
}

$result = $conn->query($query);

function buildStockSortUrl($type, $targetDir, $currentPriceSort, $currentStockSort)
{
    $params = [];
    if ($type === 'price') {
        if ($currentPriceSort !== $targetDir) {
            $params['price_sort'] = $targetDir;
        }
    } elseif ($type === 'stock') {
        if ($currentStockSort !== $targetDir) {
            $params['stock_sort'] = $targetDir;
        }
    }
    return '/stocks/stocks.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-stack"></i> Stock Management</h2>
    <div>
        <a href="/stocks/add_stock.php" class="btn btn-primary me-2">
            <i class="ph-bold ph-plus-circle"></i> Add / Receive Stock
        </a>
        <a href="/items/items.php" class="btn btn-outline-dark">
            <i class="ph-bold ph-archive"></i> Item Inventory
        </a>
    </div>
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

<!-- Statistics KPI Cards -->
<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="card stat-card text-white shadow-sm h-100"
            style="background: var(--zero-black); border: 1px solid rgba(215, 255, 224, 0.2);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50 small">Total Items</h6>
                        <h2 class="mb-0 text-white"><?php echo number_format($totalItems); ?></h2>
                    </div>
                    <i class="ph-bold ph-stack fs-1" style="color: var(--ghost-green); opacity: 0.85;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white shadow-sm h-100"
            style="background: #0d1f14; border: 1px solid rgba(215, 255, 224, 0.3);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small" style="color: rgba(215, 255, 224, 0.7);">In Stock (&gt; 10)
                        </h6>
                        <h2 class="mb-0" style="color: var(--ghost-green);"><?php echo number_format($inStockCount); ?>
                        </h2>
                    </div>
                    <i class="ph-bold ph-check-circle fs-1" style="color: var(--ghost-green);"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card shadow-sm h-100"
            style="background: #2a2205; border: 1px solid rgba(251, 191, 36, 0.3); color: #fde68a;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small" style="color: #fcd34d;">Low Stock (&le; 10)</h6>
                        <h2 class="mb-0" style="color: #fbbf24;"><?php echo number_format($lowStockCount); ?></h2>
                    </div>
                    <i class="ph-bold ph-warning fs-1" style="color: #fbbf24;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white shadow-sm h-100"
            style="background: #200d0d; border: 1px solid rgba(248, 113, 113, 0.3);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small" style="color: #fca5a5;">Out of Stock (0)</h6>
                        <h2 class="mb-0" style="color: #f87171;"><?php echo number_format($outOfStockCount); ?></h2>
                    </div>
                    <i class="ph-bold ph-x-circle fs-1" style="color: #f87171;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="ph-bold ph-magnifying-glass"></i></span>
                    <input type="text" class="form-control" id="stockSearchInput" placeholder="Search..."
                        autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" id="clearStockSearchBtn"
                        style="display: none;" title="Clear search">
                        <i class="ph-bold ph-x"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Unit</label>
                <select class="form-select" id="stockUnitSelect">
                    <option value="">All Units</option>
                    <?php foreach ($allowedUnits as $u): ?>
                        <option value="<?php echo $u; ?>"><?php echo $u; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Stock Level</label>
                <select class="form-select" id="stockStatusSelect">
                    <option value="">All Stock Levels</option>
                    <option value="in_stock">In Stock (&gt; 10)</option>
                    <option value="low_stock">Low Stock (&le; 10)</option>
                    <option value="out_of_stock">Out of Stock (0)</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" id="resetStockFiltersBtn" class="btn btn-secondary w-100"
                    title="Reset All Filters">
                    <i class="ph-bold ph-arrow-counter-clockwise"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Count Indicator -->
<div class="d-flex justify-content-between align-items-center mb-2 px-1">
    <div class="small text-muted">
        Showing <strong id="stockCountDisplay"><?php echo $result->num_rows; ?></strong>
        item<?php echo $result->num_rows != 1 ? 's' : ''; ?>
    </div>
</div>

<!-- Stock Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 12%;">Item Code</th>
                        <th style="width: 30%;">Name</th>
                        <th style="width: 8%;">Unit</th>
                        <th style="width: 16%;">
                            <div class="d-inline-flex align-items-center gap-2">
                                <span>Unit Price</span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Sort by Price">
                                    <!-- Arrow Down: Lowest Price first -->
                                    <a href="<?php echo htmlspecialchars(buildStockSortUrl('price', 'asc', $priceSort, $stockSort)); ?>"
                                        class="btn btn-sm <?php echo $priceSort === 'asc' ? 'btn-dark active' : 'btn-outline-secondary'; ?>"
                                        style="<?php echo $priceSort === 'asc' ? 'background: var(--zero-black); color: var(--ghost-green); border-color: var(--zero-black);' : ''; ?> padding: 2px 7px; font-size: 0.78rem; line-height: 1;"
                                        title="Arrow Down: Lowest Price First">
                                        <i class="ph-bold ph-arrow-down"></i>
                                    </a>
                                    <!-- Arrow Up: Highest Price first -->
                                    <a href="<?php echo htmlspecialchars(buildStockSortUrl('price', 'desc', $priceSort, $stockSort)); ?>"
                                        class="btn btn-sm <?php echo $priceSort === 'desc' ? 'btn-dark active' : 'btn-outline-secondary'; ?>"
                                        style="<?php echo $priceSort === 'desc' ? 'background: var(--zero-black); color: var(--ghost-green); border-color: var(--zero-black);' : ''; ?> padding: 2px 7px; font-size: 0.78rem; line-height: 1;"
                                        title="Arrow Up: Highest Price First">
                                        <i class="ph-bold ph-arrow-up"></i>
                                    </a>
                                </div>
                            </div>
                        </th>
                        <th style="width: 17%;">
                            <div class="d-inline-flex align-items-center gap-2">
                                <span>Current Stock</span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Sort by Stocks">
                                    <!-- Arrow Down: Lowest Stocks first -->
                                    <a href="<?php echo htmlspecialchars(buildStockSortUrl('stock', 'asc', $priceSort, $stockSort)); ?>"
                                        class="btn btn-sm <?php echo $stockSort === 'asc' ? 'btn-dark active' : 'btn-outline-secondary'; ?>"
                                        style="<?php echo $stockSort === 'asc' ? 'background: var(--zero-black); color: var(--ghost-green); border-color: var(--zero-black);' : ''; ?> padding: 2px 7px; font-size: 0.78rem; line-height: 1;"
                                        title="Arrow Down: Lowest Stocks First">
                                        <i class="ph-bold ph-arrow-down"></i>
                                    </a>
                                    <!-- Arrow Up: Highest Stocks first -->
                                    <a href="<?php echo htmlspecialchars(buildStockSortUrl('stock', 'desc', $priceSort, $stockSort)); ?>"
                                        class="btn btn-sm <?php echo $stockSort === 'desc' ? 'btn-dark active' : 'btn-outline-secondary'; ?>"
                                        style="<?php echo $stockSort === 'desc' ? 'background: var(--zero-black); color: var(--ghost-green); border-color: var(--zero-black);' : ''; ?> padding: 2px 7px; font-size: 0.78rem; line-height: 1;"
                                        title="Arrow Up: Highest Stocks First">
                                        <i class="ph-bold ph-arrow-up"></i>
                                    </a>
                                </div>
                            </div>
                        </th>
                        <th style="width: 8%;">Status</th>
                        <th style="width: 9%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="stockTableBody">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($item = $result->fetch_assoc()):
                            $stockQty = floatval($item['stocks']);
                            if ($stockQty <= 0) {
                                $badgeClass = 'bg-danger';
                                $statusText = 'Out of Stock';
                                $stockLevelKey = 'out_of_stock';
                            } elseif ($stockQty <= 10) {
                                $badgeClass = 'bg-warning text-dark';
                                $statusText = 'Low Stock';
                                $stockLevelKey = 'low_stock';
                            } else {
                                $badgeClass = 'bg-success';
                                $statusText = 'In Stock';
                                $stockLevelKey = 'in_stock';
                            }
                            $cleanName = html_entity_decode(str_ireplace('&quot;', '"', $item['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            ?>
                            <tr class="stock-item-row"
                                data-code="<?php echo htmlspecialchars(strtolower($item['description'])); ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($cleanName)); ?>"
                                data-unit="<?php echo htmlspecialchars(strtoupper(trim($item['unit']))); ?>"
                                data-stock-status="<?php echo $stockLevelKey; ?>">
                                <td><strong><?php echo htmlspecialchars($item['description']); ?></strong></td>
                                <td><?php echo htmlspecialchars($cleanName); ?></td>
                                <td><span
                                        class="badge bg-light text-dark border"><?php echo htmlspecialchars($item['unit']); ?></span>
                                </td>
                                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?> fs-6">
                                        <?php echo number_format($stockQty, 0); ?>
                                        <?php echo htmlspecialchars($item['unit']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal"
                                            data-bs-target="#adjustStockModal" data-id="<?php echo $item['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($cleanName); ?>"
                                            data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                            data-stock="<?php echo $stockQty; ?>" title="Quick Adjust Stock">
                                            <i class="ph-bold ph-sliders-horizontal"></i> Adjust
                                        </button>
                                        <a href="/stocks/edit_stock.php?id=<?php echo $item['id']; ?>"
                                            class="btn btn-outline-secondary" title="Detailed Edit">
                                            <i class="ph-bold ph-pencil-simple"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    <tr id="noLiveStockMatchesRow"
                        style="display: <?php echo $result->num_rows === 0 ? '' : 'none'; ?>;">
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="ph-bold ph-tray fs-1 d-block mb-2"></i>
                            No stock items match the search.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Quick Adjust Stock -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <input type="hidden" name="action" value="adjust_stock">
            <input type="hidden" name="item_id" id="modalItemId">

            <div class="modal-header">
                <h5 class="modal-title" id="adjustStockModalLabel"><i class="ph-bold ph-sliders"></i> Quick Stock
                    Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label text-muted">Item Name</label>
                    <div class="fw-bold fs-6" id="modalItemName">-</div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Current Stock Level</label>
                    <div class="fw-bold fs-5 text-primary" id="modalCurrentStock">0</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Adjustment Type</label>
                    <select class="form-select" name="adjustment_type" id="modalAdjustmentType" required>
                        <option value="add">Add Stock (Receive / Restock +)</option>
                        <option value="subtract">Deduct Stock (Damage / Disposal -)</option>
                        <option value="set">Set Exact Stock Count (=)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="modalQuantity">Quantity</label>
                    <input type="number" step="any" min="0" class="form-control" name="quantity" id="modalQuantity"
                        required placeholder="Enter quantity">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="ph-bold ph-check"></i> Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var adjustModal = document.getElementById('adjustStockModal');
        if (adjustModal) {
            adjustModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var name = button.getAttribute('data-name');
                var unit = button.getAttribute('data-unit');
                var stock = button.getAttribute('data-stock');

                document.getElementById('modalItemId').value = id;
                document.getElementById('modalItemName').textContent = name;
                document.getElementById('modalCurrentStock').textContent = stock + ' ' + unit;
                document.getElementById('modalQuantity').value = '';
            });
        }

        // Instant auto-filtering on search, unit, and stock status
        const stockSearchInput = document.getElementById('stockSearchInput');
        const clearStockBtn = document.getElementById('clearStockSearchBtn');
        const stockUnitSelect = document.getElementById('stockUnitSelect');
        const stockStatusSelect = document.getElementById('stockStatusSelect');
        const resetStockFiltersBtn = document.getElementById('resetStockFiltersBtn');
        const stockRows = document.querySelectorAll('.stock-item-row');
        const noStockMatchRow = document.getElementById('noLiveStockMatchesRow');
        const stockCountDisplay = document.getElementById('stockCountDisplay');

        function filterStockItems() {
            const term = (stockSearchInput ? stockSearchInput.value : '').trim().toLowerCase();
            const selectedUnit = (stockUnitSelect ? stockUnitSelect.value : '').trim().toUpperCase();
            const selectedStatus = (stockStatusSelect ? stockStatusSelect.value : '').trim();
            let matchCount = 0;

            if (clearStockBtn) {
                clearStockBtn.style.display = term.length > 0 ? 'inline-block' : 'none';
            }

            stockRows.forEach(row => {
                const code = row.getAttribute('data-code') || '';
                const name = row.getAttribute('data-name') || '';
                const unit = row.getAttribute('data-unit') || '';
                const stockStatus = row.getAttribute('data-stock-status') || '';

                const matchesSearch = term === '' || code.includes(term) || name.includes(term);
                const matchesUnit = selectedUnit === '' || unit === selectedUnit;
                const matchesStatus = selectedStatus === '' || stockStatus === selectedStatus;

                if (matchesSearch && matchesUnit && matchesStatus) {
                    row.style.display = '';
                    matchCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noStockMatchRow) {
                noStockMatchRow.style.display = (matchCount === 0) ? '' : 'none';
            }

            if (stockCountDisplay) {
                stockCountDisplay.textContent = matchCount;
            }
        }

        if (stockSearchInput) {
            stockSearchInput.addEventListener('input', filterStockItems);
            stockSearchInput.addEventListener('keyup', filterStockItems);
            stockSearchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }

        if (stockUnitSelect) stockUnitSelect.addEventListener('change', filterStockItems);
        if (stockStatusSelect) stockStatusSelect.addEventListener('change', filterStockItems);

        if (clearStockBtn) {
            clearStockBtn.addEventListener('click', function () {
                if (stockSearchInput) {
                    stockSearchInput.value = '';
                    stockSearchInput.focus();
                    filterStockItems();
                }
            });
        }

        if (resetStockFiltersBtn) {
            resetStockFiltersBtn.addEventListener('click', function () {
                if (stockSearchInput) stockSearchInput.value = '';
                if (stockUnitSelect) stockUnitSelect.value = '';
                if (stockStatusSelect) stockStatusSelect.value = '';
                filterStockItems();
            });
        }
    });
</script>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>