<?php
// =====================================================
// SDNTS - Configuration & Database Connection
// =====================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sdnts_db');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'SDNTS Academic System');
define('SITE_URL', 'http://localhost/sdnts');
define('UPLOAD_PATH', __DIR__ . '/../assets/img/uploads/');
define('BACKUP_PATH', __DIR__ . '/../backup/');
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');

// Establish PDO connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="background:#fee;padding:20px;font-family:monospace;">
                <strong>Database Connection Error</strong><br>
                Please ensure XAMPP MySQL is running and database <strong>sdnts_db</strong> exists.<br>
                Run <code>database.sql</code> first.<br>
                Error: ' . htmlspecialchars($e->getMessage()) . '
            </div>');
        }
    }
    return $pdo;
}

// Session management
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Check session timeout
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . SITE_URL . '/index.php?timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();

// CSRF Token
function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Activity Logger
function logActivity(string $action, string $module = '', string $details = '', int $studentID = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_log (userID, studentID, action, module, details, ipAddress, userAgent) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            $_SESSION['userID'] ?? null,
            $studentID,
            $action,
            $module,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Silent fail for logging
    }
}

// Get active academic year
function getActiveAY(): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM academicYear WHERE status='active' LIMIT 1");
    $stmt->execute();
    return $stmt->fetch() ?: ['ayID' => 1, 'academicYear' => '2024-2025'];
}

// Get system setting
function getSetting(string $key): string {
    $db = getDB();
    $stmt = $db->prepare("SELECT settingValue FROM system_settings WHERE settingKey=?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['settingValue'] : '';
}

// Sanitize input
function clean(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Auth check
function requireLogin(): void {
    if (!isset($_SESSION['userID'])) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['roleID'], $roles)) {
        header('Location: ' . SITE_URL . '/dashboard.php?error=access');
        exit;
    }
}

// Role IDs
const ROLE_ADMIN     = 1;
const ROLE_PRINCIPAL = 2;
const ROLE_TEACHER   = 3;
const ROLE_STUDENT   = 4;
