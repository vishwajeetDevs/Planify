<?php
/**
 * Create Label API
 */
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

require_once '../../config/db.php';
require_once '../../includes/functions.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$boardId = filter_var($data['board_id'] ?? 0, FILTER_VALIDATE_INT);
$name = trim($data['name'] ?? '');
$color = trim($data['color'] ?? '#6366f1');

if (!$boardId || !$color) {
    echo json_encode(['success' => false, 'message' => 'Board ID and color required']);
    exit;
}

global $conn;

try {
    if (!hasAccessToBoard($conn, $_SESSION['user_id'], $boardId)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO labels (board_id, name, color) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $boardId, $name, $color);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Label created',
            'label' => [
                'id' => $conn->insert_id,
                'board_id' => $boardId,
                'name' => $name,
                'color' => $color,
                'selected' => false
            ]
        ]);
    } else {
        throw new Exception('Failed to create label');
    }

} catch (Exception $e) {
    error_log('Error in label/create.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create label']);
}
