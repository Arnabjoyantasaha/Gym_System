<?php
// ──────────────────────────────────────────────────
//  auth/login.php  —  Login page
//  Anyone who isn't logged in sees this page
// ──────────────────────────────────────────────────

session_start(); // Start a session to store login data

// If already logged in, go straight to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Load database config so we can check user credentials
require_once '../config/database.php';

$error = ''; // Will hold any error message to show the user

// ── Handle Login Form Submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the submitted email and password
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? ''; // Don't trim passwords!

    // Basic validation: both fields must be filled
    if (empty($email) || empty($password)) {
        $error = 'Both email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.'; // Check email format
    } else {
        // Look up the user in the database by email
        // We also get their role name from the roles table (JOIN)
        $stmt = db()->prepare("
            SELECT u.id, u.name, u.email, u.password, u.status,
                   r.role_name
            FROM   users u
            JOIN   roles r ON r.id = u.role_id
            WHERE  u.email = ?
            LIMIT  1
        ");
        $stmt->bind_param('s', $email); // 's' = string type
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); // Get the row as an array
        $stmt->close();

        // ── Password verification ──────────────────────────────────────────
        // Support BOTH bcrypt-hashed passwords (password_hash) AND legacy
        // plain-text passwords that may still exist in older databases.
        // Strategy:
        //   1. Try password_verify() first (correct bcrypt check).
        //   2. If that fails, fall back to a plain-text comparison (legacy).
        //   3. If the plain-text match succeeds, silently re-hash and save
        //      the password as bcrypt so the account is upgraded immediately.
        // This means login works regardless of how the password was stored,
        // and all accounts migrate to secure hashed passwords on next login.
        $passwordOk = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                // Modern bcrypt hash — correct match
                $passwordOk = true;
            } elseif ($password === $user['password']) {
                // Legacy plain-text match — upgrade to bcrypt now
                $passwordOk = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upg = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
                $upg->bind_param('si', $newHash, $user['id']);
                $upg->execute();
                $upg->close();
            }
        }

        if (!$user || !$passwordOk) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is inactive. Contact an administrator.';
        } else {
            // ✅ Login successful!
            session_regenerate_id(true); // Security: create new session ID

            // Save user info into the session (remembered across pages)
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role_name'];

            // All users go to the same dashboard (it shows role-specific content)
            header('Location: ../dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — FitCore Pro</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    /* Login page specific styles */
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#0e0e0e; }
    .login-wrapper {
      display:grid; grid-template-columns:1fr 1fr;
      width:900px; max-width:96vw; min-height:580px;
      border-radius:20px; overflow:hidden;
      box-shadow:0 30px 80px rgba(0,0,0,.65); border:1px solid #222;
    }
    /* Left hero panel */
    .login-hero {
      background:linear-gradient(145deg,#0f2a17 0%,#1a4a2a 50%,#0d1f0d 100%);
      padding:60px 48px; display:flex; flex-direction:column;
      justify-content:space-between; position:relative; overflow:hidden;
    }
    .login-hero::before {
      content:''; position:absolute; inset:0;
      background:radial-gradient(ellipse 60% 50% at 30% 40%,rgba(76,175,80,.18) 0%,transparent 70%);
    }
    .hero-brand { position:relative; z-index:1; }
    .hero-brand .logo-mark { font-family:'Bebas Neue',sans-serif; font-size:52px; color:#4CAF50; letter-spacing:4px; line-height:1; }
    .hero-brand .tagline   { font-size:11px; color:rgba(255,255,255,.4); letter-spacing:2.5px; text-transform:uppercase; margin-top:6px; }
    .hero-stats { position:relative; z-index:1; }
    .hero-stat  { margin-bottom:26px; }
    .hero-stat .num { font-family:'Bebas Neue',sans-serif; font-size:44px; color:#fff; line-height:1; }
    .hero-stat .lbl { font-size:11px; color:rgba(255,255,255,.4); letter-spacing:1.5px; text-transform:uppercase; margin-top:3px; }
    /* Right form panel */
    .login-form-panel { background:#1a1a1a; padding:60px 48px; display:flex; flex-direction:column; justify-content:center; }
    .login-form-panel h2 { font-family:'Bebas Neue',sans-serif; font-size:36px; letter-spacing:3px; color:#fff; margin-bottom:4px; }
    .login-form-panel .sub { color:rgba(255,255,255,.35); font-size:13px; margin-bottom:32px; }
    /* Form fields */
    .field-group { margin-bottom:20px; }
    .field-group label { display:block; font-size:10px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:rgba(255,255,255,.45); margin-bottom:8px; }
    .field-wrap { position:relative; }
    .field-wrap input {
      width:100%; padding:13px 46px 13px 16px;
      background:#252525; border:1px solid #333; border-radius:10px;
      color:#e0e0e0; font-size:14px; font-family:'DM Sans',sans-serif;
      transition:border-color .2s,box-shadow .2s; box-sizing:border-box;
    }
    .field-wrap input:focus { outline:none; border-color:#4CAF50; box-shadow:0 0 0 3px rgba(76,175,80,.15); }
    .field-wrap input::placeholder { color:rgba(255,255,255,.2); }
    /* Show/hide password button */
    .eye-btn { position:absolute; right:13px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:4px; color:rgba(255,255,255,.25); }
    .eye-btn svg { width:17px; height:17px; display:block; }
    /* Login button */
    .btn-login { width:100%; padding:14px; background:linear-gradient(135deg,#4CAF50,#2e7d32); color:#fff; border:none; border-radius:10px; font-family:'DM Sans',sans-serif; font-size:13px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; cursor:pointer; transition:opacity .2s,transform .15s; margin-top:6px; }
    .btn-login:hover { opacity:.9; transform:translateY(-1px); }
    /* Error box */
    .alert-error { display:flex; align-items:center; gap:10px; background:rgba(239,83,80,.1); border:1px solid rgba(239,83,80,.28); color:#ef9a9a; padding:12px 16px; border-radius:9px; font-size:13px; margin-bottom:20px; }
    /* Demo accounts box */
    .demo-box { margin-top:22px; padding:14px 16px; background:#202020; border:1px solid #2a2a2a; border-radius:10px; }
    .demo-title { font-size:10px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:rgba(255,255,255,.3); margin-bottom:10px; }
    .demo-grid  { display:grid; grid-template-columns:1fr 1fr; gap:4px 16px; }
    .demo-row   { display:flex; align-items:center; gap:8px; padding:5px 6px; border-radius:6px; cursor:pointer; border:none; background:none; width:100%; text-align:left; }
    .demo-row:hover { background:rgba(76,175,80,.08); }
    .demo-row .dr { font-size:10px; font-weight:700; color:#4CAF50; width:52px; }
    .demo-row .de { font-size:11px; color:rgba(255,255,255,.4); }
    .info-note { margin-top:10px; padding:9px 12px; background:rgba(66,165,245,.07); border:1px solid rgba(66,165,245,.18); border-radius:7px; font-size:11px; color:rgba(66,165,245,.8); }
    /* Mobile: hide hero panel */
    @media(max-width:680px) {
      .login-wrapper { grid-template-columns:1fr; }
      .login-hero { display:none; }
      .login-form-panel { padding:44px 28px; }
    }
  </style>
</head>
<body>
<div class="login-wrapper">

  <!-- ── Left Hero Panel ── -->
  <div class="login-hero">
    <div class="hero-brand">
      <div class="logo-mark">FITCORE</div>
      <div class="tagline">Gym Management System</div>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><div class="num">500+</div><div class="lbl">Active Members</div></div>
      <div class="hero-stat"><div class="num">24</div><div class="lbl">Expert Trainers</div></div>
      <div class="hero-stat"><div class="num">50+</div><div class="lbl">Weekly Classes</div></div>
    </div>
  </div>

  <!-- ── Right Login Form ── -->
  <div class="login-form-panel">
    <h2>WELCOME BACK</h2>
    <p class="sub">Sign in to access your dashboard</p>

    <!-- Show error message if login failed -->
    <?php if ($error): ?>
      <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- The login form — sends POST request to this same page -->
    <form method="POST" autocomplete="off" novalidate>
      <div class="field-group">
        <label>Email Address</label>
        <div class="field-wrap">
          <input type="email" name="email" placeholder="Enter your email" required>
        </div>
      </div>
      <div class="field-group">
        <label>Password</label>
        <div class="field-wrap">
          <input type="password" id="pwField" name="password" placeholder="Enter your password" required>
          <!-- Toggle show/hide password -->
          <button type="button" class="eye-btn" id="eyeBtn">
            <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eyeClose" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-login">Sign In →</button>
    </form>

    <!-- Demo accounts (click to auto-fill the form) -->
    <div class="demo-box">
      <div class="demo-title">Demo accounts — click to fill</div>
      <div class="demo-grid">
        <button class="demo-row" onclick="fill('admin@gym.com')">   <span class="dr">Admin</span>   <span class="de">admin@gym.com</span></button>
        <button class="demo-row" onclick="fill('manager@gym.com')"> <span class="dr">Manager</span> <span class="de">manager@gym.com</span></button>
        <button class="demo-row" onclick="fill('trainer@gym.com')"> <span class="dr">Trainer</span> <span class="de">trainer@gym.com</span></button>
        <button class="demo-row" onclick="fill('staff@gym.com')">   <span class="dr">Staff</span>   <span class="de">staff@gym.com</span></button>
        <button class="demo-row" onclick="fill('member@gym.com')">  <span class="dr">Member</span>  <span class="de">member@gym.com</span></button>
      </div>
      <div class="info-note">ℹ Default password = email address.</div>
    </div>
  </div>

</div><!-- /.login-wrapper -->

<script>
// Toggle show/hide password field
var pw = document.getElementById('pwField');
document.getElementById('eyeBtn').onclick = function() {
    if (pw.type === 'password') {
        pw.type = 'text';
        document.getElementById('eyeOpen').style.display  = 'none';
        document.getElementById('eyeClose').style.display = '';
    } else {
        pw.type = 'password';
        document.getElementById('eyeOpen').style.display  = '';
        document.getElementById('eyeClose').style.display = 'none';
    }
};

// Fill email and password fields from demo buttons
function fill(email) {
    document.querySelector('[name=email]').value    = email;
    document.getElementById('pwField').value        = email; // default pw = email
}
</script>
</body>
</html>
