<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_TEACHER]);
$pageTitle = 'Enrollment';

$db = getDB();
$ay = getActiveAY();
$csrf = generateCSRF();

/* =========================================================
   HANDLE ENROLLMENT ACTIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'enroll') {

        $studentID = (int)($_POST['studentID'] ?? 0);
        $sectionID = (int)($_POST['sectionID'] ?? 0);
        $ayID      = (int)($_POST['ayID'] ?? 0);
        $yearID    = (int)($_POST['yearID'] ?? 0);
        $progID    = (int)($_POST['progID'] ?? 0);
        $strandID  = !empty($_POST['strandID'])
            ? (int)$_POST['strandID']
            : null;

        /* Basic validation */
        if (!$studentID || !$sectionID || !$ayID || !$yearID || !$progID) {
            $_SESSION['flash_error'] = 'Please complete all required enrollment fields.';
            header('Location: enrollment.php');
            exit;
        }

        /* Check section capacity */
        $cap = $db->prepare("
            SELECT COUNT(*) AS cnt, s.sectionLimit
            FROM registration r
            JOIN sections s ON r.sectionID = s.sectionID
            WHERE r.sectionID = ?
              AND r.ayID = ?
              AND r.status = 'enrolled'
              AND r.dateDeleted IS NULL
            GROUP BY s.sectionLimit
        ");

        $cap->execute([$sectionID, $ayID]);
        $capRow = $cap->fetch();

        if ($capRow && $capRow['cnt'] >= $capRow['sectionLimit']) {

            $_SESSION['flash_error'] =
                'The selected section has reached its maximum capacity.';

            header('Location: enrollment.php');
            exit;
        }

        /* Check duplicate enrollment */
        $dup = $db->prepare("
            SELECT regID
            FROM registration
            WHERE studentID = ?
              AND ayID = ?
              AND dateDeleted IS NULL
        ");

        $dup->execute([$studentID, $ayID]);

        if ($dup->fetch()) {

            $_SESSION['flash_error'] =
                'This student is already enrolled for the current academic year.';

            header('Location: enrollment.php');
            exit;
        }

        /* Generate registration code */
        $regCode =
            'REG-' .
            $ayID . '-' .
            str_pad($studentID, 5, '0', STR_PAD_LEFT) . '-' .
            strtoupper(substr(uniqid(), -4));

        $db->beginTransaction();

        try {

            /* Insert registration */
            $stmt = $db->prepare("
                INSERT INTO registration
                (
                    regCode,
                    ayID,
                    sectionID,
                    studentID,
                    yearID,
                    progID,
                    strandID,
                    status,
                    isApproved,
                    approvedBy,
                    approvedDate
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'enrolled', 1, ?, NOW())
            ");

            $stmt->execute([
                $regCode,
                $ayID,
                $sectionID,
                $studentID,
                $yearID,
                $progID,
                $strandID,
                $_SESSION['userID']
            ]);

            /* Auto-assign core subjects */
            $subjStmt = $db->prepare("
                SELECT *
                FROM subjects
                WHERE yearID = ?
                  AND isCore = 1
                  AND dateDeleted IS NULL
            ");

            $subjStmt->execute([$yearID]);
            $coreSubjects = $subjStmt->fetchAll();

            foreach ($coreSubjects as $subj) {

                $schedCheck = $db->prepare("
                    SELECT schedID, scheduleCode
                    FROM schedule
                    WHERE sectionID = ?
                      AND subjectID = ?
                      AND ayID = ?
                      AND dateDeleted IS NULL
                ");

                $schedCheck->execute([
                    $sectionID,
                    $subj['subjectID'],
                    $ayID
                ]);

                $sched = $schedCheck->fetch();

                if (!$sched) {

                    $schedCode =
                        'SCHED-' .
                        $sectionID . '-' .
                        $subj['subjectID'] . '-' .
                        $ayID;

                    $db->prepare("
                        INSERT INTO schedule
                        (
                            scheduleCode,
                            sectionID,
                            subjectID,
                            userID,
                            ayID
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([
                        $schedCode,
                        $sectionID,
                        $subj['subjectID'],
                        $_SESSION['userID'],
                        $ayID
                    ]);

                    $schedID = $db->lastInsertId();

                } else {

                    $schedID = $sched['schedID'];
                }

                /* Insert grade record */
                $db->prepare("
                    INSERT IGNORE INTO grades
                    (
                        ayID,
                        regCode,
                        studentID,
                        subjectID,
                        schedID
                    )
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([
                    $ayID,
                    $regCode,
                    $studentID,
                    $subj['subjectID'],
                    $schedID
                ]);
            }

            /* Mark student enrolled */
            $db->prepare("
                UPDATE students
                SET isEnroll = 1,
                    progID = ?,
                    strandID = ?
                WHERE studentID = ?
            ")->execute([
                $progID,
                $strandID,
                $studentID
            ]);

            $db->commit();

            logActivity(
                'Enroll Student',
                'Enrollment',
                'Enrolled student ID:' . $studentID .
                ' RegCode:' . $regCode
            );

            $_SESSION['flash_success'] =
                'Student enrolled successfully! Registration Code: ' .
                $regCode;

        } catch (Exception $e) {

            $db->rollBack();

            $_SESSION['flash_error'] =
                'Enrollment failed: ' . $e->getMessage();
        }

        header('Location: enrollment.php');
        exit;

    } elseif ($action === 'update_status') {

        $regID  = (int)($_POST['regID'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (in_array(
            $status,
            ['draft', 'enrolled', 'dropped', 'graduated'],
            true
        )) {

            $db->prepare("
                UPDATE registration
                SET status = ?
                WHERE regID = ?
            ")->execute([
                $status,
                $regID
            ]);

            if ($status === 'dropped') {

                $db->prepare("
                    UPDATE students
                    SET isEnroll = 0
                    WHERE studentID = (
                        SELECT studentID
                        FROM registration
                        WHERE regID = ?
                    )
                ")->execute([$regID]);
            }

            logActivity(
                'Update Enrollment',
                'Enrollment',
                'Updated reg ID:' . $regID . ' to ' . $status
            );

            $_SESSION['flash_success'] =
                'Enrollment status updated successfully.';
        }

        header('Location: enrollment.php');
        exit;
    }
}


/* =========================================================
   FETCH DATA
========================================================= */

/* Enrollments */
$enrollments = $db->prepare("
    SELECT
        r.*,
        s.firstName,
        s.lastName,
        s.studentNo,
        s.lrn,
        sec.sectionName,
        p.progName,
        y.yearName,
        st.strandName
    FROM registration r

    JOIN students s
        ON r.studentID = s.studentID

    JOIN sections sec
        ON r.sectionID = sec.sectionID

    JOIN programs p
        ON r.progID = p.progID

    JOIN year y
        ON r.yearID = y.yearID

    LEFT JOIN strands st
        ON r.strandID = st.strandID

    WHERE r.ayID = ?
      AND r.dateDeleted IS NULL

    ORDER BY
        s.lastName,
        s.firstName
");

$enrollments->execute([$ay['ayID']]);
$enrollmentList = $enrollments->fetchAll();


/* Students not enrolled */
$unenrolled = $db->prepare("
    SELECT s.*
    FROM students s

    WHERE s.dateDeleted IS NULL

      AND s.studentID NOT IN (
          SELECT studentID
          FROM registration
          WHERE ayID = ?
            AND dateDeleted IS NULL
      )

    ORDER BY
        s.lastName,
        s.firstName
");

$unenrolled->execute([$ay['ayID']]);
$unenrolledList = $unenrolled->fetchAll();


/* Sections */
$sections = $db->prepare("
    SELECT
        sec.*,
        p.progName,
        y.yearName
    FROM sections sec

    JOIN programs p
        ON sec.progID = p.progID

    JOIN year y
        ON sec.yearID = y.yearID

    WHERE sec.ayID = ?
      AND sec.dateDeleted IS NULL

    ORDER BY
        p.progName,
        y.yearName,
        sec.sectionName
");

$sections->execute([$ay['ayID']]);
$sectionsList = $sections->fetchAll();


/* Programs */
$programs = $db->query("
    SELECT *
    FROM programs
    WHERE dateDeleted IS NULL
    ORDER BY progName
")->fetchAll();


/* Year levels */
$yearLevels = $db->query("
    SELECT *
    FROM year
    WHERE dateDeleted IS NULL
")->fetchAll();


/* Strands */
$strands = $db->query("
    SELECT *
    FROM strands
    WHERE dateDeleted IS NULL
")->fetchAll();


/* Statistics */
$totalEnrolled = $db->prepare("
    SELECT COUNT(*)
    FROM registration
    WHERE ayID = ?
      AND status = 'enrolled'
      AND dateDeleted IS NULL
");

$totalEnrolled->execute([$ay['ayID']]);
$totalEnrolledCount = (int)$totalEnrolled->fetchColumn();


$totalDraft = $db->prepare("
    SELECT COUNT(*)
    FROM registration
    WHERE ayID = ?
      AND status = 'draft'
      AND dateDeleted IS NULL
");

$totalDraft->execute([$ay['ayID']]);
$totalDraftCount = (int)$totalDraft->fetchColumn();


$totalDropped = $db->prepare("
    SELECT COUNT(*)
    FROM registration
    WHERE ayID = ?
      AND status = 'dropped'
      AND dateDeleted IS NULL
");

$totalDropped->execute([$ay['ayID']]);
$totalDroppedCount = (int)$totalDropped->fetchColumn();


$totalGraduated = $db->prepare("
    SELECT COUNT(*)
    FROM registration
    WHERE ayID = ?
      AND status = 'graduated'
      AND dateDeleted IS NULL
");

$totalGraduated->execute([$ay['ayID']]);
$totalGraduatedCount = (int)$totalGraduated->fetchColumn();


include '../includes/header.php';
?>

<style>

/* =========================================================
   ENROLLMENT DASHBOARD
========================================================= */

.enrollment-page {
    --en-primary: #2563eb;
    --en-primary-dark: #1d4ed8;
    --en-success: #16a34a;
    --en-warning: #d97706;
    --en-danger: #dc2626;
    --en-info: #0891b2;
    --en-purple: #7c3aed;
    --en-text: #1e293b;
    --en-muted: #64748b;
    --en-border: #e2e8f0;
    --en-bg: #f8fafc;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.enrollment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
}

.enrollment-title-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
}

.enrollment-page-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 14px;

    color: #fff;
    font-size: 20px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 8px 20px
        rgba(37, 99, 235, .22);
}

.enrollment-title {
    font-weight: 750;
    color: var(--en-text);
    margin: 0;
}

.enrollment-subtitle {
    color: var(--en-muted);
    font-size: 13px;
    margin-top: 3px;
}

.enrollment-breadcrumb {
    font-size: 12px;
    margin-top: 4px;
}


/* =========================================================
   ACADEMIC YEAR BANNER
========================================================= */

.academic-year-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    padding: 15px 18px;

    margin-bottom: 20px;

    border: 1px solid #bfdbfe;
    border-radius: 13px;

    background:
        linear-gradient(
            90deg,
            #eff6ff,
            #f5f3ff
        );
}

.academic-year-info {
    display: flex;
    align-items: center;
    gap: 11px;
}

.academic-year-icon {
    width: 38px;
    height: 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background: #fff;
    color: var(--en-primary);

    box-shadow: 0 2px 8px rgba(37, 99, 235, .08);
}

.academic-year-label {
    color: #64748b;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
}

.academic-year-value {
    color: #1e3a8a;
    font-size: 16px;
    font-weight: 750;
}


/* =========================================================
   PRIMARY ACTION
========================================================= */

.btn-enroll {
    border: 0;
    border-radius: 10px;

    padding: 11px 18px;

    font-weight: 650;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    box-shadow:
        0 5px 15px
        rgba(37, 99, 235, .20);

    transition: .2s ease;
}

.btn-enroll:hover {
    transform: translateY(-1px);

    box-shadow:
        0 8px 20px
        rgba(37, 99, 235, .28);
}


/* =========================================================
   STATISTICS
========================================================= */

.enrollment-stat {
    position: relative;

    height: 100%;

    padding: 18px;

    border: 1px solid var(--en-border);
    border-radius: 14px;

    background: #fff;

    box-shadow:
        0 2px 8px
        rgba(15, 23, 42, .035);

    overflow: hidden;

    transition: .2s ease;
}

.enrollment-stat:hover {
    transform: translateY(-2px);

    box-shadow:
        0 9px 22px
        rgba(15, 23, 42, .07);
}

.enrollment-stat::after {
    content: "";

    position: absolute;

    right: -20px;
    bottom: -30px;

    width: 90px;
    height: 90px;

    border-radius: 50%;

    opacity: .35;
}

.stat-blue::after {
    background: #dbeafe;
}

.stat-green::after {
    background: #dcfce7;
}

.stat-yellow::after {
    background: #fef3c7;
}

.stat-red::after {
    background: #fee2e2;
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-icon {
    width: 42px;
    height: 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    font-size: 16px;
}

.stat-blue .stat-icon {
    color: #2563eb;
    background: #eff6ff;
}

.stat-green .stat-icon {
    color: #16a34a;
    background: #f0fdf4;
}

.stat-yellow .stat-icon {
    color: #d97706;
    background: #fffbeb;
}

.stat-red .stat-icon {
    color: #dc2626;
    background: #fef2f2;
}

.stat-label {
    color: var(--en-muted);
    font-size: 12px;
    font-weight: 550;
    margin-bottom: 5px;
}

.stat-number {
    color: var(--en-text);
    font-size: 26px;
    line-height: 1;
    font-weight: 800;
}


/* =========================================================
   MAIN CARD
========================================================= */

.enrollment-card {
    background: #fff;

    border: 1px solid var(--en-border);

    border-radius: 16px;

    overflow: hidden;

    box-shadow:
        0 3px 12px
        rgba(15, 23, 42, .045);
}

.enrollment-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    padding: 17px 20px;

    border-bottom: 1px solid var(--en-border);
}

.enrollment-card-title {
    margin: 0;

    color: var(--en-text);

    font-size: 15px;
    font-weight: 750;
}

.enrollment-card-subtitle {
    color: var(--en-muted);

    font-size: 12px;

    margin-top: 3px;
}


/* =========================================================
   TABS
========================================================= */

.enrollment-tabs {
    border-bottom: 0;
}

.enrollment-tabs .nav-link {
    border: 0;

    color: #64748b;

    font-size: 13px;
    font-weight: 600;

    padding: 14px 18px;

    border-bottom: 2px solid transparent;

    transition: .15s ease;
}

.enrollment-tabs .nav-link:hover {
    color: var(--en-primary);
}

.enrollment-tabs .nav-link.active {
    color: var(--en-primary);

    background: transparent;

    border-bottom-color: var(--en-primary);
}

.tab-count {
    display: inline-flex;

    min-width: 21px;
    height: 21px;

    padding: 0 6px;

    align-items: center;
    justify-content: center;

    margin-left: 5px;

    border-radius: 999px;

    background: #eff6ff;

    color: #2563eb;

    font-size: 10px;
}


/* =========================================================
   TABLE
========================================================= */

.enrollment-table {
    margin: 0;
}

.enrollment-table thead th {
    background: #f8fafc;

    color: #64748b;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .055em;

    font-weight: 750;

    padding: 13px 15px;

    border-bottom: 1px solid var(--en-border);

    white-space: nowrap;
}

.enrollment-table tbody td {
    padding: 14px 15px;

    vertical-align: middle;

    border-bottom: 1px solid #f1f5f9;

    color: #334155;

    font-size: 12px;
}

.enrollment-table tbody tr {
    transition: .15s ease;
}

.enrollment-table tbody tr:hover {
    background: #f8fbff;
}

.enrollment-table tbody tr:last-child td {
    border-bottom: 0;
}


/* =========================================================
   STUDENT IDENTITY
========================================================= */

.student-identity {
    display: flex;
    align-items: center;

    gap: 10px;
}

.student-avatar {
    width: 36px;
    height: 36px;

    flex: 0 0 36px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #3b82f6,
            #6366f1
        );

    color: #fff;

    font-size: 11px;
    font-weight: 750;
}

.student-name {
    color: #1e293b;

    font-size: 13px;

    font-weight: 700;
}

.student-lrn {
    color: #94a3b8;

    font-size: 10px;

    margin-top: 2px;
}


/* =========================================================
   CODE
========================================================= */

.reg-code {
    display: inline-block;

    padding: 4px 7px;

    border-radius: 6px;

    color: #475569;

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    font-size: 10px;
}

.student-no {
    color: #334155;

    font-size: 11px;

    font-weight: 650;
}


/* =========================================================
   ACADEMIC INFO
========================================================= */

.academic-info-main {
    color: #334155;

    font-weight: 650;

    font-size: 12px;
}

.academic-info-sub {
    color: #94a3b8;

    font-size: 10px;

    margin-top: 2px;
}


/* =========================================================
   STATUS
========================================================= */

.enrollment-status {
    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 5px 9px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 700;
}

.status-dot {
    width: 6px;
    height: 6px;

    border-radius: 50%;
}

.status-enrolled {
    color: #15803d;
    background: #f0fdf4;
}

.status-enrolled .status-dot {
    background: #22c55e;
}

.status-draft {
    color: #b45309;
    background: #fffbeb;
}

.status-draft .status-dot {
    background: #f59e0b;
}

.status-dropped {
    color: #b91c1c;
    background: #fef2f2;
}

.status-dropped .status-dot {
    background: #ef4444;
}

.status-graduated {
    color: #0369a1;
    background: #f0f9ff;
}

.status-graduated .status-dot {
    background: #0ea5e9;
}


/* =========================================================
   ACTION BUTTONS
========================================================= */

.enrollment-actions {
    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 5px;
}

.action-btn {
    width: 31px;
    height: 31px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    padding: 0;

    border-radius: 8px !important;

    font-size: 11px;
}

.view-btn {
    color: #0891b2;

    background: #ecfeff;

    border-color: #a5f3fc;
}

.status-btn {
    color: #475569;

    background: #f8fafc;

    border-color: #e2e8f0;
}


/* =========================================================
   UNENROLLED ROW
========================================================= */

.unenrolled-table .student-avatar {
    background:
        linear-gradient(
            135deg,
            #0ea5e9,
            #2563eb
        );
}

.quick-enroll-btn {
    border-radius: 8px;

    font-size: 11px;

    font-weight: 650;
}


/* =========================================================
   MODAL
========================================================= */

.enrollment-modal .modal-content {
    border: 0;

    border-radius: 17px;

    overflow: hidden;

    box-shadow:
        0 25px 65px
        rgba(15, 23, 42, .20);
}

.enrollment-modal .modal-header {
    padding: 20px 22px;

    border: 0;

    background:
        linear-gradient(
            135deg,
            #16a34a,
            #059669
        );
}

.enrollment-modal .modal-title {
    font-weight: 750;
}

.modal-subtitle {
    font-size: 11px;

    opacity: .78;
}

.enrollment-modal .modal-body {
    padding: 24px;
}

.enrollment-modal .modal-footer {
    padding: 15px 22px;

    border-top: 1px solid var(--en-border);

    background: #f8fafc;
}


/* =========================================================
   FORM
========================================================= */

.form-section {
    display: flex;
    align-items: center;

    gap: 8px;

    margin-bottom: 12px;
}

.form-section-icon {
    width: 28px;
    height: 28px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    color: #059669;

    background: #ecfdf5;

    font-size: 11px;
}

.form-section-title {
    color: #334155;

    font-size: 12px;

    text-transform: uppercase;

    letter-spacing: .055em;

    font-weight: 750;
}

.enrollment-form .form-label {
    color: #334155;

    font-size: 12px;

    font-weight: 650;

    margin-bottom: 6px;
}

.enrollment-form .form-select {
    min-height: 42px;

    border-radius: 9px;

    border-color: #dbe2ea;

    font-size: 12px;
}

.enrollment-form .form-select:focus {
    border-color: #34d399;

    box-shadow:
        0 0 0 3px
        rgba(16, 185, 129, .10);
}


/* =========================================================
   STUDENT SELECTED PREVIEW
========================================================= */

.student-preview {
    display: none;

    align-items: center;

    gap: 10px;

    padding: 10px 12px;

    margin-top: 9px;

    border-radius: 9px;

    border: 1px solid #dbeafe;

    background: #eff6ff;
}

.student-preview.show {
    display: flex;
}

.preview-avatar {
    width: 32px;
    height: 32px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    color: #fff;

    background: #2563eb;

    font-size: 10px;
    font-weight: 700;
}

.preview-name {
    color: #1e3a8a;

    font-size: 12px;

    font-weight: 700;
}

.preview-number {
    color: #64748b;

    font-size: 10px;
}


/* =========================================================
   INFO BOX
========================================================= */

.enrollment-info {
    display: flex;

    gap: 10px;

    padding: 12px 13px;

    margin-top: 18px;

    border-radius: 10px;

    border: 1px solid #bae6fd;

    background: #f0f9ff;

    color: #0369a1;

    font-size: 11px;

    line-height: 1.5;
}

.enrollment-info i {
    margin-top: 2px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-enrollment {
    text-align: center;

    padding: 55px 20px;

    color: #64748b;
}

.empty-enrollment-icon {
    width: 60px;
    height: 60px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin: 0 auto 14px;

    border-radius: 50%;

    color: #2563eb;

    background: #eff6ff;

    font-size: 22px;
}

.empty-enrollment h6 {
    color: #334155;

    font-weight: 750;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .enrollment-header {
        align-items: flex-start;

        flex-direction: column;
    }

    .btn-enroll {
        width: 100%;
    }

    .academic-year-banner {
        align-items: flex-start;

        flex-direction: column;
    }

    .enrollment-table thead th:nth-child(1),
    .enrollment-table tbody td:nth-child(1) {
        display: none;
    }

    .enrollment-card-header {
        align-items: stretch;

        flex-direction: column;
    }
}

@media (max-width: 576px) {

    .enrollment-title-wrap {
        align-items: flex-start;
    }

    .enrollment-page-icon {
        width: 43px;
        height: 43px;
    }

    .stat-number {
        font-size: 22px;
    }

    .enrollment-table thead th,
    .enrollment-table tbody td {
        padding: 11px 9px;
    }

    .academic-info-sub {
        display: none;
    }

    .enrollment-modal .modal-body {
        padding: 18px;
    }

    .enrollment-modal .modal-footer {
        padding: 12px 18px;
    }
}

</style>


<div class="enrollment-page">

    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="enrollment-header">

        <div class="enrollment-title-wrap">

            <div class="enrollment-page-icon">
                <i class="fas fa-clipboard-check"></i>
            </div>

            <div>

                <h4 class="enrollment-title">
                    Enrollment Management
                </h4>

                <div class="enrollment-subtitle">
                    Manage student enrollment and academic placement
                </div>

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb enrollment-breadcrumb mb-0">

                        <li class="breadcrumb-item">
                            <a href="<?= SITE_URL ?>/dashboard.php">
                                Home
                            </a>
                        </li>

                        <li class="breadcrumb-item active">
                            Enrollment
                        </li>

                    </ol>
                </nav>

            </div>

        </div>


        <?php if ($_SESSION['roleID'] !== ROLE_PRINCIPAL): ?>

            <button
                class="btn btn-primary btn-enroll"
                data-bs-toggle="modal"
                data-bs-target="#modalEnroll"
                id="btnOpenEnroll"
            >
                <i class="fas fa-user-plus me-2"></i>
                Enroll Student
            </button>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         ACADEMIC YEAR
    ====================================================== -->

    <div class="academic-year-banner">

        <div class="academic-year-info">

            <div class="academic-year-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>

            <div>

                <div class="academic-year-label">
                    Current Academic Year
                </div>

                <div class="academic-year-value">
                    <?= clean($ay['ayName'] ?? 'Active Academic Year') ?>
                </div>

            </div>

        </div>

        <span class="badge bg-success">
            <i class="fas fa-circle me-1"
               style="font-size:6px;"></i>
            Active
        </span>

    </div>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">

            <div class="enrollment-stat stat-green">

                <div class="stat-top">

                    <div>
                        <div class="stat-label">
                            Enrolled
                        </div>

                        <div class="stat-number">
                            <?= $totalEnrolledCount ?>
                        </div>
                    </div>

                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="enrollment-stat stat-yellow">

                <div class="stat-top">

                    <div>
                        <div class="stat-label">
                            Draft
                        </div>

                        <div class="stat-number">
                            <?= $totalDraftCount ?>
                        </div>
                    </div>

                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="enrollment-stat stat-red">

                <div class="stat-top">

                    <div>
                        <div class="stat-label">
                            Dropped
                        </div>

                        <div class="stat-number">
                            <?= $totalDroppedCount ?>
                        </div>
                    </div>

                    <div class="stat-icon">
                        <i class="fas fa-user-times"></i>
                    </div>

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="enrollment-stat stat-blue">

                <div class="stat-top">

                    <div>
                        <div class="stat-label">
                            Not Yet Enrolled
                        </div>

                        <div class="stat-number">
                            <?= count($unenrolledList) ?>
                        </div>
                    </div>

                    <div class="stat-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ENROLLMENT TABLE
    ====================================================== -->

    <div class="enrollment-card">

        <div class="enrollment-card-header">

            <div>

                <h6 class="enrollment-card-title">
                    <i class="fas fa-users text-primary me-2"></i>
                    Student Enrollment
                </h6>

                <div class="enrollment-card-subtitle">
                    <?= count($enrollmentList) ?>
                    enrollment record<?= count($enrollmentList) === 1 ? '' : 's' ?>
                    for the current academic year
                </div>

            </div>

        </div>


        <!-- Tabs -->

        <div class="px-3 pt-1 border-bottom">

            <ul
                class="nav enrollment-tabs"
                role="tablist"
            >

                <li class="nav-item">

                    <a
                        class="nav-link active"
                        data-bs-toggle="tab"
                        href="#tabEnrolled"
                    >

                        <i class="fas fa-user-check me-1"></i>

                        Enrolled

                        <span class="tab-count">
                            <?= count($enrollmentList) ?>
                        </span>

                    </a>

                </li>


                <li class="nav-item">

                    <a
                        class="nav-link"
                        data-bs-toggle="tab"
                        href="#tabUnenrolled"
                    >

                        <i class="fas fa-user-clock me-1"></i>

                        Not Yet Enrolled

                        <span class="tab-count">
                            <?= count($unenrolledList) ?>
                        </span>

                    </a>

                </li>

            </ul>

        </div>


        <div class="tab-content">


            <!-- =================================================
                 ENROLLED TAB
            ================================================== -->

            <div
                class="tab-pane fade show active"
                id="tabEnrolled"
            >

                <?php if (empty($enrollmentList)): ?>

                    <div class="empty-enrollment">

                        <div class="empty-enrollment-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>

                        <h6>
                            No enrolled students
                        </h6>

                        <p class="mb-0">
                            No students have been enrolled for
                            this academic year yet.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table
                            class="table enrollment-table datatable mb-0"
                            id="enrollmentTable"
                        >

                            <thead>

                                <tr>

                                    <th>Registration</th>

                                    <th>Student</th>

                                    <th>Section</th>

                                    <th>Program / Strand</th>

                                    <th>Year</th>

                                    <th>Status</th>

                                    <th class="text-end">
                                        Actions
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($enrollmentList as $e): ?>

                                <?php

                                $initials =
                                    strtoupper(
                                        substr($e['firstName'], 0, 1) .
                                        substr($e['lastName'], 0, 1)
                                    );

                                $statusClass = match ($e['status']) {

                                    'enrolled' =>
                                        'status-enrolled',

                                    'draft' =>
                                        'status-draft',

                                    'dropped' =>
                                        'status-dropped',

                                    'graduated' =>
                                        'status-graduated',

                                    default =>
                                        'status-draft'
                                };

                                ?>

                                <tr>

                                    <!-- Registration -->

                                    <td>

                                        <code class="reg-code">
                                            <?= clean($e['regCode']) ?>
                                        </code>

                                        <div class="student-no mt-1">
                                            <?= clean($e['studentNo']) ?>
                                        </div>

                                    </td>


                                    <!-- Student -->

                                    <td>

                                        <div class="student-identity">

                                            <div class="student-avatar">
                                                <?= clean($initials) ?>
                                            </div>

                                            <div>

                                                <div class="student-name">
                                                    <?= clean(
                                                        $e['lastName'] .
                                                        ', ' .
                                                        $e['firstName']
                                                    ) ?>
                                                </div>

                                                <?php if (!empty($e['lrn'])): ?>

                                                    <div class="student-lrn">
                                                        LRN:
                                                        <?= clean($e['lrn']) ?>
                                                    </div>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- Section -->

                                    <td>

                                        <div class="academic-info-main">
                                            <?= clean($e['sectionName']) ?>
                                        </div>

                                    </td>


                                    <!-- Program -->

                                    <td>

                                        <div class="academic-info-main">
                                            <?= clean($e['progName']) ?>
                                        </div>

                                        <?php if (!empty($e['strandName'])): ?>

                                            <div class="academic-info-sub">
                                                <?= clean($e['strandName']) ?>
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Year -->

                                    <td>

                                        <span class="academic-info-main">
                                            <?= clean($e['yearName']) ?>
                                        </span>

                                    </td>


                                    <!-- Status -->

                                    <td>

                                        <span
                                            class="enrollment-status <?= $statusClass ?>"
                                        >

                                            <span class="status-dot"></span>

                                            <?= ucfirst(clean($e['status'])) ?>

                                        </span>

                                    </td>


                                    <!-- Actions -->

                                    <td>

                                        <div class="enrollment-actions">

                                            <a
                                                href="<?= SITE_URL ?>/admin/student-view.php?id=<?= (int)$e['studentID'] ?>"
                                                class="btn action-btn view-btn"
                                                title="View Student"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </a>


                                            <?php if ($_SESSION['roleID'] === ROLE_ADMIN): ?>

                                                <div class="dropdown">

                                                    <button
                                                        class="btn action-btn status-btn dropdown-toggle"
                                                        data-bs-toggle="dropdown"
                                                        title="Update Status"
                                                    >
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>

                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                                                        <li>
                                                            <h6 class="dropdown-header">
                                                                Change Status
                                                            </h6>
                                                        </li>

                                                        <?php
                                                        $statusOptions = [
                                                            'enrolled' => 'Enrolled',
                                                            'draft' => 'Draft',
                                                            'dropped' => 'Dropped',
                                                            'graduated' => 'Graduated'
                                                        ];
                                                        ?>

                                                        <?php foreach ($statusOptions as $st => $label): ?>

                                                            <li>

                                                                <form
                                                                    method="POST"
                                                                    class="d-inline"
                                                                >

                                                                    <input
                                                                        type="hidden"
                                                                        name="csrf_token"
                                                                        value="<?= $csrf ?>"
                                                                    >

                                                                    <input
                                                                        type="hidden"
                                                                        name="action"
                                                                        value="update_status"
                                                                    >

                                                                    <input
                                                                        type="hidden"
                                                                        name="regID"
                                                                        value="<?= (int)$e['regID'] ?>"
                                                                    >

                                                                    <input
                                                                        type="hidden"
                                                                        name="status"
                                                                        value="<?= $st ?>"
                                                                    >

                                                                    <button
                                                                        type="submit"
                                                                        class="dropdown-item <?= $e['status'] === $st ? 'active' : '' ?>"
                                                                    >
                                                                        <i class="fas fa-circle me-2"
                                                                           style="font-size:6px;"></i>
                                                                        <?= $label ?>
                                                                    </button>

                                                                </form>

                                                            </li>

                                                        <?php endforeach; ?>

                                                    </ul>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 UNENROLLED TAB
            ================================================== -->

            <div
                class="tab-pane fade"
                id="tabUnenrolled"
            >

                <?php if (empty($unenrolledList)): ?>

                    <div class="empty-enrollment">

                        <div class="empty-enrollment-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>

                        <h6>
                            All students are enrolled
                        </h6>

                        <p class="mb-0">
                            There are no students waiting for enrollment.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table
                            class="table enrollment-table unenrolled-table datatable mb-0"
                        >

                            <thead>

                                <tr>

                                    <th>Student No.</th>
                                    <th>Student</th>
                                    <th>LRN</th>
                                    <th>Program</th>
                                    <th class="text-end">
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($unenrolledList as $u): ?>

                                <?php

                                $initials =
                                    strtoupper(
                                        substr($u['firstName'], 0, 1) .
                                        substr($u['lastName'], 0, 1)
                                    );

                                ?>

                                <tr>

                                    <td>

                                        <code class="student-no">
                                            <?= clean($u['studentNo']) ?>
                                        </code>

                                    </td>


                                    <td>

                                        <div class="student-identity">

                                            <div class="student-avatar">
                                                <?= clean($initials) ?>
                                            </div>

                                            <div>

                                                <div class="student-name">
                                                    <?= clean(
                                                        $u['lastName'] .
                                                        ', ' .
                                                        $u['firstName']
                                                    ) ?>
                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <span class="text-muted small">
                                            <?= clean($u['lrn'] ?? '-') ?>
                                        </span>

                                    </td>


                                    <td>

                                        <span class="academic-info-main">
                                            <?= clean($u['progName'] ?? '-') ?>
                                        </span>

                                    </td>


                                    <td>

                                        <?php if ($_SESSION['roleID'] !== ROLE_PRINCIPAL): ?>

                                            <div class="text-end">

                                                <button
                                                    type="button"
                                                    class="btn btn-success quick-enroll-btn btn-enroll-quick"
                                                    data-id="<?= (int)$u['studentID'] ?>"
                                                    data-name="<?= clean(
                                                        $u['firstName'] .
                                                        ' ' .
                                                        $u['lastName']
                                                    ) ?>"
                                                    data-studentno="<?= clean($u['studentNo']) ?>"
                                                    data-prog="<?= (int)$u['progID'] ?>"
                                                    data-strand="<?= (int)$u['strandID'] ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEnroll"
                                                >

                                                    <i class="fas fa-user-plus me-1"></i>
                                                    Enroll

                                                </button>

                                            </div>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     ENROLLMENT MODAL
========================================================= -->

<div
    class="modal fade enrollment-modal"
    id="modalEnroll"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <form
                method="POST"
                class="enrollment-form"
                id="enrollmentForm"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= $csrf ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="enroll"
                >

                <input
                    type="hidden"
                    name="ayID"
                    value="<?= (int)$ay['ayID'] ?>"
                >


                <!-- Modal Header -->

                <div class="modal-header text-white">

                    <div>

                        <h5 class="modal-title mb-1">

                            <i class="fas fa-user-graduate me-2"></i>

                            Enroll Student

                        </h5>

                        <div class="modal-subtitle">
                            Assign a student to an academic program and section
                        </div>

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


                    <!-- Student -->

                    <div class="form-section">

                        <div class="form-section-icon">
                            <i class="fas fa-user"></i>
                        </div>

                        <div class="form-section-title">
                            Student Information
                        </div>

                    </div>


                    <div class="mb-4">

                        <label class="form-label">
                            Student
                            <span class="text-danger">*</span>
                        </label>

                        <select
                            name="studentID"
                            id="enrollStudentID"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select a student
                            </option>

                            <?php foreach ($unenrolledList as $u): ?>

                                <option
                                    value="<?= (int)$u['studentID'] ?>"
                                    data-name="<?= clean(
                                        $u['firstName'] .
                                        ' ' .
                                        $u['lastName']
                                    ) ?>"
                                    data-studentno="<?= clean($u['studentNo']) ?>"
                                    data-prog="<?= (int)$u['progID'] ?>"
                                    data-strand="<?= (int)$u['strandID'] ?>"
                                >

                                    <?= clean(
                                        $u['studentNo'] .
                                        ' — ' .
                                        $u['lastName'] .
                                        ', ' .
                                        $u['firstName']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>


                        <div
                            class="student-preview"
                            id="studentPreview"
                        >

                            <div
                                class="preview-avatar"
                                id="previewAvatar"
                            >
                                --
                            </div>

                            <div>

                                <div
                                    class="preview-name"
                                    id="previewName"
                                >
                                </div>

                                <div
                                    class="preview-number"
                                    id="previewNumber"
                                >
                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Academic Placement -->

                    <div class="form-section">

                        <div class="form-section-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>

                        <div class="form-section-title">
                            Academic Placement
                        </div>

                    </div>


                    <div class="row g-3">


                        <!-- Program -->

                        <div class="col-md-6">

                            <label class="form-label">

                                Program
                                <span class="text-danger">*</span>

                            </label>

                            <select
                                name="progID"
                                id="enrollProgID"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select program
                                </option>

                                <?php foreach ($programs as $p): ?>

                                    <option value="<?= (int)$p['progID'] ?>">

                                        <?= clean($p['progName']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Strand -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Strand
                            </label>

                            <select
                                name="strandID"
                                id="enrollStrandID"
                                class="form-select"
                            >

                                <option value="">
                                    No strand / Select strand
                                </option>

                                <?php foreach ($strands as $st): ?>

                                    <option
                                        value="<?= (int)$st['strandID'] ?>"
                                        data-prog="<?= (int)$st['progID'] ?>"
                                    >

                                        <?= clean($st['strandName']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Year Level -->

                        <div class="col-md-6">

                            <label class="form-label">

                                Year Level
                                <span class="text-danger">*</span>

                            </label>

                            <select
                                name="yearID"
                                id="enrollYearID"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select year level
                                </option>

                                <?php foreach ($yearLevels as $y): ?>

                                    <option
                                        value="<?= (int)$y['yearID'] ?>"
                                        data-prog="<?= (int)$y['progID'] ?>"
                                    >

                                        <?= clean($y['yearName']) ?>

                                        <?php if (!empty($y['yearDesc'])): ?>
                                            —
                                            <?= clean($y['yearDesc']) ?>
                                        <?php endif; ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Section -->

                        <div class="col-md-6">

                            <label class="form-label">

                                Section
                                <span class="text-danger">*</span>

                            </label>

                            <select
                                name="sectionID"
                                id="enrollSectionID"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select section
                                </option>

                                <?php foreach ($sectionsList as $sec): ?>

                                    <option
                                        value="<?= (int)$sec['sectionID'] ?>"
                                        data-prog="<?= (int)$sec['progID'] ?>"
                                        data-year="<?= (int)$sec['yearID'] ?>"
                                    >

                                        <?= clean($sec['sectionName']) ?>

                                        —
                                        <?= clean($sec['yearName']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- Information -->

                    <div class="enrollment-info">

                        <i class="fas fa-info-circle"></i>

                        <div>

                            <strong>Automatic setup:</strong>

                            After enrollment is confirmed, the system will
                            automatically assign the student's core subjects
                            and create the required grade records.

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
                        class="btn btn-success px-4"
                    >

                        <i class="fas fa-check me-2"></i>

                        Confirm Enrollment

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const studentSelect =
        document.getElementById('enrollStudentID');

    const programSelect =
        document.getElementById('enrollProgID');

    const strandSelect =
        document.getElementById('enrollStrandID');

    const yearSelect =
        document.getElementById('enrollYearID');

    const sectionSelect =
        document.getElementById('enrollSectionID');

    const studentPreview =
        document.getElementById('studentPreview');

    const previewAvatar =
        document.getElementById('previewAvatar');

    const previewName =
        document.getElementById('previewName');

    const previewNumber =
        document.getElementById('previewNumber');


    /*
     * ---------------------------------------------------------
     * Student preview
     * ---------------------------------------------------------
     */

    function updateStudentPreview() {

        const option =
            studentSelect.options[
                studentSelect.selectedIndex
            ];

        if (!option || !option.value) {

            studentPreview.classList.remove('show');

            return;
        }

        const name =
            option.getAttribute('data-name') || '';

        const studentNo =
            option.getAttribute('data-studentno') || '';

        const parts =
            name.trim().split(/\s+/);

        let initials = '';

        if (parts.length >= 2) {
            initials =
                parts[0].charAt(0) +
                parts[parts.length - 1].charAt(0);
        } else {
            initials =
                name.substring(0, 2);
        }

        previewAvatar.textContent =
            initials.toUpperCase();

        previewName.textContent =
            name;

        previewNumber.textContent =
            'Student No. ' + studentNo;

        studentPreview.classList.add('show');


        /*
         * Automatically use student's existing program
         * and strand when available.
         */

        const prog =
            option.getAttribute('data-prog');

        const strand =
            option.getAttribute('data-strand');

        if (prog) {
            programSelect.value = prog;
            filterAcademicOptions();
        }

        if (strand && strand !== '0') {
            strandSelect.value = strand;
        }
    }


    /*
     * ---------------------------------------------------------
     * Filter strands, years and sections
     * based on selected program/year
     * ---------------------------------------------------------
     */

    function filterAcademicOptions() {

        const selectedProgram =
            programSelect.value;

        const selectedYear =
            yearSelect.value;


        /* Strand */

        Array.from(
            strandSelect.options
        ).forEach(function (option) {

            if (!option.value) {
                option.hidden = false;
                return;
            }

            const optionProgram =
                option.getAttribute('data-prog');

            option.hidden =
                selectedProgram &&
                optionProgram !== selectedProgram;

            if (option.hidden &&
                option.selected) {

                strandSelect.value = '';
            }

        });


        /* Year levels */

        Array.from(
            yearSelect.options
        ).forEach(function (option) {

            if (!option.value) {
                option.hidden = false;
                return;
            }

            const optionProgram =
                option.getAttribute('data-prog');

            option.hidden =
                selectedProgram &&
                optionProgram !== selectedProgram;

            if (option.hidden &&
                option.selected) {

                yearSelect.value = '';
            }

        });


        /* Sections */

        Array.from(
            sectionSelect.options
        ).forEach(function (option) {

            if (!option.value) {
                option.hidden = false;
                return;
            }

            const optionProgram =
                option.getAttribute('data-prog');

            const optionYear =
                option.getAttribute('data-year');

            let visible = true;

            if (
                selectedProgram &&
                optionProgram !== selectedProgram
            ) {
                visible = false;
            }

            if (
                selectedYear &&
                optionYear !== selectedYear
            ) {
                visible = false;
            }

            option.hidden = !visible;

            if (!visible && option.selected) {
                sectionSelect.value = '';
            }

        });
    }


    /*
     * ---------------------------------------------------------
     * Events
     * ---------------------------------------------------------
     */

    if (studentSelect) {

        studentSelect.addEventListener(
            'change',
            updateStudentPreview
        );

    }


    if (programSelect) {

        programSelect.addEventListener(
            'change',
            function () {

                filterAcademicOptions();

                /*
                 * Reset year and section because
                 * they depend on program.
                 */

                yearSelect.value = '';
                sectionSelect.value = '';

            }
        );

    }


    if (yearSelect) {

        yearSelect.addEventListener(
            'change',
            function () {

                filterAcademicOptions();

                sectionSelect.value = '';

            }
        );

    }


    /*
     * ---------------------------------------------------------
     * Quick enroll button
     * ---------------------------------------------------------
     */

    document.querySelectorAll(
        '.btn-enroll-quick'
    ).forEach(function (button) {

        button.addEventListener(
            'click',
            function () {

                const id =
                    this.getAttribute('data-id');

                const prog =
                    this.getAttribute('data-prog');

                const strand =
                    this.getAttribute('data-strand');


                studentSelect.value = id;

                if (prog) {
                    programSelect.value = prog;
                }

                filterAcademicOptions();

                if (
                    strand &&
                    strand !== '0'
                ) {
                    strandSelect.value = strand;
                }

                updateStudentPreview();

            }
        );

    });


    /*
     * ---------------------------------------------------------
     * Add enrollment button
     * ---------------------------------------------------------
     */

    const openButton =
        document.getElementById('btnOpenEnroll');

    if (openButton) {

        openButton.addEventListener(
            'click',
            function () {

                document.getElementById(
                    'enrollmentForm'
                ).reset();

                studentPreview.classList.remove(
                    'show'
                );

                filterAcademicOptions();

            }
        );

    }


    /*
     * ---------------------------------------------------------
     * Modal cleanup
     * ---------------------------------------------------------
     */

    const modal =
        document.getElementById('modalEnroll');

    if (modal) {

        modal.addEventListener(
            'hidden.bs.modal',
            function () {

                document.getElementById(
                    'enrollmentForm'
                ).reset();

                studentPreview.classList.remove(
                    'show'
                );

                filterAcademicOptions();

            }
        );

    }

});
</script>


<?php include '../includes/footer.php'; ?>
