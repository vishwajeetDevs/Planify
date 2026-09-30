<?php
/**
 * AI Chatbot API Endpoint
 * 
 * Handles chat requests for board-specific AI assistance
 * Features:
 * - Board-aware: Knows all board data (tasks, lists, members)
 * - User-aware: Knows who is asking
 * - Memory-aware: Remembers conversation history for context
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error logging but not display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/ai_error.log');

// Clear any buffered output
while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db.php';
require_once '../../config/ai.php';
require_once '../../includes/functions.php';

// Clean buffer
ob_clean();

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to use the chatbot']);
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET request - retrieve chat history
    $boardId = isset($_GET['board_id']) ? (int)$_GET['board_id'] : 0;
    $action = $_GET['action'] ?? 'history';
    
    if (!$boardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Board ID is required']);
        exit;
    }
    
    // Verify user has access to the board
    if (!hasAccessToBoard($conn, $userId, $boardId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have access to this board']);
        exit;
    }
    
    if ($action === 'history') {
        // Return chat history for this user and board
        $history = getChatHistory($conn, $userId, $boardId);
        echo json_encode(['success' => true, 'messages' => $history]);
        exit;
    } elseif ($action === 'status') {
        $status = getAIAvailabilityStatus($conn, $userId);
        echo json_encode(array_merge(['success' => true], $status));
        exit;
    } elseif ($action === 'clear') {
        // Clear chat history for this user and board
        clearChatHistory($conn, $userId, $boardId);
        echo json_encode(['success' => true, 'message' => 'Chat history cleared']);
        exit;
    }
    
    exit;
}

// Only accept POST requests for sending messages
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$boardId = isset($input['board_id']) ? (int)$input['board_id'] : 0;
$userMessage = trim($input['message'] ?? '');
$quickAction = trim($input['quick_action'] ?? '');

// Predefined quick questions are database queries, not AI requests.
if ($quickAction !== '') {
    if (!$boardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Board ID is required']);
        exit;
    }
    if (!hasAccessToBoard($conn, $userId, $boardId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have access to this board']);
        exit;
    }

    try {
        ensureChatMessagesTable($conn);
        $quickResult = executeQuickQuestion($conn, $boardId, $quickAction);
        if (!$quickResult) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown quick question']);
            exit;
        }

        saveChatMessage($conn, $userId, $boardId, 'user', $quickResult['label']);
        saveChatMessage($conn, $userId, $boardId, 'assistant', $quickResult['history_text']);

        echo json_encode([
            'success' => true,
            'direct_query' => true,
            'response' => $quickResult['summary'],
            'has_table' => true,
            'table_html' => $quickResult['table_html'],
        ]);
    } catch (Throwable $e) {
        error_log('Quick question error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not load this board information']);
    }
    exit;
}

// Handle file data (images and documents) - support multiple files
$filesData = $input['files'] ?? null; // New: array of files
$fileData = $input['file'] ?? $input['image'] ?? null; // Legacy: single file

// Convert single file to array format for unified processing
if (!$filesData && $fileData) {
    $filesData = [$fileData];
}

// Validate input - allow empty message if files are present
if (!$boardId || (empty($userMessage) && empty($filesData))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Board ID and message (or files) are required']);
    exit;
}

// Limit number of files (max 5)
if ($filesData && count($filesData) > 5) {
    $filesData = array_slice($filesData, 0, 5);
}

// If only files are provided, set default message based on file types
if (empty($userMessage) && !empty($filesData)) {
    if (count($filesData) === 1) {
        $fileType = $filesData[0]['type'] ?? 'image';
        if ($fileType === 'image') {
            $userMessage = 'What do you see in this image? Describe it in detail.';
        } else {
            $userMessage = 'Analyze this file and provide a summary of its contents.';
        }
    } else {
        $userMessage = 'Analyze these ' . count($filesData) . ' files and provide a summary of their contents.';
    }
}

// Verify user has access to the board
if (!hasAccessToBoard($conn, $userId, $boardId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have access to this board']);
    exit;
}

$availability = getAIAvailabilityStatus($conn, $userId);
if (!$availability['ai_available']) {
    $statusCode = $availability['reason_code'] === 'rate_limit' ? 429 : 503;
    http_response_code($statusCode);
    if ($availability['retry_after'] > 0) {
        header('Retry-After: ' . $availability['retry_after']);
    }
    echo json_encode(array_merge([
        'success' => false,
        'message' => $availability['message'],
    ], $availability));
    exit;
}

try {
    // Ensure chat messages table exists
    ensureChatMessagesTable($conn);
    
    // Fetch board data for AI context
    $boardData = fetchBoardDataForAI($conn, $boardId);
    
    if (!$boardData) {
        throw new Exception('Failed to fetch board data');
    }

    // Load the latest conversation before choosing AI or offline mode so both
    // paths can understand follow-up questions.
    $conversationHistory = getChatHistory($conn, $userId, $boardId, 20);
    
    // Save user message to history (note if files were included)
    $fileNote = '';
    if (!empty($filesData)) {
        if (count($filesData) === 1) {
            $fileName = $filesData[0]['name'] ?? 'file';
            $fileType = $filesData[0]['type'] ?? 'file';
            $fileNote = "[" . ucfirst($fileType) . " attached: {$fileName}] ";
        } else {
            $fileNames = array_map(function($f) { return $f['name'] ?? 'file'; }, $filesData);
            $fileNote = "[" . count($filesData) . " files attached: " . implode(', ', $fileNames) . "] ";
        }
    }
    $messageToSave = $fileNote . $userMessage;
    
    // Process all files for AI (extract text content for non-image files)
    $processedFilesData = [];
    if (!empty($filesData)) {
        foreach ($filesData as $singleFile) {
            $processed = processFileForAI($singleFile);
            if ($processed) {
                $processedFilesData[] = $processed;
            }
        }
    }
    
    // Build AI prompt with context
    $prompt = buildAIPrompt($boardData, $userMessage, $conversationHistory, $userName, $processedFilesData);
    
    // Call Gemini AI API (with files if provided)
    $aiResponse = callGeminiAPI($prompt, $processedFilesData);
    
    if (!$aiResponse['success']) {
        $errorMsg = strtolower($aiResponse['error'] ?? '');
        $isRateLimit = (
            strpos($errorMsg, 'quota') !== false ||
            strpos($errorMsg, 'rate limit') !== false ||
            strpos($errorMsg, 'free tier') !== false ||
            strpos($errorMsg, 'busy') !== false
        );
        $retryAfter = max(5, (int) ($aiResponse['retry_after'] ?? ($isRateLimit ? 60 : 30)));
        $reasonCode = $isRateLimit ? 'rate_limit' : 'unavailable';
        setAIUnavailable($conn, $retryAfter, $reasonCode);

        error_log("AI Chat Error: " . ($aiResponse['error'] ?? 'AI request failed'));
        http_response_code($isRateLimit ? 429 : 503);
        header('Retry-After: ' . $retryAfter);
        echo json_encode([
            'success' => false,
            'ai_available' => false,
            'reason_code' => $reasonCode,
            'retry_after' => $retryAfter,
            'message' => $isRateLimit
                ? 'AI usage limit reached. Chat will unlock automatically when the limit refreshes.'
                : 'AI is temporarily unavailable. Chat will retry automatically.',
        ]);
        exit;
    }

    clearAIUnavailable($conn);
    
    // Log the request for rate limiting
    logAIRequest($conn, $userId, $boardId);
    
    // Parse and format the response
    $formattedResponse = formatAIResponse($aiResponse['response'], $userMessage);
    
    // Save only successful exchanges. Failed attempts should not pollute
    // conversation context or reappear after a reload.
    saveChatMessage($conn, $userId, $boardId, 'user', $messageToSave);
    saveChatMessage($conn, $userId, $boardId, 'assistant', $aiResponse['response']);
    
    echo json_encode([
        'success' => true,
        'response' => $formattedResponse['text'],
        'has_table' => $formattedResponse['has_table'],
        'table_html' => $formattedResponse['table_html'] ?? null
    ]);
    
} catch (Exception $e) {
    error_log("AI Chat Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sorry, I encountered an error. Please try again.']);
}

// ============================================================
// DIRECT DATABASE QUICK QUESTIONS
// ============================================================

/**
 * Execute one configured quick question and return presentation-ready data.
 * Adding another quick question only requires a SQL query, label and columns.
 */
