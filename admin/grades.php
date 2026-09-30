<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_TEACHER]);
$pageTitle = 'Manage Grades';

$db = getDB();
$ay = getActiveAY();
$csrf = generateCSRF();

$isTeacher  = $_SESSION['roleID'] === ROLE_TEACHER;
$teacherID  = $_SESSION['userID'];

/**
 * Returns true if the current teacher is actually assigned (via schedule)
 * to teach $subjectID in $sectionID for the active AY.
 * Admin/Principal are always allowed.
 */
function teacherOwnsClass(PDO $db, int $sectionID, int $subjectID, int $ayID, bool $isTeacher, int $teacherID): bool {
    if (!$isTeacher) return true; // admin/principal not restricted
    if (!$sectionID || !$subjectID) return false;
    $chk = $db->prepare("SELECT COUNT(*) FROM schedule WHERE sectionID=? AND subjectID=? AND ayID=? AND userID=? AND dateDeleted IS NULL");
    $chk->execute([$sectionID, $subjectID, $ayID, $teacherID]);
    return (bool)$chk->fetchColumn();
}

// Handle grade save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');

    $action = $_POST['action'] ?? '';

    if ($action === 'save_grades') {
        $postSectionID = (int)($_POST['sectionID'] ?? 0);
        $postSubjectID = (int)($_POST['subjectID'] ?? 0);

        // ARCHIVE/HISTORY: the per-row delete button submits through this
        // same form (a single shared <form> can only carry one hidden
        // "action" value), signaled instead by the presence of
        // delete_grade_id on the submitting button. Handle it first and
        // exit before touching the bulk grade-saving loop below.
        if (!empty($_POST['delete_grade_id'])) {
            if ($isTeacher) { $_SESSION['flash_error']='Not allowed.'; header('Location: grades.php'); exit; }
            $gid = (int)$_POST['delete_grade_id'];
            $db->prepare("UPDATE grades SET dateDeleted = NOW(), deletedBy = ? WHERE gradeID = ?")->execute([$_SESSION['userID'], $gid]);
            logActivity('Delete Grade', 'Grades', 'Archived grade record ID: ' . $gid . ' (restorable from Archive/History)');
            $_SESSION['flash_success'] = 'Grade record archived. It can be restored from Archive/History.';
            header('Location: grades.php?sectionID=' . $postSectionID . '&subjectID=' . $postSubjectID); exit;
        }

        // SECURITY: block teachers from saving grades for classes not assigned to them,
        // even if they craft the POST request manually.
        if (!teacherOwnsClass($db, $postSectionID, $postSubjectID, $ay['ayID'], $isTeacher, $teacherID)) {
            logActivity('Unauthorized Grade Save Attempt','Grades','Attempted section '.$postSectionID.' subject '.$postSubjectID);
            $_SESSION['flash_error'] = 'You are not assigned to this class.';
            header('Location: grades.php'); exit;
        }

        // SECURITY: only allow updating gradeIDs that belong to students actually
        // enrolled in that section AND to that exact subject, so a teacher can't
        // pass an arbitrary gradeID from another class in the POST body.
        $validIDs = $db->prepare("SELECT gradeID FROM grades g
            WHERE g.subjectID=? AND g.ayID=? AND g.dateDeleted IS NULL
            AND g.studentID IN (SELECT studentID FROM registration WHERE sectionID=? AND ayID=? AND status='enrolled' AND dateDeleted IS NULL)");
        $validIDs->execute([$postSubjectID, $ay['ayID'], $postSectionID, $ay['ayID']]);
        $allowedGradeIDs = array_flip($validIDs->fetchAll(PDO::FETCH_COLUMN));

        $grades = $_POST['grades'] ?? [];
        $saved = 0;
        foreach ($grades as $gradeID => $gData) {
            $gradeID = (int)$gradeID;
            if (!isset($allowedGradeIDs[$gradeID])) continue; // not part of this class, skip

            // Only accept clean numeric input (rejects "abc", "1e10", "60,5", etc.)
            // — is_numeric() alone would let scientific notation like "1e2" through.
            $rawQ1 = $gData['q1'] ?? '';
            $rawQ2 = $gData['q2'] ?? '';
            $rawQ3 = $gData['q3'] ?? '';
            $rawQ4 = $gData['q4'] ?? '';
            $isStrictNumber = fn($v) => $v === '' ? true : (bool)preg_match('/^\d{1,3}(\.\d{1,2})?$/', trim((string)$v));

            if (!$isStrictNumber($rawQ1) || !$isStrictNumber($rawQ2) || !$isStrictNumber($rawQ3) || !$isStrictNumber($rawQ4)) {
                $_SESSION['flash_error'] = 'Grades must be plain numbers (e.g. 85 or 85.50).';
                header('Location: grades.php'); exit;
            }

            $q1 = $rawQ1 !== '' ? (float)$rawQ1 : null;
            $q2 = $rawQ2 !== '' ? (float)$rawQ2 : null;
            $q3 = $rawQ3 !== '' ? (float)$rawQ3 : null;
            $q4 = $rawQ4 !== '' ? (float)$rawQ4 : null;

            // Validate range against the configured grade scale (Settings > Grading
            // Configuration). Defaults to 60-100 (this system's existing DepEd scale)
            // unless an Admin has changed grade_min/grade_max in system_settings.
            [$gradeMin, $gradeMax] = gradeRange();
            foreach ([$q1,$q2,$q3,$q4] as $qv) {
                if ($qv !== null && ($qv < $gradeMin || $qv > $gradeMax)) {
                    $_SESSION['flash_error'] = "Grades must be between $gradeMin and $gradeMax.";
                    header('Location: grades.php'); exit;
                }
            }

            $stmt = $db->prepare("UPDATE grades SET firstGrading=?,secondGrading=?,thirdGrading=?,fourthGrading=? WHERE gradeID=?");
            $stmt->execute([$q1,$q2,$q3,$q4,$gradeID]);
            $saved++;
        }
        logActivity('Save Grades','Grades','Saved '.$saved.' grade records');
        $_SESSION['flash_success'] = 'Grades saved successfully! Averages computed automatically.';
        header('Location: grades.php?sectionID=' . $postSectionID . '&subjectID=' . $postSubjectID); exit;
    }

    if ($action === 'post_grades') {
        if ($isTeacher) { $_SESSION['flash_error']='Not allowed.'; header('Location: grades.php'); exit; }
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

// Get sections — teachers only see sections they are actually scheduled to teach in
if ($isTeacher) {
    $sections = $db->prepare("SELECT DISTINCT sec.*,p.progName,y.yearName FROM sections sec
        JOIN programs p ON sec.progID=p.progID
        JOIN year y ON sec.yearID=y.yearID
        JOIN schedule sch ON sch.sectionID=sec.sectionID AND sch.dateDeleted IS NULL AND sch.ayID=sec.ayID
        WHERE sec.ayID=? AND sec.dateDeleted IS NULL AND sch.userID=?
        ORDER BY p.progName,sec.sectionName");
    $sections->execute([$ay['ayID'], $teacherID]);
} else {
    $sections = $db->prepare("SELECT sec.*,p.progName,y.yearName FROM sections sec JOIN programs p ON sec.progID=p.progID JOIN year y ON sec.yearID=y.yearID WHERE sec.ayID=? AND sec.dateDeleted IS NULL ORDER BY p.progName,sec.sectionName");
    $sections->execute([$ay['ayID']]);
}
$sectionsList = $sections->fetchAll();

// SECURITY: if a teacher lands here with a sectionID in the URL that isn't
// actually theirs, drop the filter instead of leaking another class's roster.
if ($isTeacher && $sectionID) {
    $ownsSection = array_filter($sectionsList, fn($s) => (int)$s['sectionID'] === $sectionID);
    if (!$ownsSection) { $sectionID = 0; $subjectID = 0; }
}

// Get subjects for selected section — teachers only see their own assigned subject(s) in that section
$subjectsList = [];
if ($sectionID) {
    if ($isTeacher) {
        $subjs = $db->prepare("SELECT DISTINCT sub.*, sch.schedID FROM schedule sch JOIN subjects sub ON sch.subjectID=sub.subjectID WHERE sch.sectionID=? AND sch.ayID=? AND sch.dateDeleted IS NULL AND sch.userID=? ORDER BY sub.subjectName");
        $subjs->execute([$sectionID, $ay['ayID'], $teacherID]);
    } else {
        $subjs = $db->prepare("SELECT DISTINCT sub.*, sch.schedID FROM schedule sch JOIN subjects sub ON sch.subjectID=sub.subjectID WHERE sch.sectionID=? AND sch.ayID=? AND sch.dateDeleted IS NULL ORDER BY sub.subjectName");
        $subjs->execute([$sectionID, $ay['ayID']]);
    }
    $subjectsList = $subjs->fetchAll();
}

// SECURITY: same guard for subjectID in the URL
if ($isTeacher && $subjectID) {
    $ownsSubject = array_filter($subjectsList, fn($s) => (int)$s['subjectID'] === $subjectID);
    if (!$ownsSubject) { $subjectID = 0; }
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
[$gradeMin, $gradeMax] = gradeRange();

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
        <?php if ($isTeacher && empty($sectionsList)): ?>
        <small class="text-danger">You have no sections assigned to you yet. Contact the admin to be added to a schedule.</small>
        <?php endif; ?>
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
                       value="<?= $g[$qcol] ?? '' ?>" min="<?= $gradeMin ?>" max="<?= $gradeMax ?>" step="0.01"
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
                <?php if ($_SESSION['roleID'] !== ROLE_TEACHER): ?>
                <button type="submit" name="delete_grade_id" value="<?= $g['gradeID'] ?>"
                        class="btn btn-sm btn-outline-danger ms-1" title="Archive this grade record"
                        onclick="return confirm('Archive this grade record? It can be restored later from Archive/History.')">
                  <i class="fas fa-box-archive"></i>
                </button>
                <?php endif; ?>
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
