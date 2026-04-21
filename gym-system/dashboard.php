<?php
// ──────────────────────────────────────────────────
//  dashboard.php  —  Main dashboard (all roles)
//  Each role sees their own data and quick actions
// ──────────────────────────────────────────────────

require_once 'config/database.php';
require_once 'includes/auth_guard.php';

// All 5 roles can see the dashboard — no role restriction here
requireAuth();

$db  = db();
$uid = uid();
$r   = role();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

// ════════════════════════════════════════════════
//  DATA LOADING — Load only what each role needs
// ════════════════════════════════════════════════

// ── ADMIN dashboard data ──────────────────────────
if ($r === 'admin') {
    // Count totals for the stat cards
    $totalMembers    = $db->query("SELECT COUNT(*) FROM members")->fetch_row()[0];
    $totalTrainers   = $db->query("SELECT COUNT(*) FROM trainers")->fetch_row()[0];
    $todayPresent    = $db->query("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status='present'")->fetch_row()[0];
    $monthRevenue    = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(NOW()) AND status='paid'")->fetch_row()[0];
    $totalClasses    = $db->query("SELECT COUNT(*) FROM classes")->fetch_row()[0];
    $pendingPayments = $db->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetch_row()[0];

    // Last 5 members who joined
    $recentMembers = $db->query("
        SELECT u.name, u.email, m.membership_plan, m.join_date, m.expiry_date
        FROM   members m JOIN users u ON u.id = m.user_id
        ORDER  BY m.id DESC LIMIT 5
    ");

    // Last 5 payments
    $recentPayments = $db->query("
        SELECT u.name, p.amount, p.payment_date, p.payment_method, p.status
        FROM   payments p
        JOIN   members m ON m.id = p.member_id
        JOIN   users   u ON u.id = m.user_id
        ORDER  BY p.id DESC LIMIT 5
    ");

    // Today's check-ins (up to 8 rows)
    $todayAttList = $db->query("
        SELECT u.name, r.role_name, a.check_in, a.status
        FROM   attendance a
        JOIN   users u ON u.id = a.user_id
        JOIN   roles r ON r.id = u.role_id
        WHERE  a.date = CURDATE()
        ORDER  BY a.check_in ASC LIMIT 8
    ");
}

// ── MANAGER dashboard data ────────────────────────
if ($r === 'manager') {
    $totalMembers  = $db->query("SELECT COUNT(*) FROM members")->fetch_row()[0];
    $totalTrainers = $db->query("SELECT COUNT(*) FROM trainers")->fetch_row()[0];
    $totalStaff    = $db->query("SELECT COUNT(*) FROM users WHERE role_id=3 AND status='active'")->fetch_row()[0];
    $totalClasses  = $db->query("SELECT COUNT(*) FROM classes WHERE schedule_time >= NOW()")->fetch_row()[0];
    $todayPresent  = $db->query("SELECT COUNT(*) FROM attendance WHERE date=CURDATE() AND status='present'")->fetch_row()[0];
    $recentMembers = $db->query("
        SELECT u.name, u.email, m.membership_plan, m.join_date
        FROM   members m JOIN users u ON u.id = m.user_id
        ORDER  BY m.id DESC LIMIT 6
    ");
}

// ── STAFF dashboard data + actions ───────────────
if ($r === 'staff') {
    $msg = ''; $err = '';

    // Check if staff already checked in today
    $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = CURDATE() LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $myAtt = $stmt->get_result()->fetch_assoc(); // null if not checked in

    // Handle check-in / check-out / member check-in form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // Staff checks themselves in
        if ($action === 'checkin') {
            if ($myAtt) {
                $err = 'You already checked in today.';
            } else {
                $time = date('H:i:s');
                // If check-in is after 9:00 AM, mark as late
                $status = (date('H:i') > '09:00') ? 'late' : 'present';
                $s = $db->prepare("INSERT INTO attendance (user_id,date,check_in,status) VALUES (?,CURDATE(),?,?)");
                $s->bind_param('iss', $uid, $time, $status);
                $s->execute() ? $msg = 'Check-in recorded! (' . $status . ')' : $err = $db->error;
                // Refresh the attendance record
                $stmt->execute();
                $myAtt = $stmt->get_result()->fetch_assoc();
            }
        }

        // Staff checks themselves out
        if ($action === 'checkout') {
            $time = date('H:i:s');
            $s = $db->prepare("UPDATE attendance SET check_out = ? WHERE user_id = ? AND date = CURDATE()");
            $s->bind_param('si', $time, $uid);
            $s->execute() ? $msg = 'Check-out recorded!' : $err = $db->error;
        }

        // Staff checks a member in
        if ($action === 'member_checkin') {
            $mid = (int)$_POST['member_user_id'];
            // Check if member already checked in today
            $chk = $db->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = CURDATE()");
            $chk->bind_param('i', $mid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $err = 'This member is already checked in today.';
            } else {
                $time = date('H:i:s');
                $s    = $db->prepare("INSERT INTO attendance (user_id,date,check_in,status) VALUES (?,CURDATE(),?,'present')");
                $s->bind_param('is', $mid, $time);
                $s->execute() ? $msg = 'Member check-in recorded.' : $err = $db->error;
            }
        }
    }

    // Today's member check-ins for the table below
    $memberCheckins = $db->query("
        SELECT u.name, a.check_in, a.status
        FROM   attendance a JOIN users u ON u.id = a.user_id
        WHERE  a.date = CURDATE() AND u.role_id = 5
        ORDER  BY a.check_in DESC
    ");

    // Active members list for the dropdown
    $activeMembers = $db->query("SELECT id, name FROM users WHERE role_id=5 AND status='active' ORDER BY name");
}

// ── TRAINER dashboard data ────────────────────────
if ($r === 'trainer') {
    // Get this trainer's record ID
    $tStmt = $db->prepare("SELECT * FROM trainers WHERE user_id = ? LIMIT 1");
    $tStmt->bind_param('i', $uid);
    $tStmt->execute();
    $trainer = $tStmt->get_result()->fetch_assoc();
    $tid     = (int)($trainer['id'] ?? 0);

    // Members assigned to this trainer
    $members = $db->prepare("
        SELECT u.name, u.email, m.membership_plan, m.expiry_date,
               (SELECT br.bmi_value FROM bmi_records br WHERE br.member_id = m.id ORDER BY br.id DESC LIMIT 1) AS latest_bmi
        FROM   members m JOIN users u ON u.id = m.user_id
        WHERE  m.assigned_trainer = ?
        ORDER  BY u.name
    ");
    $members->bind_param('i', $tid);
    $members->execute();
    $members = $members->get_result();

    // Upcoming classes taught by this trainer
    $classes = $db->prepare("
        SELECT c.*, (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id) AS booked
        FROM   classes c WHERE c.trainer_id = ? AND c.schedule_time >= NOW()
        ORDER  BY c.schedule_time ASC LIMIT 8
    ");
    $classes->bind_param('i', $tid);
    $classes->execute();
    $classes = $classes->get_result();

    // Stat counts
    $memberCount = $db->prepare("SELECT COUNT(*) FROM members WHERE assigned_trainer = ?");
    $memberCount->bind_param('i', $tid);
    $memberCount->execute();
    $memberCount = $memberCount->get_result()->fetch_row()[0];

    $classCount = $db->prepare("SELECT COUNT(*) FROM classes WHERE trainer_id = ? AND schedule_time >= NOW()");
    $classCount->bind_param('i', $tid);
    $classCount->execute();
    $classCount = $classCount->get_result()->fetch_row()[0];
}

// ── MEMBER dashboard data ─────────────────────────
if ($r === 'member') {
    // Get this member's full record including trainer info
    $mStmt = $db->prepare("
        SELECT m.*, u.name, u.email, u.phone,
               tu.name AS trainer_name, t.specialization
        FROM   members m
        JOIN   users u  ON u.id = m.user_id
        LEFT JOIN trainers t  ON t.id = m.assigned_trainer
        LEFT JOIN users    tu ON tu.id = t.user_id
        WHERE  m.user_id = ? LIMIT 1
    ");
    $mStmt->bind_param('i', $uid);
    $mStmt->execute();
    $member = $mStmt->get_result()->fetch_assoc();
    $mid    = (int)($member['id'] ?? 0);

    // How many times did the member attend this month?
    $attStmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id=? AND MONTH(date)=MONTH(NOW()) AND status='present'");
    $attStmt->bind_param('i', $uid);
    $attStmt->execute();
    $attCount = $attStmt->get_result()->fetch_row()[0];

    // Latest BMI reading
    $bmiStmt = $db->prepare("SELECT * FROM bmi_records WHERE member_id=? ORDER BY id DESC LIMIT 1");
    $bmiStmt->bind_param('i', $mid);
    $bmiStmt->execute();
    $latestBMI = $bmiStmt->get_result()->fetch_assoc();

    // Total money paid
    $paidStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE member_id=? AND status='paid'");
    $paidStmt->bind_param('i', $mid);
    $paidStmt->execute();
    $totalPaid = $paidStmt->get_result()->fetch_row()[0];

    // Upcoming classes this member booked
    $bookings = $db->prepare("
        SELECT c.class_name, c.schedule_time, c.duration_min, tu.name AS trainer
        FROM   class_bookings cb
        JOIN   classes  c  ON c.id = cb.class_id
        JOIN   trainers t  ON t.id = c.trainer_id
        JOIN   users    tu ON tu.id = t.user_id
        WHERE  cb.member_id = ? AND c.schedule_time >= NOW()
        ORDER  BY c.schedule_time ASC LIMIT 5
    ");
    $bookings->bind_param('i', $mid);
    $bookings->execute();
    $bookings = $bookings->get_result();

    // Days until membership expires
    $daysLeft = $member['expiry_date'] ?
        (int)ceil((strtotime($member['expiry_date']) - time()) / 86400) : 0;
}

// Load header and sidebar
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">

<!-- ════════════════════════════════════
     ADMIN DASHBOARD
════════════════════════════════════ -->
<?php if ($r === 'admin'): ?>

<div class="page-header">
  <h1>DASHBOARD</h1>
  <p>Welcome back, <?= e($_SESSION['user_name']) ?>. Here's what's happening today.</p>
</div>

<!-- Six stat cards in a grid -->
<div class="stats-grid">
  <div class="stat-card accent"> <span class="stat-icon">🏋</span> <div class="stat-value"><?= $totalMembers    ?></div><div class="stat-label">Total Members</div></div>
  <div class="stat-card info">   <span class="stat-icon">💪</span> <div class="stat-value"><?= $totalTrainers   ?></div><div class="stat-label">Active Trainers</div></div>
  <div class="stat-card warning"><span class="stat-icon">📋</span> <div class="stat-value"><?= $todayPresent    ?></div><div class="stat-label">Today Present</div></div>
  <div class="stat-card accent"> <span class="stat-icon">💰</span> <div class="stat-value">$<?= number_format($monthRevenue, 0) ?></div><div class="stat-label">Monthly Revenue</div></div>
  <div class="stat-card info">   <span class="stat-icon">📅</span> <div class="stat-value"><?= $totalClasses    ?></div><div class="stat-label">Total Classes</div></div>
  <div class="stat-card danger"> <span class="stat-icon">⚠</span>  <div class="stat-value"><?= $pendingPayments ?></div><div class="stat-label">Pending Payments</div></div>
</div>

<div class="grid-2">
  <!-- Recent Members table -->
  <div class="card">
    <div class="card-header">
      <h3>Recent Members</h3>
      <a href="<?= APP_URL ?>/members.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Name</th><th>Plan</th><th>Joined</th><th>Expires</th></tr></thead>
        <tbody>
        <?php while ($row = $recentMembers->fetch_assoc()): ?>
          <tr>
            <td>
              <div style="font-weight:500"><?= e($row['name']) ?></div>
              <div class="text-muted text-small"><?= e($row['email']) ?></div>
            </td>
            <td><span class="badge badge-info"><?= e($row['membership_plan']) ?></span></td>
            <td class="text-muted"><?= e($row['join_date']) ?></td>
            <td>
              <?php $cls = strtotime($row['expiry_date']) < time() ? 'badge-danger' : 'badge-success'; ?>
              <span class="badge <?= $cls ?>"><?= e($row['expiry_date']) ?></span>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Today's Attendance table -->
  <div class="card">
    <div class="card-header">
      <h3>Today's Attendance</h3>
      <span class="badge badge-success"><?= $todayPresent ?> Present</span>
    </div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Name</th><th>Role</th><th>Check-in</th><th>Status</th></tr></thead>
        <tbody>
        <?php
        $sc = ['present'=>'badge-success','absent'=>'badge-danger','late'=>'badge-warning'];
        while ($row = $todayAttList->fetch_assoc()):
        ?>
          <tr>
            <td style="font-weight:500"><?= e($row['name']) ?></td>
            <td><span class="badge badge-<?= e($row['role_name']) ?>"><?= e($row['role_name']) ?></span></td>
            <td class="text-muted"><?= e($row['check_in'] ?? '—') ?></td>
            <td><span class="badge <?= $sc[$row['status']] ?? 'badge-neutral' ?>"><?= e($row['status']) ?></span></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Recent Payments table -->
<div class="card">
  <div class="card-header">
    <h3>Recent Payments</h3>
    <a href="<?= APP_URL ?>/payments.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Member</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th></tr></thead>
      <tbody>
      <?php
      $pc = ['paid'=>'badge-success','pending'=>'badge-warning','failed'=>'badge-danger'];
      while ($row = $recentPayments->fetch_assoc()):
      ?>
        <tr>
          <td style="font-weight:500"><?= e($row['name']) ?></td>
          <td class="text-accent fw-600">$<?= number_format($row['amount'], 2) ?></td>
          <td class="text-muted"><?= e($row['payment_date']) ?></td>
          <td><?= e(ucfirst($row['payment_method'])) ?></td>
          <td><span class="badge <?= $pc[$row['status']] ?? 'badge-neutral' ?>"><?= e($row['status']) ?></span></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════
     MANAGER DASHBOARD
════════════════════════════════════ -->
<?php elseif ($r === 'manager'): ?>

<div class="page-header">
  <h1>MANAGER DASHBOARD</h1>
  <p>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e($_SESSION['user_name']) ?>.</p>
</div>

<div class="stats-grid">
  <div class="stat-card accent"> <span class="stat-icon">🏋</span><div class="stat-value"><?= $totalMembers  ?></div><div class="stat-label">Total Members</div></div>
  <div class="stat-card info">   <span class="stat-icon">💪</span><div class="stat-value"><?= $totalTrainers ?></div><div class="stat-label">Trainers</div></div>
  <div class="stat-card warning"><span class="stat-icon">👔</span><div class="stat-value"><?= $totalStaff    ?></div><div class="stat-label">Active Staff</div></div>
  <div class="stat-card accent"> <span class="stat-icon">📅</span><div class="stat-value"><?= $totalClasses  ?></div><div class="stat-label">Upcoming Classes</div></div>
  <div class="stat-card info">   <span class="stat-icon">📋</span><div class="stat-value"><?= $todayPresent  ?></div><div class="stat-label">Today Present</div></div>
</div>

<div class="card">
  <div class="card-header">
    <h3>Recently Joined Members</h3>
    <a href="<?= APP_URL ?>/members.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Plan</th><th>Joined</th></tr></thead>
      <tbody>
      <?php while ($m = $recentMembers->fetch_assoc()): ?>
        <tr>
          <td style="font-weight:500"><?= e($m['name']) ?></td>
          <td class="text-muted"><?= e($m['email']) ?></td>
          <td><span class="badge badge-info"><?= e($m['membership_plan']) ?></span></td>
          <td class="text-muted"><?= e($m['join_date']) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════
     STAFF DASHBOARD
════════════════════════════════════ -->
<?php elseif ($r === 'staff'): ?>

<div class="page-header">
  <h1>STAFF DASHBOARD</h1>
  <p>Welcome, <?= e($_SESSION['user_name']) ?>. Manage your shift and member check-ins.</p>
</div>

<!-- Show success or error messages -->
<?php if ($msg): ?><div class="alert alert-success" data-dismiss="4000">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠ <?= e($err) ?></div><?php endif; ?>

<div class="grid-2">
  <!-- MY ATTENDANCE: shows check-in time or a button to check in -->
  <div class="card">
    <div class="card-header"><h3>My Attendance Today</h3></div>
    <div class="card-body">
      <?php if ($myAtt): ?>
        <!-- Already checked in — show times -->
        <div style="text-align:center;padding:20px 0">
          <div style="font-family:'Bebas Neue',sans-serif;font-size:48px;color:var(--accent)"><?= e($myAtt['check_in']) ?></div>
          <div class="text-muted" style="font-size:12px;letter-spacing:1px;text-transform:uppercase">Check-in Time</div>
          <?php if ($myAtt['check_out']): ?>
            <div style="font-family:'Bebas Neue',sans-serif;font-size:28px;color:var(--text-muted);margin-top:12px"><?= e($myAtt['check_out']) ?></div>
            <div class="text-muted" style="font-size:12px;letter-spacing:1px;text-transform:uppercase">Check-out Time</div>
          <?php else: ?>
            <!-- Not checked out yet — show checkout button -->
            <form method="POST" style="margin-top:20px">
              <input type="hidden" name="action" value="checkout">
              <button type="submit" class="btn btn-warning">Check Out Now</button>
            </form>
          <?php endif; ?>
          <span class="badge badge-<?= $myAtt['status'] === 'present' ? 'success' : 'warning' ?>" style="margin-top:12px"><?= e($myAtt['status']) ?></span>
        </div>
      <?php else: ?>
        <!-- Not checked in yet — show check-in button -->
        <div style="text-align:center;padding:20px 0">
          <p class="text-muted mb-16">You have not checked in today.</p>
          <form method="POST">
            <input type="hidden" name="action" value="checkin">
            <button type="submit" class="btn btn-primary btn-lg">Check In Now</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- MEMBER CHECK-IN: staff records a member's attendance -->
  <div class="card">
    <div class="card-header"><h3>Member Check-in</h3></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="member_checkin">
        <div class="form-group">
          <label>Select Member</label>
          <select name="member_user_id" class="form-control" required>
            <option value="">Choose member…</option>
            <?php while ($m = $activeMembers->fetch_assoc()): ?>
              <option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Record Check-in</button>
      </form>
    </div>
  </div>
</div>

<!-- Table of members checked in today -->
<div class="card">
  <div class="card-header">
    <h3>Members Checked In Today</h3>
    <span class="badge badge-success"><?= $memberCheckins->num_rows ?> check-ins</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Member</th><th>Check-in Time</th><th>Status</th></tr></thead>
      <tbody>
      <?php if ($memberCheckins->num_rows === 0): ?>
        <tr class="no-records"><td colspan="3">No members checked in today yet.</td></tr>
      <?php else: while ($row = $memberCheckins->fetch_assoc()): ?>
        <tr>
          <td style="font-weight:500"><?= e($row['name']) ?></td>
          <td class="text-muted"><?= e($row['check_in']) ?></td>
          <td><span class="badge badge-success"><?= e($row['status']) ?></span></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════
     TRAINER DASHBOARD
════════════════════════════════════ -->
<?php elseif ($r === 'trainer'): ?>

<div class="page-header">
  <h1>TRAINER DASHBOARD</h1>
  <p>Welcome, <?= e($_SESSION['user_name']) ?>. Specialization: <?= e($trainer['specialization'] ?? 'Not set') ?></p>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat-card accent"><span class="stat-icon">🏋</span><div class="stat-value"><?= $memberCount ?></div><div class="stat-label">My Members</div></div>
  <div class="stat-card info">  <span class="stat-icon">📅</span><div class="stat-value"><?= $classCount  ?></div><div class="stat-label">Upcoming Classes</div></div>
  <div class="stat-card warning"><span class="stat-icon">💪</span>
    <div class="stat-value" style="font-size:20px;line-height:1.2"><?= e($trainer['specialization'] ?? '—') ?></div>
    <div class="stat-label">Specialization</div>
  </div>
</div>

<div class="grid-2">
  <!-- Assigned members -->
  <div class="card">
    <div class="card-header">
      <h3>My Members</h3>
      <a href="<?= APP_URL ?>/trainer_members.php" class="btn btn-ghost btn-sm">Full View</a>
    </div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Member</th><th>Plan</th><th>BMI</th><th>Expires</th></tr></thead>
        <tbody>
        <?php if ($members->num_rows === 0): ?>
          <tr class="no-records"><td colspan="4">No members assigned yet.</td></tr>
        <?php else: while ($m = $members->fetch_assoc()): ?>
          <tr>
            <td>
              <div style="font-weight:500"><?= e($m['name']) ?></div>
              <div class="text-muted text-small"><?= e($m['email']) ?></div>
            </td>
            <td><span class="badge badge-info"><?= e($m['membership_plan']) ?></span></td>
            <td>
              <?php if ($m['latest_bmi']): ?>
                <span style="<?= bmiColor((float)$m['latest_bmi']) ?>;font-weight:600"><?= number_format($m['latest_bmi'], 1) ?></span>
                <span class="text-muted text-small"> <?= bmiCategory((float)$m['latest_bmi']) ?></span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= strtotime($m['expiry_date']) < time() ? 'badge-danger' : 'badge-success' ?>">
                <?= e($m['expiry_date']) ?>
              </span>
            </td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Upcoming classes -->
  <div class="card">
    <div class="card-header"><h3>My Upcoming Classes</h3></div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Class</th><th>Date/Time</th><th>Booked</th><th>Capacity</th></tr></thead>
        <tbody>
        <?php if ($classes->num_rows === 0): ?>
          <tr class="no-records"><td colspan="4">No upcoming classes.</td></tr>
        <?php else: while ($c = $classes->fetch_assoc()): ?>
          <tr>
            <td style="font-weight:500"><?= e($c['class_name']) ?></td>
            <td class="text-muted"><?= date('M j, g:i A', strtotime($c['schedule_time'])) ?></td>
            <td><span class="badge badge-info"><?= $c['booked'] ?></span></td>
            <td class="text-muted"><?= $c['capacity'] ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════
     MEMBER DASHBOARD
════════════════════════════════════ -->
<?php elseif ($r === 'member'): ?>

<div class="page-header">
  <h1>MY DASHBOARD</h1>
  <p>Welcome back, <?= e($member['name'] ?? 'Member') ?>. Here's your fitness overview.</p>
</div>

<!-- Stat cards: days remaining changes color based on urgency -->
<div class="stats-grid">
  <div class="stat-card <?= $daysLeft <= 7 ? 'danger' : ($daysLeft <= 30 ? 'warning' : 'accent') ?>">
    <span class="stat-icon">🎫</span>
    <div class="stat-value"><?= $daysLeft ?></div>
    <div class="stat-label">Days Remaining</div>
    <div style="font-size:11px;color:var(--text-muted);margin-top:4px"><?= e(ucfirst($member['membership_plan'] ?? '')) ?> plan</div>
  </div>
  <div class="stat-card info">
    <span class="stat-icon">📋</span>
    <div class="stat-value"><?= $attCount ?></div>
    <div class="stat-label">Sessions This Month</div>
  </div>
  <div class="stat-card accent">
    <span class="stat-icon">💳</span>
    <div class="stat-value">$<?= number_format($totalPaid, 0) ?></div>
    <div class="stat-label">Total Paid</div>
  </div>
  <?php if ($latestBMI): ?>
  <div class="stat-card">
    <span class="stat-icon">⚖️</span>
    <div class="stat-value" style="<?= bmiColor((float)$latestBMI['bmi_value']) ?>"><?= number_format($latestBMI['bmi_value'], 1) ?></div>
    <div class="stat-label">Latest BMI</div>
    <div style="font-size:11px;margin-top:4px;<?= bmiColor((float)$latestBMI['bmi_value']) ?>"><?= bmiCategory((float)$latestBMI['bmi_value']) ?></div>
  </div>
  <?php endif; ?>
</div>

<div class="grid-2">
  <!-- Membership details card -->
  <div class="card">
    <div class="card-header"><h3>Membership Details</h3></div>
    <div class="card-body">
      <div style="display:grid;gap:14px">
        <?php
        // Loop through details array to display each row
        $details = [
            'Plan'    => ucfirst($member['membership_plan'] ?? '—'),
            'Joined'  => $member['join_date']    ?? '—',
            'Expires' => $member['expiry_date']  ?? '—',
            'Email'   => $member['email']        ?? '—',
            'Phone'   => $member['phone']        ?? '—',
        ];
        foreach ($details as $label => $value):
        ?>
          <div style="display:flex;justify-content:space-between;padding-bottom:10px;border-bottom:1px solid var(--border)">
            <span class="text-muted"><?= $label ?></span>
            <span style="font-weight:500"><?= e($value) ?></span>
          </div>
        <?php endforeach; ?>
        <!-- Show "Renew" button if membership expires in 30 days or less -->
        <?php if ($daysLeft <= 30): ?>
          <a href="<?= APP_URL ?>/payments.php" class="btn btn-primary btn-block">🔄 Renew Membership</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Assigned trainer card -->
  <div class="card">
    <div class="card-header"><h3>My Trainer</h3></div>
    <div class="card-body">
      <?php if ($member['trainer_name']): ?>
        <!-- Trainer is assigned — show their avatar and name -->
        <div style="text-align:center;padding:10px 0">
          <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-dark));display:flex;align-items:center;justify-content:center;font-family:'Bebas Neue',sans-serif;font-size:28px;color:#fff;margin:0 auto 16px">
            <?= strtoupper(substr($member['trainer_name'], 0, 1)) ?>
          </div>
          <div style="font-size:20px;font-weight:600"><?= e($member['trainer_name']) ?></div>
          <div class="text-muted" style="font-size:12px;margin-top:4px"><?= e($member['specialization'] ?? '') ?></div>
        </div>
      <?php else: ?>
        <!-- No trainer yet -->
        <div style="text-align:center;padding:30px 0;color:var(--text-muted)">
          <div style="font-size:40px;margin-bottom:8px">💪</div>
          <div>No trainer assigned yet.</div>
          <div class="text-small mt-8">Contact admin or manager.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Upcoming class bookings table -->
<div class="card">
  <div class="card-header">
    <h3>My Upcoming Classes</h3>
    <a href="<?= APP_URL ?>/classes.php" class="btn btn-ghost btn-sm">Browse All</a>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Class</th><th>Trainer</th><th>Date &amp; Time</th><th>Duration</th></tr></thead>
      <tbody>
      <?php if ($bookings->num_rows === 0): ?>
        <tr class="no-records"><td colspan="4">No upcoming bookings. <a href="<?= APP_URL ?>/classes.php">Book a class →</a></td></tr>
      <?php else: while ($b = $bookings->fetch_assoc()): ?>
        <tr>
          <td style="font-weight:500"><?= e($b['class_name']) ?></td>
          <td class="text-muted"><?= e($b['trainer']) ?></td>
          <td><?= date('M j, Y g:i A', strtotime($b['schedule_time'])) ?></td>
          <td class="text-muted"><?= $b['duration_min'] ?> min</td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; // end role checks ?>

</main>

<?php require_once 'includes/footer.php'; ?>
