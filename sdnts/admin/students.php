<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_TEACHER]);

$pageTitle = 'Manage Students';

$db = getDB();
$ay = getActiveAY();
$csrf = generateCSRF();

/*
|--------------------------------------------------------------------------
| Handle POST Actions
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {

        $fields = [
            'firstName'       => trim($_POST['firstName'] ?? ''),
            'middleName'      => trim($_POST['middleName'] ?? ''),
            'lastName'        => trim($_POST['lastName'] ?? ''),
            'extName'         => trim($_POST['extName'] ?? ''),
            'lrn'             => trim($_POST['lrn'] ?? '') ?: null,
            'dateofBirth'     => $_POST['dateofBirth'] ?? '',
            'isMale'          => (int)($_POST['isMale'] ?? 1),
            'address'         => trim($_POST['address'] ?? ''),
            'contactNo'       => trim($_POST['contactNo'] ?? ''),
            'guardianName'    => trim($_POST['guardianName'] ?? ''),
            'guardianContact' => trim($_POST['guardianContact'] ?? ''),
            'email'           => trim($_POST['email'] ?? '') ?: null,
            'progID'          => (int)($_POST['progID'] ?? 0) ?: null,
            'strandID'        => (int)($_POST['strandID'] ?? 0) ?: null,
        ];

        if (
            empty($fields['firstName']) ||
            empty($fields['lastName']) ||
            empty($fields['dateofBirth'])
        ) {

            $_SESSION['flash_error'] = 'Please fill all required fields.';

        } else {

            if ($action === 'add') {

                /*
                 * Default password:
                 * lastname + birth year
                 *
                 * Example:
                 * Dela Cruz + 2008 = delacruz2008
                 */
                $cleanLastName = strtolower(
                    preg_replace('/\s+/', '', $fields['lastName'])
                );

                $birthYear = date(
                    'Y',
                    strtotime($fields['dateofBirth'])
                );

                $defaultPw = $cleanLastName . $birthYear;

                $hashedPw = password_hash(
                    $defaultPw,
                    PASSWORD_BCRYPT,
                    ['cost' => 12]
                );

                $stmt = $db->prepare("
                    INSERT INTO students (
                        firstName,
                        middleName,
                        lastName,
                        extName,
                        lrn,
                        dateofBirth,
                        isMale,
                        address,
                        contactNo,
                        guardianName,
                        guardianContact,
                        email,
                        password,
                        progID,
                        strandID
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->execute([
                    $fields['firstName'],
                    $fields['middleName'],
                    $fields['lastName'],
                    $fields['extName'],
                    $fields['lrn'],
                    $fields['dateofBirth'],
                    $fields['isMale'],
                    $fields['address'],
                    $fields['contactNo'],
                    $fields['guardianName'],
                    $fields['guardianContact'],
                    $fields['email'],
                    $hashedPw,
                    $fields['progID'],
                    $fields['strandID']
                ]);

                $newID = $db->lastInsertId();

                logActivity(
                    'Add Student',
                    'Students',
                    'Added student ID: ' . $newID
                );

                $_SESSION['flash_success'] =
                    'Student added successfully. Default password: ' . $defaultPw;

            } else {

                $sid = (int)($_POST['studentID'] ?? 0);

                if (!$sid) {

                    $_SESSION['flash_error'] =
                        'Invalid student record.';

                } else {

                    $stmt = $db->prepare("
                        UPDATE students SET
                            firstName=?,
                            middleName=?,
                            lastName=?,
                            extName=?,
                            lrn=?,
                            dateofBirth=?,
                            isMale=?,
                            address=?,
                            contactNo=?,
                            guardianName=?,
                            guardianContact=?,
                            email=?,
                            progID=?,
                            strandID=?
                        WHERE studentID=?
                    ");

                    $stmt->execute([
                        $fields['firstName'],
                        $fields['middleName'],
                        $fields['lastName'],
                        $fields['extName'],
                        $fields['lrn'],
                        $fields['dateofBirth'],
                        $fields['isMale'],
                        $fields['address'],
                        $fields['contactNo'],
                        $fields['guardianName'],
                        $fields['guardianContact'],
                        $fields['email'],
                        $fields['progID'],
                        $fields['strandID'],
                        $sid
                    ]);

                    logActivity(
                        'Edit Student',
                        'Students',
                        'Edited student ID: ' . $sid
                    );

                    $_SESSION['flash_success'] =
                        'Student information updated successfully.';
                }
            }
        }

    } elseif ($action === 'delete') {

        $sid = (int)($_POST['studentID'] ?? 0);

        if ($sid) {

            $db->prepare("
                UPDATE students
                SET dateDeleted = NOW()
                WHERE studentID = ?
            ")->execute([$sid]);

            logActivity(
                'Delete Student',
                'Students',
                'Soft deleted student ID: ' . $sid
            );

            $_SESSION['flash_success'] =
                'Student removed successfully.';
        }
    }

    header('Location: students.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        s.*,
        p.progName,
        st.strandName
    FROM students s

    LEFT JOIN programs p
        ON s.progID = p.progID

    LEFT JOIN strands st
        ON s.strandID = st.strandID

    WHERE s.dateDeleted IS NULL
";

if ($search !== '') {

    $sql .= "
        AND (
            s.firstName LIKE ?
            OR s.middleName LIKE ?
            OR s.lastName LIKE ?
            OR s.studentNo LIKE ?
            OR s.lrn LIKE ?
            OR s.email LIKE ?
        )
    ";
}

$sql .= "
    ORDER BY
        s.lastName ASC,
        s.firstName ASC
";

$stmt = $db->prepare($sql);

if ($search !== '') {

    $searchParam = '%' . $search . '%';

    $stmt->execute([
        $searchParam,
        $searchParam,
        $searchParam,
        $searchParam,
        $searchParam,
        $searchParam
    ]);

} else {

    $stmt->execute();
}

$students = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Programs and Strands
|--------------------------------------------------------------------------
*/
$programs = $db->query("
    SELECT *
    FROM programs
    WHERE dateDeleted IS NULL
    ORDER BY progName
")->fetchAll();

$strands = $db->query("
    SELECT *
    FROM strands
    WHERE dateDeleted IS NULL
    ORDER BY strandName
")->fetchAll();


/*
|--------------------------------------------------------------------------
| Student Statistics
|--------------------------------------------------------------------------
*/
$totalStudents = count($students);

$enrolledCount = 0;
$notEnrolledCount = 0;
$maleCount = 0;
$femaleCount = 0;

foreach ($students as $student) {

    if ((int)$student['isEnroll'] === 1) {
        $enrolledCount++;
    } else {
        $notEnrolledCount++;
    }

    if ((int)$student['isMale'] === 1) {
        $maleCount++;
    } else {
        $femaleCount++;
    }
}

include '../includes/header.php';
?>

<style>

/* =========================================================
   STUDENT MANAGEMENT PAGE
========================================================= */

.student-page .page-title {
    font-weight: 700;
    color: #1f2937;
}

.student-page .page-subtitle {
    color: #6b7280;
    font-size: .9rem;
}

.student-page .card {
    border-radius: 12px;
}

.student-page .stat-card {
    border: 0;
    transition: transform .2s ease, box-shadow .2s ease;
}

.student-page .stat-card:hover {
    transform: translateY(-2px);
}

.student-page .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.student-page .stat-number {
    font-size: 1.45rem;
    font-weight: 700;
    line-height: 1;
}

.student-page .stat-label {
    font-size: .78rem;
    color: #6b7280;
}

/* Search */

.student-page .search-card {
    background: linear-gradient(
        135deg,
        #ffffff 0%,
        #f8fafc 100%
    );
}

.student-page .search-input {
    border-radius: 8px 0 0 8px;
}

.student-page .search-button {
    border-radius: 0 8px 8px 0;
}

/* Table */

.student-page .table {
    margin-bottom: 0;
}

.student-page .table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    font-weight: 700;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
    padding: 13px 12px;
}

.student-page .table tbody td {
    padding: 12px;
    vertical-align: middle;
}

.student-page .table tbody tr {
    transition: background-color .15s ease;
}

.student-page .table tbody tr:hover {
    background: #f8fbff;
}

.student-number {
    font-family: " Roboto",  Roboto;
    font-size: .82rem;
    background: #f1f5f9;
    color: #334155;
    padding: 4px 7px;
    border-radius: 5px;
}

.student-name {
    font-weight: 600;
    color: #1f2937;
}

.student-email {
    font-size: .75rem;
    color: #94a3b8;
}

.program-name {
    font-size: .82rem;
    font-weight: 600;
}

.strand-name {
    font-size: .73rem;
    color: #64748b;
}

/* Badges */

.gender-badge {
    min-width: 58px;
    display: inline-block;
}

.bg-pink {
    background-color: #e83e8c !important;
}

/* Actions */

.student-actions .btn {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.student-actions .dropdown-menu {
    min-width: 160px;
}

/* Modal */

#modalStudent .modal-content {
    border: 0;
    border-radius: 14px;
    overflow: hidden;
}

#modalStudent .modal-header {
    padding: 18px 22px;
}

#modalStudent .modal-body {
    padding: 22px;
}

#modalStudent .form-label {
    font-size: .82rem;
    font-weight: 600;
    color: #374151;
}

