<?php
require_once '../includes/config.php';
requireRole([ROLE_ADMIN]);
$pageTitle = 'System Settings';
$db = getDB();
$csrf = generateCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $settings = $_POST['settings'] ?? [];
    foreach ($settings as $key => $val) {
        $db->prepare("INSERT INTO system_settings (settingKey,settingValue) VALUES (?,?) ON DUPLICATE KEY UPDATE settingValue=?")->execute([clean($key),clean($val),clean($val)]);
    }
    logActivity('Update Settings','Settings','System settings updated');
    $_SESSION['flash_success'] = 'Settings saved successfully.';
    header('Location: settings.php'); exit;
}

$allSettings = $db->query("SELECT settingKey, settingValue FROM system_settings ORDER BY settingKey")->fetchAll(PDO::FETCH_KEY_PAIR);
include '../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="mb-1"><i class="fas fa-cog me-2 text-primary"></i>System Settings</h4>
  <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= SITE_URL ?>/dashboard.php">Home</a></li><li class="breadcrumb-item active">Settings</li></ol></nav></div>
</div>
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0">School Information</h6></div>
        <div class="card-body">
          <?php
          $schoolFields = ['school_name'=>'School Name','school_address'=>'Address','school_contact'=>'Contact No.','school_email'=>'Email'];
          foreach($schoolFields as $key=>$label): ?>
          <div class="mb-3">
            <label class="form-label"><?= $label ?></label>
            <input type="text" name="settings[<?= $key ?>]" class="form-control" value="<?= clean($allSettings[$key]??'') ?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0">Grading Configuration</h6></div>
        <div class="card-body">
          <div class="mb-3"><label class="form-label">Grade Scale Minimum</label><input type="number" name="settings[grade_min]" class="form-control" value="<?= clean($allSettings['grade_min']??'60') ?>" min="0" max="100" step="0.01"><div class="form-text">Lowest value teachers may enter for any grading period.</div></div>
          <div class="mb-3"><label class="form-label">Grade Scale Maximum</label><input type="number" name="settings[grade_max]" class="form-control" value="<?= clean($allSettings['grade_max']??'100') ?>" min="0" max="100" step="0.01"><div class="form-text">Highest value teachers may enter for any grading period.</div></div>
          <div class="mb-3"><label class="form-label">Passing Grade</label><input type="number" name="settings[passing_grade]" class="form-control" value="<?= clean($allSettings['passing_grade']??'75') ?>" min="0" max="100" step="0.01"></div>
          <div class="mb-3"><label class="form-label">With Honor GWA</label><input type="number" name="settings[honor_with]" class="form-control" value="<?= clean($allSettings['honor_with']??'90') ?>" step="0.01"></div>
          <div class="mb-3"><label class="form-label">With High Honor GWA</label><input type="number" name="settings[honor_with_high]" class="form-control" value="<?= clean($allSettings['honor_with_high']??'95') ?>" step="0.01"></div>
          <div class="mb-3"><label class="form-label">With Highest Honor GWA</label><input type="number" name="settings[honor_with_highest]" class="form-control" value="<?= clean($allSettings['honor_with_highest']??'98') ?>" step="0.01"></div>
        </div>
      </div>
    </div>
    <div class="col-12 text-end">
      <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Save Settings</button>
    </div>
  </div>
</form>
<?php include '../includes/footer.php'; ?>