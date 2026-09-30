<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_TEACHER]);
$pageTitle = 'Manage Grades';

$db = getDB();
$ay = getActiveAY();
$csrf = generateCSRF();

// Handle grade save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_grades') {
        $grades = $_POST['grades'] ?? [];
        $saved = 0;
        foreach ($grades as $gradeID => $gData) {
            $gradeID = (int)$gradeID;
            $q1 = is_numeric($gData['q1']) ? (float)$gData['q1'] : null;
            $q2 = is_numeric($gData['q2']) ? (float)$gData['q2'] : null;
            $q3 = is_numeric($gData['q3']) ? (float)$gData['q3'] : null;
            $q4 = is_numeric($gData['q4']) ? (float)$gData['q4'] : null;

            // Validate range 60-100
            foreach ([$q1,$q2,$q3,$q4] as $qv) {
                if ($qv !== null && ($qv < 60 || $qv > 100)) {
                    $_SESSION['flash_error'] = 'Grades must be between 60 and 100.';
                    header('Location: grades.php'); exit;
                }
            }

            $stmt = $db->prepare("UPDATE grades SET firstGrading=?,secondGrading=?,thirdGrading=?,fourthGrading=? WHERE gradeID=?");
            $stmt->execute([$q1,$q2,$q3,$q4,$gradeID]);
            $saved++;
        }
        logActivity('Save Grades','Grades','Saved '.$saved.' grade records');
        $_SESSION['flash_success'] = 'Grades saved successfully! Averages computed automatically.';
        header('Location: grades.php?sectionID=' . ($_POST['sectionID'] ?? '') . '&subjectID=' . ($_POST['subjectID'] ?? '')); exit;
    }
    
    if ($action === 'post_grades') {
        $gradeIDs = explode(',', $_POST['gradeIDs'] ?? '');
        $period = $_POST['period'] ?? 'first';
        $col = $period . 'GradingIsPost';
        foreach ($gradeIDs as $gid) {
            $gid = (int)trim($gid);
            if ($gid) $db->prepare("UPDATE grades SET $col=1 WHERE gradeID=?")->execute([$gid]);
        }
        $_SESSION['flash_success'] = 'Grades posted for ' . ucfirst($period) . ' grading period.';
        header('Location: grades.php'); exit;
    }
}

// Filters
$sectionID = (int)($_GET['sectionID'] ?? 0);
$subjectID = (int)($_GET['subjectID'] ?? 0);

// Get sections
$sections = $db->prepare("SELECT sec.*,p.progName,y.yearName FROM sections sec JOIN programs p ON sec.progID=p.progID JOIN year y ON sec.yearID=y.yearID WHERE sec.ayID=? AND sec.dateDeleted IS NULL ORDER BY p.progName,sec.sectionName");
$sections->execute([$ay['ayID']]);
$sectionsList = $sections->fetchAll();

// Get subjects for selected section
$subjectsList = [];
if ($sectionID) {
    $subjs = $db->prepare("SELECT DISTINCT sub.*, sch.schedID FROM schedule sch JOIN subjects sub ON sch.subjectID=sub.subjectID WHERE sch.sectionID=? AND sch.ayID=? AND sch.dateDeleted IS NULL ORDER BY sub.subjectName");
    $subjs->execute([$sectionID, $ay['ayID']]);
    $subjectsList = $subjs->fetchAll();
}

