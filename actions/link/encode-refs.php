<?php
/**
 * Return encrypted URL refs for board / task / list (for client-side deep links).
 */
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../helpers/IdEncrypt.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$boardId = filter_input(INPUT_GET, 'board_id', FILTER_VALIDATE_INT);
$cardId = filter_input(INPUT_GET, 'card_id', FILTER_VALIDATE_INT);
$listId = filter_input(INPUT_GET, 'list_id', FILTER_VALIDATE_INT);

if (!$boardId && !$cardId && !$listId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    if ($cardId) {
        $stmt = $conn->prepare('
            SELECT c.id, c.list_id, l.board_id
            FROM cards c
            INNER JOIN lists l ON c.list_id = l.id
            WHERE c.id = ?
        ');
        $stmt->bind_param('i', $cardId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !hasAccessToBoard($conn, $userId, (int) $row['board_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        $boardId = (int) $row['board_id'];
        $listId = (int) $row['list_id'];
    } elseif ($listId) {
        $stmt = $conn->prepare('SELECT board_id FROM lists WHERE id = ?');
        $stmt->bind_param('i', $listId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !hasAccessToBoard($conn, $userId, (int) $row['board_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        $boardId = (int) $row['board_id'];
    } elseif ($boardId && !hasAccessToBoard($conn, $userId, $boardId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $payload = ['success' => true];
    if ($boardId) {
        $payload['ref'] = encryptId($boardId);
    }
    if ($cardId && $boardId) {
        $payload['o'] = encryptOpenToken($boardId, $cardId, null);
    } elseif ($listId && $boardId) {
        $payload['l'] = encryptId($listId);
    }

    echo json_encode($payload);
} catch (Exception $e) {
    error_log('encode-refs error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
