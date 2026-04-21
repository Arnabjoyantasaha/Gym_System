<?php
// ──────────────────────────────────────────────────
//  payments.php  —  Payment management
//
//  ADMIN / MANAGER : Record new payments, update status, view all
//  MEMBER          : Submit renewal request, view own payment history
// ──────────────────────────────────────────────────

require_once 'config/database.php';
require_once 'includes/auth_guard.php';
requireAuth(['admin', 'manager', 'member']);

$db  = db();
$r   = role();
$uid = uid();
$msg = ''; $err = '';

// ════════════════════════════════════════════════
//  ADMIN / MANAGER: Handle payment actions
// ════════════════════════════════════════════════
if (in_array($r, ['admin', 'manager']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Record a new payment ──
    if ($action === 'add_payment') {
        $mid    = (int)$_POST['member_id'];
        $amount = (float)$_POST['amount'];
        $method = $_POST['payment_method'] ?? 'cash';
        $status = $_POST['status']         ?? 'paid';
        $notes  = trim($_POST['notes']     ?? '');
        $date   = $_POST['payment_date']   ?: date('Y-m-d');

        if (!$mid || $amount <= 0) {
            $err = 'Please select a member and enter a valid amount.';
        } else {
            $s = $db->prepare("INSERT INTO payments (member_id,amount,payment_date,payment_method,status,notes) VALUES (?,?,?,?,?,?)");
            $s->bind_param('idssss', $mid, $amount, $date, $method, $status, $notes);
            $s->execute() ? $msg = 'Payment recorded.' : $err = $db->error;
        }
    }

    // ── Update an existing payment's status ──
    if ($action === 'update_status') {
        $pid    = (int)$_POST['payment_id'];
        $status = $_POST['status'];
        $s = $db->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $s->bind_param('si', $status, $pid);
        $s->execute();
        $msg = 'Payment status updated.';
    }
}

// ════════════════════════════════════════════════
//  MEMBER: Submit a payment request
// ════════════════════════════════════════════════
if ($r === 'member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get this member's record
    $mStmt = $db->prepare("SELECT id, membership_plan FROM members WHERE user_id = ? LIMIT 1");
    $mStmt->bind_param('i', $uid);
    $mStmt->execute();
    $member = $mStmt->get_result()->fetch_assoc();
    $mid    = (int)($member['id'] ?? 0);

    if ($mid) {
        // Calculate price based on plan
        $amount = match($member['membership_plan']) {
            'quarterly' => 120.00,
            'annual'    => 400.00,
            default     => 50.00,
        };
        $method = $_POST['payment_method'] ?? 'card';
        $date   = date('Y-m-d');
        $note   = ucfirst($member['membership_plan']) . ' plan renewal';

        // Insert as 'pending' — staff will confirm it
        $s = $db->prepare("INSERT INTO payments (member_id,amount,payment_date,payment_method,status,notes) VALUES (?,?,?,?,'pending',?)");
        $s->bind_param('idsss', $mid, $amount, $date, $method, $note);
        $s->execute() ? $msg = "Payment of \${$amount} submitted! Staff will confirm soon." : $err = $db->error;
    }
}

// ════════════════════════════════════════════════
//  LOAD DATA based on role
// ════════════════════════════════════════════════

if (in_array($r, ['admin', 'manager'])) {
    // All payments + member names
    $payments = $db->query("
        SELECT p.*, u.name AS member_name
        FROM   payments p
        JOIN   members m ON m.id = p.member_id
        JOIN   users   u ON u.id = m.user_id
        ORDER  BY p.id DESC LIMIT 100
    ");

    // Members dropdown for "Add Payment" modal
    $memberList = $db->query("SELECT m.id, u.name FROM members m JOIN users u ON u.id = m.user_id ORDER BY u.name");

    // Summary totals
    $totalPaid    = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetch_row()[0];
    $totalPending = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='pending'")->fetch_row()[0];
    $monthRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND MONTH(payment_date)=MONTH(NOW())")->fetch_row()[0];

} else {
    // Member: their own info and payments
    $mStmt = $db->prepare("SELECT id, membership_plan FROM members WHERE user_id = ? LIMIT 1");
    $mStmt->bind_param('i', $uid);
    $mStmt->execute();
    $member = $mStmt->get_result()->fetch_assoc();
    $mid    = (int)($member['id'] ?? 0);

    // Their payment history
    $payments = $db->prepare("SELECT * FROM payments WHERE member_id = ? ORDER BY id DESC");
    $payments->bind_param('i', $mid);
    $payments->execute();
    $payments = $payments->get_result();

    // Total amount paid
    $totalPaid = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE member_id = ? AND status='paid'");
    $totalPaid->bind_param('i', $mid);
    $totalPaid->execute();
    $totalPaid = $totalPaid->get_result()->fetch_row()[0];

    // Price for their plan
    $planPrice = match($member['membership_plan'] ?? 'monthly') {
        'quarterly' => 120, 'annual' => 400, default => 50
    };
}

// Badge colors for payment status
$statusBadge = ['paid' => 'badge-success', 'pending' => 'badge-warning', 'failed' => 'badge-danger'];

$pageTitle = 'Payments';
$activeNav = 'payments';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">

<!-- ════════════════════════════════════
     ADMIN / MANAGER VIEW
════════════════════════════════════ -->
<?php if (in_array($r, ['admin', 'manager'])): ?>

<div class="page-header">
  <h1>PAYMENTS</h1>
  <p>Track and manage membership fee payments.</p>
</div>

<?php if ($msg): ?><div class="alert alert-success" data-dismiss="4000">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠ <?= e($err) ?></div><?php endif; ?>

<!-- Summary stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card accent"> <span class="stat-icon">💰</span><div class="stat-value">$<?= number_format($totalPaid,    0) ?></div><div class="stat-label">Total Collected</div></div>
  <div class="stat-card warning"><span class="stat-icon">⏳</span><div class="stat-value">$<?= number_format($totalPending, 0) ?></div><div class="stat-label">Pending</div></div>
  <div class="stat-card info">   <span class="stat-icon">📅</span><div class="stat-value">$<?= number_format($monthRevenue, 0) ?></div><div class="stat-label">This Month</div></div>
</div>

<!-- Payment records table -->
<div class="card">
  <div class="card-header">
    <h3>Payment Records</h3>
    <!-- Button opens Add Payment modal -->
    <button class="btn btn-primary" onclick="document.getElementById('payModal').style.display='flex'">+ Record Payment</button>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>#</th><th>Member</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th><th>Notes</th><th>Change Status</th></tr>
      </thead>
      <tbody>
      <?php if ($payments->num_rows === 0): ?>
        <tr class="no-records"><td colspan="8">No payment records found.</td></tr>
      <?php else: while ($p = $payments->fetch_assoc()): ?>
        <tr>
          <td class="text-muted"><?= $p['id'] ?></td>
          <td style="font-weight:500"><?= e($p['member_name']) ?></td>
          <td class="text-accent fw-600">$<?= number_format($p['amount'], 2) ?></td>
          <td class="text-muted"><?= e($p['payment_date']) ?></td>
          <td><?= e(ucfirst($p['payment_method'])) ?></td>
          <td><span class="badge <?= $statusBadge[$p['status']] ?? 'badge-neutral' ?>"><?= e($p['status']) ?></span></td>
          <td class="text-muted text-small"><?= e($p['notes'] ?: '—') ?></td>
          <td>
            <!-- Inline form to change status of this payment -->
            <form method="POST" style="display:flex;gap:4px">
              <input type="hidden" name="action"     value="update_status">
              <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
              <select name="status" class="form-control" style="padding:4px 8px;font-size:11px">
                <?php foreach (['paid', 'pending', 'failed'] as $s): ?>
                  <option <?= $p['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-ghost btn-sm">Save</button>
            </form>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════
     MEMBER VIEW
════════════════════════════════════ -->
<?php else: ?>

<div class="page-header">
  <h1>MY PAYMENTS</h1>
  <p>Membership fee history and renewal.</p>
</div>

<?php if ($msg): ?><div class="alert alert-success" data-dismiss="5000">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠ <?= e($err) ?></div><?php endif; ?>

<div class="grid-2">
  <!-- Renew membership form -->
  <div class="card">
    <div class="card-header"><h3>Renew Membership</h3></div>
    <div class="card-body">
      <!-- Plan price display -->
      <div style="background:var(--bg-elevated);border-radius:10px;padding:20px;margin-bottom:20px;text-align:center">
        <div class="text-muted text-small" style="text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">
          <?= e(ucfirst($member['membership_plan'] ?? '')) ?> Plan
        </div>
        <div style="font-family:'Bebas Neue',sans-serif;font-size:52px;color:var(--accent);letter-spacing:2px">
          $<?= $planPrice ?>
        </div>
        <div class="text-muted text-small">
          per <?= $member['membership_plan'] === 'annual' ? 'year' : ($member['membership_plan'] === 'quarterly' ? '3 months' : 'month') ?>
        </div>
      </div>
      <form method="POST">
        <div class="form-group">
          <label>Payment Method</label>
          <select name="payment_method" class="form-control">
            <option value="card">Credit/Debit Card</option>
            <option value="cash">Cash at Front Desk</option>
            <option value="online">Online Transfer</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg"
                data-confirm="Submit payment of $<?= $planPrice ?> for <?= ucfirst($member['membership_plan'] ?? '') ?> plan?">
          Pay $<?= $planPrice ?> Now
        </button>
        <p class="text-muted text-small mt-8 text-center">Payment will be confirmed by staff.</p>
      </form>
    </div>
  </div>

  <!-- Payment history -->
  <div>
    <div class="stat-card accent mb-16">
      <span class="stat-icon">💳</span>
      <div class="stat-value">$<?= number_format($totalPaid, 0) ?></div>
      <div class="stat-label">Total Paid (All Time)</div>
    </div>
    <div class="card">
      <div class="card-header"><h3>Payment History</h3></div>
      <div class="table-responsive">
        <table>
          <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
          <tbody>
          <?php if ($payments->num_rows === 0): ?>
            <tr class="no-records"><td colspan="4">No payments yet.</td></tr>
          <?php else: while ($p = $payments->fetch_assoc()): ?>
            <tr>
              <td><?= e($p['payment_date']) ?></td>
              <td class="fw-600 text-accent">$<?= number_format($p['amount'], 2) ?></td>
              <td class="text-muted"><?= e(ucfirst($p['payment_method'])) ?></td>
              <td><span class="badge <?= $statusBadge[$p['status']] ?? 'badge-neutral' ?>"><?= e($p['status']) ?></span></td>
            </tr>
          <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php endif; // end role check ?>

</main>

<!-- ════════════ MODAL: Add Payment (admin/manager only) ════════════ -->
<?php if (in_array($r, ['admin', 'manager'])): ?>
<div id="payModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border-lite);border-radius:16px;width:460px;max-width:95vw">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h2 class="font-display" style="font-size:20px;letter-spacing:2px">RECORD PAYMENT</h2>
      <button onclick="document.getElementById('payModal').style.display='none'"
              style="background:none;border:none;color:var(--text-muted);font-size:22px;cursor:pointer">✕</button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="add_payment">
      <div class="form-group">
        <label>Member *</label>
        <select name="member_id" class="form-control" required>
          <option value="">Select member…</option>
          <?php while ($m = $memberList->fetch_assoc()): ?>
            <option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Amount ($) *</label>
          <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required placeholder="50.00">
        </div>
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Method</label>
          <select name="payment_method" class="form-control">
            <option value="cash">Cash</option>
            <option value="card">Card</option>
            <option value="online">Online</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <option value="paid">Paid</option>
            <option value="pending">Pending</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <input type="text" name="notes" class="form-control" placeholder="Optional notes…">
      </div>
      <div class="d-flex gap-8" style="justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('payModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Payment</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
