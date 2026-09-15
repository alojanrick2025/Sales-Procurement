<?php
$pageTitle = 'List of System Users';
require_once __DIR__ . '/../config.php';
requireAdminLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// Pagination
$entriesPerPage = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $entriesPerPage;

// Search
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT id, username, email, full_name, phone, user_type, status, created_at, avatar FROM users WHERE 1=1";
$countQuery = "SELECT COUNT(*) as total FROM users WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $countQuery .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

$query .= " ORDER BY id ASC LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $entriesPerPage;
$params[] = $offset;

// Get total count
$countStmt = $conn->prepare($countQuery);
if (!empty($search)) {
    $countParams = [];
    $countTypes = '';
    for ($i = 0; $i < 3; $i++) {
        $countParams[] = "%$search%";
        $countTypes .= 's';
    }
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $entriesPerPage);

// Get users
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Function to get avatar HTML
function getAvatarHtml($user) {
    if (!empty($user['avatar']) && file_exists($user['avatar'])) {
        return '<img src="' . htmlspecialchars($user['avatar']) . '" alt="Avatar" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">';
    } else {
        $initials = getUserInitials($user['full_name']);
        return '<div class="user-avatar">' . htmlspecialchars($initials) . '</div>';
    }
}
?>

<style>
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
    }
    .table thead th {
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
    }
    .table tbody td {
        vertical-align: middle;
    }
    .entries-select {
        width: auto;
        display: inline-block;
    }
    .search-box {
        width: auto;
        display: inline-block;
    }
    .action-dropdown {
        position: relative;
    }
    .pagination .page-link {
        color: #667eea;
    }
    .pagination .page-item.active .page-link {
        background-color: #667eea;
        border-color: #667eea;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people"></i> List of System Users</h2>
    <a href="/admin/add_user.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Create New User
    </a>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- Controls -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <label class="me-2">Show</label>
                <select class="form-select entries-select d-inline-block" style="width: auto;" onchange="window.location.href='?entries=' + this.value + '&search=<?php echo urlencode($search); ?>'">
                    <option value="10" <?php echo $entriesPerPage == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $entriesPerPage == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $entriesPerPage == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $entriesPerPage == 100 ? 'selected' : ''; ?>>100</option>
                </select>
                <label class="ms-2">entries</label>
            </div>
            <div class="col-md-6">
                <form method="GET" action="" class="d-flex">
                    <input type="text" class="form-control search-box me-2" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="user_list.php" class="btn btn-secondary ms-2">
                            <i class="bi bi-x"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Avatar</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>User Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php 
                        $rowNumber = ($currentPage - 1) * $entriesPerPage + 1;
                        while ($user = $result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><strong><?php echo $rowNumber++; ?></strong></td>
                                <td>
                                    <?php echo getAvatarHtml($user); ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['user_type'] === 'admin' ? 'primary' : 'info'; ?>">
                                        <?php echo getUserTypeDisplay($user['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form action="/admin/toggle_user_status.php" method="POST" class="d-inline" onsubmit="return confirm('Change status for user <?php echo htmlspecialchars($user['username']); ?>?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="badge border-0 bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>" style="cursor: pointer;" title="Click to toggle status">
                                            <?php echo ucfirst($user['status']); ?> <i class="bi bi-arrow-repeat ms-1"></i>
                                        </button>
                                    </form>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="/admin/edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="/admin/delete_user.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No users found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>&entries=<?php echo $entriesPerPage; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $currentPage == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&entries=<?php echo $entriesPerPage; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>&entries=<?php echo $entriesPerPage; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


