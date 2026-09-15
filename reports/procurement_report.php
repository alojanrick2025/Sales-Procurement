<?php
$pageTitle = 'Procurement Reports';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// Summary metrics
$totalProcQuery = $conn->query("SELECT SUM(total_amount) as total, COUNT(*) as count FROM supplier_orders WHERE status != 'cancelled'");
$procData = $totalProcQuery->fetch_assoc();
$totalProcurement = floatval($procData['total'] ?? 0);
$totalSupplierOrders = intval($procData['count'] ?? 0);

$completedProcQuery = $conn->query("SELECT SUM(total_amount) as total, COUNT(*) as count FROM supplier_orders WHERE status = 'completed'");
$compProcData = $completedProcQuery->fetch_assoc();
$completedProcurement = floatval($compProcData['total'] ?? 0);
$completedSupplierOrders = intval($compProcData['count'] ?? 0);

// Active suppliers count (from unified clients/partners table)
$supCountQuery = $conn->query("SELECT COUNT(*) as total FROM clients WHERE partner_type = 'supplier'");
$activeSuppliers = intval($supCountQuery->fetch_assoc()['total'] ?? 0);

// Procurement by Supplier
$supplierBreakdown = $conn->query("
    SELECT supplier_name, COUNT(*) as orders_count, SUM(total_amount) as total_procured
    FROM supplier_orders
    WHERE status != 'cancelled'
    GROUP BY supplier_name
    ORDER BY total_procured DESC
");

// Recent Procurement Orders
$recentProcOrders = $conn->query("
    SELECT * FROM supplier_orders ORDER BY order_date DESC LIMIT 10
");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-chart-pie-slice"></i> Procurement Reports</h2>
    <a href="/admin/index.php" class="btn btn-secondary">
        <i class="ph-bold ph-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- KPI Summary Cards -->
<div class="row mb-4 g-3">
    <div class="col-md-4">
        <div class="card stat-card text-white shadow-sm h-100" style="background: var(--zero-black); border: 1px solid rgba(215, 255, 224, 0.2);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50 small">Total Procurement Spend</h6>
                        <h3 class="mb-0 text-white">₱<?php echo number_format($totalProcurement, 2); ?></h3>
                        <small class="text-white-50"><?php echo $totalSupplierOrders; ?> Supplier Purchase Orders</small>
                    </div>
                    <i class="ph-bold ph-shopping-cart fs-1" style="color: var(--ghost-green);"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card stat-card text-white shadow-sm h-100" style="background: #0d1f14; border: 1px solid rgba(215, 255, 224, 0.35);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small" style="color: rgba(215, 255, 224, 0.7);">Fulfilled Procurement</h6>
                        <h3 class="mb-0" style="color: var(--ghost-green);">₱<?php echo number_format($completedProcurement, 2); ?></h3>
                        <small style="color: rgba(215, 255, 224, 0.7);"><?php echo $completedSupplierOrders; ?> Orders Delivered to Stock</small>
                    </div>
                    <i class="ph-bold ph-package fs-1" style="color: var(--ghost-green);"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card stat-card text-white shadow-sm h-100" style="background: #1e3a8a; border: 1px solid rgba(96, 165, 250, 0.3);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50 small">Active Suppliers</h6>
                        <h3 class="mb-0 text-white"><?php echo $activeSuppliers; ?> Suppliers</h3>
                        <small class="text-white-50">Vetted supply partners</small>
                    </div>
                    <i class="ph-bold ph-buildings fs-1" style="color: #93c5fd;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Supplier Breakdown -->
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="ph-bold ph-buildings"></i> Spend by Supplier</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if ($supplierBreakdown->num_rows > 0): ?>
                        <?php while ($sb = $supplierBreakdown->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($sb['supplier_name']); ?></h6>
                                    <small class="text-muted"><?php echo $sb['orders_count']; ?> PO(s)</small>
                                </div>
                                <span class="fw-bold fs-6">₱<?php echo number_format($sb['total_procured'], 2); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-4">No supplier procurement data available.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Recent Procurement Orders -->
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="ph-bold ph-list-dashes"></i> Recent Procurement POs</h5>
                <a href="/orders/supplier_po.php" class="btn btn-sm btn-outline-dark">View All POs</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>PO #</th>
                                <th>Supplier</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentProcOrders->num_rows > 0): ?>
                                <?php while ($rpo = $recentProcOrders->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($rpo['po_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($rpo['supplier_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($rpo['order_date'])); ?></td>
                                        <td class="fw-bold">₱<?php echo number_format($rpo['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($rpo['status'] === 'completed') ? 'success' : (($rpo['status'] === 'cancelled') ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($rpo['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No recent procurement orders found.</td>
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
