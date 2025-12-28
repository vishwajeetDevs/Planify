<?php
/**
 * Public Index - Redirect to appropriate page
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header('Location: dashboard.php');
} else {
    // User is not logged in, redirect to login
    header('Location: login.php');
}
exit;
