<?php
header('Location: /clients/clients.php?tab=suppliers');
exit();

$conn = getDBConnection();

// Search and filter
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM supplier WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR contact_person LIKE ? OR city LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    $types = 'sssss';
}

$query .= " ORDER BY name ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-buildings"></i> Suppliers</h2>
    <a href="/suppliers/add_supplier.php" class="btn btn-primary">
        <i class="ph-bold ph-plus-circle"></i> Add New Supplier
    </a>
</div>

<!-- Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-8">
                <input type="text" class="form-control" name="search" placeholder="Search by supplier name, contact person, city, phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ph-bold ph-magnifying-glass"></i> Search
                </button>
            </div>
            <div class="col-md-2">
                <a href="suppliers.php" class="btn btn-secondary w-100">
                    <i class="ph-bold ph-arrow-counter-clockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Suppliers Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>City / Address</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($supplier = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $supplier['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($supplier['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($supplier['contact_person'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($supplier['phone'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($supplier['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($supplier['city'] ?? $supplier['address']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($supplier['status'] === 'active') ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($supplier['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/suppliers/edit_supplier.php?id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-outline-dark" title="Edit Supplier">
                                        <i class="ph-bold ph-pencil-simple"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No suppliers found.</td>
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
