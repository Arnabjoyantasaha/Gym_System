<?php
// ──────────────────────────────────────────────────
//  trainer_members.php  —  Trainer's Members (detailed)
//  Shows all members assigned to the logged-in trainer
//  with full details including BMI, contact, plan, etc.
// ──────────────────────────────────────────────────

require_once 'config/database.php';
require_once 'includes/auth_guard.php';
requireAuth(['trainer']); // Only trainers can access this page

$db  = db();
$uid = uid();

// ── Get this trainer's record ────────────────────
$tStmt = $db->prepare("SELECT * FROM trainers WHERE user_id = ? LIMIT 1");
$tStmt->bind_param('i', $uid);
$tStmt->execute();
$trainer = $tStmt->get_result()->fetch_assoc();
$tid     = (int)($trainer['id'] ?? 0);

// ── Load all members assigned to this trainer ────
// We join with users to get name/email, and bmi_records to get latest BMI
$members = $db->prepare("
    SELECT u.name, u.email, u.phone, u.status,
           m.id AS mid, m.membership_plan, m.join_date, m.expiry_date,
           (SELECT br.bmi_value   FROM bmi_records br WHERE br.member_id = m.id ORDER BY br.id DESC LIMIT 1) AS latest_bmi,
           (SELECT br.weight_kg   FROM bmi_records br WHERE br.member_id = m.id ORDER BY br.id DESC LIMIT 1) AS latest_weight,
           (SELECT br.record_date FROM bmi_records br WHERE br.member_id = m.id ORDER BY br.id DESC LIMIT 1) AS bmi_date,
           (SELECT COUNT(*)       FROM attendance a WHERE a.user_id = u.id AND MONTH(a.date) = MONTH(NOW()) AND a.status = 'present') AS month_sessions
    FROM   members m
    JOIN   users u ON u.id = m.user_id
    WHERE  m.assigned_trainer = ?
    ORDER  BY u.name ASC
");
$members->bind_param('i', $tid);
$members->execute();
$members = $members->get_result();

// Count total members for the stat card
$memberCount = $db->prepare("SELECT COUNT(*) FROM members WHERE assigned_trainer = ?");
$memberCount->bind_param('i', $tid);
$memberCount->execute();
$memberCount = $memberCount->get_result()->fetch_row()[0];

$pageTitle = 'My Members';
$activeNav = 'trainer_members';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">
<div class="page-header">
  <h1>MY MEMBERS</h1>
  <p>Detailed view of all members assigned to you. Specialization: <?= e($trainer['specialization'] ?? 'Not set') ?></p>
</div>

<!-- Stat: total assigned members -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card accent">
    <span class="stat-icon">🏋</span>
    <div class="stat-value"><?= $memberCount ?></div>
    <div class="stat-label">Assigned Members</div>
  </div>
  <div class="stat-card info">
    <span class="stat-icon">💪</span>
    <div class="stat-value"><?= e($trainer['specialization'] ?? '—') ?></div>
    <div class="stat-label" style="font-size:9px">Specialization</div>
  </div>
  <div class="stat-card warning">
    <span class="stat-icon">📅</span>
    <div class="stat-value"><?= date('M Y') ?></div>
    <div class="stat-label">Current Month</div>
  </div>
</div>

<!-- Members table with full details -->
<div class="card">
  <div class="card-header">
    <h3>Member Details</h3>
    <!-- Live search — filters table rows as you type (handled by app.js) -->
    <div class="search-bar">
      <span>🔍</span>
      <input type="text" id="tableSearch" placeholder="Search members…">
    </div>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>Member</th>
          <th>Contact</th>
          <th>Plan</th>
          <th>Joined</th>
          <th>Expires</th>
          <th>Sessions/Mo</th>
          <th>Latest BMI</th>
          <th>BMI Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($members->num_rows === 0): ?>
        <tr class="no-records">
          <td colspan="9">No members assigned to you yet. Ask an admin or manager to assign some members.</td>
        </tr>
      <?php else: while ($m = $members->fetch_assoc()): ?>
        <tr>
          <td>
            <!-- Avatar circle with first letter -->
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-dark));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff;flex-shrink:0">
                <?= strtoupper(substr($m['name'], 0, 1)) ?>
              </div>
              <span style="font-weight:500"><?= e($m['name']) ?></span>
            </div>
          </td>
          <td>
            <div class="text-muted text-small"><?= e($m['email']) ?></div>
            <?php if ($m['phone']): ?>
              <div class="text-muted text-small"><?= e($m['phone']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-info"><?= e($m['membership_plan']) ?></span></td>
          <td class="text-muted"><?= e($m['join_date']) ?></td>
          <td>
            <?php $expired = strtotime($m['expiry_date']) < time(); ?>
            <span class="badge <?= $expired ? 'badge-danger' : 'badge-success' ?>">
              <?= e($m['expiry_date']) ?>
            </span>
          </td>
          <td>
            <!-- How many times this member attended this month -->
            <span class="badge <?= $m['month_sessions'] >= 10 ? 'badge-success' : ($m['month_sessions'] >= 4 ? 'badge-warning' : 'badge-danger') ?>">
              <?= $m['month_sessions'] ?> sessions
            </span>
          </td>
          <td>
            <?php if ($m['latest_bmi']): ?>
              <!-- Color the BMI value with bmiColor() helper -->
              <span style="<?= bmiColor((float)$m['latest_bmi']) ?>;font-weight:700;font-size:16px">
                <?= number_format($m['latest_bmi'], 1) ?>
              </span>
              <span class="text-muted text-small"> — <?= bmiCategory((float)$m['latest_bmi']) ?></span>
              <?php if ($m['latest_weight']): ?>
                <div class="text-muted text-small"><?= e($m['latest_weight']) ?> kg</div>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-muted">No data yet</span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= e($m['bmi_date'] ?? '—') ?></td>
          <td>
            <span class="badge <?= $m['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
              <?= e($m['status']) ?>
            </span>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</main>
<?php require_once 'includes/footer.php'; ?>
