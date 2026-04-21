<?php
// ──────────────────────────────────────────────────
//  includes/sidebar.php  —  Left sidebar navigation
//  Each role sees a different set of menu items
// ──────────────────────────────────────────────────

$r = role(); // Get current user's role (e.g. 'admin', 'member')

// Build the navigation items array based on role
// Each item: icon + label + link + key (used to highlight active item)
$navItems = [];

if ($r === 'admin') {
    // Admin sees everything
    $navItems = [
        ['icon' => '⊞',  'label' => 'Dashboard',  'href' => APP_URL.'/dashboard.php',   'key' => 'dashboard'],
        ['icon' => '👥', 'label' => 'Users',       'href' => APP_URL.'/members.php',      'key' => 'members'],
        ['icon' => '📋', 'label' => 'Attendance',  'href' => APP_URL.'/attendance.php',   'key' => 'attendance'],
        ['icon' => '💳', 'label' => 'Payments',    'href' => APP_URL.'/payments.php',     'key' => 'payments'],
        ['icon' => '📅', 'label' => 'Classes',     'href' => APP_URL.'/classes.php',      'key' => 'classes'],
        ['icon' => '📊', 'label' => 'Reports',     'href' => APP_URL.'/reports.php',      'key' => 'reports'],
    ];
} elseif ($r === 'manager') {
    // Manager: no user management, no reports (admin-only)
    $navItems = [
        ['icon' => '⊞',  'label' => 'Dashboard',  'href' => APP_URL.'/dashboard.php',   'key' => 'dashboard'],
        ['icon' => '👥', 'label' => 'Members',     'href' => APP_URL.'/members.php',      'key' => 'members'],
        ['icon' => '📋', 'label' => 'Attendance',  'href' => APP_URL.'/attendance.php',   'key' => 'attendance'],
        ['icon' => '💳', 'label' => 'Payments',    'href' => APP_URL.'/payments.php',     'key' => 'payments'],
        ['icon' => '📅', 'label' => 'Classes',     'href' => APP_URL.'/classes.php',      'key' => 'classes'],
    ];
} elseif ($r === 'staff') {
    // Staff: limited access — dashboard handles check-in, attendance shows own records
    $navItems = [
        ['icon' => '⊞',  'label' => 'Dashboard',    'href' => APP_URL.'/dashboard.php',  'key' => 'dashboard'],
        ['icon' => '📋', 'label' => 'My Attendance', 'href' => APP_URL.'/attendance.php', 'key' => 'attendance'],
    ];
} elseif ($r === 'trainer') {
    // Trainer: see assigned members, own classes, BMI records
    $navItems = [
        ['icon' => '⊞',  'label' => 'Dashboard',   'href' => APP_URL.'/dashboard.php',        'key' => 'dashboard'],
        ['icon' => '🏋', 'label' => 'My Members',   'href' => APP_URL.'/trainer_members.php',  'key' => 'trainer_members'],
        ['icon' => '📅', 'label' => 'My Classes',   'href' => APP_URL.'/classes.php',          'key' => 'classes'],
        ['icon' => '⚖️', 'label' => 'BMI Records',  'href' => APP_URL.'/bmi.php',              'key' => 'bmi'],
    ];
} elseif ($r === 'member') {
    // Member: personal pages only
    $navItems = [
        ['icon' => '⊞',  'label' => 'Dashboard',    'href' => APP_URL.'/dashboard.php',   'key' => 'dashboard'],
        ['icon' => '📋', 'label' => 'My Attendance', 'href' => APP_URL.'/attendance.php',  'key' => 'attendance'],
        ['icon' => '💳', 'label' => 'Payments',      'href' => APP_URL.'/payments.php',    'key' => 'payments'],
        ['icon' => '📅', 'label' => 'Classes',       'href' => APP_URL.'/classes.php',     'key' => 'classes'],
        ['icon' => '⚖️', 'label' => 'BMI Tracker',   'href' => APP_URL.'/bmi.php',         'key' => 'bmi'],
    ];
}
?>

<!-- ── Sidebar HTML ── -->
<aside class="sidebar" id="sidebar">

  <!-- Sidebar top: shows role label and username -->
  <div class="sidebar-header">
    <span class="role-label"><?= e(strtoupper($r)) ?></span>
    <span class="role-name"><?= e($_SESSION['user_name'] ?? '') ?></span>
  </div>

  <!-- Navigation links -->
  <nav class="sidebar-nav">
    <?php foreach ($navItems as $item): ?>
      <!-- Add class "active" to the currently-open page's link -->
      <a href="<?= e($item['href']) ?>"
         class="nav-item <?= ($activeNav ?? '') === $item['key'] ? 'active' : '' ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <span class="nav-label"><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- Bottom links: always visible to all roles -->
  <div class="sidebar-footer">
    <a href="<?= APP_URL ?>/auth/change_password.php"
       class="nav-item <?= ($activeNav ?? '') === 'change_password' ? 'active' : '' ?>">
      <span class="nav-icon">🔑</span>
      <span class="nav-label">Change Password</span>
    </a>
    <a href="<?= APP_URL ?>/auth/logout.php" class="nav-item">
      <span class="nav-icon">⏻</span>
      <span class="nav-label">Sign Out</span>
    </a>
  </div>
</aside>

<!-- Dark overlay shown behind sidebar on mobile (click to close) -->
<div class="sidebar-overlay"
     onclick="document.body.classList.remove('sidebar-open')"></div>
