<?php
/**
 * Notification Helper Class
 * Handles all notification creation and management
 */

class NotificationHelper {
    
    /**
     * Notification Types
     */
    const TYPE_MENTION = 'mention';
    const TYPE_ASSIGNMENT = 'assignment';
    const TYPE_TASK_UPDATE = 'task_update';
    const TYPE_COMMENT = 'comment';
    const TYPE_DUE_DATE = 'due_date';
    const TYPE_CHECKLIST = 'checklist';
    const TYPE_ATTACHMENT = 'attachment';
    const TYPE_TASK_COMPLETED = 'task_completed';
    const TYPE_TASK_MOVED = 'task_moved';
    
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create a notification
     */
    public function create($userId, $type, $title, $message, $data = []) {
        try {
            $dataJson = !empty($data) ? json_encode($data) : null;
            
            $stmt = $this->conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, data) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('issss', $userId, $type, $title, $message, $dataJson);
            $stmt->execute();
            
            return $stmt->insert_id;
        } catch (Exception $e) {
            error_log('Failed to create notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification for mentioned user
     */
    public function createMentionNotification($mentionedUserId, $mentionerUserId, $cardId, $boardId, $commentId = null) {
        if ($mentionedUserId === $mentionerUserId) return false;
        
        try {
            $mentioner = $this->getUser($mentionerUserId);
            $card = $this->getCard($cardId);
            
            if (!$mentioner || !$card) return false;
            
            $title = 'You were mentioned';
            $message = "{$mentioner['name']} mentioned you in a comment on \"{$card['title']}\"";
            
            return $this->create($mentionedUserId, self::TYPE_MENTION, $title, $message, [
                'card_id' => $cardId,
                'board_id' => $boardId,
                'comment_id' => $commentId,
                'actor_id' => $mentionerUserId,
                'actor_name' => $mentioner['name']
            ]);
        } catch (Exception $e) {
            error_log('Failed to create mention notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification for task assignment
     */
    public function createAssignmentNotification($assignedUserId, $assignerUserId, $cardId, $boardId) {
        if ($assignedUserId === $assignerUserId) return false;
        
        try {
            $assigner = $this->getUser($assignerUserId);
            $card = $this->getCard($cardId);
            $board = $this->getBoard($boardId);
            
            if (!$assigner || !$card || !$board) return false;
            
            $title = 'New task assigned';
            $message = "{$assigner['name']} assigned you to \"{$card['title']}\" in {$board['name']}";
            
            return $this->create($assignedUserId, self::TYPE_ASSIGNMENT, $title, $message, [
                'card_id' => $cardId,
                'board_id' => $boardId,
                'actor_id' => $assignerUserId,
                'actor_name' => $assigner['name']
            ]);
        } catch (Exception $e) {
            error_log('Failed to create assignment notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify all assignees of a task about an update
     */
    public function notifyTaskAssignees($cardId, $actorUserId, $action, $details = '') {
        try {
            $assignees = $this->getCardAssignees($cardId);
            $actor = $this->getUser($actorUserId);
            $card = $this->getCard($cardId);
            
            if (!$actor || !$card || empty($assignees)) return false;
            
            $boardId = $card['board_id'];
            
            foreach ($assignees as $assignee) {
                // Don't notify the actor
                if ($assignee['user_id'] == $actorUserId) continue;
                
                $title = $this->getActionTitle($action);
                $message = $this->buildUpdateMessage($actor['name'], $card['title'], $action, $details);
                
                $this->create($assignee['user_id'], self::TYPE_TASK_UPDATE, $title, $message, [
                    'card_id' => $cardId,
                    'board_id' => $boardId,
                    'action' => $action,
                    'actor_id' => $actorUserId,
                    'actor_name' => $actor['name'],
                    'details' => $details
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Failed to notify task assignees: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification for new comment on assigned task
     * @param array $excludeUserIds - User IDs to exclude (e.g., already notified via mention)
     */
    public function notifyCommentOnAssignedTask($cardId, $commenterUserId, $commentPreview = '', $excludeUserIds = []) {
        try {
            $assignees = $this->getCardAssignees($cardId);
            $commenter = $this->getUser($commenterUserId);
            $card = $this->getCard($cardId);
            
            if (!$commenter || !$card || empty($assignees)) return false;
            
            $boardId = $card['board_id'];
            
            // Ensure excludeUserIds is an array
            if (!is_array($excludeUserIds)) {
                $excludeUserIds = [];
            }
            
            foreach ($assignees as $assignee) {
                // Don't notify the commenter OR already-mentioned users
                if ($assignee['user_id'] == $commenterUserId || in_array($assignee['user_id'], $excludeUserIds)) continue;
                
                $title = 'New comment on your task';
                $preview = strlen($commentPreview) > 50 ? substr($commentPreview, 0, 50) . '...' : $commentPreview;
                $message = "{$commenter['name']} commented on \"{$card['title']}\": {$preview}";
                
                $this->create($assignee['user_id'], self::TYPE_COMMENT, $title, $message, [
                    'card_id' => $cardId,
                    'board_id' => $boardId,
                    'actor_id' => $commenterUserId,
                    'actor_name' => $commenter['name']
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Failed to notify comment on assigned task: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification for due date change
     */
    public function notifyDueDateChange($cardId, $actorUserId, $newDueDate) {
        return $this->notifyTaskAssignees($cardId, $actorUserId, 'due_date_changed', $newDueDate);
    }
    
    /**
     * Create notification for task completion
     */
    public function notifyTaskCompleted($cardId, $actorUserId, $isCompleted) {
        $action = $isCompleted ? 'task_completed' : 'task_reopened';
        return $this->notifyTaskAssignees($cardId, $actorUserId, $action);
    }
    
    /**
     * Create notification for task moved to different list
     */
    public function notifyTaskMoved($cardId, $actorUserId, $fromList, $toList) {
        return $this->notifyTaskAssignees($cardId, $actorUserId, 'task_moved', "from \"{$fromList}\" to \"{$toList}\"");
    }
    
    /**
     * Create notification for checklist item update
     */
    public function notifyChecklistUpdate($cardId, $actorUserId, $itemTitle, $isCompleted) {
        $action = $isCompleted ? 'checklist_completed' : 'checklist_uncompleted';
        return $this->notifyTaskAssignees($cardId, $actorUserId, $action, $itemTitle);
    }
    
    /**
     * Create notification for attachment added
     */
    public function notifyAttachmentAdded($cardId, $actorUserId, $filename) {
        return $this->notifyTaskAssignees($cardId, $actorUserId, 'attachment_added', $filename);
    }
    
    /**
     * Get action title based on action type
     */
    private function getActionTitle($action) {
        $titles = [
            'task_updated' => 'Task updated',
            'description_updated' => 'Description updated',
            'due_date_changed' => 'Due date changed',
            'task_completed' => 'Task completed',
            'task_reopened' => 'Task reopened',
            'task_moved' => 'Task moved',
            'checklist_completed' => 'Checklist item completed',
            'checklist_uncompleted' => 'Checklist item uncompleted',
            'attachment_added' => 'Attachment added',
            'priority_changed' => 'Priority changed',
            'title_changed' => 'Task renamed'
        ];
        
        return $titles[$action] ?? 'Task activity';
    }
    
    /**
     * Build update message
     */
    private function buildUpdateMessage($actorName, $cardTitle, $action, $details = '') {
        $messages = [
            'task_updated' => "{$actorName} updated \"{$cardTitle}\"",
            'description_updated' => "{$actorName} updated the description of \"{$cardTitle}\"",
            'due_date_changed' => "{$actorName} changed the due date of \"{$cardTitle}\"" . ($details ? " to {$details}" : ''),
            'task_completed' => "{$actorName} marked \"{$cardTitle}\" as complete",
            'task_reopened' => "{$actorName} reopened \"{$cardTitle}\"",
            'task_moved' => "{$actorName} moved \"{$cardTitle}\" {$details}",
            'checklist_completed' => "{$actorName} completed \"{$details}\" in \"{$cardTitle}\"",
            'checklist_uncompleted' => "{$actorName} unchecked \"{$details}\" in \"{$cardTitle}\"",
            'attachment_added' => "{$actorName} added an attachment to \"{$cardTitle}\"",
            'priority_changed' => "{$actorName} changed the priority of \"{$cardTitle}\"" . ($details ? " to {$details}" : ''),
            'title_changed' => "{$actorName} renamed a task to \"{$cardTitle}\""
        ];
        
        return $messages[$action] ?? "{$actorName} made changes to \"{$cardTitle}\"";
    }
    
    /**
     * Get user by ID
     */
    private function getUser($userId) {
        $stmt = $this->conn->prepare("SELECT id, name, email, avatar FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get card by ID with board info
     */
    private function getCard($cardId) {
        $stmt = $this->conn->prepare("
            SELECT c.id, c.title, c.description, l.board_id 
            FROM cards c 
            JOIN lists l ON c.list_id = l.id 
            WHERE c.id = ?
        ");
        $stmt->bind_param('i', $cardId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get board by ID
     */
    private function getBoard($boardId) {
        $stmt = $this->conn->prepare("SELECT id, name FROM boards WHERE id = ?");
        $stmt->bind_param('i', $boardId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get all assignees of a card
     */
    private function getCardAssignees($cardId) {
        $stmt = $this->conn->prepare("SELECT user_id FROM card_assignees WHERE card_id = ?");
        $stmt->bind_param('i', $cardId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get unread notification count for a user
     */
    public static function getUnreadCount($conn, $userId) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] ?? 0;
    }
    
    /**
     * Get notifications for a user
     */
    public static function getNotifications($conn, $userId, $limit = 20, $offset = 0, $unreadOnly = false) {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iii', $userId, $limit, $offset);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Parse JSON data
        foreach ($notifications as &$notification) {
            if (!empty($notification['data'])) {
                $notification['data'] = json_decode($notification['data'], true);
            }
        }
        
        return $notifications;
    }
    
    /**
     * Mark notification as read
     */
    public static function markAsRead($conn, $notificationId, $userId) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $notificationId, $userId);
        return $stmt->execute();
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public static function markAllAsRead($conn, $userId) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $userId);
        return $stmt->execute();
    }
    
    /**
     * Delete a notification
     */
    public static function delete($conn, $notificationId, $userId) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $notificationId, $userId);
        return $stmt->execute();
    }
    
    /**
     * Delete old notifications (cleanup)
     */
    public static function deleteOld($conn, $days = 30) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param('i', $days);
        return $stmt->execute();
    }
}

