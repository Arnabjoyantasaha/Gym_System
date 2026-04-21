<?php
// ──────────────────────────────────────────────────
//  bmi.php  —  BMI Tracker
//
//  MEMBER  : Calculate BMI, save records, view history
//  TRAINER : View BMI records of all assigned members
// ──────────────────────────────────────────────────

require_once 'config/database.php';
require_once 'includes/auth_guard.php';
requireAuth(['member', 'trainer']); // Only members and trainers

$db  = db();
$r   = role();
$uid = uid();
$msg = ''; $err = '';

// ════════════════════════════════════════════════
//  MEMBER: Save a new BMI record
// ════════════════════════════════════════════════
if ($r === 'member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Find this member's record ID
    $mStmt = $db->prepare("SELECT id FROM members WHERE user_id = ? LIMIT 1");
    $mStmt->bind_param('i', $uid);
    $mStmt->execute();
    $mid = (int)($mStmt->get_result()->fetch_row()[0] ?? 0);

    if ($mid) {
        $height = (float)$_POST['height_cm']; // Height in centimeters
        $weight = (float)$_POST['weight_kg']; // Weight in kilograms
        $date   = $_POST['record_date'] ?: date('Y-m-d');
        $notes  = trim($_POST['notes']  ?? '');

        if ($height <= 0 || $weight <= 0) {
            $err = 'Please enter valid height and weight values.';
        } else {
            // Use the calcBMI() helper from auth_guard.php
            $bmi = calcBMI($height, $weight);

            $s = $db->prepare("INSERT INTO bmi_records (member_id,height_cm,weight_kg,bmi_value,record_date,notes) VALUES (?,?,?,?,?,?)");
            $s->bind_param('idddss', $mid, $height, $weight, $bmi, $date, $notes);
            if ($s->execute()) {
                $msg = "BMI saved: {$bmi} — " . bmiCategory($bmi) . ". Keep up the great work!";
            } else {
                $err = $db->error;
            }
        }
    }
}

// ════════════════════════════════════════════════
//  LOAD DATA based on role
// ════════════════════════════════════════════════

