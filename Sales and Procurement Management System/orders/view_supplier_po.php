<?php
$pageTitle = 'View Supplier Purchase Order';
require_once __DIR__ . '/../config.php';
requireLogin();

$conn = getDBConnection();
$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM supplier_orders WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$po) {
    header('Location: /orders/supplier_po.php');
    exit();
}

$success = '';
$error = '';

// Handle status update with CSRF validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateCsrfToken()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $newStatus = $_POST['status'] ?? $po['status'];
        if (in_array($newStatus, ['pending', 'approved', 'processing', 'completed', 'cancelled'])) {
            $uStmt = $conn->prepare("UPDATE supplier_orders SET status = ? WHERE id = ?");
            $uStmt->bind_param("si", $newStatus, $id);
            if ($uStmt->execute()) {
                $po['status'] = $newStatus;
                $success = 'Procurement PO status updated successfully to ' . ucfirst($newStatus) . '.';
            } else {
                $error = 'Failed to update order status: ' . $uStmt->error;
            }
            $uStmt->close();
        } else {
            $error = 'Invalid order status value.';
        }
    }
}

// Fetch line items for this Supplier Purchase Order
$itemsStmt = $conn->prepare("SELECT * FROM supplier_order_items WHERE supplier_order_id = ? ORDER BY id ASC");
$itemsStmt->bind_param("i", $id);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}
$itemsStmt->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="mb-1"><i class="ph-bold ph-shopping-cart text-primary"></i> Supplier PO: <?php echo htmlspecialchars($po['po_number']); ?></h2>
        <span class="text-muted small">Issued on <?php echo date('F d, Y', strtotime($po['order_date'])); ?></span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="ph-bold ph-printer"></i> Print PO
        </button>
        <a href="/orders/supplier_po.php" class="btn btn-secondary">
            <i class="ph-bold ph-arrow-left"></i> Back to PO List
        </a>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-warning me-1"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- PO Overview Details -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold text-dark"><i class="ph-bold ph-info text-primary me-2"></i>Procurement Order Details</h5>
                <code><?php echo htmlspecialchars($po['po_number']); ?></code>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Vendor / Supplier:</span>
                        <div class="fw-bold fs-5 text-dark"><?php echo htmlspecialchars($po['supplier_name']); ?></div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">PO Reference Number:</span>
                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($po['po_number']); ?></div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Order Date:</span>
                        <div class="text-dark fw-medium"><?php echo date('F d, Y', strtotime($po['order_date'])); ?></div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Total Procurement Cost:</span>
                        <div class="text-primary fw-bold fs-4">₱<?php echo number_format($po['total_amount'], 2); ?></div>
                    </div>
                    <?php if (!empty($po['notes'])): ?>
                    <div class="col-12">
                        <hr class="my-2 text-muted">
                        <span class="text-muted small d-block mb-1">Procurement Notes / Terms:</span>
                        <div class="p-3 bg-light rounded text-secondary small">
                            <?php echo nl2br(htmlspecialchars($po['notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Status & Management Widget -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold text-dark"><i class="ph-bold ph-activity text-primary me-2"></i>Order Status</h5>
            </div>
            <div class="card-body p-4">
                <div class="mb-3 text-center p-3 bg-light rounded">
                    <span class="text-muted small d-block mb-1">Current Status</span>
                    <?php
                    $statusBadgeMap = [
                        'pending' => 'bg-warning text-dark',
                        'approved' => 'bg-info text-dark',
                        'processing' => 'bg-primary text-white',
                        'completed' => 'bg-success text-white',
                        'cancelled' => 'bg-danger text-white'
                    ];
                    $badgeClass = $statusBadgeMap[$po['status']] ?? 'bg-secondary text-white';
                    ?>
                    <span class="badge <?php echo $badgeClass; ?> fs-6 px-3 py-2 text-uppercase fw-semibold">
                        <?php echo ucfirst($po['status']); ?>
                    </span>
                </div>

                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="update_status" value="1">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Change Status</label>
                        <select class="form-select" name="status">
                            <option value="pending" <?php echo ($po['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo ($po['status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="processing" <?php echo ($po['status'] === 'processing') ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo ($po['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($po['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ph-bold ph-check"></i> Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Dedicated Line Items Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold text-dark d-flex align-items-center gap-2">
            <i class="ph-bold ph-list-dashes text-primary"></i> Line Items Ordered
        </h5>
        <span class="badge bg-light text-dark border">
            <?php echo count($items); ?> <?php echo count($items) === 1 ? 'Line Item' : 'Line Items'; ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;" class="text-center">#</th>
                        <th style="width: 30%;">Item Name & Description</th>
                        <th style="width: 10%;" class="text-center">Unit</th>
                        <th style="width: 10%;" class="text-center">Quantity</th>
                        <th style="width: 15%;" class="text-end">Unit Cost / Price</th>
                        <th style="width: 15%;" class="text-end">Markdown %</th>
                        <th style="width: 15%;" class="text-end">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="ph-bold ph-package fs-1 d-block mb-2 text-secondary"></i>
                                No line items recorded for this purchase order.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $computedSubtotal = 0;
                        foreach ($items as $idx => $it): 
                            $lineTotal = floatval($it['total_price'] ?? ($it['quantity'] * $it['unit_price']));
                            $computedSubtotal += $lineTotal;
                        ?>
                            <tr>
                                <td class="text-center text-secondary fw-semibold"><?php echo $idx + 1; ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($it['item_name']); ?></div>
                                    <?php if (!empty($it['description'])): ?>
                                        <small class="text-muted d-block"><?php echo nl2br(htmlspecialchars($it['description'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($it['unit'] ?: 'PCS'); ?></span>
                                </td>
                                <td class="text-center fw-semibold"><?php echo number_format($it['quantity'], 2); ?></td>
                                <td class="text-end">₱<?php echo number_format($it['unit_price'], 2); ?></td>
                                <td class="text-end text-danger"><?php echo !empty($it['markdown_rate']) ? number_format($it['markdown_rate'], 2) . '%' : '-'; ?></td>
                                <td class="text-end fw-bold text-dark">₱<?php echo number_format($lineTotal, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="6" class="text-end fw-bold fs-6">Grand Total:</td>
                        <td class="text-end fw-bold fs-5 text-primary">
                            ₱<?php echo number_format(!empty($po['total_amount']) ? $po['total_amount'] : ($computedSubtotal ?? 0), 2); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .top-navbar, .sidebar, .btn, .alert, form, .card-header .badge {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
}
</style>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>