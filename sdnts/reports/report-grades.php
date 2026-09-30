<?php
require_once '../includes/config.php';
requireLogin();
$pageTitle = 'Grade Report';
$db = getDB();
$ay = getActiveAY();

$studentID = (int)($_GET['studentID'] ?? 0);
$sectionID = (int)($_GET['sectionID'] ?? 0);
$subjectID = (int)($_GET['subjectID'] ?? 0);
$passingGrade = getSetting('passing_grade') ?: 75;
$schoolName = getSetting('school_name');

// Single student report card
if ($studentID) {
    $student = $db->prepare("SELECT s.*,p.progName,st.strandName FROM students s LEFT JOIN programs p ON s.progID=p.progID LEFT JOIN strands st ON s.strandID=st.strandID WHERE s.studentID=?")->execute([$studentID]) ? null : null;
    $stmt = $db->prepare("SELECT s.*,p.progName,st.strandName FROM students s LEFT JOIN programs p ON s.progID=p.progID LEFT JOIN strands st ON s.strandID=st.strandID WHERE s.studentID=?");
    $stmt->execute([$studentID]); $student = $stmt->fetch();
    
    $reg = $db->prepare("SELECT r.*,sec.sectionName,y.yearName FROM registration r JOIN sections sec ON r.sectionID=sec.sectionID JOIN year y ON r.yearID=y.yearID WHERE r.studentID=? AND r.ayID=? AND r.status='enrolled' LIMIT 1");
    $reg->execute([$studentID,$ay['ayID']]); $registration = $reg->fetch();
    
    $grades = $db->prepare("SELECT g.*,sub.subjectCode,sub.subjectName FROM grades g JOIN subjects sub ON g.subjectID=sub.subjectID WHERE g.studentID=? AND g.ayID=? AND g.dateDeleted IS NULL ORDER BY sub.subjectName");
    $grades->execute([$studentID,$ay['ayID']]); $gradeList = $grades->fetchAll();
    
    $gwa = $db->prepare("SELECT ROUND(AVG(average),2) FROM grades WHERE studentID=? AND ayID=? AND average IS NOT NULL AND dateDeleted IS NULL");
    $gwa->execute([$studentID,$ay['ayID']]); $gwaVal = $gwa->fetchColumn();
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Report Card</title>
<style>
body{font-family:Arial,sans-serif;font-size:12px;margin:20px}
.header{text-align:center;border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px}
.header h2{margin:0;font-size:16px} .header h3{margin:0;font-size:13px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;margin-bottom:15px}
.info-item{padding:3px 0} .info-item label{font-weight:bold;margin-right:5px}
table{width:100%;border-collapse:collapse;margin-bottom:15px}
th,td{border:1px solid #333;padding:5px 8px;text-align:center}
th{background:#f0f0f0;font-weight:bold} .text-left{text-align:left}
.passed{color:green;font-weight:bold} .failed{color:red;font-weight:bold}
.gwa-row{background:#e8f4f8;font-weight:bold;font-size:13px}
.signatures{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:30px}
.sig-box{text-align:center;border-top:1px solid #333;padding-top:5px}
@media print{body{margin:0}.no-print{display:none}}
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:15px"><button onclick="window.print()">🖨 Print</button> <button onclick="history.back()">← Back</button></div>
<div class="header">
  <p style="margin:0;font-size:11px">Republic of the Philippines · Department of Education</p>
  <h2><?= clean($schoolName) ?></h2>
  <p style="margin:0">Baloc, Sto. Domingo, Nueva Ecija</p>
  <h3>STUDENT REPORT CARD</h3>
  <p style="margin:0">School Year: <?= clean($ay['academicYear']) ?></p>
</div>
<div class="info-grid">
  <div class="info-item"><label>Name:</label><?= clean($student['lastName'].', '.$student['firstName'].' '.($student['middleName']??'')) ?></div>
  <div class="info-item"><label>Student No:</label><?= clean($student['studentNo']) ?></div>
  <div class="info-item"><label>LRN:</label><?= clean($student['lrn']??'-') ?></div>
  <div class="info-item"><label>Grade/Year:</label><?= clean($registration['yearName']??'-') ?></div>
  <div class="info-item"><label>Section:</label><?= clean($registration['sectionName']??'-') ?></div>
  <div class="info-item"><label>Program:</label><?= clean($student['progName']??'-') ?></div>
  <div class="info-item"><label>Strand:</label><?= clean($student['strandName']??'-') ?></div>
  <div class="info-item"><label>Date Printed:</label><?= date('F d, Y') ?></div>
</div>
<table>
  <thead><tr><th class="text-left">Subject</th><th>1st Grading</th><th>2nd Grading</th><th>3rd Grading</th><th>4th Grading</th><th>Final Average</th><th>Remarks</th></tr></thead>
  <tbody>
    <?php foreach($gradeList as $g): ?>
    <tr>
      <td class="text-left"><?= clean($g['subjectCode'].' - '.$g['subjectName']) ?></td>
      <td><?= $g['firstGrading']!==null?number_format($g['firstGrading'],2):'-' ?></td>
      <td><?= $g['secondGrading']!==null?number_format($g['secondGrading'],2):'-' ?></td>
      <td><?= $g['thirdGrading']!==null?number_format($g['thirdGrading'],2):'-' ?></td>
      <td><?= $g['fourthGrading']!==null?number_format($g['fourthGrading'],2):'-' ?></td>
      <td><?= $g['average']!==null?number_format($g['average'],2):'-' ?></td>
      <td class="<?= $g['remarks']==='Passed'?'passed':'failed' ?>"><?= clean($g['remarks']??'-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if($gwaVal): ?>
    <tr class="gwa-row"><td colspan="5" class="text-left">GENERAL WEIGHTED AVERAGE (GWA)</td><td><?= number_format($gwaVal,2) ?></td><td class="<?= $gwaVal>=$passingGrade?'passed':'failed' ?>"><?= $gwaVal>=$passingGrade?'PASSED':'FAILED' ?></td></tr>
    <?php endif; ?>
  </tbody>
</table>
<div class="signatures">
  <div class="sig-box"><br><br><?= clean($student['firstName'].' '.$student['lastName']) ?><br><small>Student</small></div>
  <div class="sig-box"><br><br>______________________<br><small>Class Adviser</small></div>
  <div class="sig-box"><br><br>______________________<br><small>School Principal</small></div>
</div>
</body></html>
<?php exit; }

// Section + Subject grade report
include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="fas fa-print me-2"></i>Grade Reports</h4>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <p class="text-muted">Select a student from the <a href="<?= SITE_URL ?>/admin/students.php">Students</a> page or use the print button in the <a href="<?= SITE_URL ?>/admin/grades.php">Grades</a> section.</p>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
