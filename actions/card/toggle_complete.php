<?php
/**
 * Toggle Card Completion Status API
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress PHP errors from appearing in output
error_reporting(0);
ini_set('display_errors', 0);

// Clear any previous output
while (ob_get_level()) ob_end_clean();
ob_start();

require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/NotificationHelper.php';

// Clean buffer before output
ob_clean();
header('Content-Type: application/json');

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
$csrfToken = $input['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
    exit;
}

$cardId = $input['card_id'] ?? null;

if (!$cardId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Card ID required']);
    exit;
}

try {
    // Get card and verify access
    $stmt = $conn->prepare("
        SELECT c.*, l.board_id 
        FROM cards c
        INNER JOIN lists l ON c.list_id = l.id
        WHERE c.id = ?
    ");
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    $stmt->bind_param("i", $cardId);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$card) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Card not found']);
        exit;
    }
    
    // Check if user can edit the board (owner, admin, or member - not viewer/commenter)
    if (!canEditBoard($conn, $userId, $card['board_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this card']);
        exit;
    }
    
    // Toggle the completion status
    $newStatus = !$card['is_completed'];
    $completedAt = $newStatus ? date('Y-m-d H:i:s') : null;
    
    $updateStmt = $conn->prepare("
        UPDATE cards 
        SET is_completed = ?, completed_at = ?
        WHERE id = ?
    ");
    if (!$updateStmt) {
        throw new Exception("Failed to prepare update: " . $conn->error);
    }
    $updateStmt->bind_param("isi", $newStatus, $completedAt, $cardId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Log activity
    $action = $newStatus ? 'completed' : 'uncompleted';
    $description = "marked task as " . ($newStatus ? 'completed' : 'incomplete');
    logActivity($conn, $card['board_id'], $userId, $action, $description, $cardId);
    
    // Notify assignees about completion status change
    $notificationHelper = new NotificationHelper($conn);
    $notificationHelper->notifyTaskCompleted($cardId, $userId, $newStatus);
    
    echo json_encode([
        'success' => true,
        'is_completed' => $newStatus,
        'completed_at' => $completedAt,
        'message' => $newStatus ? 'Task marked as completed' : 'Task marked as incomplete'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

