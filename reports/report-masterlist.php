<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN, ROLE_PRINCIPAL]);

$db = getDB();
$ay = getActiveAY();
$schoolName = getSetting('school_name');

$students = $db->query("
    SELECT 
        s.*,
        p.progName,
        st.strandName
    FROM students s
    LEFT JOIN programs p ON s.progID = p.progID
    LEFT JOIN strands st ON s.strandID = st.strandID
    WHERE s.dateDeleted IS NULL
    ORDER BY s.lastName, s.firstName, s.middleName
")->fetchAll();

$totalStudents = count($students);
$totalEnrolled = 0;
$totalNotEnrolled = 0;
$totalMale = 0;
$totalFemale = 0;

foreach ($students as $s) {
    if ($s['isEnroll']) {
        $totalEnrolled++;
    } else {
        $totalNotEnrolled++;
    }

    if ($s['isMale']) {
        $totalMale++;
    } else {
        $totalFemale++;
    }
}

$academicYear = $ay['ayName'] ?? ($ay['academicYear'] ?? 'Active Academic Year');
$printedDate = date('F d, Y');
$printedTime = date('h:i A');
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master List of Students - <?= clean($schoolName) ?></title>

<style>
    * {
        box-sizing: border-box;
    }

    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 9px;
        color: #222;
        background: #fff;
        margin: 0;
        padding: 20px;
    }

    /* =========================
       PRINT CONTROLS
    ========================== */
    .no-print {
        margin-bottom: 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }

    .print-actions {
        display: flex;
        gap: 8px;
    }

    .btn {
        border: 0;
        border-radius: 6px;
        padding: 9px 16px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-print {
        background: #0d6efd;
        color: #fff;
    }

    .btn-back {
        background: #6c757d;
        color: #fff;
    }

    .btn:hover {
        opacity: .9;
    }

    /* =========================
       DOCUMENT HEADER
    ========================== */
    .document {
        width: 100%;
    }

    .school-header {
        text-align: center;
        padding-bottom: 10px;
        border-bottom: 3px solid #1f4e79;
        margin-bottom: 10px;
    }

    .school-name {
        font-size: 18px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin: 0 0 3px;
        color: #1f4e79;
    }

    .document-title {
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        margin: 3px 0;
        letter-spacing: .8px;
    }

    .academic-year {
        font-size: 10px;
        font-weight: 600;
        color: #555;
        margin-top: 4px;
    }

    /* =========================
       SUMMARY
    ========================== */
    .summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 6px;
        margin: 10px 0;
    }

    .summary-box {
        border: 1px solid #cfd6dd;
        background: #f8f9fa;
        padding: 6px 8px;
        border-radius: 3px;
        text-align: center;
    }

    .summary-label {
        display: block;
        font-size: 7.5px;
        color: #666;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: .3px;
    }

    .summary-value {
        display: block;
        font-size: 12px;
        font-weight: 700;
        margin-top: 2px;
    }

    .summary-total .summary-value {
        color: #1f4e79;
    }

    .summary-enrolled .summary-value {
        color: #198754;
    }

    .summary-pending .summary-value {
        color: #dc3545;
    }

    .summary-gender .summary-value {
        color: #6f42c1;
    }

    /* =========================
       TABLE
    ========================== */
    .table-wrapper {
        width: 100%;
        overflow: visible;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    th,
    td {
        border: 1px solid #9da7b1;
        padding: 4px 5px;
        vertical-align: middle;
        line-height: 1.2;
    }

    th {
        background: #1f4e79;
        color: #fff;
        text-align: center;
        font-size: 8px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .2px;
    }

    td {
        font-size: 8px;
    }

    tbody tr:nth-child(even) {
        background: #f5f7fa;
    }

    tbody tr {
        page-break-inside: avoid;
    }

    .text-left {
        text-align: left;
    }

    .text-center {
        text-align: center;
    }

    .student-no {
        font-family: "Courier New", monospace;
        font-weight: 600;
        white-space: nowrap;
    }

    .lrn {
        font-family: "Courier New", monospace;
        white-space: nowrap;
    }

    .name {
        font-weight: 600;
    }

    .program,
    .strand {
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    /* Column widths */
    .col-number {
        width: 3%;
    }

    .col-student-no {
        width: 8%;
    }

    .col-last {
        width: 10%;
    }

    .col-first {
        width: 10%;
    }

    .col-middle {
        width: 9%;
    }

    .col-lrn {
        width: 9%;
    }

    .col-gender {
        width: 6%;
    }

    .col-birthday {
        width: 8%;
    }

    .col-program {
        width: 14%;
    }

    .col-strand {
        width: 13%;
    }

    .col-status {
        width: 10%;
    }

    /* =========================
       STATUS
    ========================== */
    .status {
        display: inline-block;
        padding: 2px 5px;
        border-radius: 10px;
        font-size: 7px;
        font-weight: 700;
        text-align: center;
        white-space: nowrap;
    }

    .status-enrolled {
        background: #d1e7dd;
        color: #0f5132;
        border: 1px solid #a3cfbb;
    }

    .status-not-enrolled {
        background: #f8d7da;
        color: #842029;
        border: 1px solid #f1aeb5;
    }

    /* =========================
       FOOTER
    ========================== */
    .document-footer {
        margin-top: 12px;
        padding-top: 7px;
        border-top: 1px solid #aaa;
        display: flex;
        justify-content: space-between;
        font-size: 7.5px;
        color: #666;
    }

    .signature-area {
        margin-top: 28px;
        display: flex;
        justify-content: flex-end;
    }

    .signature {
        width: 220px;
        text-align: center;
        font-size: 8px;
    }

    .signature-line {
        border-top: 1px solid #333;
        margin-bottom: 3px;
    }

    .empty-state {
        text-align: center;
        padding: 20px;
        color: #777;
        font-style: italic;
    }

    /* =========================
       PRINT
    ========================== */
    @media print {

        html,
        body {
            width: 100%;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .no-print {
            display: none !important;
        }

        .document {
            width: 100%;
        }

        .school-header {
            margin-top: 0;
        }

        th {
            background: #1f4e79 !important;
            color: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        tbody tr:nth-child(even) {
            background: #f5f7fa !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .status-enrolled {
            background: #d1e7dd !important;
            color: #0f5132 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .status-not-enrolled {
            background: #f8d7da !important;
            color: #842029 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .summary-box {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        a {
            color: inherit;
            text-decoration: none;
        }
    }

    @media screen and (max-width: 1000px) {
        body {
            padding: 10px;
            overflow-x: auto;
        }

        .document {
            min-width: 1000px;
        }
    }
</style>

</head>

<body>

<!-- =========================
     SCREEN-ONLY CONTROLS
========================== -->

<div class="no-print">
    <div>
        <strong style="font-size:14px;">Student Master List</strong>
        <div style="font-size:12px;color:#777;">
            <?= clean($academicYear) ?>
        </div>
    </div>

<div class="print-actions">
    <button type="button" class="btn btn-back" onclick="history.back()">
        ← Back
    </button>

    <button type="button" class="btn btn-print" onclick="window.print()">
        🖨 Print Master List
    </button>
</div>

</div>

<div class="document">

<!-- =========================
     HEADER
========================== -->
<div class="school-header">

    <div class="school-name">
        <?= clean($schoolName) ?>
    </div>

    <div class="document-title">
        MASTER LIST OF STUDENTS
    </div>

    <div class="academic-year">
        Academic Year: <?= clean($academicYear) ?>
    </div>

</div>

<!-- =========================
     SUMMARY
========================== -->
<div class="summary">

    <div class="summary-box summary-total">
        <span class="summary-label">Total Students</span>
        <span class="summary-value"><?= number_format($totalStudents) ?></span>
    </div>

    <div class="summary-box summary-enrolled">
        <span class="summary-label">Enrolled</span>
        <span class="summary-value"><?= number_format($totalEnrolled) ?></span>
    </div>

    <div class="summary-box summary-pending">
        <span class="summary-label">Not Enrolled</span>
        <span class="summary-value"><?= number_format($totalNotEnrolled) ?></span>
    </div>

    <div class="summary-box summary-gender">
        <span class="summary-label">Male / Female</span>
        <span class="summary-value">
            <?= number_format($totalMale) ?> / <?= number_format($totalFemale) ?>
        </span>
    </div>

</div>

<!-- =========================
     STUDENT TABLE
========================== -->
<div class="table-wrapper">

    <table>

        <thead>
            <tr>
                <th class="col-number">#</th>
                <th class="col-student-no">Student No.</th>
                <th class="col-last">Last Name</th>
                <th class="col-first">First Name</th>
                <th class="col-middle">Middle Name</th>
                <th class="col-lrn">LRN</th>
                <th class="col-gender">Gender</th>
                <th class="col-birthday">Birthday</th>
                <th class="col-program">Program</th>
                <th class="col-strand">Strand</th>
                <th class="col-status">Status</th>
            </tr>
        </thead>

        <tbody>

        <?php if ($students): ?>

            <?php $i = 1; ?>

            <?php foreach ($students as $s): ?>

                <tr>

                    <td class="text-center">
                        <?= $i++ ?>
                    </td>

                    <td class="student-no text-center">
                        <?= clean($s['studentNo'] ?? '-') ?>
                    </td>

                    <td class="text-left name">
                        <?= clean($s['lastName'] ?? '') ?>
                    </td>

                    <td class="text-left name">
                        <?= clean($s['firstName'] ?? '') ?>
                    </td>

                    <td class="text-left">
                        <?= clean($s['middleName'] ?? '') ?>
                    </td>

                    <td class="lrn text-center">
                        <?= clean($s['lrn'] ?? '-') ?>
                    </td>

                    <td class="text-center">
                        <?= $s['isMale'] ? 'Male' : 'Female' ?>
                    </td>

                    <td class="text-center">
                        <?= !empty($s['dateofBirth'])
                            ? date('M d, Y', strtotime($s['dateofBirth']))
                            : '-' ?>
                    </td>

                    <td class="text-left program">
                        <?= clean($s['progName'] ?? '-') ?>
                    </td>

                    <td class="text-left strand">
                        <?= clean($s['strandName'] ?? '-') ?>
                    </td>

                    <td class="text-center">

                        <?php if ($s['isEnroll']): ?>

                            <span class="status status-enrolled">
                                ENROLLED
                            </span>

                        <?php else: ?>

                            <span class="status status-not-enrolled">
                                NOT ENROLLED
                            </span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="11" class="empty-state">
                    No students found.
                </td>
            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>

<!-- =========================
     FOOTER
========================== -->
<div class="document-footer">

    <div>
        Total Records: <strong><?= number_format($totalStudents) ?></strong>
    </div>

    <div>
        Printed: <?= clean($printedDate) ?> at <?= clean($printedTime) ?>
    </div>

</div>

<!-- =========================
     CERTIFICATION
========================== -->
<div class="signature-area">

    <div class="signature">

        <div style="height:25px;"></div>

        <div class="signature-line"></div>

        <strong>Authorized School Personnel</strong>

        <div>
            Signature over Printed Name
        </div>

    </div>

</div>

</div>

<script>
    // Improve printing experience
    window.addEventListener('beforeprint', function () {
        document.title = 'Master List of Students - <?= htmlspecialchars($schoolName, ENT_QUOTES) ?>';
    });
</script>

</body>
</html>
