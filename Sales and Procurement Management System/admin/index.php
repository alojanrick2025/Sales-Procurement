<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// 1. Total Quotations
$totQuoteRes = $conn->query("SELECT COUNT(*) as total FROM quotations");
$totalQuotations = intval($totQuoteRes->fetch_assoc()['total'] ?? 0);

// 2. Pending Quotations
$pendingQuoteRes = $conn->query("SELECT COUNT(*) as pending FROM quotations WHERE status IN ('draft', 'sent', 'pending')");
$pendingQuotations = intval($pendingQuoteRes->fetch_assoc()['pending'] ?? 0);

// 3. Customer POs
$custPoRes = $conn->query("SELECT COUNT(*) as total FROM customer_orders");
$totalCustomerPOs = intval($custPoRes->fetch_assoc()['total'] ?? 0);

// 4. Supplier POs
$suppPoRes = $conn->query("SELECT COUNT(*) as total FROM supplier_orders");
$totalSupplierPOs = intval($suppPoRes->fetch_assoc()['total'] ?? 0);

// 5. Completed Orders (combined customer and supplier orders)
$compOrdersRes = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM customer_orders WHERE status = 'completed') +
        (SELECT COUNT(*) FROM supplier_orders WHERE status = 'completed') as total
");
$completedOrders = intval($compOrdersRes->fetch_assoc()['total'] ?? 0);

// 6. Cancelled Orders (combined customer and supplier orders)
$cancOrdersRes = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM customer_orders WHERE status = 'cancelled') +
        (SELECT COUNT(*) FROM supplier_orders WHERE status = 'cancelled') as total
");
$cancelledOrders = intval($cancOrdersRes->fetch_assoc()['total'] ?? 0);

// Total Sales and Procurement Values for activity widgets
$salesValRes = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM customer_orders WHERE status != 'cancelled'");
$totalSalesValue = floatval($salesValRes->fetch_assoc()['total'] ?? 0);

$procValRes = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM supplier_orders WHERE status != 'cancelled'");
$totalProcValue = floatval($procValRes->fetch_assoc()['total'] ?? 0);

// Recent Transactions Query (combined quotations, customer POs, and supplier POs)
$recentTransSql = "
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

ORDER BY trans_datetime DESC, trans_date DESC
LIMIT 10;
";

$recentTransactions = $conn->query($recentTransSql);

$conn->close();
?>

<!-- Header Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="ph-bold ph-squares-four"></i> Sales and Procurement Dashboard</h2>
        <span class="text-muted">Welcome back, <strong class="text-dark"><?php echo htmlspecialchars($admin['full_name']); ?></strong>! Overview of sales &amp; procurement operations.</span>
    </div>
    <div class="d-none d-md-flex gap-2">
        <a href="/quotation/add_quotation.php" class="btn btn-primary btn-sm">
            <i class="ph-bold ph-plus-circle"></i> New Quotation
        </a>
        <a href="/orders/add_customer_po.php" class="btn btn-outline-dark btn-sm">
            <i class="ph-bold ph-receipt"></i> Customer PO
        </a>
        <a href="/orders/add_supplier_po.php" class="btn btn-outline-dark btn-sm">
            <i class="ph-bold ph-shopping-cart"></i> Supplier PO
        </a>
    </div>
</div>


<!-- 6 Summary Cards (Soft Pastel Tints with Preserved Status Meaning) -->
<div class="row mb-4 g-3">
    <!-- Card 1: Total Quotations -->
    <div class="col-6 col-md-4 col-lg-2 col-xl-2">
        <div class="card stat-card shadow-sm h-100" style="background: #EBF5EE; border: 1px solid #D0E7D6;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small fw-semibold" style="color: #406852;">Total Quotations</h6>
                        <h2 class="mb-0 fw-bold" style="color: #16231D;"><?php echo $totalQuotations; ?></h2>
                    </div>
                    <i class="ph-bold ph-file-text fs-2" style="color: #2D6A4F;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card 2: Pending Quotations -->
    <div class="col-6 col-md-4 col-lg-2 col-xl-2">
        <div class="card stat-card shadow-sm h-100" style="background: #FEF6E9; border: 1px solid #FCE3BF;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small fw-semibold" style="color: #8A530E;">Pending Quotes</h6>
                        <h2 class="mb-0 fw-bold" style="color: #78350F;"><?php echo $pendingQuotations; ?></h2>
                    </div>
                    <i class="ph-bold ph-hourglass-simple fs-2" style="color: #D97706;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card 3: Customer POs -->
    <div class="col-6 col-md-4 col-lg-2 col-xl-2">
        <div class="card stat-card shadow-sm h-100" style="background: #EFF6FF; border: 1px solid #DBEAFE;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small fw-semibold" style="color: #1E40AF;">Customer POs</h6>
                        <h2 class="mb-0 fw-bold" style="color: #1E3A8A;"><?php echo $totalCustomerPOs; ?></h2>
                    </div>
                    <i class="ph-bold ph-receipt fs-2" style="color: #3B82F6;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card 4: Supplier POs -->
    <div class="col-6 col-md-4 col-lg-2 col-xl-2">
        <div class="card stat-card shadow-sm h-100" style="background: #F0F4F2; border: 1px solid #D9E2DE;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small fw-semibold" style="color: #40584B;">Supplier POs</h6>
                        <h2 class="mb-0 fw-bold" style="color: #16231D;"><?php echo $totalSupplierPOs; ?></h2>
                    </div>
                    <i class="ph-bold ph-shopping-cart fs-2" style="color: #52796F;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card 5: Completed Orders -->
    <div class="col-6 col-md-4 col-lg-2 col-xl-2">
        <div class="card stat-card shadow-sm h-100" style="background: #EAF8EF; border: 1px solid #C6EDD3;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small fw-semibold" style="color: #166534;">Completed</h6>
                        <h2 class="mb-0 fw-bold" style="color: #14532D;"><?php echo $completedOrders; ?></h2>
                    </div>
                    <i class="ph-bold ph-check-circle fs-2" style="color: #16A34A;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card 6: Cancelled Orders -->
    <div class="col-6 col-md-4 col-lg-2 col-xl-2">
        <div class="card stat-card shadow-sm h-100" style="background: #FEF2F2; border: 1px solid #FECACA;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 small fw-semibold" style="color: #991B1B;">Cancelled</h6>
                        <h2 class="mb-0 fw-bold" style="color: #7F1D1D;"><?php echo $cancelledOrders; ?></h2>
                    </div>
                    <i class="ph-bold ph-x-circle fs-2" style="color: #DC2626;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales & Procurement Quick Overview -->
