<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN]);
$pageTitle = 'Manage Users';

$db = getDB();
$csrf = generateCSRF();

function validatePasswordStrength(string $pw): array {
    $errors = [];

    if (strlen($pw) < 8) {
        $errors[] = 'at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $pw)) {
        $errors[] = 'one uppercase letter (A-Z)';
    }
    if (!preg_match('/[a-z]/', $pw)) {
        $errors[] = 'one lowercase letter (a-z)';
    }
    if (!preg_match('/[0-9]/', $pw)) {
        $errors[] = 'one number (0-9)';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) {
        $errors[] = 'one special character (e.g. ! @ # $ % ^ &)';
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $f = [
            'firstName'  => trim($_POST['firstName'] ?? ''),
            'middleName' => trim($_POST['middleName'] ?? ''),
            'lastName'   => trim($_POST['lastName'] ?? ''),
            'extName'    => trim($_POST['extName'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
            'roleID'     => (int)($_POST['roleID'] ?? 0),
        ];

        $password = $_POST['password'] ?? '';
        $uid = $action === 'edit' ? (int)($_POST['userID'] ?? 0) : null;
        $namePattern = '/^[\p{L}\s.\'-]+$/u';

        $passwordProvided = $password !== '';
        $passwordCheckNeeded = ($action === 'add') || $passwordProvided;
        $passwordErrors = $passwordCheckNeeded ? validatePasswordStrength($password) : [];

        if (
            empty($f['firstName']) ||
            empty($f['lastName']) ||
            empty($f['email']) ||
            !$f['roleID']
        ) {
            $_SESSION['flash_error'] = 'Please fill in all required fields.';
        } elseif (!preg_match($namePattern, $f['firstName'])) {
            $_SESSION['flash_error'] = 'First name must contain letters only (no numbers or symbols).';
        } elseif (!preg_match($namePattern, $f['lastName'])) {
            $_SESSION['flash_error'] = 'Last name must contain letters only (no numbers or symbols).';
        } elseif ($f['middleName'] !== '' && !preg_match($namePattern, $f['middleName'])) {
            $_SESSION['flash_error'] = 'Middle name must contain letters only (no numbers or symbols).';
        } elseif ($f['extName'] !== '' && !preg_match($namePattern, $f['extName'])) {
            $_SESSION['flash_error'] = 'Extension must contain letters only (no numbers or symbols).';
        } elseif (!empty($passwordErrors)) {
            $_SESSION['flash_error'] =
                'Password does not meet requirements. Missing: ' . implode(', ', $passwordErrors) . '.';
        } else {

            // --------------------------------------------------------
            // Enforce a single Admin and a single Principal system-wide.
            // --------------------------------------------------------
            $roleLookupStmt = $db->prepare("SELECT roleName FROM roles WHERE roleID = ?");
            $roleLookupStmt->execute([$f['roleID']]);
            $roleLookupRow = $roleLookupStmt->fetch();
            $roleNameLower = $roleLookupRow ? strtolower(trim($roleLookupRow['roleName'])) : '';

            $isRestrictedRole = in_array($roleNameLower, ['admin', 'principal'], true);
            $duplicateRoleBlocked = false;

            if ($isRestrictedRole) {
                $dupSql = "SELECT userID FROM users WHERE roleID = ? AND dateDeleted IS NULL";
                $dupParams = [$f['roleID']];

                if ($action === 'edit') {
                    $dupSql .= " AND userID != ?";
                    $dupParams[] = $uid;
                }

                $dupStmt = $db->prepare($dupSql);
                $dupStmt->execute($dupParams);

                if ($dupStmt->fetch()) {
                    $duplicateRoleBlocked = true;
                    $_SESSION['flash_error'] =
                        ucfirst($roleNameLower) . ' role is already assigned to another user. ' .
                        'Only one ' . ucfirst($roleNameLower) . ' is allowed in the system.';
                }
            }

            if ($duplicateRoleBlocked) {
                header('Location: users.php');
                exit;
            }

            if ($action === 'add') {

                // Password strength was already validated above.
                $hashed = password_hash(
                    $password,
                    PASSWORD_BCRYPT,
                    ['cost' => 12]
                );

                $stmt = $db->prepare("
                    INSERT INTO users
                    (firstName, middleName, lastName, extName, email, password, roleID)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $f['firstName'],
                    $f['middleName'],
                    $f['lastName'],
                    $f['extName'],
                    $f['email'],
                    $hashed,
                    $f['roleID']
                ]);

                logActivity(
                    'Add User',
                    'Users',
                    'Added user: ' . $f['email']
                );

                $_SESSION['flash_success'] =
                    'User added successfully. Employee No. auto-generated.';

            } else {

                $stmt = $db->prepare("
                    UPDATE users
                    SET firstName = ?,
                        middleName = ?,
                        lastName = ?,
                        extName = ?,
                        email = ?,
                        roleID = ?
                    WHERE userID = ?
                ");

                $stmt->execute([
                    $f['firstName'],
                    $f['middleName'],
                    $f['lastName'],
                    $f['extName'],
                    $f['email'],
                    $f['roleID'],
                    $uid
                ]);

                if ($passwordProvided) {
                    // Password strength was already validated above.
                    $db->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE userID = ?
                    ")->execute([
                        password_hash(
                            $password,
                            PASSWORD_BCRYPT,
                            ['cost' => 12]
                        ),
                        $uid
                    ]);
                }

                logActivity(
                    'Edit User',
                    'Users',
                    'Edited user ID: ' . $uid
                );

                $_SESSION['flash_success'] = 'User updated successfully.';
            }
        }

    } elseif ($action === 'toggle') {

        $uid = (int)$_POST['userID'];

        $db->prepare("
            UPDATE users
            SET isActive = NOT isActive
            WHERE userID = ?
        ")->execute([$uid]);

        $_SESSION['flash_success'] = 'User status updated.';

    } elseif ($action === 'delete') {

        $uid = (int)$_POST['userID'];

        if ($uid === (int)$_SESSION['userID']) {

            $_SESSION['flash_error'] = 'You cannot delete your own account.';

        } else {

            $db->prepare("
                UPDATE users
                SET dateDeleted = NOW(),
                    isActive = 0
                WHERE userID = ?
            ")->execute([$uid]);

            logActivity(
                'Delete User',
                'Users',
                'Deleted user ID: ' . $uid
            );

            $_SESSION['flash_success'] = 'User removed successfully.';
        }
    }

    header('Location: users.php');
    exit;
}

$users = $db->query("
    SELECT u.*, r.roleName
    FROM users u
    JOIN roles r ON u.roleID = r.roleID
    WHERE u.dateDeleted IS NULL
    ORDER BY r.roleID, u.lastName
")->fetchAll();

$roles = $db->query("
    SELECT *
    FROM roles
    WHERE dateDeleted IS NULL
")->fetchAll();

$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['isActive']));
$inactiveUsers = $totalUsers - $activeUsers;
$totalRoles = count($roles);

// Track which user currently holds each restricted role (Admin / Principal)
// so the role dropdown can gray those options out for everyone else.
$roleAssignments = [];
foreach ($users as $u) {
    $rnLower = strtolower(trim($u['roleName']));
    if (in_array($rnLower, ['admin', 'principal'], true)) {
        $roleAssignments[$rnLower] = (int)$u['userID'];
    }
}

include '../includes/header.php';
?>

<style>
/* =========================================================
   USER MANAGEMENT PAGE
   ========================================================= */

.users-page {
    --um-primary: #2563eb;
    --um-primary-dark: #1d4ed8;
    --um-success: #16a34a;
    --um-danger: #dc2626;
    --um-warning: #d97706;
    --um-text: #1e293b;
    --um-muted: #64748b;
    --um-border: #e2e8f0;
    --um-bg: #f8fafc;
}

/* Header */
.page-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.page-heading-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.page-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    color: #fff;
    border-radius: 13px;
    font-size: 20px;
    box-shadow: 0 7px 18px rgba(37, 99, 235, .20);
}

.page-heading h4 {
    font-weight: 700;
    color: var(--um-text);
    margin: 0;
}

.page-subtitle {
    color: var(--um-muted);
    font-size: 13px;
    margin-top: 3px;
}

.breadcrumb {
    font-size: 13px;
}

/* Add button */
.btn-add-user {
    border: 0;
    padding: 11px 18px;
    font-weight: 600;
    border-radius: 10px;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
    box-shadow: 0 5px 14px rgba(37, 99, 235, .20);
    transition: .2s ease;
}

.btn-add-user:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(37, 99, 235, .28);
}

