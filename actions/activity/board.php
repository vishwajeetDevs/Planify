<?php
/**
 * Recent activity for a board (used by the live Board Activity panel).
 * GET /actions/activity/board.php?board_id=
 */
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) {
    ob_end_clean();
}

require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$boardId = filter_input(INPUT_GET, 'board_id', FILTER_VALIDATE_INT);
$beforeId = filter_input(INPUT_GET, 'before_id', FILTER_VALIDATE_INT);
$afterId = filter_input(INPUT_GET, 'after_id', FILTER_VALIDATE_INT);
$pageSize = 25;

if (!$boardId) {
    echo json_encode(['success' => false, 'message' => 'Invalid board']);
    exit;
}

if (!hasAccessToBoard($conn, $_SESSION['user_id'], $boardId)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$fetchLimit = $pageSize + 1;

if ($afterId) {
    $stmt = $conn->prepare("
        SELECT a.id, a.action, a.description, a.created_at, a.card_id, a.user_id,
               COALESCE(u.name, 'System') AS user_name
        FROM activities a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.board_id = ? AND a.id > ?
        ORDER BY a.id DESC
        LIMIT {$pageSize}
    ");
    $stmt->bind_param('ii', $boardId, $afterId);
} elseif ($beforeId) {
    $stmt = $conn->prepare("
        SELECT a.id, a.action, a.description, a.created_at, a.card_id, a.user_id,
               COALESCE(u.name, 'System') AS user_name
        FROM activities a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.board_id = ? AND a.id < ?
        ORDER BY a.id DESC
        LIMIT {$fetchLimit}
    ");
    $stmt->bind_param('ii', $boardId, $beforeId);
} else {
    $stmt = $conn->prepare("
        SELECT a.id, a.action, a.description, a.created_at, a.card_id, a.user_id,
               COALESCE(u.name, 'System') AS user_name
        FROM activities a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.board_id = ?
        ORDER BY a.id DESC
        LIMIT {$fetchLimit}
    ");
    $stmt->bind_param('i', $boardId);
}

$stmt->execute();
$result = $stmt->get_result();

$activities = [];
while ($row = $result->fetch_assoc()) {
    $activities[] = [
        'id' => (int) $row['id'],
        'action' => $row['action'],
        'description' => $row['description'] ?: ($row['action'] ?: 'updated'),
        'user_name' => $row['user_name'] ?: 'System',
        'card_id' => $row['card_id'] !== null ? (int) $row['card_id'] : null,
        'time_ago' => timeAgo($row['created_at']),
    ];
}
$stmt->close();

$hasMore = count($activities) > $pageSize;
if ($hasMore) {
    $activities = array_slice($activities, 0, $pageSize);
}

if ($afterId) {
    $hasMore = false;
}

echo json_encode([
    'success' => true,
    'activities' => $activities,
    'has_more' => $hasMore,
]);
