<?php
require_once 'includes/config.php';
requireLogin();
$pageTitle = 'My Profile';
$db = getDB();
$csrf = generateCSRF();

$isStudent = isset($_SESSION['studentID']);
$uid = $_SESSION['userID'];

// Fetch profile
if ($isStudent) {
    $stmt = $db->prepare("SELECT * FROM students WHERE studentID=?");
    $stmt->execute([$_SESSION['studentID']]);
} else {
    $stmt = $db->prepare("SELECT u.*,r.roleName FROM users u JOIN roles r ON u.roleID=r.roleID WHERE u.userID=?");
    $stmt->execute([$uid]);
}
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $currentPw  = $_POST['current_password'] ?? '';
        $newPw      = $_POST['new_password'] ?? '';
        $confirmPw  = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPw, $profile['password'])) {
            $_SESSION['flash_error'] = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 8) {
            $_SESSION['flash_error'] = 'New password must be at least 8 characters.';
        } elseif ($newPw !== $confirmPw) {
            $_SESSION['flash_error'] = 'Passwords do not match.';
        } else {
            $hashed = password_hash($newPw, PASSWORD_BCRYPT, ['cost'=>12]);
            if ($isStudent) {
                $db->prepare("UPDATE students SET password=? WHERE studentID=?")->execute([$hashed, $_SESSION['studentID']]);
            } else {
                $db->prepare("UPDATE users SET password=? WHERE userID=?")->execute([$hashed, $uid]);
            }
            logActivity('Change Password', 'Profile', 'Password changed');
            $_SESSION['flash_success'] = 'Password changed successfully.';
        }
    } elseif ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $contactNo = trim($_POST['contactNo'] ?? '');

        // Handle photo upload
        $pic = $profile['pic'];
        if (!empty($_FILES['pic']['name'])) {
            $ext = strtolower(pathinfo($_FILES['pic']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif'])) {
                $_SESSION['flash_error'] = 'Only JPG, PNG, GIF allowed.';
                header('Location: profile.php'); exit;
            }
            if ($_FILES['pic']['size'] > 2 * 1024 * 1024) {
                $_SESSION['flash_error'] = 'File too large (max 2MB).';
                header('Location: profile.php'); exit;
            }
            $uploadDir = __DIR__ . '/assets/img/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newName = ($isStudent ? 'student_' : 'user_') . ($isStudent ? $_SESSION['studentID'] : $uid) . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['pic']['tmp_name'], $uploadDir . $newName);
            $pic = $newName;
            $_SESSION['pic'] = $pic;
        }

        if ($isStudent) {
            $db->prepare("UPDATE students SET email=?,contactNo=?,pic=? WHERE studentID=?")->execute([$email,$contactNo,$pic,$_SESSION['studentID']]);
        } else {
            $db->prepare("UPDATE users SET email=?,pic=? WHERE userID=?")->execute([$email,$pic,$uid]);
        }
        logActivity('Update Profile', 'Profile', 'Profile updated');
        $_SESSION['flash_success'] = 'Profile updated.';
    }
    header('Location: profile.php'); exit;
}

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0"><i class="fas fa-user-circle me-2 text-primary"></i>My Profile</h4>
</div>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm text-center">
      <div class="card-body py-4">
        <img src="<?= SITE_URL ?>/assets/img/uploads/<?= clean($profile['pic']??'default.png') ?>"
             class="rounded-circle mb-3 border" width="100" height="100" style="object-fit:cover"
             onerror="this.src='<?= SITE_URL ?>/assets/img/default.png'" id="profilePreview">
        <h5><?= clean(($profile['firstName']??'').' '.($profile['lastName']??'')) ?></h5>
        <p class="text-muted"><?= clean($_SESSION['roleName']??'') ?></p>
        <p class="text-muted small"><?= clean($profile['email']??'') ?></p>
        <?php if (!$isStudent && !empty($profile['employeeNo'])): ?>
        <code><?= clean($profile['employeeNo']) ?></code>
        <?php elseif ($isStudent): ?>
        <code><?= clean($profile['studentNo']??'') ?></code>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabInfo">Update Info</a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabPw">Change Password</a></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="tabInfo">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="update_profile">
              <div class="mb-3">
                <label class="form-label">Profile Photo</label>
                <input type="file" name="pic" class="form-control" accept="image/*" onchange="previewImg(this)">
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= clean($profile['email']??'') ?>">
              </div>
              <?php if ($isStudent): ?>
              <div class="mb-3">
                <label class="form-label">Contact No.</label>
                <input type="text" name="contactNo" class="form-control" value="<?= clean($profile['contactNo']??'') ?>">
              </div>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
            </form>
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="tabPw">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="change_password">
              <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
              <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" minlength="8" required></div>
              <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
              <button type="submit" class="btn btn-warning"><i class="fas fa-key me-2"></i>Change Password</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function previewImg(input){
  if(input.files&&input.files[0]){
    var r=new FileReader();
    r.onload=function(e){document.getElementById('profilePreview').src=e.target.result;};
    r.readAsDataURL(input.files[0]);
  }
}
</script>
<?php include 'includes/footer.php'; ?>
