<?php
$pageTitle = 'Stock History';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/inventory.php';
require_once __DIR__ . '/../includes/pagination.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// Filters
$itemFilter = intval($_GET['item_id'] ?? 0);
$typeFilter = $_GET['type'] ?? '';
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';
$isDate = function ($value) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
};

$where = " WHERE 1=1";
$params = [];
$types = '';
if ($itemFilter > 0) {
    $where .= " AND m.item_id = ?";
    $params[] = $itemFilter;
    $types .= 'i';
}
if (isset(STOCK_MOVEMENT_TYPES[$typeFilter])) {
    $where .= " AND m.movement_type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}
if ($isDate($dateFrom)) {
    $where .= " AND m.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}
if ($isDate($dateTo)) {
    $where .= " AND m.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM stock_movements m" . $where);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$pagination = paginate($countStmt->get_result()->fetch_assoc()['total']);
$countStmt->close();

$sql = "SELECT m.*, u.full_name, u.username, il.unit
        FROM stock_movements m
        LEFT JOIN users u ON u.id = m.created_by
        LEFT JOIN item_list il ON il.id = m.item_id" . $where . "
        ORDER BY m.created_at DESC, m.id DESC LIMIT ? OFFSET ?";
$pageParams = array_merge($params, [$pagination['limit'], $pagination['offset']]);
$stmt = $conn->prepare($sql);
$stmt->bind_param($types . 'ii', ...$pageParams);
$stmt->execute();
$movements = $stmt->get_result();

// Items for the filter, and the selected item's current stock
$itemOptions = $conn->query("SELECT id, name, description, stocks, unit FROM item_list ORDER BY TRIM(name) ASC")->fetch_all(MYSQLI_ASSOC);
$selectedItem = null;
foreach ($itemOptions as $opt) {
    if ((int) $opt['id'] === $itemFilter) {
        $selectedItem = $opt;
    }
}
$cleanName = function ($name) {
    return html_entity_decode(str_ireplace('&quot;', '"', (string) $name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
};
$typeBadges = [
    'receipt' => 'bg-success',
    'delivery' => 'bg-primary',
    'reversal' => 'bg-warning text-dark',
    'adjustment' => 'bg-secondary',
    'opening' => 'bg-info text-dark',
];
$referenceLinks = [
    'supplier_order' => '/orders/view_supplier_po.php?id=',
    'customer_order' => '/orders/view_customer_po.php?id=',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h2 class="mb-0"><i class="ph-bold ph-clock-counter-clockwise"></i> Stock History</h2>
    <div class="d-flex flex-wrap gap-2">
        <a href="/reports/export.php?<?php echo htmlspecialchars(http_build_query(['type' => 'stock_history', 'item_id' => $itemFilter ?: '', 'type_filter' => $typeFilter, 'from' => $dateFrom, 'to' => $dateTo])); ?>" class="btn btn-outline-dark">
            <i class="ph-bold ph-file-csv"></i> Export CSV
        </a>
        <a href="/stocks/stocks.php" class="btn btn-secondary">
            <i class="ph-bold ph-arrow-left"></i> Back to Stock Management
        </a>
    </div>
</div>

<?php if ($selectedItem): ?>
    <div class="alert alert-light border mb-4">
        <strong><?php echo htmlspecialchars($cleanName($selectedItem['name'])); ?></strong>
        <span class="text-muted">(<?php echo htmlspecialchars($selectedItem['description']); ?>)</span>
        &mdash; current stock:
        <strong><?php echo number_format((float) $selectedItem['stocks'], 2); ?> <?php echo htmlspecialchars($selectedItem['unit']); ?></strong>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Item</label>
                <select class="form-select" name="item_id">
                    <option value="">All items</option>
                    <?php foreach ($itemOptions as $opt): ?>
                        <option value="<?php echo $opt['id']; ?>" <?php echo (int) $opt['id'] === $itemFilter ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cleanName($opt['name'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Type</label>
                <select class="form-select" name="type">
                    <option value="">All types</option>
                    <?php foreach (STOCK_MOVEMENT_TYPES as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $typeFilter === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">From</label>
                <input type="date" class="form-control" name="from" value="<?php echo $isDate($dateFrom) ? $dateFrom : ''; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">To</label>
                <input type="date" class="form-control" name="to" value="<?php echo $isDate($dateTo) ? $dateTo : ''; ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="ph-bold ph-funnel"></i> Filter</button>
                <a href="/stocks/stock_history.php" class="btn btn-secondary" title="Reset"><i class="ph-bold ph-arrow-counter-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th class="text-end">Change</th>
                        <th class="text-end">Balance</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Note</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($movements->num_rows === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="ph-bold ph-tray fs-1 d-block mb-2"></i>
                                No stock changes recorded<?php echo ($itemFilter || $typeFilter || $dateFrom || $dateTo) ? ' for these filters' : ' yet'; ?>.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php while ($m = $movements->fetch_assoc()):
                        $change = (float) $m['quantity_change'];
                        ?>
                        <tr>
                            <td class="text-nowrap"><?php echo date('M d, Y g:i A', strtotime($m['created_at'])); ?></td>
                            <td>
                                <a href="?item_id=<?php echo (int) $m['item_id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($m['item_name']); ?>
                                </a>
                            </td>
                            <td class="text-end fw-bold <?php echo $change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($change >= 0 ? '+' : '&minus;') . number_format(abs($change), 2); ?>
                            </td>
                            <td class="text-end"><?php echo number_format((float) $m['balance_after'], 2); ?> <?php echo htmlspecialchars($m['unit'] ?? ''); ?></td>
                            <td>
                                <span class="badge <?php echo $typeBadges[$m['movement_type']] ?? 'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars(STOCK_MOVEMENT_TYPES[$m['movement_type']] ?? $m['movement_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($m['reference_number']) && isset($referenceLinks[$m['reference_type']])): ?>
                                    <a href="<?php echo $referenceLinks[$m['reference_type']] . (int) $m['reference_id']; ?>"><?php echo htmlspecialchars($m['reference_number']); ?></a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($m['note'] ?? ''); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($m['full_name'] ?: ($m['username'] ?: '-')); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php echo paginationLinks($pagination); ?>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
