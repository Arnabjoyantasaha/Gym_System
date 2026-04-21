<?php
// ──────────────────────────────────────────────────
//  includes/header.php  —  Top navigation bar
//  Included at the top of every protected page.
//
//  Variables set by the page before including this:
//    $pageTitle  - the browser tab title
//    $activeNav  - which sidebar link is active
// ──────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Browser tab title -->
  <title><?= e($pageTitle ?? 'FitCore Pro') ?> — FitCore Pro</title>

  <!-- Google Fonts: Bebas Neue for headings, DM Sans for body text -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

  <!-- Main stylesheet (our dark theme) -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<!-- ── Top Navigation Bar ── -->
<nav class="topnav">

  <!-- Left side: hamburger menu + brand logo -->
  <div class="topnav-left">
    <!-- Hamburger button: toggles sidebar on mobile -->
    <button class="sidebar-toggle"
            onclick="document.body.classList.toggle('sidebar-open')"
            aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>

    <!-- Brand name: clicking goes to home page -->
    <a href="<?= APP_URL ?>/index.php" class="brand">
      <span class="brand-name">FITCORE</span>
      <span class="brand-sub">PRO</span>
    </a>
  </div>

  <!-- Right side: role badge + user avatar with dropdown -->
  <div class="topnav-right">
    <!-- Role badge (e.g. ADMIN, MEMBER) -->
    <div class="nav-badge"><?= e(strtoupper(role())) ?></div>

    <!-- User menu: shows avatar + name, hover shows dropdown -->
    <div class="user-menu">
      <!-- Avatar circle with first letter of name -->
      <div class="user-avatar">
        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
      </div>
      <span class="user-name"><?= e($_SESSION['user_name'] ?? '') ?></span>

      <!-- Dropdown menu (appears on hover) -->
      <div class="user-dropdown">
        <a href="<?= APP_URL ?>/auth/change_password.php">🔑 Change Password</a>
        <a href="<?= APP_URL ?>/auth/logout.php">⏻ Sign Out</a>
      </div>
    </div>
  </div>
</nav>

<!-- ── Main Layout Wrapper: sidebar + content side by side ── -->
<div class="layout-wrapper">
