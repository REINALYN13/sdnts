<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN]);
$pageTitle = 'Academic Year';
$db = getDB();
$csrf = generateCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $ay = trim($_POST['academicYear']??'');
        $desc = trim($_POST['academicDesc']??'');
        $status = $_POST['status']??'inactive';
        if (!$ay) { $_SESSION['flash_error']='Academic year required.'; }
        else {
            if ($status === 'active') $db->query("UPDATE academicYear SET status='inactive'");
            if ($action==='add') { $db->prepare("INSERT INTO academicYear (academicYear,academicDesc,status) VALUES (?,?,?)")->execute([$ay,$desc,$status]); $_SESSION['flash_success']='Added.'; }
            else { $id=(int)$_POST['ayID']; $db->prepare("UPDATE academicYear SET academicYear=?,academicDesc=?,status=? WHERE ayID=?")->execute([$ay,$desc,$status,$id]); $_SESSION['flash_success']='Updated.'; }
        }
    } elseif ($action==='set_active') {
        $id=(int)$_POST['ayID'];
        $db->query("UPDATE academicYear SET status='inactive'");
        $db->prepare("UPDATE academicYear SET status='active' WHERE ayID=?")->execute([$id]);
        $db->prepare("UPDATE system_settings SET settingValue=? WHERE settingKey='active_ay'")->execute([$id]);
        $_SESSION['flash_success']='Active academic year set.';
    } elseif ($action==='delete') {
        $db->prepare("UPDATE academicYear SET dateDeleted=NOW() WHERE ayID=?")->execute([(int)$_POST['ayID']]);
        $_SESSION['flash_success']='Removed.';
    }
    header('Location: academic-year.php'); exit;
}

$list = $db->query("SELECT * FROM academicYear WHERE dateDeleted IS NULL ORDER BY academicYear DESC")->fetchAll();
include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="mb-1"><i class="fas fa-calendar me-2 text-primary"></i>Academic Year</h4>
  <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">Home</a></li><li class="breadcrumb-item active">Academic Year</li></ol></nav></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAY"><i class="fas fa-plus me-2"></i>Add</button>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead class="table-light"><tr><th>Academic Year</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($list as $a): ?>
        <tr>
          <td><strong><?= clean($a['academicYear']) ?></strong></td>
          <td><?= clean($a['academicDesc']??'') ?></td>
          <td><?= $a['status']==='active'?'<span class="badge bg-success fs-6">Active</span>':'<span class="badge bg-secondary">Inactive</span>' ?></td>
          <td>
            <div class="btn-group btn-group-sm">
              <?php if ($a['status']!=='active'): ?>
              <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="set_active"><input type="hidden" name="ayID" value="<?= $a['ayID'] ?>"><button type="submit" class="btn btn-outline-success" onclick="return confirm('Set this as active academic year?')"><i class="fas fa-check me-1"></i>Set Active</button></form>
              <?php endif; ?>
              <button class="btn btn-outline-primary btn-edit-ay" data-ay='<?= htmlspecialchars(json_encode($a),ENT_QUOTES) ?>' data-bs-toggle="modal" data-bs-target="#modalAY"><i class="fas fa-edit"></i></button>
              <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="ayID" value="<?= $a['ayID'] ?>"><button type="submit" class="btn btn-outline-danger btn-delete"><i class="fas fa-trash"></i></button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="modal fade" id="modalAY" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="ayForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="add" id="ayAction">
      <input type="hidden" name="ayID" id="ayID">
      <div class="modal-header bg-primary text-white"><h5 class="modal-title"><span id="ayTitle">Add Academic Year</span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Academic Year (e.g. 2025-2026) <span class="text-danger">*</span></label><input type="text" name="academicYear" id="ay_year" class="form-control" placeholder="2025-2026" required></div>
        <div class="mb-3"><label class="form-label">Description</label><input type="text" name="academicDesc" id="ay_desc" class="form-control" placeholder="School Year 2025-2026"></div>
        <div class="mb-3"><label class="form-label">Status</label><select name="status" id="ay_status" class="form-select"><option value="inactive">Inactive</option><option value="active">Active</option></select></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save</button></div>
    </form>
  </div></div>
</div>
<script>
document.querySelectorAll('.btn-edit-ay').forEach(function(btn){
  btn.addEventListener('click',function(){
    var a=JSON.parse(this.getAttribute('data-ay'));
    document.getElementById('ayAction').value='edit';
    document.getElementById('ayID').value=a.ayID;
    document.getElementById('ayTitle').textContent='Edit Academic Year';
    document.getElementById('ay_year').value=a.academicYear||'';
    document.getElementById('ay_desc').value=a.academicDesc||'';
    document.getElementById('ay_status').value=a.status||'inactive';
  });
});
</script>
<?php include '../includes/footer.php'; ?>
