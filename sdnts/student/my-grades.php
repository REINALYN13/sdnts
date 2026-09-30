<?php
require_once '../includes/config.php';
requireRole([ROLE_STUDENT]);
$pageTitle = 'My Grades';
$db = getDB();
$ay = getActiveAY();
$studentID = $_SESSION['studentID'];
$passingGrade = getSetting('passing_grade') ?: 75;

$grades = $db->prepare("SELECT g.*,sub.subjectCode,sub.subjectName FROM grades g JOIN subjects sub ON g.subjectID=sub.subjectID WHERE g.studentID=? AND g.ayID=? AND g.dateDeleted IS NULL ORDER BY sub.subjectName");
$grades->execute([$studentID,$ay['ayID']]); $gradeList = $grades->fetchAll();

$gwa = $db->prepare("SELECT ROUND(AVG(average),2) FROM grades WHERE studentID=? AND ayID=? AND average IS NOT NULL AND dateDeleted IS NULL");
$gwa->execute([$studentID,$ay['ayID']]); $gwaVal = $gwa->fetchColumn();

include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="fas fa-star-half-alt me-2 text-primary"></i>My Grades — SY <?= clean($ay['academicYear']) ?></h4>
  <a href="<?= SITE_URL ?>/reports/report-grades.php?studentID=<?= $studentID ?>" class="btn btn-outline-success" target="_blank"><i class="fas fa-print me-1"></i>Print Report Card</a>
</div>
<?php if ($gwaVal): ?>
<div class="alert alert-info d-flex align-items-center gap-3 mb-4">
  <div class="h2 mb-0 text-primary"><?= number_format($gwaVal,2) ?></div>
  <div><strong>My General Weighted Average (GWA)</strong><br><span class="badge bg-<?= $gwaVal>=$passingGrade?'success':'danger' ?> fs-6"><?= $gwaVal>=$passingGrade?'PASSED':'FAILED' ?></span></div>
</div>
<?php endif; ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered mb-0">
        <thead class="table-primary text-center"><tr><th class="text-start">Subject</th><th>1st Grading</th><th>2nd Grading</th><th>3rd Grading</th><th>4th Grading</th><th>Average</th><th>Remarks</th></tr></thead>
        <tbody>
          <?php foreach($gradeList as $g): ?>
          <tr>
            <td><?= clean($g['subjectCode'].' - '.$g['subjectName']) ?></td>
            <?php foreach(['firstGrading','secondGrading','thirdGrading','fourthGrading'] as $col): ?>
            <td class="text-center"><?= $g[$col]!==null?number_format($g[$col],2):'<span class="text-muted">-</span>' ?></td>
            <?php endforeach; ?>
            <td class="text-center fw-bold"><?= $g['average']!==null?'<span class="badge bg-'.($g['average']>=$passingGrade?'success':'danger').' fs-6">'.number_format($g['average'],2).'</span>':'<span class="text-muted">-</span>' ?></td>
            <td class="text-center"><?= $g['remarks']?'<span class="badge bg-'.($g['remarks']==='Passed'?'success':'danger').'">'.clean($g['remarks']).'</span>':'' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($gradeList)): ?><tr><td colspan="7" class="text-center text-muted py-3">No grades recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
