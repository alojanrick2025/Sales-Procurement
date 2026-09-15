<?php
require_once __DIR__ . '/../config.php';
requireLogin();
$currentUser = getCurrentUser();
$admin = getCurrentAdmin();
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
    return '<div class="header-avatar-initials">' . htmlspecialchars($initials) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Admin Panel'; ?> - Sales and Procurement Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Phosphor Icons -->
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css"/>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css"/>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"/>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --forest-charcoal: #16231D;
            --forest-header: #1B2A22;
            --forest-dark: #121C17;
            --forest-hover: rgba(215, 255, 224, 0.08);
            --bg-main: #F7F9F8;
            --card-bg: #FFFFFF;
            --text-main: #24312B;
            --text-secondary: #6B756F;
            --border-color: #E3E8E5;
            --ghost-green: #D7FFE0;
            --ghost-green-rgb: 215, 255, 224;
            --ghost-green-glow: rgba(215, 255, 224, 0.2);
            /* Aliases for backwards compatibility */
            --zero-black: #16231D;
            --zero-dark: #1B2A22;
            --zero-card: #203328;
        }
        * {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background-color: #F7F9F8;
            color: #24312B;
        }
        .app-layout-wrapper {
            display: flex;
            width: 100%;
            min-height: calc(100vh - 66px);
            position: relative;
            overflow-x: hidden;
        }
        .sidebar {
            width: 260px;
            min-width: 260px;
            max-width: 260px;
            min-height: calc(100vh - 66px);
            background: #16231D;
            border-right: 1px solid rgba(215, 255, 224, 0.08);
            color: white;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease;
            flex-shrink: 0;
            overflow-y: auto;
            position: relative;
            z-index: 100;
        }
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #16231D;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(215, 255, 224, 0.2);
            border-radius: 4px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(215, 255, 224, 0.4);
        }
        .hamburger-btn {
            background: rgba(215, 255, 224, 0.08);
            border: 1px solid rgba(215, 255, 224, 0.2);
            color: var(--ghost-green);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0;
            user-select: none;
            flex-shrink: 0;
        }
        .hamburger-btn:hover, .hamburger-btn:focus {
            background: var(--ghost-green);
            border-color: var(--ghost-green);
            color: #16231D;
            box-shadow: 0 0 14px var(--ghost-green-glow);
            outline: none;
        }
        .hamburger-btn i {
            font-size: 1.4rem;
            line-height: 1;
        }
        /* Desktop Collapsed State */
        body.sidebar-collapsed .sidebar,
        html.sidebar-collapsed .sidebar {
            margin-left: -260px;
            opacity: 0;
            pointer-events: none;
        }
        /* Mobile Responsive */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 66px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
            z-index: 1040;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 66px;
                left: 0;
                bottom: 0;
                height: calc(100vh - 66px);
                min-height: calc(100vh - 66px);
                margin-left: -280px;
                width: 280px;
                max-width: 85vw;
                z-index: 1050;
                box-shadow: none;
                transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
            }
            body.sidebar-mobile-open .sidebar {
                margin-left: 0;
                opacity: 1;
                pointer-events: auto;
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.5);
            }
            body.sidebar-mobile-open .sidebar-backdrop {
                display: block;
                opacity: 1;
            }
            body.sidebar-collapsed .sidebar,
            html.sidebar-collapsed .sidebar {
                margin-left: -280px;
                opacity: 1;
                pointer-events: auto;
            }
            body.sidebar-collapsed.sidebar-mobile-open .sidebar {
                margin-left: 0;
            }
        }
        .sidebar-brand-title {
            color: var(--ghost-green);
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-brand-title i {
            color: var(--ghost-green);
            font-size: 1.5rem;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.72);
            padding: 11px 18px;
            margin: 4px 10px;
            border-radius: 9px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.92rem;
            font-weight: 400;
        }
        .sidebar .nav-link i {
            font-size: 1.25rem;
            color: rgba(215, 255, 224, 0.75);
            transition: color 0.2s ease;
        }
        .sidebar .nav-link:hover {
            background: rgba(215, 255, 224, 0.08);
            color: var(--ghost-green);
        }
        .sidebar .nav-link:hover i {
            color: var(--ghost-green);
        }
        .sidebar .nav-link.active {
            background: var(--ghost-green) !important;
            color: var(--forest-charcoal) !important;
            font-weight: 600;
            box-shadow: 0 3px 12px rgba(215, 255, 224, 0.22);
        }
        .sidebar .nav-link.active i {
            color: var(--forest-charcoal) !important;
        }
        .sidebar .nav-link.text-danger {
            color: #f87171 !important;
        }
        .sidebar .nav-link.text-danger:hover {
            background: rgba(248, 113, 113, 0.12);
            color: #fca5a5 !important;
        }
        .sidebar .nav-link.text-danger i {
            color: #f87171 !important;
        }
        .main-content {
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: calc(100vh - 66px);
            flex-grow: 1;
            min-width: 0;
            width: 100%;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card {
            border-radius: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid var(--border-color);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(22, 35, 29, 0.08) !important;
        }
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 4px 12px rgba(22, 35, 29, 0.03);
            color: var(--text-main);
        }
        .card-header {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            font-weight: 600;
            border-top-left-radius: 12px !important;
            border-top-right-radius: 12px !important;
        }
        .text-muted {
            color: var(--text-secondary) !important;
        }
        .btn-primary {
            background-color: var(--forest-charcoal) !important;
            color: var(--ghost-green) !important;
            border: 1.5px solid var(--forest-charcoal) !important;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: #24372D !important;
            color: #FFFFFF !important;
            border-color: #24372D !important;
            box-shadow: 0 4px 14px rgba(22, 35, 29, 0.18);
        }
        .btn-outline-dark {
            border-color: #D9E2DE !important;
            color: var(--text-main) !important;
            background-color: #FFFFFF !important;
            font-weight: 500;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }
        .btn-outline-dark:hover {
            background-color: #F0F4F2 !important;
            border-color: #CBD5E1 !important;
            color: var(--forest-charcoal) !important;
        }

        /* ==========================================================================
           GLOBAL BADGE SYSTEM - PASTEL / THEME COLORS MATCHING SPEC
           ========================================================================== */
        .badge {
            font-weight: 600 !important;
            font-size: 0.76rem !important;
            letter-spacing: 0.3px;
            padding: 4px 10px !important;
            border-radius: 6px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 5px !important;
            box-shadow: none !important;
            text-shadow: none !important;
        }

        /* Pending / Sent / Expired - Warm Peach / Soft Orange */
        .badge.bg-warning,
        .badge.bg-warning.text-dark,
        .badge-pending,
        .badge-sent,
        .badge-expired,
        .status-pending,
        .status-sent {
            background-color: #FEF6E9 !important;
            color: #9A3412 !important;
            border: 1px solid #FDBA74 !important;
        }

        /* Processing / Approved - Soft Blue */
        .badge.bg-primary,
        .badge.bg-primary.text-white,
        .badge.bg-info,
        .badge.bg-info.text-white,
        .badge.bg-info.text-dark,
        .badge-processing,
        .badge-approved,
        .status-processing,
        .status-approved {
            background-color: #EFF6FF !important;
            color: #1D4ED8 !important;
            border: 1px solid #BFDBFE !important;
        }

        /* Completed / Accepted / Active - Soft Mint Green */
        .badge.bg-success,
        .badge.bg-success.text-white,
        .badge-completed,
        .badge-accepted,
        .badge-active,
        .status-completed,
        .status-accepted {
            background-color: #EAF8EF !important;
            color: #166534 !important;
            border: 1px solid #BBF7D0 !important;
        }

        /* Cancelled / Rejected / Inactive - Soft Light Red */
        .badge.bg-danger,
        .badge.bg-danger.text-white,
        .badge-cancelled,
        .badge-rejected,
        .badge-inactive,
        .status-cancelled,
        .status-rejected {
            background-color: #FEF2F2 !important;
            color: #991B1B !important;
            border: 1px solid #FECACA !important;
        }

        /* Draft / Secondary - Soft Slate Gray */
        .badge.bg-secondary,
        .badge.bg-secondary.text-white,
        .badge-draft,
        .status-draft {
            background-color: #F3F4F6 !important;
            color: #4B5563 !important;
            border: 1px solid #E5E7EB !important;
        }

        /* Transaction Types */
        .badge-type-quotation {
            background-color: #EBF5EE !important;
            color: #1E402B !important;
            border: 1px solid #D0E7D6 !important;
        }

        .badge-type-customer-po {
            background-color: #EFF6FF !important;
            color: #1D4ED8 !important;
            border: 1px solid #DBEAFE !important;
        }

        .badge-type-supplier-po {
            background-color: #FEF3C7 !important;
            color: #92400E !important;
            border: 1px solid #FDE68A !important;
        }
        table thead {
            background-color: #F0F4F2;
        }
        table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.6px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            background-color: #F0F4F2;
        }
        table tbody td {
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
        }
        table tbody tr:hover {
            background-color: #F8FAF9;
        }
        .form-control, .form-select {
            border-color: var(--border-color);
            color: var(--text-main);
            background-color: #FFFFFF;
        }
        .form-control:focus, .form-select:focus {
            border-color: #38664B;
            box-shadow: 0 0 0 0.2rem rgba(56, 102, 75, 0.15);
            color: var(--text-main);
        }
        .nav-separator {
            color: var(--ghost-green);
            opacity: 0.75;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 14px 18px 4px 18px;
            margin: 16px 10px 4px 10px;
            border-top: 1px solid rgba(215, 255, 224, 0.08);
        }
        .top-navbar {
            background: var(--forest-header);
            color: white;
            padding: 13px 24px;
            border-bottom: 1px solid rgba(215, 255, 224, 0.1);
            box-shadow: 0 2px 14px rgba(22, 35, 29, 0.16);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .top-navbar .navbar-brand {
            color: var(--ghost-green);
            font-weight: 600;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.3px;
        }
        .top-navbar .navbar-brand i {
            font-size: 1.6rem;
            color: var(--ghost-green);
        }
        .user-profile-dropdown {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }
        .header-avatar-img, .header-avatar-initials {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .header-avatar-initials {
            background: var(--ghost-green);
            color: var(--forest-charcoal);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            border: 2px solid var(--forest-header);
        }
        .header-avatar-img {
            border: 2px solid var(--ghost-green);
        }
        .user-info {
            display: flex;
            flex-direction: column;
        }
        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            line-height: 1.2;
            color: #ffffff;
        }
        .user-role {
            font-size: 0.75rem;
            color: var(--ghost-green);
            opacity: 0.85;
            line-height: 1.2;
        }
        .dropdown-menu {
            border: 1px solid var(--border-color);
            box-shadow: 0 6px 20px rgba(22, 35, 29, 0.08);
            border-radius: 10px;
            margin-top: 8px;
        }
        .dropdown-item {
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--text-main);
        }
        .dropdown-item:hover {
            background-color: #F0F4F2;
            color: var(--forest-charcoal);
        }
        .dropdown-item i {
            font-size: 1.15rem;
        }
    </style>
    <script>
        (function() {
            try {
                if (window.innerWidth > 768 && localStorage.getItem('sales_sidebar_collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch(e) {}
        })();
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
            <div class="user-profile-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <?php echo getHeaderAvatarHtml($currentUser, $sysLogo); ?>
                <div class="user-info">
                    <span class="user-name"><?php 
                        $displayName = !empty($systemInfoGlobal['user_name']) ? $systemInfoGlobal['user_name'] : $currentUser['full_name'];
                        echo htmlspecialchars($displayName); 
                    ?></span>
                    <span class="user-role"><?php echo getUserTypeDisplay($currentUser['user_type']); ?></span>
                </div>
                <i class="ph-bold ph-caret-down text-white-50 ms-1"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="/admin/my_profile.php">
                        <i class="ph-bold ph-user-circle"></i> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item text-danger" href="/auth/logout.php">
                        <i class="ph-bold ph-sign-out"></i> Logout
                    </a>
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
                            <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['customer_po.php', 'add_customer_po.php', 'view_customer_po.php']) ? 'active' : ''; ?>" href="/orders/customer_po.php">
                                <i class="ph-bold ph-receipt"></i> Customer Purchase Orders
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

