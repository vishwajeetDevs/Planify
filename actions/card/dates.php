<?php
/**
 * Card Dates API
 */

// Suppress all errors from being output
error_reporting(0);
ini_set('display_errors', 0);

// Clean all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start fresh buffer
ob_start();

// Include files first (they handle session)
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/NotificationHelper.php';
require_once '../../src/MailHelper.php';

// Discard any output from includes
ob_end_clean();
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Helper function to return JSON and exit
function jsonExitDates($data) {
    while (ob_get_level()) ob_end_clean();
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    jsonExitDates(['success' => false, 'message' => 'Unauthorized']);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    jsonExitDates(['success' => false, 'message' => 'Invalid JSON input']);
}

// Validate CSRF token
$csrfToken = $data['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    jsonExitDates(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
}

$cardId = filter_var($data['card_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$cardId) {
    jsonExitDates(['success' => false, 'message' => 'Card ID required']);
}

global $conn;

if (!$conn) {
    jsonExitDates(['success' => false, 'message' => 'Database connection failed']);
}

try {
    $stmt = $conn->prepare("SELECT c.*, l.board_id FROM cards c JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
    if (!$stmt) {
        jsonExitDates(['success' => false, 'message' => 'Database error']);
    }
    
    $stmt->bind_param('i', $cardId);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    
    if (!$card) {
        jsonExitDates(['success' => false, 'message' => 'Card not found']);
    }
    
    if (!canEditBoard($conn, $_SESSION['user_id'], $card['board_id'])) {
        jsonExitDates(['success' => false, 'message' => 'You do not have permission to modify this card']);
    }
    
    // Get dates from input or keep existing
    $startDate = isset($data['start_date']) ? ($data['start_date'] ?: null) : $card['start_date'];
    $dueDate = isset($data['due_date']) ? ($data['due_date'] ?: null) : $card['due_date'];
    $dueTime = isset($data['due_time']) ? ($data['due_time'] ?: null) : $card['due_time'];
    
    // Validate date order
    if ($startDate && $dueDate && strtotime($startDate) > strtotime($dueDate)) {
        jsonExitDates(['success' => false, 'message' => 'Start date cannot be after due date']);
    }
    
    $stmt = $conn->prepare("UPDATE cards SET start_date = ?, due_date = ?, due_time = ? WHERE id = ?");
    if (!$stmt) {
        jsonExitDates(['success' => false, 'message' => 'Database error']);
    }
    
    $stmt->bind_param('sssi', $startDate, $dueDate, $dueTime, $cardId);
    
    if ($stmt->execute()) {
        // Calculate status
        $status = null;
        if ($dueDate) {
            $now = new DateTime();
            $dueDateTime = $dueDate . ($dueTime ? ' ' . $dueTime : ' 23:59:59');
            $due = new DateTime($dueDateTime);
            
            if (!empty($card['is_completed'])) {
                $status = 'completed';
            } else if ($due < $now) {
                $status = 'overdue';
            } else if ($due->diff($now)->days <= 1) {
                $status = 'due_soon';
            } else {
                $status = 'on_track';
            }
        }
        
        // Check if dates changed for email notification
        $datesChanged = ($startDate !== $card['start_date']) || ($dueDate !== $card['due_date']);
        $shouldSendEmail = $datesChanged;
        $emailCardId = $cardId;
        $emailUserId = $_SESSION['user_id'];
        $emailStartDate = $startDate;
        $emailDueDate = $dueDate;
        $emailDueTime = $dueTime;
        
        // Send response immediately (before email)
        while (ob_get_level()) ob_end_clean();
        
        // Set headers for immediate response
        header('Content-Type: application/json; charset=utf-8');
        header('Connection: close');
        
        $response = json_encode([
            'success' => true,
            'message' => 'Dates updated',
            'dates' => [
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'due_time' => $dueTime,
                'status' => $status
            ]
        ]);
        
        header('Content-Length: ' . strlen($response));
        echo $response;
        
        // Flush all output to send response immediately
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
        }
        
        // Now send notifications in background (after response sent)
        if ($shouldSendEmail) {
            $dateDetails = [];
            if ($emailStartDate) {
                $dateDetails['start_date'] = date('M j, Y', strtotime($emailStartDate));
            }
            if ($emailDueDate) {
                $dateDetails['due_date'] = date('M j, Y', strtotime($emailDueDate)) . ($emailDueTime ? ' at ' . date('g:i A', strtotime($emailDueTime)) : '');
            }
            
            try {
                // Send in-app notification to assigned users
                $notificationHelper = new NotificationHelper($conn);
                $formattedDate = $emailDueDate ? date('M j, Y', strtotime($emailDueDate)) : '';
                $notificationHelper->notifyDueDateChange($emailCardId, $emailUserId, $formattedDate);
                
                // Send email notification
                MailHelper::sendTaskUpdateNotifications(
                    $conn,
                    $emailCardId,
                    'dates_changed',
                    $emailUserId,
                    $dateDetails
                );
            } catch (Exception $e) {
                error_log('Notification error: ' . $e->getMessage());
            }
        }
        
        exit;
    } else {
        jsonExitDates(['success' => false, 'message' => 'Failed to update dates']);
    }

} catch (Exception $e) {
    error_log('Error in card/dates.php: ' . $e->getMessage());
    jsonExitDates(['success' => false, 'message' => 'Server error']);
}
