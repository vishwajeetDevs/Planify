<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/functions.php';
require_once '../../config/db.php';
require_once '../../src/MailHelper.php';

// Log errors but don't display them in production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Authentication required', 
        'redirect' => '/login.php'
    ]);
    exit;
}

// Validate CSRF token
validateCSRFToken();

$userId = $_SESSION['user_id'];

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Get the card ID from POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $cardId = isset($input['id']) ? $input['id'] : null;
    
    // Fallback to regular POST data if JSON parsing fails
    if ($cardId === null) {
        $cardId = $_POST['id'] ?? null;
    }

    // Validate card ID
    if (!$cardId || !is_numeric($cardId)) {
        throw new Exception('Invalid card ID');
    }

    $cardId = (int)$cardId;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('User not authenticated');
    }

    // First, verify the user has permission to delete this card
    $stmt = $conn->prepare("
        SELECT c.id, c.title, l.board_id, b.name as board_name
        FROM cards c
        JOIN lists l ON c.list_id = l.id
        JOIN boards b ON l.board_id = b.id
        JOIN board_members bm ON l.board_id = bm.board_id
        WHERE c.id = ? AND bm.user_id = ? AND (bm.role = 'owner' OR bm.role = 'member')
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $cardId, $userId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Card not found or permission denied');
    }

    // Get board ID and task info for activity log and notifications
    $boardData = $result->fetch_assoc();
    $boardId = $boardData['board_id'] ?? 0;
    $taskTitle = $boardData['title'] ?? 'Task';
    $boardName = $boardData['board_name'] ?? '';
    
    // Get assignees before deleting for email notification
    $assigneesStmt = $conn->prepare("
        SELECT u.id, u.name, u.email
        FROM card_assignees ca
        JOIN users u ON ca.user_id = u.id
        WHERE ca.card_id = ? AND u.id != ?
    ");
    $assigneesStmt->bind_param('ii', $cardId, $userId);
    $assigneesStmt->execute();
    $assigneesToNotify = $assigneesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $assigneesStmt->close();
    
    // Get actor name
    $actorName = $_SESSION['user_name'] ?? 'Someone';

    // Begin transaction
    $conn->begin_transaction();
    // Function to execute a prepared statement with error handling
    function executeStatement($conn, $sql, $params = [], $types = '') {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute statement: ' . $stmt->error);
        }
        
        return $stmt;
    }

    // Delete card attachments (if any)
    executeStatement($conn, "DELETE FROM attachments WHERE card_id = ?", [$cardId], 'i');
    
    // Delete card comments (if any)
    executeStatement($conn, "DELETE FROM comments WHERE card_id = ?", [$cardId], 'i');
    
    // Delete card checklists and items (if any)
    executeStatement($conn, "
        DELETE ci FROM checklist_items ci
        JOIN checklists c ON ci.checklist_id = c.id
        WHERE c.card_id = ?", [$cardId], 'i');
    
    executeStatement($conn, "DELETE FROM checklists WHERE card_id = ?", [$cardId], 'i');
    
    // Delete card labels (if any)
    executeStatement($conn, "DELETE FROM card_labels WHERE card_id = ?", [$cardId], 'i');
    
    // Finally, delete the card
    executeStatement($conn, "DELETE FROM cards WHERE id = ?", [$cardId], 'i');
    
    // Log activity
    $activityDesc = "deleted a card";
    executeStatement($conn, 
        "INSERT INTO activities (user_id, board_id, action, description) VALUES (?, ?, 'delete_card', ?)",
        [$userId, $boardId, $activityDesc], 'iis');
    
    $conn->commit();
    
    // Send email notifications to assignees about task deletion
    if (!empty($assigneesToNotify)) {
        foreach ($assigneesToNotify as $assignee) {
            if (!empty($assignee['email']) && filter_var($assignee['email'], FILTER_VALIDATE_EMAIL)) {
                MailHelper::sendTaskUpdateEmail(
                    $assignee['email'],
                    $assignee['name'],
                    $taskTitle,
                    'task_deleted',
                    $actorName,
                    $boardName,
                    [],
                    ''
                );
            }
        }
    }
    
    $response = [
        'success' => true,
        'message' => 'Card deleted successfully'
    ];
    
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    
    error_log("Error in delete.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    // Log the full error for debugging (server-side only)
    error_log("Card delete error - CardId: " . ($cardId ?? 'null') . ", UserId: " . ($userId ?? 'null') . ", Error: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'message' => 'An error occurred while deleting the card. Please try again.'
    ];
}

// Ensure no output before this
while (ob_get_level()) ob_end_clean();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
