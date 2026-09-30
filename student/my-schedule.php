<?php
require_once '../includes/config.php';
requireRole([ROLE_STUDENT]);
$pageTitle = 'My Schedule';
$db = getDB();
$ay = getActiveAY();
$studentID = $_SESSION['studentID'];

$reg = $db->prepare("SELECT r.*,sec.sectionName FROM registration r JOIN sections sec ON r.sectionID=sec.sectionID WHERE r.studentID=? AND r.ayID=? AND r.status='enrolled' LIMIT 1");
$reg->execute([$studentID,$ay['ayID']]); $myReg = $reg->fetch();

$subjects = [];
if ($myReg) {
    $subjQ = $db->prepare("SELECT g.*,sub.subjectCode,sub.subjectName,sub.units,sch.dayOfWeek,sch.timeStart,sch.timeEnd,sch.room,u.firstName as tFirst,u.lastName as tLast FROM grades g JOIN subjects sub ON g.subjectID=sub.subjectID LEFT JOIN schedule sch ON g.schedID=sch.schedID LEFT JOIN users u ON sch.userID=u.userID WHERE g.studentID=? AND g.ayID=? AND g.dateDeleted IS NULL ORDER BY sub.subjectName");
    $subjQ->execute([$studentID,$ay['ayID']]); $subjects = $subjQ->fetchAll();
}
include '../includes/header.php';
?>
<h4 class="mb-4"><i class="fas fa-clock me-2 text-primary"></i>My Schedule — SY <?= clean($ay['academicYear']) ?></h4>
<?php if ($myReg): ?>
<div class="alert alert-primary"><i class="fas fa-chalkboard me-2"></i>Section: <strong><?= clean($myReg['sectionName']) ?></strong></div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>Code</th><th>Subject</th><th>Units</th><th>Day</th><th>Time</th><th>Room</th><th>Teacher</th></tr></thead>
      <tbody>
        <?php foreach($subjects as $s): ?>
        <tr>
          <td><code><?= clean($s['subjectCode']) ?></code></td>
          <td><?= clean($s['subjectName']) ?></td>
          <td class="text-center"><?= $s['units'] ?></td>
          <td><?= clean($s['dayOfWeek']??'-') ?></td>
          <td><?= $s['timeStart']?date('h:i A',strtotime($s['timeStart'])).' - '.date('h:i A',strtotime($s['timeEnd'])):'-' ?></td>
          <td><?= clean($s['room']??'-') ?></td>
          <td><?= $s['tLast']?clean($s['tLast'].', '.$s['tFirst']):'TBA' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($subjects)): ?><tr><td colspan="7" class="text-center text-muted">No subjects yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?><div class="alert alert-warning">You are not yet enrolled for SY <?= clean($ay['academicYear']) ?>.</div><?php endif; ?>
<?php include '../includes/footer.php'; ?>
