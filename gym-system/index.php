<?php
// ──────────────────────────────────────────────────
//  index.php  —  Entry point of the application
//  If the user is logged in → go to dashboard
//  If not logged in → go to login page
// ──────────────────────────────────────────────────

// Start a session so we can read login data
session_start();

// Load the database config (needed for APP_URL constant)
require_once 'config/database.php';

// Check if the user is logged in (user_id stored in session means logged in)
if (empty($_SESSION['user_id'])) {
    // Not logged in → send to login page
    header('Location: auth/login.php');
    exit;
}

// User is logged in → send to dashboard
header('Location: dashboard.php');
exit;
