<?php
$pageTitle = 'Inventory';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

$allowedUnits = ['PCS', 'SET', 'ASS', 'FEET', 'MTR'];

$priceSort = $_GET['price_sort'] ?? '';
$stockSort = $_GET['stock_sort'] ?? '';

// Sorting logic:
// Default: Alphabetical based on its name (TRIM(name) ASC)
// Arrow Down (asc): lowest first
// Arrow Up (desc): highest first
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

function buildSortUrl($type, $targetDir, $currentPriceSort, $currentStockSort)
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
    return 'items.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

$flashSuccess = $_SESSION['success_message'] ?? ($_GET['success'] ?? '');
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-archive"></i> Item Inventory</h2>
    <a href="/items/add_item.php" class="btn btn-primary">
        <i class="ph-bold ph-plus-circle"></i> Add New Item
    </a>
</div>

<?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
        <i class="ph-bold ph-check-circle fs-5 me-1"></i> <?php echo htmlspecialchars($flashSuccess); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="ph-bold ph-magnifying-glass"></i></span>
                    <input type="text" class="form-control" id="itemSearchInput" placeholder="Search..."
                        autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" style="display: none;"
                        title="Clear search">
                        <i class="ph-bold ph-x"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Unit</label>
                <select class="form-select" id="unitFilterSelect">
                    <option value="">All Units</option>
                    <?php foreach ($allowedUnits as $u): ?>
                        <option value="<?php echo $u; ?>"><?php echo $u; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Status</label>
                <select class="form-select" id="statusFilterSelect">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" id="resetFiltersBtn" class="btn btn-secondary w-100" title="Reset All Filters">
                    <i class="ph-bold ph-arrow-clockwise"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Count Indicator -->
<div class="d-flex justify-content-between align-items-center mb-2 px-1">
    <div class="small text-muted">
        Showing <strong id="itemCountDisplay"><?php echo $result->num_rows; ?></strong>
        item<?php echo $result->num_rows != 1 ? 's' : ''; ?>
    </div>
</div>

