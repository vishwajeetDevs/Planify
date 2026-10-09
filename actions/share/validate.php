<?php
/**
 * Validate a share token and get board info
 * GET /actions/share/validate.php?token=X
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    jsonResponse(['success' => false, 'message' => 'Token is required'], 400);
}

// Hash the token to look it up
$tokenHash = hash('sha256', $token);

try {
    // Get share link details
    $stmt = $conn->prepare("
        SELECT sl.*, b.name as board_name, b.description as board_description, 
               u.name as owner_name, u.avatar as owner_avatar
        FROM share_links sl
        INNER JOIN boards b ON sl.board_id = b.id
        INNER JOIN users u ON sl.owner_id = u.id
        WHERE sl.token_hash = ?
    ");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $shareLink = $stmt->get_result()->fetch_assoc();
    
    if (!$shareLink) {
        jsonResponse([
            'success' => false, 
            'message' => 'This link is not valid',
            'error_code' => 'INVALID_TOKEN'
        ], 404);
    }
    
    $linkState = validateShareLinkState($shareLink);
    if (!$linkState['ok']) {
        jsonResponse([
            'success' => false,
            'message' => $linkState['message'],
            'error_code' => $linkState['error_code'],
            'board_name' => $shareLink['board_name'],
            'owner_name' => $shareLink['owner_name'],
        ], 410);
    }
    
    // Check if user is logged in
    $isLoggedIn = isLoggedIn();
    $userId = $isLoggedIn ? $_SESSION['user_id'] : null;
    $alreadyMember = false;
    $existingRole = null;
    
    if ($isLoggedIn) {
        // Check if user is already a member
        $stmt = $conn->prepare("
            SELECT role FROM board_members 
            WHERE board_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $shareLink['board_id'], $userId);
        $stmt->execute();
        $membership = $stmt->get_result()->fetch_assoc();
        
        if ($membership) {
            $alreadyMember = true;
            $existingRole = $membership['role'];
        }
        
        if ($shareLink['restrict_domain']) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user || !userEmailMatchesShareDomain($user['email'], $shareLink['restrict_domain'])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'This link is restricted to ' . $shareLink['restrict_domain'] . ' email addresses',
                    'error_code' => 'DOMAIN_RESTRICTED',
                    'board_name' => $shareLink['board_name'],
                    'owner_name' => $shareLink['owner_name'],
                ], 403);
            }
        }
    }
    
    // Return share link info
    jsonResponse([
        'success' => true,
        'is_logged_in' => $isLoggedIn,
        'already_member' => $alreadyMember,
        'existing_role' => $existingRole,
        'share_link' => [
            'id' => $shareLink['id'],
            'board_id' => $shareLink['board_id'],
            'board_name' => $shareLink['board_name'],
            'board_description' => $shareLink['board_description'],
            'owner_name' => $shareLink['owner_name'],
            'owner_avatar' => $shareLink['owner_avatar'],
            'access_type' => $shareLink['access_type'],
            'role_on_join' => $shareLink['role_on_join'],
            'restrict_domain' => $shareLink['restrict_domain']
        ]
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>

