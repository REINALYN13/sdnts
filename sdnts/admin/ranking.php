<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_TEACHER]);
$pageTitle = 'Academic Ranking';

$db = getDB();
$ay = getActiveAY();
$csrf = generateCSRF();

// Compute ranking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $sectionID = (int)($_POST['sectionID'] ?? 0);
    $ayID = $ay['ayID'];
    
    if ($sectionID) {
        $db->prepare("CALL sp_compute_ranking(?,?)")->execute([$ayID, $sectionID]);
        logActivity('Compute Ranking','Ranking','Computed ranking for section '.$sectionID);
        $_SESSION['flash_success'] = 'Rankings computed successfully!';
    }
    header('Location: ranking.php?sectionID=' . $sectionID); exit;
}

$sectionID = (int)($_GET['sectionID'] ?? 0);

// Sections
$sections = $db->prepare("SELECT sec.*,p.progName,y.yearName FROM sections sec JOIN programs p ON sec.progID=p.progID JOIN year y ON sec.yearID=y.yearID WHERE sec.ayID=? AND sec.dateDeleted IS NULL ORDER BY p.progName,sec.sectionName");
$sections->execute([$ay['ayID']]);
$sectionsList = $sections->fetchAll();

// Rankings for selected section
$rankings = [];
$sectionInfo = null;
if ($sectionID) {
    $ri = $db->prepare("SELECT sec.*,p.progName,y.yearName FROM sections sec JOIN programs p ON sec.progID=p.progID JOIN year y ON sec.yearID=y.yearID WHERE sec.sectionID=?");
    $ri->execute([$sectionID]); $sectionInfo = $ri->fetch();

    // Compute GWA per student in this section
    $rankQuery = $db->prepare("
        SELECT s.studentID, s.studentNo, s.firstName, s.lastName, s.middleName,
               ROUND(AVG(g.average),2) as gwa,
               COUNT(g.gradeID) as totalSubj,
               SUM(CASE WHEN g.average IS NOT NULL AND g.average < 75 THEN 1 ELSE 0 END) as failCount,
               SUM(CASE WHEN g.average IS NULL THEN 1 ELSE 0 END) as incompleteCount
        FROM registration r
        JOIN students s ON r.studentID = s.studentID
        LEFT JOIN grades g ON g.studentID = s.studentID AND g.ayID = r.ayID AND g.dateDeleted IS NULL
        WHERE r.sectionID = ? AND r.ayID = ? AND r.status = 'enrolled' AND r.dateDeleted IS NULL
        GROUP BY s.studentID
        ORDER BY gwa DESC, s.lastName ASC
    ");
    $rankQuery->execute([$sectionID, $ay['ayID']]);
    $rankings = $rankQuery->fetchAll();
}

$honorWith = (float)(getSetting('honor_with') ?: 90);
$honorWithHigh = (float)(getSetting('honor_with_high') ?: 95);
$honorWithHighest = (float)(getSetting('honor_with_highest') ?: 98);

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-1"><i class="fas fa-trophy me-2 text-warning"></i>Academic Ranking</h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">Home</a></li>
      <li class="breadcrumb-item active">Ranking</li>
    </ol></nav>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-3">
    <!-- Section list -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="fas fa-list me-2"></i>Sections</h6></div>
      <div class="list-group list-group-flush">
        <?php foreach($sectionsList as $sec): ?>
        <a href="ranking.php?sectionID=<?= $sec['sectionID'] ?>" 
           class="list-group-item list-group-item-action <?= $sectionID==$sec['sectionID']?'active':'' ?>">
          <div class="fw-semibold"><?= clean($sec['sectionName']) ?></div>
          <small class="<?= $sectionID==$sec['sectionID']?'text-white-50':'text-muted' ?>"><?= clean($sec['yearName'].' - '.$sec['progName']) ?></small>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-md-9">
    <?php if ($sectionID && $sectionInfo): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <div>
          <h6 class="mb-0"><i class="fas fa-trophy text-warning me-2"></i><?= clean($sectionInfo['sectionName']) ?> &mdash; SY <?= clean($ay['academicYear']) ?></h6>
          <small class="text-muted"><?= clean($sectionInfo['yearName'].' - '.$sectionInfo['progName']) ?></small>
        </div>
        <div class="d-flex gap-2">
          <?php if ($_SESSION['roleID'] !== ROLE_TEACHER): ?>
          <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="sectionID" value="<?= $sectionID ?>">
            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Recompute rankings for this section?')">
              <i class="fas fa-sync me-1"></i>Compute Rankings
            </button>
          </form>
          <?php endif; ?>
          <a href="<?= SITE_URL ?>/reports/report-ranking.php?sectionID=<?= $sectionID ?>" class="btn btn-sm btn-outline-success" target="_blank">
            <i class="fas fa-print me-1"></i>Print
          </a>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="text-center">Rank</th>
                <th>Student</th>
                <th>Student No</th>
                <th class="text-center">Subjects</th>
                <th class="text-center">Incomplete</th>
                <th class="text-center">Failed</th>
                <th class="text-center">GWA</th>
                <th>Honor</th>
              </tr>
            </thead>
            <tbody>
              <?php $rank = 1; foreach ($rankings as $r):
                $honor = null;
                if ($r['gwa'] !== null) {
                  if ($r['gwa'] >= $honorWithHighest) $honor = ['With Highest Honor','success'];
                  elseif ($r['gwa'] >= $honorWithHigh) $honor = ['With High Honor','primary'];
                  elseif ($r['gwa'] >= $honorWith) $honor = ['With Honor','info'];
                }
              ?>
              <tr class="<?= $rank<=3?'table-warning':'' ?>">
                <td class="text-center">
                  <?php if ($rank===1): ?><i class="fas fa-trophy text-warning fs-5"></i>
                  <?php elseif ($rank===2): ?><i class="fas fa-medal text-secondary fs-5"></i>
                  <?php elseif ($rank===3): ?><i class="fas fa-medal text-danger fs-5"></i>
                  <?php else: ?><span class="fw-bold text-muted"><?= $rank ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="student-view.php?id=<?= $r['studentID'] ?>" class="text-decoration-none">
                    <strong><?= clean($r['lastName'].', '.$r['firstName'].' '.($r['middleName']?substr($r['middleName'],0,1).'.':'')) ?></strong>
                  </a>
                </td>
                <td><code><?= clean($r['studentNo']) ?></code></td>
                <td class="text-center"><?= $r['totalSubj'] ?></td>
                <td class="text-center"><?= $r['incompleteCount'] > 0 ? '<span class="badge bg-warning">'.$r['incompleteCount'].'</span>' : '0' ?></td>
                <td class="text-center"><?= $r['failCount'] > 0 ? '<span class="badge bg-danger">'.$r['failCount'].'</span>' : '0' ?></td>
                <td class="text-center">
                  <?php if ($r['gwa'] !== null): ?>
                  <span class="badge fs-6 bg-<?= $r['gwa']>=$honorWith?'success':($r['gwa']>=75?'primary':'danger') ?>">
                    <?= number_format($r['gwa'],2) ?>
                  </span>
                  <?php else: ?>
                  <span class="badge bg-secondary">No Grades</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($honor): ?>
                  <span class="badge bg-<?= $honor[1] ?>"><i class="fas fa-star me-1"></i><?= $honor[0] ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php $rank++; endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if (!empty($rankings)): ?>
      <div class="card-footer bg-light">
        <div class="row text-center g-2">
          <?php
          $withHighest = count(array_filter($rankings, fn($r)=>$r['gwa']>=$honorWithHighest));
          $withHigh = count(array_filter($rankings, fn($r)=>$r['gwa']>=$honorWithHigh && $r['gwa']<$honorWithHighest));
          $with = count(array_filter($rankings, fn($r)=>$r['gwa']>=$honorWith && $r['gwa']<$honorWithHigh));
          ?>
          <div class="col"><span class="badge bg-success fs-6"><?= $withHighest ?></span><br><small>With Highest Honor</small></div>
          <div class="col"><span class="badge bg-primary fs-6"><?= $withHigh ?></span><br><small>With High Honor</small></div>
          <div class="col"><span class="badge bg-info fs-6"><?= $with ?></span><br><small>With Honor</small></div>
          <div class="col"><span class="badge bg-secondary fs-6"><?= count($rankings) ?></span><br><small>Total Students</small></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <i class="fas fa-trophy text-muted" style="font-size:4rem"></i>
        <h5 class="mt-3 text-muted">Select a Section</h5>
        <p class="text-muted">Choose a section from the list to view academic rankings.</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
