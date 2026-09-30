<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN]);
$pageTitle = 'Database Backup';
$db = getDB();
$csrf = generateCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    
    $backupDir = __DIR__ . '/../backup/';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    
    $filename = 'sdnts_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . $filename;
    
    // Get all tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- SDNTS Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n-- Database: " . DB_NAME . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // Drop + create
        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create['Create Table'] . ";\n\n";
        
        // Data
        $rows = $db->query("SELECT * FROM `$table`")->fetchAll();
        if ($rows) {
            $cols = array_keys($rows[0]);
            $colStr = implode('`,`', $cols);
            $sql .= "INSERT INTO `$table` (`$colStr`) VALUES\n";
            $vals = [];
            foreach ($rows as $row) {
                $escaped = array_map(fn($v) => $v === null ? 'NULL' : "'" . addslashes($v) . "'", array_values($row));
                $vals[] = '(' . implode(',', $escaped) . ')';
            }
            $sql .= implode(",\n", $vals) . ";\n\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    file_put_contents($filepath, $sql);
    $size = filesize($filepath);
    
    $db->prepare("INSERT INTO backup_log (filename,filesize,createdBy) VALUES (?,?,?)")->execute([$filename,$size,$_SESSION['userID']]);
    logActivity('Database Backup','Backup','Created: '.$filename);
    
    // Download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $size);
    readfile($filepath);
    exit;
}

$backupLogs = $db->query("SELECT b.*,u.firstName,u.lastName FROM backup_log b LEFT JOIN users u ON b.createdBy=u.userID ORDER BY b.dateCreated DESC LIMIT 20")->fetchAll();
include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="mb-1"><i class="fas fa-database me-2 text-primary"></i>Database Backup</h4>
  <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">Home</a></li><li class="breadcrumb-item active">Backup</li></ol></nav></div>
</div>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-5">
        <i class="fas fa-database text-primary" style="font-size:4rem"></i>
        <h5 class="mt-3">Create Database Backup</h5>
        <p class="text-muted">Downloads a complete SQL backup of the SDNTS database including all tables and data.</p>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-download me-2"></i>Download Backup Now</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-history me-2"></i>Backup History</h6></div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead class="table-light"><tr><th>Filename</th><th>Size</th><th>By</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach($backupLogs as $b): ?>
            <tr>
              <td><small><?= clean($b['filename']) ?></small></td>
              <td><small><?= number_format($b['filesize']/1024,1) ?> KB</small></td>
              <td><small><?= clean($b['lastName']??'System') ?></small></td>
              <td><small><?= date('M d, Y H:i', strtotime($b['dateCreated'])) ?></small></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($backupLogs)): ?><tr><td colspan="4" class="text-center text-muted">No backups yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>