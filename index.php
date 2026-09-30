<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['userID'])) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

$error = '';
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $db = getDB();
            // Check users table
            $stmt = $db->prepare("SELECT u.*, r.roleName FROM users u JOIN roles r ON u.roleID=r.roleID WHERE u.email=? AND u.isActive=1 AND u.dateDeleted IS NULL");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['userID']   = $user['userID'];
                $_SESSION['roleID']   = $user['roleID'];
                $_SESSION['roleName'] = $user['roleName'];
                $_SESSION['name']     = $user['firstName'] . ' ' . $user['lastName'];
                $_SESSION['email']    = $user['email'];
                $_SESSION['pic']      = $user['pic'];
                logActivity('Login', 'Auth', 'User logged in successfully');
                header('Location: ' . SITE_URL . '/dashboard.php');
                exit;
            }

            // Check students
            $stmt2 = $db->prepare("SELECT * FROM students WHERE email=? AND dateDeleted IS NULL");
            $stmt2->execute([$email]);
            $student = $stmt2->fetch();
            if ($student && $student['password'] && password_verify($password, $student['password'])) {
                session_regenerate_id(true);
                $_SESSION['userID']    = 's_' . $student['studentID'];
                $_SESSION['studentID'] = $student['studentID'];
                $_SESSION['roleID']    = ROLE_STUDENT;
                $_SESSION['roleName']  = 'Student';
                $_SESSION['name']      = $student['firstName'] . ' ' . $student['lastName'];
                $_SESSION['email']     = $student['email'];
                $_SESSION['pic']       = $student['pic'];
                header('Location: ' . SITE_URL . '/dashboard.php');
                exit;
            }

            $error = 'Invalid email or password. Please try again.';
            logActivity('Failed Login', 'Auth', 'Failed login attempt for: ' . $email);
        }
    }
}

$csrf = generateCSRF();
$schoolName = 'Sto. Domingo National Trade School';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In &mdash; SDNTS Academic Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
<style>
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --navy: #071525;
  --navy-mid: #0e2340;
  --blue: #1d5fa8;
  --blue-lt: #3a87d4;
  --gold: #c9a84c;
  --gold-lt: #e8c97a;
  --cream: #f9f6f0;
  --white: #ffffff;
  --text: #0e1a2c;
  --muted: #6b7a93;
  --border: rgba(255,255,255,0.12);
  --glass: rgba(255,255,255,0.06);
}

html, body { height: 100%; font-family: 'DM Sans', sans-serif; overflow: hidden; }

/* ── BACKGROUND ── */
.scene {
  position: fixed; inset: 0;
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 55%, #163257 100%);
}
.scene-photo {
  position: fixed; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover;
}
.scene::after {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(
    110deg,
    rgba(7, 21, 37, 0.93) 0%,
    rgba(11, 34, 65, 0.82) 40%,
    rgba(7, 21, 37, 0.65) 100%
  );
}

/* ── LAYOUT ── */
.layout {
  position: relative; z-index: 1;
  height: 100vh; display: flex; flex-direction: column;
}

/* ── TOP BAR ── */
.topbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 2.5rem; height: 80px;
  background: rgba(7,21,37,0.75); backdrop-filter: blur(25px);
  border-bottom: 1px solid var(--border);
  box-shadow: 0 8px 32px rgba(0,0,0,.3);
}

.topbar-brand {
  display: flex; align-items: center; gap: 16px;
  flex: 1; max-width: 400px;
}

.main-logo {
  width: 52px; height: 52px; flex-shrink: 0;
  border-radius: 12px;
  border: 2px solid var(--gold);
  box-shadow:
    0 0 0 1px rgba(255,255,255,.2),
    0 4px 20px rgba(201,168,76,.4);
  object-fit: cover;
  transition: all .3s ease;
}

.main-logo:hover {
  transform: scale(1.05) rotate(2deg);
  box-shadow:
    0 0 0 1px rgba(255,255,255,.3),
    0 8px 32px rgba(201,168,76,.6);
}

