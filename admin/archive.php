<?php
require_once '../includes/config.php';
// Archiving/deletion of master academic data is an administrative concern.
// Principal may VIEW the archive (oversight); only Admin may RESTORE.
requireRole([ROLE_ADMIN, ROLE_PRINCIPAL]);
$pageTitle = 'Archive / History';
$db = getDB();
$ay = getActiveAY();
$csrf = generateCSRF();
$isAdmin = $_SESSION['roleID'] === ROLE_ADMIN;

// ---------------------------------------------------------------
// Whitelisted table/primary-key map. A "type" key (never the raw
// table name) comes from the restore form, so this list is the only
// thing that decides which table/column gets touched — this is what
// keeps the restore action safe from SQL injection via table name.
// ---------------------------------------------------------------
$archiveTypes = [
    'student'      => ['table' => 'students',     'pk' => 'studentID',  'label' => 'Student',              'tracksWho' => true],
    'registration' => ['table' => 'registration',  'pk' => 'regID',      'label' => 'Enrollment Record',    'tracksWho' => true],
    'schedule'     => ['table' => 'schedule',      'pk' => 'schedID',    'label' => 'Teacher Assignment',   'tracksWho' => true],
    'grade'        => ['table' => 'grades',        'pk' => 'gradeID',    'label' => 'Grade Record',         'tracksWho' => true],
    'user'         => ['table' => 'users',         'pk' => 'userID',    'label' => 'User Account',         'tracksWho' => true],
    'section'      => ['table' => 'sections',      'pk' => 'sectionID',  'label' => 'Section',              'tracksWho' => true],
    'subject'      => ['table' => 'subjects',      'pk' => 'subjectID',  'label' => 'Subject',              'tracksWho' => false],
    'program'      => ['table' => 'programs',      'pk' => 'progID',     'label' => 'Program',              'tracksWho' => false],
    'strand'       => ['table' => 'strands',       'pk' => 'strandID',   'label' => 'Strand',               'tracksWho' => false],
    'yearlevel'    => ['table' => 'year',          'pk' => 'yearID',     'label' => 'Year Level',           'tracksWho' => false],
    'academicyear' => ['table' => 'academicYear',  'pk' => 'ayID',       'label' => 'Academic Year',         'tracksWho' => false],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');

    if (!$isAdmin) {
        denyAccess('archive.php', 'Only an Administrator may restore archived records.');
    }

    $action = $_POST['action'] ?? '';
    $type   = $_POST['type'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'restore' && isset($archiveTypes[$type]) && $id) {
        $meta = $archiveTypes[$type];
        $table = $meta['table'];
        $pk = $meta['pk'];

        // Whitelisted, never interpolated from user input beyond the
        // vetted $table/$pk strings above.
        if ($meta['tracksWho']) {
            $db->prepare("UPDATE `$table` SET dateDeleted = NULL, deletedBy = NULL WHERE `$pk` = ?")->execute([$id]);
        } else {
            $db->prepare("UPDATE `$table` SET dateDeleted = NULL WHERE `$pk` = ?")->execute([$id]);
        }

        logActivity('Restore Record', 'Archive', 'Restored ' . $meta['label'] . ' (ID: ' . $id . ')');
        $_SESSION['flash_success'] = $meta['label'] . ' restored successfully.';
    }

    header('Location: archive.php?tab=' . urlencode($type ?: 'student'));
    exit;
}

$activeTab = $_GET['tab'] ?? 'student';