#modalStudent .form-control,
#modalStudent .form-select {
    border-radius: 8px;
    min-height: 42px;
}

#modalStudent textarea.form-control {
    min-height: auto;
}

.form-section {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 18px;
    background: #fafbfc;
}

.form-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .85rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 14px;
}

.form-section-title i {
    color: #0d6efd;
}

.required-note {
    font-size: .75rem;
    color: #6b7280;
}

.password-notice {
    border-left: 4px solid #0d6efd;
    background: #eff6ff;
    color: #334155;
    font-size: .78rem;
    padding: 10px 12px;
    border-radius: 6px;
}

/* Empty state */

.empty-state {
    padding: 50px 20px !important;
    text-align: center;
    color: #94a3b8;
}

.empty-state i {
    font-size: 40px;
    margin-bottom: 10px;
}

/* Responsive */

@media (max-width: 768px) {

    .student-page .page-header {
        align-items: flex-start !important;
    }

    .student-page .page-header .btn {
        white-space: nowrap;
    }

    .student-page .stat-card {
        margin-bottom: 0;
    }

    #modalStudent .modal-body {
        padding: 16px;
    }
}

</style>

<div class="student-page">

<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="d-flex justify-content-between align-items-center mb-4 page-header">

<div>

    <div class="d-flex align-items-center gap-2">

        <h4 class="page-title mb-1">
            <i class="fas fa-user-graduate text-primary me-2"></i>
            Manage Students
        </h4>

    </div>

    <div class="page-subtitle">
        Maintain student profiles, enrollment information, and contact details.
    </div>

    <nav aria-label="breadcrumb" class="mt-1">

        <ol class="breadcrumb mb-0 small">

            <li class="breadcrumb-item">
                <a href="<?= SITE_URL ?>/dashboard.php">
                    Home
                </a>
            </li>

            <li class="breadcrumb-item active">
                Students
            </li>

        </ol>

    </nav>

