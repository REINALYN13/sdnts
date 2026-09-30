<?php
requireLogin();
$activeAY = getActiveAY();
$schoolName = getSetting('school_name') ?: 'SDNTS';
$currentUser = null;
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT u.*, r.roleName FROM users u JOIN roles r ON u.roleID=r.roleID WHERE u.userID=?");
    $stmt->execute([$_SESSION['userID']]);
    $currentUser = $stmt->fetch();
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? clean($pageTitle).' - ' : '' ?><?= clean($schoolName) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">

  <style>
    /* ---- Site-wide design tokens ---- */
    :root {
      --navy-900: #10233F;
      --navy-800: #16304F;
      --navy-700: #1B3A63;
      --navy-500: #2F5687;
      --gold-500: #B8862E;
      --gold-100: #FBF1DD;
      --sage-600: #3E7A5B;
      --sage-100: #E4F1EA;
      --plum-600: #5B4B8A;
      --brick-600: #B5432F;
      --brick-100: #F8E7E2;
      --cream-50: #F7F5F0;
      --ink-900: #1A1D23;
      --ink-600: #565C66;
      --ink-400: #8A909B;
      --line: #E7E3D9;
      --font-display: 'Fraunces', Georgia, serif;
      --font-body: 'Inter', -apple-system, sans-serif;
      --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    }
    body { font-family: var(--font-body); background: var(--cream-50); color: var(--ink-900); }

    /* Navbar */
    .site-navbar {
      background: linear-gradient(180deg, var(--navy-900), var(--navy-800));
      border-bottom: 1px solid rgba(255,255,255,0.06);
      padding-top: .6rem; padding-bottom: .6rem;
    }
    .site-navbar .navbar-brand {
      font-family: var(--font-display);
      font-weight: 600;
      font-size: 1.15rem;
      letter-spacing: -0.01em;
      color: #fff !important;
    }
    .site-navbar .navbar-brand i { color: var(--gold-500); }
    .site-navbar .nav-link {
      font-size: 0.88rem;
      font-weight: 500;
      color: rgba(255,255,255,0.82) !important;
      padding: 0.5rem 0.85rem !important;
      border-radius: 8px;
      transition: background .15s ease, color .15s ease;
    }
    .site-navbar .nav-link:hover,
    .site-navbar .nav-link:focus,
    .site-navbar .nav-link.show { background: rgba(255,255,255,0.08); color: #fff !important; }
    .site-navbar .nav-link i { color: var(--gold-500); opacity: 0.9; }
    .site-navbar .navbar-toggler { border-color: rgba(255,255,255,0.25); }
    .site-navbar .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255,0.85)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }

    .ay-chip {
      display: inline-flex; align-items: center; gap: 0.4rem;
      background: var(--gold-100);
      color: var(--gold-500);
      font-family: var(--font-mono);
      font-weight: 700;
      font-size: 0.76rem;
      padding: 0.4rem 0.8rem;
      border-radius: 999px;
    }

    .user-menu-toggle {
      padding: 0.3rem 0.6rem 0.3rem 0.3rem !important;
      border-radius: 999px;
      background: rgba(255,255,255,0.06);
    }
    .user-menu-toggle:hover { background: rgba(255,255,255,0.12); }
    .user-avatar-img { border: 2px solid rgba(255,255,255,0.25); }

    .dropdown-menu {
      border: 1px solid var(--line);
      border-radius: 12px;
      box-shadow: 0 12px 28px rgba(16,35,63,0.14);
      padding: 0.4rem;
    }
    .dropdown-menu .dropdown-header {
      font-family: var(--font-display);
      font-weight: 600;
      color: var(--navy-900);
      font-size: 0.95rem;
      padding: 0.5rem 0.75rem 0.1rem;
    }
    .dropdown-menu .dropdown-item-text { padding: 0 0.75rem 0.5rem; }
    .dropdown-menu .dropdown-item {
      border-radius: 8px;
      font-size: 0.87rem;
      padding: 0.5rem 0.75rem;
    }
    .dropdown-menu .dropdown-item:hover { background: var(--cream-50); }
    .dropdown-menu .dropdown-item i { color: var(--navy-500); width: 1.1rem; }
    .dropdown-menu .dropdown-item.text-danger i { color: var(--brick-600); }

    /* Alerts */
    .alert {
      border: 1px solid transparent;
      border-left-width: 4px;
      border-radius: 10px;
      font-size: 0.9rem;
      padding: 0.85rem 1.1rem;
    }
    .alert-success {
      background: var(--sage-100);
      border-left-color: var(--sage-600);
      color: #285B41;
    }
    .alert-danger {
      background: var(--brick-100);
      border-left-color: var(--brick-600);
      color: #8C3423;
    }
  </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark site-navbar shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= SITE_URL ?>/dashboard.php">
      <i class="fas fa-graduation-cap fa-lg"></i>
      <span class="d-none d-md-inline"><?= clean($schoolName) ?></span>
      <span class="d-inline d-md-none">SDNTS</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <?php if (in_array($_SESSION['roleID'], [ROLE_ADMIN, ROLE_PRINCIPAL])): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="fas fa-cog me-1"></i>Setup</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/academic-year.php"><i class="fas fa-calendar me-2"></i>Academic Year</a></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/programs.php"><i class="fas fa-book me-2"></i>Programs</a></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/strands.php"><i class="fas fa-stream me-2"></i>Strands</a></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/year-level.php"><i class="fas fa-layer-group me-2"></i>Year Level</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/subjects.php"><i class="fas fa-list me-2"></i>Subjects</a></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/sections.php"><i class="fas fa-chalkboard me-2"></i>Sections</a></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/schedule.php"><i class="fas fa-clock me-2"></i>Schedule</a></li>
          </ul>
        </li>
        <?php endif; ?>
        <?php if ($_SESSION['roleID'] === ROLE_ADMIN): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/admin/users.php"><i class="fas fa-users me-1"></i>Users</a>
        </li>
        <?php endif; ?>
        <?php if (in_array($_SESSION['roleID'], [ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_TEACHER])): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/admin/students.php"><i class="fas fa-user-graduate me-1"></i>Students</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/admin/enrollment.php"><i class="fas fa-clipboard-check me-1"></i>Enrollment</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/admin/grades.php"><i class="fas fa-star-half-alt me-1"></i>Grades</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/admin/ranking.php"><i class="fas fa-trophy me-1"></i>Ranking</a>
        </li>
        <?php endif; ?>
        <?php if ($_SESSION['roleID'] === ROLE_STUDENT): ?>
        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/student/my-grades.php"><i class="fas fa-star me-1"></i>My Grades</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/student/my-schedule.php"><i class="fas fa-clock me-1"></i>Schedule</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= SITE_URL ?>/student/my-ranking.php"><i class="fas fa-trophy me-1"></i>Ranking</a></li>
        <?php endif; ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="fas fa-chart-bar me-1"></i>Reports</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/reports/report-grades.php<?= $_SESSION['roleID'] === ROLE_STUDENT ? '?studentID='.(int)($_SESSION['studentID']??0) : '' ?>">Grade Report</a></li>
            <?php if ($_SESSION['roleID'] !== ROLE_STUDENT): ?>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/reports/report-ranking.php">Ranking Report</a></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/reports/report-enrollment.php">Enrollment Report</a></li>
            <?php endif; ?>
            <?php if (in_array($_SESSION['roleID'], [ROLE_ADMIN, ROLE_PRINCIPAL])): ?>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/reports/report-masterlist.php">Master List</a></li>
            <?php endif; ?>
          </ul>
        </li>
        <?php if (in_array($_SESSION['roleID'], [ROLE_ADMIN, ROLE_PRINCIPAL])): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/admin/archive.php"><i class="fas fa-box-archive me-1"></i>Archive</a>
        </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item me-2">
          <span class="ay-chip"><i class="fas fa-calendar-alt"></i><?= clean($activeAY['academicYear']) ?></span>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 user-menu-toggle" href="#" data-bs-toggle="dropdown">
            <img src="<?= SITE_URL ?>/assets/img/uploads/<?= clean($currentUser['pic'] ?? 'default.png') ?>"
                 class="rounded-circle user-avatar-img" width="32" height="32" style="object-fit:cover;" onerror="this.src='<?= SITE_URL ?>/assets/img/default.png'">
            <span class="d-none d-lg-inline"><?= clean($currentUser['firstName'] ?? '') ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header"><?= clean(($currentUser['firstName']??'').' '.($currentUser['lastName']??'')) ?></h6></li>
            <li><span class="dropdown-item-text text-muted small"><i class="fas fa-id-badge me-1"></i><?= clean($currentUser['roleName']??'') ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
            <?php if ($_SESSION['roleID'] === ROLE_ADMIN): ?>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/backup.php"><i class="fas fa-database me-2"></i>Backup</a></li>
            <li><a class="dropdown-item" href="<?= SITE_URL ?>/admin/activity-log.php"><i class="fas fa-history me-2"></i>Activity Log</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= SITE_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<!-- Breadcrumb & Messages -->
<div class="container-fluid mt-3">
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= clean($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= clean($_GET['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_SESSION['flash_success'])): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= clean($_SESSION['flash_success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['flash_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= clean($_SESSION['flash_error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>