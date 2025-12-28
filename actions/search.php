<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../helpers/IdEncrypt.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$query = $_GET['q'] ?? '';
$userId = $_SESSION['user_id'];
$workspaceId = isset($_GET['workspace_id']) && $_GET['workspace_id'] !== '' ? intval($_GET['workspace_id']) : null;
$boardId = isset($_GET['board_id']) && $_GET['board_id'] !== '' ? intval($_GET['board_id']) : null;

if (strlen($query) < 2) {
    jsonResponse(['success' => false, 'message' => 'Query too short'], 400);
}

try {
    // Search cards with optional workspace/board filter
    $searchTerm = '%' . $query . '%';
    
    // Build dynamic query based on filters
    $sql = "
        SELECT c.id, c.title, c.description,
               l.title as list_name,
               b.id as board_id, b.name as board_name,
               w.id as workspace_id, w.name as workspace_name,
               c.due_date, c.priority,
               (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') 
                FROM card_assignees ca 
                JOIN users u ON ca.user_id = u.id 
                WHERE ca.card_id = c.id) as assignees
        FROM cards c
        INNER JOIN lists l ON c.list_id = l.id
        INNER JOIN boards b ON l.board_id = b.id
        INNER JOIN workspaces w ON b.workspace_id = w.id
        LEFT JOIN board_members bm ON b.id = bm.board_id
        WHERE (b.created_by = ? OR bm.user_id = ?)
          AND (c.title LIKE ? OR c.description LIKE ?)
    ";
    
    $params = [$userId, $userId, $searchTerm, $searchTerm];
    $types = "iiss";
    
    // Add workspace filter if provided
    if ($workspaceId) {
        $sql .= " AND w.id = ?";
        $params[] = $workspaceId;
        $types .= "i";
    }
    
    // Add board filter if provided
    if ($boardId) {
        $sql .= " AND b.id = ?";
        $params[] = $boardId;
        $types .= "i";
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.updated_at DESC LIMIT 15";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Format results with additional info
    foreach ($results as &$result) {
        // Add encrypted board reference for secure URLs
        $result['board_ref'] = encryptId($result['board_id']);
        
        // Format due date and calculate priority based on deadline
        if ($result['due_date']) {
            $dueDate = new DateTime($result['due_date']);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $dueDate->setTime(0, 0, 0);
            
            $diff = $today->diff($dueDate);
            $daysUntilDue = (int)$diff->format('%r%a'); // Negative if overdue
            
            $result['due_date_formatted'] = $dueDate->format('M j, Y');
            $result['is_overdue'] = $daysUntilDue < 0;
            
            // Calculate priority based on due date
            if ($daysUntilDue < 0) {
                $result['priority'] = 'overdue';
                $result['priority_label'] = 'Overdue';
            } elseif ($daysUntilDue <= 2) {
                $result['priority'] = 'high';
                $result['priority_label'] = 'High';
            } elseif ($daysUntilDue <= 7) {
                $result['priority'] = 'medium';
                $result['priority_label'] = 'Medium';
            } else {
                $result['priority'] = 'low';
                $result['priority_label'] = 'Low';
            }
        } else {
            // No due date - no priority
            $result['priority'] = null;
            $result['priority_label'] = null;
        }
    }
    
    jsonResponse([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'filters' => [
            'workspace_id' => $workspaceId,
            'board_id' => $boardId
        ]
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Search error: ' . $e->getMessage()], 500);
}
?>