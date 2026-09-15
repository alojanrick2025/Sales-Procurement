<?php
// Router script for PHP built-in server
// Handles routing for the reorganized folder structure

$request_uri = $_SERVER['REQUEST_URI'];
$request_path = parse_url($request_uri, PHP_URL_PATH);
$path = ltrim($request_path, '/');
$file_path = explode('?', $path)[0];
$normalized_path = str_replace('\\', '/', $file_path);

// Block direct execution or access to executable scripts in uploads/ directory
if (stripos($normalized_path, 'uploads/') === 0 && preg_match('/\.(php|phtml|phar|inc|cgi|pl|py|sh|bat|cmd)$/i', $normalized_path)) {
    http_response_code(403);
    echo "<h1>403 - Forbidden</h1><p>Access denied to executable files in uploads directory.</p>";
    exit();
}

// Block access to sensitive files, scripts, and configuration files (.sql, .bat, .md, .env, .ini, etc.)
if (preg_match('/\.(sql|bat|cmd|sh|ps1|md|env|ini|config|cfg|yml|yaml|json|lock|log|bak|backup|old|dist|htaccess|htpasswd)$/i', $normalized_path) || preg_match('/(^|\/)\.[^\/]+/', $normalized_path)) {
    http_response_code(403);
    echo "<h1>403 - Forbidden</h1><p>Access to this resource is prohibited.</p>";
    exit();
}

// Block direct access to internal directories (migrations, scratch)
if (preg_match('/^(migrations|scratch)(\/|$)/i', $normalized_path)) {
    http_response_code(403);
    echo "<h1>403 - Forbidden</h1><p>Access to this directory is prohibited.</p>";
    exit();
}

// Serve static files directly (CSS, JS, images, etc.)
if (preg_match('/\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$/i', $file_path)) {
    return false; // Let PHP server handle static files
}

// Root access - redirect to login
if (empty($file_path) || $file_path === 'index.php' || $file_path === '/') {
    header('Location: /auth/login.php');
    exit();
}

// Map old paths to new folder structure (backward compatibility)
$path_map = [
    'login.php' => 'auth/login.php',
    'logout.php' => 'auth/logout.php',
    'index.php' => 'admin/index.php',
    'user_list.php' => 'admin/user_list.php',
    'system_info.php' => 'admin/system_info.php',
    'toggle_user_status.php' => 'admin/toggle_user_status.php',
    'clients.php' => 'clients/clients.php',
    'add_client.php' => 'clients/add_client.php',
    'edit_client.php' => 'clients/edit_client.php',
    'delete_client.php' => 'clients/delete_client.php',
    'add_user.php' => 'admin/add_user.php',
    'edit_user.php' => 'admin/edit_user.php',
    'delete_user.php' => 'admin/delete_user.php',
    'user_list.php' => 'admin/user_list.php',
    'items.php' => 'items/items.php',
    'add_item.php' => 'items/add_item.php',
    'edit_item.php' => 'items/edit_item.php',
    'delete_item.php' => 'items/delete_item.php',
    'import_items.php' => 'items/import_items.php',
    'stocks.php' => 'stocks/stocks.php',
    'add_stock.php' => 'stocks/add_stock.php',
    'edit_stock.php' => 'stocks/edit_stock.php',
    'quotation.php' => 'quotation/quotation.php',
    'add_quotation.php' => 'quotation/add_quotation.php',
    'edit_quotation.php' => 'quotation/edit_quotation.php',
    'view_quotation.php' => 'quotation/view_quotation.php',
    'delete_quotation.php' => 'quotation/delete_quotation.php',
];

// Check if it's an old path that needs routing
if (isset($path_map[$file_path])) {
    $target = __DIR__ . '/' . $path_map[$file_path];
    if (file_exists($target)) {
        $_SERVER['SCRIPT_NAME'] = '/' . $path_map[$file_path];
        include $target;
        return true;
    }
}

// Check if path already matches new structure
$full_path = __DIR__ . '/' . $file_path;
if (file_exists($full_path) && is_file($full_path) && pathinfo($full_path, PATHINFO_EXTENSION) === 'php') {
    include $full_path;
    return true;
}

// Try to find in subdirectories (handle folder/file.php)
$parts = explode('/', $file_path);
if (count($parts) === 2) {
    $target = __DIR__ . '/' . $parts[0] . '/' . $parts[1];
    if (file_exists($target) && is_file($target)) {
        include $target;
        return true;
    }
}

// Default: show login page
if (file_exists(__DIR__ . '/auth/login.php')) {
    include __DIR__ . '/auth/login.php';
    return true;
}

// 404 - file not found
http_response_code(404);
echo "<h1>404 - File Not Found</h1>";
echo "<p>The requested file could not be found.</p>";
return true;