<!-- Items Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 12%;">Code</th>
                        <th style="width: 32%;">Name</th>
                        <th style="width: 8%;">Unit</th>
                        <th style="width: 15%;">
                            <div class="d-inline-flex align-items-center gap-2">
                                <span>Price</span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Sort by Price">
                                    <!-- Arrow Down: Lowest Price first -->
                                    <a href="<?php echo htmlspecialchars(buildSortUrl('price', 'asc', $priceSort, $stockSort)); ?>"
                                        class="btn btn-sm <?php echo $priceSort === 'asc' ? 'btn-dark active' : 'btn-outline-secondary'; ?>"
                                        style="<?php echo $priceSort === 'asc' ? 'background: var(--zero-black); color: var(--ghost-green); border-color: var(--zero-black);' : ''; ?> padding: 2px 7px; font-size: 0.78rem; line-height: 1;"
                                        title="Arrow Down: Lowest Price First">
                                        <i class="ph-bold ph-arrow-down"></i>
                                    </a>
                                    <!-- Arrow Up: Highest Price first -->
                                    <a href="<?php echo htmlspecialchars(buildSortUrl('price', 'desc', $priceSort, $stockSort)); ?>"
                                        class="btn btn-sm <?php echo $priceSort === 'desc' ? 'btn-dark active' : 'btn-outline-secondary'; ?>"
                                        style="<?php echo $priceSort === 'desc' ? 'background: var(--zero-black); color: var(--ghost-green); border-color: var(--zero-black);' : ''; ?> padding: 2px 7px; font-size: 0.78rem; line-height: 1;"
                                        title="Arrow Up: Highest Price First">
                                        <i class="ph-bold ph-arrow-up"></i>
                                    </a>
                                </div>
                            </div>
                        </th>
                        <th style="width: 15%;">
                            <div class="d-inline-flex align-items-center gap-2">
                                <span>Stocks</span>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Sort by Stocks">
                                    <!-- Arrow Down: Lowest Stocks first -->
                                    <a href="<?php echo htmlspecialchars(buildSortUrl('stock', 'asc', $priceSort, $stockSort)); ?>"
                                        class="btn btn-sm <?php echo $stockSort === 'asc' ? 'btn-dark active' : 'btn-outline-secondary'; ?>"
                                        style="<?php echo $stockSort === 'asc' ? 'background: var(--zero-black); color: var(--ghost-green); border-color: var(--zero-black);' : ''; ?> padding: 2px 7px; font-size: 0.78rem; line-height: 1;"
                                        title="Arrow Down: Lowest Stocks First">
                                        <i class="ph-bold ph-arrow-down"></i>
                                    </a>
                                    <!-- Arrow Up: Highest Stocks first -->
                                    <a href="<?php echo htmlspecialchars(buildSortUrl('stock', 'desc', $priceSort, $stockSort)); ?>"
                                        class="btn btn-sm <?php echo $stockSort === 'desc' ? 'btn-dark active' : 'btn-outline-secondary'; ?>"
                                        style="<?php echo $stockSort === 'desc' ? 'background: var(--zero-black); color: var(--ghost-green); border-color: var(--zero-black);' : ''; ?> padding: 2px 7px; font-size: 0.78rem; line-height: 1;"
                                        title="Arrow Up: Highest Stocks First">
                                        <i class="ph-bold ph-arrow-up"></i>
                                    </a>
                                </div>
                            </div>
                        </th>
                        <th style="width: 8%;">Status</th>
                        <th style="width: 10%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($item = $result->fetch_assoc()):
                            $cleanName = html_entity_decode(str_ireplace('&quot;', '"', $item['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            ?>
                            <tr class="item-row" data-code="<?php echo htmlspecialchars(strtolower($item['description'])); ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($cleanName)); ?>"
                                data-unit="<?php echo htmlspecialchars(strtoupper(trim($item['unit']))); ?>"
                                data-status="<?php echo $item['status']; ?>">
                                <td><strong><?php echo htmlspecialchars($item['description']); ?></strong></td>
                                <td><?php echo htmlspecialchars($cleanName); ?></td>
                                <td><span
                                        class="badge bg-light text-dark border"><?php echo htmlspecialchars($item['unit']); ?></span>
                                </td>
                                <td class="fw-bold">₱<?php echo number_format($item['price'], 2); ?></td>
                                <td>
                                    <span
                                        class="badge bg-<?php echo $item['stocks'] > 10 ? 'success' : ($item['stocks'] > 0 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($item['stocks'], 0); ?>
                                        <?php echo htmlspecialchars($item['unit']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $item['status'] == 1 ? 'success' : 'secondary'; ?>">
                                        <?php echo $item['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="/items/edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-warning"
                                            title="Edit">
                                            <i class="ph-bold ph-pencil-simple"></i>
                                        </a>
                                        <form action="/items/delete_item.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Delete">
                                                <i class="ph-bold ph-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    <tr id="noLiveMatchesRow" style="display: <?php echo $result->num_rows === 0 ? '' : 'none'; ?>;">
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="ph-bold ph-tray fs-1 d-block mb-2"></i>
                            No items found matching the selected filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('itemSearchInput');
        const clearBtn = document.getElementById('clearSearchBtn');
        const unitSelect = document.getElementById('unitFilterSelect');
        const statusSelect = document.getElementById('statusFilterSelect');
        const resetBtn = document.getElementById('resetFiltersBtn');
        const rows = document.querySelectorAll('.item-row');
        const noMatchRow = document.getElementById('noLiveMatchesRow');
        const countDisplay = document.getElementById('itemCountDisplay');

        function filterItems() {
            const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
            const selectedUnit = (unitSelect ? unitSelect.value : '').trim().toUpperCase();
            const selectedStatus = (statusSelect ? statusSelect.value : '').trim();
            let matchCount = 0;

            if (clearBtn) {
                clearBtn.style.display = term.length > 0 ? 'inline-block' : 'none';
            }

            rows.forEach(row => {
                const code = row.getAttribute('data-code') || '';
                const name = row.getAttribute('data-name') || '';
                const unit = row.getAttribute('data-unit') || '';
                const status = row.getAttribute('data-status') || '';

                const matchesSearch = term === '' || code.includes(term) || name.includes(term);
                const matchesUnit = selectedUnit === '' || unit === selectedUnit;
                const matchesStatus = selectedStatus === '' || status === selectedStatus;

                if (matchesSearch && matchesUnit && matchesStatus) {
                    row.style.display = '';
                    matchCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noMatchRow) {
                noMatchRow.style.display = (matchCount === 0) ? '' : 'none';
            }

            if (countDisplay) {
                countDisplay.textContent = matchCount;
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterItems);
            searchInput.addEventListener('keyup', filterItems);
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }

        if (unitSelect) {
            unitSelect.addEventListener('change', filterItems);
        }

        if (statusSelect) {
            statusSelect.addEventListener('change', filterItems);
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                    filterItems();
                }
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (searchInput) searchInput.value = '';
                if (unitSelect) unitSelect.value = '';
                if (statusSelect) statusSelect.value = '';
                filterItems();
            });
        }
    });
</script>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>