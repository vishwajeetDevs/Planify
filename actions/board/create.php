<?php
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

$workspaceId = intval($_POST['workspace_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$backgroundColor = $_POST['background_color'] ?? '#4F46E5';
$userId = $_SESSION['user_id'];

if (empty($name)) {
    jsonResponse(['success' => false, 'message' => 'Board name is required'], 400);
}

if ($workspaceId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid workspace'], 400);
}

try {
    // Insert board
    $stmt = $conn->prepare("
        INSERT INTO boards (workspace_id, name, description, background_color, created_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssi", $workspaceId, $name, $description, $backgroundColor, $userId);
    
    if ($stmt->execute()) {
        $boardId = $conn->insert_id;
        
        // Add creator as owner
        $stmt = $conn->prepare("INSERT INTO board_members (board_id, user_id, role) VALUES (?, ?, 'owner')");
        $stmt->bind_param("ii", $boardId, $userId);
        $stmt->execute();
        
        // Create default lists
        $defaultLists = ['To Do', 'In Progress', 'Done'];
        $position = 0;
        foreach ($defaultLists as $listName) {
            $stmt = $conn->prepare("INSERT INTO lists (board_id, title, position) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $boardId, $listName, $position);
            $stmt->execute();
            $position++;
        }
        
        // Get encrypted board ref for URL
        require_once '../../helpers/IdEncrypt.php';
        $boardRef = encryptId($boardId);
        
        // Count the lists we just created
        $listCount = count($defaultLists);
        
        jsonResponse([
            'success' => true,
            'message' => 'Board created successfully',
            'board_id' => $boardId,
            'board' => [
                'id' => $boardId,
                'ref' => $boardRef,
                'name' => $name,
                'description' => $description,
                'background_color' => $backgroundColor,
                'list_count' => $listCount
            ]
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create board'], 500);
    }
} catch (Exception $e) {
    secureErrorResponse($e, 'Board creation');
}
?>