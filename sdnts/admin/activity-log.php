<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN]);
$pageTitle = 'Activity Log';
$db = getDB();
$logs = $db->query("SELECT l.*,u.firstName,u.lastName FROM activity_log l LEFT JOIN users u ON l.userID=u.userID ORDER BY l.dateCreated DESC LIMIT 500")->fetchAll();
include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="mb-1"><i class="fas fa-history me-2 text-primary"></i>Activity Log</h4>
  <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">Home</a></li><li class="breadcrumb-item active">Activity Log</li></ol></nav></div>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover datatable mb-0">
        <thead class="table-light"><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
          <?php foreach($logs as $l): ?>
          <tr>
            <td><small><?= date('M d, Y H:i:s', strtotime($l['dateCreated'])) ?></small></td>
            <td><?= $l['firstName']?clean($l['lastName'].', '.$l['firstName']):'<em class="text-muted">System</em>' ?></td>
            <td><span class="badge bg-<?= str_contains($l['action'],'Delete')||str_contains($l['action'],'Failed')?'danger':(str_contains($l['action'],'Add')||str_contains($l['action'],'Enroll')?'success':'primary') ?>"><?= clean($l['action']) ?></span></td>
            <td><?= clean($l['module']??'-') ?></td>
            <td><small><?= clean($l['details']??'') ?></small></td>
            <td><code><?= clean($l['ipAddress']??'') ?></code></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; 
?>