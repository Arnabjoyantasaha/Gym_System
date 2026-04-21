<?php
// ──────────────────────────────────────────────────
//  auth/change_password.php  —  Change password
//  All roles can change their OWN password
//  Admin can also reset any OTHER user's password
// ──────────────────────────────────────────────────

require_once '../config/database.php';
require_once '../includes/auth_guard.php';

// requireAuth() with no arguments = any logged-in user can access
requireAuth();

$db  = db();
$msg = ''; // Success messages
$err = ''; // Error messages
$adminMsg = ''; $adminErr = '';

// ── Handle: Change MY OWN Password ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'self') {
    $current = $_POST['current_password'] ?? '';
    $newPw   = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Fetch the current password from database
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', uid());
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Validate each condition
    // Support both bcrypt-hashed and legacy plain-text passwords
    $currentOk = password_verify($current, $row['password']) || $current === $row['password'];
    if (!$currentOk) {
        $err = 'Your current password is incorrect.';
    } elseif (empty($newPw)) {
        $err = 'New password cannot be empty.';
    } elseif ($newPw !== $confirm) {
        $err = 'New passwords do not match.';
    } elseif ($newPw === $current) {
        $err = 'New password must be different from your current one.';
    } else {
        // Hash the new password securely with bcrypt before saving
        $hashedPw = password_hash($newPw, PASSWORD_DEFAULT);
        $upd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param('si', $hashedPw, uid());
        $upd->execute();
        $upd->close();

        $msg = 'Password changed successfully!';
    }
}

// ── Handle: Admin Resets ANOTHER User's Password ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_reset' && role() === 'admin') {
    $targetId = (int)($_POST['target_user_id']      ?? 0);
    $newPw    =       $_POST['admin_new_password']   ?? '';
    $confirm  =       $_POST['admin_confirm']        ?? '';

    if ($targetId <= 0) {
        $adminErr = 'Please select a user.';
    } elseif ($targetId === uid()) {
        $adminErr = 'Use the left form to change your own password.';
    } elseif (empty($newPw)) {
        $adminErr = 'Password cannot be empty.';
    } elseif ($newPw !== $confirm) {
        $adminErr = 'Passwords do not match.';
    } else {
        $hashedPw = password_hash($newPw, PASSWORD_DEFAULT);
        $upd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param('si', $hashedPw, $targetId);
        $upd->execute();

        // Get target user's name for the success message
        $nm = $db->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
        $nm->bind_param('i', $targetId);
        $nm->execute();
        $name = $nm->get_result()->fetch_assoc()['name'] ?? 'User';

        $adminMsg = "Password for <strong>" . e($name) . "</strong> has been reset.";
    }
}

// ── Load all users for admin dropdown (excluding self) ──
$allUsers = null;
if (role() === 'admin') {
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, r.role_name
        FROM   users u JOIN roles r ON r.id = u.role_id
        WHERE  u.id != ?
        ORDER  BY r.id, u.name
    ");
    $stmt->bind_param('i', uid());
    $stmt->execute();
    $allUsers = $stmt->get_result();
}

$pageTitle = 'Change Password';
$activeNav = 'change_password';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">

<!-- ── NORMAL CHANGE: Standard page with sidebar ── -->
<div class="page-header">
  <h1>CHANGE PASSWORD</h1>
  <p>Manage your password<?= role() === 'admin' ? ' and reset other users\' passwords' : '' ?>.</p>
</div>

<div class="<?= role() === 'admin' ? 'grid-2' : '' ?>" style="max-width:<?= role() === 'admin' ? '100%' : '520px' ?>">

  <!-- Change OWN password form -->
  <div class="card">
    <div class="card-header"><h3>🔑 Change My Password</h3></div>
    <div class="card-body">
      <?php if ($err): ?><div class="alert alert-error mb-16">⚠ <?= e($err) ?></div><?php endif; ?>
      <?php if ($msg): ?><div class="alert alert-success mb-16">✓ <?= e($msg) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="self">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
          <label>New Password <span style="color:var(--text-muted);font-size:10px">(min 8 chars)</span></label>
          <input type="password" name="new_password" id="np1" class="form-control" required minlength="8">
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" id="np2" class="form-control" required minlength="8">
        </div>
        <!-- Password strength indicator -->
        <div style="margin-bottom:18px">
          <div style="height:4px;background:#2a2a2a;border-radius:2px;overflow:hidden">
            <div id="strengthBar" style="height:100%;width:0;border-radius:2px;transition:width .3s,background .3s"></div>
          </div>
          <div id="strengthLabel" style="font-size:10px;color:var(--text-muted);margin-top:5px"></div>
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </form>
    </div>
  </div>

  <!-- Admin-only: Reset any user's password -->
  <?php if (role() === 'admin'): ?>
  <div class="card">
    <div class="card-header"><h3>🛡 Reset Any User's Password</h3></div>
    <div class="card-body">
      <?php if ($adminErr): ?><div class="alert alert-error mb-16">⚠ <?= e($adminErr) ?></div><?php endif; ?>
      <?php if ($adminMsg): ?><div class="alert alert-success mb-16">✓ <?= $adminMsg ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="admin_reset">
        <div class="form-group">
          <label>Select User</label>
          <select name="target_user_id" class="form-control" required>
            <option value="">— Choose a user —</option>
            <?php
            // Group users by role in the dropdown
            $currentGroup = '';
            while ($u = $allUsers->fetch_assoc()):
                if ($currentGroup !== $u['role_name']) {
                    if ($currentGroup) echo '</optgroup>';
                    echo '<optgroup label="' . e(ucfirst($u['role_name'])) . 's">';
                    $currentGroup = $u['role_name'];
                }
            ?>
              <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
            <?php endwhile; ?>
            <?php if ($currentGroup) echo '</optgroup>'; ?>
          </select>
        </div>
        <div class="form-group">
          <label>New Password <span style="color:var(--text-muted);font-size:10px">(min 8 chars)</span></label>
          <input type="password" name="admin_new_password" class="form-control" required minlength="8">
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="admin_confirm" class="form-control" required minlength="8">
        </div>
        <button type="submit" class="btn btn-warning"
                data-confirm="Reset this user's password?">Reset Password</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /.grid -->

</main>

<script>
// Password strength meter — updates as you type
(function() {
    var bar   = document.getElementById('strengthBar');
    var label = document.getElementById('strengthLabel');
    var np1   = document.getElementById('np1');
    var np2   = document.getElementById('np2');
    if (!np1 || !bar) return;

    np1.addEventListener('input', function() {
        var v = this.value, score = 0;
        if (v.length >= 8)           score++; // Long enough
        if (v.length >= 12)          score++; // Even longer
        if (/[A-Z]/.test(v))         score++; // Has uppercase
        if (/[0-9]/.test(v))         score++; // Has number
        if (/[^A-Za-z0-9]/.test(v))  score++; // Has special char

        var labels = ['','Weak','Fair','Good','Strong','Very Strong'];
        var colors = ['','#ef5350','#ffa726','#42a5f5','#4CAF50','#4CAF50'];
        var pcts   = [0, 20, 40, 60, 80, 100];

        bar.style.width      = pcts[score]  + '%';
        bar.style.background = colors[score];
        label.textContent    = labels[score];
    });

    // Highlight confirm field red if passwords don't match
    if (np2) {
        np2.addEventListener('input', function() {
            this.style.borderColor = (this.value && this.value !== np1.value) ? '#ef5350' : '';
        });
    }
})();
</script>

<?php require_once '../includes/footer.php'; ?>
