<?php
require_once '../includes/config.php';
requireLogin();
$db = getDB();
$ay = getActiveAY();
$sectionID = (int)($_GET['sectionID'] ?? 0);
$schoolName = getSetting('school_name');
$passingGrade = getSetting('passing_grade') ?: 75;
$honorWith = (float)(getSetting('honor_with') ?: 90);
$honorWithHigh = (float)(getSetting('honor_with_high') ?: 95);
$honorWithHighest = (float)(getSetting('honor_with_highest') ?: 98);

if (!$sectionID) { header('Location: ' . SITE_URL . '/admin/ranking.php'); exit; }

$si = $db->prepare("SELECT sec.*,p.progName,y.yearName FROM sections sec JOIN programs p ON sec.progID=p.progID JOIN year y ON sec.yearID=y.yearID WHERE sec.sectionID=?");
$si->execute([$sectionID]); $section = $si->fetch();

$rankQ = $db->prepare("SELECT s.studentID,s.studentNo,s.firstName,s.lastName,s.middleName,ROUND(AVG(g.average),2) as gwa,COUNT(g.gradeID) as totalSubj,SUM(CASE WHEN g.average IS NOT NULL AND g.average < 75 THEN 1 ELSE 0 END) as failCount FROM registration r JOIN students s ON r.studentID=s.studentID LEFT JOIN grades g ON g.studentID=s.studentID AND g.ayID=r.ayID AND g.dateDeleted IS NULL WHERE r.sectionID=? AND r.ayID=? AND r.status='enrolled' AND r.dateDeleted IS NULL GROUP BY s.studentID ORDER BY gwa DESC,s.lastName ASC");
$rankQ->execute([$sectionID,$ay['ayID']]); $rankings = $rankQ->fetchAll();
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Ranking Report</title>
<style>
body{font-family:Arial,sans-serif;font-size:12px;margin:20px}
.header{text-align:center;border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px}
.header h2{margin:2px 0;font-size:16px}.header h3{margin:2px 0;font-size:13px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #333;padding:5px 8px;text-align:center}
th{background:#f0f0f0;font-weight:bold}.text-left{text-align:left}
.honor{color:green;font-style:italic}.failed{color:red}
@media print{.no-print{display:none}}
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:15px"><button onclick="window.print()">🖨 Print</button> <a href="<?= SITE_URL ?>/admin/ranking.php?sectionID=<?= $sectionID ?>">← Back</a></div>
<div class="header">
  <p style="margin:0;font-size:10px">Republic of the Philippines · Department of Education</p>
  <h2><?= clean($schoolName) ?></h2>
  <p style="margin:0;font-size:11px">Baloc, Sto. Domingo, Nueva Ecija</p>
  <h3>ACADEMIC RANKING REPORT</h3>
  <p style="margin:2px 0">School Year: <?= clean($ay['academicYear']) ?> &nbsp;|&nbsp; Section: <?= clean($section['sectionName']) ?> &nbsp;|&nbsp; <?= clean($section['yearName'].' - '.$section['progName']) ?></p>
  <p style="margin:2px 0;font-size:11px">Date Printed: <?= date('F d, Y \a\t h:i A') ?></p>
</div>
<table>
  <thead>
    <tr>
      <th style="width:50px">Rank</th>
      <th class="text-left">Student Name</th>
      <th>Student No.</th>
      <th>Subjects</th>
      <th>Failed</th>
      <th>GWA</th>
      <th>Honor</th>
    </tr>
  </thead>
  <tbody>
    <?php $rank=1; foreach($rankings as $r):
      $honor=null;
      if($r['gwa']>=$honorWithHighest) $honor='With Highest Honor';
      elseif($r['gwa']>=$honorWithHigh) $honor='With High Honor';
      elseif($r['gwa']>=$honorWith) $honor='With Honor';
    ?>
    <tr>
      <td><strong><?= $rank ?></strong></td>
      <td class="text-left"><?= clean($r['lastName'].', '.$r['firstName'].' '.($r['middleName']?substr($r['middleName'],0,1).'.':'')) ?></td>
      <td><?= clean($r['studentNo']) ?></td>
      <td><?= $r['totalSubj'] ?></td>
      <td class="<?= $r['failCount']>0?'failed':'' ?>"><?= $r['failCount']>0?$r['failCount']:'-' ?></td>
      <td><strong><?= $r['gwa']!==null?number_format($r['gwa'],2):'-' ?></strong></td>
      <td class="honor"><?= $honor??'' ?></td>
    </tr>
    <?php $rank++; endforeach; ?>
  </tbody>
</table>
<br>
<p>Total Students: <strong><?= count($rankings) ?></strong> &nbsp;|&nbsp;
With Highest Honor: <strong><?= count(array_filter($rankings,fn($r)=>$r['gwa']>=$honorWithHighest)) ?></strong> &nbsp;|&nbsp;
With High Honor: <strong><?= count(array_filter($rankings,fn($r)=>$r['gwa']>=$honorWithHigh&&$r['gwa']<$honorWithHighest)) ?></strong> &nbsp;|&nbsp;
With Honor: <strong><?= count(array_filter($rankings,fn($r)=>$r['gwa']>=$honorWith&&$r['gwa']<$honorWithHigh)) ?></strong>
</p>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:30px">
  <div style="text-align:center;border-top:1px solid #333;padding-top:5px">______________________<br><small>Class Adviser</small></div>
  <div style="text-align:center;border-top:1px solid #333;padding-top:5px">______________________<br><small>School Principal</small></div>
</div>
</body></html>