function executeQuickQuestion($conn, int $boardId, string $action): ?array {
    $configs = [
        'pending_tasks' => [
            'label' => 'Pending tasks',
            'sql' => "
                SELECT c.id AS card_id, c.title AS task, l.title AS list_name,
                       c.due_date, c.priority
                FROM cards c
                INNER JOIN lists l ON l.id = c.list_id
                WHERE l.board_id = ? AND l.is_archived = 0 AND c.is_completed = 0
                ORDER BY
                    CASE c.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2
                                    WHEN 'medium' THEN 3 ELSE 4 END,
                    c.due_date IS NULL, c.due_date, c.position
            ",
            'columns' => [
                ['key' => 'task', 'label' => 'Task'],
                ['key' => 'list_name', 'label' => 'List'],
                ['key' => 'due_date', 'label' => 'Due Date', 'format' => 'date'],
                ['key' => 'priority', 'label' => 'Priority', 'format' => 'priority'],
            ],
        ],
        'board_summary' => [
            'label' => 'Board summary',
            'sql' => "
                SELECT l.id AS list_id, l.title AS list_name,
                       COUNT(c.id) AS total_tasks,
                       COALESCE(SUM(c.is_completed = 0), 0) AS pending_tasks,
                       COALESCE(SUM(c.is_completed = 1), 0) AS completed_tasks,
                       COALESCE(SUM(c.is_completed = 0 AND c.due_date IS NOT NULL
                                    AND c.due_date < CURDATE()), 0) AS overdue_tasks
                FROM lists l
                LEFT JOIN cards c ON c.list_id = l.id
                WHERE l.board_id = ? AND l.is_archived = 0
                GROUP BY l.id, l.title, l.position
                ORDER BY l.position, l.id
            ",
            'columns' => [
                ['key' => 'list_name', 'label' => 'List'],
                ['key' => 'total_tasks', 'label' => 'Total'],
                ['key' => 'pending_tasks', 'label' => 'Pending'],
                ['key' => 'completed_tasks', 'label' => 'Completed'],
                ['key' => 'overdue_tasks', 'label' => 'Overdue'],
            ],
        ],
        'overdue_tasks' => [
            'label' => 'Overdue tasks',
            'sql' => "
                SELECT c.id AS card_id, c.title AS task, l.title AS list_name,
                       c.due_date,
                       DATEDIFF(CURDATE(), c.due_date) AS days_overdue,
                       c.priority
                FROM cards c
                INNER JOIN lists l ON l.id = c.list_id
                WHERE l.board_id = ? AND l.is_archived = 0
                      AND c.is_completed = 0
                      AND c.due_date IS NOT NULL
                      AND c.due_date < CURDATE()
                ORDER BY c.due_date, c.position
            ",
            'columns' => [
                ['key' => 'task', 'label' => 'Task'],
                ['key' => 'list_name', 'label' => 'List'],
                ['key' => 'due_date', 'label' => 'Due Date', 'format' => 'date'],
                ['key' => 'days_overdue', 'label' => 'Days Overdue'],
                ['key' => 'priority', 'label' => 'Priority', 'format' => 'priority'],
            ],
        ],
        'assignees' => [
            'label' => 'Assignees',
            'sql' => "
                SELECT c.id AS card_id, u.name AS assignee, c.title AS task,
                       l.title AS list_name,
                       CASE WHEN c.is_completed = 1 THEN 'Completed'
                            WHEN c.due_date IS NOT NULL AND c.due_date < CURDATE() THEN 'Overdue'
                            ELSE 'Pending' END AS task_status,
                       c.due_date
                FROM card_assignees ca
                INNER JOIN users u ON u.id = ca.user_id
                INNER JOIN cards c ON c.id = ca.card_id
                INNER JOIN lists l ON l.id = c.list_id
                WHERE l.board_id = ? AND l.is_archived = 0
                ORDER BY u.name, c.is_completed, c.due_date IS NULL, c.due_date, c.position
            ",
            'columns' => [
                ['key' => 'assignee', 'label' => 'Assignee'],
                ['key' => 'task', 'label' => 'Task'],
                ['key' => 'list_name', 'label' => 'List'],
                ['key' => 'task_status', 'label' => 'Status'],
                ['key' => 'due_date', 'label' => 'Due Date', 'format' => 'date'],
            ],
        ],
    ];

    if (!isset($configs[$action])) {
        return null;
    }

    $config = $configs[$action];
    $stmt = $conn->prepare($config['sql']);
    if (!$stmt) {
        throw new RuntimeException('Could not prepare quick-question query');
    }
    $stmt->bind_param('i', $boardId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $boardStmt = $conn->prepare('SELECT name FROM boards WHERE id = ?');
    $boardStmt->bind_param('i', $boardId);
    $boardStmt->execute();
    $boardName = (string) ($boardStmt->get_result()->fetch_assoc()['name'] ?? 'this board');
    $boardStmt->close();

    $summary = buildQuickQuestionSummary($action, $rows, $boardName);
    $tableHtml = buildQuickQuestionTable($config['columns'], $rows);
    $historyText = html_entity_decode(strip_tags(str_replace('<br>', "\n", $summary)), ENT_QUOTES, 'UTF-8')
        . "\n\n" . buildQuickQuestionMarkdownTable($config['columns'], $rows);

    return [
        'label' => $config['label'],
        'summary' => $summary,
        'table_html' => $tableHtml,
        'history_text' => $historyText,
    ];
}

function buildQuickQuestionSummary(string $action, array $rows, string $boardName): string {
    $safeBoard = htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8');
    $count = count($rows);

    if ($action === 'pending_tasks') {
        $overdue = count(array_filter($rows, fn($row) =>
            !empty($row['due_date']) && $row['due_date'] < date('Y-m-d')
        ));
        return "<strong>{$count} pending task" . ($count === 1 ? '' : 's') . "</strong> found on {$safeBoard}.<br>"
            . ($overdue ? "{$overdue} of them are already overdue and need attention." : 'None of these tasks are currently overdue.');
    }

    if ($action === 'overdue_tasks') {
        return $count
            ? "<strong>{$count} overdue task" . ($count === 1 ? '' : 's') . "</strong> found on {$safeBoard}.<br>They are ordered by the oldest due date first."
            : "<strong>No overdue tasks</strong> were found on {$safeBoard}.<br>All incomplete tasks are still within their due dates.";
    }

    if ($action === 'board_summary') {
        $total = array_sum(array_column($rows, 'total_tasks'));
        $pending = array_sum(array_column($rows, 'pending_tasks'));
        $completed = array_sum(array_column($rows, 'completed_tasks'));
        $overdue = array_sum(array_column($rows, 'overdue_tasks'));
        return "<strong>{$safeBoard}</strong> has " . count($rows) . " list" . (count($rows) === 1 ? '' : 's')
            . " and {$total} task" . ($total === 1 ? '' : 's') . ".<br>"
            . "{$pending} pending, {$completed} completed, and {$overdue} overdue.";
    }

    $assignees = array_unique(array_column($rows, 'assignee'));
    $tasks = array_unique(array_column($rows, 'card_id'));
    return $count
        ? '<strong>' . count($assignees) . ' assignee' . (count($assignees) === 1 ? '' : 's')
            . '</strong> are assigned to ' . count($tasks) . ' task' . (count($tasks) === 1 ? '' : 's')
            . " on {$safeBoard}.<br>Each assignment is listed below with its current status."
        : "<strong>No task assignments</strong> were found on {$safeBoard}.<br>Tasks can be assigned from the task details panel.";
}

function buildQuickQuestionTable(array $columns, array $rows): string {
    $html = '<table class="ai-table"><thead><tr><th>#</th>';
    foreach ($columns as $column) {
        $html .= '<th>' . htmlspecialchars($column['label'], ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    if (!$rows) {
        return $html . '<tr><td colspan="' . (count($columns) + 1)
            . '" class="text-center">No records found</td></tr></tbody></table>';
    }

    foreach ($rows as $index => $row) {
        $html .= '<tr><td>' . ($index + 1) . '</td>';
        foreach ($columns as $column) {
            $value = formatQuickQuestionValue($row[$column['key']] ?? null, $column['format'] ?? null);
            $html .= '<td>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

function buildQuickQuestionMarkdownTable(array $columns, array $rows): string {
    $labels = array_merge(['#'], array_column($columns, 'label'));
    $markdown = '| ' . implode(' | ', $labels) . " |\n";
    $markdown .= '| ' . implode(' | ', array_fill(0, count($labels), '---')) . " |\n";
    foreach ($rows as $index => $row) {
        $values = [$index + 1];
        foreach ($columns as $column) {
            $value = formatQuickQuestionValue($row[$column['key']] ?? null, $column['format'] ?? null);
            $values[] = str_replace('|', '\|', $value);
        }
        $markdown .= '| ' . implode(' | ', $values) . " |\n";
    }
    return $markdown;
}

function formatQuickQuestionValue($value, ?string $format = null): string {
    if ($value === null || $value === '') {
        return '—';
    }
    if ($format === 'date') {
        return date('M j, Y', strtotime((string) $value));
    }
    if ($format === 'priority') {
        $priority = strtolower((string) $value);
        $icon = in_array($priority, ['urgent', 'high'], true) ? '🔴' : ($priority === 'medium' ? '🟡' : '🟢');
        return $icon . ' ' . ucfirst($priority);
    }
    return (string) $value;
}

// ============================================================
// CHAT HISTORY FUNCTIONS
// ============================================================

/**
 * Ensure chat messages table exists
 */
function ensureChatMessagesTable($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS ai_chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            board_id INT NOT NULL,
            role ENUM('user', 'assistant') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_board (user_id, board_id),
            INDEX idx_board_time (board_id, created_at)
        ) ENGINE=InnoDB
    ");
}

/**
 * Get chat history for a user and board
 */
function getChatHistory($conn, $userId, $boardId, $limit = 50) {
    $stmt = $conn->prepare("
        SELECT role, message, created_at
        FROM (
            SELECT id, role, message, created_at
            FROM ai_chat_messages
            WHERE user_id = ? AND board_id = ?
            ORDER BY id DESC
            LIMIT ?
        ) AS recent_messages
        ORDER BY id ASC
    ");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("iii", $userId, $boardId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rawMessages = [];
    
    while ($row = $result->fetch_assoc()) {
        // Operational notices belong in the status banner, not chat history.
        if ($row['role'] === 'assistant' && preg_match(
            '/running in offline mode|AI temporarily busy|full AI capabilities to restore|'
            . 'checking whether AI is available|AI is temporarily unavailable|'
            . 'chat will retry|usage limit.*refresh|unable to connect to the AI/i',
            $row['message']
        )) {
            continue;
        }
        $rawMessages[] = [
            'role' => $row['role'],
            'message' => $row['message'],
            'timestamp' => $row['created_at']
        ];
    }
    
    $stmt->close();

    // Successful requests are stored as user/assistant pairs. Hide user
    // messages left orphaned by older failed requests.
    $messages = [];
    $messageCount = count($rawMessages);
    for ($i = 0; $i < $messageCount; $i++) {
        $message = $rawMessages[$i];
        if ($message['role'] === 'user') {
            $next = $rawMessages[$i + 1] ?? null;
            if (!$next || $next['role'] !== 'assistant') {
                continue;
            }
        }
        $messages[] = $message;
    }

    return $messages;
}

/**
 * Save a chat message
 */
function saveChatMessage($conn, $userId, $boardId, $role, $message) {
    $stmt = $conn->prepare("
        INSERT INTO ai_chat_messages (user_id, board_id, role, message) 
        VALUES (?, ?, ?, ?)
    ");
    
    if ($stmt) {
        $stmt->bind_param("iiss", $userId, $boardId, $role, $message);
        $stmt->execute();
        $stmt->close();
    }
    
    // Clean up old messages using prepared statement (prevent SQL injection)
    $cleanupStmt = $conn->prepare("
        DELETE FROM ai_chat_messages 
        WHERE user_id = ? AND board_id = ? 
        AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM ai_chat_messages 
                WHERE user_id = ? AND board_id = ? 
                ORDER BY created_at DESC LIMIT 100
            ) as recent
        )
    ");
    if ($cleanupStmt) {
        $cleanupStmt->bind_param("iiii", $userId, $boardId, $userId, $boardId);
        $cleanupStmt->execute();
        $cleanupStmt->close();
    }
}

/**
 * Clear chat history for a user and board
 */
function clearChatHistory($conn, $userId, $boardId) {
    $stmt = $conn->prepare("DELETE FROM ai_chat_messages WHERE user_id = ? AND board_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $boardId);
        $stmt->execute();
        $stmt->close();
    }
}

// ============================================================
// RATE LIMITING FUNCTIONS
// ============================================================

function ensureAIServiceStatusTable($conn): void {
    $conn->query("
        CREATE TABLE IF NOT EXISTS ai_service_status (
            provider VARCHAR(50) PRIMARY KEY,
            unavailable_until DATETIME DEFAULT NULL,
            reason_code VARCHAR(50) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
}

function setAIUnavailable($conn, int $retryAfter, string $reasonCode): void {
    ensureAIServiceStatusTable($conn);
    $provider = AI_PROVIDER;
    $unavailableUntil = date('Y-m-d H:i:s', time() + max(5, $retryAfter));
    $stmt = $conn->prepare("
        INSERT INTO ai_service_status (provider, unavailable_until, reason_code)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            unavailable_until = VALUES(unavailable_until),
            reason_code = VALUES(reason_code)
    ");
    if ($stmt) {
        $stmt->bind_param('sss', $provider, $unavailableUntil, $reasonCode);
        $stmt->execute();
        $stmt->close();
    }
}

function clearAIUnavailable($conn): void {
    ensureAIServiceStatusTable($conn);
    $provider = AI_PROVIDER;
    $stmt = $conn->prepare("
        UPDATE ai_service_status
        SET unavailable_until = NULL, reason_code = NULL
        WHERE provider = ?
    ");
    if ($stmt) {
        $stmt->bind_param('s', $provider);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Return one status shape used by both the status endpoint and message guard.
 */
function getAIAvailabilityStatus($conn, int $userId): array {
    if (!isAIConfigured()) {
        return [
            'ai_available' => false,
            'reason_code' => 'not_configured',
            'retry_after' => 0,
            'message' => 'AI chat is currently unavailable.',
        ];
    }

    ensureAIServiceStatusTable($conn);
    $provider = AI_PROVIDER;
    $stmt = $conn->prepare("
        SELECT UNIX_TIMESTAMP(unavailable_until) AS unavailable_until, reason_code
        FROM ai_service_status
        WHERE provider = ?
    ");
    if ($stmt) {
        $stmt->bind_param('s', $provider);
        $stmt->execute();
        $serviceStatus = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $until = (int) ($serviceStatus['unavailable_until'] ?? 0);
        $reasonCode = $serviceStatus['reason_code'] ?? null;
        if ($until > time()) {
            $retryAfter = $until - time();
            $reasonCode = $reasonCode ?: 'unavailable';
            return [
                'ai_available' => false,
                'reason_code' => $reasonCode,
                'retry_after' => $retryAfter,
                'message' => $reasonCode === 'rate_limit'
                    ? 'AI usage limit reached. Chat will unlock automatically when the limit refreshes.'
                    : 'AI is temporarily unavailable. Chat will retry automatically.',
            ];
        }

        // The provider-supplied retry window has elapsed. Do not send a probe:
        // generation probes consume the same quota and can perpetuate a lock.
        if ($until > 0 && $reasonCode) {
            clearAIUnavailable($conn);
        }
    }

    // User-specific application rate limit.
    $windowStart = date('Y-m-d H:i:s', time() - AI_RATE_LIMIT_WINDOW);
    $rateStmt = $conn->prepare("
        SELECT COUNT(*) AS request_count, UNIX_TIMESTAMP(MIN(created_at)) AS oldest_request
        FROM ai_chat_logs
        WHERE user_id = ? AND created_at > ?
    ");
    if ($rateStmt) {
        $rateStmt->bind_param('is', $userId, $windowStart);
        $rateStmt->execute();
        $rateData = $rateStmt->get_result()->fetch_assoc();
        $rateStmt->close();
        if ((int) ($rateData['request_count'] ?? 0) >= (int) AI_RATE_LIMIT_REQUESTS) {
            $oldest = (int) ($rateData['oldest_request'] ?? time());
            $retryAfter = max(1, AI_RATE_LIMIT_WINDOW - (time() - $oldest));
            return [
                'ai_available' => false,
                'reason_code' => 'rate_limit',
                'retry_after' => $retryAfter,
                'message' => 'Your AI usage limit is refreshing. Chat will unlock automatically.',
            ];
        }
    }

    return [
        'ai_available' => true,
        'reason_code' => null,
        'retry_after' => 0,
        'message' => 'AI is available.',
    ];
}

/**
 * Check if user has exceeded rate limit
 */
function checkRateLimit($conn, $userId) {
    $windowStart = date('Y-m-d H:i:s', time() - AI_RATE_LIMIT_WINDOW);
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM ai_chat_logs 
        WHERE user_id = ? AND created_at > ?
    ");
    
    if (!$stmt) {
        return true; // Allow if table doesn't exist yet
    }
    
    $stmt->bind_param("is", $userId, $windowStart);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return ($result['count'] ?? 0) < AI_RATE_LIMIT_REQUESTS;
}

/**
 * Log AI request for rate limiting
 */
function logAIRequest($conn, $userId, $boardId) {
    // Create table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS ai_chat_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            board_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_time (user_id, created_at)
        )
    ");
    
    $stmt = $conn->prepare("INSERT INTO ai_chat_logs (user_id, board_id) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $boardId);
        $stmt->execute();
        $stmt->close();
    }
}

// ============================================================
// BOARD DATA FUNCTIONS
// ============================================================

/**
 * Fetch board data for AI context (minimal, secure data only)
 */
function fetchBoardDataForAI($conn, $boardId) {
    // Get board info
    $stmt = $conn->prepare("
        SELECT b.id, b.name, b.description, w.name as workspace_name
        FROM boards b
        LEFT JOIN workspaces w ON b.workspace_id = w.id
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $board = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$board) return null;
    
    // Get lists
    $stmt = $conn->prepare("
        SELECT id, title, position 
        FROM lists 
        WHERE board_id = ? 
        ORDER BY position
    ");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $lists = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get tasks with details
    $stmt = $conn->prepare("
        SELECT 
            c.id, c.title, c.description, c.start_date, c.due_date, 
            c.is_completed, c.created_at,
            l.title as list_name,
            u.name as created_by
        FROM cards c
        INNER JOIN lists l ON c.list_id = l.id
        INNER JOIN users u ON c.created_by = u.id
        WHERE l.board_id = ?
        ORDER BY l.position, c.position
    ");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get assignees for each task
    foreach ($tasks as &$task) {
        $stmt = $conn->prepare("
            SELECT u.name 
            FROM card_assignees ca
            INNER JOIN users u ON ca.user_id = u.id
            WHERE ca.card_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $task['id']);
            $stmt->execute();
            $assignees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $task['assignees'] = array_column($assignees, 'name');
            $stmt->close();
        } else {
            $task['assignees'] = [];
        }
        
        // Get labels
        $stmt = $conn->prepare("
            SELECT lb.name, lb.color 
            FROM card_labels cl
            INNER JOIN labels lb ON cl.label_id = lb.id
            WHERE cl.card_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $task['id']);
            $stmt->execute();
            $labels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $task['labels'] = array_column($labels, 'name');
            $stmt->close();
        } else {
            $task['labels'] = [];
        }
        
        // Remove internal ID from response
        unset($task['id']);
    }
    
    // Get board members
    $stmt = $conn->prepare("
        SELECT u.name, bm.role
        FROM board_members bm
        INNER JOIN users u ON bm.user_id = u.id
        WHERE bm.board_id = ?
    ");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Build clean data structure
    return [
        'board_name' => $board['name'],
        'workspace' => $board['workspace_name'],
        'description' => $board['description'] ?? '',
        'lists' => array_column($lists, 'title'),
        'total_lists' => count($lists),
        'total_tasks' => count($tasks),
        'tasks' => $tasks,
        'members' => $members,
        'stats' => calculateBoardStats($tasks),
        'current_date' => date('Y-m-d'),
        'current_time' => date('H:i:s')
    ];
}

/**
 * Calculate board statistics
 */
function calculateBoardStats($tasks) {
    $stats = [
        'total' => count($tasks),
        'completed' => 0,
        'pending' => 0,
        'overdue' => 0,
        'due_today' => 0,
        'due_this_week' => 0
    ];
    
    $today = date('Y-m-d');
    $weekEnd = date('Y-m-d', strtotime('+7 days'));
    
    foreach ($tasks as $task) {
        if ($task['is_completed']) {
            $stats['completed']++;
        } else {
            $stats['pending']++;
            
            if (!empty($task['due_date'])) {
                $dueDate = date('Y-m-d', strtotime($task['due_date']));
                if ($dueDate < $today) {
                    $stats['overdue']++;
                } elseif ($dueDate === $today) {
                    $stats['due_today']++;
                } elseif ($dueDate <= $weekEnd) {
                    $stats['due_this_week']++;
                }
            }
        }
    }
    
    return $stats;
}

// ============================================================
// AI PROMPT BUILDING
// ============================================================

/**
 * Build the AI prompt with context, history, and user question
 * @param array $filesData - Array of processed file data (can be empty, single, or multiple)
 */
function buildAIPrompt($boardData, $userMessage, $conversationHistory, $userName, $filesData = []) {
    $systemPrompt = AI_SYSTEM_PROMPT;
    
    // Handle both single file (legacy) and multiple files
    if (!empty($filesData) && !isset($filesData[0])) {
        // Single file passed directly (legacy format)
        $filesData = [$filesData];
    }
    
    // Add file analysis instructions based on file types
    if (!empty($filesData)) {
        $fileCount = count($filesData);
        $imageCount = count(array_filter($filesData, fn($f) => ($f['type'] ?? '') === 'image'));
        $textCount = count(array_filter($filesData, fn($f) => ($f['type'] ?? '') === 'text'));
        $docCount = count(array_filter($filesData, fn($f) => ($f['type'] ?? '') === 'document'));
        
        $fileNames = array_map(fn($f) => $f['name'] ?? 'file', $filesData);
        $fileListStr = implode(', ', $fileNames);
        
        if ($fileCount === 1) {
            $fileType = $filesData[0]['type'] ?? 'file';
            $fileName = $filesData[0]['name'] ?? 'file';
            
            if ($fileType === 'image') {
                $systemPrompt .= "\n\nIMAGE ANALYSIS MODE:
- The user has uploaded an image ({$fileName}) for you to analyze
- Describe what you see in the image clearly and helpfully
- If the user asks a specific question about the image, answer it directly
- You can still reference board data if the question relates to tasks
- Be detailed but concise in your image descriptions";
            } elseif ($fileType === 'text') {
                $systemPrompt .= "\n\nFILE ANALYSIS MODE:
- The user has uploaded a file ({$fileName}) for you to analyze
- The file content is provided below the question
- Analyze the content and answer the user's question about it
- For CSV/data files: summarize the data, identify patterns, answer questions about specific entries
- For code files: explain the code, identify issues, suggest improvements
- For text files: summarize content, answer questions about the text
- You can still reference board data if the question relates to tasks
- Be helpful and provide actionable insights";
            } elseif ($fileType === 'document') {
                $systemPrompt .= "\n\nDOCUMENT ANALYSIS MODE:
- The user has uploaded a document ({$fileName}) for you to analyze
- Analyze the document visually and answer the user's question
- For PDFs: describe visible content, text, images, layout
- Be helpful and provide useful information about the document";
            }
        } else {
            // Multiple files
            $systemPrompt .= "\n\nMULTIPLE FILES ANALYSIS MODE:
- The user has uploaded {$fileCount} files for you to analyze: {$fileListStr}";
            
            if ($imageCount > 0) {
                $systemPrompt .= "\n- {$imageCount} image(s): Describe what you see in each image";
            }
            if ($textCount > 0) {
                $systemPrompt .= "\n- {$textCount} text/code file(s): The content is provided below - analyze and summarize";
            }
            if ($docCount > 0) {
                $systemPrompt .= "\n- {$docCount} document(s): Analyze visually";
            }
            
            $systemPrompt .= "
- Analyze ALL files and provide a comprehensive response
- Compare files if relevant to the user's question
- You can still reference board data if the question relates to tasks
- Be organized - address each file clearly if discussing multiple files";
        }
    }
    
    // Add user context
    $systemPrompt .= "\n\nCURRENT USER: " . $userName;
    $systemPrompt .= "\nCURRENT DATE: " . date('l, F j, Y');
    $systemPrompt .= "\nCURRENT TIME: " . date('g:i A');
    $systemPrompt .= "\n\nFORMAT GUIDANCE FOR THIS QUESTION:\n"
        . getQuestionFormatGuidance($userMessage);
    
    // Build conversation history for context
    $historyText = "";
    if (!empty($conversationHistory)) {
        $historyText = "\n\nPREVIOUS CONVERSATION (for context - understand follow-up questions based on this):\n";
        // Include the latest turns in chronological order so references such as
        // "those tasks" and "the second one" remain meaningful.
        $recentHistory = array_slice($conversationHistory, -20);
        foreach ($recentHistory as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
            // Retain enough detail (including table rows) to resolve follow-ups.
            $msgText = strlen($msg['message']) > 1200
                ? substr($msg['message'], 0, 1200) . '...'
                : $msg['message'];
            $historyText .= "$role: $msgText\n";
        }
    }
    
    $contextPrompt = "
BOARD DATA:
" . json_encode($boardData, JSON_PRETTY_PRINT) . "
$historyText
CURRENT USER QUESTION: " . $userMessage . "

IMPORTANT: If this question references something from the previous conversation (like 'those tasks', 'from this week', 'the same', etc.), use the conversation history to understand what the user is referring to.";
    
    return [
        'system' => $systemPrompt,
        'user' => $contextPrompt
    ];
}

/**
 * Give the model a query-specific presentation hint while leaving final
 * judgment to it.
 */
function getQuestionFormatGuidance(string $question): string {
    $question = strtolower(trim($question));

    if (preg_match(
        '/\b(description|describe it|who is assigned|assigned to|due date|priority|status|'
        . 'what is (?:the )?name|when is|where is)\b/i',
        $question
    )) {
        return '- This is a focused fact/description request. Use plain text in 1-3 sentences. Do not use a table.';
    }

    if (preg_match('/\b(names?|members?|assignees?)\b/i', $question)
        && !preg_match('/\b(compare|breakdown|statistics?|details?)\b/i', $question)) {
        return '- If several names are returned, use a short bullet list. Do not use a table unless comparison columns are needed.';
    }

    if (preg_match('/\b(all|pending|overdue|compare|summary|breakdown|statistics?|report)\b/i', $question)) {
        return '- This may return multiple structured records. Use a table only if multiple rows and useful comparison columns are present.';
    }

    return '- Choose naturally between concise prose, bullets, or a table. Prefer prose unless structure materially improves clarity.';
}

// ============================================================
// FILE PROCESSING FUNCTIONS
// ============================================================

/**
 * Process file data for AI analysis
 * - Images: Keep as base64 for vision API
 * - Text files: Extract text content
 * - PDF: Extract text (limited support)
 */
function processFileForAI($fileData) {
    if (!$fileData || empty($fileData['data'])) {
        return null;
    }
    
    $fileType = $fileData['type'] ?? 'image';
    $mimeType = $fileData['mime_type'] ?? 'application/octet-stream';
    $fileName = $fileData['name'] ?? 'file';
    
    // For images, return as-is for vision API
    if ($fileType === 'image') {
        return [
            'type' => 'image',
            'data' => $fileData['data'],
            'mime_type' => $mimeType,
            'name' => $fileName
        ];
    }
    
    // For text-based files, decode and extract content
    $decodedData = base64_decode($fileData['data']);
    
    if ($fileType === 'text' || isTextMimeType($mimeType)) {
        // Direct text content
        $textContent = $decodedData;
        
        // Limit text size to prevent token overflow (max ~15000 chars)
        if (strlen($textContent) > 15000) {
            $textContent = substr($textContent, 0, 15000) . "\n\n[... Content truncated due to size limit ...]";
        }
        
        return [
            'type' => 'text',
            'content' => $textContent,
            'mime_type' => $mimeType,
            'name' => $fileName
        ];
    }
    
    // For PDF files, try to extract text
    if ($mimeType === 'application/pdf' || $fileType === 'document') {
        $textContent = extractTextFromPDF($decodedData);
        
        if ($textContent) {
            // Limit text size
            if (strlen($textContent) > 15000) {
                $textContent = substr($textContent, 0, 15000) . "\n\n[... Content truncated due to size limit ...]";
            }
            
            return [
                'type' => 'text',
                'content' => $textContent,
                'mime_type' => $mimeType,
                'name' => $fileName
            ];
        }
        
        // If PDF text extraction fails, return as document for vision API
        return [
            'type' => 'document',
            'data' => $fileData['data'],
            'mime_type' => $mimeType,
            'name' => $fileName,
            'note' => 'PDF text extraction failed, using vision analysis'
        ];
    }
    
    return null;
}

/**
 * Check if MIME type is text-based
 */
function isTextMimeType($mimeType) {
    $textTypes = [
        'text/plain',
        'text/csv',
        'text/html',
        'text/css',
        'text/javascript',
        'text/xml',
        'text/markdown',
        'text/x-python',
        'application/json',
        'application/xml',
        'application/javascript',
        'application/x-httpd-php',
        'application/sql'
    ];
    
    return in_array($mimeType, $textTypes) || strpos($mimeType, 'text/') === 0;
}

/**
 * Extract text from PDF (basic implementation)
 */
function extractTextFromPDF($pdfData) {
    // Simple PDF text extraction
    // This is a basic implementation - for better results, use a library like TCPDF or Smalot
    
    $text = '';
    
    // Try to find text streams in PDF
    if (preg_match_all('/stream\s*\n(.+?)\nendstream/s', $pdfData, $matches)) {
        foreach ($matches[1] as $stream) {
            // Try to decompress if zlib compressed
            $decompressed = @gzuncompress($stream);
            if ($decompressed !== false) {
                $stream = $decompressed;
            }
            
            // Extract text from stream
            if (preg_match_all('/\(([^)]+)\)/', $stream, $textMatches)) {
                $text .= implode(' ', $textMatches[1]) . ' ';
            }
            
            // Also try BT...ET text blocks
            if (preg_match_all('/BT\s*(.+?)\s*ET/s', $stream, $btMatches)) {
                foreach ($btMatches[1] as $btContent) {
                    if (preg_match_all('/\(([^)]+)\)/', $btContent, $innerText)) {
                        $text .= implode(' ', $innerText[1]) . ' ';
                    }
                    if (preg_match_all('/\<([^>]+)\>/', $btContent, $hexText)) {
                        foreach ($hexText[1] as $hex) {
                            $text .= @hex2bin($hex) . ' ';
                        }
                    }
                }
            }
        }
    }
    
    // Clean up extracted text
    $text = preg_replace('/[^\x20-\x7E\n\r\t]/', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    return !empty($text) ? $text : null;
}

// ============================================================
// GEMINI API FUNCTIONS
// ============================================================

/**
 * Call Gemini AI API
 * @param array $filesData - Array of processed file data (can be empty, single, or multiple)
 */
function callGeminiAPI(
    $prompt,
    $filesData = [],
    ?int $maxOutputTokens = null,
    int $timeoutSeconds = 30
) {
    // Check if curl extension is available
    if (!function_exists('curl_init')) {
        error_log("cURL extension not available");
        return ['success' => false, 'error' => 'Connection error: cURL not available'];
    }
    
    $url = AI_API_URL . '?key=' . AI_API_KEY;
    
    // Handle both single file (legacy) and multiple files
    if (!empty($filesData) && !isset($filesData[0])) {
        // Single file passed directly (legacy format)
        $filesData = [$filesData];
    }
    
    // Build the parts array
    $parts = [];
    
    // Build the prompt text
    $promptText = $prompt['system'] . "\n\n" . $prompt['user'];
    
    // Append text content from all text files to the prompt
    if (!empty($filesData)) {
        foreach ($filesData as $index => $fileData) {
            if ($fileData && $fileData['type'] === 'text' && !empty($fileData['content'])) {
                $fileName = $fileData['name'] ?? 'file';
                $fileNum = count($filesData) > 1 ? " #" . ($index + 1) : "";
                $promptText .= "\n\n--- FILE{$fileNum} CONTENT ({$fileName}) ---\n" . $fileData['content'] . "\n--- END OF FILE ---";
            }
        }
    }
    
    // Add text prompt
    $parts[] = ['text' => $promptText];
    
    // Add images/documents (for vision API) - Gemini supports multiple images
    if (!empty($filesData)) {
        foreach ($filesData as $fileData) {
            if ($fileData && ($fileData['type'] === 'image' || $fileData['type'] === 'document') && !empty($fileData['data'])) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $fileData['mime_type'] ?? 'image/jpeg',
                        'data' => $fileData['data']
                    ]
                ];
            }
        }
    }
    
    $requestBody = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => $parts
            ]
        ],
        'generationConfig' => [
            'temperature' => AI_TEMPERATURE,
            'maxOutputTokens' => $maxOutputTokens ?? AI_MAX_TOKENS,
            'topP' => 0.8,
            'topK' => 40
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE']
        ]
    ];
    
    $ch = curl_init();
    if (!$ch) {
        error_log("Failed to initialize cURL");
        return ['success' => false, 'error' => 'Connection error: Failed to initialize'];
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestBody),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(3, $timeoutSeconds),
        CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification for localhost/Windows compatibility
        CURLOPT_SSL_VERIFYHOST => 0
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    
    if ($error || $errno) {
        error_log("Gemini API cURL Error [{$errno}]: " . $error);
        // Check if it's a connection/network error
        if (strpos($error, 'resolve') !== false || strpos($error, 'connect') !== false || strpos($error, 'timed out') !== false || $errno == 6 || $errno == 7) {
            return ['success' => false, 'error' => 'Connection error: Unable to reach AI service'];
        }
        // SSL errors
        if (strpos($error, 'SSL') !== false || strpos($error, 'certificate') !== false || $errno == 35 || $errno == 60) {
            return ['success' => false, 'error' => 'Connection error: SSL certificate issue'];
        }
        return ['success' => false, 'error' => 'Connection error: ' . $error];
    }
    
    // Check if response is empty (blocked by hosting)
    if (empty($response) && $httpCode === 0) {
        return ['success' => false, 'error' => 'Connection error: No response from API'];
    }
    
    if ($httpCode !== 200) {
        error_log("Gemini API HTTP Error: " . $httpCode . " - " . $response);
        $errorData = json_decode($response, true);
        $errorMessage = 'AI service temporarily unavailable';
        $retryAfter = null;
        
        if (isset($errorData['error']['message'])) {
            $apiError = $errorData['error']['message'];
            
            if (strpos($apiError, 'API key') !== false) {
                $errorMessage = 'AI service configuration error. Please contact administrator.';
            } elseif ($httpCode === 429 || strpos($apiError, 'quota') !== false || strpos($apiError, 'RESOURCE_EXHAUSTED') !== false) {
                // Extract retry time if available
                $retryTime = '';
                if (isset($errorData['error']['details'])) {
                    foreach ($errorData['error']['details'] as $detail) {
                        if (isset($detail['retryDelay'])) {
                            $retryTime = ' Please try again in ' . $detail['retryDelay'] . '.';
                            if (preg_match('/(\d+)/', (string) $detail['retryDelay'], $retryMatch)) {
                                $retryAfter = (int) $retryMatch[1];
                            }
                            break;
                        }
                    }
                }
                $errorMessage = 'AI service is busy. The free tier limit has been reached.' . $retryTime;
            } else {
                $errorMessage = 'AI service error. Please try again later.';
            }
        }
        return [
            'success' => false,
            'error' => $errorMessage,
            'retry_after' => $retryAfter,
        ];
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Gemini API Invalid Response: " . $response);
        return ['success' => false, 'error' => 'Invalid API response'];
    }
    
    return [
        'success' => true,
        'response' => $data['candidates'][0]['content']['parts'][0]['text']
    ];
}

// ============================================================
// FALLBACK RESPONSE GENERATOR (for when AI API is not available)
// ============================================================

/**
 * Generate a fallback response based on the user's question and board data
 * This is used when the AI API is unavailable (e.g., on free hosting)
 */
function generateFallbackResponse($userMessage, $boardData, $userName, $conversationHistory = []) {
    $message = strtolower(trim($userMessage));
    $boardName = $boardData['board_name'] ?? 'this board';

    // In offline mode, carry the last user request into vague follow-ups.
    if (preg_match('/\b(those|them|these|same|previous|above|ones)\b/i', $userMessage)) {
        for ($i = count($conversationHistory) - 1; $i >= 0; $i--) {
            if (($conversationHistory[$i]['role'] ?? '') === 'user') {
                $message = strtolower($conversationHistory[$i]['message'] . ' ' . $userMessage);
                break;
            }
        }
    }
    
    // Extract data from boardData
    $lists = $boardData['lists'] ?? [];
    $tasks = $boardData['tasks'] ?? [];
    $members = $boardData['members'] ?? [];
    
    // Count tasks
    $totalTasks = count($tasks);
    $completedTasks = 0;
    $pendingTasks = 0;
    $overdueTasks = 0;
    $highPriorityTasks = 0;
    $userAssignedTasks = [];
    $today = date('Y-m-d');
    
    foreach ($tasks as $task) {
        $listName = strtolower($task['list_name'] ?? '');
        if (strpos($listName, 'done') !== false || strpos($listName, 'complete') !== false) {
            $completedTasks++;
        } else {
            $pendingTasks++;
        }
        
        // Check overdue
        if (!empty($task['due_date']) && $task['due_date'] < $today) {
            if (strpos($listName, 'done') === false && strpos($listName, 'complete') === false) {
                $overdueTasks++;
            }
        }
        
        // Check priority
        if (($task['priority'] ?? '') === 'high') {
            $highPriorityTasks++;
        }
        
        // Check assigned to current user
        $assignees = $task['assignees'] ?? [];
        if (is_array($assignees)) {
            // Check if current user is in assignees array
            foreach ($assignees as $assignee) {
                if (stripos($assignee, $userName) !== false) {
                    $userAssignedTasks[] = $task;
                    break;
                }
            }
        } elseif (is_string($assignees) && stripos($assignees, $userName) !== false) {
            $userAssignedTasks[] = $task;
        }
    }
    
    // Pattern matching for common questions
    
    // Board summary
    if (preg_match('/(summary|overview|tell me about|what.*(is|about)|describe).*board/i', $message) || 
        $message === 'board summary' || $message === 'summary') {
        $response = "📋 **Board Summary: {$boardName}**\n\n";
        
        // Overview stats table
        $response .= "| Metric | Count |\n";
        $response .= "|--------|-------|\n";
        $response .= "| 📊 Total Lists | " . count($lists) . " |\n";
        $response .= "| 📝 Total Tasks | {$totalTasks} |\n";
        $response .= "| ✅ Completed | {$completedTasks} |\n";
        $response .= "| ⏳ Pending | {$pendingTasks} |\n";
        if ($overdueTasks > 0) {
            $response .= "| ⚠️ Overdue | {$overdueTasks} |\n";
        }
        if ($highPriorityTasks > 0) {
            $response .= "| 🔴 High Priority | {$highPriorityTasks} |\n";
        }
        $response .= "| 👥 Team Members | " . count($members) . " |\n";
        
        // Task breakdown by list
        if (!empty($lists)) {
            $response .= "\n**Tasks by List:**\n\n";
            $response .= "| List | Tasks | Progress |\n";
            $response .= "|------|-------|----------|\n";
            
            foreach ($lists as $list) {
                $listName = $list['title'] ?? 'Untitled List';
                $taskCount = 0;
                foreach ($tasks as $task) {
                    if (($task['list_id'] ?? 0) == ($list['id'] ?? -1)) {
                        $taskCount++;
                    }
                }
                $progress = $taskCount === 0 ? '✨ Empty' : ($taskCount > 5 ? '🔥 ' . $taskCount : '📝 ' . $taskCount);
                $response .= "| {$listName} | {$taskCount} | {$progress} |\n";
            }
        }
        
        $response .= "\n";
        if ($pendingTasks > 0) {
            $response .= "**Status:** You have {$pendingTasks} pending task(s) to work on.";
        } else {
            $response .= "**Status:** Great job! All tasks are completed! 🎉";
        }
        return $response;
    }
    
    // Pending tasks
    if (preg_match('/(pending|open|remaining|not done|incomplete|to.?do)/i', $message)) {
        if ($pendingTasks === 0) {
            return "✅ Great news! There are no pending tasks on **{$boardName}**. All tasks have been completed!";
        }
        
        $response = "📝 **Pending Tasks on {$boardName}**\n\n";
        $response .= "| # | Task | List | Due Date | Priority |\n";
        $response .= "|---|------|------|----------|----------|\n";
        
        $count = 0;
        $highCount = 0;
        $mediumCount = 0;
        foreach ($tasks as $task) {
            $listName = strtolower($task['list_name'] ?? '');
            if (strpos($listName, 'done') === false && strpos($listName, 'complete') === false) {
                $count++;
                if ($count <= 10) {
                    $title = $task['title'] ?? 'Untitled';
                    $list = $task['list_name'] ?? 'Unknown List';
                    $priority = $task['priority'] ?? 'low';
                    $priorityIcon = $priority === 'high' ? '🔴 High' : ($priority === 'medium' ? '🟡 Medium' : '🟢 Low');
                    $dueDate = !empty($task['due_date']) ? date('M j, Y', strtotime($task['due_date'])) : '-';
                    $response .= "| {$count} | {$title} | {$list} | {$dueDate} | {$priorityIcon} |\n";
                }
                if ($priority === 'high') $highCount++;
                if ($priority === 'medium') $mediumCount++;
            }
        }
        if ($pendingTasks > 10) {
            $response .= "\n*... and " . ($pendingTasks - 10) . " more tasks.*\n";
        }
        $response .= "\n**Summary:** {$pendingTasks} pending tasks";
        if ($highCount > 0) $response .= ", {$highCount} high priority";
        if ($mediumCount > 0) $response .= ", {$mediumCount} medium priority";
        return $response;
    }
    
    // Overdue tasks
    if (preg_match('/(overdue|late|past due|missed|expired)/i', $message)) {
        if ($overdueTasks === 0) {
            return "✅ Excellent! There are no overdue tasks on **{$boardName}**. You're all caught up!";
        }
        
        $response = "⚠️ **Overdue Tasks on {$boardName}**\n\n";
        $response .= "| # | Task | List | Due Date | Days Overdue |\n";
        $response .= "|---|------|------|----------|-------------|\n";
        
        $count = 0;
        foreach ($tasks as $task) {
            if (!empty($task['due_date']) && $task['due_date'] < $today) {
                $listName = strtolower($task['list_name'] ?? '');
                if (strpos($listName, 'done') === false && strpos($listName, 'complete') === false) {
                    $count++;
                    $title = $task['title'] ?? 'Untitled';
                    $list = $task['list_name'] ?? 'Unknown';
                    $dueDate = date('M j, Y', strtotime($task['due_date']));
                    $daysOverdue = floor((strtotime($today) - strtotime($task['due_date'])) / 86400);
                    $response .= "| {$count} | {$title} | {$list} | {$dueDate} | {$daysOverdue} day(s) |\n";
                }
            }
        }
        $response .= "\n**Summary:** {$overdueTasks} task(s) need immediate attention!";
        return $response;
    }
    
    // My tasks / assigned to me
    if (preg_match('/(my task|assigned to me|my assignment|what.*(i|my).*do|my work)/i', $message)) {
        if (empty($userAssignedTasks)) {
            return "📋 You don't have any tasks assigned to you on **{$boardName}** at the moment.";
        }
        
        $response = "📋 **Your Assigned Tasks on {$boardName}**\n\n";
        $response .= "| # | Task | List | Due Date | Priority |\n";
        $response .= "|---|------|------|----------|----------|\n";
        
        $count = 0;
        foreach ($userAssignedTasks as $task) {
            $count++;
            $title = $task['title'] ?? 'Untitled';
            $list = $task['list_name'] ?? 'Unknown';
            $dueDate = !empty($task['due_date']) ? date('M j, Y', strtotime($task['due_date'])) : '-';
            $priority = $task['priority'] ?? 'low';
            $priorityIcon = $priority === 'high' ? '🔴 High' : ($priority === 'medium' ? '🟡 Medium' : '🟢 Low');
            $response .= "| {$count} | {$title} | {$list} | {$dueDate} | {$priorityIcon} |\n";
        }
        $response .= "\n**Summary:** " . count($userAssignedTasks) . " task(s) assigned to you.";
        return $response;
    }
    
    // Team members / assignees
    if (preg_match('/(member|team|who|assignee|people|collaborator)/i', $message)) {
        if (empty($members)) {
            return "I couldn't find team member information for this board.";
        }
        
        // Count tasks per member
        $memberTaskCounts = [];
        foreach ($tasks as $task) {
            $assignees = $task['assignees'] ?? [];
            foreach ($members as $member) {
                $memberName = $member['name'] ?? '';
                if (!empty($memberName)) {
                    $found = false;
                    if (is_array($assignees)) {
                        foreach ($assignees as $assignee) {
                            if (stripos($assignee, $memberName) !== false) {
                                $found = true;
                                break;
                            }
                        }
                    } elseif (is_string($assignees) && stripos($assignees, $memberName) !== false) {
                        $found = true;
                    }
                    if ($found) {
                        if (!isset($memberTaskCounts[$memberName])) {
                            $memberTaskCounts[$memberName] = 0;
                        }
                        $memberTaskCounts[$memberName]++;
                    }
                }
            }
        }
        
        $response = "👥 **Team Members on {$boardName}**\n\n";
        $response .= "| # | Member | Role | Assigned Tasks |\n";
        $response .= "|---|--------|------|---------------|\n";
        
        $count = 0;
        foreach ($members as $member) {
            $count++;
            $name = $member['name'] ?? 'Unknown';
            $role = $member['role'] ?? 'member';
            $roleDisplay = $role === 'owner' ? 'Super Admin' : ($role === 'admin' ? 'Admin' : 'Member');
            $taskCount = $memberTaskCounts[$name] ?? 0;
            $response .= "| {$count} | {$name} | {$roleDisplay} | {$taskCount} |\n";
        }
        $response .= "\n**Summary:** " . count($members) . " team member(s) on this board.";
        return $response;
    }
    
    // Lists
    if (preg_match('/(list|column|stage|workflow)/i', $message)) {
        if (empty($lists)) {
            return "This board doesn't have any lists yet.";
        }
        
        $response = "📊 **Lists on {$boardName}**\n\n";
        $response .= "| # | List Name | Tasks | Status |\n";
        $response .= "|---|-----------|-------|--------|\n";
        
        $count = 0;
        $totalTasksInLists = 0;
        foreach ($lists as $list) {
            $count++;
            $listName = $list['title'] ?? 'Untitled List';
            $taskCount = 0;
            foreach ($tasks as $task) {
                if (($task['list_id'] ?? 0) == ($list['id'] ?? -1)) {
                    $taskCount++;
                }
            }
            $totalTasksInLists += $taskCount;
            $status = $taskCount === 0 ? '✅ Empty' : ($taskCount > 5 ? '🔥 Busy' : '📝 Active');
            $response .= "| {$count} | {$listName} | {$taskCount} | {$status} |\n";
        }
        $response .= "\n**Summary:** " . count($lists) . " list(s), {$totalTasksInLists} total task(s).";
        return $response;
    }
    
    // High priority
    if (preg_match('/(high priority|urgent|important|critical)/i', $message)) {
        if ($highPriorityTasks === 0) {
            return "✅ There are no high-priority tasks on **{$boardName}** at the moment.";
        }
        
        $response = "🔴 **High Priority Tasks on {$boardName}**\n\n";
        $response .= "| # | Task | List | Due Date | Assignees |\n";
        $response .= "|---|------|------|----------|----------|\n";
        
        $count = 0;
        foreach ($tasks as $task) {
            if (($task['priority'] ?? '') === 'high') {
                $count++;
                $title = $task['title'] ?? 'Untitled';
                $list = $task['list_name'] ?? 'Unknown';
                $dueDate = !empty($task['due_date']) ? date('M j, Y', strtotime($task['due_date'])) : '-';
                $assigneesRaw = $task['assignees'] ?? [];
                $assignees = is_array($assigneesRaw) ? (empty($assigneesRaw) ? '-' : implode(', ', $assigneesRaw)) : ($assigneesRaw ?: '-');
                $response .= "| {$count} | {$title} | {$list} | {$dueDate} | {$assignees} |\n";
            }
        }
        $response .= "\n**Summary:** {$highPriorityTasks} high-priority task(s) need attention!";
        return $response;
    }
    
    // Greeting
    if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)/i', $message)) {
        return "Hello {$userName}! 👋 I'm your Planify Assistant (AI is temporarily busy). I can help you with:\n\n" .
               "• **Board summary** - Overview of this board\n" .
               "• **Pending tasks** - Tasks that need to be done\n" .
               "• **Overdue tasks** - Tasks past their due date\n" .
               "• **My tasks** - Tasks assigned to you\n" .
               "• **Team members** - Who's on this board\n\n" .
               "What would you like to know?";
    }
    
    // Help
    if (preg_match('/(help|what can you|how do|assist)/i', $message)) {
        return "👋 I'm your Planify Assistant (AI temporarily busy). Here's what I can help you with:\n\n" .
               "**Quick Commands:**\n" .
               "• \"Board summary\" - Get an overview of the board\n" .
               "• \"Pending tasks\" - See incomplete tasks\n" .
               "• \"Overdue tasks\" - Find tasks past due date\n" .
               "• \"My tasks\" - View your assigned tasks\n" .
               "• \"Team members\" - See who's on this board\n" .
               "• \"High priority\" - View urgent tasks\n" .
               "• \"Lists\" - See all lists and task counts\n\n" .
               "Just type any of these commands!";
    }
    
    // Statistics / count
    if (preg_match('/(how many|count|total|number of|statistic)/i', $message)) {
        $response = "📊 **Board Statistics for {$boardName}:**\n\n";
        $response .= "• **Lists:** " . count($lists) . "\n";
        $response .= "• **Total Tasks:** {$totalTasks}\n";
        $response .= "• **Completed:** {$completedTasks}\n";
        $response .= "• **Pending:** {$pendingTasks}\n";
        $response .= "• **Overdue:** {$overdueTasks}\n";
        $response .= "• **High Priority:** {$highPriorityTasks}\n";
        $response .= "• **Team Members:** " . count($members);
        return $response;
    }
    
    // Default response with suggestions
    return "I'm running in **offline mode** because the AI API is temporarily busy (rate limit reached). " .
           "I can still help with basic queries about your board!\n\n" .
           "**Try asking:**\n" .
           "• \"Board summary\"\n" .
           "• \"Pending tasks\"\n" .
           "• \"Overdue tasks\"\n" .
           "• \"My tasks\"\n" .
           "• \"Team members\"\n\n" .
           "_Wait 1-2 minutes for full AI capabilities to restore._";
}

// ============================================================
// RESPONSE FORMATTING
// ============================================================

/**
 * Format AI response and detect tables
 */
function formatAIResponse($text, string $userMessage = '') {
    $hasTable = false;
    $tableHtml = null;
    $summaryText = '';
    
    // Check if response contains a markdown table
    if (preg_match('/\|.*\|.*\n\|[-:| ]+\|/', $text)) {
        $hasTable = true;
        
        // Extract non-table text (summary) and convert table to HTML
        $result = extractTextAndTable($text);
        $summaryText = $result['text'];
        $tableHtml = $result['table_html'];

        // Guard against redundant one-row tables for focused questions such as
        // descriptions, assignees, dates, priorities, and statuses.
        $formatHint = getQuestionFormatGuidance($userMessage);
        if (($result['row_count'] ?? 0) <= 1
            && strpos($formatHint, 'plain text') !== false
            && trim($summaryText) !== '') {
            $hasTable = false;
            $tableHtml = null;
        }
    } else {
        $summaryText = $text;
    }
    
    // Clean up the summary text
    $summaryText = trim($summaryText);
    
    // Convert markdown headers (### Header)
    $summaryText = preg_replace('/^### (.*?)$/m', '<h4 class="font-bold text-base mt-3 mb-1">$1</h4>', $summaryText);
    $summaryText = preg_replace('/^## (.*?)$/m', '<h3 class="font-bold text-lg mt-3 mb-1">$1</h3>', $summaryText);
    $summaryText = preg_replace('/^# (.*?)$/m', '<h2 class="font-bold text-xl mt-3 mb-1">$1</h2>', $summaryText);
    
    // Convert markdown bold to HTML
    $summaryText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $summaryText);
    
    // Convert markdown italic to HTML (but not ** patterns)
    $summaryText = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $summaryText);
    
    // Convert bullet points (• or - or * at start of line)
    $lines = explode("\n", $summaryText);
    $inList = false;
    $formattedLines = [];
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Check if line starts with bullet point markers
        if (preg_match('/^[•\-\*]\s+(.*)$/', $trimmed, $matches)) {
            if (!$inList) {
                $formattedLines[] = '<ul class="list-disc list-inside my-2 space-y-1">';
                $inList = true;
            }
            $formattedLines[] = '<li class="ml-2">' . $matches[1] . '</li>';
        } else {
            if ($inList) {
                $formattedLines[] = '</ul>';
                $inList = false;
            }
            $formattedLines[] = $line;
        }
    }
    
    if ($inList) {
        $formattedLines[] = '</ul>';
    }
    
    $summaryText = implode("\n", $formattedLines);
    
    // Convert remaining line breaks (but not inside list items)
    $summaryText = preg_replace('/(?<!<\/li>)\n(?!<)/', '<br>', $summaryText);
    $summaryText = str_replace("\n", '', $summaryText); // Clean up remaining newlines
    
    return [
        'text' => $summaryText,
        'has_table' => $hasTable,
        'table_html' => $tableHtml
    ];
}

/**
 * Extract text and convert markdown table to HTML
 * Returns both the non-table text (summary) and the HTML table
 */
function extractTextAndTable($text) {
    $lines = explode("\n", $text);
    $inTable = false;
    $tableHtml = '';
    $outputText = '';
    $dataRowCount = 0;
    
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        
        // Check if this is a table row (starts and ends with |)
        if (preg_match('/^\|.*\|$/', $trimmedLine)) {
            // Skip separator row (|---|---|)
            if (preg_match('/^\|[-:| ]+\|$/', $trimmedLine)) {
                continue;
            }
            
            if (!$inTable) {
                $tableHtml .= '<table class="ai-table">';
                $inTable = true;
                
                // First row is header
                $cells = array_map('trim', explode('|', trim($trimmedLine, '|')));
                $tableHtml .= '<thead><tr>';
                foreach ($cells as $cell) {
                    $tableHtml .= '<th>' . htmlspecialchars($cell) . '</th>';
                }
                $tableHtml .= '</tr></thead><tbody>';
            } else {
                // Data row
                $cells = array_map('trim', explode('|', trim($trimmedLine, '|')));
                $tableHtml .= '<tr>';
                foreach ($cells as $cell) {
                    $tableHtml .= '<td>' . htmlspecialchars($cell) . '</td>';
                }
                $tableHtml .= '</tr>';
                $dataRowCount++;
            }
        } else {
            // Not a table row - this is regular text
            if ($inTable) {
                $tableHtml .= '</tbody></table>';
                $inTable = false;
            }
            // Only add non-empty lines to output text
            if (!empty($trimmedLine)) {
                $outputText .= $trimmedLine . "\n";
            }
        }
    }
    
    if ($inTable) {
        $tableHtml .= '</tbody></table>';
    }
    
    return [
        'text' => trim($outputText),
        'table_html' => $tableHtml,
        'row_count' => $dataRowCount,
    ];
}
