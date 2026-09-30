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

// =====================================================
// Teacher-to-student ownership / access control helpers
// -----------------------------------------------------
// These centralize the "which students/sections may this
// teacher touch" logic so every page (students, grades,
// ranking, enrollment, student-view, reports) enforces the
// SAME rule in the backend, not just by hiding UI elements.
// Admin and Principal are always treated as fully authorized.
// =====================================================

// All sectionIDs this teacher is actually scheduled to teach in, for the
// given academic year (based on the `schedule` table, which is the only
// place a teacher<->section relationship is recorded).
function teacherSectionIDs(PDO $db, int $teacherID, int $ayID): array {
    $stmt = $db->prepare("SELECT DISTINCT sectionID FROM schedule WHERE userID=? AND ayID=? AND dateDeleted IS NULL");
    $stmt->execute([$teacherID, $ayID]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// True if $roleID is allowed to see everything (no ownership filtering needed).
function isUnrestrictedRole(int $roleID): bool {
    return in_array($roleID, [ROLE_ADMIN, ROLE_PRINCIPAL], true);
}

// True if this section is one the teacher actually teaches in, for this AY.
// Admin/Principal always pass. Any other role (e.g. a student poking at a
// teacher-only endpoint) always fails.
function teacherOwnsSection(PDO $db, int $sectionID, int $ayID, int $roleID, int $teacherID): bool {
    if (isUnrestrictedRole($roleID)) return true;
    if ($roleID !== ROLE_TEACHER) return false;
    if (!$sectionID) return false;
    $stmt = $db->prepare("SELECT COUNT(*) FROM schedule WHERE sectionID=? AND ayID=? AND userID=? AND dateDeleted IS NULL");
    $stmt->execute([$sectionID, $ayID, $teacherID]);
    return (bool)$stmt->fetchColumn();
}

// True if this exact section+subject combination is on the teacher's own
// schedule (used before letting a teacher view/save grades for a class).
function teacherOwnsSchedule(PDO $db, int $sectionID, int $subjectID, int $ayID, int $roleID, int $teacherID): bool {
    if (isUnrestrictedRole($roleID)) return true;
    if ($roleID !== ROLE_TEACHER) return false;
    if (!$sectionID || !$subjectID) return false;
    $stmt = $db->prepare("SELECT COUNT(*) FROM schedule WHERE sectionID=? AND subjectID=? AND ayID=? AND userID=? AND dateDeleted IS NULL");
    $stmt->execute([$sectionID, $subjectID, $ayID, $teacherID]);
    return (bool)$stmt->fetchColumn();
}

// True if the given student currently has an enrolled registration in a
// section that this teacher teaches, for this AY. This is the master check
// used by students.php / student-view.php to decide whether a teacher may
// view, edit, or delete a particular student record.
function teacherOwnsStudent(PDO $db, int $studentID, int $ayID, int $roleID, int $teacherID): bool {
    if (isUnrestrictedRole($roleID)) return true;
    if ($roleID !== ROLE_TEACHER) return false;
    if (!$studentID) return false;
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM registration r
        JOIN schedule sch ON sch.sectionID = r.sectionID AND sch.ayID = r.ayID
        WHERE r.studentID = ? AND r.ayID = ? AND r.status = 'enrolled' AND r.dateDeleted IS NULL
          AND sch.userID = ? AND sch.dateDeleted IS NULL
    ");
    $stmt->execute([$studentID, $ayID, $teacherID]);
    return (bool)$stmt->fetchColumn();
}

// Builds a safe "column IN (?,?,...)" fragment + matching bind params for a
// list of integer IDs. Returns ['1=0', []] (always false) for an empty list
// so "teacher has zero sections" correctly yields zero rows instead of
// accidentally matching everything.
function inClause(string $column, array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (empty($ids)) return ["1=0", []];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return ["$column IN ($placeholders)", $ids];
}

// Denies access with a flash message, logs the attempt, and redirects.
// Used whenever an ownership check above fails on a direct/tampered request.
function denyAccess(string $redirectTo, string $message = 'You are not authorized to access that record.'): void {
    logActivity('Unauthorized Access Attempt', 'Security', $message . ' (' . ($_SERVER['REQUEST_URI'] ?? '') . ')');
    $_SESSION['flash_error'] = $message;
    header('Location: ' . $redirectTo);
    exit;
}

// Configured grade min/max (defaults preserve the system's existing 60-100
// DepEd grading scale unless an admin changes it in Settings).
function gradeRange(): array {
    $min = getSetting('grade_min');
    $max = getSetting('grade_max');
    $min = ($min !== '' && is_numeric($min)) ? (float)$min : 60.0;
    $max = ($max !== '' && is_numeric($max)) ? (float)$max : 100.0;
    return [$min, $max];
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
