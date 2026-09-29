<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../helpers/IdEncrypt.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$query = $_GET['q'] ?? '';
$userId = $_SESSION['user_id'];
$workspaceId = isset($_GET['workspace_id']) && $_GET['workspace_id'] !== '' ? intval($_GET['workspace_id']) : null;
$boardId = isset($_GET['board_id']) && $_GET['board_id'] !== '' ? intval($_GET['board_id']) : null;

if (strlen($query) < 2) {
    jsonResponse(['success' => false, 'message' => 'Query too short'], 400);
}

try {
    $searchTerm = '%' . $query . '%';
    $results = [];

    // Boards (skip when already scoped to a single board — task search only)
    if (!$boardId) {
        $boardSql = "
            SELECT b.id AS board_id, b.name AS board_name,
                   w.id AS workspace_id, w.name AS workspace_name
            FROM boards b
            INNER JOIN workspaces w ON b.workspace_id = w.id
            LEFT JOIN board_members bm ON b.id = bm.board_id
            WHERE (b.created_by = ? OR bm.user_id = ?)
              AND b.name LIKE ?
        ";
        $boardParams = [$userId, $userId, $searchTerm];
        $boardTypes = 'iis';

        if ($workspaceId) {
            $boardSql .= ' AND w.id = ?';
            $boardParams[] = $workspaceId;
            $boardTypes .= 'i';
        }

        $boardSql .= ' GROUP BY b.id ORDER BY b.updated_at DESC LIMIT 8';

        $boardStmt = $conn->prepare($boardSql);
        $boardStmt->bind_param($boardTypes, ...$boardParams);
        $boardStmt->execute();
        $boards = $boardStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $boardStmt->close();

        foreach ($boards as $board) {
            $results[] = [
                'type' => 'board',
                'board_id' => (int) $board['board_id'],
                'board_name' => $board['board_name'],
                'board_ref' => encryptId((int) $board['board_id']),
                'workspace_id' => (int) $board['workspace_id'],
                'workspace_name' => $board['workspace_name'],
            ];
        }
    }

    // Tasks / cards
    $sql = "
        SELECT c.id, c.title, c.description,
               l.id AS list_id, l.title AS list_name,
               b.id AS board_id, b.name AS board_name,
               w.id AS workspace_id, w.name AS workspace_name,
               c.due_date, c.priority,
               (SELECT GROUP_CONCAT(u.name SEPARATOR ', ')
                FROM card_assignees ca
                JOIN users u ON ca.user_id = u.id
                WHERE ca.card_id = c.id) AS assignees
        FROM cards c
        INNER JOIN lists l ON c.list_id = l.id
        INNER JOIN boards b ON l.board_id = b.id
        INNER JOIN workspaces w ON b.workspace_id = w.id
        LEFT JOIN board_members bm ON b.id = bm.board_id
        WHERE (b.created_by = ? OR bm.user_id = ?)
          AND (c.title LIKE ? OR c.description LIKE ?)
    ";

    $params = [$userId, $userId, $searchTerm, $searchTerm];
    $types = 'iiss';

    if ($workspaceId) {
        $sql .= ' AND w.id = ?';
        $params[] = $workspaceId;
        $types .= 'i';
    }

    if ($boardId) {
        $sql .= ' AND b.id = ?';
        $params[] = $boardId;
        $types .= 'i';
    }

    $sql .= ' GROUP BY c.id ORDER BY c.updated_at DESC LIMIT 15';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $cards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($cards as $result) {
        $result['type'] = 'task';
        $result['board_ref'] = encryptId((int) $result['board_id']);
        $result['list_id'] = (int) $result['list_id'];
        $result['open_ref'] = encryptOpenToken(
            (int) $result['board_id'],
            (int) $result['id'],
            null
        );

        if ($result['due_date']) {
            $dueDate = new DateTime($result['due_date']);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $dueDate->setTime(0, 0, 0);

            $diff = $today->diff($dueDate);
            $daysUntilDue = (int) $diff->format('%r%a');

            $result['due_date_formatted'] = $dueDate->format('M j, Y');
            $result['is_overdue'] = $daysUntilDue < 0;

            if ($daysUntilDue < 0) {
                $result['priority'] = 'overdue';
                $result['priority_label'] = 'Overdue';
            } elseif ($daysUntilDue <= 2) {
                $result['priority'] = 'high';
                $result['priority_label'] = 'High';
            } elseif ($daysUntilDue <= 7) {
                $result['priority'] = 'medium';
                $result['priority_label'] = 'Medium';
            } else {
                $result['priority'] = 'low';
                $result['priority_label'] = 'Low';
            }
        } else {
            $result['priority'] = null;
            $result['priority_label'] = null;
        }

        $results[] = $result;
    }

    jsonResponse([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'filters' => [
            'workspace_id' => $workspaceId,
            'board_id' => $boardId,
        ],
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Search error: ' . $e->getMessage()], 500);
}
