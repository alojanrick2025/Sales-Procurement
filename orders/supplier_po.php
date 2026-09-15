<?php
$pageTitle = 'Supplier Purchase Orders';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$supplierFilter = trim($_GET['supplier_id'] ?? '');

// Fetch business partner suppliers for the filter dropdown
$bpSuppliersRes = $conn->query("SELECT id, name FROM clients WHERE partner_type = 'supplier' ORDER BY name ASC");
$bpSuppliers = [];
while ($bps = $bpSuppliersRes->fetch_assoc())
    $bpSuppliers[] = $bps;

$query = "SELECT * FROM supplier_orders WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (po_number LIKE ? OR supplier_name LIKE ? OR notes LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam];
    $types = 'sss';
}

if (!empty($statusFilter)) {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($supplierFilter)) {
    // Match by supplier_id (if linked) or by name from clients table
    $bpName = '';
    foreach ($bpSuppliers as $bps) {
        if ($bps['id'] == $supplierFilter) {
            $bpName = $bps['name'];
            break;
        }
    }
    if (!empty($bpName)) {
        $query .= " AND supplier_name = ?";
        $params[] = $bpName;
        $types .= 's';
    }
}

$query .= " ORDER BY order_date DESC, id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-shopping-cart"></i> Supplier Purchase Orders</h2>
    <a href="/orders/add_supplier_po.php" class="btn btn-primary">
        <i class="ph-bold ph-plus-circle"></i> New Supplier PO
    </a>
</div>

<!-- Search and Filter Bar (Live Real-Time) -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3" id="filterForm" onsubmit="return false;">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i
                            class="ph-bold ph-magnifying-glass"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" name="search" id="liveSearch"
                        placeholder="Type to search PO #, Supplier, or Notes..."
                        value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="supplier_id" id="supplierFilter">
                    <option value="">All Suppliers</option>
                    <?php foreach ($bpSuppliers as $bps): ?>
                        <option value="<?php echo $bps['id']; ?>" <?php echo ($supplierFilter == $bps['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($bps['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status" id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending
                    </option>
                    <option value="approved" <?php echo ($statusFilter === 'approved') ? 'selected' : ''; ?>>Approved
                    </option>
                    <option value="processing" <?php echo ($statusFilter === 'processing') ? 'selected' : ''; ?>>
                        Processing</option>
                    <option value="completed" <?php echo ($statusFilter === 'completed') ? 'selected' : ''; ?>>Completed
                    </option>
                    <option value="cancelled" <?php echo ($statusFilter === 'cancelled') ? 'selected' : ''; ?>>Cancelled
                    </option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- PO Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>PO #</th>
                        <th>Supplier</th>
                        <th>Order Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="poTableBody">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($po = $result->fetch_assoc()):
                            $statusBadgeMap = [
                                'pending' => 'bg-warning',
                                'approved' => 'bg-info',
                                'processing' => 'bg-primary',
                                'completed' => 'bg-success',
                                'cancelled' => 'bg-danger'
                            ];
                            $badgeClass = $statusBadgeMap[$po['status']] ?? 'bg-secondary';
                            ?>
                            <tr class="po-data-row" data-po="<?php echo htmlspecialchars(strtolower($po['po_number'])); ?>"
                                data-supplier="<?php echo htmlspecialchars(strtolower($po['supplier_name'])); ?>"
                                data-notes="<?php echo htmlspecialchars(strtolower($po['notes'] ?? '')); ?>"
                                data-status="<?php echo htmlspecialchars($po['status']); ?>">
                                <td><strong><?php echo htmlspecialchars($po['po_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($po['order_date'])); ?></td>
                                <td class="fw-bold">₱<?php echo number_format($po['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($po['status']); ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($po['notes'] ?? '-'); ?></td>
                                <td>
                                    <a href="/orders/view_supplier_po.php?id=<?php echo $po['id']; ?>"
                                        class="btn btn-sm btn-outline-dark" title="View Supplier PO">
                                        <i class="ph-bold ph-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No Supplier Purchase Orders found.</td>
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

<script>
    (function () {
        const searchInput = document.getElementById('liveSearch');
        const statusSelect = document.getElementById('statusFilter');
        const supplierSelect = document.getElementById('supplierFilter');
        const tbody = document.getElementById('poTableBody');
        const noResultsRow = document.createElement('tr');
        noResultsRow.id = 'noResultsRow';
        noResultsRow.innerHTML = '<td colspan="7" class="text-center text-muted py-4">No results found.</td>';

        function filterTable() {
            const query = searchInput.value.toLowerCase().trim();
            const status = statusSelect.value.toLowerCase();
            const suppName = supplierSelect.options[supplierSelect.selectedIndex]?.textContent?.toLowerCase().trim() || '';
            let visibleCount = 0;

            Array.from(tbody.querySelectorAll('tr.po-data-row')).forEach(function (row) {
                const po = (row.dataset.po || '').toLowerCase();
                const supplier = (row.dataset.supplier || '').toLowerCase();
                const notes = (row.dataset.notes || '').toLowerCase();
                const rowStatus = (row.dataset.status || '').toLowerCase();

                const matchSearch = !query || po.includes(query) || supplier.includes(query) || notes.includes(query);
                const matchStatus = !status || rowStatus === status;
                const matchSupplier = !supplierSelect.value || supplier.includes(suppName);

                if (matchSearch && matchStatus && matchSupplier) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const existing = document.getElementById('noResultsRow');
            if (visibleCount === 0) {
                if (!existing) tbody.appendChild(noResultsRow);
            } else {
                if (existing) existing.remove();
            }
        }

        searchInput.addEventListener('input', filterTable);
        statusSelect.addEventListener('change', filterTable);
        supplierSelect.addEventListener('change', filterTable);

        filterTable();
    })();
</script>