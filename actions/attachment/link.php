<?php
/**
 * Add Link Attachment API
 */
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../src/MailHelper.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate CSRF token
validateCSRFToken();

$data = json_decode(file_get_contents('php://input'), true);
$cardId = filter_var($data['card_id'] ?? 0, FILTER_VALIDATE_INT);
$url = trim($data['url'] ?? '');
$name = trim($data['name'] ?? '');

if (!$cardId || !$url) {
    echo json_encode(['success' => false, 'message' => 'Card ID and URL required']);
    exit;
}

// Auto-add https:// if no protocol is specified
if (!empty($url) && !preg_match('/^https?:\/\//i', $url)) {
    $url = 'https://' . $url;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid URL format. Please enter a valid URL.']);
    exit;
}

global $conn;

try {
    $stmt = $conn->prepare("SELECT l.board_id FROM cards c JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
    $stmt->bind_param('i', $cardId);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    
    if (!$card || !hasAccessToBoard($conn, $_SESSION['user_id'], $card['board_id'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    if (empty($name)) $name = parse_url($url, PHP_URL_HOST) ?: $url;
    
    $filename = 'link_' . uniqid();
    $stmt = $conn->prepare("INSERT INTO attachments (card_id, user_id, filename, original_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, 0, 'link')");
    $stmt->bind_param('iisss', $cardId, $_SESSION['user_id'], $filename, $name, $url);
    
    if ($stmt->execute()) {
        $insertId = $conn->insert_id;
        $emailCardId = $cardId;
        $emailUserId = $_SESSION['user_id'];
        $emailName = $name;
        $emailUrl = $url;
        
        // Send response immediately (before email)
        $response = json_encode([
            'success' => true,
            'message' => 'Link added',
            'attachment' => [
                'id' => $insertId,
                'filename' => $filename,
                'original_name' => $name,
                'file_path' => $url,
                'file_size' => 0,
                'mime_type' => 'link',
                'is_link' => true
            ]
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
        
        // Send email notification in background
        try {
            MailHelper::sendTaskUpdateNotifications(
                $conn,
                $emailCardId,
                'link_added',
                $emailUserId,
                ['link_name' => $emailName, 'url' => $emailUrl]
            );
        } catch (Exception $e) {
            error_log('Email notification error: ' . $e->getMessage());
        }
        
        exit;
    } else {
        throw new Exception('Failed to add link');
    }

} catch (Exception $e) {
    error_log('Error in attachment/link.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to add link']);
}
