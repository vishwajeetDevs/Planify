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
    
    // Check if link is revoked
    if ($shareLink['is_revoked']) {
        jsonResponse([
            'success' => false, 
            'message' => 'This link has been revoked',
            'error_code' => 'REVOKED',
            'board_name' => $shareLink['board_name'],
            'owner_name' => $shareLink['owner_name']
        ], 410);
    }
    
    // Check if link is expired
    if ($shareLink['expires_at'] && strtotime($shareLink['expires_at']) < time()) {
        jsonResponse([
            'success' => false, 
            'message' => 'This link has expired',
            'error_code' => 'EXPIRED',
            'board_name' => $shareLink['board_name'],
            'owner_name' => $shareLink['owner_name']
        ], 410);
    }
    
    // Check if max uses reached
    if ($shareLink['max_uses'] && $shareLink['uses'] >= $shareLink['max_uses']) {
        jsonResponse([
            'success' => false, 
            'message' => 'This link has reached its maximum number of uses',
            'error_code' => 'MAX_USES_REACHED',
            'board_name' => $shareLink['board_name'],
            'owner_name' => $shareLink['owner_name']
        ], 410);
    }
    
    // Check if single use and already used
    if ($shareLink['single_use'] && $shareLink['uses'] > 0) {
        jsonResponse([
            'success' => false, 
            'message' => 'This link has already been used',
            'error_code' => 'ALREADY_USED',
            'board_name' => $shareLink['board_name'],
            'owner_name' => $shareLink['owner_name']
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
        
        // Check domain restriction
        if ($shareLink['restrict_domain']) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            $userDomain = '@' . substr(strrchr($user['email'], '@'), 1);
            if (strtolower($userDomain) !== strtolower($shareLink['restrict_domain'])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'This link is restricted to ' . $shareLink['restrict_domain'] . ' email addresses',
                    'error_code' => 'DOMAIN_RESTRICTED',
                    'board_name' => $shareLink['board_name'],
                    'owner_name' => $shareLink['owner_name']
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