.topbar-name {
  font-size: 16px;
  font-weight: 700;
  color: rgba(255,255,255,.95);
  letter-spacing: .015em;
  line-height: 1.2;
  text-shadow: 0 1px 2px rgba(0,0,0,.3);
}

.topbar-nav {
  display: flex;
  align-items: center;
  gap: 2rem;
}
.topbar-nav a {
  font-size: 13px;
  color: rgba(255,255,255,.6);
  text-decoration: none;
  letter-spacing: .025em;
  font-weight: 500;
  transition: all .2s ease;
  position: relative;
}
.topbar-nav a::after {
  content: '';
  position: absolute; bottom: -4px; left: 0;
  width: 0; height: 1px;
  background: var(--gold);
  transition: width .2s ease;
}
.topbar-nav a:hover {
  color: #fff;
}
.topbar-nav a:hover::after {
  width: 100%;
}
.topbar-divider {
  width: 1px; height: 20px;
  background: rgba(255,255,255,.15);
  margin: 0 1rem;
}

/* ── MAIN CONTENT ── */
.main {
  flex: 1; display: flex; align-items: center;
  padding: 2rem 2.5rem; gap: 3rem; overflow: hidden;
}

/* ── LEFT PANEL ── */
.left {
  flex: 1; max-width: 560px;
  animation: fadeSlideLeft .7s ease both;
}
@keyframes fadeSlideLeft { from { opacity:0; transform: translateX(-24px); } to { opacity:1; transform: translateX(0); } }

.school-seal {
  width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
  border: 2px solid var(--gold); box-shadow: 0 0 28px rgba(201,168,76,.25);
  margin-bottom: 1.5rem;
}
.eyebrow {
  display: flex; align-items: center; gap: 10px;
  font-size: 11px; letter-spacing: .15em; text-transform: uppercase;
  color: var(--gold); margin-bottom: 1rem;
}
.eyebrow::before { content: ''; width: 24px; height: 1px; background: var(--gold); opacity: .6; }

.left h1 {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2.2rem, 3.5vw, 3rem);
  font-weight: 700; line-height: 1.1;
  color: #fff; margin-bottom: 1rem;
}
.left h1 em { font-style: italic; color: var(--gold-lt); }

.divider-line {
  width: 48px; height: 2px;
  background: linear-gradient(90deg, var(--gold), transparent);
  margin-bottom: 1.25rem;
}

.left p {
  font-size: 14px; color: rgba(255,255,255,.45);
  line-height: 1.8; max-width: 420px; margin-bottom: 2rem;
}

