<?php
/**
 * Card Viewers API
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

global $conn;
$userId = $_SESSION['user_id'];

/** MySQL UTC datetime from UTC_TIMESTAMP() → ISO-8601 Z and epoch ms */
function planifyUtcMysqlToIso(?string $mysqlDatetime): ?string {
    if (!$mysqlDatetime) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, new DateTimeZone('UTC'));
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d\TH:i:s\Z');
}

function planifyUtcMysqlToMs(?string $mysqlDatetime): ?int {
    if (!$mysqlDatetime) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, new DateTimeZone('UTC'));
    if (!$dt) {
        return null;
    }
    return $dt->getTimestamp() * 1000;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cardId = filter_input(INPUT_GET, 'card_id', FILTER_VALIDATE_INT);
    
    if (!$cardId) {
        echo json_encode(['success' => false, 'message' => 'Card ID is required']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("SELECT c.id, l.board_id FROM cards c INNER JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
        $stmt->bind_param("i", $cardId);
        $stmt->execute();
        $card = $stmt->get_result()->fetch_assoc();
        
        if (!$card || !hasAccessToBoard($conn, $userId, $card['board_id'])) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        // Get current server time in UTC for comparison
        $serverNow = time();
        
        $stmt = $conn->prepare("SELECT cv.user_id, cv.viewed_at, cv.last_viewed_at, cv.view_count, u.name, u.email, u.avatar FROM card_viewers cv INNER JOIN users u ON cv.user_id = u.id WHERE cv.card_id = ? ORDER BY cv.last_viewed_at DESC");
        $stmt->bind_param("i", $cardId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $viewers = [];
        while ($row = $result->fetch_assoc()) {
            // Timestamps are stored with UTC_TIMESTAMP(), so they're already UTC
            // Format as ISO 8601 with Z suffix
            $viewedAt = planifyUtcMysqlToIso($row['viewed_at']);
            $lastViewedAt = planifyUtcMysqlToIso($row['last_viewed_at']);
            $lastViewedMs = planifyUtcMysqlToMs($row['last_viewed_at']);
            
            $viewers[] = [
                'user_id' => (int)$row['user_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'avatar' => $row['avatar'],
                'viewed_at' => $viewedAt,
                'last_viewed_at' => $lastViewedAt,
                'last_viewed_at_ms' => $lastViewedMs,
                'view_count' => (int)$row['view_count']
            ];
        }
        
        // UTC clock for client-side relative times (matches UTC_TIMESTAMP on views)
        $dbTimeResult = $conn->query("SELECT UTC_TIMESTAMP() AS server_time_utc");
        $serverTimeUtc = gmdate('Y-m-d H:i:s');
        if ($dbTimeResult && $dbRow = $dbTimeResult->fetch_assoc()) {
            $serverTimeUtc = $dbRow['server_time_utc'];
        }
        $serverTimeMs = planifyUtcMysqlToMs($serverTimeUtc);
        
        echo json_encode([
            'success' => true,
            'viewers' => $viewers,
            'total_count' => count($viewers),
            'server_time' => $serverTimeUtc,
            'server_time_ms' => $serverTimeMs,
        ]);
        exit;
    } catch (Exception $e) {
        error_log('Error in card/viewers.php GET: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $cardId = filter_var($data['card_id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$cardId) {
        echo json_encode(['success' => false, 'message' => 'Card ID is required']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("SELECT c.id, l.board_id FROM cards c INNER JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
        $stmt->bind_param("i", $cardId);
        $stmt->execute();
        $card = $stmt->get_result()->fetch_assoc();
        
        if (!$card || !hasAccessToBoard($conn, $userId, $card['board_id'])) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        // Use UTC_TIMESTAMP() to store times consistently across different server timezones
        $stmt = $conn->prepare("INSERT INTO card_viewers (card_id, user_id, viewed_at, view_count, last_viewed_at) VALUES (?, ?, UTC_TIMESTAMP(), 1, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE view_count = view_count + 1, last_viewed_at = UTC_TIMESTAMP()");
        $stmt->bind_param("ii", $cardId, $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'View tracked successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to track view']);
        }
        exit;
    } catch (Exception $e) {
        error_log('Error in card/viewers.php POST: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
