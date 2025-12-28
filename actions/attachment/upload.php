<?php
/**
 * Upload Attachment API
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

$cardId = filter_var($_POST['card_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$cardId) {
    echo json_encode(['success' => false, 'message' => 'Card ID required']);
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
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['file'];
    $maxSize = 10 * 1024 * 1024;
    
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 10MB)']);
        exit;
    }
    
    // Validate file upload with extension whitelist and content verification
    $validation = validateFileUpload($file, null, $maxSize);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'message' => $validation['message']]);
        exit;
    }
    
    $uploadDir = '../../uploads/attachments/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    $ext = $validation['extension']; // Use validated extension
    $filename = uniqid('attach_') . '_' . time() . '.' . $ext;
    $filePath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $mimeType = mime_content_type($filePath);
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        $relativePath = $basePath . '/uploads/attachments/' . $filename;
        
        $stmt = $conn->prepare("INSERT INTO attachments (card_id, user_id, filename, original_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iisssis', $cardId, $_SESSION['user_id'], $filename, $file['name'], $relativePath, $file['size'], $mimeType);
        
        if ($stmt->execute()) {
            // Send email notification to assignees
            MailHelper::sendTaskUpdateNotifications(
                $conn,
                $cardId,
                'attachment_added',
                $_SESSION['user_id'],
                ['filename' => $file['name']]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded',
                'attachment' => [
                    'id' => $conn->insert_id,
                    'filename' => $filename,
                    'original_name' => $file['name'],
                    'file_path' => $relativePath,
                    'file_size' => $file['size'],
                    'mime_type' => $mimeType,
                    'is_image' => strpos($mimeType, 'image/') === 0
                ]
            ]);
        } else {
            unlink($filePath);
            throw new Exception('Failed to save attachment');
        }
    } else {
        throw new Exception('Failed to move uploaded file');
    }

} catch (Exception $e) {
    error_log('Error in attachment/upload.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
}
