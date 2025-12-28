<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

$input = json_decode(file_get_contents('php://input'), true);
$theme = $input['theme'] ?? 'light';
$userId = $_SESSION['user_id'];

if (!in_array($theme, ['light', 'dark'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid theme'], 400);
}

try {
    // Update user theme preference
    $stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
    $stmt->bind_param("si", $theme, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['theme'] = $theme;
        jsonResponse(['success' => true, 'message' => 'Theme updated', 'theme' => $theme]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update theme'], 500);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>