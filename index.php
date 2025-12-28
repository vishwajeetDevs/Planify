<?php
/**
 * Planify - Entry Point
 * 
 * This is the main entry point of the application.
 * It redirects users based on their authentication status:
 * - Logged in users -> Dashboard
 * - Not logged in users -> Login page
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header('Location: public/dashboard.php');
} else {
    // User is not logged in, redirect to login
    header('Location: public/login.php');
}
exit;