<div class="row mb-4 g-3">
    <div class="col-md-6">
        <div class="card shadow-sm h-100 border">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: #EFF6FF; border: 1px solid #DBEAFE; width: 48px; height: 48px;">
                    <i class="ph-bold ph-trend-up fs-4 text-primary"></i>
                </div>
                <div>
                    <h6 class="text-muted small mb-0">Sales Activity Volume</h6>
                    <h4 class="mb-0 fw-bold" style="color: #16231D;">₱<?php echo number_format($totalSalesValue, 2); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm h-100 border">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: #EAF8EF; border: 1px solid #C6EDD3; width: 48px; height: 48px;">
                    <i class="ph-bold ph-shopping-bag-open fs-4" style="color: #16A34A;"></i>
                </div>
                <div>
                    <h6 class="text-muted small mb-0">Procurement Activity Spend</h6>
                    <h4 class="mb-0 fw-bold" style="color: #16231D;">₱<?php echo number_format($totalProcValue, 2); ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="card shadow-sm border">
    <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom py-3">
        <h5 class="mb-0 fw-bold" style="color: #16231D;"><i class="ph-bold ph-list-dashes me-1"></i> Recent Transactions</h5>
        <a href="/reports/transactions.php" class="btn btn-sm btn-primary">
            <i class="ph-bold ph-arrow-right"></i> View All
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
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
                    <?php if ($recentTransactions && $recentTransactions->num_rows > 0): ?>
                        <?php while ($tx = $recentTransactions->fetch_assoc()): 
                            $typeStyle = 'background: #F0F4F2; color: #24312B; border: 1px solid #D9E2DE;';
                            $typeIcon = 'ph-file-text';
                            if ($tx['transaction_type'] === 'Quotation') {
                                $typeStyle = 'background: #EBF5EE; color: #1E402B; border: 1px solid #D0E7D6;';
                                $typeIcon = 'ph-file-text';
                            } elseif ($tx['transaction_type'] === 'Customer Purchase Order') {
                                $typeStyle = 'background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE;';
                                $typeIcon = 'ph-receipt';
                            } elseif ($tx['transaction_type'] === 'Supplier Purchase Order') {
                                $typeStyle = 'background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A;';
                                $typeIcon = 'ph-shopping-cart';
                            }

                            $statusStyleMap = [
                                'pending' => 'background: #FEF6E9; color: #9A3412; border: 1px solid #FDBA74;',
                                'approved' => 'background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD;',
                                'processing' => 'background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE;',
                                'completed' => 'background: #EAF8EF; color: #166534; border: 1px solid #BBF7D0;',
                                'cancelled' => 'background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA;',
                                'draft' => 'background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB;',
                                'sent' => 'background: #FEF6E9; color: #9A3412; border: 1px solid #FDBA74;',
                                'accepted' => 'background: #EAF8EF; color: #166534; border: 1px solid #BBF7D0;',
                                'rejected' => 'background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA;'
                            ];
                            $statusBadgeStyle = $statusStyleMap[$tx['status']] ?? 'background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB;';
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($tx['ref_number']); ?></strong></td>
                                <td>
                                    <span class="badge py-1 px-2" style="<?php echo $typeStyle; ?>">
                                        <i class="ph-bold <?php echo $typeIcon; ?> me-1"></i>
                                        <?php echo htmlspecialchars($tx['transaction_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($tx['party_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($tx['trans_date'])); ?></td>
                                <td class="fw-bold" style="color: #16231D;">₱<?php echo number_format($tx['amount'], 2); ?></td>
                                <td>
                                    <span class="badge py-1 px-2" style="<?php echo $statusBadgeStyle; ?>">
                                        <?php echo ucfirst($tx['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($tx['view_link']); ?>" class="btn btn-sm btn-outline-dark" title="View Transaction">
                                        <i class="ph-bold ph-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No recent transactions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
