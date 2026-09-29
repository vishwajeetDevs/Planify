<?php
/**
 * Live sync stream.
 *
 * Pushes database change events to the signed-in user as Server-Sent Events.
 * The connection is short (about 25s) and the browser reconnects on its own,
 * so one PHP worker is not held open permanently.
 */

require_once '../../config/db.php';
require_once '../../includes/functions.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
session_write_close();

@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
@set_time_limit(40);
ignore_user_abort(false);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

$since = null;
if (isset($_SERVER['HTTP_LAST_EVENT_ID']) && $_SERVER['HTTP_LAST_EVENT_ID'] !== '') {
    $since = (int) $_SERVER['HTTP_LAST_EVENT_ID'];
} elseif (isset($_GET['since']) && $_GET['since'] !== '') {
    $since = (int) $_GET['since'];
}

if ($since === null) {
    $max = $conn->query('SELECT IFNULL(MAX(id), 0) AS max_id FROM realtime_events');
    $since = $max ? (int) $max->fetch_assoc()['max_id'] : 0;
}

echo "retry: 1000\n";
echo "id: {$since}\n";
echo "event: ready\n";
echo 'data: ' . json_encode(['cursor' => $since]) . "\n\n";
flush();

$stmt = $conn->prepare("
    SELECT id, board_id, workspace_id, card_id, target_user_id, actor_id,
           entity_type, entity_id, action, summary
    FROM realtime_events
    WHERE id > ?
      AND (
            target_user_id = ?
         OR board_id IN (SELECT board_id FROM board_members WHERE user_id = ?)
         OR (
                entity_type IN ('workspace', 'workspace_member', 'board')
            AND workspace_id IN (SELECT workspace_id FROM workspace_members WHERE user_id = ?)
         )
      )
    ORDER BY id ASC
    LIMIT 100
");

if (!$stmt) {
    echo "event: error\n";
    echo 'data: ' . json_encode(['message' => 'Live sync is not installed']) . "\n\n";
    flush();
    exit;
}

$deadline = time() + 25;
while (time() < $deadline) {
    if (connection_aborted()) {
        break;
    }

    $stmt->bind_param('iiii', $since, $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $since = (int) $row['id'];
        $payload = [
            'id' => $since,
            'board_id' => $row['board_id'] !== null ? (int) $row['board_id'] : null,
            'workspace_id' => $row['workspace_id'] !== null ? (int) $row['workspace_id'] : null,
            'card_id' => $row['card_id'] !== null ? (int) $row['card_id'] : null,
            'target_user_id' => $row['target_user_id'] !== null ? (int) $row['target_user_id'] : null,
            'actor_id' => $row['actor_id'] !== null ? (int) $row['actor_id'] : null,
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            'action' => $row['action'],
            'summary' => $row['summary'],
        ];
        echo "id: {$since}\n";
        echo 'data: ' . json_encode($payload) . "\n\n";
    }
    $result->free();

    echo ": ping\n\n";
    flush();
    usleep(500000);
}

$stmt->close();
$conn->query('DELETE FROM realtime_events WHERE created_at < (NOW() - INTERVAL 2 DAY)');
