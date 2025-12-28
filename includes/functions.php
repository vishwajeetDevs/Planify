<?php
// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // For AJAX requests, return JSON response
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['redirect' => $basePath . '/public/login.php']);
        } else {
            // For regular requests, redirect to login
            header('Location: ' . $basePath . '/public/login.php');
        }
        exit;
    }
}

// Get current user data
function getCurrentUser($conn, $userId = null) {
    if (!isLoggedIn()) {
        return null;
    }
    
    // Use provided userId or fall back to session
    $userId = $userId ?? $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, name, email, avatar, theme, theme_color FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Check if user has access to board
// User has access if they are a board member OR the board creator
function hasAccessToBoard($conn, $userId, $boardId) {
    // First check board_members table
    $stmt = $conn->prepare("
        SELECT bm.role 
        FROM board_members bm 
        WHERE bm.board_id = ? AND bm.user_id = ?
    ");
    $stmt->bind_param("ii", $boardId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $membership = $result->fetch_assoc();
    
    if ($membership) {
        return $membership;
    }
    
    // If not a member, check if user is the board creator
    $stmt = $conn->prepare("SELECT created_by FROM boards WHERE id = ?");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $board = $stmt->get_result()->fetch_assoc();
    
    if ($board && $board['created_by'] == $userId) {
        // Creator has owner-level access
        return ['role' => 'owner'];
    }
    
    return null;
}

// Get all boards the user has access to
// User has access if they are a board member OR the board creator
function getUserAccessibleBoards($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT DISTINCT b.*, w.name as workspace_name, w.id as workspace_id,
               COALESCE(bm.role, 'owner') as user_role
        FROM boards b
        INNER JOIN workspaces w ON b.workspace_id = w.id
        LEFT JOIN board_members bm ON b.id = bm.board_id AND bm.user_id = ?
        WHERE bm.user_id IS NOT NULL OR b.created_by = ?
        ORDER BY b.updated_at DESC
    ");
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get all workspaces the user has access to
// User has access to a workspace if they have access to at least one board in it
// OR if they are the workspace owner
function getUserAccessibleWorkspaces($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT DISTINCT w.*, 
               (SELECT COUNT(DISTINCT b2.id) 
                FROM boards b2 
                LEFT JOIN board_members bm2 ON b2.id = bm2.board_id AND bm2.user_id = ?
                WHERE b2.workspace_id = w.id 
                AND (bm2.user_id IS NOT NULL OR b2.created_by = ?)) as visible_board_count,
               (w.owner_id = ?) as is_owner
        FROM workspaces w
        LEFT JOIN boards b ON w.id = b.workspace_id
        LEFT JOIN board_members bm ON b.id = bm.board_id AND bm.user_id = ?
        WHERE w.owner_id = ? 
           OR bm.user_id IS NOT NULL 
           OR b.created_by = ?
        GROUP BY w.id
        HAVING visible_board_count > 0 OR is_owner = 1
        ORDER BY w.created_at ASC
    ");
    $stmt->bind_param("iiiiii", $userId, $userId, $userId, $userId, $userId, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get boards in a workspace that the user has access to
function getUserAccessibleBoardsInWorkspace($conn, $userId, $workspaceId) {
    $stmt = $conn->prepare("
        SELECT DISTINCT b.*, u.name as created_by_name,
               (SELECT COUNT(*) FROM lists WHERE board_id = b.id) as list_count,
               COALESCE(bm.role, 'owner') as user_role
        FROM boards b
        INNER JOIN users u ON b.created_by = u.id
        LEFT JOIN board_members bm ON b.id = bm.board_id AND bm.user_id = ?
        WHERE b.workspace_id = ?
        AND (bm.user_id IS NOT NULL OR b.created_by = ?)
        ORDER BY b.created_at DESC
    ");
    $stmt->bind_param("iii", $userId, $workspaceId, $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Check if user has access to workspace
// User has access if they own the workspace OR have access to at least one board in it
function hasAccessToWorkspace($conn, $userId, $workspaceId) {
    // Check if user is workspace owner
    $stmt = $conn->prepare("SELECT owner_id FROM workspaces WHERE id = ?");
    $stmt->bind_param("i", $workspaceId);
    $stmt->execute();
    $workspace = $stmt->get_result()->fetch_assoc();
    
    if (!$workspace) {
        return false;
    }
    
    if ($workspace['owner_id'] == $userId) {
        return true;
    }
    
    // Check if user has access to any board in this workspace
    $stmt = $conn->prepare("
        SELECT COUNT(*) as board_count
        FROM boards b
        LEFT JOIN board_members bm ON b.id = bm.board_id AND bm.user_id = ?
        WHERE b.workspace_id = ?
        AND (bm.user_id IS NOT NULL OR b.created_by = ?)
    ");
    $stmt->bind_param("iii", $userId, $workspaceId, $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['board_count'] > 0;
}

// Check if user can edit board
function canEditBoard($conn, $userId, $boardId) {
    $access = hasAccessToBoard($conn, $userId, $boardId);
    // Owner, admin, and member can edit the board content
    return $access && in_array($access['role'], ['owner', 'admin', 'member']);
}

// Check if user can manage board members (add/remove/update roles)
function canManageBoard($conn, $userId, $boardId) {
    $access = hasAccessToBoard($conn, $userId, $boardId);
    // Only owner and admin can manage board settings and members
    return $access && in_array($access['role'], ['owner', 'admin']);
}

// Check if user is board owner
function isBoardOwner($conn, $userId, $boardId) {
    $access = hasAccessToBoard($conn, $userId, $boardId);
    return $access && $access['role'] === 'owner';
}

// Check if user is a workspace owner (for owner-only features like export)
function isAdmin($conn, $userId) {
    // Only workspace owners can access admin features
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM workspaces WHERE owner_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'] > 0;
}

// Get all workspaces owned by the user (for export feature)
function getAdminWorkspaces($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT * FROM workspaces WHERE owner_id = ? ORDER BY name ASC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format date for display
function formatDate($date) {
    if (!$date) return '';
    return date('M d, Y', strtotime($date));
}

// Format datetime for display
function formatDateTime($datetime) {
    if (!$datetime) return '';
    return date('M d, Y h:i A', strtotime($datetime));
}

// Time ago function
function timeAgo($datetime) {
    if (empty($datetime)) {
        return '';
    }
    
    $timestamp = strtotime($datetime);
    
    // Handle invalid timestamps
    if ($timestamp === false || $timestamp <= 0) {
        return '';
    }
    
    $now = time();
    $difference = $now - $timestamp;
    
    // Handle future dates (clock sync issues)
    if ($difference < 0) {
        return '0 min ago';
    }
    
    if ($difference < 60) {
        return '0 min ago';
    } elseif ($difference < 3600) {
        $mins = floor($difference / 60);
        return $mins . ' min ago';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' hr ago';
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}

// Generate random color for labels
function generateRandomColor() {
    $colors = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899', '#6366F1'];
    return $colors[array_rand($colors)];
}

// Log activity
function logActivity($conn, $boardId, $userId, $action, $description, $cardId = null) {
    // Ensure action is not empty
    if (empty($action)) {
        error_log("logActivity called with empty action - boardId: $boardId, userId: $userId");
        return false;
    }
    
    // Handle NULL cardId properly - use separate query if cardId is null
    if ($cardId === null) {
        $stmt = $conn->prepare("
            INSERT INTO activities (board_id, user_id, card_id, action, description) 
            VALUES (?, ?, NULL, ?, ?)
        ");
        
        if (!$stmt) {
            error_log("logActivity prepare failed: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("iiss", $boardId, $userId, $action, $description);
    } else {
    $stmt = $conn->prepare("
        INSERT INTO activities (board_id, user_id, card_id, action, description) 
        VALUES (?, ?, ?, ?, ?)
    ");
        
        if (!$stmt) {
            error_log("logActivity prepare failed: " . $conn->error);
            return false;
        }
        
    $stmt->bind_param("iiiss", $boardId, $userId, $cardId, $action, $description);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("logActivity execute failed: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

// Upload file
function uploadFile($file, $cardId) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    if ($file['size'] > 5242880) { // 5MB
        return ['success' => false, 'message' => 'File size too large (max 5MB)'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'card_' . $cardId . '_' . uniqid() . '.' . $extension;
    $filepath = UPLOAD_PATH . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'original_name' => $file['name'],
            'file_path' => $filepath,
            'file_size' => $file['size'],
            'mime_type' => $file['type']
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

// Send JSON response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Get priority badge color
function getPriorityColor($priority) {
    switch ($priority) {
        case 'high':
            return 'bg-red-100 text-red-800';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800';
        case 'low':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Escape output
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Validate and sanitize integer input
function validateInt($value, $min = null, $max = null) {
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === false) {
        return false;
    }
    if ($min !== null && $value < $min) {
        return false;
    }
    if ($max !== null && $value > $max) {
        return false;
    }
    return $value;
}

// Validate required fields
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

// Get JSON input from request body
function getJsonInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

// Merge JSON and POST data
function getRequestData() {
    $jsonInput = getJsonInput();
    return array_merge($_POST, $jsonInput);
}

// Secure error response - hides sensitive details in production
function secureErrorResponse($e, $context = 'Operation') {
    // Log the full error for debugging
    error_log("Error in {$context}: " . $e->getMessage() . " | File: " . $e->getFile() . ":" . $e->getLine());
    
    // Return generic message in production, detailed in development
    if (class_exists('Env') && Env::isDevelopment()) {
        jsonResponse(['success' => false, 'message' => $context . ' error: ' . $e->getMessage()], 500);
    } else {
        jsonResponse(['success' => false, 'message' => 'A server error occurred. Please try again later.'], 500);
    }
}

// Validate CSRF token
function validateCSRFToken() {
    // Check for token in multiple places: POST data, header, or JSON body
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    // If not found in POST or header, check JSON body
    if (empty($token)) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['_token'] ?? '';
        }
    }
    
    if (empty($_SESSION['csrf_token'])) {
        // Generate token if not exists (for backward compatibility)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonResponse(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.'], 403);
    }
}

// Generate CSRF token if not exists
function ensureCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Check rate limit for an action (generic rate limiter)
function checkActionRateLimit($conn, $action, $identifier, $maxAttempts = 5, $windowSeconds = 300) {
    // Create rate_limits table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(50) NOT NULL,
            identifier VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action_identifier (action, identifier),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB
    ");
    
    // Clean old entries
    $conn->query("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    
    // Check current count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempts 
        FROM rate_limits 
        WHERE action = ? AND identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->bind_param("ssi", $action, $identifier, $windowSeconds);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['attempts'] < $maxAttempts;
}

// Log rate limit attempt (generic rate limiter)
function logActionRateLimitAttempt($conn, $action, $identifier) {
    $stmt = $conn->prepare("INSERT INTO rate_limits (action, identifier) VALUES (?, ?)");
    $stmt->bind_param("ss", $action, $identifier);
    $stmt->execute();
    $stmt->close();
}

// Sanitize value for CSV export to prevent CSV injection
function sanitizeCSVValue($value) {
    if (empty($value)) return $value;
    
    $value = (string) $value;
    $firstChar = substr($value, 0, 1);
    $dangerousChars = ['=', '+', '-', '@', "\t", "\r", "\n"];
    
    if (in_array($firstChar, $dangerousChars)) {
        return "'" . $value; // Prefix with single quote to prevent formula execution
    }
    return $value;
}

// Validate file upload with extension whitelist
function validateFileUpload($file, $allowedExtensions = null, $maxSize = 10485760) {
    if ($allowedExtensions === null) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'rar', '7z'];
    }
    
    // Check file error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'File upload failed'];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'message' => 'File size exceeds maximum limit'];
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return ['valid' => false, 'message' => 'File type not allowed'];
    }
    
    // Verify actual file content matches extension
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $mimeToExt = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls', 'csv'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'text/plain' => ['txt', 'csv'],
        'text/csv' => ['csv'],
        'application/csv' => ['csv'],
        'application/zip' => ['zip'],
        'application/x-zip-compressed' => ['zip'],
        'application/x-rar-compressed' => ['rar'],
        'application/x-7z-compressed' => ['7z'],
        'application/octet-stream' => ['doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', '7z', 'csv'] // Fallback for some file types
    ];
    
    $valid = false;
    if (isset($mimeToExt[$detectedMime])) {
        if (in_array($ext, $mimeToExt[$detectedMime])) {
            $valid = true;
        }
    }
    
    // Allow octet-stream for some document types as fallback
    if (!$valid && $detectedMime === 'application/octet-stream') {
        $documentExts = ['doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', '7z', 'csv'];
        if (in_array($ext, $documentExts)) {
            $valid = true;
        }
    }
    
    // CSV files can have various MIME types depending on the system
    if (!$valid && $ext === 'csv') {
        $csvMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'];
        if (in_array($detectedMime, $csvMimes)) {
            $valid = true;
        }
    }
    
    if (!$valid) {
        return ['valid' => false, 'message' => 'File content does not match the file extension'];
    }
    
    return ['valid' => true, 'extension' => $ext, 'mime_type' => $detectedMime];
}
?>