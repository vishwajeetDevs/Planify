<?php
/**
 * Create a share link for a board
 * POST /actions/share/create.php
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

// Validate CSRF token
validateCSRFToken();

$userId = $_SESSION['user_id'];

// Parse JSON input if content type is JSON
$input = [];
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

$boardId = intval($input['board_id'] ?? 0);
$accessType = $input['access_type'] ?? 'join_on_click';
$roleOnJoin = $input['role_on_join'] ?? 'viewer';
$expiresIn = $input['expires_in'] ?? null; // 'never', '1day', '7days', '30days', or custom datetime
$maxUses = isset($input['max_uses']) ? intval($input['max_uses']) : null;
$restrictDomain = trim($input['restrict_domain'] ?? '');
$singleUse = isset($input['single_use']) ? (bool)$input['single_use'] : false;
$notes = trim($input['notes'] ?? '');

// Validate board ID
if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board ID'], 400);
}

// Validate access type
$validAccessTypes = ['view_only', 'join_on_click', 'invite_only'];
if (!in_array($accessType, $validAccessTypes)) {
    jsonResponse(['success' => false, 'message' => 'Invalid access type'], 400);
}

// Validate role on join
$validRoles = ['viewer', 'commenter', 'member'];
if (!in_array($roleOnJoin, $validRoles)) {
    jsonResponse(['success' => false, 'message' => 'Invalid role'], 400);
}

// Check if user has permission to share this board (must be owner or member with share permission)
$stmt = $conn->prepare("
    SELECT bm.role, b.name as board_name, u.name as owner_name
    FROM board_members bm
    INNER JOIN boards b ON bm.board_id = b.id
    INNER JOIN users u ON b.created_by = u.id
    WHERE bm.board_id = ? AND bm.user_id = ?
");
$stmt->bind_param("ii", $boardId, $userId);
$stmt->execute();
$access = $stmt->get_result()->fetch_assoc();

if (!$access || !in_array($access['role'], ['owner', 'admin', 'member'])) {
    jsonResponse(['success' => false, 'message' => 'You do not have permission to share this board'], 403);
}

// Calculate expiration date
$expiresAt = null;
if ($expiresIn && $expiresIn !== 'never') {
    switch ($expiresIn) {
        case '1day':
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));
            break;
        case '7days':
            $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
            break;
        case '30days':
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            break;
        default:
            // Custom datetime
            $timestamp = strtotime($expiresIn);
            if ($timestamp && $timestamp > time()) {
                $expiresAt = date('Y-m-d H:i:s', $timestamp);
            }
            break;
    }
}

// Generate a cryptographically secure token (32 bytes = 256 bits)
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);

// Validate domain restriction format
if ($restrictDomain && !preg_match('/^@?[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $restrictDomain)) {
    jsonResponse(['success' => false, 'message' => 'Invalid domain format'], 400);
}

// Normalize domain (ensure it starts with @)
if ($restrictDomain && $restrictDomain[0] !== '@') {
    $restrictDomain = '@' . $restrictDomain;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO share_links (
            board_id, owner_id, token_hash, role_on_join, access_type, 
            max_uses, expires_at, restrict_domain, single_use, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $singleUseInt = $singleUse ? 1 : 0;
    $stmt->bind_param(
        "iisssissis",
        $boardId,
        $userId,
        $tokenHash,
        $roleOnJoin,
        $accessType,
        $maxUses,
        $expiresAt,
        $restrictDomain,
        $singleUseInt,
        $notes
    );
    
    if ($stmt->execute()) {
        $shareLinkId = $conn->insert_id;
        
        // Log activity
        logActivity($conn, $boardId, $userId, 'share_link_created', 'Created a share link for the board');
        
        // Build the share URL
        $baseUrl = rtrim(BASE_URL, '/');
        $shareUrl = $baseUrl . '/share.php?token=' . $token;
        
        jsonResponse([
            'success' => true,
            'message' => 'Share link created successfully',
            'share_link' => [
                'id' => $shareLinkId,
                'token' => $token, // Show token only once at creation
                'url' => $shareUrl,
                'access_type' => $accessType,
                'role_on_join' => $roleOnJoin,
                'expires_at' => $expiresAt,
                'max_uses' => $maxUses,
                'restrict_domain' => $restrictDomain,
                'single_use' => $singleUse
            ]
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create share link'], 500);
    }
} catch (Exception $e) {
    secureErrorResponse($e, 'Share link creation');
}
?>