// Get grade records
$gradeRecords = [];
if ($sectionID && $subjectID) {
    $gStmt = $db->prepare("SELECT g.*, s.firstName, s.lastName, s.studentNo, s.lrn FROM grades g 
        JOIN students s ON g.studentID = s.studentID 
        WHERE g.ayID=? AND g.subjectID=? 
        AND g.studentID IN (SELECT studentID FROM registration WHERE sectionID=? AND ayID=? AND status='enrolled' AND dateDeleted IS NULL)
        AND g.dateDeleted IS NULL
        ORDER BY s.lastName, s.firstName");
    $gStmt->execute([$ay['ayID'], $subjectID, $sectionID, $ay['ayID']]);
    $gradeRecords = $gStmt->fetchAll();
}

// Get section and subject names for display
$sectionInfo = null; $subjectInfo = null;
if ($sectionID) {
    $si = $db->prepare("SELECT sec.*,p.progName,y.yearName FROM sections sec JOIN programs p ON sec.progID=p.progID JOIN year y ON sec.yearID=y.yearID WHERE sec.sectionID=?");
    $si->execute([$sectionID]); $sectionInfo = $si->fetch();
}
if ($subjectID) {
    $sj = $db->prepare("SELECT * FROM subjects WHERE subjectID=?");
    $sj->execute([$subjectID]); $subjectInfo = $sj->fetch();
}

$passingGrade = getSetting('passing_grade') ?: 75;

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-1"><i class="fas fa-star-half-alt me-2 text-primary"></i>Grade Management</h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">Home</a></li>
      <li class="breadcrumb-item active">Grades</li>
    </ol></nav>
  </div>
</div>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3">
      <div class="col-md-5">
        <label class="form-label fw-semibold">Section</label>
        <select name="sectionID" class="form-select" onchange="this.form.submit()">
          <option value="">-- Select Section --</option>
          <?php foreach($sectionsList as $sec): ?>
          <option value="<?= $sec['sectionID'] ?>" <?= $sectionID==$sec['sectionID']?'selected':'' ?>>
            <?= clean($sec['sectionName'].' ('.$sec['yearName'].' - '.$sec['progName'].')') ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($sectionID): ?>
      <div class="col-md-5">
        <label class="form-label fw-semibold">Subject</label>
        <select name="subjectID" class="form-select" onchange="this.form.submit()">
          <option value="">-- Select Subject --</option>
          <?php foreach($subjectsList as $sub): ?>
          <option value="<?= $sub['subjectID'] ?>" <?= $subjectID==$sub['subjectID']?'selected':'' ?>>
            <?= clean($sub['subjectCode'].' - '.$sub['subjectName']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($sectionID && $subjectID && !empty($gradeRecords)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
    <div>
      <h6 class="mb-0"><i class="fas fa-table me-2"></i><?= clean($subjectInfo['subjectName'] ?? '') ?></h6>
      <small class="text-muted">Section: <?= clean($sectionInfo['sectionName'] ?? '') ?> &mdash; SY <?= clean($ay['academicYear']) ?></small>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= SITE_URL ?>/reports/report-grades.php?sectionID=<?= $sectionID ?>&subjectID=<?= $subjectID ?>" 
         class="btn btn-sm btn-outline-success" target="_blank">
        <i class="fas fa-print me-1"></i>Print
      </a>
    </div>
  </div>
  <div class="card-body p-0">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="save_grades">
      <input type="hidden" name="sectionID" value="<?= $sectionID ?>">
      <input type="hidden" name="subjectID" value="<?= $subjectID ?>">
      <div class="table-responsive">
        <table class="table table-bordered mb-0">
          <thead class="table-primary text-center">
            <tr>
              <th class="text-start" rowspan="2" style="width:40px">#</th>
              <th class="text-start" rowspan="2">Student</th>
              <th class="text-start" rowspan="2">Student No</th>
              <th colspan="4">Grading Periods</th>
              <th rowspan="2">Average</th>
              <th rowspan="2">Remarks</th>
            </tr>
            <tr>
              <th>1st</th><th>2nd</th><th>3rd</th><th>4th</th>
            </tr>
          </thead>
          <tbody>
            <?php $i=1; foreach($gradeRecords as $g):
              $avg = $g['average'];
              $passed = $avg !== null && $avg >= $passingGrade;
            ?>
            <tr>
              <td class="text-center"><?= $i++ ?></td>
              <td><?= clean($g['lastName'].', '.$g['firstName']) ?></td>
              <td><code class="small"><?= clean($g['studentNo']) ?></code></td>
              <?php foreach(['q1'=>'firstGrading','q2'=>'secondGrading','q3'=>'thirdGrading','q4'=>'fourthGrading'] as $qkey=>$qcol): ?>
              <td class="p-1">
                <input type="number" name="grades[<?= $g['gradeID'] ?>][<?= $qkey ?>]" 
                       class="form-control form-control-sm text-center grade-input" 
                       value="<?= $g[$qcol] ?? '' ?>" min="60" max="100" step="0.01"
                       style="width:70px;margin:auto"
                       <?= $_SESSION['roleID'] === ROLE_PRINCIPAL ? 'disabled' : '' ?>>
              </td>
              <?php endforeach; ?>
              <td class="text-center fw-bold">
                <?php if ($avg !== null): ?>
                <span class="badge bg-<?= $passed?'success':'danger' ?> fs-6"><?= number_format($avg,2) ?></span>
                <?php else: ?>
                <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($g['remarks']): ?>
                <span class="badge bg-<?= $g['remarks']==='Passed'?'success':'danger' ?>"><?= clean($g['remarks']) ?></span>
                <?php else: ?><span class="text-muted small">Pending</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-light">
              <td colspan="3" class="fw-bold text-end">Class Average:</td>
              <?php
              $q1s = array_filter(array_column($gradeRecords,'firstGrading'), fn($v)=>$v!==null);
              $q2s = array_filter(array_column($gradeRecords,'secondGrading'), fn($v)=>$v!==null);
              $q3s = array_filter(array_column($gradeRecords,'thirdGrading'), fn($v)=>$v!==null);
              $q4s = array_filter(array_column($gradeRecords,'fourthGrading'), fn($v)=>$v!==null);
              $avgs = array_filter(array_column($gradeRecords,'average'), fn($v)=>$v!==null);
              ?>
              <td class="text-center fw-bold"><?= count($q1s)?number_format(array_sum($q1s)/count($q1s),2):'-' ?></td>
              <td class="text-center fw-bold"><?= count($q2s)?number_format(array_sum($q2s)/count($q2s),2):'-' ?></td>
              <td class="text-center fw-bold"><?= count($q3s)?number_format(array_sum($q3s)/count($q3s),2):'-' ?></td>
              <td class="text-center fw-bold"><?= count($q4s)?number_format(array_sum($q4s)/count($q4s),2):'-' ?></td>
              <td class="text-center fw-bold text-primary"><?= count($avgs)?number_format(array_sum($avgs)/count($avgs),2):'-' ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php if ($_SESSION['roleID'] !== ROLE_PRINCIPAL): ?>
      <div class="p-3 d-flex justify-content-between align-items-center bg-light">
        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Grades auto-compute average. Passing grade: <?= $passingGrade ?></small>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Grades</button>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>
<?php elseif ($sectionID && $subjectID): ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No enrolled students found for this section/subject. Enroll students first.</div>
<?php elseif ($sectionID): ?>
<div class="alert alert-secondary"><i class="fas fa-arrow-up me-2"></i>Please select a subject to view grades.</div>
<?php else: ?>
<div class="alert alert-secondary"><i class="fas fa-arrow-up me-2"></i>Please select a section to start managing grades.</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