// ---------------------------------------------------------------
// Pull archived rows for each tab. Kept as simple, readable queries
// rather than one giant dynamic UNION, since each table's display
// columns differ.
// ---------------------------------------------------------------
$archivedStudents = $db->query("
    SELECT s.*, u.firstName AS delFirst, u.lastName AS delLast
    FROM students s
    LEFT JOIN users u ON u.userID = s.deletedBy
    WHERE s.dateDeleted IS NOT NULL
    ORDER BY s.dateDeleted DESC
")->fetchAll();

$archivedRegistrations = $db->query("
    SELECT r.*, s.firstName AS stuFirst, s.lastName AS stuLast, s.studentNo,
           sec.sectionName, a.academicYear, u.firstName AS delFirst, u.lastName AS delLast
    FROM registration r
    JOIN students s ON s.studentID = r.studentID
    JOIN sections sec ON sec.sectionID = r.sectionID
    JOIN academicYear a ON a.ayID = r.ayID
    LEFT JOIN users u ON u.userID = r.deletedBy
    WHERE r.dateDeleted IS NOT NULL
    ORDER BY r.dateDeleted DESC
")->fetchAll();

$archivedSchedules = $db->query("
    SELECT sch.*, sec.sectionName, sub.subjectName, sub.subjectCode,
           t.firstName AS teacherFirst, t.lastName AS teacherLast,
           u.firstName AS delFirst, u.lastName AS delLast
    FROM schedule sch
    JOIN sections sec ON sec.sectionID = sch.sectionID
    JOIN subjects sub ON sub.subjectID = sch.subjectID
    LEFT JOIN users t ON t.userID = sch.userID
    LEFT JOIN users u ON u.userID = sch.deletedBy
    WHERE sch.dateDeleted IS NOT NULL
    ORDER BY sch.dateDeleted DESC
")->fetchAll();

$archivedGrades = $db->query("
    SELECT g.*, s.firstName AS stuFirst, s.lastName AS stuLast, s.studentNo,
           sub.subjectName, sub.subjectCode, u.firstName AS delFirst, u.lastName AS delLast
    FROM grades g
    JOIN students s ON s.studentID = g.studentID
    JOIN subjects sub ON sub.subjectID = g.subjectID
    LEFT JOIN users u ON u.userID = g.deletedBy
    WHERE g.dateDeleted IS NOT NULL
    ORDER BY g.dateDeleted DESC
")->fetchAll();

$archivedUsers = $db->query("
    SELECT us.*, r.roleName, u.firstName AS delFirst, u.lastName AS delLast
    FROM users us
    JOIN roles r ON r.roleID = us.roleID
    LEFT JOIN users u ON u.userID = us.deletedBy
    WHERE us.dateDeleted IS NOT NULL
    ORDER BY us.dateDeleted DESC
")->fetchAll();

$archivedOther = [];
foreach (['section' => 'sections', 'subject' => 'subjects', 'program' => 'programs', 'strand' => 'strands', 'yearlevel' => 'year', 'academicyear' => 'academicYear'] as $typeKey => $table) {
    $nameCol = match($table) {
        'sections' => 'sectionName',
        'subjects' => 'subjectName',
        'programs' => 'progName',
        'strands' => 'strandName',
        'year' => 'yearName',
        'academicYear' => 'academicYear',
        default => null,
    };
    if (!$nameCol) continue;
    $pk = $archiveTypes[$typeKey]['pk'];
    $rows = $db->query("SELECT `$pk` AS id, `$nameCol` AS name, dateDeleted FROM `$table` WHERE dateDeleted IS NOT NULL ORDER BY dateDeleted DESC")->fetchAll();
    foreach ($rows as $r) {
        $archivedOther[] = ['type' => $typeKey, 'label' => $archiveTypes[$typeKey]['label'], 'id' => $r['id'], 'name' => $r['name'], 'dateDeleted' => $r['dateDeleted']];
    }
}
usort($archivedOther, fn($a,$b) => strtotime($b['dateDeleted']) <=> strtotime($a['dateDeleted']));

$counts = [
    'student' => count($archivedStudents),
    'registration' => count($archivedRegistrations),
    'schedule' => count($archivedSchedules),
    'grade' => count($archivedGrades),
    'user' => count($archivedUsers),
    'other' => count($archivedOther),
];

include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="mb-1"><i class="fas fa-box-archive me-2 text-primary"></i>Archive / History</h4>
  <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">Home</a></li><li class="breadcrumb-item active">Archive / History</li></ol></nav></div>
</div>

<?php if (!$isAdmin): ?>
<div class="alert alert-secondary"><i class="fas fa-eye me-2"></i>You have view-only access to the Archive. Restoring records requires an Administrator account.</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link <?= $activeTab==='student'?'active':'' ?>" data-bs-toggle="tab" href="#tabStudents">Students <span class="badge bg-secondary"><?= $counts['student'] ?></span></a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='registration'?'active':'' ?>" data-bs-toggle="tab" href="#tabRegistrations">Enrollment <span class="badge bg-secondary"><?= $counts['registration'] ?></span></a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='schedule'?'active':'' ?>" data-bs-toggle="tab" href="#tabSchedules">Teacher Assignments <span class="badge bg-secondary"><?= $counts['schedule'] ?></span></a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='grade'?'active':'' ?>" data-bs-toggle="tab" href="#tabGrades">Grades <span class="badge bg-secondary"><?= $counts['grade'] ?></span></a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='user'?'active':'' ?>" data-bs-toggle="tab" href="#tabUsers">User Accounts <span class="badge bg-secondary"><?= $counts['user'] ?></span></a></li>
  <li class="nav-item"><a class="nav-link <?= $activeTab==='other'?'active':'' ?>" data-bs-toggle="tab" href="#tabOther">Other Records <span class="badge bg-secondary"><?= $counts['other'] ?></span></a></li>
</ul>

<div class="tab-content">

  <div class="tab-pane fade <?= $activeTab==='student'?'show active':'' ?>" id="tabStudents">
    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Student</th><th>Student No</th><th>Archived Date</th><th>Archived By</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($archivedStudents as $s): ?>
          <tr>
            <td><?= clean($s['lastName'].', '.$s['firstName']) ?></td>
            <td><code><?= clean($s['studentNo']??'-') ?></code></td>
            <td><small><?= date('M d, Y H:i', strtotime($s['dateDeleted'])) ?></small></td>
            <td><?= $s['delFirst'] ? clean($s['delLast'].', '.$s['delFirst']) : '<span class="text-muted">-</span>' ?></td>
            <td>
              <?php if ($isAdmin): ?>
              <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="type" value="student"><input type="hidden" name="id" value="<?= $s['studentID'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Restore this student to the active list?')"><i class="fas fa-rotate-left me-1"></i>Restore</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archivedStudents)): ?><tr><td colspan="5" class="text-center text-muted py-3">No archived students.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></div></div>
  </div>

  <div class="tab-pane fade <?= $activeTab==='registration'?'show active':'' ?>" id="tabRegistrations">
    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Student</th><th>Section</th><th>SY</th><th>Status</th><th>Archived Date</th><th>Archived By</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($archivedRegistrations as $r): ?>
          <tr>
            <td><?= clean($r['stuLast'].', '.$r['stuFirst']) ?> <code class="small"><?= clean($r['studentNo']) ?></code></td>
            <td><?= clean($r['sectionName']) ?></td>
            <td><?= clean($r['academicYear']) ?></td>
            <td><span class="badge bg-secondary"><?= ucfirst($r['status']) ?></span></td>
            <td><small><?= date('M d, Y H:i', strtotime($r['dateDeleted'])) ?></small></td>
            <td><?= $r['delFirst'] ? clean($r['delLast'].', '.$r['delFirst']) : '<span class="text-muted">-</span>' ?></td>
            <td>
              <?php if ($isAdmin): ?>
              <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="type" value="registration"><input type="hidden" name="id" value="<?= $r['regID'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Restore this enrollment record?')"><i class="fas fa-rotate-left me-1"></i>Restore</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archivedRegistrations)): ?><tr><td colspan="7" class="text-center text-muted py-3">No archived enrollment records.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></div></div>
  </div>

  <div class="tab-pane fade <?= $activeTab==='schedule'?'show active':'' ?>" id="tabSchedules">
    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Teacher</th><th>Section</th><th>Subject</th><th>Archived Date</th><th>Archived By</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($archivedSchedules as $s): ?>
          <tr>
            <td><?= $s['teacherLast'] ? clean($s['teacherLast'].', '.$s['teacherFirst']) : '<span class="text-muted">TBA</span>' ?></td>
            <td><?= clean($s['sectionName']) ?></td>
            <td><?= clean($s['subjectCode'].' - '.$s['subjectName']) ?></td>
            <td><small><?= date('M d, Y H:i', strtotime($s['dateDeleted'])) ?></small></td>
            <td><?= $s['delFirst'] ? clean($s['delLast'].', '.$s['delFirst']) : '<span class="text-muted">-</span>' ?></td>
            <td>
              <?php if ($isAdmin): ?>
              <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="type" value="schedule"><input type="hidden" name="id" value="<?= $s['schedID'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Restore this teacher assignment?')"><i class="fas fa-rotate-left me-1"></i>Restore</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archivedSchedules)): ?><tr><td colspan="6" class="text-center text-muted py-3">No archived teacher assignments.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></div></div>
  </div>

  <div class="tab-pane fade <?= $activeTab==='grade'?'show active':'' ?>" id="tabGrades">
    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Student</th><th>Subject</th><th>Average</th><th>Archived Date</th><th>Archived By</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($archivedGrades as $g): ?>
          <tr>
            <td><?= clean($g['stuLast'].', '.$g['stuFirst']) ?> <code class="small"><?= clean($g['studentNo']) ?></code></td>
            <td><?= clean($g['subjectCode'].' - '.$g['subjectName']) ?></td>
            <td><?= $g['average']!==null?number_format($g['average'],2):'-' ?></td>
            <td><small><?= date('M d, Y H:i', strtotime($g['dateDeleted'])) ?></small></td>
            <td><?= $g['delFirst'] ? clean($g['delLast'].', '.$g['delFirst']) : '<span class="text-muted">-</span>' ?></td>
            <td>
              <?php if ($isAdmin): ?>
              <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="type" value="grade"><input type="hidden" name="id" value="<?= $g['gradeID'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Restore this grade record?')"><i class="fas fa-rotate-left me-1"></i>Restore</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archivedGrades)): ?><tr><td colspan="6" class="text-center text-muted py-3">No archived grade records.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></div></div>
  </div>

  <div class="tab-pane fade <?= $activeTab==='user'?'show active':'' ?>" id="tabUsers">
    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Name</th><th>Role</th><th>Email</th><th>Archived Date</th><th>Archived By</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($archivedUsers as $u): ?>
          <tr>
            <td><?= clean($u['lastName'].', '.$u['firstName']) ?></td>
            <td><span class="badge bg-primary"><?= clean($u['roleName']) ?></span></td>
            <td><?= clean($u['email']) ?></td>
            <td><small><?= date('M d, Y H:i', strtotime($u['dateDeleted'])) ?></small></td>
            <td><?= $u['delFirst'] ? clean($u['delLast'].', '.$u['delFirst']) : '<span class="text-muted">-</span>' ?></td>
            <td>
              <?php if ($isAdmin): ?>
              <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="type" value="user"><input type="hidden" name="id" value="<?= $u['userID'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Restore this user account?')"><i class="fas fa-rotate-left me-1"></i>Restore</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archivedUsers)): ?><tr><td colspan="6" class="text-center text-muted py-3">No archived user accounts.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></div></div>
  </div>

  <div class="tab-pane fade <?= $activeTab==='other'?'show active':'' ?>" id="tabOther">
    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Type</th><th>Name</th><th>Archived Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($archivedOther as $o): ?>
          <tr>
            <td><span class="badge bg-secondary"><?= clean($o['label']) ?></span></td>
            <td><?= clean($o['name']) ?></td>
            <td><small><?= date('M d, Y H:i', strtotime($o['dateDeleted'])) ?></small></td>
            <td>
              <?php if ($isAdmin): ?>
              <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="type" value="<?= clean($o['type']) ?>"><input type="hidden" name="id" value="<?= $o['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Restore this record?')"><i class="fas fa-rotate-left me-1"></i>Restore</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($archivedOther)): ?><tr><td colspan="4" class="text-center text-muted py-3">No other archived records.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></div></div>
  </div>

</div>
<?php include '../includes/footer.php'; ?>
