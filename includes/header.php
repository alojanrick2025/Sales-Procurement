<?php
require_once __DIR__ . '/../config.php';
requireLogin();
$currentUser = getCurrentUser();
$admin = ($currentUser && $currentUser['user_type'] === 'admin') ? $currentUser : null;
$systemInfoGlobal = getSystemInfo();
$companyName = !empty($systemInfoGlobal['company_name']) ? $systemInfoGlobal['company_name'] : (!empty($systemInfoGlobal['short_name']) ? $systemInfoGlobal['short_name'] : 'JUSTLY ELECTRICAL SUPPLIES AND SERVICES');
$sysShortName = $companyName;
$sysLogo = !empty($systemInfoGlobal['logo']) ? '/' . ltrim($systemInfoGlobal['logo'], '/') : '';

// Function to get user initials for avatar
function getUserInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

// Function to get user type display name
function getUserTypeDisplay($type) {
    return 'Administrator';
}

// Function to get avatar HTML for header
function getHeaderAvatarHtml($user, $sysLogo = '') {
    if (!empty($user['avatar'])) {
        // Handle different avatar path formats
        $avatarPath = $user['avatar'];
        if (strpos($avatarPath, '/') !== 0) {
            $avatarPath = '/' . $avatarPath;
        }
        // Check if file exists (try both with and without leading slash)
        $fullPath1 = __DIR__ . '/..' . $avatarPath;
        $fullPath2 = __DIR__ . '/../' . ltrim($avatarPath, '/');
        if (file_exists($fullPath1) || file_exists($fullPath2)) {
            return '<img src="' . htmlspecialchars($avatarPath) . '" alt="Avatar" class="header-avatar-img rounded-circle">';
        }
    }
    if (!empty($sysLogo)) {
        $fullLogo1 = __DIR__ . '/..' . $sysLogo;
        $fullLogo2 = __DIR__ . '/../' . ltrim($sysLogo, '/');
        if (file_exists($fullLogo1) || file_exists($fullLogo2)) {
            return '<img src="' . htmlspecialchars($sysLogo) . '" alt="Avatar" class="header-avatar-img rounded-circle">';
        }
    }
    $initials = getUserInitials($user['full_name']);
    return '<span class="header-avatar-initials">' . htmlspecialchars($initials) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Admin Panel'; ?> - Sales and Procurement Management System</title>
    <?php echo faviconTag(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php echo iconFontTags(); ?>
    <?php if (!empty($useBootstrapIcons)): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo assetUrl('/assets/css/app.css'); ?>">
    <script>
        (function() {
            try {
                if (window.innerWidth > 768 && localStorage.getItem('sales_sidebar_collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch(e) {}
        })();

        // Call fn only once the user pauses for `wait` ms (used by the live search boxes,
        // which re-check every table row)
        function debounce(fn, wait) {
            var timer;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function() { fn.apply(context, args); }, wait);
            };
        }
    </script>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="top-navbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <button type="button" class="btn hamburger-btn me-3" id="sidebarToggle" aria-label="Toggle Sidebar" title="Toggle Sidebar">
                <i class="ph-bold ph-list"></i>
            </button>
            <div class="navbar-brand mb-0 d-flex align-items-center gap-2">
                <?php if (!empty($sysLogo)): ?>
                    <img src="<?php echo htmlspecialchars($sysLogo); ?>" alt="Logo" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(215, 255, 224, 0.3);">
                <?php else: ?>
                    <i class="ph-bold ph-package"></i>
                <?php endif; ?>
                <span>Sales and Procurement Management System</span>
            </div>
        </div>
        
        <?php if ($currentUser): ?>
        <div class="dropdown">
            <button type="button" class="user-profile-dropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                <?php echo getHeaderAvatarHtml($currentUser, $sysLogo); ?>
                <span class="user-info">
                    <span class="user-name"><?php
                        $displayName = !empty($systemInfoGlobal['user_name']) ? $systemInfoGlobal['user_name'] : $currentUser['full_name'];
                        echo htmlspecialchars($displayName);
                    ?></span>
                    <span class="user-role"><?php echo getUserTypeDisplay($currentUser['user_type']); ?></span>
                </span>
                <i class="ph-bold ph-caret-down text-white-50 ms-1"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="/admin/my_profile.php">
                        <i class="ph-bold ph-user-circle"></i> My Profile
                    </a>
                </li>
                <li>
                    <!-- POST with a token, so a browser preloading links can't log the user out -->
                    <form method="POST" action="/auth/logout.php" class="m-0">
                        <?php echo csrfField(); ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="ph-bold ph-sign-out"></i> Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </nav>

    <div class="container-fluid p-0">
        <div class="app-layout-wrapper">
            <!-- Sidebar -->
            <nav class="sidebar" id="sidebar">
                <div class="p-3 pt-4">
                    <div class="d-flex align-items-center justify-content-between px-3 mb-4">
                        <h4 class="sidebar-brand-title mb-0 d-flex align-items-center gap-2">
                            <?php if (!empty($sysLogo)): ?>
                                <img src="<?php echo htmlspecialchars($sysLogo); ?>" alt="Logo" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(215, 255, 224, 0.3);">
                            <?php else: ?>
                                <i class="ph-bold ph-package"></i>
                            <?php endif; ?>
                            <span>Sales & Procurement</span>
                        </h4>
                        <button type="button" class="btn hamburger-btn d-md-none" id="sidebarCloseBtn" aria-label="Close Sidebar" title="Close Sidebar">
                            <i class="ph-bold ph-x"></i>
                        </button>
                    </div>
                    
                    <!-- Main Navigation -->
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="/admin/index.php">
                                <i class="ph-bold ph-squares-four"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['clients.php', 'add_client.php', 'edit_client.php', 'suppliers.php']) ? 'active' : ''; ?>" href="/clients/clients.php">
                                <i class="ph-bold ph-handshake"></i> Business Partners
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['quotation.php', 'add_quotation.php', 'edit_quotation.php', 'view_quotation.php']) ? 'active' : ''; ?>" href="/quotation/quotation.php">
                                <i class="ph-bold ph-file-text"></i> Quotations
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['supplier_po.php', 'add_supplier_po.php', 'view_supplier_po.php']) ? 'active' : ''; ?>" href="/orders/supplier_po.php">
                                <i class="ph-bold ph-shopping-cart"></i> Supplier Purchase Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['items.php', 'add_item.php', 'edit_item.php']) ? 'active' : ''; ?>" href="/items/items.php">
                                <i class="ph-bold ph-archive"></i> Inventory
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Reports Section -->
                    <div class="nav-separator">
                        Reports
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sales_report.php' ? 'active' : ''; ?>" href="/reports/sales_report.php">
                                <i class="ph-bold ph-chart-line-up"></i> Sales Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'procurement_report.php' ? 'active' : ''; ?>" href="/reports/procurement_report.php">
                                <i class="ph-bold ph-chart-pie-slice"></i> Procurement Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" href="/reports/transactions.php">
                                <i class="ph-bold ph-clock-counter-clockwise"></i> Transaction History
                            </a>
                        </li>
                    </ul>
                    
                    <!-- System Section -->
                    <?php if ($currentUser && $currentUser['user_type'] === 'admin'): ?>
                    <div class="nav-separator">
                        System
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system_info.php' ? 'active' : ''; ?>" href="/admin/system_info.php">
                                <i class="ph-bold ph-gear-six"></i> System Information
                            </a>
                        </li>
                    </ul>
                    <?php endif; ?>
                </div>
                
            </nav>
            
            <!-- Mobile Sidebar Backdrop Overlay -->
            <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

            <!-- Main Content -->
            <main class="main-content flex-grow-1 p-4" id="mainContent">

