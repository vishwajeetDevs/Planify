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

// Support both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? $_POST['name'] ?? '');
$description = trim($input['description'] ?? $_POST['description'] ?? '');
$userId = $_SESSION['user_id'];

if (empty($name)) {
    jsonResponse(['success' => false, 'message' => 'Workspace name is required'], 400);
}

try {
    // Insert workspace
    $stmt = $conn->prepare("INSERT INTO workspaces (name, description, owner_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $description, $userId);
    
    if ($stmt->execute()) {
        $workspaceId = $conn->insert_id;
        
        // Add owner as member
        $stmt = $conn->prepare("INSERT INTO workspace_members (workspace_id, user_id, role) VALUES (?, ?, 'owner')");
        $stmt->bind_param("ii", $workspaceId, $userId);
        $stmt->execute();
        
        jsonResponse([
            'success' => true,
            'message' => 'Workspace created successfully',
            'workspace_id' => $workspaceId,
            'workspace' => [
                'id' => $workspaceId,
                'name' => $name,
                'description' => $description
            ]
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create workspace'], 500);
    }
} catch (Exception $e) {
    secureErrorResponse($e, 'Workspace creation');
}
?>