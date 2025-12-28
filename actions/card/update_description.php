<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/NotificationHelper.php';
require_once '../../src/MailHelper.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
$csrfToken = $data['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    jsonResponse(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.'], 403);
}

$cardId = isset($data['card_id']) ? intval($data['card_id']) : 0;
$description = isset($data['description']) ? trim($data['description']) : '';
$userId = $_SESSION['user_id'];

if ($cardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid card ID'], 400);
}

try {
    // Get card and board info
    $stmt = $conn->prepare("
        SELECT c.*, l.board_id 
        FROM cards c 
        JOIN lists l ON c.list_id = l.id 
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $cardId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Card not found'], 404);
    }
    
    $card = $result->fetch_assoc();
    $boardId = $card['board_id'];
    $oldDescription = $card['description'] ?? '';
    
    // Check permission
    if (!canEditBoard($conn, $userId, $boardId)) {
        jsonResponse(['success' => false, 'message' => 'You do not have permission to edit this card'], 403);
    }
    
    // Update description
    $stmt = $conn->prepare("UPDATE cards SET description = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $description, $cardId);
    
    if ($stmt->execute()) {
        // Log activity
        logActivity($conn, $boardId, $userId, 'update_card_description', 'Updated card description', $cardId);
        
        // Check if description changed for email notification
        $shouldSendEmail = ($description !== $oldDescription);
        
        // Send response immediately (before email)
        $response = json_encode([
            'success' => true,
            'message' => 'Description updated successfully',
            'description' => $description
        ]);
        
        header('Content-Length: ' . strlen($response));
        header('Connection: close');
        echo $response;
        
        // Flush output to send response immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
            if (function_exists('ob_flush')) @ob_flush();
        }
        
        // Send notifications in background
        if ($shouldSendEmail) {
            try {
                // Send in-app notification to assigned users
                $notificationHelper = new NotificationHelper($conn);
                $notificationHelper->notifyTaskAssignees($cardId, $userId, 'description_updated');
                
                // Send email notification
                MailHelper::sendTaskUpdateNotifications(
                    $conn,
                    $cardId,
                    'description_changed',
                    $userId,
                    ['new_description' => $description]
                );
            } catch (Exception $e) {
                error_log('Notification error: ' . $e->getMessage());
            }
        }
        
        exit;
    } else {
        throw new Exception('Failed to update description');
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>

