<?php
// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'sales_procurement');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// Google Sign-In (OAuth 2.0) - set these as environment variables, never in code
// trim() guards against stray spaces/newlines pasted into the hosting dashboard
define('GOOGLE_CLIENT_ID', trim(getenv('GOOGLE_CLIENT_ID') ?: ''));
define('GOOGLE_CLIENT_SECRET', trim(getenv('GOOGLE_CLIENT_SECRET') ?: ''));
define('GOOGLE_REDIRECT_URI', trim(getenv('GOOGLE_REDIRECT_URI') ?: ''));

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

// Render terminates HTTPS at its proxy and forwards plain HTTP
function isHttpsRequest() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

// Logged-in users are signed out after this many seconds without activity
define('SESSION_IDLE_TIMEOUT', 8 * 60 * 60);

/**
 * Sessions are stored in the database instead of files: Render wipes the
 * container's files on every deploy and when the free instance sleeps,
 * which logged everyone out.
 */
class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    private $conn;

    public function open($path, $name): bool {
        $this->conn = getDBConnection();
        return true;
    }

    // Run a query; on first use create the sessions table (MySQL error 1146 = table missing)
    private function query($sql, $types, ...$params) {
        try {
            $stmt = $this->conn->prepare($sql);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1146) {
                throw $e;
            }
            $this->conn->query("CREATE TABLE IF NOT EXISTS app_sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                data MEDIUMBLOB NOT NULL,
                last_activity INT NOT NULL,
                KEY last_activity (last_activity)
            )");
            $stmt = $this->conn->prepare($sql);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt;
    }

    public function close(): bool {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
        return true;
    }

    public function read($id): string|false {
        $stmt = $this->query("SELECT data FROM app_sessions WHERE id = ? AND last_activity >= ?", "si", $id, time() - SESSION_IDLE_TIMEOUT);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool {
        // Don't store empty sessions (visitors who never logged in, bots)
        if ($data === '') {
            return $this->destroy($id);
        }
        $this->query("REPLACE INTO app_sessions (id, data, last_activity) VALUES (?, ?, ?)", "ssi", $id, $data, time())->close();
        return true;
    }

    public function destroy($id): bool {
        $this->query("DELETE FROM app_sessions WHERE id = ?", "s", $id)->close();
        return true;
    }

    public function gc($maxLifetime): int|false {
        $stmt = $this->query("DELETE FROM app_sessions WHERE last_activity < ?", "i", time() - $maxLifetime);
        $deleted = $stmt->affected_rows;
        $stmt->close();
        return $deleted;
    }

    // Used with strict mode: only accept session IDs this server issued (and not expired)
    public function validateId($id): bool {
        $stmt = $this->query("SELECT 1 FROM app_sessions WHERE id = ? AND last_activity >= ?", "si", $id, time() - SESSION_IDLE_TIMEOUT);
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    // Data unchanged: just keep the session alive
    public function updateTimestamp($id, $data): bool {
        $this->query("UPDATE app_sessions SET last_activity = ? WHERE id = ?", "is", time(), $id)->close();
        return true;
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_TIMEOUT);
    session_set_cookie_params([
        'lifetime' => 0,               // until the browser is closed
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,            // not readable by JavaScript
        'samesite' => 'Lax',           // not sent on form posts from other websites
    ]);
    session_set_save_handler(new DbSessionHandler(), true);
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

// Browser tab icon: the round JESS logo with a transparent background
// (favicon-32.png / apple-touch-icon.png in the project root)
function faviconTag() {
    return '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">' . "\n"
        . '    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">' . "\n";
}

// Google Sign-In is shown only when credentials are configured
function isGoogleLoginEnabled() {
    return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
}

// Callback URL registered in Google Cloud Console (must match exactly)
function getGoogleRedirectUri() {
    if (GOOGLE_REDIRECT_URI !== '') {
        return GOOGLE_REDIRECT_URI;
    }
    $scheme = isHttpsRequest() ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . '/auth/google_callback.php';
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

