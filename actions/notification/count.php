<?php
/**
 * Get Unread Notification Count API
 * Lightweight endpoint for polling
 */
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/NotificationHelper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $unreadCount = NotificationHelper::getUnreadCount($conn, $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount
    ]);
    
} catch (Exception $e) {
    error_log('Error getting notification count: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

