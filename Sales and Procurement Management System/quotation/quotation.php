<?php
$pageTitle = 'Quotations';
require_once __DIR__ . '/../config.php';
requireLogin();

$conn = getDBConnection();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Search and filter
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// KPI statistics
$totalQry = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(grand_total), 0) as total_val FROM quotations");
$totalData = $totalQry->fetch_assoc();
$totalQuotations = $totalData['total'] ?? 0;
$totalQuotedValue = $totalData['total_val'] ?? 0;

$draftQry = $conn->query("SELECT COUNT(*) as total FROM quotations WHERE status = 'draft'");
$draftCount = $draftQry->fetch_assoc()['total'] ?? 0;

$sentQry = $conn->query("SELECT COUNT(*) as total FROM quotations WHERE status = 'sent'");
$sentCount = $sentQry->fetch_assoc()['total'] ?? 0;

$acceptedQry = $conn->query("SELECT COUNT(*) as total FROM quotations WHERE status = 'accepted'");
$acceptedCount = $acceptedQry->fetch_assoc()['total'] ?? 0;

// Query quotations
$query = "SELECT * FROM quotations WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (quotation_number LIKE ? OR client_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if (!empty($statusFilter)) {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-file-text"></i> Quotations</h2>
    <a href="/quotation/add_quotation.php" class="btn btn-primary">
        <i class="ph-bold ph-plus-circle"></i> Create Quotation
    </a>
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
                        <h6 class="card-subtitle mb-2 text-white-50 small">Total Quotes</h6>
                        <h2 class="mb-0 text-white"><?php echo number_format($totalQuotations); ?></h2>
                    </div>
                    <i class="ph-bold ph-file-text fs-1" style="color: var(--ghost-green); opacity: 0.85;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white shadow-sm h-100"
            style="background: #111111; border: 1px solid rgba(255, 255, 255, 0.1);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50 small">Sent to Client</h6>
                        <h2 class="mb-0 text-white"><?php echo number_format($sentCount); ?></h2>
                    </div>
                    <i class="ph-bold ph-paper-plane-tilt fs-1" style="color: #60a5fa; opacity: 0.85;"></i>
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
                        <h6 class="card-subtitle mb-2 small" style="color: rgba(215, 255, 224, 0.7);">Accepted</h6>
                        <h2 class="mb-0" style="color: var(--ghost-green);"><?php echo number_format($acceptedCount); ?>
                        </h2>
                    </div>
                    <i class="ph-bold ph-check-circle fs-1" style="color: var(--ghost-green);"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-white shadow-sm h-100"
            style="background: var(--zero-black); border: 1px solid rgba(215, 255, 224, 0.2);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-white-50 small">Total Quoted Value</h6>
                        <h2 class="mb-0" style="color: var(--ghost-green);">
                            ₱<?php echo number_format($totalQuotedValue, 2); ?></h2>
                    </div>
                    <i class="ph-bold ph-coins fs-1" style="color: var(--ghost-green); opacity: 0.85;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="search" placeholder="Search by Quote # or Customer..."
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="sent" <?php echo $statusFilter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                    <option value="accepted" <?php echo $statusFilter === 'accepted' ? 'selected' : ''; ?>>Accepted
                    </option>
                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected
                    </option>
                    <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ph-bold ph-magnifying-glass"></i> Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="/quotation/quotation.php" class="btn btn-secondary w-100" title="Reset">
                    <i class="ph-bold ph-arrow-counter-clockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Quotations Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Quote #</th>
                        <th>Customer</th>
                        <th>Grand Total</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($quote = $result->fetch_assoc()): ?>
                            <?php
                            $badgeClass = 'bg-secondary';
                            switch ($quote['status']) {
                                case 'draft':
                                    $badgeClass = 'bg-secondary';
                                    break;
                                case 'sent':
                                    $badgeClass = 'bg-warning';
                                    break;
                                case 'accepted':
                                    $badgeClass = 'bg-success';
                                    break;
                                case 'rejected':
                                    $badgeClass = 'bg-danger';
                                    break;
                                case 'expired':
                                    $badgeClass = 'bg-warning';
                                    break;
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($quote['quotation_number']); ?></strong></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($quote['client_name']); ?></div>
                                    <small
                                        class="text-muted"><?php echo htmlspecialchars($quote['client_phone'] ?? ''); ?></small>
                                </td>
                                <td><strong
                                        class="text-primary">₱<?php echo number_format($quote['grand_total'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php echo !empty($quote['valid_until']) ? date('M d, Y', strtotime($quote['valid_until'])) : '<span class="text-muted">-</span>'; ?>
                                </td>
                                <td>
                                    <span
                                        class="badge <?php echo $badgeClass; ?> text-capitalize"><?php echo htmlspecialchars($quote['status']); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($quote['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="/quotation/view_quotation.php?id=<?php echo $quote['id']; ?>"
                                            class="btn btn-outline-dark" title="View & Print">
                                            <i class="ph-bold ph-eye"></i>
                                        </a>
                                        <a href="/quotation/edit_quotation.php?id=<?php echo $quote['id']; ?>"
                                            class="btn btn-outline-warning" title="Edit">
                                            <i class="ph-bold ph-pencil-simple"></i>
                                        </a>
                                        <form action="/quotation/delete_quotation.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this quotation?')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo $quote['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Delete">
                                                <i class="ph-bold ph-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="ph-bold ph-tray fs-1 d-block mb-2"></i>
                                No quotations found. Click "Create Quotation" to create one.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>