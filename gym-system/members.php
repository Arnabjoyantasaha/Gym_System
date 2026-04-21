<?php
// ──────────────────────────────────────────────────
//  members.php  —  Member & User Management
//
//  ADMIN   : Add users, toggle status, reset passwords,
//            delete users, update membership plan & trainer
//  MANAGER : View members, update plan & trainer
// ──────────────────────────────────────────────────

require_once 'config/database.php';
require_once 'includes/auth_guard.php';
requireAuth(['admin', 'manager']); // Only admin and manager can access

$db  = db();
$r   = role();
$msg = ''; $err = '';

// ════════════════════════════════════════════════
//  HANDLE FORM SUBMISSIONS (POST requests)
// ════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD NEW USER (admin only) ──────────────────
    if ($action === 'add_user' && $r === 'admin') {
        $name   = trim($_POST['name']   ?? '');
        $email  = trim($_POST['email']  ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $phone  = trim($_POST['phone']  ?? '');

        if (!$name || !$email || !$roleId) {
            $err = 'Name, email, and role are all required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid email address.';
        } else {
            // Check if email is already used
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $chk->bind_param('s', $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $err = 'This email address is already registered.';
            } else {
                // Default password = their email address (hashed)
                $hash = password_hash($email, PASSWORD_DEFAULT);
                $ins  = $db->prepare("INSERT INTO users (name,email,password,role_id,phone) VALUES (?,?,?,?,?)");
                $ins->bind_param('sssis', $name, $email, $hash, $roleId, $phone);

                if ($ins->execute()) {
                    $newId = $db->insert_id; // ID of the newly created user

                    // If role is Trainer (role_id=4), also create a trainers record
                    if ($roleId == 4) {
                        $spec = trim($_POST['specialization'] ?? '');
                        $ti   = $db->prepare("INSERT INTO trainers (user_id, specialization) VALUES (?, ?)");
                        $ti->bind_param('is', $newId, $spec);
                        $ti->execute();
                    }

                    // If role is Member (role_id=5), also create a members record
                    if ($roleId == 5) {
                        $plan = $_POST['membership_plan'] ?? 'monthly';
                        $join = date('Y-m-d');
                        // Calculate expiry date based on plan
                        $exp  = match($plan) {
                            'quarterly' => date('Y-m-d', strtotime('+3 months')),
                            'annual'    => date('Y-m-d', strtotime('+1 year')),
                            default     => date('Y-m-d', strtotime('+1 month')),
                        };
                        $mi = $db->prepare("INSERT INTO members (user_id,membership_plan,join_date,expiry_date) VALUES (?,?,?,?)");
                        $mi->bind_param('isss', $newId, $plan, $join, $exp);
                        $mi->execute();
                    }
                    $msg = "User <strong>" . e($name) . "</strong> created. Default password = their email address.";
                } else {
                    $err = 'Database error: ' . $db->error;
                }
            }
        }
    }

    // ── TOGGLE USER STATUS: active ↔ inactive (admin only) ──
    if ($action === 'toggle_status' && $r === 'admin') {
        $tid = (int)$_POST['user_id'];
        if ($tid === uid()) {
            $err = 'You cannot deactivate yourself.';
        } else {
            // IF status is 'active' → set to 'inactive', and vice versa
            $s = $db->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ?");
            $s->bind_param('i', $tid);
            $s->execute();
            $msg = 'User status updated.';
        }
    }

    // ── RESET A USER'S PASSWORD (admin only) ──
    if ($action === 'reset_password' && $r === 'admin') {
        $tid = (int)$_POST['user_id'];
        // Fetch the user's email to use as their reset password
        $nm  = $db->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
        $nm->bind_param('i', $tid);
        $nm->execute();
        $target = $nm->get_result()->fetch_assoc();
        if ($target) {
            $hash = password_hash($target['email'], PASSWORD_DEFAULT);
            $upd  = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param('si', $hash, $tid);
            $upd->execute();
            $msg = "Password for <strong>" . e($target['name']) . "</strong> reset to their email address.";
        }
    }

    // ── DELETE USER (admin only) ──
    if ($action === 'delete_user' && $r === 'admin') {
        $tid = (int)$_POST['user_id'];
        if ($tid === uid()) {
            $err = 'You cannot delete your own account.';
        } else {
            $s = $db->prepare("DELETE FROM users WHERE id = ?");
            $s->bind_param('i', $tid);
            $s->execute();
            $msg = 'User deleted.';
        }
    }

    // ── UPDATE MEMBER PLAN (admin + manager) ──
    if ($action === 'update_plan') {
        $mid  = (int)$_POST['member_id'];
        $plan = $_POST['membership_plan'] ?? 'monthly';
        $exp  = match($plan) {
            'quarterly' => date('Y-m-d', strtotime('+3 months')),
            'annual'    => date('Y-m-d', strtotime('+1 year')),
            default     => date('Y-m-d', strtotime('+1 month')),
        };
        $s = $db->prepare("UPDATE members SET membership_plan = ?, expiry_date = ? WHERE id = ?");
        $s->bind_param('ssi', $plan, $exp, $mid);
        $s->execute();
        $msg = 'Membership plan updated.';
    }

    // ── ASSIGN TRAINER TO MEMBER (admin + manager) ──
    if ($action === 'assign_trainer') {
        $mid = (int)$_POST['member_id'];
        $trainerVal = (int)$_POST['trainer_id'];
        if ($trainerVal > 0) {
            $s = $db->prepare("UPDATE members SET assigned_trainer = ? WHERE id = ?");
            $s->bind_param('ii', $trainerVal, $mid);
        } else {
            $s = $db->prepare("UPDATE members SET assigned_trainer = NULL WHERE id = ?");
            $s->bind_param('i', $mid);
        }
        $s->execute();
        $msg = 'Trainer assignment updated.';
    }
}

