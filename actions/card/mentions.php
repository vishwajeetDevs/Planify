<?php
/**
 * Get Card Mentions API
 * Returns mentioned users for cards on a board (for task card avatars)
 */

// Ensure no output before headers
while (ob_get_level()) {
    ob_end_clean();
}

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

global $conn;

// Check database connection
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get board ID or card IDs
$board_id = filter_input(INPUT_GET, 'board_id', FILTER_VALIDATE_INT);
$card_ids_param = $_GET['card_ids'] ?? '';

if (!$board_id && empty($card_ids_param)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Board ID or Card IDs required']);
    exit;
}

try {
    // If board_id provided, verify access and get all cards
    if ($board_id) {
        $accessStmt = $conn->prepare("
            SELECT role FROM board_members 
            WHERE board_id = ? AND user_id = ?
        ");
        $accessStmt->bind_param('ii', $board_id, $_SESSION['user_id']);
        $accessStmt->execute();
        $accessResult = $accessStmt->get_result();
        
        if ($accessResult->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        // Get all mentioned users grouped by card
        $stmt = $conn->prepare("
            SELECT 
                cm.card_id,
                cm.mentioned_user_id,
                u.name,
                u.avatar
            FROM comment_mentions cm
            INNER JOIN cards c ON cm.card_id = c.id
            INNER JOIN lists l ON c.list_id = l.id
            INNER JOIN users u ON cm.mentioned_user_id = u.id
            WHERE l.board_id = ?
            ORDER BY cm.card_id, cm.created_at DESC
        ");
        $stmt->bind_param('i', $board_id);
    } else {
        // Parse card IDs
        $card_ids = array_filter(array_map('intval', explode(',', $card_ids_param)));
        if (empty($card_ids)) {
            echo json_encode(['success' => true, 'mentions' => []]);
            exit;
        }
        
        // Get mentioned users for specific cards
        $placeholders = implode(',', array_fill(0, count($card_ids), '?'));
        $types = str_repeat('i', count($card_ids));
        
        $stmt = $conn->prepare("
            SELECT 
                cm.card_id,
                cm.mentioned_user_id,
                u.name,
                u.avatar
            FROM comment_mentions cm
            INNER JOIN users u ON cm.mentioned_user_id = u.id
            WHERE cm.card_id IN ($placeholders)
            ORDER BY cm.card_id, cm.created_at DESC
        ");
        $stmt->bind_param($types, ...$card_ids);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Group by card_id
    $mentionsByCard = [];
    while ($row = $result->fetch_assoc()) {
        $cardId = (int)$row['card_id'];
        $userId = (int)$row['mentioned_user_id'];
        
        if (!isset($mentionsByCard[$cardId])) {
            $mentionsByCard[$cardId] = [];
        }
        
        // Avoid duplicates
        $exists = false;
        foreach ($mentionsByCard[$cardId] as $existing) {
            if ($existing['id'] === $userId) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $mentionsByCard[$cardId][] = [
                'id' => $userId,
                'name' => $row['name'],
                'avatar' => $row['avatar']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'mentions' => $mentionsByCard
    ]);
    
} catch (Exception $e) {
    error_log('Error in card/mentions.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}

