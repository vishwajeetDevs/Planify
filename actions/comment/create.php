<?php
// Ensure no output before headers - clean any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start session and include required files
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/NotificationHelper.php';

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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    if (ob_get_level()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please log in'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate CSRF token
validateCSRFToken();

// Get and validate input - handle both JSON and FormData
$isFormData = !empty($_POST) || !empty($_FILES);
$attachment_path = null;
$attachment_name = null;
$attachment_type = null;

// Detailed debug logging to trace file upload issues
$debugInfo = [
    'isFormData' => $isFormData,
    'has_POST' => !empty($_POST),
    'has_FILES' => !empty($_FILES),
    'POST_keys' => array_keys($_POST),
    'FILES_keys' => array_keys($_FILES),
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
];
error_log('[Comment Create] Debug: ' . json_encode($debugInfo));

if (!empty($_FILES)) {
    foreach ($_FILES as $key => $file) {
        error_log("[Comment Create] FILE[$key]: name={$file['name']}, size={$file['size']}, error={$file['error']}, type={$file['type']}");
    }
}

if ($isFormData) {
    // FormData submission (with or without attachment)
    $card_id = filter_var($_POST['card_id'] ?? 0, FILTER_VALIDATE_INT);
    $content = trim($_POST['content'] ?? '');
    $mentioned_user_ids = isset($_POST['mentioned_user_ids']) ? json_decode($_POST['mentioned_user_ids'], true) : [];
    
    // Handle attachment upload (supports both 'attachment' and 'image' field names for backward compatibility)
    $fileField = isset($_FILES['attachment']) ? 'attachment' : (isset($_FILES['image']) ? 'image' : null);
    error_log("[Comment Create] FileField: " . ($fileField ?: 'none'));
    
    if ($fileField && isset($_FILES[$fileField])) {
        error_log("[Comment Create] File error code: " . $_FILES[$fileField]['error']);
        
        if ($_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
            ];
            error_log("[Comment Create] Upload error: " . ($errorMessages[$_FILES[$fileField]['error']] ?? 'Unknown'));
        }
    }
    
    if ($fileField && isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$fileField];
        
        // Allowed file types
        $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowed_file_types = [
            'application/pdf',
            'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'application/zip', 
            'application/x-rar-compressed', 
            'application/x-7z-compressed'
        ];
        $all_allowed_types = array_merge($allowed_image_types, $allowed_file_types);
        $max_size = 10 * 1024 * 1024; // 10MB
        
        $is_image = in_array($file['type'], $allowed_image_types);
        
        if (!in_array($file['type'], $all_allowed_types)) {
            http_response_code(400);
            if (ob_get_level()) ob_clean();
            echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed: Images, PDF, Word, Excel, Text, ZIP'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if ($file['size'] > $max_size) {
            http_response_code(400);
            if (ob_get_level()) ob_clean();
            echo json_encode(['success' => false, 'message' => 'File must be less than 10MB'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Generate unique filename
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe_ext = preg_replace('/[^a-z0-9]/', '', $ext);
        $filename = 'comment_' . time() . '_' . uniqid() . '.' . $safe_ext;
        $upload_dir = dirname(dirname(__DIR__)) . '/uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $attachment_path = $filename;
            $attachment_name = $file['name'];
            $attachment_type = $is_image ? 'image' : 'file';
            error_log("[Comment Create] File saved successfully: $filename");
        } else {
            error_log("[Comment Create] Failed to move uploaded file to: $upload_path");
            http_response_code(500);
            if (ob_get_level()) ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to upload file'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
} else {
    // JSON submission (without attachment)
    $data = json_decode(file_get_contents('php://input'), true);
    $card_id = filter_var($data['card_id'] ?? 0, FILTER_VALIDATE_INT);
    $content = trim($data['content'] ?? '');
    $mentioned_user_ids = $data['mentioned_user_ids'] ?? [];
}

// Validate and sanitize mentioned user IDs
if (!is_array($mentioned_user_ids)) {
    $mentioned_user_ids = [];
}
$mentioned_user_ids = array_filter(array_map('intval', $mentioned_user_ids), function($id) {
    return $id > 0;
});
$mentioned_user_ids = array_unique($mentioned_user_ids);

if (!$card_id) {
    http_response_code(400);
    if (ob_get_level()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid card ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Allow empty content if there's an attachment
if (empty($content) && empty($attachment_path)) {
    http_response_code(400);
    if (ob_get_level()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    global $conn;
    
    // Check if the card exists and get board_id
    $stmt = $conn->prepare("SELECT c.id, l.board_id FROM cards c INNER JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
    $stmt->bind_param('i', $card_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        if (ob_get_level()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'Card not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $card = $result->fetch_assoc();
    $board_id = $card['board_id'];
    
    // Validate mentioned user IDs belong to the board
    $valid_mentioned_ids = [];
    if (!empty($mentioned_user_ids)) {
        $placeholders = implode(',', array_fill(0, count($mentioned_user_ids), '?'));
        $types = str_repeat('i', count($mentioned_user_ids) + 1);
        $params = array_merge([$board_id], $mentioned_user_ids);
        
        $validate_stmt = $conn->prepare("
            SELECT user_id FROM board_members 
            WHERE board_id = ? AND user_id IN ($placeholders)
        ");
        $validate_stmt->bind_param($types, ...$params);
        $validate_stmt->execute();
        $valid_result = $validate_stmt->get_result();
        while ($row = $valid_result->fetch_assoc()) {
            $valid_mentioned_ids[] = (int)$row['user_id'];
        }
    }
    
    // Convert to JSON for storage
    $mentioned_json = !empty($valid_mentioned_ids) ? json_encode($valid_mentioned_ids) : null;
    
    // Insert the comment with mentioned_user_ids, attachment info
    $stmt = $conn->prepare("INSERT INTO comments (card_id, user_id, content, mentioned_user_ids, image_path, attachment_name, attachment_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iisssss', $card_id, $_SESSION['user_id'], $content, $mentioned_json, $attachment_path, $attachment_name, $attachment_type);
    $success = $stmt->execute();
    
    if ($success) {
        $comment_id = $stmt->insert_id;
        
        // Insert into comment_mentions table for each mentioned user
        if (!empty($valid_mentioned_ids)) {
            $mention_stmt = $conn->prepare("
                INSERT INTO comment_mentions (comment_id, card_id, mentioned_user_id) 
                VALUES (?, ?, ?)
            ");
            foreach ($valid_mentioned_ids as $mentioned_id) {
                $mention_stmt->bind_param('iii', $comment_id, $card_id, $mentioned_id);
                $mention_stmt->execute();
                
                // Create notification for mentioned user (if not self-mention)
                if ($mentioned_id !== $_SESSION['user_id']) {
                    createMentionNotification($conn, $mentioned_id, $_SESSION['user_id'], $card_id, $board_id, $comment_id);
                }
            }
        }
        
        // Notify assignees about the new comment (excluding mentioned users who already got notified)
        $notificationHelper = new NotificationHelper($conn);
        $commentPreview = strip_tags($content);
        $notificationHelper->notifyCommentOnAssignedTask($card_id, $_SESSION['user_id'], $commentPreview, $valid_mentioned_ids);
        
        // Log activity - use correct column names: board_id, action (not type)
        $activity_desc = 'added a comment';
        $activity_action = 'comment';
        $activity_stmt = $conn->prepare("INSERT INTO activities (board_id, user_id, card_id, action, description) VALUES (?, ?, ?, ?, ?)");
        $activity_stmt->bind_param('iiiss', $board_id, $_SESSION['user_id'], $card_id, $activity_action, $activity_desc);
        $activity_stmt->execute();
        
        // Get the comment with user info for the response
        $comment_stmt = $conn->prepare("
            SELECT c.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar
            FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.id = ?"
        );
        $comment_stmt->bind_param('i', $comment_id);
        $comment_stmt->execute();
        $comment = $comment_stmt->get_result()->fetch_assoc();
        
        // Ensure IDs are integers for proper JavaScript comparison
        $comment['id'] = (int)$comment['id'];
        $comment['user_id'] = (int)$comment['user_id'];
        $comment['card_id'] = (int)$comment['card_id'];
        
        // Parse mentioned_user_ids JSON
        $comment['mentioned_user_ids'] = $comment['mentioned_user_ids'] 
            ? json_decode($comment['mentioned_user_ids'], true) 
            : [];
        
        // Get mentioned users' details for rendering
        $mentioned_users = [];
        if (!empty($valid_mentioned_ids)) {
            $placeholders = implode(',', array_fill(0, count($valid_mentioned_ids), '?'));
            $types = str_repeat('i', count($valid_mentioned_ids));
            $users_stmt = $conn->prepare("SELECT id, name, avatar FROM users WHERE id IN ($placeholders)");
            $users_stmt->bind_param($types, ...$valid_mentioned_ids);
            $users_stmt->execute();
            $users_result = $users_stmt->get_result();
            while ($user = $users_result->fetch_assoc()) {
                $mentioned_users[] = [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'avatar' => $user['avatar']
                ];
            }
        }
        $comment['mentioned_users'] = $mentioned_users;
        
        // Clean output buffer before sending JSON
        if (ob_get_level()) ob_clean();
        
        echo json_encode([
            'success' => true, 
            'comment' => $comment,
            'message' => 'Comment added successfully'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        throw new Exception('Failed to add comment to database');
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Comment creation error: ' . $e->getMessage());
    
    // Clean any output before sending JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'debug' => ['card_id' => $card_id ?? null, 'user_id' => $_SESSION['user_id'] ?? null]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Create a notification for a mentioned user
 */
function createMentionNotification($conn, $mentionedUserId, $mentionerUserId, $cardId, $boardId, $commentId) {
    try {
        // Get mentioner's name
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param('i', $mentionerUserId);
        $stmt->execute();
        $mentioner = $stmt->get_result()->fetch_assoc();
        
        // Get card title
        $stmt = $conn->prepare("SELECT title FROM cards WHERE id = ?");
        $stmt->bind_param('i', $cardId);
        $stmt->execute();
        $card = $stmt->get_result()->fetch_assoc();
        
        if (!$mentioner || !$card) return;
        
        $title = 'You were mentioned';
        $message = "{$mentioner['name']} mentioned you in a comment on \"{$card['title']}\"";
        $data = json_encode([
            'type' => 'mention',
            'card_id' => $cardId,
            'board_id' => $boardId,
            'comment_id' => $commentId,
            'mentioner_id' => $mentionerUserId
        ]);
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, data) 
            VALUES (?, 'mention', ?, ?, ?)
        ");
        $stmt->bind_param('isss', $mentionedUserId, $title, $message, $data);
        $stmt->execute();
        
    } catch (Exception $e) {
        error_log('Failed to create mention notification: ' . $e->getMessage());
    }
}
