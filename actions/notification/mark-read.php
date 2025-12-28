<?php
/**
 * Mark Notification(s) as Read API
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

try {
    if (isset($data['mark_all']) && $data['mark_all'] === true) {
        // Mark all notifications as read
        NotificationHelper::markAllAsRead($conn, $userId);
        $unreadCount = 0;
    } elseif (isset($data['notification_id'])) {
        // Mark single notification as read
        $notificationId = (int)$data['notification_id'];
        NotificationHelper::markAsRead($conn, $notificationId, $userId);
        $unreadCount = NotificationHelper::getUnreadCount($conn, $userId);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount
    ]);
    
} catch (Exception $e) {
    error_log('Error marking notification as read: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