</div>


<?php if ($_SESSION['roleID'] !== ROLE_PRINCIPAL): ?>

    <button
        type="button"
        class="btn btn-primary shadow-sm"
        data-bs-toggle="modal"
        data-bs-target="#modalStudent">

        <i class="fas fa-plus me-2"></i>
        Add Student

    </button>

<?php endif; ?>

</div>

<!-- =========================================================
     STATISTICS
========================================================= -->

<div class="row g-3 mb-4">

<div class="col-6 col-xl-3">

    <div class="card stat-card shadow-sm h-100">

        <div class="card-body d-flex align-items-center">

            <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                <i class="fas fa-users"></i>
            </div>

            <div>

                <div class="stat-number">
                    <?= number_format($totalStudents) ?>
                </div>

                <div class="stat-label">
                    Total Students
                </div>

            </div>

        </div>

    </div>

</div>


<div class="col-6 col-xl-3">

    <div class="card stat-card shadow-sm h-100">

        <div class="card-body d-flex align-items-center">

            <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                <i class="fas fa-user-check"></i>
            </div>

            <div>

                <div class="stat-number">
                    <?= number_format($enrolledCount) ?>
                </div>

                <div class="stat-label">
                    Enrolled
                </div>

            </div>

        </div>

    </div>

</div>


