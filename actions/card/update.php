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

// Validate CSRF token
validateCSRFToken();

// Get card ID from POST or GET (URL query string)
$cardId = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
$title = isset($_POST['title']) ? trim($_POST['title']) : null;
$description = isset($_POST['description']) ? trim($_POST['description']) : null;
$dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$userId = $_SESSION['user_id'];

if ($cardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid card ID'], 400);
}

if ($title === null && $description === null && $dueDate === null) {
    jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
}

try {
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
    $oldTitle = $card['title'];
    $oldDescription = $card['description'] ?? '';
    $oldDueDate = $card['due_date'];
    
    if (!canEditBoard($conn, $userId, $boardId)) {
        jsonResponse(['success' => false, 'message' => 'You do not have permission to edit this card'], 403);
    }
    
    $updateFields = [];
    $updateParams = [];
    $types = '';
    
    if ($title !== null) {
        if (empty($title)) {
            jsonResponse(['success' => false, 'message' => 'Card title cannot be empty'], 400);
        }
        $updateFields[] = 'title = ?';
        $updateParams[] = $title;
        $types .= 's';
    }
    
    if ($description !== null) {
        $updateFields[] = 'description = ?';
        $updateParams[] = $description;
        $types .= 's';
    }
    
    if ($dueDate !== null) {
        $updateFields[] = 'due_date = ?';
        $updateParams[] = $dueDate;
        $types .= 's';
    }
    
    if (empty($updateFields)) {
        jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }
    
    $updateParams[] = $cardId;
    $types .= 'i';
    
    $sql = "UPDATE cards SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    $bindParams = [];
    $bindParams[] = $types;
    foreach ($updateParams as $key => $value) {
        $bindParams[] = &$updateParams[$key];
    }
    
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    
    if ($stmt->execute()) {
        $action = 'update_card';
        $details = 'Updated card';
        if ($description !== null) {
            $action = 'update_card_description';
            $details = 'Updated card description';
        }
        logActivity($conn, $boardId, $userId, $action, $details, $cardId);
        
        // Send in-app and email notifications for changes
        $notificationHelper = new NotificationHelper($conn);
        
        // Title changed
        if ($title !== null && $title !== $oldTitle) {
            $notificationHelper->notifyTaskAssignees($cardId, $userId, 'title_changed');
            MailHelper::sendTaskUpdateNotifications(
                $conn,
                $cardId,
                'title_changed',
                $userId,
                ['old_title' => $oldTitle, 'new_title' => $title]
            );
        }
        
        // Description changed
        if ($description !== null && $description !== $oldDescription) {
            $notificationHelper->notifyTaskAssignees($cardId, $userId, 'description_updated');
            MailHelper::sendTaskUpdateNotifications(
                $conn,
                $cardId,
                'description_changed',
                $userId,
                ['new_description' => $description]
            );
        }
        
        // Due date changed
        if ($dueDate !== null && $dueDate !== $oldDueDate) {
            $formattedDate = $dueDate ? date('M j, Y', strtotime($dueDate)) : 'Removed';
            $notificationHelper->notifyDueDateChange($cardId, $userId, $formattedDate);
            MailHelper::sendTaskUpdateNotifications(
                $conn,
                $cardId,
                'dates_changed',
                $userId,
                ['due_date' => $formattedDate]
            );
        }
        
        $stmt = $conn->prepare("SELECT * FROM cards WHERE id = ?");
        $stmt->bind_param('i', $cardId);
        $stmt->execute();
        $updatedCard = $stmt->get_result()->fetch_assoc();
        
        jsonResponse([
            'success' => true,
            'message' => 'Card updated successfully',
            'card' => [
                'id' => $updatedCard['id'],
                'title' => $updatedCard['title'],
                'description' => $updatedCard['description'],
                'due_date' => $updatedCard['due_date']
            ]
        ]);
    } else {
        throw new Exception('Failed to update card: ' . $conn->error);
    }
} catch (Exception $e) {
    secureErrorResponse($e, 'Card update');
}
?>