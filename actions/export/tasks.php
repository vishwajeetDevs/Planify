<?php
/**
 * Export Tasks API - Board Level (Owner Only)
 * Exports tasks from a specific board within a date range in CSV format
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Require login
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get request parameters
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Helper function to check if user can export from this board
 * Allow board owner, admin, and member to export
 */
function canExportBoard($conn, $userId, $boardId) {
    // Check if user created the board
    $stmt = $conn->prepare("SELECT created_by FROM boards WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result && $result['created_by'] == $userId) {
        return true;
    }
    
    // Check if user has owner, admin, or member role in board_members
    $stmt = $conn->prepare("SELECT role FROM board_members WHERE board_id = ? AND user_id = ? AND role IN ('owner', 'admin', 'member')");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $boardId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $canExport = $result->num_rows > 0;
    $stmt->close();
    
    return $canExport;
}

if ($method === 'GET') {
    // Return export preview/info for a specific board
    header('Content-Type: application/json');
    
    $boardId = filter_input(INPUT_GET, 'board_id', FILTER_VALIDATE_INT);
    
    if (!$boardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Board ID required']);
        exit;
    }
    
    // Check if user can export (owner, admin, or member)
    if (!canExportBoard($conn, $userId, $boardId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Board members only.']);
        exit;
    }
    
    try {
        // Count lists in this board
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lists WHERE board_id = ?");
        $stmt->bind_param("i", $boardId);
        $stmt->execute();
        $listCount = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        // Count tasks in this board
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM cards c
            INNER JOIN lists l ON c.list_id = l.id
            WHERE l.board_id = ?
        ");
        $stmt->bind_param("i", $boardId);
        $stmt->execute();
        $taskCount = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'lists' => $listCount,
                'tasks' => $taskCount
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

if ($method === 'POST') {
    // Process export request
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate CSRF token
    $csrfToken = $input['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
        exit;
    }
    
    $boardId = $input['board_id'] ?? null;
    $fromDate = $input['from_date'] ?? null;
    $toDate = $input['to_date'] ?? null;
    $format = $input['format'] ?? 'csv';
    
    // Validate board ID
    if (!$boardId) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Board ID required']);
        exit;
    }
    
    // Check if user is board owner
    if (!isBoardOwner($conn, $userId, $boardId)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Board owner only.']);
        exit;
    }
    
    // Validate dates
    if (!$fromDate || !$toDate) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Date range required']);
        exit;
    }
    
    // Validate date format
    $fromDateTime = DateTime::createFromFormat('Y-m-d', $fromDate);
    $toDateTime = DateTime::createFromFormat('Y-m-d', $toDate);
    
    if (!$fromDateTime || !$toDateTime) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
        exit;
    }
    
    // Ensure from date is before to date
    if ($fromDateTime > $toDateTime) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'From date must be before To date']);
        exit;
    }
    
    try {
        // Get board details
        $stmt = $conn->prepare("
            SELECT b.*, w.name as workspace_name, u.name as created_by_name
            FROM boards b
            INNER JOIN workspaces w ON b.workspace_id = w.id
            INNER JOIN users u ON b.created_by = u.id
            WHERE b.id = ?
        ");
        if (!$stmt) {
            throw new Exception("Failed to prepare board query: " . $conn->error);
        }
        $stmt->bind_param("i", $boardId);
        $stmt->execute();
        $board = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$board) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Board not found']);
            exit;
        }
        
        // Get lists for this board
        $listsStmt = $conn->prepare("SELECT * FROM lists WHERE board_id = ? ORDER BY position ASC");
        if (!$listsStmt) {
            throw new Exception("Failed to prepare lists query: " . $conn->error);
        }
        $listsStmt->bind_param("i", $boardId);
        $listsStmt->execute();
        $lists = $listsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $listsStmt->close();
        
        // Build export data
        $exportData = [];
        
        // Get tasks within date range
        $tasksStmt = $conn->prepare("
            SELECT c.*, l.title as list_name, u.name as created_by_name,
                   (SELECT COUNT(*) FROM comments WHERE card_id = c.id) as comment_count
            FROM cards c
            INNER JOIN lists l ON c.list_id = l.id
            INNER JOIN users u ON c.created_by = u.id
            WHERE l.board_id = ?
            AND DATE(c.created_at) >= ? AND DATE(c.created_at) <= ?
            ORDER BY l.position ASC, c.position ASC
        ");
        if (!$tasksStmt) {
            throw new Exception("Failed to prepare tasks query: " . $conn->error);
        }
        $tasksStmt->bind_param("iss", $boardId, $fromDate, $toDate);
        $tasksStmt->execute();
        $tasks = $tasksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $tasksStmt->close();
        
        foreach ($tasks as $task) {
            $assigneeNames = [];
            $labelNames = [];
            $attachmentNames = [];
            
            // Get assigned members
            $stmt2 = $conn->prepare("
                SELECT u.name 
                FROM card_assignees ca
                INNER JOIN users u ON ca.user_id = u.id
                WHERE ca.card_id = ?
            ");
            if ($stmt2) {
                $stmt2->bind_param("i", $task['id']);
                $stmt2->execute();
                $assignees = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                $assigneeNames = array_column($assignees, 'name');
                $stmt2->close();
            }
            
            // Get labels
            $stmt2 = $conn->prepare("
                SELECT lb.name, lb.color
                FROM card_labels cl
                INNER JOIN labels lb ON cl.label_id = lb.id
                WHERE cl.card_id = ?
            ");
            if ($stmt2) {
                $stmt2->bind_param("i", $task['id']);
                $stmt2->execute();
                $labels = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                $labelNames = array_column($labels, 'name');
                $stmt2->close();
            }
            
            $exportData[] = [
                'list_name' => $task['list_name'],
                'title' => $task['title'],
                'description' => strip_tags($task['description'] ?? ''),
                'assigned_members' => implode(', ', $assigneeNames),
                'labels' => implode(', ', $labelNames),
                'start_date' => $task['start_date'] ?? '',
                'due_date' => $task['due_date'] ?? '',
                'created_date' => $task['created_at'],
                'created_by' => $task['created_by_name']
            ];
        }
        
        // Generate filename
        $safeBoardName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $board['name']);
        $filename = "{$safeBoardName}_export_{$fromDate}_to_{$toDate}";
        
        if ($format === 'csv') {
            // Generate CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Clear any buffered output
            while (ob_get_level()) ob_end_clean();
            
            $output = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write clean header section
            fputcsv($output, [$board['workspace_name']]);
            fputcsv($output, ['Board: ' . $board['name'] . ' | Date Range: ' . $fromDate . ' to ' . $toDate . ' | Exported: ' . date('d M Y, h:i A')]);
            fputcsv($output, []); // Empty row
            
            // Write header row - cleaner structure with merged concepts
            fputcsv($output, [
                'Status',
                'Task',
                'Description',
                'Assigned To',
                'Labels',
                'Timeline',
                'Created'
            ]);
            
            // Write data rows
            if (empty($exportData)) {
                fputcsv($output, ['No tasks found in the selected date range', '', '', '', '', '', '']);
            } else {
                foreach ($exportData as $task) {
                    // Format timeline (Start Date - Due Date)
                    $timeline = '';
                    if (!empty($task['start_date']) && !empty($task['due_date'])) {
                        $timeline = date('d M Y', strtotime($task['start_date'])) . ' → ' . date('d M Y', strtotime($task['due_date']));
                    } elseif (!empty($task['due_date'])) {
                        $timeline = 'Due: ' . date('d M Y', strtotime($task['due_date']));
                    } elseif (!empty($task['start_date'])) {
                        $timeline = 'Start: ' . date('d M Y', strtotime($task['start_date']));
                    }
                    
                    // Format created info (Date by User)
                    $created = '';
                    if (!empty($task['created_date'])) {
                        $created = date('d M Y', strtotime($task['created_date']));
                        if (!empty($task['created_by'])) {
                            $created .= ' by ' . $task['created_by'];
                        }
                    }
                    
                    // Sanitize values to prevent CSV injection attacks
                    fputcsv($output, [
                        sanitizeCSVValue($task['list_name']),
                        sanitizeCSVValue($task['title']),
                        sanitizeCSVValue($task['description']),
                        sanitizeCSVValue($task['assigned_members']),
                        sanitizeCSVValue($task['labels']),
                        sanitizeCSVValue($timeline),
                        sanitizeCSVValue($created)
                    ]);
                }
            }
            
            fclose($output);
            exit;
        } else {
            // Return JSON data for other formats
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'board' => [
                        'name' => $board['name'],
                        'workspace' => $board['workspace_name']
                    ],
                    'tasks' => $exportData
                ],
                'meta' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'exported_at' => date('Y-m-d H:i:s'),
                    'exported_by' => $userId
                ]
            ]);
        }
        
    } catch (Exception $e) {
        // Clear any output that might have been generated
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
        exit;
    } catch (Error $e) {
        // Clear any output that might have been generated
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Export error: ' . $e->getMessage()]);
        exit;
    }
    exit;
}

// Invalid method
header('Content-Type: application/json');
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
