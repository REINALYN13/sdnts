<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_TEACHER]);
$pageTitle = 'Student Profile';

$db = getDB();
$ay = getActiveAY();
$studentID = (int)($_GET['id'] ?? 0);

if (!$studentID) { header('Location: students.php'); exit; }

$stmt = $db->prepare("SELECT s.*, p.progName, st.strandName FROM students s LEFT JOIN programs p ON s.progID=p.progID LEFT JOIN strands st ON s.strandID=st.strandID WHERE s.studentID=? AND s.dateDeleted IS NULL");
$stmt->execute([$studentID]);
$student = $stmt->fetch();
if (!$student) { header('Location: students.php?error=Student+not+found'); exit; }

// SECURITY: a teacher may only view students currently enrolled in a
// section that teacher actually teaches. Enforced here in the backend so
// manually editing ?id= in the URL cannot expose another teacher's student
// (this is the exact ID/URL manipulation scenario the school flagged).
if ($_SESSION['roleID'] === ROLE_TEACHER
    && !teacherOwnsStudent($db, $studentID, $ay['ayID'], (int)$_SESSION['roleID'], (int)$_SESSION['userID'])) {
    denyAccess('students.php', 'You may only view students in your own sections.');
}

// Enrollment history
$enrollHist = $db->prepare("SELECT r.*,sec.sectionName,p.progName,y.yearName,a.academicYear FROM registration r JOIN sections sec ON r.sectionID=sec.sectionID JOIN programs p ON r.progID=p.progID JOIN year y ON r.yearID=y.yearID JOIN academicYear a ON r.ayID=a.ayID WHERE r.studentID=? AND r.dateDeleted IS NULL ORDER BY r.dateRegistration DESC");
$enrollHist->execute([$studentID]);

// Current AY grades
$grades = $db->prepare("SELECT g.*, sub.subjectName, sub.subjectCode FROM grades g JOIN subjects sub ON g.subjectID=sub.subjectID WHERE g.studentID=? AND g.ayID=? AND g.dateDeleted IS NULL ORDER BY sub.subjectName");
$grades->execute([$studentID, $ay['ayID']]);
$gradeList = $grades->fetchAll();

// GWA
$gwaQ = $db->prepare("SELECT ROUND(AVG(average),2) as gwa FROM grades WHERE studentID=? AND ayID=? AND average IS NOT NULL AND dateDeleted IS NULL");
$gwaQ->execute([$studentID, $ay['ayID']]);
$gwa = $gwaQ->fetchColumn();

// Ranking
$rankQ = $db->prepare("SELECT r.*,sec.sectionName FROM ranking r JOIN sections sec ON r.sectionID=sec.sectionID WHERE r.studentID=? AND r.ayID=? ORDER BY r.dateCreated DESC LIMIT 1");
$rankQ->execute([$studentID, $ay['ayID']]);
$ranking = $rankQ->fetch();