// ════════════════════════════════════════════════
//  LOAD DATA for the page
// ════════════════════════════════════════════════

// Admin sees ALL users; manager sees only members
if ($r === 'admin') {
    $users = $db->query("
        SELECT u.id, u.name, u.email, u.phone, u.status,
               u.created_at, r.role_name
        FROM   users u JOIN roles r ON r.id = u.role_id
        ORDER  BY u.id DESC
    ");
}

// Both admin and manager see member details
$members = $db->query("
    SELECT m.id AS mid, m.membership_plan, m.join_date, m.expiry_date,
           m.assigned_trainer,
           u.id AS uid, u.name, u.email, u.phone, u.status,
           tu.name AS trainer_name
    FROM   members m
    JOIN   users u  ON u.id = m.user_id
    LEFT JOIN trainers t  ON t.id = m.assigned_trainer
    LEFT JOIN users    tu ON tu.id = t.user_id
    ORDER  BY m.id DESC
");

// Trainer list for the "assign trainer" dropdown
$trainers = $db->query("SELECT t.id, u.name FROM trainers t JOIN users u ON u.id = t.user_id ORDER BY u.name");

// All roles for the "add user" dropdown
$roles = $db->query("SELECT * FROM roles ORDER BY id");

$pageTitle = 'Members';
$activeNav = 'members';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
<div class="page-header">
  <h1><?= $r === 'admin' ? 'USERS & MEMBERS' : 'MEMBERS' ?></h1>
  <p><?= $r === 'admin' ? 'Manage all user accounts and memberships.' : 'View and manage member memberships.' ?></p>
</div>

<!-- Alerts -->
<?php if ($msg): ?><div class="alert alert-success" data-dismiss="5000">✓ <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠ <?= e($err) ?></div><?php endif; ?>

<!-- ════════════════ ADMIN: ALL USERS TABLE ════════════════ -->
<?php if ($r === 'admin'): ?>
<div class="card mb-24">
  <div class="card-header">
    <h3>All User Accounts</h3>
    <!-- Button opens the Add User modal -->
    <button class="btn btn-primary" onclick="document.getElementById('modalAdd').style.display='flex'">+ Add User</button>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if ($users->num_rows === 0): ?>
        <tr class="no-records"><td colspan="7">No users found.</td></tr>
      <?php else: while ($u = $users->fetch_assoc()): ?>
        <tr>
          <td class="text-muted"><?= $u['id'] ?></td>
          <td style="font-weight:500"><?= e($u['name']) ?></td>
          <td class="text-muted"><?= e($u['email']) ?></td>
          <td><span class="badge badge-<?= e($u['role_name']) ?>"><?= e($u['role_name']) ?></span></td>
          <td><span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e($u['status']) ?></span></td>
          <td class="text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div class="d-flex gap-8" style="flex-wrap:wrap">
              <!-- Toggle active/inactive -->
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"  value="toggle_status">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-warning btn-sm" data-confirm="Toggle status for <?= e($u['name']) ?>?">
                  <?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?>
                </button>
              </form>
              <!-- Reset password to their email -->
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"  value="reset_password">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-info btn-sm" data-confirm="Reset password for <?= e($u['name']) ?> to their email?">🔑 Reset PW</button>
              </form>
              <!-- Delete (can't delete self) -->
              <?php if ($u['id'] !== uid()): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"  value="delete_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-danger btn-sm" data-confirm="Permanently delete <?= e($u['name']) ?>?">Delete</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; // admin users table ?>

<!-- ════════════════ MEMBERS TABLE (admin + manager) ════════════════ -->
<div class="card">
  <div class="card-header"><h3>Member Details</h3></div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>#</th><th>Member</th><th>Plan</th><th>Joined</th><th>Expires</th><th>Trainer</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php if ($members->num_rows === 0): ?>
        <tr class="no-records"><td colspan="8">No members found.</td></tr>
      <?php else: while ($m = $members->fetch_assoc()): ?>
        <tr>
          <td class="text-muted"><?= $m['mid'] ?></td>
          <td>
            <div style="font-weight:500"><?= e($m['name']) ?></div>
            <div class="text-muted text-small"><?= e($m['email']) ?></div>
          </td>
          <td><span class="badge badge-info"><?= e($m['membership_plan']) ?></span></td>
          <td class="text-muted"><?= e($m['join_date']) ?></td>
          <td>
            <?php $expired = strtotime($m['expiry_date']) < time(); ?>
            <span class="badge <?= $expired ? 'badge-danger' : 'badge-success' ?>"><?= e($m['expiry_date']) ?></span>
          </td>
          <td class="text-muted"><?= e($m['trainer_name'] ?? 'Unassigned') ?></td>
          <td><span class="badge <?= $m['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= e($m['status']) ?></span></td>
          <td>
            <!-- Edit button opens the modal and pre-fills it with this member's data -->
            <button class="btn btn-ghost btn-sm"
              onclick="openMemberModal(
                <?= $m['mid'] ?>,
                '<?= e(addslashes($m['name'])) ?>',
                '<?= e($m['membership_plan']) ?>',
                <?= (int)$m['assigned_trainer'] ?>
              )">Edit</button>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</main>

<!-- ════════════ MODAL: Add New User (admin only) ════════════ -->
<?php if ($r === 'admin'): ?>
<div id="modalAdd" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border-lite);border-radius:16px;width:520px;max-width:95vw;max-height:90vh;overflow-y:auto">
    <div style="padding:24px 28px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <div>
        <h2 class="font-display" style="font-size:22px;letter-spacing:2px">ADD NEW USER</h2>
        <p style="font-size:12px;color:var(--text-muted);margin-top:4px">Default password = their email address.</p>
      </div>
      <button onclick="document.getElementById('modalAdd').style.display='none'"
              style="background:none;border:none;color:var(--text-muted);font-size:22px;cursor:pointer">✕</button>
    </div>
    <form method="POST" style="padding:28px">
      <input type="hidden" name="action" value="add_user">
      <div class="form-row">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" class="form-control" required placeholder="Jane Doe">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" class="form-control" placeholder="555-0000">
        </div>
      </div>
      <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" class="form-control" required placeholder="jane@example.com">
      </div>
      <div class="form-group">
        <label>Role *</label>
        <!-- onchange() shows extra fields depending on role -->
        <select name="role_id" class="form-control" required onchange="showRoleFields(this.value)">
          <option value="">Select role…</option>
          <?php $roles->data_seek(0); while ($rv = $roles->fetch_assoc()): ?>
            <option value="<?= $rv['id'] ?>"><?= e(ucfirst($rv['role_name'])) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <!-- Extra field for trainers: specialization -->
      <div id="trainerFields" style="display:none" class="form-group">
        <label>Specialization</label>
        <input type="text" name="specialization" class="form-control" placeholder="e.g. Yoga, HIIT, Strength">
      </div>
      <!-- Extra field for members: membership plan -->
      <div id="memberFields" style="display:none" class="form-group">
        <label>Membership Plan</label>
        <select name="membership_plan" class="form-control">
          <option value="monthly">Monthly</option>
          <option value="quarterly">Quarterly</option>
          <option value="annual">Annual</option>
        </select>
      </div>
      <div class="d-flex gap-8" style="justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalAdd').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ════════════ MODAL: Edit Member Plan & Trainer ════════════ -->
<div id="memberModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border-lite);border-radius:16px;width:440px;max-width:95vw">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h2 class="font-display" style="font-size:20px;letter-spacing:2px">EDIT MEMBER</h2>
      <button onclick="document.getElementById('memberModal').style.display='none'"
              style="background:none;border:none;color:var(--text-muted);font-size:22px;cursor:pointer">✕</button>
    </div>
    <div style="padding:24px">
      <p id="modalName" style="color:var(--accent);font-weight:600;margin-bottom:20px"></p>

      <!-- Update Membership Plan -->
      <form method="POST" class="mb-16">
        <input type="hidden" name="action"    value="update_plan">
        <input type="hidden" name="member_id" id="mp_mid">
        <div class="form-group">
          <label>Membership Plan</label>
          <select name="membership_plan" id="mp_plan" class="form-control">
            <option value="monthly">Monthly</option>
            <option value="quarterly">Quarterly</option>
            <option value="annual">Annual</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Update Plan</button>
      </form>

      <div class="divider"></div>

      <!-- Assign Trainer -->
      <form method="POST">
        <input type="hidden" name="action"    value="assign_trainer">
        <input type="hidden" name="member_id" id="at_mid">
        <div class="form-group">
          <label>Assign Trainer</label>
          <select name="trainer_id" id="at_trainer" class="form-control">
            <option value="0">No Trainer</option>
            <?php $trainers->data_seek(0); while ($t = $trainers->fetch_assoc()): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-info btn-sm">Assign Trainer</button>
      </form>
    </div>
  </div>
</div>

<script>
// Show/hide role-specific fields in the Add User modal
function showRoleFields(roleId) {
    document.getElementById('trainerFields').style.display = (roleId == 4) ? 'block' : 'none';
    document.getElementById('memberFields').style.display  = (roleId == 5) ? 'block' : 'none';
}

// Open the Edit Member modal and pre-fill with selected member's data
function openMemberModal(memberId, name, plan, trainerId) {
    document.getElementById('memberModal').style.display = 'flex';
    document.getElementById('modalName').textContent     = name;
    document.getElementById('mp_mid').value              = memberId;
    document.getElementById('mp_plan').value             = plan;
    document.getElementById('at_mid').value              = memberId;
    document.getElementById('at_trainer').value          = trainerId || 0;
}
</script>

<?php require_once 'includes/footer.php'; ?>
