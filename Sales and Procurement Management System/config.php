<?php
// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'sales_procurement');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// Create database connection
function getDBConnection() {
    try {
        $conn = mysqli_init();
        
        // For cloud databases (like TiDB, Aiven) that require SSL
        $flags = (DB_HOST !== 'localhost') ? MYSQLI_CLIENT_SSL : 0;
        
        @mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, NULL, $flags);
        
        // Fallback to legacy database if it doesn't exist yet
        if ($conn->connect_error) {
            $conn = mysqli_init();
            @mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, 'sales_procurement', DB_PORT, NULL, $flags);
            
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
        }
        
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (any type)
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_username']);
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}


// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit();
    }
}

// Redirect admin to login if not authenticated
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: /auth/login.php');
        exit();
    }
}

// CSRF Protection Functions
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfToken() {
    return generateCsrfToken();
}

function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrfToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requirePostWithCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die("Method Not Allowed: This action requires a POST request.");
    }
    if (!validateCsrfToken()) {
        http_response_code(403);
        die("Invalid CSRF token. Request verification failed.");
    }
}


// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, username, email, full_name, phone, user_type, status, avatar FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $user;
}

// Get current admin info (for backward compatibility)
function getCurrentAdmin() {
    $user = getCurrentUser();
    return ($user && $user['user_type'] === 'admin') ? $user : null;
}

// Get system info settings
function getSystemInfo() {
    static $sysInfo = null;
    if ($sysInfo === null) {
        $conn = getDBConnection();
        $res = $conn->query("SELECT meta_field, meta_value FROM system_info");
        $sysInfo = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $sysInfo[$row['meta_field']] = $row['meta_value'];
            }
        }
        $conn->close();
    }
    return $sysInfo;
}

