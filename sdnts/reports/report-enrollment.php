<?php
require_once '../includes/config.php';
requireLogin();

$pageTitle = 'Enrollment Report';

$db = getDB();
$ay = getActiveAY();
$schoolName = getSetting('school_name');

$enrollments = $db->prepare("
    SELECT
        r.*,
        s.firstName,
        s.lastName,
        s.studentNo,
        s.lrn,
        s.isMale,
        sec.sectionName,
        p.progName,
        y.yearName,
        st.strandName
    FROM registration r
    JOIN students s ON r.studentID = s.studentID
    JOIN sections sec ON r.sectionID = sec.sectionID
    JOIN programs p ON r.progID = p.progID
    JOIN year y ON r.yearID = y.yearID
    LEFT JOIN strands st ON r.strandID = st.strandID
    WHERE r.ayID = ?
      AND r.dateDeleted IS NULL
    ORDER BY
        y.yearName,
        p.progName,
        sec.sectionName,
        s.lastName,
        s.firstName
");

$enrollments->execute([$ay['ayID']]);
$list = $enrollments->fetchAll();

/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/
$total = count($list);

$enrolled = count(array_filter(
    $list,
    fn($r) => $r['status'] === 'enrolled'
));

$draft = count(array_filter(
    $list,
    fn($r) => $r['status'] === 'draft'
));

$dropped = count(array_filter(
    $list,
    fn($r) => $r['status'] === 'dropped'
));

$graduated = count(array_filter(
    $list,
    fn($r) => $r['status'] === 'graduated'
));

$male = count(array_filter(
    $list,
    fn($r) => (int)$r['isMale'] === 1
));

$female = $total - $male;

$academicYear = $ay['academicYear'] ?? 'Active Academic Year';
$printedDate = date('F d, Y');
$printedTime = date('h:i A');
?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
    Enrollment Report - <?= clean($schoolName) ?>
</title>

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

/* =========================================================
   SCREEN CONTROLS
========================================================= */

.no-print {
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.report-label {
    font-size: 14px;
    font-weight: 700;
}

.report-subtitle {
    color: #777;
    font-size: 12px;
    margin-top: 2px;
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

/* =========================================================
   REPORT HEADER
========================================================= */

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
    color: #1f4e79;
    margin-bottom: 3px;
}

.report-title {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .7px;
    margin: 3px 0;
}

.academic-year {
    font-size: 10px;
    color: #555;
    font-weight: 600;
    margin-top: 4px;
}

/* =========================================================
   SUMMARY CARDS
========================================================= */

.summary {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 6px;
    margin: 10px 0 12px;
}

.summary-box {
    border: 1px solid #cfd6dd;
    background: #f8f9fa;
    border-radius: 4px;
    padding: 7px;
    text-align: center;
}

.summary-label {
    display: block;
    font-size: 7px;
    color: #666;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: .3px;
}

.summary-number {
    display: block;
    font-size: 14px;
    font-weight: 700;
    margin-top: 2px;
}

.summary-total .summary-number {
    color: #1f4e79;
}

.summary-enrolled .summary-number {
    color: #198754;
}

.summary-draft .summary-number {
    color: #b58105;
}

.summary-dropped .summary-number {
    color: #dc3545;
}

.summary-male .summary-number {
    color: #0d6efd;
}

.summary-female .summary-number {
    color: #c2185b;
}

/* =========================================================
   REPORT TABLE
========================================================= */

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

.student-no,
.lrn {
    font-family: "Courier New", monospace;
    white-space: nowrap;
}

.student-name {
    font-weight: 600;
}

.program {
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* Column sizing */

.col-number {
    width: 3%;
}

.col-student-no {
    width: 9%;
}

.col-name {
    width: 16%;
}

.col-lrn {
    width: 10%;
}

.col-gender {
    width: 6%;
}

.col-year {
    width: 10%;
}

.col-section {
    width: 13%;
}

.col-program {
    width: 20%;
}

.col-status {
    width: 9%;
}

/* =========================================================
   STATUS BADGES
========================================================= */

.status {
    display: inline-block;
    min-width: 52px;
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

.status-draft {
    background: #fff3cd;
    color: #664d03;
    border: 1px solid #ffecb5;
}

.status-dropped {
    background: #f8d7da;
    color: #842029;
    border: 1px solid #f1aeb5;
}

.status-graduated {
    background: #cff4fc;
    color: #055160;
    border: 1px solid #9eeaf9;
}

.status-unknown {
    background: #e2e3e5;
    color: #41464b;
}

/* =========================================================
   REPORT FOOTER
========================================================= */

.document-footer {
    margin-top: 10px;
    padding-top: 7px;
    border-top: 1px solid #aaa;

    display: flex;
    justify-content: space-between;

    font-size: 7.5px;
    color: #666;
}

.footer-left strong {
    color: #333;
}

/* =========================================================
   CERTIFICATION
========================================================= */

.certification {
    margin-top: 28px;
    display: flex;
    justify-content: flex-end;
}

.signature {
    width: 220px;
    text-align: center;
    font-size: 8px;
}

.signature-space {
    height: 24px;
}

.signature-line {
    border-top: 1px solid #333;
    margin-bottom: 3px;
}

/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    text-align: center;
    padding: 20px;
    color: #777;
    font-style: italic;
}

/* =========================================================
   PRINT
========================================================= */

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

    .summary-box,
    .status {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .status-enrolled {
        background: #d1e7dd !important;
        color: #0f5132 !important;
    }

    .status-draft {
        background: #fff3cd !important;
        color: #664d03 !important;
    }

    .status-dropped {
        background: #f8d7da !important;
        color: #842029 !important;
    }

    .status-graduated {
        background: #cff4fc !important;
        color: #055160 !important;
    }

    a {
        color: inherit;
        text-decoration: none;
    }
}

/* =========================================================
   RESPONSIVE SCREEN VIEW
========================================================= */

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

<!-- =========================================================
     SCREEN-ONLY HEADER
========================================================= -->

<div class="no-print">

<div>
    <div class="report-label">
        Enrollment Report
    </div>

    <div class="report-subtitle">
        Academic Year: <?= clean($academicYear) ?>
    </div>
</div>

<div class="print-actions">

    <button
        type="button"
        class="btn btn-back"
        onclick="history.back()">
         Back
    </button>

    <button
        type="button"
        class="btn btn-print"
        onclick="window.print()">
        🖨 Print Report
    </button>

</div>

</div>

<div class="document">

<!-- =====================================================
     SCHOOL HEADER
====================================================== -->

<div class="school-header">

    <div class="school-name">
        <?= clean($schoolName) ?>
    </div>

    <div class="report-title">
        ENROLLMENT REPORT
    </div>

    <div class="academic-year">
        Academic Year: <?= clean($academicYear) ?>
    </div>

</div>


<!-- =====================================================
     SUMMARY
====================================================== -->

<div class="summary">

    <div class="summary-box summary-total">
        <span class="summary-label">
            Total
        </span>

        <span class="summary-number">
            <?= number_format($total) ?>
        </span>
    </div>


    <div class="summary-box summary-enrolled">
        <span class="summary-label">
            Enrolled
        </span>

        <span class="summary-number">
            <?= number_format($enrolled) ?>
        </span>
    </div>


    <div class="summary-box summary-draft">
        <span class="summary-label">
            Draft
        </span>

        <span class="summary-number">
            <?= number_format($draft) ?>
        </span>
    </div>


    <div class="summary-box summary-dropped">
        <span class="summary-label">
            Dropped
        </span>

        <span class="summary-number">
            <?= number_format($dropped) ?>
        </span>
    </div>


    <div class="summary-box summary-male">
        <span class="summary-label">
            Male
        </span>

        <span class="summary-number">
            <?= number_format($male) ?>
        </span>
    </div>


    <div class="summary-box summary-female">
        <span class="summary-label">
            Female
        </span>

        <span class="summary-number">
            <?= number_format($female) ?>
        </span>
    </div>

</div>


<!-- =====================================================
     TABLE
====================================================== -->

<div class="table-wrapper">

    <table>

        <thead>

            <tr>

                <th class="col-number">
                    #
                </th>

                <th class="col-student-no">
                    Student No.
                </th>

                <th class="col-name text-left">
                    Student Name
                </th>

                <th class="col-lrn">
                    LRN
                </th>

                <th class="col-gender">
                    Gender
                </th>

                <th class="col-year text-left">
                    Year Level
                </th>

                <th class="col-section text-left">
                    Section
                </th>

                <th class="col-program text-left">
                    Program / Strand
                </th>

                <th class="col-status">
                    Status
                </th>

            </tr>

        </thead>


        <tbody>

        <?php if ($list): ?>

            <?php $i = 1; ?>

            <?php foreach ($list as $e): ?>

                <?php

                $statusClass = match ($e['status']) {
                    'enrolled'  => 'status-enrolled',
                    'draft'     => 'status-draft',
                    'dropped'   => 'status-dropped',
                    'graduated' => 'status-graduated',
                    default     => 'status-unknown'
                };

                $statusLabel = ucfirst($e['status']);

                ?>

                <tr>

                    <td class="text-center">
                        <?= $i++ ?>
                    </td>


                    <td class="student-no text-center">
                        <?= clean($e['studentNo'] ?? '-') ?>
                    </td>


                    <td class="student-name text-left">
                        <?= clean(
                            $e['lastName'] . ', ' . $e['firstName']
                        ) ?>
                    </td>


                    <td class="lrn text-center">
                        <?= clean($e['lrn'] ?? '-') ?>
                    </td>


                    <td class="text-center">
                        <?= $e['isMale'] ? 'M' : 'F' ?>
                    </td>


                    <td class="text-left">
                        <?= clean($e['yearName']) ?>
                    </td>


                    <td class="text-left">
                        <?= clean($e['sectionName']) ?>
                    </td>


                    <td class="text-left program">

                        <?= clean($e['progName']) ?>

                        <?php if (!empty($e['strandName'])): ?>

                            <br>

                            <span style="color:#666;">
                                <?= clean($e['strandName']) ?>
                            </span>

                        <?php endif; ?>

                    </td>


                    <td class="text-center">

                        <span class="status <?= $statusClass ?>">
                            <?= clean($statusLabel) ?>
                        </span>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>

                <td
                    colspan="9"
                    class="empty-state">

                    No enrollment records found for
                    <?= clean($academicYear) ?>.

                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>


<!-- =====================================================
     FOOTER
====================================================== -->

<div class="document-footer">

    <div class="footer-left">

        Total Records:
        <strong><?= number_format($total) ?></strong>

        &nbsp; | &nbsp;

        Enrolled:
        <strong><?= number_format($enrolled) ?></strong>

    </div>


    <div>

        Printed:
        <?= clean($printedDate) ?>
        at
        <?= clean($printedTime) ?>

    </div>

</div>


<!-- =====================================================
     CERTIFICATION
====================================================== -->

<div class="certification">

    <div class="signature">

        <div class="signature-space"></div>

        <div class="signature-line"></div>

        <strong>
            Authorized School Personnel
        </strong>

        <div>
            Signature over Printed Name
        </div>

    </div>

</div>

</div>

<script>

window.addEventListener('beforeprint', function () {

    document.title =
        'Enrollment Report - <?= htmlspecialchars($schoolName, ENT_QUOTES) ?>';

});

</script>

</body>
</html>