if ($r === 'member') {
    // Get member ID
    $mStmt = $db->prepare("SELECT id FROM members WHERE user_id = ? LIMIT 1");
    $mStmt->bind_param('i', $uid);
    $mStmt->execute();
    $mid = (int)($mStmt->get_result()->fetch_row()[0] ?? 0);

    // Load this member's BMI history (last 12 records)
    $history = $db->prepare("
        SELECT * FROM bmi_records
        WHERE  member_id = ?
        ORDER  BY record_date DESC, id DESC LIMIT 12
    ");
    $history->bind_param('i', $mid);
    $history->execute();
    $history = $history->get_result();

} elseif ($r === 'trainer') {
    // Get trainer's ID
    $tStmt = $db->prepare("SELECT id FROM trainers WHERE user_id = ? LIMIT 1");
    $tStmt->bind_param('i', $uid);
    $tStmt->execute();
    $tid = (int)($tStmt->get_result()->fetch_row()[0] ?? 0);

    // Load BMI records for all members assigned to this trainer
    $bmiRecords = $db->prepare("
        SELECT br.*, u.name AS member_name, m.membership_plan
        FROM   bmi_records br
        JOIN   members m ON m.id = br.member_id
        JOIN   users   u ON u.id = m.user_id
        WHERE  m.assigned_trainer = ?
        ORDER  BY br.record_date DESC, br.id DESC
        LIMIT  50
    ");
    $bmiRecords->bind_param('i', $tid);
    $bmiRecords->execute();
    $bmiRecords = $bmiRecords->get_result();
}

$pageTitle = 'BMI Tracker';
$activeNav = 'bmi';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">

<!-- ════════════════════════════════════
     MEMBER VIEW: Calculator + History
════════════════════════════════════ -->
<?php if ($r === 'member'): ?>

<div class="page-header">
  <h1>BMI TRACKER</h1>
  <p>Monitor your Body Mass Index progress over time.</p>
</div>

<?php if ($msg): ?><div class="alert alert-success" data-dismiss="6000">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠ <?= e($err) ?></div><?php endif; ?>

<div class="grid-2">

  <!-- BMI CALCULATOR CARD -->
  <div class="card">
    <div class="card-header"><h3>BMI Calculator</h3></div>
    <div class="card-body">
      <!-- id="bmiCalcForm" makes app.js live-update the BMI as you type -->
      <form method="POST" id="bmiCalcForm">
        <div class="form-row">
          <div class="form-group">
            <label>Height (cm) *</label>
            <!-- id="height" is used by app.js to read the value -->
            <input type="number" id="height" name="height_cm" class="form-control"
                   step="0.1" min="50" max="250" placeholder="170" required>
          </div>
          <div class="form-group">
            <label>Weight (kg) *</label>
            <!-- id="weight" is used by app.js to read the value -->
            <input type="number" id="weight" name="weight_kg" class="form-control"
                   step="0.1" min="10" max="300" placeholder="70" required>
          </div>
        </div>

        <!-- Live result box — updated by app.js as user types -->
        <div style="background:var(--bg-elevated);border-radius:var(--radius-sm);padding:24px;text-align:center;margin-bottom:18px;min-height:80px">
          <div id="bmiResult" style="color:var(--text-muted)">Enter height &amp; weight above to calculate</div>
        </div>

        <!-- Color-coded BMI scale bar -->
        <div style="margin-bottom:18px">
          <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted);margin-bottom:4px">
            <span style="color:#64b5f6">Underweight &lt;18.5</span>
            <span style="color:#4CAF50">Normal 18.5–24.9</span>
            <span style="color:#ffa726">Overweight 25–29.9</span>
            <span style="color:#ef5350">Obese 30+</span>
          </div>
          <!-- id="bmiFill" is moved by app.js to show where your BMI falls -->
          <div class="bmi-meter"><div class="bmi-fill" id="bmiFill" style="width:0%"></div></div>
        </div>

        <!-- Hidden field: app.js writes the calculated BMI value here before form submits -->
        <input type="hidden" name="bmi_value" id="bmi_value">

        <div class="form-group">
          <label>Date</label>
          <input type="date" name="record_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Notes (optional)</label>
          <input type="text" name="notes" class="form-control" placeholder="e.g. After workout, morning measurement…">
        </div>
        <button type="submit" class="btn btn-primary btn-block">💾 Save BMI Record</button>
      </form>
    </div>

    <!-- BMI reference table at the bottom of the card -->
    <div class="card-footer">
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:center">
        <div><div style="color:#64b5f6;font-weight:700">&lt;18.5</div><div class="text-muted text-small">Underweight</div></div>
        <div><div style="color:#4CAF50;font-weight:700">18.5–24.9</div><div class="text-muted text-small">Normal</div></div>
        <div><div style="color:#ffa726;font-weight:700">25–29.9</div><div class="text-muted text-small">Overweight</div></div>
        <div><div style="color:#ef5350;font-weight:700">30+</div><div class="text-muted text-small">Obese</div></div>
      </div>
    </div>
  </div>

  <!-- BMI HISTORY TABLE -->
  <div class="card">
    <div class="card-header"><h3>My BMI History</h3></div>
    <div class="table-responsive">
      <table>
        <thead>
          <tr><th>Date</th><th>Height</th><th>Weight</th><th>BMI</th><th>Category</th><th>Notes</th></tr>
        </thead>
        <tbody>
        <?php if ($history->num_rows === 0): ?>
          <tr class="no-records"><td colspan="6">No records yet. Add your first measurement above!</td></tr>
        <?php else: while ($b = $history->fetch_assoc()): ?>
          <tr>
            <td style="font-weight:500"><?= e($b['record_date']) ?></td>
            <td class="text-muted"><?= e($b['height_cm']) ?> cm</td>
            <td class="text-muted"><?= e($b['weight_kg']) ?> kg</td>
            <!-- BMI value colored using bmiColor() helper -->
            <td style="<?= bmiColor((float)$b['bmi_value']) ?>;font-weight:700;font-size:16px">
              <?= e($b['bmi_value']) ?>
            </td>
            <td><span class="badge badge-neutral"><?= bmiCategory((float)$b['bmi_value']) ?></span></td>
            <td class="text-muted text-small"><?= e($b['notes'] ?: '—') ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /.grid-2 -->

<!-- ════════════════════════════════════
     TRAINER VIEW: Members' BMI records
════════════════════════════════════ -->
<?php else: ?>

<div class="page-header">
  <h1>BMI RECORDS</h1>
  <p>Body mass index records for your assigned members.</p>
</div>

<div class="card">
  <div class="card-header"><h3>My Members' BMI History</h3></div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>Member</th><th>Date</th><th>Height</th><th>Weight</th><th>BMI</th><th>Category</th><th>Notes</th></tr>
      </thead>
      <tbody>
      <?php if ($bmiRecords->num_rows === 0): ?>
        <tr class="no-records"><td colspan="7">No BMI records for your members yet.</td></tr>
      <?php else: while ($b = $bmiRecords->fetch_assoc()): ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= e($b['member_name']) ?></div>
            <div class="text-muted text-small"><?= e($b['membership_plan']) ?> plan</div>
          </td>
          <td class="text-muted"><?= e($b['record_date']) ?></td>
          <td class="text-muted"><?= e($b['height_cm']) ?> cm</td>
          <td class="text-muted"><?= e($b['weight_kg']) ?> kg</td>
          <td style="<?= bmiColor((float)$b['bmi_value']) ?>;font-weight:700;font-size:16px">
            <?= e($b['bmi_value']) ?>
          </td>
          <td><span class="badge badge-neutral"><?= bmiCategory((float)$b['bmi_value']) ?></span></td>
          <td class="text-muted text-small"><?= e($b['notes'] ?: '—') ?></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; // role check ?>

</main>
<?php require_once 'includes/footer.php'; ?>
