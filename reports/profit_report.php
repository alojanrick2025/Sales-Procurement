<?php
$pageTitle = 'Profit Report';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../includes/reports.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

/*
 * Profit on customer purchase orders: what the customer pays (line total) minus what
 * the items cost (quantity x the cost price saved on the line when sold, or the item's
 * current cost price for older orders). Lines whose item has no cost price are left
 * out of the profit and shown separately, so they never count as pure profit.
 */
$isDate = function ($value) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1;
};
$from = $isDate($_GET['from'] ?? '') ? $_GET['from'] : date('Y-01-01');
$to = $isDate($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$basis = ($_GET['basis'] ?? '') === 'all' ? 'all' : 'completed';
$lines = profitLinesSql($basis);
$sums = PROFIT_SUMS;

function profitQuery($conn, $sql, $from, $to, $extraTypes = '', $extraParams = []) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss' . $extraTypes, $from, $to, ...$extraParams);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$totals = profitQuery($conn, "SELECT $sums FROM ($lines) l", $from, $to)[0];
[$totalProfit, $totalMargin] = profitFigures($totals);
$byMonth = profitQuery($conn, "SELECT DATE_FORMAT(order_date, '%Y-%m') AS month, $sums FROM ($lines) l GROUP BY month ORDER BY month DESC", $from, $to);
$byCustomer = profitQuery($conn, "SELECT customer_name, $sums FROM ($lines) l GROUP BY customer_name ORDER BY (SUM(CASE WHEN cost IS NOT NULL THEN revenue END) - SUM(cost)) DESC, customer_name", $from, $to);

$pagination = paginate((int) $totals['orders']);
$byOrder = profitQuery($conn, "SELECT order_id, po_number, order_date, customer_name, $sums FROM ($lines) l
    GROUP BY order_id, po_number, order_date, customer_name ORDER BY order_date DESC, order_id DESC LIMIT ? OFFSET ?",
    $from, $to, 'ii', [$pagination['limit'], $pagination['offset']]);

$peso = function ($amount) {
    return ($amount < 0 ? '-' : '') . '₱' . number_format(abs($amount), 2);
};
$marginText = function ($margin) {
    return $margin === null ? '-' : number_format($margin, 1) . '%';
};
$profitClass = function ($profit) {
    return $profit < 0 ? 'text-danger' : 'text-success';
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h2 class="mb-0"><i class="ph-bold ph-coins"></i> Profit Report</h2>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach (['month' => 'By Month', 'customer' => 'By Customer', 'order' => 'By Order'] as $group => $label): ?>
            <a href="/reports/export.php?<?php echo htmlspecialchars(http_build_query(['type' => 'profit', 'group' => $group, 'from' => $from, 'to' => $to, 'basis' => $basis])); ?>" class="btn btn-outline-dark btn-sm">
                <i class="ph-bold ph-file-csv"></i> <?php echo $label; ?> CSV
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">From</label>
                <input type="date" class="form-control" name="from" value="<?php echo $from; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">To</label>
                <input type="date" class="form-control" name="to" value="<?php echo $to; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Orders</label>
                <select class="form-select" name="basis">
                    <option value="completed" <?php echo $basis === 'completed' ? 'selected' : ''; ?>>Completed customer orders (delivered)</option>
                    <option value="all" <?php echo $basis === 'all' ? 'selected' : ''; ?>>All customer orders except cancelled</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="ph-bold ph-funnel"></i> Apply</button>
                <a href="/reports/profit_report.php" class="btn btn-secondary" title="Reset"><i class="ph-bold ph-arrow-counter-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Totals -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Sales', $peso((float) $totals['revenue']), (int) $totals['orders'] . ' order' . ((int) $totals['orders'] === 1 ? '' : 's'), ''],
        ['Cost of Goods', $peso((float) $totals['cost']), 'quantity × cost price', ''],
        ['Gross Profit', $peso($totalProfit), 'on items with a cost price', $profitClass($totalProfit)],
        ['Margin', $marginText($totalMargin), 'profit ÷ sales with a cost price', $totalMargin !== null ? $profitClass($totalMargin) : ''],
    ] as [$label, $value, $hint, $class]): ?>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted"><?php echo $label; ?></div>
                    <div class="fs-4 fw-bold <?php echo $class; ?>"><?php echo $value; ?></div>
                    <div class="small text-muted"><?php echo $hint; ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ((float) $totals['uncosted_revenue'] > 0): ?>
    <div class="alert alert-warning">
        <i class="ph-bold ph-warning"></i>
        <?php echo $peso((float) $totals['uncosted_revenue']); ?> of these sales are items with no cost price, so their profit is not counted.
        Set cost prices on the <a href="/items/items.php">Inventory</a> page (receiving a supplier PO also sets them).
    </div>
<?php endif; ?>

<?php
// One table layout for the month, customer and order breakdowns
function profitTable($title, $icon, $firstHeading, $rows, $firstCell, $peso, $marginText, $profitClass, $footer = '') {
    ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3"><h5 class="mb-0"><i class="ph-bold <?php echo $icon; ?>"></i> <?php echo $title; ?></h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?php echo $firstHeading; ?></th>
                            <th class="text-end">Orders</th>
                            <th class="text-end">Sales</th>
                            <th class="text-end">Cost</th>
                            <th class="text-end">Profit</th>
                            <th class="text-end">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No customer orders in this period.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row):
                            [$profit, $margin] = profitFigures($row);
                            ?>
                            <tr>
                                <td><?php echo $firstCell($row); ?></td>
                                <td class="text-end"><?php echo (int) $row['orders']; ?></td>
                                <td class="text-end"><?php echo $peso((float) $row['revenue']); ?></td>
                                <td class="text-end"><?php echo $peso((float) $row['cost']); ?></td>
                                <td class="text-end fw-bold <?php echo $profitClass($profit); ?>"><?php echo $peso($profit); ?></td>
                                <td class="text-end"><?php echo $marginText($margin); ?><?php echo (float) $row['uncosted_revenue'] > 0 ? ' <i class="ph-bold ph-warning text-warning" title="Some items have no cost price"></i>' : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php echo $footer; ?>
        </div>
    </div>
    <?php
}

profitTable('By Month', 'ph-calendar', 'Month', $byMonth, function ($row) {
    return date('F Y', strtotime($row['month'] . '-01'));
}, $peso, $marginText, $profitClass);

profitTable('By Customer', 'ph-users', 'Customer', $byCustomer, function ($row) {
    return htmlspecialchars($row['customer_name']);
}, $peso, $marginText, $profitClass);

profitTable('By Order', 'ph-receipt', 'Order', $byOrder, function ($row) {
    return '<a href="/orders/view_customer_po.php?id=' . (int) $row['order_id'] . '">' . htmlspecialchars($row['po_number']) . '</a>'
        . '<div class="small text-muted">' . htmlspecialchars($row['customer_name']) . ' &middot; ' . date('M d, Y', strtotime($row['order_date'])) . '</div>';
}, $peso, $marginText, $profitClass, paginationLinks($pagination));
?>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
