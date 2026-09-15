<?php
$pageTitle = 'Add Business Partner';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $partner_type = $_POST['partner_type'] ?? 'customer';
    if (!in_array($partner_type, ['customer', 'supplier'])) {
        $partner_type = 'customer';
    }
    $address = trim($_POST['address'] ?? '');
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $city = trim($_POST['city'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($latitude) || empty($longitude)) {
        $error = 'Please fill in all required fields (Partner Name, Partner Type, Company Email, Phone Number, Address, and Coordinates)';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid company email address (e.g. contact@company.com)';
    } elseif (!preg_match('/^09[0-9]{9}$/', $phone)) {
        $error = 'Phone number must be exactly 11 digits and start with 09 (e.g., 09171234567)';
    } else {
        // Validate coordinates
        $lat = floatval($latitude);
        $lng = floatval($longitude);

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $error = 'Invalid coordinates. Latitude must be between -90 and 90, Longitude between -180 and 180';
        } else {
            $stmt = $conn->prepare("INSERT INTO clients (name, partner_type, email, phone, address, latitude, longitude, city) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssdds", $name, $partner_type, $email, $phone, $address, $lat, $lng, $city);

            if ($stmt->execute()) {
                $success = 'Business Partner added successfully!';
                $_POST = [];
                $name = $address = $city = $email = $phone = '';
                $latitude = $longitude = '';
            } else {
                $error = 'Error adding business partner: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="ph-bold ph-plus-circle"></i> Add Business Partner</h2>
    <a href="/clients/clients.php" class="btn btn-secondary">
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

<div class="card">
    <div class="card-body">
        <form method="POST" action="" id="clientForm">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label class="form-label">Partner Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name"
                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required
                        placeholder="Company or Entity Name">
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label">Partner Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="partner_type" required>
                        <?php $selectedType = $_POST['partner_type'] ?? $_GET['partner_type'] ?? 'customer'; ?>
                        <option value="customer" <?php echo $selectedType === 'customer' ? 'selected' : ''; ?>>Client</option>
                        <option value="supplier" <?php echo $selectedType === 'supplier' ? 'selected' : ''; ?>>Supplier</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" class="form-control" name="city"
                        value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" placeholder="e.g., Bacolod City">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Company Email <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ph-bold ph-envelope-simple"></i></span>
                        <input type="email" class="form-control" name="email" id="email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required
                            placeholder="e.g., info@company.com">
                    </div>
                    <small class="text-muted">Required official business email address</small>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ph-bold ph-phone"></i></span>
                        <input type="tel" class="form-control" name="phone" id="phone"
                            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required
                            placeholder="09171234567" maxlength="11" pattern="09[0-9]{9}"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)">
                    </div>
                    <small class="text-muted">Must be 11 digits starting with 09 (e.g. 09171234567)</small>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Address <span class="text-danger">*</span></label>
                <textarea class="form-control" name="address" rows="3" required
                    placeholder="Full business or office address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
            </div>

            <hr class="my-4">
            <h5><i class="bi bi-geo-alt"></i> Location Coordinates</h5>
            <p class="text-muted small">Click on the map below to set the client's location, or enter coordinates
                manually.</p>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Latitude <span class="text-danger">*</span></label>
                    <input type="number" step="0.00000001" class="form-control" name="latitude" id="latitude"
                        value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Longitude <span class="text-danger">*</span></label>
                    <input type="number" step="0.00000001" class="form-control" name="longitude" id="longitude"
                        value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>" required>
                </div>
            </div>

            <!-- Map for coordinate selection -->
            <div class="mb-4">
                <div id="map" style="height: 400px; width: 100%; border-radius: 10px; border: 1px solid #dee2e6;"></div>
                <small class="text-muted">Click on the map to set coordinates</small>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="/clients/clients.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-bold ph-check-circle"></i> Save Business Partner
                </button>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Initialize map centered on Philippines
    var map = L.map('map').setView([10.3157, 123.8854], 7);

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Default marker (can be moved)
    var marker = L.marker([10.3157, 123.8854], { draggable: true }).addTo(map);

    // Update coordinates when marker is dragged
    marker.on('dragend', function (e) {
        var pos = marker.getLatLng();
        document.getElementById('latitude').value = pos.lat.toFixed(8);
        document.getElementById('longitude').value = pos.lng.toFixed(8);
    });

    // Update marker position when map is clicked
    map.on('click', function (e) {
        var lat = e.latlng.lat;
        var lng = e.latlng.lng;

        marker.setLatLng([lat, lng]);
        document.getElementById('latitude').value = lat.toFixed(8);
        document.getElementById('longitude').value = lng.toFixed(8);
    });

    // Update marker when coordinates are manually entered
    document.getElementById('latitude').addEventListener('change', updateMarker);
    document.getElementById('longitude').addEventListener('change', updateMarker);

    function updateMarker() {
        var lat = parseFloat(document.getElementById('latitude').value);
        var lng = parseFloat(document.getElementById('longitude').value);

        if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 13);
        }
    }

    // Set initial coordinates if they exist
    var initialLat = document.getElementById('latitude').value;
    var initialLng = document.getElementById('longitude').value;
    if (initialLat && initialLng) {
        var lat = parseFloat(initialLat);
        var lng = parseFloat(initialLng);
        if (!isNaN(lat) && !isNaN(lng)) {
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 13);
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>