/* Summary cards */
.user-stat-card {
    background: #fff;
    border: 1px solid var(--um-border);
    border-radius: 14px;
    padding: 18px;
    height: 100%;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .035);
    transition: .2s ease;
}

.user-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(15, 23, 42, .07);
}

.stat-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-label {
    color: var(--um-muted);
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 4px;
}

.stat-value {
    color: var(--um-text);
    font-size: 25px;
    font-weight: 750;
    line-height: 1;
}

.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.stat-blue {
    background: #eff6ff;
    color: #2563eb;
}

.stat-green {
    background: #f0fdf4;
    color: #16a34a;
}

.stat-red {
    background: #fef2f2;
    color: #dc2626;
}

.stat-purple {
    background: #faf5ff;
    color: #9333ea;
}

/* Main table card */
.users-card {
    background: #fff;
    border: 1px solid var(--um-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .045);
}

.users-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--um-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.users-card-title {
    font-weight: 700;
    color: var(--um-text);
    margin: 0;
}

.users-card-subtitle {
    font-size: 12px;
    color: var(--um-muted);
    margin-top: 3px;
}

.user-search {
    position: relative;
    width: 280px;
}

.user-search i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.user-search input {
    padding-left: 36px;
    border-radius: 9px;
    border-color: var(--um-border);
    font-size: 13px;
}

