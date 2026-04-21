<?php
// ──────────────────────────────────────────────────
//  classes.php  —  Fitness Classes
//
//  ADMIN / MANAGER : Add, delete, view all classes
//  TRAINER         : View own upcoming classes
//  MEMBER          : Browse classes + book or cancel
// ──────────────────────────────────────────────────

require_once 'config/database.php';
require_once 'includes/auth_guard.php';
requireAuth(['admin', 'manager', 'trainer', 'member']);

$db  = db();
$r   = role();
$uid = uid();
$msg = ''; $err = '';

// ════════════════════════════════════════════════
//  ADMIN / MANAGER: Add or delete classes
// ════════════════════════════════════════════════
if (in_array($r, ['admin', 'manager']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add a new class ──
    if ($action === 'add_class') {
        $name  = trim($_POST['class_name']    ?? '');
        $tid   = (int)$_POST['trainer_id'];
        $sched = $_POST['schedule_time']      ?? '';
        $dur   = (int)$_POST['duration_min'];
        $cap   = (int)$_POST['capacity'];
        $desc  = trim($_POST['description']   ?? '');

        if (!$name || !$tid || !$sched) {
            $err = 'Class name, trainer, and schedule are required.';
        } else {
            $s = $db->prepare("INSERT INTO classes (class_name,trainer_id,schedule_time,duration_min,capacity,description) VALUES (?,?,?,?,?,?)");
            $s->bind_param('sisiis', $name, $tid, $sched, $dur, $cap, $desc);
            $s->execute() ? $msg = 'Class added successfully.' : $err = $db->error;
        }
    }

    // ── Delete a class ──
    if ($action === 'delete_class') {
        $cid = (int)$_POST['class_id'];
        $s   = $db->prepare("DELETE FROM classes WHERE id = ?");
        $s->bind_param('i', $cid);
        $s->execute();
        $msg = 'Class deleted.';
    }
}

// ════════════════════════════════════════════════
//  MEMBER: Book or cancel a class
// ════════════════════════════════════════════════
if ($r === 'member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get this member's record ID
    $mStmt = $db->prepare("SELECT id FROM members WHERE user_id = ? LIMIT 1");
    $mStmt->bind_param('i', $uid);
    $mStmt->execute();
    $mid    = (int)($mStmt->get_result()->fetch_row()[0] ?? 0);
    $action = $_POST['action'] ?? '';
    $cid    = (int)$_POST['class_id'];

    if ($action === 'book') {
        // Check if class is full before booking
        $capStmt = $db->prepare("
            SELECT c.capacity,
                   (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id) AS booked
            FROM classes c WHERE c.id = ? LIMIT 1
        ");
        $capStmt->bind_param('i', $cid);
        $capStmt->execute();
        $cap = $capStmt->get_result()->fetch_assoc();

        if (!$cap) {
            $err = 'Class not found.';
        } elseif ($cap['booked'] >= $cap['capacity']) {
            $err = 'Sorry, this class is full.';
        } else {
            // INSERT IGNORE: silently ignores if already booked (unique key)
            $s = $db->prepare("INSERT IGNORE INTO class_bookings (member_id, class_id) VALUES (?, ?)");
            $s->bind_param('ii', $mid, $cid);
            $s->execute() ? $msg = 'Class booked successfully! ✓' : $err = 'You already booked this class.';
        }
    }

    if ($action === 'cancel') {
        $s = $db->prepare("DELETE FROM class_bookings WHERE member_id = ? AND class_id = ?");
        $s->bind_param('ii', $mid, $cid);
        $s->execute();
        $msg = 'Booking cancelled.';
    }
}

// ════════════════════════════════════════════════
//  LOAD DATA based on role
// ════════════════════════════════════════════════

if (in_array($r, ['admin', 'manager'])) {
    // All classes with booking count
    $classes = $db->query("
        SELECT c.*, tu.name AS trainer_name,
               (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id) AS booked
        FROM   classes c
        JOIN   trainers t  ON t.id = c.trainer_id
        JOIN   users    tu ON tu.id = t.user_id
        ORDER  BY c.schedule_time ASC
    ");
    // Trainers dropdown for Add Class modal
    $trainers = $db->query("SELECT t.id, u.name FROM trainers t JOIN users u ON u.id = t.user_id ORDER BY u.name");

} elseif ($r === 'trainer') {
    // Get this trainer's record
    $tStmt = $db->prepare("SELECT id FROM trainers WHERE user_id = ? LIMIT 1");
    $tStmt->bind_param('i', $uid);
    $tStmt->execute();
    $tid = (int)($tStmt->get_result()->fetch_row()[0] ?? 0);

    // Only show this trainer's upcoming classes
    $classes = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id) AS booked
        FROM   classes c
        WHERE  c.trainer_id = ? AND c.schedule_time >= NOW()
        ORDER  BY c.schedule_time ASC
    ");
    $classes->bind_param('i', $tid);
    $classes->execute();
    $classes = $classes->get_result();

} elseif ($r === 'member') {
    // Get this member's ID for booking status
    $mStmt = $db->prepare("SELECT id FROM members WHERE user_id = ? LIMIT 1");
    $mStmt->bind_param('i', $uid);
    $mStmt->execute();
    $mid = (int)($mStmt->get_result()->fetch_row()[0] ?? 0);

    // All upcoming classes with booking status for THIS member
    $classes = $db->query("
        SELECT c.*, tu.name AS trainer_name, t.specialization,
               (SELECT COUNT(*) FROM class_bookings cb WHERE cb.class_id = c.id) AS booked,
               (SELECT COUNT(*) FROM class_bookings cb2 WHERE cb2.class_id = c.id AND cb2.member_id = $mid) AS is_booked
        FROM   classes c
        JOIN   trainers t  ON t.id = c.trainer_id
        JOIN   users    tu ON tu.id = t.user_id
        WHERE  c.schedule_time >= NOW()
        ORDER  BY c.schedule_time ASC
    ");
}

$pageTitle = 'Classes';
$activeNav = 'classes';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="main-content">

<!-- ════════════════════════════════════
     ADMIN / MANAGER VIEW
════════════════════════════════════ -->
<?php if (in_array($r, ['admin', 'manager'])): ?>

<div class="page-header">
  <h1>CLASSES</h1>
  <p>Schedule and manage fitness classes.</p>
</div>

<?php if ($msg): ?><div class="alert alert-success" data-dismiss="4000">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠ <?= e($err) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3>All Classes</h3>
    <button class="btn btn-primary" onclick="document.getElementById('classModal').style.display='flex'">+ Add Class</button>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>#</th><th>Class</th><th>Trainer</th><th>Schedule</th><th>Duration</th><th>Bookings</th><th>Capacity Bar</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php if ($classes->num_rows === 0): ?>
        <tr class="no-records"><td colspan="8">No classes scheduled yet.</td></tr>
      <?php else: while ($c = $classes->fetch_assoc()):
        // Calculate % of capacity filled
        $pct = $c['capacity'] > 0 ? round($c['booked'] / $c['capacity'] * 100) : 0;
      ?>
        <tr>
          <td class="text-muted"><?= $c['id'] ?></td>
          <td>
            <div style="font-weight:500"><?= e($c['class_name']) ?></div>
            <div class="text-muted text-small"><?= e(substr($c['description'] ?? '', 0, 50)) ?>…</div>
          </td>
          <td class="text-muted"><?= e($c['trainer_name']) ?></td>
          <td class="text-muted"><?= date('M j, Y g:i A', strtotime($c['schedule_time'])) ?></td>
          <td><?= $c['duration_min'] ?> min</td>
          <td>
            <span class="badge <?= $pct >= 100 ? 'badge-danger' : ($pct >= 75 ? 'badge-warning' : 'badge-success') ?>">
              <?= $c['booked'] ?> / <?= $c['capacity'] ?>
            </span>
          </td>
          <td>
            <!-- Visual capacity bar -->
            <div style="background:var(--bg-elevated);border-radius:4px;height:6px;width:80px">
              <div style="height:100%;border-radius:4px;background:var(--accent);width:<?= $pct ?>%"></div>
            </div>
          </td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"   value="delete_class">
              <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
              <button class="btn btn-danger btn-sm"
                      data-confirm="Delete class '<?= e(addslashes($c['class_name'])) ?>'?">Delete</button>
            </form>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════
     TRAINER VIEW
════════════════════════════════════ -->
<?php elseif ($r === 'trainer'): ?>

<div class="page-header">
  <h1>MY CLASSES</h1>
  <p>Your upcoming scheduled classes.</p>
</div>

<div class="card">
  <div class="card-header"><h3>Upcoming Classes</h3></div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>Class</th><th>Date &amp; Time</th><th>Duration</th><th>Booked</th><th>Capacity</th></tr></thead>
      <tbody>
      <?php if ($classes->num_rows === 0): ?>
        <tr class="no-records"><td colspan="5">No upcoming classes assigned to you.</td></tr>
      <?php else: while ($c = $classes->fetch_assoc()): ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= e($c['class_name']) ?></div>
            <div class="text-muted text-small"><?= e($c['description'] ?? '') ?></div>
          </td>
          <td class="text-muted"><?= date('M j, Y g:i A', strtotime($c['schedule_time'])) ?></td>
          <td><?= $c['duration_min'] ?> min</td>
          <td><span class="badge badge-info"><?= $c['booked'] ?></span></td>
          <td class="text-muted"><?= $c['capacity'] ?></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════
     MEMBER VIEW (card grid with book/cancel)
════════════════════════════════════ -->
<?php else: ?>

<div class="page-header">
  <h1>CLASS SCHEDULE</h1>
  <p>Browse and book upcoming fitness classes.</p>
</div>

<?php if ($msg): ?><div class="alert alert-success" data-dismiss="4000">✓ <?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠ <?= e($err) ?></div><?php endif; ?>

<?php if ($classes->num_rows === 0): ?>
  <div class="card"><div class="card-body text-center text-muted" style="padding:50px">No upcoming classes scheduled.</div></div>
<?php else: ?>

<!-- Classes shown as cards in a responsive grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px">
  <?php while ($c = $classes->fetch_assoc()):
    $full = $c['booked'] >= $c['capacity']; // Is class full?
    $pct  = $c['capacity'] > 0 ? round($c['booked'] / $c['capacity'] * 100) : 0;
  ?>
  <div class="card" style="<?= $c['is_booked'] ? 'border-color:rgba(76,175,80,.4)' : '' ?>">
    <div class="card-body">
      <!-- Status badge -->
      <?php if ($c['is_booked']): ?>
        <span class="badge badge-success" style="margin-bottom:12px">✓ Booked</span>
      <?php elseif ($full): ?>
        <span class="badge badge-danger" style="margin-bottom:12px">Full</span>
      <?php endif; ?>

      <h3 style="font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:1.5px;margin-bottom:4px"><?= e($c['class_name']) ?></h3>
      <p style="color:var(--accent);font-size:12px;font-weight:600;margin-bottom:12px"><?= e($c['trainer_name']) ?> · <?= e($c['specialization']) ?></p>
      <p class="text-muted text-small mb-16"><?= e($c['description'] ?? 'No description.') ?></p>

      <!-- 4-cell info grid: date, time, duration, spots left -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
        <div style="background:var(--bg-elevated);padding:8px 12px;border-radius:8px">
          <div class="text-muted" style="font-size:10px;letter-spacing:1px;text-transform:uppercase">Date</div>
          <div style="font-weight:600;font-size:12px"><?= date('M j, Y', strtotime($c['schedule_time'])) ?></div>
        </div>
        <div style="background:var(--bg-elevated);padding:8px 12px;border-radius:8px">
          <div class="text-muted" style="font-size:10px;letter-spacing:1px;text-transform:uppercase">Time</div>
          <div style="font-weight:600;font-size:12px"><?= date('g:i A', strtotime($c['schedule_time'])) ?></div>
        </div>
        <div style="background:var(--bg-elevated);padding:8px 12px;border-radius:8px">
          <div class="text-muted" style="font-size:10px;letter-spacing:1px;text-transform:uppercase">Duration</div>
          <div style="font-weight:600;font-size:12px"><?= $c['duration_min'] ?> min</div>
        </div>
        <div style="background:var(--bg-elevated);padding:8px 12px;border-radius:8px">
          <div class="text-muted" style="font-size:10px;letter-spacing:1px;text-transform:uppercase">Spots Left</div>
          <div style="font-weight:600;font-size:12px;color:<?= $full ? 'var(--danger)' : 'var(--accent)' ?>">
            <?= $c['capacity'] - $c['booked'] ?>
          </div>
        </div>
      </div>

      <!-- Capacity bar -->
      <div style="margin-bottom:16px">
        <div style="background:var(--bg-elevated);border-radius:4px;height:6px">
          <div style="height:100%;border-radius:4px;background:<?= $pct >= 100 ? 'var(--danger)' : ($pct >= 75 ? 'var(--warning)' : 'var(--accent)') ?>;width:<?= $pct ?>%"></div>
        </div>
        <div class="text-muted text-small mt-4"><?= $c['booked'] ?> / <?= $c['capacity'] ?> booked</div>
      </div>

      <!-- Book or Cancel button -->
      <?php if ($c['is_booked']): ?>
        <form method="POST">
          <input type="hidden" name="action"   value="cancel">
          <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
          <button class="btn btn-ghost btn-block btn-sm"
                  data-confirm="Cancel your booking for '<?= e(addslashes($c['class_name'])) ?>'?">
            Cancel Booking
          </button>
        </form>
      <?php elseif (!$full): ?>
        <form method="POST">
          <input type="hidden" name="action"   value="book">
          <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
          <button class="btn btn-primary btn-block">Book This Class</button>
        </form>
      <?php else: ?>
        <button class="btn btn-ghost btn-block" disabled>Class Full</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endwhile; ?>
</div>

<?php endif; // classes empty check ?>
<?php endif; // role check ?>

</main>

<!-- ════════════ MODAL: Add Class (admin/manager only) ════════════ -->
<?php if (in_array($r, ['admin', 'manager'])): ?>
<div id="classModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center">
  <div style="background:var(--bg-card);border:1px solid var(--border-lite);border-radius:16px;width:480px;max-width:95vw">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h2 class="font-display" style="font-size:20px;letter-spacing:2px">NEW CLASS</h2>
      <button onclick="document.getElementById('classModal').style.display='none'"
              style="background:none;border:none;color:var(--text-muted);font-size:22px;cursor:pointer">✕</button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="add_class">
      <div class="form-group">
        <label>Class Name *</label>
        <input type="text" name="class_name" class="form-control" required placeholder="e.g. Morning HIIT">
      </div>
      <div class="form-group">
        <label>Trainer *</label>
        <select name="trainer_id" class="form-control" required>
          <option value="">Select trainer…</option>
          <?php $trainers->data_seek(0); while ($t = $trainers->fetch_assoc()): ?>
            <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Date &amp; Time *</label>
        <input type="datetime-local" name="schedule_time" class="form-control" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Duration (min)</label>
          <input type="number" name="duration_min" class="form-control" value="60" min="15" max="180">
        </div>
        <div class="form-group">
          <label>Capacity</label>
          <input type="number" name="capacity" class="form-control" value="20" min="1" max="100">
        </div>
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="2" placeholder="Short description…"></textarea>
      </div>
      <div class="d-flex gap-8" style="justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('classModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Class</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
