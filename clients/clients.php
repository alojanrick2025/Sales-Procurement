<?php
$pageTitle = 'Business Partners';
require_once __DIR__ . '/../config.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// Active tab from URL parameter (support 'clients', 'suppliers', 'all', and alias 'customers' -> 'clients')
$activeTab = $_GET['tab'] ?? 'all';
if ($activeTab === 'customers') {
    $activeTab = 'clients';
}
if (!in_array($activeTab, ['all', 'clients', 'suppliers'])) {
    $activeTab = 'all';
}

// Search and filter
$search = trim($_GET['search'] ?? '');

$baseQuery = "SELECT * FROM clients WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $baseQuery .= " AND (name LIKE ? OR address LIKE ? OR city LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
    $types = 'sssss';
}

$baseQuery .= " ORDER BY id ASC";

$stmt = $conn->prepare($baseQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$allPartners = [];
$clientsList = [];
$suppliers = [];

while ($row = $result->fetch_assoc()) {
    $allPartners[] = $row;

    // Rely strictly on partner_type column
    $isSupplier = ($row['partner_type'] === 'supplier');
    if ($isSupplier) {
        $suppliers[] = $row;
    } else {
        $clientsList[] = $row;
    }
}
$stmt->close();
$conn->close();

// Prepare JSON payload for the Philippines map
$mapPartners = [];
foreach ($allPartners as $p) {
    if (!empty($p['latitude']) && !empty($p['longitude']) && floatval($p['latitude']) != 0) {
        $isSupp = ($p['partner_type'] === 'supplier');
        $mapPartners[] = [
            'id' => intval($p['id']),
            'name' => $p['name'],
            'type' => $isSupp ? 'supplier' : 'client',
            'type_label' => $isSupp ? 'Supplier' : 'Client',
            'type_code' => $isSupp ? 'S' : 'C',
            'address' => strip_tags($p['address']),
            'city' => $p['city'] ?? '',
            'phone' => $p['phone'] ?? '',
            'email' => $p['email'] ?? '',
            'lat' => floatval($p['latitude']),
            'lng' => floatval($p['longitude']),
        ];
    }
}

function renderPartnerTable($list, $showTypeBadge = true)
{
    if (empty($list)) {
        echo '<tr><td colspan="' . ($showTypeBadge ? '9' : '8') . '" class="text-center text-muted py-5">';
        echo '<i class="ph-bold ph-folder-open fs-1 d-block mb-2 text-secondary"></i>';
        echo 'No business partners found in this section.';
        echo '</td></tr>';
        return;
    }

    foreach ($list as $partner) {
        $isSupplier = ($partner['partner_type'] === 'supplier');
        echo '<tr>';
        echo '<td><strong>#' . htmlspecialchars($partner['id']) . '</strong></td>';

        if ($showTypeBadge) {
            echo '<td>';
            if ($isSupplier) {
                echo '<span class="badge" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-weight: 600; font-size: 0.75rem; padding: 4px 8px;"><i class="ph-bold ph-buildings me-1"></i> Supplier (S)</span>';
            } else {
                echo '<span class="badge" style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE; font-weight: 600; font-size: 0.75rem; padding: 4px 8px;"><i class="ph-bold ph-user-circle me-1"></i> Client (C)</span>';
            }
            echo '</td>';
        }

        echo '<td class="fw-semibold text-dark">' . htmlspecialchars($partner['name']) . '</td>';
        echo '<td class="text-secondary">' . htmlspecialchars(strip_tags($partner['address'])) . '</td>';
        echo '<td><span class="badge" style="background: #F0F4F2; color: #24312B; border: 1px solid #E3E8E5;">' . htmlspecialchars($partner['city'] ?: 'N/A') . '</span></td>';
        echo '<td>';
        if (!empty($partner['email'])) {
            echo '<div><i class="ph-bold ph-envelope-simple me-1 text-primary"></i> <span class="fw-medium">' . htmlspecialchars($partner['email']) . '</span></div>';
        }
        if (!empty($partner['phone'])) {
            echo '<div class="small text-muted"><i class="ph-bold ph-phone me-1"></i> ' . htmlspecialchars($partner['phone']) . '</div>';
        }
        if (empty($partner['email']) && empty($partner['phone'])) {
            echo '<span class="text-muted">N/A</span>';
        }
        echo '</td>';
        echo '<td><small class="text-muted">' . number_format($partner['latitude'], 6) . ', ' . number_format($partner['longitude'], 6) . '</small></td>';
        echo '<td class="text-muted small">' . date('M d, Y', strtotime($partner['created_at'])) . '</td>';
        echo '<td>';
        echo '<div class="btn-group btn-group-sm">';
        echo '<a href="/clients/edit_client.php?id=' . $partner['id'] . '" class="btn btn-outline-dark" title="Edit"><i class="bi bi-pencil"></i></a>';
        echo '<form action="/clients/delete_client.php" method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete this partner?\')">';
        echo csrfField();
        echo '<input type="hidden" name="id" value="' . $partner['id'] . '">';
        echo '<button type="submit" class="btn btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Delete"><i class="bi bi-trash"></i></button>';
        echo '</form>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    .custom-partner-tabs {
        background: #ffffff;
        padding: 6px;
        border-radius: 12px;
        border: 1px solid #E3E8E5;
        display: inline-flex;
        gap: 6px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    .custom-partner-tabs .nav-link {
        color: #6B756F;
        font-weight: 500;
        font-size: 0.9rem;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .custom-partner-tabs .nav-link:hover {
        color: #16231D;
        background-color: #F0F4F2;
    }

    .custom-partner-tabs .nav-link.active {
        background-color: #16231D !important;
        color: #D7FFE0 !important;
        border-color: #16231D;
        box-shadow: 0 3px 10px rgba(22, 35, 29, 0.15);
    }

    .custom-partner-tabs .nav-link .tab-count-badge {
        background: rgba(0, 0, 0, 0.06);
        color: inherit;
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 20px;
        font-weight: 600;
    }

    .custom-partner-tabs .nav-link.active .tab-count-badge {
        background: rgba(215, 255, 224, 0.25);
        color: #D7FFE0;
    }

    /* Custom Leaflet Teardrop Pin styling with C and S */
    .custom-partner-pin {
        background: transparent;
        border: none;
    }

    .partner-pin-inner {
        width: 34px;
        height: 34px;
        border-radius: 50% 50% 50% 0;
        transform: rotate(-45deg);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.35);
        border: 2px solid #ffffff;
        cursor: pointer;
        transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.2s ease;
    }

    .partner-pin-inner:hover {
        transform: rotate(-45deg) scale(1.18);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.45);
        z-index: 1000 !important;
    }

    .partner-pin-inner span {
        transform: rotate(45deg);
        font-weight: 800;
        font-size: 14px;
        color: #ffffff;
        line-height: 1;
        font-family: system-ui, -apple-system, sans-serif;
        letter-spacing: -0.5px;
    }

    .pin-client {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
    }

    .pin-supplier {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .map-legend-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .map-legend-circle {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 800;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }
</style>

<!-- Header & Title -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><i class="ph-bold ph-handshake"></i> Business Partners</h2>
        <span class="text-muted">Manage all clients and suppliers across sales &amp; procurement operations.</span>
    </div>
    <a href="/clients/add_client.php" class="btn btn-primary">
        <i class="ph-bold ph-plus-circle"></i> Add Business Partner
    </a>
</div>

<!-- Search Form -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" action="" class="row g-3" id="searchForm">
            <input type="hidden" name="tab" id="activeTabInput" value="<?php echo htmlspecialchars($activeTab); ?>">
            <div class="col-md-8">
                <input type="text" class="form-control" name="search"
                    placeholder="Search by name, address, city, phone, or email..."
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ph-bold ph-magnifying-glass"></i> Search
                </button>
            </div>
            <div class="col-md-2">
                <a href="/clients/clients.php?tab=<?php echo urlencode($activeTab); ?>" class="btn btn-secondary w-100">
                    <i class="ph-bold ph-arrow-counter-clockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Category Tabs -->
<div class="mb-3">
    <ul class="nav nav-pills custom-partner-tabs" id="partnerTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'all' ? 'active' : ''; ?>" id="all-tab"
                data-bs-toggle="pill" data-bs-target="#all-pane" type="button" role="tab" data-tab-name="all">
                <i class="ph-bold ph-handshake me-1"></i> All Partners
                <span class="badge ms-2 tab-count-badge"><?php echo count($allPartners); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'clients' ? 'active' : ''; ?>" id="clients-tab"
                data-bs-toggle="pill" data-bs-target="#clients-pane" type="button" role="tab" data-tab-name="clients">
                <i class="ph-bold ph-user-circle me-1"></i> Clients
                <span class="badge ms-2 tab-count-badge"><?php echo count($clientsList); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'suppliers' ? 'active' : ''; ?>" id="suppliers-tab"
                data-bs-toggle="pill" data-bs-target="#suppliers-pane" type="button" role="tab"
                data-tab-name="suppliers">
                <i class="ph-bold ph-buildings me-1"></i> Suppliers
                <span class="badge ms-2 tab-count-badge"><?php echo count($suppliers); ?></span>
            </button>
        </li>
    </ul>
</div>

<!-- Tab Contents -->
<div class="tab-content mb-4" id="partnerTabsContent">
    <!-- Tab 1: All Partners -->
    <div class="tab-pane fade <?php echo $activeTab === 'all' ? 'show active' : ''; ?>" id="all-pane" role="tabpanel">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="ph-bold ph-handshake me-1"></i> All Business Partners</span>
                <span class="badge bg-secondary"><?php echo count($allPartners); ?> Total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>City</th>
                                <th>Contact Info</th>
                                <th>Coordinates</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php renderPartnerTable($allPartners, true); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 2: Clients -->
    <div class="tab-pane fade <?php echo $activeTab === 'clients' ? 'show active' : ''; ?>" id="clients-pane"
        role="tabpanel">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="ph-bold ph-user-circle text-primary me-1"></i> Client Accounts (ID
                    #1, #3, #4, #5)</span>
                <span
                    class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo count($clientsList); ?>
                    Clients</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>City</th>
                                <th>Contact Info</th>
                                <th>Coordinates</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php renderPartnerTable($clientsList, true); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab 3: Suppliers -->
    <div class="tab-pane fade <?php echo $activeTab === 'suppliers' ? 'show active' : ''; ?>" id="suppliers-pane"
        role="tabpanel">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="ph-bold ph-buildings text-warning me-1"></i> Supplier Accounts (ID
                    #2, #6)</span>
                <span
                    class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><?php echo count($suppliers); ?>
                    Suppliers</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>City</th>
                                <th>Contact Info</th>
                                <th>Coordinates</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php renderPartnerTable($suppliers, true); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- PHILIPPINES NATIONWIDE PARTNER MAPPING (C: Client, S: Supplier) -->
<!-- ========================================================== -->
<div class="card shadow-sm border mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-bold" style="color: #16231D;"><i class="ph-bold ph-map-trifold me-2 text-primary"></i>
                Business Partners Geographic Mapping</h5>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="map-legend-pill" style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE;">
                <span class="map-legend-circle pin-client">C</span> Client (<?php echo count($clientsList); ?>)
            </span>
            <span class="map-legend-pill" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A;">
                <span class="map-legend-circle pin-supplier">S</span> Supplier (<?php echo count($suppliers); ?>)
            </span>
            <button type="button" class="btn btn-sm btn-outline-dark" id="btnFitMap"
                title="Fit all partner markers on the map">
                <i class="ph-bold ph-arrows-out-cardinal"></i> Fit All
            </button>
        </div>
    </div>
    <div class="card-body p-0 position-relative">
        <div id="philippinesPartnerMap" style="height: 520px; width: 100%; z-index: 1;"></div>
    </div>
    <div class="card-footer py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2 small text-muted"
        style="background: #F7F9F8; border-top: 1px solid #E3E8E5;">
        <div>
            <i class="ph-bold ph-info me-1 text-primary"></i> Marker Legend:
            <span class="badge me-1" style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE;"><strong
                    style="font-size: 11px;">C</strong></span> <strong>Client</strong> &nbsp;|&nbsp;
            <span class="badge me-1" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A;"><strong
                    style="font-size: 11px;">S</strong></span> <strong>Supplier</strong>.
            Click any pin to view details and contact info.
        </div>
        <div id="mapStatusText">
            Loaded <strong><?php echo count($mapPartners); ?></strong> partner coordinates across the Philippines.
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Tab Synchronization
        const tabButtons = document.querySelectorAll('#partnerTabs button[data-bs-toggle="pill"]');
        tabButtons.forEach(function (tabBtn) {
            tabBtn.addEventListener('shown.bs.tab', function (e) {
                var tabName = e.target.getAttribute('data-tab-name');
                var input = document.getElementById('activeTabInput');
                if (input) {
                    input.value = tabName;
                }
                var url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', url);

                // Invalidate map size and filter markers to match tab
                if (window.partnerMap) {
                    setTimeout(function () {
                        window.partnerMap.invalidateSize();
                        filterMapMarkers(tabName);
                    }, 100);
                }
            });
        });

        // 2. Initialize Leaflet Map of the Philippines
        // Centered around the Philippine archipelago
        const defaultCenter = [11.5, 122.5];
        const defaultZoom = 6;

        const map = L.map('philippinesPartnerMap', {
            center: defaultCenter,
            zoom: defaultZoom,
            scrollWheelZoom: true
        });
        window.partnerMap = map;

        // Add OpenStreetMap tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18,
            minZoom: 5
        }).addTo(map);

        // Partner data injected from PHP
        const partners = <?php echo json_encode($mapPartners, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        // Custom Icon Factory for "C" (Client) and "S" (Supplier)
        function createPartnerIcon(typeCode, typeClass) {
            return L.divIcon({
                className: 'custom-partner-pin',
                html: '<div class="partner-pin-inner ' + typeClass + '"><span>' + typeCode + '</span></div>',
                iconSize: [34, 34],
                iconAnchor: [17, 34],
                popupAnchor: [0, -32]
            });
        }

        const clientIcon = createPartnerIcon('C', 'pin-client');
        const supplierIcon = createPartnerIcon('S', 'pin-supplier');

        const markers = [];
        const markerBounds = L.latLngBounds([]);

        partners.forEach(function (p) {
            const isClient = (p.type === 'client');
            const icon = isClient ? clientIcon : supplierIcon;
            const typeBadge = isClient
                ? '<span class="badge" style="background:#0d6efd; color:#fff; font-size:11px; padding:3px 7px;">Client (C)</span>'
                : '<span class="badge" style="background:#f59e0b; color:#fff; font-size:11px; padding:3px 7px;">Supplier (S)</span>';

            const popupContent = `
            <div style="font-family:inherit; min-width:210px; padding:2px;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    ${typeBadge}
                    <small class="text-muted fw-bold">#${p.id}</small>
                </div>
                <h6 class="fw-bold mb-1 text-dark" style="font-size:14px;">${p.name}</h6>
                <div class="text-secondary small mb-2">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> ${p.address}${p.city ? ' &bull; ' + p.city : ''}
                </div>
                ${p.email ? `<div class="small text-muted mb-1"><i class="bi bi-envelope-fill text-primary me-1"></i> ${p.email}</div>` : ''}
                ${p.phone ? `<div class="small text-muted mb-2"><i class="bi bi-telephone-fill text-success me-1"></i> ${p.phone}</div>` : ''}
                <div class="d-flex gap-1 mt-2 pt-2 border-top">
                    <a href="/clients/edit_client.php?id=${p.id}" class="btn btn-sm btn-outline-primary w-100 py-1" style="font-size:11px;">
                        <i class="bi bi-pencil-square me-1"></i> Edit Partner
                    </a>
                </div>
            </div>
        `;

            const marker = L.marker([p.lat, p.lng], { icon: icon })
                .bindPopup(popupContent, { maxWidth: 280 })
                .bindTooltip('<strong>[' + p.type_code + ']</strong> ' + p.name, {
                    direction: 'top',
                    offset: [0, -30],
                    className: 'small'
                });

            marker.partnerType = p.type;
            marker.addTo(map);
            markers.push(marker);
            markerBounds.extend([p.lat, p.lng]);
        });

        // Auto fit map to show all partner markers if any exist
        function fitAllMarkers() {
            if (markers.length > 0 && markerBounds.isValid()) {
                map.fitBounds(markerBounds, { padding: [50, 50], maxZoom: 14 });
            } else {
                map.setView(defaultCenter, defaultZoom);
            }
        }

        if (markers.length > 0) {
            fitAllMarkers();
        }

        // Fit button event
        document.getElementById('btnFitMap').addEventListener('click', function () {
            fitAllMarkers();
        });

        // Filter markers on tab switch
        function filterMapMarkers(tabName) {
            const activeBounds = L.latLngBounds([]);
            let visibleCount = 0;

            markers.forEach(function (m) {
                let show = false;
                if (tabName === 'all') {
                    show = true;
                } else if (tabName === 'clients' && m.partnerType === 'client') {
                    show = true;
                } else if (tabName === 'suppliers' && m.partnerType === 'supplier') {
                    show = true;
                }

                if (show) {
                    if (!map.hasLayer(m)) {
                        map.addLayer(m);
                    }
                    activeBounds.extend(m.getLatLng());
                    visibleCount++;
                } else {
                    if (map.hasLayer(m)) {
                        map.removeLayer(m);
                    }
                }
            });

            const statusEl = document.getElementById('mapStatusText');
            if (statusEl) {
                statusEl.innerHTML = `Showing <strong>${visibleCount}</strong> ${tabName === 'all' ? 'partner' : tabName} locations on map.`;
            }

            if (visibleCount > 0 && activeBounds.isValid()) {
                map.fitBounds(activeBounds, { padding: [50, 50], maxZoom: 14 });
            }
        }

        // Run initial filter based on active tab
        const initialTab = '<?php echo $activeTab; ?>';
        if (initialTab !== 'all') {
            filterMapMarkers(initialTab);
        }
    });
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>