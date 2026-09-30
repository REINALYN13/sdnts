<?php
require_once '../includes/config.php';
requireRole([ROLE_STUDENT]);
$pageTitle = 'My Ranking';
$db = getDB();
$ay = getActiveAY();
$studentID = $_SESSION['studentID'];

$reg = $db->prepare("SELECT r.*,sec.sectionName FROM registration r JOIN sections sec ON r.sectionID=sec.sectionID WHERE r.studentID=? AND r.ayID=? AND r.status='enrolled' LIMIT 1");
$reg->execute([$studentID,$ay['ayID']]); $myReg = $reg->fetch();

$myGwa = null; $myRank = null; $classRanking = [];
if ($myReg) {
    $gwaQ = $db->prepare("SELECT ROUND(AVG(average),2) FROM grades WHERE studentID=? AND ayID=? AND average IS NOT NULL AND dateDeleted IS NULL");
    $gwaQ->execute([$studentID,$ay['ayID']]); $myGwa = $gwaQ->fetchColumn();

    $rankQ = $db->prepare("SELECT s.studentID,s.firstName,s.lastName,ROUND(AVG(g.average),2) as gwa FROM registration r JOIN students s ON r.studentID=s.studentID LEFT JOIN grades g ON g.studentID=s.studentID AND g.ayID=r.ayID AND g.dateDeleted IS NULL WHERE r.sectionID=? AND r.ayID=? AND r.status='enrolled' AND r.dateDeleted IS NULL GROUP BY s.studentID ORDER BY gwa DESC,s.lastName ASC");
    $rankQ->execute([$myReg['sectionID'],$ay['ayID']]); $classRanking = $rankQ->fetchAll();

    foreach ($classRanking as $i => $r) { if ($r['studentID'] == $studentID) { $myRank = $i+1; break; } }
}

$honorWith = getSetting('honor_with') ?: 90;
$honorWithHigh = getSetting('honor_with_high') ?: 95;
$honorWithHighest = getSetting('honor_with_highest') ?: 98;
$myHonor = null;
if ($myGwa >= $honorWithHighest) $myHonor = ['With Highest Honor','success'];
elseif ($myGwa >= $honorWithHigh) $myHonor = ['With High Honor','primary'];
elseif ($myGwa >= $honorWith) $myHonor = ['With Honor','info'];

include '../includes/header.php';
?>
<h4 class="mb-4"><i class="fas fa-trophy me-2 text-warning"></i>My Ranking — SY <?= clean($ay['academicYear']) ?></h4>
<?php if ($myReg && $myGwa): ?>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card border-0 shadow-sm text-center py-3"><div class="h2 text-warning mb-0">#<?= $myRank ?></div><div class="text-muted">My Rank</div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm text-center py-3"><div class="h2 text-primary mb-0"><?= number_format($myGwa,2) ?></div><div class="text-muted">My GWA</div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm text-center py-3"><div class="h2 text-info mb-0"><?= count($classRanking) ?></div><div class="text-muted">Classmates</div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm text-center py-3">
    <?php if($myHonor): ?><div class="h5 text-<?= $myHonor[1] ?> mb-0"><i class="fas fa-star"></i></div><div class="<?= 'text-'.$myHonor[1] ?> fw-bold"><?= $myHonor[0] ?></div><?php else: ?><div class="text-muted">No Honor Yet</div><?php endif; ?>
  </div></div>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white"><h6 class="mb-0">Section Ranking — <?= clean($myReg['sectionName']) ?></h6></div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th class="text-center">Rank</th><th>Name</th><th class="text-center">GWA</th></tr></thead>
      <tbody>
        <?php foreach($classRanking as $i=>$r): $isMe=$r['studentID']==$studentID; ?>
        <tr class="<?= $isMe?'table-warning':'' ?>">
          <td class="text-center">
            <?php if($i===0): ?><i class="fas fa-trophy text-warning"></i><?php elseif($i===1): ?><i class="fas fa-medal text-secondary"></i><?php elseif($i===2): ?><i class="fas fa-medal text-danger"></i><?php else: ?><?= $i+1 ?><?php endif; ?>
          </td>
          <td><?= clean($r['lastName'].', '.$r['firstName']) ?><?= $isMe?' <span class="badge bg-warning text-dark">You</span>':'' ?></td>
          <td class="text-center"><span class="badge bg-primary"><?= $r['gwa']!==null?number_format($r['gwa'],2):'N/A' ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>You are not yet enrolled or grades have not been posted yet.</div>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
