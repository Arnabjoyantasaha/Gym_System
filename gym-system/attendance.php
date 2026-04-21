<?php
// ──────────────────────────────────────────────────
//  attendance.php  —  Attendance management
//
//  ADMIN / MANAGER : Mark attendance for anyone + view records by date
//  STAFF           : View own attendance records
//  MEMBER          : View own attendance history
// ──────────────────────────────────────────────────

require_once 'config/database.php';
require_once 'includes/auth_guard.php';

// All 4 roles can view attendance (different perspectives)
requireAuth(['admin', 'manager', 'staff', 'member']);

$db  = db();
$uid = uid();
$r   = role();

$msg = ''; $err = '';

// ════════════════════════════════════════════════
//  ADMIN / MANAGER: Handle "Mark Attendance" form
// ════════════════════════════════════════════════
if (in_array($r, ['admin', 'manager']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUid = (int)$_POST['user_id'];
    $date      = $_POST['date']      ?: date('Y-m-d');
    $status    = $_POST['status']    ?? 'present';
    $checkIn   = $_POST['check_in']  ?: null;
    $checkOut  = $_POST['check_out'] ?: null;

    // Check if a record already exists for this user+date (UPSERT logic)
    $chk = $db->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ? LIMIT 1");
    $chk->bind_param('is', $targetUid, $date);
    $chk->execute();

    if ($chk->get_result()->num_rows > 0) {
        // Record exists — UPDATE it
        $s = $db->prepare("UPDATE attendance SET status=?, check_in=?, check_out=? WHERE user_id=? AND date=?");
        $s->bind_param('sssis', $status, $checkIn, $checkOut, $targetUid, $date);
    } else {
        // No record — INSERT a new one
        $s = $db->prepare("INSERT INTO attendance (user_id,date,check_in,check_out,status) VALUES (?,?,?,?,?)");
        $s->bind_param('issss', $targetUid, $date, $checkIn, $checkOut, $status);
    }
    $s->execute() ? $msg = 'Attendance saved successfully.' : $err = $db->error;
}

// ════════════════════════════════════════════════
//  Load attendance records to display
// ════════════════════════════════════════════════

// Badge colors for attendance status
$statusBadge = ['present' => 'badge-success', 'absent' => 'badge-danger', 'late' => 'badge-warning'];

if (in_array($r, ['admin', 'manager'])) {
    // Admin/Manager: view ALL records for a selected date
    $dateFilter = $_GET['date'] ?? date('Y-m-d'); // Default = today
    $safe       = mysqli_real_escape_string($db, $dateFilter); // Escape for query safety

    $attendance = $db->query("
        SELECT a.*, u.name, r.role_name
        FROM   attendance a
        JOIN   users u ON u.id = a.user_id
        JOIN   roles r ON r.id = u.role_id
        WHERE  a.date = '$safe'
        ORDER  BY a.check_in ASC
    ");

    // Count present/absent/late for that date
    $present = $db->query("SELECT COUNT(*) FROM attendance WHERE date='$safe' AND status='present'")->fetch_row()[0];
    $absent  = $db->query("SELECT COUNT(*) FROM attendance WHERE date='$safe' AND status='absent'")->fetch_row()[0];
    $late    = $db->query("SELECT COUNT(*) FROM attendance WHERE date='$safe' AND status='late'")->fetch_row()[0];

    // All active users for the mark-attendance dropdown
    $allUsers = $db->query("SELECT id, name FROM users WHERE status='active' ORDER BY name");

} else {
    // Staff/Member: view only THEIR OWN records (last 30 records)
    $myAttendance = $db->prepare("
        SELECT * FROM attendance
        WHERE  user_id = ?
        ORDER  BY date DESC, id DESC
        LIMIT  30
    ");
    $myAttendance->bind_param('i', $uid);
    $myAttendance->execute();
    $myAttendance = $myAttendance->get_result();

    // Total presents this month for the stat card
    $monthPresent = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id=? AND MONTH(date)=MONTH(NOW()) AND status='present'");
    $monthPresent->bind_param('i', $uid);
    $monthPresent->execute();
    $monthPresent = $monthPresent->get_result()->fetch_row()[0];
}

$pageTitle = 'Attendance';
$activeNav = 'attendance';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">

<!-- ════════════════════════════════════
     ADMIN / MANAGER VIEW
════════════════════════════════════ -->
<?php if (in_array($r, ['admin', 'manager'])): ?>

<div class="page-header">
  <h1>ATTENDANCE</h1>
  <p>Track daily check-ins and check-outs for all staff and members.</p>
</div>

<!-- Alerts -->
<?php if ($msg): ?><div class="alert alert-success" data-dismiss="4000">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠ <?= e($err) ?></div><?php endif; ?>

<!-- Summary stats for the selected date -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card accent"> <span class="stat-icon">✅</span><div class="stat-value"><?= $present ?></div><div class="stat-label">Present</div></div>
  <div class="stat-card danger"> <span class="stat-icon">❌</span><div class="stat-value"><?= $absent  ?></div><div class="stat-label">Absent</div></div>
  <div class="stat-card warning"><span class="stat-icon">⏰</span><div class="stat-value"><?= $late    ?></div><div class="stat-label">Late</div></div>
</div>

<div class="grid-2">
  <!-- Mark Attendance Form -->
  <div class="card">
    <div class="card-header"><h3>Mark Attendance</h3></div>
    <div class="card-body">
      <form method="POST">
        <div class="form-group">
          <label>Person *</label>
          <select name="user_id" class="form-control" required>
            <option value="">Select person…</option>
            <?php while ($u = $allUsers->fetch_assoc()): ?>
              <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Check-in Time</label>
            <input type="time" name="check_in" class="form-control" value="<?= date('H:i') ?>">
          </div>
          <div class="form-group">
            <label>Check-out Time</label>
            <input type="time" name="check_out" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <option value="present">Present</option>
            <option value="absent">Absent</option>
            <option value="late">Late</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Save Attendance</button>
      </form>
    </div>
  </div>

  <!-- View Records by Date -->
  <div class="card">
    <div class="card-header">
      <h3>Records</h3>
      <!-- Date filter form — GET request to reload page with selected date -->
      <form method="GET" style="display:flex;gap:8px">
        <input type="date" name="date" class="form-control" value="<?= e($dateFilter) ?>" style="width:160px">
        <button type="submit" class="btn btn-ghost btn-sm">View</button>
      </form>
    </div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Name</th><th>Role</th><th>In</th><th>Out</th><th>Status</th></tr></thead>
        <tbody>
        <?php if ($attendance->num_rows === 0): ?>
          <tr class="no-records"><td colspan="5">No records for <?= e($dateFilter) ?>.</td></tr>
        <?php else: while ($a = $attendance->fetch_assoc()): ?>
          <tr>
            <td style="font-weight:500"><?= e($a['name']) ?></td>
            <td><span class="badge badge-<?= e($a['role_name']) ?>"><?= e($a['role_name']) ?></span></td>
            <td class="text-muted"><?= e($a['check_in']  ?? '—') ?></td>
            <td class="text-muted"><?= e($a['check_out'] ?? '—') ?></td>
            <td><span class="badge <?= $statusBadge[$a['status']] ?? 'badge-neutral' ?>"><?= e($a['status']) ?></span></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════
     STAFF / MEMBER VIEW (own records)
════════════════════════════════════ -->
<?php else: ?>

<div class="page-header">
  <h1>MY ATTENDANCE</h1>
  <p>Your check-in history for the past 30 records.</p>
</div>

<!-- Stat: how many times attended this month -->
<div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:24px">
  <div class="stat-card accent">
    <span class="stat-icon">✅</span>
    <div class="stat-value"><?= $monthPresent ?></div>
    <div class="stat-label">Present This Month</div>
  </div>
  <div class="stat-card info">
    <span class="stat-icon">📅</span>
    <div class="stat-value"><?= date('F Y') ?></div>
    <div class="stat-label">Current Month</div>
  </div>
</div>

<!-- Attendance history table -->
<div class="card">
  <div class="card-header"><h3>Attendance History</h3></div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Date</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr></thead>
      <tbody>
      <?php if ($myAttendance->num_rows === 0): ?>
        <tr class="no-records"><td colspan="4">No attendance records found.</td></tr>
      <?php else: while ($a = $myAttendance->fetch_assoc()): ?>
        <tr>
          <td style="font-weight:500"><?= e($a['date']) ?></td>
          <td class="text-muted"><?= e($a['check_in']  ?? '—') ?></td>
          <td class="text-muted"><?= e($a['check_out'] ?? '—') ?></td>
          <td><span class="badge <?= $statusBadge[$a['status']] ?? 'badge-neutral' ?>"><?= e($a['status']) ?></span></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; // end role check ?>

</main>
<?php require_once 'includes/footer.php'; ?>