<div class="col-6 col-xl-3">

    <div class="card stat-card shadow-sm h-100">

        <div class="card-body d-flex align-items-center">

            <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                <i class="fas fa-user-clock"></i>
            </div>

            <div>

                <div class="stat-number">
                    <?= number_format($notEnrolledCount) ?>
                </div>

                <div class="stat-label">
                    Not Enrolled
                </div>

            </div>

        </div>

    </div>

</div>


<div class="col-6 col-xl-3">

    <div class="card stat-card shadow-sm h-100">

        <div class="card-body d-flex align-items-center">

            <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                <i class="fas fa-venus-mars"></i>
            </div>

            <div>

                <div class="stat-number">
                    <?= number_format($maleCount) ?>
                    <span class="text-muted fw-normal" style="font-size:.9rem;">
                        M
                    </span>
                    /
                    <?= number_format($femaleCount) ?>
                    <span class="text-muted fw-normal" style="font-size:.9rem;">
                        F
                    </span>
                </div>

                <div class="stat-label">
                    Gender Distribution
                </div>

            </div>

        </div>

    </div>

</div>


</div>

<!-- =========================================================
     SEARCH
========================================================= -->

<div class="card search-card shadow-sm border-0 mb-3">

<div class="card-body">

    <form method="GET">

        <div class="row g-2 align-items-center">

            <div class="col-md">

                <div class="input-group">

                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>

                    <input
                        type="text"
                        name="search"
                        class="form-control search-input border-start-0"
                        placeholder="Search name, student number, LRN, or email..."
                        value="<?= clean($search) ?>"
                        autocomplete="off">

                </div>

            </div>


            <div class="col-auto">

                <button
                    type="submit"
                    class="btn btn-primary">

                    <i class="fas fa-search me-1"></i>
                    Search

                </button>

            </div>


            <?php if ($search !== ''): ?>

                <div class="col-auto">

                    <a
                        href="students.php"
                        class="btn btn-outline-secondary">

                        <i class="fas fa-times me-1"></i>
                        Clear

                    </a>

                </div>

            <?php endif; ?>

        </div>

    </form>

</div>


</div>

<!-- =========================================================
     STUDENT LIST
========================================================= -->

<div class="card border-0 shadow-sm">

<div class="card-header bg-white border-0 py-3">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>

            <div class="fw-bold text-dark">

                <i class="fas fa-list me-2 text-primary"></i>
                Student Directory

                <span class="badge bg-primary ms-1">
                    <?= number_format(count($students)) ?>
                </span>

            </div>

            <small class="text-muted">

                <?php if ($search !== ''): ?>

                    Showing results for:
                    <strong><?= clean($search) ?></strong>

                <?php else: ?>

                    All active student records

                <?php endif; ?>

            </small>

        </div>


        <a
            href="<?= SITE_URL ?>/reports/report-masterlist.php"
            target="_blank"
            class="btn btn-sm btn-outline-success">

            <i class="fas fa-print me-1"></i>
            Print Master List

        </a>

    </div>

