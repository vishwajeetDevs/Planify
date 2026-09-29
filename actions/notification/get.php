<?php
/**
 * Get Notifications API
 * Returns notifications for the current user
 */
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/NotificationHelper.php';
require_once '../../helpers/IdEncrypt.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

try {
    // Get notifications
    $notifications = NotificationHelper::getNotifications($conn, $userId, $limit, $offset, $unreadOnly);
    
    // Get unread count
    $unreadCount = NotificationHelper::getUnreadCount($conn, $userId);
    
    // Format timestamps
    foreach ($notifications as &$notification) {
        $notification['time_ago'] = timeAgo($notification['created_at']);
        $notification['formatted_date'] = date('M j, Y g:i A', strtotime($notification['created_at']));

        $payload = $notification['data'];
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (is_array($payload) && !empty($payload['board_id'])) {
            $payload['board_ref'] = encryptId((int) $payload['board_id']);
            if (!empty($payload['card_id'])) {
                $payload['open_ref'] = encryptOpenToken(
                    (int) $payload['board_id'],
                    (int) $payload['card_id'],
                    null
                );
            } elseif (!empty($payload['list_id'])) {
                $payload['list_ref'] = encryptId((int) $payload['list_id']);
            }
            $notification['data'] = $payload;
        }
    }
    unset($notification);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'has_more' => count($notifications) === $limit
    ]);
    
} catch (Exception $e) {
    error_log('Error getting notifications: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
