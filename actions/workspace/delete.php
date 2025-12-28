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

// Get the request body as JSON if it's a JSON request
$input = json_decode(file_get_contents('php://input'), true);
$workspaceId = $input['id'] ?? $_POST['workspace_id'] ?? 0;
$userId = $_SESSION['user_id'];

if (empty($workspaceId)) {
    jsonResponse(['success' => false, 'message' => 'Workspace ID is required'], 400);
}

try {
    // Check if user is the owner of the workspace
    $stmt = $conn->prepare("SELECT owner_id FROM workspaces WHERE id = ?");
    $stmt->bind_param("i", $workspaceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Use unified error message to prevent information disclosure (IDOR protection)
    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'Cannot delete workspace'], 403);
    }
    
    $workspace = $result->fetch_assoc();
    if ($workspace['owner_id'] != $userId) {
        jsonResponse(['success' => false, 'message' => 'Cannot delete workspace'], 403);
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete workspace members
        $stmt = $conn->prepare("DELETE FROM workspace_members WHERE workspace_id = ?");
        $stmt->bind_param("i", $workspaceId);
        $stmt->execute();
        
        // Delete the workspace
        $stmt = $conn->prepare("DELETE FROM workspaces WHERE id = ?");
        $stmt->bind_param("i", $workspaceId);
        $stmt->execute();
        
        $conn->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Workspace deleted successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    secureErrorResponse($e, 'Workspace delete');
}
?>
