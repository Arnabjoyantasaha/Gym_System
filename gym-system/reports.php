<?php
// ──────────────────────────────────────────────────
//  reports.php  —  Analytics & Reports (admin only)
//  Shows attendance trends, revenue, class bookings,
//  and membership plan distribution
// ──────────────────────────────────────────────────

require_once 'config/database.php';
require_once 'includes/auth_guard.php';
requireAuth(['admin']); // Only admins can see reports

$db = db();

// ── Attendance for last 7 days ──────────────────────
// GROUP BY date to get daily totals
$attReport = $db->query("
    SELECT date,
           COUNT(*)                     AS total,
           SUM(status = 'present')      AS present,
           SUM(status = 'absent')       AS absent,
           SUM(status = 'late')         AS late
    FROM   attendance
    WHERE  date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP  BY date
    ORDER  BY date DESC
");

// ── Revenue for last 6 months ──────────────────────
// DATE_FORMAT groups by year-month (e.g. '2025-04')
$revReport = $db->query("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month,
           COALESCE(SUM(CASE WHEN status = 'paid'    THEN amount END), 0) AS paid,
           COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) AS pending
    FROM   payments
    WHERE  payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP  BY month
    ORDER  BY month DESC
");

// ── Class booking popularity ─────────────────────
// LEFT JOIN so classes with 0 bookings still appear
$classReport = $db->query("
    SELECT c.class_name, COUNT(cb.id) AS bookings,
           c.capacity, tu.name AS trainer
    FROM   classes c
    LEFT   JOIN class_bookings cb ON cb.class_id = c.id
    JOIN   trainers t  ON t.id = c.trainer_id
    JOIN   users    tu ON tu.id = t.user_id
    GROUP  BY c.id
    ORDER  BY bookings DESC
");

// ── Membership plan distribution ─────────────────
$planReport = $db->query("
    SELECT membership_plan, COUNT(*) AS cnt
    FROM   members
    GROUP  BY membership_plan
");
$totalMemberCount = $db->query("SELECT COUNT(*) FROM members")->fetch_row()[0];

// Quick summary stats
$totalRevenue    = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetch_row()[0];
$totalMembers    = $db->query("SELECT COUNT(*) FROM members")->fetch_row()[0];
$avgAttendance   = $db->query("SELECT ROUND(AVG(daily_count),1) FROM (SELECT COUNT(*) AS daily_count FROM attendance WHERE status='present' GROUP BY date) AS t")->fetch_row()[0];
$totalBookings   = $db->query("SELECT COUNT(*) FROM class_bookings")->fetch_row()[0];

$pageTitle = 'Reports';
$activeNav = 'reports';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
<div class="page-header">
  <h1>REPORTS</h1>
  <p>Analytics and summary data for gym operations.</p>
</div>

<!-- Quick summary stat cards -->
<div class="stats-grid">
  <div class="stat-card accent"> <span class="stat-icon">💰</span><div class="stat-value">$<?= number_format($totalRevenue, 0) ?></div><div class="stat-label">All-Time Revenue</div></div>
  <div class="stat-card info">   <span class="stat-icon">🏋</span><div class="stat-value"><?= $totalMembers   ?></div><div class="stat-label">Total Members</div></div>
  <div class="stat-card warning"><span class="stat-icon">📋</span><div class="stat-value"><?= $avgAttendance  ?></div><div class="stat-label">Avg. Daily Attendance</div></div>
  <div class="stat-card accent"> <span class="stat-icon">📅</span><div class="stat-value"><?= $totalBookings  ?></div><div class="stat-label">Total Class Bookings</div></div>
</div>

<div class="grid-2">

  <!-- ATTENDANCE: last 7 days -->
  <div class="card">
    <div class="card-header"><h3>Attendance — Last 7 Days</h3></div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Date</th><th>Present</th><th>Absent</th><th>Late</th><th>Total</th></tr></thead>
        <tbody>
        <?php if ($attReport->num_rows === 0): ?>
          <tr class="no-records"><td colspan="5">No attendance records found.</td></tr>
        <?php else: while ($row = $attReport->fetch_assoc()): ?>
          <tr>
            <td style="font-weight:500"><?= e($row['date']) ?></td>
            <td><span class="badge badge-success"><?= $row['present'] ?></span></td>
            <td><span class="badge badge-danger"><?= $row['absent']  ?></span></td>
            <td><span class="badge badge-warning"><?= $row['late']   ?></span></td>
            <td class="text-muted"><?= $row['total'] ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- REVENUE: last 6 months -->
  <div class="card">
    <div class="card-header"><h3>Revenue — Last 6 Months</h3></div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Month</th><th>Collected</th><th>Pending</th></tr></thead>
        <tbody>
        <?php if ($revReport->num_rows === 0): ?>
          <tr class="no-records"><td colspan="3">No revenue data found.</td></tr>
        <?php else: while ($row = $revReport->fetch_assoc()): ?>
          <tr>
            <td style="font-weight:500"><?= e($row['month']) ?></td>
            <td class="text-accent fw-600">$<?= number_format($row['paid'], 2) ?></td>
            <td><span class="badge badge-warning">$<?= number_format($row['pending'], 2) ?></span></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- CLASS BOOKINGS: most popular classes -->
  <div class="card">
    <div class="card-header"><h3>Class Booking Popularity</h3></div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Class</th><th>Trainer</th><th>Bookings</th><th>Capacity</th></tr></thead>
        <tbody>
        <?php if ($classReport->num_rows === 0): ?>
          <tr class="no-records"><td colspan="4">No classes found.</td></tr>
        <?php else: while ($row = $classReport->fetch_assoc()): ?>
          <tr>
            <td style="font-weight:500"><?= e($row['class_name']) ?></td>
            <td class="text-muted"><?= e($row['trainer']) ?></td>
            <td><span class="badge badge-info"><?= $row['bookings'] ?></span></td>
            <td class="text-muted"><?= $row['capacity'] ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- MEMBERSHIP PLAN DISTRIBUTION: progress bars -->
  <div class="card">
    <div class="card-header"><h3>Membership Plan Distribution</h3></div>
    <div class="card-body">
      <?php
      $planReport->data_seek(0); // Reset result pointer to the beginning
      while ($row = $planReport->fetch_assoc()):
          // Calculate what % of members are on this plan
          $pct = $totalMemberCount > 0 ? round($row['cnt'] / $totalMemberCount * 100) : 0;
      ?>
      <div style="margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span style="font-weight:500;text-transform:capitalize"><?= e($row['membership_plan']) ?></span>
          <span class="text-muted"><?= $row['cnt'] ?> members (<?= $pct ?>%)</span>
        </div>
        <!-- Progress bar: width is the percentage of members on this plan -->
        <div style="background:var(--bg-elevated);border-radius:4px;height:8px">
          <div style="height:100%;border-radius:4px;background:var(--accent);width:<?= $pct ?>%;transition:width .4s"></div>
        </div>
      </div>
      <?php endwhile; ?>
      <?php if ($totalMemberCount === 0): ?>
        <p class="text-muted text-center">No members registered yet.</p>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.grid-2 -->
</main>
<?php require_once 'includes/footer.php'; ?>
