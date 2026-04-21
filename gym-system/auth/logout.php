<?php
// ──────────────────────────────────────────────────
//  auth/logout.php  —  Log the user out
// ──────────────────────────────────────────────────

session_start();
session_unset();
session_destroy();

// Use absolute path so it works regardless of server config
require_once '../config/database.php';
header('Location: ' . APP_URL . '/auth/login.php');
exit;
