<?php
$pageTitle = 'Add New Supplier';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($name)) {
        $error = 'Supplier Name is required.';
    } elseif (!empty($phone) && !preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = 'Phone number must be exactly 11 digits and start with 09 (e.g., 09171234567)';
    } else {
        $stmt = $conn->prepare("INSERT INTO supplier (name, contact_person, email, phone, address, city, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $name, $contact_person, $email, $phone, $address, $city, $status);
        if ($stmt->execute()) {
            $success = 'Supplier added successfully!';
        } else {
            $error = 'Error adding supplier: ' . $conn->error;
        }
        $stmt->close();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-plus-circle"></i> Add New Supplier</h2>
    <a href="/suppliers/suppliers.php" class="btn btn-secondary">
        <i class="ph-bold ph-arrow-left"></i> Back to List
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-warning-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ph-bold ph-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" required
                        placeholder="e.g. SteelAsia Manufacturing Corp.">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contact Person</label>
                    <input type="text" class="form-control" name="contact_person" placeholder="e.g. Maria Santos">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-control" name="phone"
                        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="09171234567"
                        maxlength="11" pattern="09[0-9]{9}"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                    <div class="form-text text-muted small">Must be 11 digits starting with 09 (e.g. 09171234567)</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" placeholder="e.g. sales@steelasia.com">
                </div>
            </div>

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="address" placeholder="e.g. Bacolod Port Area">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" class="form-control" name="city" placeholder="e.g. Bacolod City">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="/suppliers/suppliers.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check"></i> Save Supplier
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>