/* Table */
.users-table {
    margin: 0;
}

.users-table thead th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    font-weight: 700;
    border-bottom: 1px solid var(--um-border);
    padding: 13px 16px;
    white-space: nowrap;
}

.users-table tbody td {
    padding: 14px 16px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 13px;
}

.users-table tbody tr {
    transition: background .15s ease;
}

.users-table tbody tr:hover {
    background: #f8fbff;
}

.users-table tbody tr:last-child td {
    border-bottom: 0;
}

/* Employee number */
.employee-number {
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 4px 7px;
    border-radius: 6px;
    font-size: 11px;
}

/* User identity */
.user-identity {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
}

.user-name {
    font-weight: 650;
    color: #1e293b;
}

.user-email {
    color: #64748b;
    font-size: 12px;
}

/* Badges */
.role-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 650;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.status-active {
    background: #f0fdf4;
    color: #15803d;
}

.status-active .status-dot {
    background: #22c55e;
}

.status-inactive {
    background: #fef2f2;
    color: #b91c1c;
}

.status-inactive .status-dot {
    background: #ef4444;
}

/* Action buttons */
.user-actions {
    display: flex;
    gap: 5px;
}

.user-action-btn {
    width: 31px;
    height: 31px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px !important;
    border-width: 1px;
    transition: .15s ease;
}

.user-action-btn:hover {
    transform: translateY(-1px);
}

.btn-edit {
    color: #2563eb;
    border-color: #bfdbfe;
    background: #eff6ff;
}

.btn-toggle {
    color: #d97706;
    border-color: #fde68a;
    background: #fffbeb;
}

.btn-delete-user {
    color: #dc2626;
    border-color: #fecaca;
    background: #fef2f2;
}

/* Modal */
.user-modal .modal-content {
    border: 0;
    border-radius: 17px;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(15, 23, 42, .20);
}

