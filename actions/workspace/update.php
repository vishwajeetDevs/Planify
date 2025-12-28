<?php
// Ensure no output before headers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Ensure user is logged in
requireLogin();

// Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);
$workspaceId = $input['id'] ?? null;
$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');

// Validate input
if (empty($workspaceId) || empty($name)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Workspace name is required']);
    exit;
}

try {
    // Check if the workspace exists and the current user is the owner
    $stmt = $conn->prepare("
        SELECT id, owner_id 
        FROM workspaces 
        WHERE id = ? AND owner_id = ?
    ");
    $stmt->bind_param("ii", $workspaceId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Workspace not found or access denied']);
        exit;
    }
    
    // Update the workspace
    $stmt = $conn->prepare("
        UPDATE workspaces 
        SET name = ?, description = ?, updated_at = NOW() 
        WHERE id = ? AND owner_id = ?
    ");
    $stmt->bind_param("ssii", $name, $description, $workspaceId, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Workspace updated successfully',
            'workspace' => [
                'id' => $workspaceId,
                'name' => $name,
                'description' => $description
            ]
        ]);
    } else {
        throw new Exception('Failed to update workspace');
    }
    
} catch (Exception $e) {
    error_log('Error updating workspace: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while updating the workspace: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