/* Info cards */
.info-cards { display: grid; grid-template-columns: repeat(3,1fr); gap: .75rem; max-width: 460px; }
.info-card {
  background: var(--glass); border: 1px solid var(--border);
  border-radius: 10px; padding: .875rem 1rem;
  backdrop-filter: blur(8px);
}
.info-card .ic-num { font-size: 1.4rem; font-weight: 700; color: #fff; line-height: 1; }
.info-card .ic-lbl { font-size: 10.5px; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .05em; margin-top: 3px; }

/* Program tags */
.prog-tags { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1.25rem; }
.prog-tag {
  font-size: 11px; font-weight: 500; padding: 4px 11px; border-radius: 20px;
  background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
  color: rgba(255,255,255,.65); letter-spacing: .02em; transition: all .2s;
}
.prog-tag:hover { background: rgba(255,255,255,.14); color: #fff; }
.prog-tag.gold { background: rgba(201,168,76,.15); border-color: rgba(201,168,76,.3); color: var(--gold-lt); }

/* ── RIGHT PANEL (FORM) ── */
.right {
  width: 420px; flex-shrink: 0;
  animation: fadeSlideRight .7s .15s ease both;
}
@keyframes fadeSlideRight { from { opacity:0; transform: translateX(24px); } to { opacity:1; transform: translateX(0); } }

.form-card {
  background: rgba(255,255,255,.97);
  border-radius: 20px; padding: 2.5rem 2.25rem;
  box-shadow:
    0 0 0 1px rgba(255,255,255,.08),
    0 32px 80px rgba(7,21,37,.5),
    0 8px 32px rgba(7,21,37,.25);
}

.form-top { margin-bottom: 2rem; }
.form-tag {
  display: inline-flex; align-items: center; gap: 6px;
  background: #f0f7ff; color: #1d5fa8;
  font-size: 11px; font-weight: 600; letter-spacing: .04em;
  text-transform: uppercase; padding: 4px 12px; border-radius: 20px;
  border: 1px solid #c7dff5; margin-bottom: .85rem;
}
.form-tag::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: #1d5fa8; }
.form-top h2 { font-size: 1.3rem; font-weight: 600; color: var(--text); margin-bottom: 4px; }
.form-top p  { font-size: 13px; color: var(--muted); line-height: 1.5; }

/* Alerts */
.alert {
  display: flex; align-items: flex-start; gap: 9px;
  background: #fff1f2; border: 1px solid #fecdd3; border-radius: 10px;
  padding: 10px 14px; margin-bottom: 1.25rem;
  animation: shake .35s ease;
}
.alert.alert-warn { background: #fffbeb; border-color: #fde68a; }
.alert.alert-warn span { color: #92400e; }
.alert.alert-warn .alert-icon { color: #d97706; }
@keyframes shake {
  0%,100% { transform: translateX(0); }
  20%,60%  { transform: translateX(-4px); }
  40%,80%  { transform: translateX(4px); }
}
.alert-icon { width: 14px; height: 14px; flex-shrink: 0; margin-top: 1px; color: #e11d48; }
.alert span { font-size: 13px; color: #9f1239; line-height: 1.4; }

/* Fields */
.field { margin-bottom: 1rem; }
.field label {
  display: block; font-size: 11px; font-weight: 600; color: var(--muted);
  text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px;
}
.input-wrap { position: relative; }
.input-icon {
  position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
  width: 15px; height: 15px; color: #cbd5e1; pointer-events: none;
  transition: color .15s;
}
.input-wrap:focus-within .input-icon { color: var(--blue); }
.input-wrap input {
  width: 100%; height: 46px;
  border: 1.5px solid #e2e8f0; border-radius: 11px;
  background: #f8fafc; padding: 0 40px 0 40px;
  font-size: 14px; font-family: 'DM Sans', sans-serif; color: var(--text);
  outline: none; transition: border-color .15s, background .15s, box-shadow .15s;
}
.input-wrap input::placeholder { color: #b0bec8; }
.input-wrap input:focus {
  border-color: var(--blue); background: #fff;
  box-shadow: 0 0 0 4px rgba(29,95,168,.1);
}
.toggle-pw {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; padding: 6px;
  color: #94a3b8; display: flex; align-items: center;
}
.toggle-pw:hover { color: var(--blue); }
.toggle-pw svg { width: 16px; height: 16px; }
.field-footer { display: flex; justify-content: flex-end; margin-top: 7px; }
.field-footer a { font-size: 12px; color: var(--blue); text-decoration: none; transition: color .15s; }
.field-footer a:hover { color: #0e3a7a; }

/* Submit */
.btn-submit {
  width: 100%; height: 48px; margin-top: .5rem;
  background: linear-gradient(135deg, #071525 0%, #0e2340 50%, #193d72 100%);
  color: #fff; border: none; border-radius: 11px;
  font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500;
  cursor: pointer; letter-spacing: .03em;
  box-shadow: 0 4px 16px rgba(7,21,37,.35);
  transition: all .2s; position: relative; overflow: hidden;
}
.btn-submit:hover { box-shadow: 0 8px 28px rgba(7,21,37,.45); transform: translateY(-1px); }
.btn-submit:active { transform: scale(.99); }

/* Form footer */
.form-footer {
  margin-top: 1.5rem; padding-top: 1.25rem;
  border-top: 1px solid #f1f5f9;
  display: flex; align-items: center; gap: .5rem;
  justify-content: center;
}
.form-footer p { font-size: 11.5px; color: var(--muted); text-align: center; }
.form-footer a { color: var(--blue); text-decoration: none; font-size: 11.5px; }

/* Default-admin note (kept from prior design; remove if not wanted in production) */
.default-admin-note {
  margin-top: 1rem; padding: .65rem .9rem; border-radius: 10px;
  background: #f8fafc; border: 1px solid #e2e8f0;
  font-size: 11px; color: var(--muted); text-align: center; line-height: 1.5;
}

/* Accreditation strip */
.accred {
  display: flex; align-items: center; gap: 1rem; margin-top: 1.5rem;
  padding: 0 1.25rem;
}
.accred-item { display: flex; align-items: center; gap: .4rem; }
.accred-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--gold); opacity: .7; }
.accred-item span { font-size: 11px; color: rgba(255,255,255,.4); letter-spacing: .04em; }

/* ── FOOTER ── */
.footer {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: .5rem;
  padding: .85rem 2.5rem;
  background: rgba(7,21,37,.7); backdrop-filter: blur(12px);
  border-top: 1px solid var(--border);
}
.footer p { font-size: 11px; color: rgba(255,255,255,.25); }
.footer nav { display: flex; gap: 1.5rem; }
.footer nav a { font-size: 11px; color: rgba(255,255,255,.25); text-decoration: none; transition: color .15s; }
.footer nav a:hover { color: rgba(255,255,255,.6); }

/* Gold shimmer animation */
@keyframes shimmer {
  0%   { box-shadow: 0 0 20px rgba(201,168,76,.2); }
  50%  { box-shadow: 0 0 36px rgba(201,168,76,.45); }
  100% { box-shadow: 0 0 20px rgba(201,168,76,.2); }
}
.main-logo, .school-seal { animation: shimmer 4s ease-in-out infinite; }

@media (max-width: 900px) {
  .left { display: none; }
  .main { justify-content: center; padding: 1.5rem; }
  .right { width: 100%; max-width: 400px; }
  .topbar { padding: 1rem 1.5rem; }
  .topbar-nav { gap: 1rem; }
  .topbar-nav a { font-size: 12px; }
  html, body { overflow: auto; }
  .layout { height: auto; min-height: 100vh; }
}

@media (max-width: 480px) {
  .topbar-brand { gap: 12px; }
  .main-logo { width: 44px; height: 44px; }
  .topbar-name { font-size: 14px; }
}
</style>
</head>
<body>

<div class="scene">
  <img class="scene-photo" src="https://scontent.fmnl37-1.fna.fbcdn.net/v/t39.30808-6/674397324_122109876056949763_2589836678596861711_n.jpg?stp=cp6_dst-jpg_tt6&cstp=mx1637x640&ctp=p180x540&_nc_cat=109&_nc_map=urlgen_bucketless&ccb=1-7&_nc_sid=833d8c&_nc_eui2=AeH-_lqSnWB1C4VAbFsiVNAmBpzekEIyz6cGnN6QQjLPpw-FhU8whe3X3m4xi-qYCtFquO0S-oFOXZnoBORGDi3p&_nc_ohc=WTiTBFBIcJ8Q7kNvwFRq93r&_nc_oc=AdpU1atfrpztph5lUupYPlQedXnBFK3uQvbkfG9K4BW-gQ6n_y8wDpUItbYzyfRp5qs&_nc_zt=23&_nc_ht=scontent.fmnl37-1.fna&_nc_gid=LdJhlcZKt7jScdrgjcF1PQ&_nc_ss=7b2a8&oh=00_AQKjdd-8Pk9tstGVZFGe_1kMlprFUhdYeo0xay5OwYlVqQ&oe=6A9B0A99" alt=""
       onerror="this.remove()">
</div>

<div class="layout">

  <!-- TOP BAR -->
  <header class="topbar">
    <div class="topbar-brand">
      <img class="main-logo"
           src="https://scontent.fmnl3-4.fna.fbcdn.net/v/t39.30808-6/489424089_1107861254688808_5570020434837337672_n.jpg?stp=dst-jpg_tt6&cstp=mx288x288&ctp=s288x288&_nc_cat=106&_nc_map=urlgen_bucketless&ccb=1-7&_nc_sid=a5f93a&_nc_eui2=AeHJnVgDXNNbzgVyGEDn_sFvq8PP58TLcFmrw8_nxMtwWReTxwFy5-iEIPLA8TAQsEWchCw1pfyr6XGeBJ3ArC1M&_nc_ohc=6ordqiWoSqoQ7kNvwEeZICa&_nc_oc=Adpd1k_j0W3Z_hZehWdJsMloOWncPIygy2PXBUnmCEVahSqSfjKXw9KsU5vcRhhjdwE&_nc_zt=23&_nc_ht=scontent.fmnl3-4.fna&_nc_gid=AimdwcA9AJJeBxO3L77qNg&_nc_ss=7b2a8&oh=00_AQJzswgDpcybvqbfr4CGF8ZSubhP0AjNLuGEcxvCz6KqSw&oe=6A9AFBE1"
           alt="SDNTS Official Logo"
           onerror="this.style.display='none'">
      <span class="topbar-name">STO. DOMINGO<br>NATIONAL TRADE SCHOOL</span>
    </div>
    <nav class="topbar-nav">
      <a href="mailto:registrar@sdnts.edu.ph">Registrar's Office</a>
      <div class="topbar-divider"></div>
      <a href="#">IT Helpdesk</a>
      <div class="topbar-divider"></div>
      <a href="#">School Website</a>
    </nav>
  </header>

  <!-- MAIN -->
  <main class="main">

    <!-- LEFT: School info -->
    <div class="left">
      <img class="school-seal"
           src="https://scontent.fmnl3-4.fna.fbcdn.net/v/t39.30808-6/489424089_1107861254688808_5570020434837337672_n.jpg?stp=dst-jpg_tt6&cstp=mx288x288&ctp=s288x288&_nc_cat=106&_nc_map=urlgen_bucketless&ccb=1-7&_nc_sid=a5f93a&_nc_eui2=AeHJnVgDXNNbzgVyGEDn_sFvq8PP58TLcFmrw8_nxMtwWReTxwFy5-iEIPLA8TAQsEWchCw1pfyr6XGeBJ3ArC1M&_nc_ohc=6ordqiWoSqoQ7kNvwEeZICa&_nc_oc=Adpd1k_j0W3Z_hZehWdJsMloOWncPIygy2PXBUnmCEVahSqSfjKXw9KsU5vcRhhjdwE&_nc_zt=23&_nc_ht=scontent.fmnl3-4.fna&_nc_gid=AimdwcA9AJJeBxO3L77qNg&_nc_ss=7b2a8&oh=00_AQJzswgDpcybvqbfr4CGF8ZSubhP0AjNLuGEcxvCz6KqSw&oe=6A9AFBE1"
           onerror="this.style.display='none'" alt="">

      <div class="eyebrow">Academic Portal</div>
      <h1>Sto. Domingo<br><em>National</em> Trade School</h1>
      <div class="divider-line"></div>
      <p>
        A public secondary school in Sto. Domingo, Nueva Ecija, committed to
        quality technical-vocational and academic education. Empowering students
        with knowledge, skills, and values for a better future.
      </p>

      <div class="info-cards">
        <div class="info-card">
          <div class="ic-num">K&ndash;12</div>
          <div class="ic-lbl">Curriculum</div>
        </div>
        <div class="info-card">
          <div class="ic-num">SHS</div>
          <div class="ic-lbl">Senior High</div>
        </div>
        <div class="info-card">
          <div class="ic-lbl">DepEd Accredited</div>
        </div>
      </div>

      <div class="prog-tags">
        <span class="prog-tag gold">STEM</span>
        <span class="prog-tag">ABM</span>
        <span class="prog-tag">HUMSS</span>
        <span class="prog-tag">TVL &mdash; ICT</span>
        <span class="prog-tag">General Track</span>
        <span class="prog-tag">STE Program</span>
      </div>

      <div class="accred">
        <div class="accred-item"><div class="accred-dot"></div><span>DepEd Region III</span></div>
        <div class="accred-item"><div class="accred-dot"></div><span>TESDA Accredited</span></div>
        <div class="accred-item"><div class="accred-dot"></div><span>Nueva Ecija</span></div>
      </div>
    </div>

    <!-- RIGHT: Form -->
    <div class="right">
      <div class="form-card">
        <div class="form-top">
          <div class="form-tag">Secure Access</div>
          <h2>Welcome!</h2>
          <p>Sign in to your SDNTS account to continue.</p>
        </div>

        <?php if ($timeout): ?>
        <div class="alert alert-warn">
          <svg class="alert-icon" viewBox="0 0 14 14" fill="none">
            <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
            <line x1="7" y1="4.5" x2="7" y2="7.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
            <circle cx="7" cy="9.5" r=".7" fill="currentColor"/>
          </svg>
          <span>Session expired. Please log in again.</span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert">
          <svg class="alert-icon" viewBox="0 0 14 14" fill="none">
            <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
            <line x1="7" y1="4.5" x2="7" y2="7.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
            <circle cx="7" cy="9.5" r=".7" fill="currentColor"/>
          </svg>
          <span><?= clean($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

          <div class="field">
            <label>Email Address</label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 15 15" fill="none">
                <rect x="1" y="3" width="13" height="9" rx="1.5" stroke="currentColor" stroke-width="1.15"/>
                <path d="M1 5l6.5 4.2L14 5" stroke="currentColor" stroke-width="1.15" stroke-linecap="round"/>
              </svg>
              <input type="email" name="email" placeholder="you@sdnts.edu.ph" autocomplete="email"
                     value="<?= clean($_POST['email'] ?? '') ?>" required autofocus>
            </div>
          </div>

          <div class="field">
            <label>Password</label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 15 15" fill="none">
                <rect x="3" y="7" width="9" height="6.5" rx="1" stroke="currentColor" stroke-width="1.15"/>
                <path d="M5.5 7V5a2 2 0 014 0v2" stroke="currentColor" stroke-width="1.15"/>
              </svg>
              <input type="password" name="password" id="passwordField" placeholder="Enter your password" autocomplete="current-password" required>
              <button class="toggle-pw" type="button" onclick="togglePw()" aria-label="Show password">
                <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                  <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-submit">Sign In to Portal</button>
        </form>

        

        <div class="form-footer">
          <p>Having trouble? <a href="mailto:registrar@sdnts.edu.ph">Contact the Registrar's Office</a></p>
        </div>
      </div>
    </div>

  </main>

  <!-- FOOTER -->
  <footer class="footer">
    <p>&copy; <?= date('Y') ?> Sto. Domingo National Trade School &middot; Baloc, Sto. Domingo, Nueva Ecija &middot; All Rights Reserved</p>
    <nav>
      <a href="#">Privacy Policy</a>
      <a href="#">Terms of Use</a>
      <a href="#">IT Helpdesk</a>
    </nav>
  </footer>

</div>

<script>
function togglePw(){
  var f = document.getElementById('passwordField');
  f.type = (f.type === 'password') ? 'text' : 'password';
}
</script>
</body>
</html>