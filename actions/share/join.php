<?php
/**
 * Join a board using a share link
 * POST /actions/share/join.php
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../helpers/IdEncrypt.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'You must be logged in to join a board'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

$userId = $_SESSION['user_id'];

// Rate limit join attempts (10 attempts per 5 minutes per user)
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkActionRateLimit($conn, 'join_board', $userId . '_' . $ipAddress, 10, 300)) {
    jsonResponse(['success' => false, 'message' => 'Too many join attempts. Please try again later.'], 429);
}

// Parse JSON input if content type is JSON
$input = [];
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

$token = trim($input['token'] ?? '');

if (empty($token)) {
    jsonResponse(['success' => false, 'message' => 'Token is required'], 400);
}

// Hash the token to look it up
$tokenHash = hash('sha256', $token);

try {
    $conn->begin_transaction();
    
    // Get share link details with lock for update
    $stmt = $conn->prepare("
        SELECT sl.*, b.name as board_name, u.name as owner_name
        FROM share_links sl
        INNER JOIN boards b ON sl.board_id = b.id
        INNER JOIN users u ON sl.owner_id = u.id
        WHERE sl.token_hash = ?
        FOR UPDATE
    ");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $shareLink = $stmt->get_result()->fetch_assoc();
    
    if (!$shareLink) {
        $conn->rollback();
        jsonResponse(['success' => false, 'message' => 'Invalid share link'], 404);
    }
    
    $linkState = validateShareLinkState($shareLink);
    if (!$linkState['ok']) {
        $conn->rollback();
        jsonResponse([
            'success' => false,
            'message' => $linkState['message'],
            'error_code' => $linkState['error_code'] ?? 'INVALID',
        ], 410);
    }
    
    // Check if user is already a member
    $stmt = $conn->prepare("
        SELECT role FROM board_members 
        WHERE board_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $shareLink['board_id'], $userId);
    $stmt->execute();
    $existingMembership = $stmt->get_result()->fetch_assoc();
    
    if ($existingMembership) {
        $conn->rollback();
        jsonResponse([
            'success' => true, 
            'message' => 'You are already a member of this board',
            'already_member' => true,
            'board_ref' => encryptId($shareLink['board_id']),
            'role' => $existingMembership['role']
        ]);
    }
    
    if ($shareLink['restrict_domain']) {
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !userEmailMatchesShareDomain($user['email'], $shareLink['restrict_domain'])) {
            $conn->rollback();
            jsonResponse([
                'success' => false,
                'message' => 'This link is restricted to ' . $shareLink['restrict_domain'] . ' email addresses',
            ], 403);
        }
    }
    
    // Handle based on access type
    if ($shareLink['access_type'] === 'invite_only') {
        // For invite-only, create a join request
        $stmt = $conn->prepare("
            INSERT INTO join_requests (share_link_id, board_id, user_id, status)
            VALUES (?, ?, ?, 'pending')
            ON DUPLICATE KEY UPDATE status = 'pending', created_at = NOW(), handled_by = NULL, handled_at = NULL
        ");
        $stmt->bind_param("iii", $shareLink['id'], $shareLink['board_id'], $userId);
        if (!$stmt->execute()) {
            throw new Exception('Could not save the access request');
        }
        
        // Log the usage
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $conn->prepare("
            INSERT INTO share_link_uses (share_link_id, user_id, ip_address, user_agent, action)
            VALUES (?, ?, ?, ?, 'requested')
        ");
        $stmt->bind_param("iiss", $shareLink['id'], $userId, $ipAddress, $userAgent);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE share_links SET uses = uses + 1 WHERE id = ?");
        $stmt->bind_param("i", $shareLink['id']);
        $stmt->execute();
        
        // Create notification for owner
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $requester = $stmt->get_result()->fetch_assoc();
        
        $requestedRole = in_array($shareLink['role_on_join'], ['admin', 'member'], true) ? $shareLink['role_on_join'] : 'member';
        $notificationData = json_encode([
            'board_id' => (int) $shareLink['board_id'],
            'user_id' => (int) $userId,
            'share_link_id' => (int) $shareLink['id'],
            'role' => $requestedRole,
            'open' => 'requests'
        ]);
        
        $stmt = $conn->prepare("
            SELECT user_id FROM board_members
            WHERE board_id = ? AND role IN ('owner', 'admin') AND user_id <> ?
        ");
        $stmt->bind_param("ii", $shareLink['board_id'], $userId);
        $stmt->execute();
        $reviewers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$reviewers) {
            $reviewers = [['user_id' => $shareLink['owner_id']]];
        }
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, data)
            VALUES (?, 'join_request', 'Access Request', ?, ?)
        ");
        $message = $requester['name'] . ' requested access to "' . $shareLink['board_name'] . '" as ' . roleLabel($requestedRole);
        foreach ($reviewers as $reviewer) {
            $reviewerId = (int) $reviewer['user_id'];
            $stmt->bind_param("iss", $reviewerId, $message, $notificationData);
            $stmt->execute();
        }
        
        $conn->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Your access request was sent to the Super Admin and Admins',
            'request_sent' => true,
            'board_name' => $shareLink['board_name']
        ]);
    } else {
        // For view_only and join_on_click, add user as member directly
        $role = in_array($shareLink['role_on_join'], ['admin', 'member'], true) ? $shareLink['role_on_join'] : 'member';
        
        $stmt = $conn->prepare("
            INSERT INTO board_members (board_id, user_id, role)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $shareLink['board_id'], $userId, $role);
        $stmt->execute();
        
        // Also add to workspace members if not already
        $stmt = $conn->prepare("
            SELECT workspace_id FROM boards WHERE id = ?
        ");
        $stmt->bind_param("i", $shareLink['board_id']);
        $stmt->execute();
        $board = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("
            INSERT IGNORE INTO workspace_members (workspace_id, user_id, role)
            VALUES (?, ?, 'member')
        ");
        $stmt->bind_param("ii", $board['workspace_id'], $userId);
        $stmt->execute();
        
        // Increment usage count
        $stmt = $conn->prepare("
            UPDATE share_links SET uses = uses + 1 WHERE id = ?
        ");
        $stmt->bind_param("i", $shareLink['id']);
        $stmt->execute();
        
        // Log the usage
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $conn->prepare("
            INSERT INTO share_link_uses (share_link_id, user_id, ip_address, user_agent, action)
            VALUES (?, ?, ?, ?, 'joined')
        ");
        $stmt->bind_param("iiss", $shareLink['id'], $userId, $ipAddress, $userAgent);
        $stmt->execute();
        
        // Log activity
        logActivity($conn, $shareLink['board_id'], $userId, 'joined_board', 'Joined the board via share link');
        
        // Create notification for owner
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $joiner = $stmt->get_result()->fetch_assoc();
        
        $notificationData = json_encode([
            'board_id' => $shareLink['board_id'],
            'user_id' => $userId,
            'share_link_id' => $shareLink['id']
        ]);
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, data)
            VALUES (?, 'member_joined', 'New Member Joined', ?, ?)
        ");
        $message = $joiner['name'] . ' has joined your board "' . $shareLink['board_name'] . '"';
        $stmt->bind_param("iss", $shareLink['owner_id'], $message, $notificationData);
        $stmt->execute();
        
        $conn->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'You have successfully joined the board',
            'joined' => true,
            'board_id' => $shareLink['board_id'],
            'board_name' => $shareLink['board_name'],
            'role' => $role
        ]);
    }
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>

