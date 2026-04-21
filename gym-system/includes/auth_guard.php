<?php
// ──────────────────────────────────────────────────
//  includes/auth_guard.php  —  Login protection
//  Include this file at the top of EVERY protected page
//  It provides helper functions used throughout the app
// ──────────────────────────────────────────────────

// Start session if it hasn't started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ──────────────────────────────────────────────────
//  requireAuth()  —  Protects a page from guests
//
//  Usage: requireAuth(['admin', 'manager'])
//  This will:
//   1. Redirect to login if not logged in
//   2. Show 403 error if role is not allowed
// ──────────────────────────────────────────────────
function requireAuth(array $allowedRoles = []): void {

    // STEP 1: Check if the user is logged in
    if (empty($_SESSION['user_id'])) {
        // No session → not logged in → send to login
        $base = defined('APP_URL') ? APP_URL : '';
        header('Location: ' . $base . '/auth/login.php');
        exit;
    }

    // STEP 2: Check if the user's role is allowed on this page
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        // Role not in the allowed list → access denied
        $base = defined('APP_URL') ? APP_URL : '';
        http_response_code(403);
        echo '<div style="font-family:monospace;padding:40px;color:#ef5350;background:#121212;min-height:100vh">
              <h2>403 — Access Denied</h2>
              <p>You do not have permission to view this page.</p>
              <a href="' . $base . '/index.php" style="color:#4CAF50">← Go back to dashboard</a>
              </div>';
        exit;
    }
}

// ──────────────────────────────────────────────────
//  e()  —  Safe output (prevents XSS attacks)
//  Always use e() when printing user-supplied data
//  Example: echo e($user['name']);
// ──────────────────────────────────────────────────
function e(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

// ── Shortcut helpers ──────────────────────────────

// role()   — Returns the logged-in user's role (e.g. 'admin')
function role(): string { return $_SESSION['role'] ?? ''; }

// uid()    — Returns the logged-in user's ID as integer
function uid(): int    { return (int)($_SESSION['user_id'] ?? 0); }

// ── Get the member record ID for logged-in member ──
function getMemberId(): int {
    // Only works if user is a member
    if (role() !== 'member') return 0;

    // Query the members table to find the member record for this user
    $stmt = db()->prepare("SELECT id FROM members WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

// ── Get the trainer record for logged-in trainer ──
function getTrainerRow(): ?array {
    if (role() !== 'trainer') return null;

    $stmt = db()->prepare("SELECT * FROM trainers WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// ── BMI helper functions ──────────────────────────

// Calculate BMI from height (cm) and weight (kg)
function calcBMI(float $heightCm, float $weightKg): float {
    if ($heightCm <= 0) return 0;
    $heightM = $heightCm / 100;           // convert cm to meters
    return round($weightKg / ($heightM ** 2), 2); // BMI formula: weight / height²
}

// Return the BMI category name
function bmiCategory(float $bmi): string {
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25.0) return 'Normal';
    if ($bmi < 30.0) return 'Overweight';
    return 'Obese';
}

// Return a CSS color string based on BMI value
function bmiColor(float $bmi): string {
    if ($bmi < 18.5) return 'color:#64b5f6'; // blue = underweight
    if ($bmi < 25.0) return 'color:#4CAF50'; // green = normal
    if ($bmi < 30.0) return 'color:#ffa726'; // orange = overweight
    return 'color:#ef5350';                  // red = obese
}
