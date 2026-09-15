<?php
$pageTitle = 'Sales Reports';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// Summary metrics
$totalSalesQuery = $conn->query("SELECT SUM(total_amount) as total, COUNT(*) as count FROM customer_orders WHERE status != 'cancelled'");
$salesData = $totalSalesQuery->fetch_assoc();
$totalSales = floatval($salesData['total'] ?? 0);
$totalOrders = intval($salesData['count'] ?? 0);

$completedSalesQuery = $conn->query("SELECT SUM(total_amount) as total, COUNT(*) as count FROM customer_orders WHERE status = 'completed'");
$completedData = $completedSalesQuery->fetch_assoc();
$completedSales = floatval($completedData['total'] ?? 0);
$completedOrders = intval($completedData['count'] ?? 0);

$totalQuotesQuery = $conn->query("SELECT SUM(grand_total) as total, COUNT(*) as count FROM quotations");
$quotesData = $totalQuotesQuery->fetch_assoc();
$totalQuoteValue = floatval($quotesData['total'] ?? 0);
$totalQuoteCount = intval($quotesData['count'] ?? 0);

// Top Customers
$topCustomers = $conn->query("
    SELECT customer_name, COUNT(*) as orders_count, SUM(total_amount) as total_spent
    FROM customer_orders
    WHERE status != 'cancelled'
    GROUP BY customer_name
    ORDER BY total_spent DESC
    LIMIT 5
");

// Recent sales orders
$recentOrders = $conn->query("
    SELECT * FROM customer_orders ORDER BY order_date DESC LIMIT 10
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-chart-line-up"></i> Sales Reports</h2>
    <a href="/admin/index.php" class="btn btn-secondary">
        <i class="ph-bold ph-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- KPI Summary Cards -->
<div class="row mb-4 g-3">
    <div class="col-md-4">
        <div class="card stat-card text-white shadow-sm h-100"
            style="background: var(--zero-black); border: 1px solid rgba(215, 255, 224, 0.2);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50 small">Total Sales Volume</h6>
                        <h3 class="mb-0 text-white">₱<?php echo number_format($totalSales, 2); ?></h3>
                        <small class="text-white-50"><?php echo $totalOrders; ?> Customer Orders</small>
                    </div>
                    <i class="ph-bold ph-currency-circle-dollar fs-1" style="color: var(--ghost-green);"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card stat-card text-white shadow-sm h-100"
            style="background: #0d1f14; border: 1px solid rgba(215, 255, 224, 0.35);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small" style="color: rgba(215, 255, 224, 0.7);">Fulfilled &
                            Completed</h6>
                        <h3 class="mb-0" style="color: var(--ghost-green);">
                            ₱<?php echo number_format($completedSales, 2); ?></h3>
                        <small style="color: rgba(215, 255, 224, 0.7);"><?php echo $completedOrders; ?> Orders
                            Completed</small>
                    </div>
                    <i class="ph-bold ph-check-circle fs-1" style="color: var(--ghost-green);"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card stat-card text-white shadow-sm h-100"
            style="background: #1f2937; border: 1px solid rgba(215, 255, 224, 0.25);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50 small">Quotation Pipeline</h6>
                        <h3 class="mb-0" style="color: var(--ghost-green);">
                            ₱<?php echo number_format($totalQuoteValue, 2); ?></h3>
                        <small class="text-white-50"><?php echo $totalQuoteCount; ?> Quotations Issued</small>
                    </div>
                    <i class="ph-bold ph-file-text fs-1" style="color: var(--ghost-green);"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Top Customers -->
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="ph-bold ph-trophy"></i> Top Customers by Revenue</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if ($topCustomers->num_rows > 0): ?>
                        <?php while ($tc = $topCustomers->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($tc['customer_name']); ?></h6>
                                    <small class="text-muted"><?php echo $tc['orders_count']; ?> Order(s)</small>
                                </div>
                                <span class="fw-bold fs-6">₱<?php echo number_format($tc['total_spent'], 2); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-4">No customer sales data available.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Recent Sales Orders -->
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="ph-bold ph-list-dashes"></i> Recent Sales Orders</h5>
                <a href="/orders/customer_po.php" class="btn btn-sm btn-outline-dark">View All POs</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>PO #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentOrders->num_rows > 0): ?>
                                <?php while ($ro = $recentOrders->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ro['po_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($ro['customer_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($ro['order_date'])); ?></td>
                                        <td class="fw-bold">₱<?php echo number_format($ro['total_amount'], 2); ?></td>
                                        <td>
                                            <span
                                                class="badge bg-<?php echo ($ro['status'] === 'completed') ? 'success' : (($ro['status'] === 'cancelled') ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($ro['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No recent orders found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>