$passingGrade = getSetting('passing_grade') ?: 75;

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-1"><i class="fas fa-user-graduate me-2 text-primary"></i>Student Profile</h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">Home</a></li>
      <li class="breadcrumb-item"><a href="students.php">Students</a></li>
      <li class="breadcrumb-item active"><?= clean($student['firstName'].' '.$student['lastName']) ?></li>
    </ol></nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= SITE_URL ?>/reports/report-grades.php?studentID=<?= $studentID ?>" class="btn btn-outline-success" target="_blank">
      <i class="fas fa-print me-1"></i>Print Report Card
    </a>
    <a href="students.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <!-- Student Info -->
  <div class="col-md-4">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body">
        <img src="<?= SITE_URL ?>/assets/img/uploads/<?= clean($student['pic']) ?>" 
             class="rounded-circle mb-3" width="100" height="100" style="object-fit:cover"
             onerror="this.src='<?= SITE_URL ?>/assets/img/default.png'">
        <h5 class="mb-1"><?= clean($student['firstName'].' '.($student['middleName']?substr($student['middleName'],0,1).'. ':'').$student['lastName'].' '.($student['extName']??'')) ?></h5>
        <p class="text-muted mb-2"><code><?= clean($student['studentNo']) ?></code></p>
        <?php if($student['lrn']): ?><p class="text-muted small mb-2">LRN: <?= clean($student['lrn']) ?></p><?php endif; ?>
        <div class="mb-2">
          <?= $student['isEnroll'] ? '<span class="badge bg-success">Enrolled</span>' : '<span class="badge bg-secondary">Not Enrolled</span>' ?>
          <?= $student['isMale'] ? '<span class="badge bg-primary">Male</span>' : '<span class="badge bg-pink">Female</span>' ?>
        </div>
        <?php if ($gwa): ?>
        <div class="mt-3">
          <div class="h3 text-primary mb-0"><?= number_format($gwa,2) ?></div>
          <small class="text-muted">GWA — SY <?= clean($ay['academicYear']) ?></small>
          <?php if ($ranking): ?>
          <div class="mt-1"><span class="badge bg-warning text-dark">Rank #<?= $ranking['rankName'] ?></span>
          <?php if($ranking['honor']): ?><br><span class="badge bg-success"><?= clean($ranking['honor']) ?></span><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card border-0 shadow-sm mt-3">
      <div class="card-header bg-white"><h6 class="mb-0">Personal Information</h6></div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item"><small class="text-muted">Birthday</small><br><?= clean($student['dateofBirth']) ?></li>
        <li class="list-group-item"><small class="text-muted">Program</small><br><?= clean($student['progName']??'-') ?></li>
        <li class="list-group-item"><small class="text-muted">Strand</small><br><?= clean($student['strandName']??'-') ?></li>
        <li class="list-group-item"><small class="text-muted">Contact</small><br><?= clean($student['contactNo']??'-') ?></li>
        <li class="list-group-item"><small class="text-muted">Guardian</small><br><?= clean($student['guardianName']??'-') ?></li>
        <li class="list-group-item"><small class="text-muted">Guardian Contact</small><br><?= clean($student['guardianContact']??'-') ?></li>
        <li class="list-group-item"><small class="text-muted">Address</small><br><?= clean($student['address']??'-') ?></li>
      </ul>
    </div>
  </div>

  <!-- Grades & History -->
  <div class="col-md-8">
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabGrades">Grades (SY <?= clean($ay['academicYear']) ?>)</a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabHistory">Enrollment History</a></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="tabGrades">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-bordered mb-0">
                <thead class="table-primary text-center">
                  <tr><th class="text-start">Subject</th><th>1st</th><th>2nd</th><th>3rd</th><th>4th</th><th>Average</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                  <?php foreach($gradeList as $g): ?>
                  <tr>
                    <td><?= clean($g['subjectCode'].' - '.$g['subjectName']) ?></td>
                    <?php foreach(['firstGrading','secondGrading','thirdGrading','fourthGrading'] as $col): ?>
                    <td class="text-center"><?= $g[$col] !== null ? number_format($g[$col],2) : '<span class="text-muted">-</span>' ?></td>
                    <?php endforeach; ?>
                    <td class="text-center fw-bold">
                      <?php if ($g['average'] !== null): ?>
                      <span class="badge bg-<?= $g['average'] >= $passingGrade ? 'success':'danger' ?>"><?= number_format($g['average'],2) ?></span>
                      <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                      <?php if($g['remarks']): ?>
                      <span class="badge bg-<?= $g['remarks']==='Passed'?'success':'danger' ?>"><?= clean($g['remarks']) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(empty($gradeList)): ?>
                  <tr><td colspan="7" class="text-center text-muted py-3">No grades recorded yet.</td></tr>
                  <?php endif; ?>
                </tbody>
                <?php if(!empty($gradeList) && $gwa): ?>
                <tfoot>
                  <tr class="table-info fw-bold">
                    <td colspan="5" class="text-end">General Weighted Average:</td>
                    <td class="text-center"><span class="badge bg-primary fs-6"><?= number_format($gwa,2) ?></span></td>
                    <td class="text-center"><span class="badge bg-<?= $gwa>=$passingGrade?'success':'danger' ?>"><?= $gwa>=$passingGrade?'Passed':'Failed' ?></span></td>
                  </tr>
                </tfoot>
                <?php endif; ?>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="tabHistory">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-0">
            <table class="table table-hover mb-0">
              <thead class="table-light"><tr><th>Reg Code</th><th>Academic Year</th><th>Section</th><th>Year Level</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                <?php while($eh = $enrollHist->fetch()): ?>
                <tr>
                  <td><code class="small"><?= clean($eh['regCode']) ?></code></td>
                  <td><?= clean($eh['academicYear']) ?></td>
                  <td><?= clean($eh['sectionName']) ?></td>
                  <td><?= clean($eh['yearName']) ?></td>
                  <td><span class="badge bg-<?= $eh['status']==='enrolled'?'success':($eh['status']==='dropped'?'danger':'warning') ?>"><?= ucfirst($eh['status']) ?></span></td>
                  <td><small><?= date('M d, Y', strtotime($eh['dateRegistration'])) ?></small></td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