</div>


<div class="card-body p-0">

    <div class="table-responsive">

        <table class="table table-hover datatable mb-0">

            <thead>

                <tr>

                    <th>Student No.</th>
                    <th>LRN</th>
                    <th>Student</th>
                    <th>Gender</th>
                    <th>Program / Strand</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($students): ?>

                <?php foreach ($students as $s): ?>

                    <?php

                    $studentName =
                        $s['lastName']
                        . ', '
                        . $s['firstName']
                        . (
                            !empty($s['middleName'])
                            ? ' ' . substr($s['middleName'], 0, 1) . '.'
                            : ''
                        )
                        . (
                            !empty($s['extName'])
                            ? ' ' . $s['extName']
                            : ''
                        );

                    $studentJson = htmlspecialchars(
                        json_encode(
                            $s,
                            JSON_HEX_TAG |
                            JSON_HEX_APOS |
                            JSON_HEX_AMP |
                            JSON_HEX_QUOT
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    ?>

                    <tr>

                        <!-- Student Number -->

                        <td>

                            <code class="student-number">
                                <?= clean($s['studentNo'] ?? '-') ?>
                            </code>

                        </td>


                        <!-- LRN -->

                        <td>

                            <span class="text-muted">
                                <?= clean($s['lrn'] ?? '-') ?>
                            </span>

                        </td>


                        <!-- Student -->

                        <td>

                            <div class="student-name">
                                <?= clean($studentName) ?>
                            </div>

                            <?php if (!empty($s['email'])): ?>

                                <div class="student-email">

                                    <i class="fas fa-envelope me-1"></i>

                                    <?= clean($s['email']) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- Gender -->

                        <td>

                            <?php if ((int)$s['isMale'] === 1): ?>

                                <span class="badge bg-primary gender-badge">
                                    <i class="fas fa-mars me-1"></i>
                                    Male
                                </span>

                            <?php else: ?>

                                <span class="badge bg-pink gender-badge">
                                    <i class="fas fa-venus me-1"></i>
                                    Female
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Program -->

                        <td>

                            <?php if (!empty($s['progName'])): ?>

                                <div class="program-name">
                                    <?= clean($s['progName']) ?>
                                </div>

                            <?php else: ?>

                                <div class="text-muted">
                                    No program
                                </div>

                            <?php endif; ?>


                            <?php if (!empty($s['strandName'])): ?>

                                <div class="strand-name">
                                    <i class="fas fa-layer-group me-1"></i>
                                    <?= clean($s['strandName']) ?>
                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- Enrollment Status -->

                        <td>

                            <?php if ((int)$s['isEnroll'] === 1): ?>

                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Enrolled
                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    <i class="fas fa-clock me-1"></i>
                                    Not Enrolled
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Actions -->

                        <td class="text-center">

                            <div class="btn-group btn-group-sm student-actions">

                                <a
                                    href="student-view.php?id=<?= (int)$s['studentID'] ?>"
                                    class="btn btn-outline-info"
                                    title="View Student">

                                    <i class="fas fa-eye"></i>

                                </a>


                                <?php if ($_SESSION['roleID'] !== ROLE_PRINCIPAL): ?>

                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-edit-student"
                                        data-student="<?= $studentJson ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalStudent"
                                        title="Edit Student">

                                        <i class="fas fa-edit"></i>

                                    </button>


                                    <form
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirmDeleteStudent();">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= $csrf ?>">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete">

                                        <input
                                            type="hidden"
                                            name="studentID"
                                            value="<?= (int)$s['studentID'] ?>">

                                        <button
                                            type="submit"
                                            class="btn btn-outline-danger"
                                            title="Remove Student">

                                            <i class="fas fa-trash"></i>

                                        </button>

                                    </form>

                                <?php endif; ?>


                                <?php if ((int)$s['isEnroll'] === 0): ?>

                                    <a
                                        href="<?= SITE_URL ?>/admin/enrollment.php?studentID=<?= (int)$s['studentID'] ?>"
                                        class="btn btn-outline-success"
                                        title="Enroll Student">

                                        <i class="fas fa-clipboard-check"></i>

                                    </a>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="7" class="empty-state">

                        <i class="fas fa-user-slash d-block"></i>

                        <div class="fw-semibold text-dark">
                            No students found
                        </div>

                        <small>
                            <?php if ($search !== ''): ?>
                                Try a different search term.
                            <?php else: ?>
                                No student records are currently available.
                            <?php endif; ?>
                        </small>

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</div>

<!-- =========================================================
     STUDENT MODAL
========================================================= -->

<div
    class="modal fade"
    id="modalStudent"
    tabindex="-1"
    aria-labelledby="modalStudentTitle"
    aria-hidden="true">

<div class="modal-dialog modal-lg modal-dialog-centered">

    <div class="modal-content">

        <form method="POST" id="studentForm">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= $csrf ?>">

            <input
                type="hidden"
                name="action"
                value="add"
                id="formAction">

            <input
                type="hidden"
                name="studentID"
                id="editStudentID">


            <!-- Modal Header -->

            <div class="modal-header bg-primary text-white">

                <div>

                    <h5 class="modal-title mb-1" id="modalStudentTitle">

                        <i class="fas fa-user-plus me-2"></i>
                        Add Student

                    </h5>

                    <small class="opacity-75">
                        Enter the student's personal and academic information.
                    </small>

                </div>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close">
                </button>

            </div>


            <!-- Modal Body -->

            <div class="modal-body">


                <!-- Personal Information -->

                <div class="form-section">

                    <div class="form-section-title">

                        <i class="fas fa-user"></i>

                        Personal Information

                    </div>


                    <div class="row g-3">

                        <div class="col-md-4">

                            <label class="form-label">
                                First Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="firstName"
                                id="s_firstName"
                                class="form-control"
                                maxlength="100"
                                required>

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Middle Name
                            </label>

                            <input
                                type="text"
                                name="middleName"
                                id="s_middleName"
                                class="form-control"
                                maxlength="100">

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">
                                Last Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="lastName"
                                id="s_lastName"
                                class="form-control"
                                maxlength="100"
                                required>

                        </div>


                        <div class="col-md-1">

                            <label class="form-label">
                                Ext.
                            </label>

                            <input
                                type="text"
                                name="extName"
                                id="s_extName"
                                class="form-control"
                                maxlength="20"
                                placeholder="Jr.">

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                LRN
                            </label>

                            <input
                                type="text"
                                name="lrn"
                                id="s_lrn"
                                class="form-control"
                                maxlength="20"
                                inputmode="numeric"
                                placeholder="Learner Reference No.">

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Date of Birth
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="date"
                                name="dateofBirth"
                                id="s_dob"
                                class="form-control"
                                required>

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Gender
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="isMale"
                                id="s_isMale"
                                class="form-select">

                                <option value="1">
                                    Male
                                </option>

                                <option value="0">
                                    Female
                                </option>

                            </select>

                        </div>

                    </div>

                </div>


                <!-- Academic Information -->

                <div class="form-section">

                    <div class="form-section-title">

                        <i class="fas fa-graduation-cap"></i>

                        Academic Information

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Program
                            </label>

                            <select
                                name="progID"
                                id="s_progID"
                                class="form-select">

                                <option value="">
                                    -- Select Program --
                                </option>

                                <?php foreach ($programs as $p): ?>

                                    <option
                                        value="<?= (int)$p['progID'] ?>">

                                        <?= clean($p['progName']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Strand
                            </label>

                            <select
                                name="strandID"
                                id="s_strandID"
                                class="form-select">

                                <option value="">
                                    -- Select Strand --
                                </option>

                                <?php foreach ($strands as $st): ?>

                                    <option
                                        value="<?= (int)$st['strandID'] ?>"
                                        data-prog="<?= (int)$st['progID'] ?>">

                                        <?= clean($st['strandName']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="form-text">
                                Strand options are filtered by the selected program.
                            </div>

                        </div>

                    </div>

                </div>


                <!-- Contact Information -->

                <div class="form-section">

                    <div class="form-section-title">

                        <i class="fas fa-address-book"></i>

                        Contact & Guardian Information

                    </div>


                    <div class="row g-3">

                        <div class="col-12">

                            <label class="form-label">
                                Address
                            </label>

                            <textarea
                                name="address"
                                id="s_address"
                                class="form-control"
                                rows="2"
                                maxlength="500"
                                placeholder="Complete residential address"></textarea>

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Contact No.
                            </label>

                            <input
                                type="text"
                                name="contactNo"
                                id="s_contactNo"
                                class="form-control"
                                maxlength="30"
                                inputmode="tel"
                                placeholder="09XXXXXXXXX">

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Guardian Name
                            </label>

                            <input
                                type="text"
                                name="guardianName"
                                id="s_guardianName"
                                class="form-control"
                                maxlength="150">

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Guardian Contact
                            </label>

                            <input
                                type="text"
                                name="guardianContact"
                                id="s_guardianContact"
                                class="form-control"
                                maxlength="30"
                                inputmode="tel">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Student Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="s_email"
                                class="form-control"
                                maxlength="150"
                                placeholder="student@example.com">

                            <div class="form-text">
                                Used for student login.
                            </div>

                        </div>

                    </div>

                </div>


                <!-- Login Notice -->

                <div
                    class="password-notice"
                    id="defaultPasswordNotice">

                    <i class="fas fa-key me-2 text-primary"></i>

                    <strong>Default Login Password:</strong>

                    The student's password will automatically be generated
                    using their last name and birth year.

                    <div class="mt-1 text-muted">
                        Example: <strong>delacruz2008</strong>
                    </div>

                </div>


                <div class="required-note mt-3">

                    <span class="text-danger">*</span>
                    Required fields

                </div>

            </div>


            <!-- Modal Footer -->

            <div class="modal-footer bg-light">

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="saveStudentBtn">

                    <i class="fas fa-save me-2"></i>
                    Save Student

                </button>

            </div>

        </form>

    </div>

</div>

</div>

<script>

/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const studentForm = document.getElementById('studentForm');

const formAction = document.getElementById('formAction');

const editStudentID = document.getElementById('editStudentID');

const modalStudentTitle =
    document.getElementById('modalStudentTitle');

const defaultPasswordNotice =
    document.getElementById('defaultPasswordNotice');

const saveStudentBtn =
    document.getElementById('saveStudentBtn');

const programSelect =
    document.getElementById('s_progID');

const strandSelect =
    document.getElementById('s_strandID');


/*
|--------------------------------------------------------------------------
| Filter Strands Based on Program
|--------------------------------------------------------------------------
*/

function filterStrands(selectedStrand = '') {

    const programID = programSelect.value;

    let selectedStillValid = false;

    Array.from(strandSelect.options).forEach(function(option) {

        if (!option.value) {
            option.hidden = false;
            return;
        }

        const optionProgram =
            option.getAttribute('data-prog');

        const matches =
            programID !== '' &&
            optionProgram === programID;

        option.hidden = !matches;

        if (
            matches &&
            option.value === String(selectedStrand)
        ) {
            selectedStillValid = true;
        }

    });


    if (selectedStillValid) {

        strandSelect.value = selectedStrand;

    } else {

        strandSelect.value = '';

    }
}


programSelect.addEventListener(
    'change',
    function() {

        filterStrands();

    }
);


/*
|--------------------------------------------------------------------------
| Edit Student
|--------------------------------------------------------------------------
*/

document.querySelectorAll('.btn-edit-student')
    .forEach(function(btn) {

        btn.addEventListener('click', function() {

            const s =
                JSON.parse(
                    this.getAttribute('data-student')
                );


            formAction.value = 'edit';

            editStudentID.value =
                s.studentID || '';


            modalStudentTitle.innerHTML =
                '<i class="fas fa-user-edit me-2"></i>Edit Student';


            document.getElementById('s_firstName').value =
                s.firstName || '';

            document.getElementById('s_middleName').value =
                s.middleName || '';

            document.getElementById('s_lastName').value =
                s.lastName || '';

            document.getElementById('s_extName').value =
                s.extName || '';

            document.getElementById('s_lrn').value =
                s.lrn || '';

            document.getElementById('s_dob').value =
                s.dateofBirth || '';

            document.getElementById('s_isMale').value =
                s.isMale ?? 1;

            document.getElementById('s_address').value =
                s.address || '';

            document.getElementById('s_contactNo').value =
                s.contactNo || '';

            document.getElementById('s_guardianName').value =
                s.guardianName || '';

            document.getElementById('s_guardianContact').value =
                s.guardianContact || '';

            document.getElementById('s_email').value =
                s.email || '';

            programSelect.value =
                s.progID || '';


            filterStrands(
                s.strandID || ''
            );


            defaultPasswordNotice.style.display =
                'none';


            saveStudentBtn.innerHTML =
                '<i class="fas fa-save me-2"></i>Update Student';

        });

    });


/*
|--------------------------------------------------------------------------
| Add Student
|--------------------------------------------------------------------------
*/

const addStudentButton =
    document.querySelector(
        '[data-bs-target="#modalStudent"]:not(.btn-edit-student)'
    );


if (addStudentButton) {

    addStudentButton.addEventListener(
        'click',
        function() {

            studentForm.reset();

            formAction.value = 'add';

            editStudentID.value = '';

            modalStudentTitle.innerHTML =
                '<i class="fas fa-user-plus me-2"></i>Add Student';


            defaultPasswordNotice.style.display =
                'block';


            saveStudentBtn.innerHTML =
                '<i class="fas fa-save me-2"></i>Save Student';


            Array.from(strandSelect.options)
                .forEach(function(option) {

                    option.hidden = false;

                });

        }
    );

}


/*
|--------------------------------------------------------------------------
| Clear Form After Modal Closes
|--------------------------------------------------------------------------
*/

document.getElementById('modalStudent')
    .addEventListener(
        'hidden.bs.modal',
        function() {

            studentForm.reset();

            formAction.value = 'add';

            editStudentID.value = '';

            modalStudentTitle.innerHTML =
                '<i class="fas fa-user-plus me-2"></i>Add Student';

            defaultPasswordNotice.style.display =
                'block';

            saveStudentBtn.innerHTML =
                '<i class="fas fa-save me-2"></i>Save Student';


            Array.from(strandSelect.options)
                .forEach(function(option) {

                    option.hidden = false;

                });

        }
    );


/*
|--------------------------------------------------------------------------
| Delete Confirmation
|--------------------------------------------------------------------------
*/

function confirmDeleteStudent() {

    return confirm(
        'Are you sure you want to remove this student?\n\n' +
        'The student record will be archived and will no longer appear ' +
        'in the active student list.'
    );

}


/*
|--------------------------------------------------------------------------
| Prevent Double Submission
|--------------------------------------------------------------------------
*/

studentForm.addEventListener(
    'submit',
    function() {

        saveStudentBtn.disabled = true;

        saveStudentBtn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>' +
            'Saving...';

    }
);


/*
|--------------------------------------------------------------------------
| Initial Strand State
|--------------------------------------------------------------------------
*/

filterStrands();

</script>

<?php include '../includes/footer.php'; ?>
