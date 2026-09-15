<?php
$pageTitle = 'Transaction History';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

$typeFilter = trim($_GET['type'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

// Build unified query
$sql = "
SELECT 
    'Quotation' AS transaction_type,
    quotation_number AS ref_number,
    client_name AS party_name,
    DATE(created_at) AS trans_date,
    created_at AS trans_datetime,
    grand_total AS amount,
    CASE 
        WHEN status = 'accepted' THEN 'approved'
        WHEN status IN ('draft', 'sent') THEN 'pending'
        WHEN status = 'rejected' THEN 'cancelled'
        ELSE status
    END AS status,
    CONCAT('/quotation/view_quotation.php?id=', id) AS view_link
FROM quotations

UNION ALL

SELECT 
    'Customer Purchase Order' AS transaction_type,
    po_number AS ref_number,
    customer_name AS party_name,
    order_date AS trans_date,
    created_at AS trans_datetime,
    total_amount AS amount,
    status,
    CONCAT('/orders/view_customer_po.php?id=', id) AS view_link
FROM customer_orders

UNION ALL

SELECT 
    'Supplier Purchase Order' AS transaction_type,
    po_number AS ref_number,
    supplier_name AS party_name,
    order_date AS trans_date,
    created_at AS trans_datetime,
    total_amount AS amount,
    status,
    CONCAT('/orders/view_supplier_po.php?id=', id) AS view_link
FROM supplier_orders
";

$outerSql = "SELECT * FROM ($sql) AS all_trans WHERE 1=1";
$params = [];
$types = '';

if (!empty($typeFilter)) {
    $outerSql .= " AND transaction_type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

if (!empty($statusFilter)) {
    $outerSql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($search)) {
    $outerSql .= " AND (ref_number LIKE ? OR party_name LIKE ?)";
    $sParam = "%$search%";
    $params[] = $sParam;
    $params[] = $sParam;
    $types .= 'ss';
}

$outerSql .= " ORDER BY trans_datetime DESC, trans_date DESC";

$stmt = $conn->prepare($outerSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-clock-counter-clockwise"></i> Complete Transaction History</h2>
    <a href="/admin/index.php" class="btn btn-secondary">
        <i class="ph-bold ph-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search reference # or customer/supplier..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="type">
                    <option value="">All Transaction Types</option>
                    <option value="Quotation" <?php echo ($typeFilter === 'Quotation') ? 'selected' : ''; ?>>Quotations</option>
                    <option value="Customer Purchase Order" <?php echo ($typeFilter === 'Customer Purchase Order') ? 'selected' : ''; ?>>Customer Purchase Orders</option>
                    <option value="Supplier Purchase Order" <?php echo ($typeFilter === 'Supplier Purchase Order') ? 'selected' : ''; ?>>Supplier Purchase Orders</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo ($statusFilter === 'approved') ? 'selected' : ''; ?>>Approved</option>
                    <option value="processing" <?php echo ($statusFilter === 'processing') ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo ($statusFilter === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo ($statusFilter === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="ph-bold ph-magnifying-glass"></i> Filter</button>
                <a href="/reports/transactions.php" class="btn btn-secondary" title="Reset"><i class="ph-bold ph-arrow-counter-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference #</th>
                        <th>Transaction Type</th>
                        <th>Customer / Supplier</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions->num_rows > 0): ?>
                        <?php while ($tx = $transactions->fetch_assoc()): 
                            $typeClass = 'badge-secondary';
                            $typeIcon = 'ph-file-text';
                            if ($tx['transaction_type'] === 'Quotation') {
                                $typeClass = 'badge-type-quotation';
                                $typeIcon = 'ph-file-text';
                            } elseif ($tx['transaction_type'] === 'Customer Purchase Order') {
                                $typeClass = 'badge-type-customer-po';
                                $typeIcon = 'ph-receipt';
                            } elseif ($tx['transaction_type'] === 'Supplier Purchase Order') {
                                $typeClass = 'badge-type-supplier-po';
                                $typeIcon = 'ph-shopping-cart';
                            }

                            $statusBadgeMap = [
                                'pending'    => 'bg-warning',
                                'approved'   => 'bg-info',
                                'processing' => 'bg-primary',
                                'completed'  => 'bg-success',
                                'cancelled'  => 'bg-danger',
                                'draft'      => 'bg-secondary',
                                'sent'       => 'bg-warning',
                                'accepted'   => 'bg-success',
                                'rejected'   => 'bg-danger'
                            ];
                            $badgeClass = $statusBadgeMap[$tx['status']] ?? 'bg-secondary';
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($tx['ref_number']); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $typeClass; ?>">
                                        <i class="ph-bold <?php echo $typeIcon; ?> me-1"></i>
                                        <?php echo htmlspecialchars($tx['transaction_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($tx['party_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($tx['trans_date'])); ?></td>
                                <td class="fw-bold">₱<?php echo number_format($tx['amount'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($tx['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($tx['view_link']); ?>" class="btn btn-sm btn-outline-dark">
                                        <i class="ph-bold ph-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No transactions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
$conn->close();
require_once __DIR__ . '/../includes/footer.php'; 
?>