.user-modal .modal-header {
    padding: 20px 22px;
    border: 0;
    background: linear-gradient(135deg, #2563eb, #4f46e5);
}

.user-modal .modal-title {
    font-weight: 700;
}

.user-modal .modal-body {
    padding: 24px;
}

.user-modal .modal-footer {
    padding: 15px 22px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

.form-section-title {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 750;
    color: #64748b;
    margin: 4px 0 2px;
}

.form-label {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

/*
 * All text inputs and selects in the user form share identical
 * height, padding, border, and radius so the form reads as one
 * consistent grid regardless of column width.
 */
.user-modal .form-control,
.user-modal .form-select {
    width: 100%;
    height: 42px;
    min-height: 42px;
    padding: 8px 12px;
    border-radius: 9px;
    border: 1px solid #dbe2ea;
    font-size: 13px;
    line-height: 1.4;
    box-sizing: border-box;
}

.user-modal .form-control:focus,
.user-modal .form-select:focus {
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, .10);
}

.user-modal .form-control.is-invalid {
    border-color: var(--um-danger);
}

.user-modal select.form-select option:disabled {
    color: #b8c0cc;
    font-style: italic;
}

.password-wrapper {
    position: relative;
}

.password-wrapper .form-control {
    padding-right: 42px;
}

.password-toggle {
    position: absolute;
    right: 7px;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: #64748b;
    width: 32px;
    height: 32px;
}

.password-hint,
.field-hint {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 5px;
}

.field-hint.field-error {
    color: var(--um-danger);
}

/* Live password requirement checklist */
.password-requirements {
    list-style: none;
    margin: 8px 0 0;
    padding: 0;
    display: none;
}

.password-requirements.is-visible {
    display: block;
}

.password-requirements li {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #94a3b8;
    padding: 2px 0;
    transition: color .15s ease;
}

.password-requirements li i {
    font-size: 11px;
    color: #cbd5e1;
}

.password-requirements li.valid {
    color: #15803d;
}

.password-requirements li.valid i {
    color: #22c55e;
}

.password-requirements li.invalid {
    color: var(--um-danger);
}

.password-requirements li.invalid i {
    color: var(--um-danger);
}

/* Empty state */
.empty-users {
    text-align: center;
    padding: 55px 20px;
    color: #64748b;
}

.empty-users-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    border-radius: 50%;
    background: #eff6ff;
    color: #3b82f6;
    font-size: 23px;
}

.empty-users h6 {
    color: #334155;
    font-weight: 700;
}

/* Responsive */
@media (max-width: 768px) {
    .page-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .btn-add-user {
        width: 100%;
    }

    .user-search {
        width: 100%;
    }

    .users-card-header {
        align-items: stretch;
    }

    .users-table thead th:nth-child(3),
    .users-table tbody td:nth-child(3) {
        display: none;
    }
}

@media (max-width: 576px) {
    .page-heading-left {
        align-items: flex-start;
    }

    .page-icon {
        width: 42px;
        height: 42px;
    }

    .stat-value {
        font-size: 21px;
    }

    .users-table thead th,
    .users-table tbody td {
        padding: 11px 10px;
    }

    .user-email {
        display: none;
    }

    .user-modal .modal-body {
        padding: 18px;
    }
}
</style>

<div class="users-page">

    <!-- Page Header -->
    <div class="page-heading">
        <div class="page-heading-left">
            <div class="page-icon">
                <i class="fas fa-users"></i>
            </div>

            <div>
                <h4>User Management</h4>
                <div class="page-subtitle">
                    Manage employee accounts, roles, and access
                </div>

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 mt-1">
                        <li class="breadcrumb-item">
                            <a href="<?= SITE_URL ?>/dashboard.php">Home</a>
                        </li>
                        <li class="breadcrumb-item active">Users</li>
                    </ol>
                </nav>
            </div>
        </div>

        <button
            class="btn btn-primary btn-add-user"
            data-bs-toggle="modal"
            data-bs-target="#modalUser"
            id="btnAddUser"
        >
            <i class="fas fa-plus me-2"></i>
            Add User
        </button>
    </div>

    <!-- Statistics -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">
            <div class="user-stat-card">
                <div class="stat-content">
                    <div>
                        <div class="stat-label">Total Users</div>
                        <div class="stat-value"><?= $totalUsers ?></div>
                    </div>
                    <div class="stat-icon stat-blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="user-stat-card">
                <div class="stat-content">
                    <div>
                        <div class="stat-label">Active Users</div>
                        <div class="stat-value"><?= $activeUsers ?></div>
                    </div>
                    <div class="stat-icon stat-green">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="user-stat-card">
                <div class="stat-content">
                    <div>
                        <div class="stat-label">Inactive Users</div>
                        <div class="stat-value"><?= $inactiveUsers ?></div>
                    </div>
                    <div class="stat-icon stat-red">
                        <i class="fas fa-user-slash"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="user-stat-card">
                <div class="stat-content">
                    <div>
                        <div class="stat-label">Available Roles</div>
                        <div class="stat-value"><?= $totalRoles ?></div>
                    </div>
                    <div class="stat-icon stat-purple">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Users Card -->
    <div class="users-card">

        <div class="users-card-header">

            <div>
                <h6 class="users-card-title">
                    <i class="fas fa-address-book text-primary me-2"></i>
                    System Users
                </h6>
                <div class="users-card-subtitle">
                    <?= $totalUsers ?> account<?= $totalUsers == 1 ? '' : 's' ?> registered
                </div>
            </div>

            <div class="user-search">
                <i class="fas fa-search"></i>
                <input
                    type="search"
                    id="userSearch"
                    class="form-control"
                    placeholder="Search users..."
                    autocomplete="off"
                >
            </div>

        </div>

        <div class="table-responsive">
            <table class="table users-table mb-0" id="usersTable">

                <thead>
                    <tr>
                        <th>Employee No.</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($users)): ?>

                    <tr>
                        <td colspan="6">
                            <div class="empty-users">

                                <div class="empty-users-icon">
                                    <i class="fas fa-users"></i>
                                </div>

                                <h6>No users found</h6>

                                <p class="mb-3">
                                    There are currently no registered users.
                                </p>

                                <button
                                    class="btn btn-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalUser"
                                    id="btnEmptyAddUser"
                                >
                                    <i class="fas fa-plus me-1"></i>
                                    Add First User
                                </button>

                            </div>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($users as $u): ?>

                        <?php
                        $fullName =
                            $u['lastName'] . ', ' .
                            $u['firstName'] . ' ' .
                            ($u['middleName']
                                ? substr($u['middleName'], 0, 1) . '.'
                                : '') .
                            ' ' .
                            ($u['extName'] ?? '');

                        $initials =
                            strtoupper(
                                substr($u['firstName'], 0, 1) .
                                substr($u['lastName'], 0, 1)
                            );

                        $roleClass = match ((int)$u['roleID']) {
                            1 => 'bg-primary',
                            2 => 'bg-success',
                            3 => 'bg-warning text-dark',
                            4 => 'bg-info text-dark',
                            default => 'bg-secondary'
                        };
                        ?>

                        <tr class="user-row">

                            <td>
                                <code class="employee-number">
                                    <?= clean($u['employeeNo'] ?? '-') ?>
                                </code>
                            </td>

                            <td>
                                <div class="user-identity">

                                    <div class="user-avatar">
                                        <?= clean($initials) ?>
                                    </div>

                                    <div>
                                        <div class="user-name">
                                            <?= clean(trim($fullName)) ?>
                                        </div>

                                        <div class="user-email">
                                            <?= clean($u['email']) ?>
                                        </div>
                                    </div>

                                </div>
                            </td>

                            <td>
                                <span class="text-muted">
                                    <?= clean($u['email']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?= $roleClass ?> role-badge">
                                    <i class="fas fa-user-shield"></i>
                                    <?= clean($u['roleName']) ?>
                                </span>
                            </td>

                            <td>

                                <?php if ($u['isActive']): ?>

                                    <span class="status-badge status-active">
                                        <span class="status-dot"></span>
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-inactive">
                                        <span class="status-dot"></span>
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <div class="user-actions justify-content-end">

                                    <!-- Edit -->
                                    <button
                                        type="button"
                                        class="btn user-action-btn btn-edit btn-edit-user"
                                        title="Edit User"
                                        data-user='<?= htmlspecialchars(
                                            json_encode($u),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>'
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalUser"
                                    >
                                        <i class="fas fa-pen"></i>
                                    </button>

                                    <?php if ($u['userID'] != $_SESSION['userID']): ?>

                                        <!-- Toggle -->
                                        <form method="POST" class="d-inline">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= $csrf ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle"
                                            >

                                            <input
                                                type="hidden"
                                                name="userID"
                                                value="<?= $u['userID'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn user-action-btn btn-toggle"
                                                title="<?= $u['isActive'] ? 'Deactivate User' : 'Activate User' ?>"
                                            >
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        </form>

                                        <!-- Delete -->
                                        <form method="POST" class="d-inline delete-user-form">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= $csrf ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="userID"
                                                value="<?= $u['userID'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn user-action-btn btn-delete-user"
                                                title="Remove User"
                                            >
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>
        </div>

    </div>

</div>


<!-- =========================================================
     ADD / EDIT USER MODAL
========================================================= -->

<div
    class="modal fade user-modal"
    id="modalUser"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <form method="POST" id="userForm" novalidate>

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= $csrf ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="add"
                    id="uFormAction"
                >

                <input
                    type="hidden"
                    name="userID"
                    id="uEditID"
                >

                <!-- Modal Header -->
                <div class="modal-header text-white">

                    <div>
                        <h5 class="modal-title mb-1">
                            <i class="fas fa-user-plus me-2"></i>
                            <span id="uModalTitle">Add User</span>
                        </h5>

                        <small class="opacity-75">
                            Create and manage system access
                        </small>
                    </div>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>

                </div>

                <!-- Modal Body -->
                <div class="modal-body">

                    <div class="form-section-title">
                        Personal Information
                    </div>

                    <!--
                        Names use a uniform two-column (6/6) grid throughout
                        so every text box lines up with the same width,
                        height, and spacing.
                    -->
                    <div class="row g-3 mt-1">

                        <div class="col-md-6">
                            <label class="form-label">
                                First Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="firstName"
                                id="u_fn"
                                class="form-control"
                                placeholder="Enter first name"
                                pattern="[A-Za-zÀ-ÖØ-öø-ÿ\s.'\-]+"
                                title="Letters only (no numbers or symbols)"
                                autocomplete="given-name"
                                required
                            >
                            <div class="field-hint" data-hint-for="u_fn">
                                Type your name.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Last Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="lastName"
                                id="u_ln"
                                class="form-control"
                                placeholder="Enter last name"
                                pattern="[A-Za-zÀ-ÖØ-öø-ÿ\s.'\-]+"
                                title="Letters only (no numbers or symbols)"
                                autocomplete="family-name"
                                required
                            >
                            <div class="field-hint" data-hint-for="u_ln">
                                Type your last name.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Middle Name
                            </label>

                            <input
                                type="text"
                                name="middleName"
                                id="u_mn"
                                class="form-control"
                                placeholder="Enter middle name"
                                pattern="[A-Za-zÀ-ÖØ-öø-ÿ\s.'\-]+"
                                title="Letters only (no numbers or symbols)"
                                autocomplete="additional-name"
                            >
                            <div class="field-hint" data-hint-for="u_mn">
                                Type your middle name. Optional.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                Extension
                            </label>

                            <input
                                type="text"
                                name="extName"
                                id="u_ext"
                                class="form-control"
                                placeholder="Jr., Sr., III"
                                pattern="[A-Za-zÀ-ÖØ-öø-ÿ\s.'\-]+"
                                title="Letters only (no numbers or symbols)"
                            >
                            <div class="field-hint" data-hint-for="u_ext">
                                Type you extension name (e.g. Jr., Sr., III).
                            </div>
                        </div>

                    </div>

                    <div class="form-section-title mt-4">
                        Account Information
                    </div>

                    <div class="row g-3 mt-1">

                        <div class="col-md-6">

                            <label class="form-label">
                                Email Address
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="u_email"
                                class="form-control"
                                placeholder="name@example.com"
                                autocomplete="email"
                                required
                            >

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Role
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="roleID"
                                id="u_role"
                                class="form-select"
                                required
                            >
                                <option value="">Select user role</option>

                                <?php foreach ($roles as $r): ?>

                                    <?php
                                    $rNameLower = strtolower(trim($r['roleName']));
                                    $isRestrictedRole = in_array($rNameLower, ['admin', 'principal'], true);
                                    $assignedTo = $roleAssignments[$rNameLower] ?? '';
                                    ?>

                                    <option
                                        value="<?= $r['roleID'] ?>"
                                        <?php if ($isRestrictedRole): ?>
                                        data-restricted="1"
                                        data-assigned-to="<?= $assignedTo ?>"
                                        <?php endif; ?>
                                    >
                                        <?= clean($r['roleName']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="field-hint" id="roleHint">
                                
                            </div>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Password
                                <span
                                    id="pwRequired"
                                    class="text-danger"
                                >*</span>
                            </label>

                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    name="password"
                                    id="u_pw"
                                    class="form-control"
                                    minlength="8"
                                    placeholder="Minimum 8 characters"
                                    autocomplete="new-password"
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="togglePassword"
                                    title="Show password"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>

                            </div>

                            <div
                                class="password-hint"
                                id="pwHint"
                            >
                                Leave blank to keep the existing password
                                when editing.
                            </div>

                           

                        </div>

                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary px-4"
                    >
                        <i class="fas fa-save me-2"></i>
                        <span id="saveButtonText">Create User</span>
                    </button>

                </div>

            </form>

        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('userForm');
    const addButton = document.getElementById('btnAddUser');
    const emptyAddButton = document.getElementById('btnEmptyAddUser');

    const actionInput = document.getElementById('uFormAction');
    const editIdInput = document.getElementById('uEditID');

    const modalTitle = document.getElementById('uModalTitle');
    const saveButtonText = document.getElementById('saveButtonText');

    const passwordInput = document.getElementById('u_pw');
    const passwordRequired = document.getElementById('pwRequired');
    const passwordHint = document.getElementById('pwHint');
    const passwordRequirements = document.getElementById('pwRequirements');

    const searchInput = document.getElementById('userSearch');

    const roleSelect = document.getElementById('u_role');
    const NAME_FIELD_IDS = ['u_fn', 'u_mn', 'u_ln', 'u_ext'];
    const NAME_ALLOWED_PATTERN = /[^\p{L}\s.'-]/gu;

    function sanitizeNameField(input) {
        const original = input.value;
        const cleaned = original.replace(NAME_ALLOWED_PATTERN, '');

        if (cleaned !== original) {
            input.value = cleaned;
            showNameFieldError(input, true);
        } else {
            showNameFieldError(input, false);
        }
    }

    function showNameFieldError(input, hasError) {
        const hint = document.querySelector('[data-hint-for="' + input.id + '"]');
        input.classList.toggle('is-invalid', hasError);

        if (hint) {
            if (hasError) {
                hint.textContent = 'Numbers and symbols are not allowed.';
                hint.classList.add('field-error');
            } else {
                hint.textContent = hint.dataset.defaultText || hint.textContent;
                hint.classList.remove('field-error');
            }
        }
    }

    NAME_FIELD_IDS.forEach(function (id) {
        const input = document.getElementById(id);
        if (!input) return;

        // Remember each hint's default helper text so it can be restored
        // after an error is cleared.
        const hint = document.querySelector('[data-hint-for="' + id + '"]');
        if (hint) {
            hint.dataset.defaultText = hint.textContent;
        }

        input.addEventListener('input', function () {
            sanitizeNameField(input);
        });

        input.addEventListener('keypress', function (e) {
            // Block digits outright as they're typed.
            if (/[0-9]/.test(e.key)) {
                e.preventDefault();
                showNameFieldError(input, true);
            }
        });

        input.addEventListener('paste', function () {
            // Let the paste land, then sanitize on the next tick.
            setTimeout(function () {
                sanitizeNameField(input);
            }, 0);
        });
    });

    /*
     * ---------------------------------------------------------
     * Admin / Principal role restriction
     * ---------------------------------------------------------
     * Only one user in the system may hold the Admin role, and only
     * one may hold the Principal role. Any option already assigned
     * to a different user is disabled here in the UI; the server
     * enforces the same rule on save.
     */
    function updateRoleOptions(currentUserId) {
        if (!roleSelect) return;

        roleSelect.querySelectorAll('option[data-restricted="1"]').forEach(function (opt) {
            const assignedToRaw = opt.getAttribute('data-assigned-to');
            const assignedTo = assignedToRaw ? parseInt(assignedToRaw, 10) : null;

            const takenByOther =
                assignedTo !== null &&
                !Number.isNaN(assignedTo) &&
                assignedTo !== currentUserId;

            opt.disabled = takenByOther;

            const baseLabel = (opt.dataset.baseLabel || opt.textContent).trim();
            opt.dataset.baseLabel = baseLabel;
            opt.textContent = takenByOther
                ? baseLabel + ' (already assigned)'
                : baseLabel;
        });

        // If the currently selected option just became disabled,
        // clear the selection so an invalid role can't be submitted.
        const selected = roleSelect.selectedOptions[0];
        if (selected && selected.disabled) {
            roleSelect.value = '';
        }
    }

    /*
     * ---------------------------------------------------------
     * Password strength validation
     * ---------------------------------------------------------
     * Requires: 8+ characters, one uppercase, one lowercase, one
     * number, and one special character. Blank is only allowed
     * while editing (keeps the current password).
     */
    function evaluatePassword(pw) {
        return {
            length: pw.length >= 8,
            upper: /[A-Z]/.test(pw),
            lower: /[a-z]/.test(pw),
            number: /[0-9]/.test(pw),
            special: /[^A-Za-z0-9]/.test(pw)
        };
    }

    function isPasswordSatisfied(results) {
        return results.length && results.upper && results.lower &&
            results.number && results.special;
    }

    function renderPasswordChecklist(results, isActive) {
        if (!passwordRequirements) return;

        Object.keys(results).forEach(function (rule) {
            const li = passwordRequirements.querySelector('[data-rule="' + rule + '"]');
            if (!li) return;

            if (!isActive) {
                li.classList.remove('valid', 'invalid');
                return;
            }

            li.classList.toggle('valid', results[rule]);
            li.classList.toggle('invalid', !results[rule]);
        });
    }

    // Validates the password field, updates the visible checklist,
    // and reports the result through setCustomValidity so the
    // browser's native form validation (and our submit guard) block
    // submission until every requirement is met.
    function validatePasswordConstraint() {
        const pw = passwordInput.value;
        const isRequired = passwordInput.hasAttribute('required');
        const results = evaluatePassword(pw);

        // Editing + left blank = keep the existing password, no
        // strength check needed and the checklist stays hidden.
        if (pw === '' && !isRequired) {
            passwordInput.setCustomValidity('');
            passwordInput.classList.remove('is-invalid');
            if (passwordRequirements) {
                passwordRequirements.classList.remove('is-visible');
            }
            renderPasswordChecklist(results, false);
            return true;
        }

        if (passwordRequirements) {
            passwordRequirements.classList.add('is-visible');
        }
        renderPasswordChecklist(results, true);

        const satisfied = isPasswordSatisfied(results);

        passwordInput.classList.toggle('is-invalid', !satisfied);
        passwordInput.setCustomValidity(
            satisfied ? '' : 'Password does not meet all requirements.'
        );

        return satisfied;
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', validatePasswordConstraint);
        passwordInput.addEventListener('focus', function () {
            if (passwordRequirements) {
                passwordRequirements.classList.add('is-visible');
            }
        });
    }

    /*
     * ---------------------------------------------------------
     * Reset modal for ADD
     * ---------------------------------------------------------
     */
    function setupAddUser() {

        form.reset();

        actionInput.value = 'add';
        editIdInput.value = '';

        modalTitle.textContent = 'Add User';
        saveButtonText.textContent = 'Create User';

        passwordInput.value = '';
        passwordInput.setAttribute('required', 'required');
        passwordInput.classList.remove('is-invalid');
        passwordInput.setCustomValidity('');

        passwordRequired.style.display = 'inline';
        passwordHint.textContent = 'Password is required for new users.';

        if (passwordRequirements) {
            passwordRequirements.classList.add('is-visible');
        }
        renderPasswordChecklist(evaluatePassword(''), true);

        NAME_FIELD_IDS.forEach(function (id) {
            const input = document.getElementById(id);
            if (input) showNameFieldError(input, false);
        });

        // No user is currently being edited, so any Admin/Principal
        // role already assigned to someone must be disabled.
        updateRoleOptions(null);
    }

    /*
     * ---------------------------------------------------------
     * Setup modal for EDIT
     * ---------------------------------------------------------
     */
    document.querySelectorAll('.btn-edit-user').forEach(function (button) {

        button.addEventListener('click', function () {

            const user = JSON.parse(
                this.getAttribute('data-user')
            );

            actionInput.value = 'edit';
            editIdInput.value = user.userID;

            modalTitle.textContent = 'Edit User';
            saveButtonText.textContent = 'Save Changes';

            document.getElementById('u_fn').value =
                user.firstName || '';

            document.getElementById('u_mn').value =
                user.middleName || '';

            document.getElementById('u_ln').value =
                user.lastName || '';

            document.getElementById('u_ext').value =
                user.extName || '';

            document.getElementById('u_email').value =
                user.email || '';

            // Allow this user's own current role to stay selectable
            // even if it's Admin/Principal; disable it for anyone else.
            updateRoleOptions(parseInt(user.userID, 10));

            document.getElementById('u_role').value =
                user.roleID || '';

            passwordInput.value = '';
            passwordInput.removeAttribute('required');
            passwordInput.classList.remove('is-invalid');
            passwordInput.setCustomValidity('');

            passwordRequired.style.display = 'none';

            passwordHint.textContent =
                'Leave blank to keep the current password.';

            if (passwordRequirements) {
                passwordRequirements.classList.remove('is-visible');
            }
            renderPasswordChecklist(evaluatePassword(''), false);

            NAME_FIELD_IDS.forEach(function (id) {
                const input = document.getElementById(id);
                if (input) showNameFieldError(input, false);
            });
        });

    });

    /*
     * ---------------------------------------------------------
     * Add button
     * ---------------------------------------------------------
     */
    if (addButton) {
        addButton.addEventListener('click', setupAddUser);
    }

    if (emptyAddButton) {
        emptyAddButton.addEventListener('click', setupAddUser);
    }

    /*
     * ---------------------------------------------------------
     * Password visibility
     * ---------------------------------------------------------
     */
    const togglePassword =
        document.getElementById('togglePassword');

    if (togglePassword) {

        togglePassword.addEventListener('click', function () {

            const icon = this.querySelector('i');

            if (passwordInput.type === 'password') {

                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');

            } else {

                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');

            }

        });

    }

    /*
     * ---------------------------------------------------------
     * User search
     * ---------------------------------------------------------
     */
    if (searchInput) {

        searchInput.addEventListener('input', function () {

            const query =
                this.value.toLowerCase().trim();

            document.querySelectorAll('.user-row').forEach(function (row) {

                const text =
                    row.textContent.toLowerCase();

                row.style.display =
                    text.includes(query) ? '' : 'none';

            });

        });

    }

    /*
     * ---------------------------------------------------------
     * Delete confirmation
     * ---------------------------------------------------------
     */
    document.querySelectorAll('.delete-user-form').forEach(function (form) {

        form.addEventListener('submit', function (event) {

            const confirmed = confirm(
                'Are you sure you want to remove this user? ' +
                'This action will deactivate the account.'
            );

            if (!confirmed) {
                event.preventDefault();
            }

        });

    });

    /*
     * ---------------------------------------------------------
     * Final submit guard: re-run name sanitization and block
     * submission if the browser's own validation (pattern,
     * required, minlength) reports any field as invalid.
     * ---------------------------------------------------------
     */
    form.addEventListener('submit', function (event) {

        NAME_FIELD_IDS.forEach(function (id) {
            const input = document.getElementById(id);
            if (input) sanitizeNameField(input);
        });

        validatePasswordConstraint();

        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }

    });

    /*
     * ---------------------------------------------------------
     * Bootstrap modal cleanup
     * ---------------------------------------------------------
     */
    const modal =
        document.getElementById('modalUser');

    if (modal) {

        modal.addEventListener('hidden.bs.modal', function () {

            setupAddUser();

        });

    }

});
</script>

<?php include '../includes/footer.php'; ?>