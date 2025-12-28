<?php
/**
 * Theme Color API
 * Handles saving and retrieving user theme color preference
 */

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
$themeColor = $input['theme_color'] ?? 'purple';
$userId = $_SESSION['user_id'];

// Valid theme colors
$validColors = [
    'indigo', 'blue', 'purple', 'pink', 'rose', 'red', 
    'orange', 'amber', 'green', 'emerald', 'teal', 'cyan', 'slate'
];

if (!in_array($themeColor, $validColors)) {
    jsonResponse(['success' => false, 'message' => 'Invalid theme color'], 400);
}

try {
    // Update user theme color preference
    $stmt = $conn->prepare("UPDATE users SET theme_color = ? WHERE id = ?");
    $stmt->bind_param("si", $themeColor, $userId);
    
    if ($stmt->execute()) {
        $_SESSION['theme_color'] = $themeColor;
        jsonResponse([
            'success' => true, 
            'message' => 'Theme color updated', 
            'theme_color' => $themeColor
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update theme color'], 500);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>

