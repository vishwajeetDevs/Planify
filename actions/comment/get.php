<?php
// Ensure no output before headers - clean any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start session and include required files
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Set JSON header first, before any output
header('Content-Type: application/json; charset=utf-8');

global $conn;

// Ensure we have a valid database connection
if (!isset($conn) || !$conn) {
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    if (ob_get_level()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$card_id = filter_input(INPUT_GET, 'card_id', FILTER_VALIDATE_INT);

if (!$card_id) {
    http_response_code(400);
    if (ob_get_level()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid card ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Check if comments table exists
    $result = $conn->query("SHOW TABLES LIKE 'comments'");
    $tableExists = $result && $result->num_rows > 0;
    
    if (!$tableExists) {
        // Create comments table if it doesn't exist
        $conn->query("CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            card_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;");
        
        if (ob_get_level()) ob_clean();
        echo json_encode(['success' => true, 'comments' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get comments with user information
    $stmt = $conn->prepare("
        SELECT c.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar
        FROM comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.card_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->bind_param('i', $card_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    
    // Collect all mentioned user IDs to fetch in one query
    $allMentionedIds = [];
    $rawComments = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['user_name'])) {
            $row['user_name'] = 'Unknown User';
        }
        // Ensure user_id is an integer for proper JavaScript comparison
        $row['user_id'] = (int)$row['user_id'];
        $row['id'] = (int)$row['id'];
        $row['card_id'] = (int)$row['card_id'];
        
        // Keep timestamps as-is, frontend will handle display
        // Just ensure they're in a parseable format
        
        // Parse mentioned_user_ids JSON
        $mentionedIds = [];
        if (!empty($row['mentioned_user_ids'])) {
            $mentionedIds = json_decode($row['mentioned_user_ids'], true) ?: [];
            $allMentionedIds = array_merge($allMentionedIds, $mentionedIds);
        }
        $row['mentioned_user_ids'] = $mentionedIds;
        $rawComments[] = $row;
    }
    
    // Fetch all mentioned users in one query
    $mentionedUsersMap = [];
    if (!empty($allMentionedIds)) {
        $allMentionedIds = array_unique($allMentionedIds);
        $placeholders = implode(',', array_fill(0, count($allMentionedIds), '?'));
        $types = str_repeat('i', count($allMentionedIds));
        $users_stmt = $conn->prepare("SELECT id, name, avatar FROM users WHERE id IN ($placeholders)");
        $users_stmt->bind_param($types, ...$allMentionedIds);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();
        while ($user = $users_result->fetch_assoc()) {
            $mentionedUsersMap[(int)$user['id']] = [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'avatar' => $user['avatar']
            ];
        }
    }
    
    // Add mentioned_users to each comment
    foreach ($rawComments as $row) {
        $row['mentioned_users'] = [];
        if (!empty($row['mentioned_user_ids'])) {
            foreach ($row['mentioned_user_ids'] as $mid) {
                if (isset($mentionedUsersMap[$mid])) {
                    $row['mentioned_users'][] = $mentionedUsersMap[$mid];
                }
            }
        }
        $comments[] = $row;
    }
    
    // Get current database time for relative time calculation
    $dbTimeResult = $conn->query("SELECT NOW() as db_time");
    $dbTime = date('Y-m-d H:i:s');
    if ($dbTimeResult && $dbRow = $dbTimeResult->fetch_assoc()) {
        $dbTime = $dbRow['db_time'];
    }
    
    // Clean output buffer before sending JSON
    if (ob_get_level()) ob_clean();
    echo json_encode([
        'success' => true, 
        'comments' => $comments,
        'current_user_id' => (int)$_SESSION['user_id'], // Include current user ID for permission checking (cast to int)
        'server_time' => $dbTime // Database server time for accurate relative time calculation
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Exception $e) {
    error_log('Error in comment/get.php: ' . $e->getMessage());
    http_response_code(500);
    
    // Clean any output before sending JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading comments',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
