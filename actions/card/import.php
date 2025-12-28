<?php
/**
 * Import Tasks API - Bulk create tasks from CSV/XLSX file
 * 
 * Security: Only board members with edit permission can import
 * Features:
 * - CSV and XLSX file support
 * - Auto-create lists if not exist
 * - Assign users by email (if board member)
 * - Attach labels by name (create if not exist)
 * - Detailed error reporting
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

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Validate CSRF token only for POST requests (not for GET sample download)
if ($method === 'POST') {
    validateCSRFToken();
}

// Expected column headers (exact match required)
// Note: Priority is NOT included as it's calculated automatically based on due date
define('EXPECTED_HEADERS', [
    'Task Title',
    'Task Description',
    'Task List Name',
    'Start Date',
    'Due Date',
    'Assignee Email',
    'Labels'
]);

// Maximum file size (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed file types
define('ALLOWED_EXTENSIONS', ['csv', 'xlsx']);
define('ALLOWED_MIME_TYPES', [
    'text/csv',
    'text/plain',
    'application/csv',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
]);

// Note: Uses canEditBoard() from functions.php which allows owner, admin, member roles

/**
 * Get or create a list by name
 */
function getOrCreateList($conn, $boardId, $listName, $userId) {
    $listName = trim((string)($listName ?? ''));
    if (empty($listName)) {
        return null;
    }
    
    // Check if list exists
    $stmt = $conn->prepare("SELECT id FROM lists WHERE board_id = ? AND title = ? AND is_archived = 0");
    $stmt->bind_param("is", $boardId, $listName);
    $stmt->execute();
    $list = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($list) {
        return $list['id'];
    }
    
    // Create new list
    $stmt = $conn->prepare("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM lists WHERE board_id = ?");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $position = $stmt->get_result()->fetch_assoc()['next_pos'];
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO lists (board_id, title, position) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $boardId, $listName, $position);
    
    if ($stmt->execute()) {
        $listId = $conn->insert_id;
        $stmt->close();
        
        // Log activity
        logActivity($conn, $boardId, $userId, 'list_created', "created list \"$listName\" via import");
        
        return $listId;
    }
    
    $stmt->close();
    return null;
}

/**
 * Get user ID by email if they are a board member
 */
function getBoardMemberByEmail($conn, $boardId, $email) {
    $email = trim(strtolower((string)($email ?? '')));
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    
    $stmt = $conn->prepare("
        SELECT u.id, u.name 
        FROM users u 
        INNER JOIN board_members bm ON u.id = bm.user_id 
        WHERE bm.board_id = ? AND LOWER(u.email) = ?
    ");
    $stmt->bind_param("is", $boardId, $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $user;
}

/**
 * Get or create a label by name
 */
function getOrCreateLabel($conn, $boardId, $labelName) {
    $labelName = trim((string)($labelName ?? ''));
    if (empty($labelName)) {
        return null;
    }
    
    // Check if label exists
    $stmt = $conn->prepare("SELECT id FROM labels WHERE board_id = ? AND name = ?");
    $stmt->bind_param("is", $boardId, $labelName);
    $stmt->execute();
    $label = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($label) {
        return $label['id'];
    }
    
    // Create new label with a random color
    $colors = ['#EF4444', '#F97316', '#EAB308', '#22C55E', '#06B6D4', '#3B82F6', '#8B5CF6', '#EC4899'];
    $color = $colors[array_rand($colors)];
    
    $stmt = $conn->prepare("INSERT INTO labels (board_id, name, color) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $boardId, $labelName, $color);
    
    if ($stmt->execute()) {
        $labelId = $conn->insert_id;
        $stmt->close();
        return $labelId;
    }
    
    $stmt->close();
    return null;
}

/**
 * Parse date string to MySQL format
 */
function parseDate($dateStr) {
    $dateStr = trim((string)($dateStr ?? ''));
    if (empty($dateStr)) {
        return null;
    }
    
    // Try various date formats
    $formats = [
        'Y-m-d',
        'd/m/Y',
        'm/d/Y',
        'd-m-Y',
        'm-d-Y',
        'Y/m/d',
        'd.m.Y',
        'M d, Y',
        'F d, Y',
        'd M Y',
        'd F Y'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateStr);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }
    
    // Try strtotime as fallback
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

/**
 * Remove BOM (Byte Order Mark) from string
 */
function removeBOM($str) {
    // UTF-8 BOM
    if (substr($str, 0, 3) === "\xEF\xBB\xBF") {
        return substr($str, 3);
    }
    // UTF-16 LE BOM
    if (substr($str, 0, 2) === "\xFF\xFE") {
        return substr($str, 2);
    }
    // UTF-16 BE BOM
    if (substr($str, 0, 2) === "\xFE\xFF") {
        return substr($str, 2);
    }
    return $str;
}

/**
 * Clean cell value - remove quotes and trim
 */
function cleanCellValue($value) {
    if ($value === null) {
        return '';
    }
    $value = trim((string)$value);
    // Remove surrounding quotes if present
    if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
        $value = substr($value, 1, -1);
    }
    return trim($value);
}

/**
 * Parse CSV file
 */
function parseCSV($filePath) {
    $rows = [];
    
    // Read entire file and remove BOM
    $content = file_get_contents($filePath);
    $content = removeBOM($content);
    
    // Write back to temp file without BOM
    $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tempFile, $content);
    
    $handle = fopen($tempFile, 'r');
    
    if ($handle === false) {
        @unlink($tempFile);
        throw new Exception('Could not open file');
    }
    
    // Detect delimiter
    $firstLine = fgets($handle);
    rewind($handle);
    
    $delimiter = ',';
    if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
        $delimiter = ';';
    } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
        $delimiter = "\t";
    }
    
    $lineNum = 0;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $lineNum++;
        // Skip completely empty rows
        if (count(array_filter($row, fn($cell) => trim((string)($cell ?? '')) !== '')) === 0) {
            continue;
        }
        // Clean each cell value
        $rows[] = array_map('cleanCellValue', $row);
    }
    
    fclose($handle);
    @unlink($tempFile);
    
    return $rows;
}

/**
 * Parse XLSX file
 */
function parseXLSX($filePath) {
    // Simple XLSX parser without external libraries
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('Could not open XLSX file');
    }
    
    // Read shared strings
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml !== false) {
        $xml = simplexml_load_string($sharedStringsXml);
        if ($xml !== false) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }
    }
    
    // Read worksheet
    $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($worksheetXml === false) {
        $zip->close();
        throw new Exception('Could not read worksheet');
    }
    
    $xml = simplexml_load_string($worksheetXml);
    if ($xml === false) {
        $zip->close();
        throw new Exception('Could not parse worksheet XML');
    }
    
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $maxCol = 0;
        
        foreach ($row->c as $cell) {
            $cellRef = (string)$cell['r'];
            preg_match('/([A-Z]+)(\d+)/', $cellRef, $matches);
            $colLetter = $matches[1];
            $colIndex = 0;
            
            // Convert column letter to index
            for ($i = 0; $i < strlen($colLetter); $i++) {
                $colIndex = $colIndex * 26 + (ord($colLetter[$i]) - ord('A') + 1);
            }
            $colIndex--; // 0-based
            
            $value = '';
            if (isset($cell['t']) && (string)$cell['t'] === 's') {
                // Shared string
                $stringIndex = (int)$cell->v;
                $value = isset($sharedStrings[$stringIndex]) ? $sharedStrings[$stringIndex] : '';
            } elseif (isset($cell->v)) {
                $value = (string)$cell->v;
            }
            
            // Pad array if needed
            while (count($rowData) < $colIndex) {
                $rowData[] = '';
            }
            $rowData[$colIndex] = trim((string)($value ?? ''));
            $maxCol = max($maxCol, $colIndex);
        }
        
        // Pad to max column
        while (count($rowData) <= $maxCol) {
            $rowData[] = '';
        }
        
        // Skip completely empty rows
        if (count(array_filter($rowData, fn($cell) => $cell !== '')) > 0) {
            $rows[] = $rowData;
        }
    }
    
    $zip->close();
    return $rows;
}

/**
 * Normalize header string for comparison
 */
function normalizeHeader($h) {
    $h = trim((string)($h ?? ''));
    // Remove BOM if present
    $h = removeBOM($h);
    // Remove surrounding quotes
    if (strlen($h) >= 2 && $h[0] === '"' && $h[strlen($h) - 1] === '"') {
        $h = substr($h, 1, -1);
    }
    // Remove any remaining special characters at the start
    $h = preg_replace('/^[\x00-\x1F\x7F-\xFF]+/', '', $h);
    return trim(strtolower($h));
}

/**
 * Validate headers match expected format
 */
function validateHeaders($headers) {
    $expected = EXPECTED_HEADERS;
    $errors = [];
    
    // Normalize headers
    $normalizedHeaders = array_map('normalizeHeader', $headers);
    
    $normalizedExpected = array_map(function($h) {
        return trim(strtolower($h));
    }, $expected);
    
    // Check for missing headers
    foreach ($normalizedExpected as $index => $expectedHeader) {
        if (!isset($normalizedHeaders[$index]) || $normalizedHeaders[$index] !== $expectedHeader) {
            $errors[] = "Column " . ($index + 1) . " should be '" . $expected[$index] . "'" . 
                        (isset($headers[$index]) ? " (found: '" . $headers[$index] . "')" : " (missing)");
        }
    }
    
    return $errors;
}

// ========================================
// HANDLE GET REQUEST - Download Sample File
// ========================================
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'sample') {
    $format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';
    
    // Sample data (Priority is calculated automatically based on due date)
    $headers = EXPECTED_HEADERS;
    $sampleRows = [
        ['Design Homepage', 'Create the main homepage design with hero section', 'To Do', '2025-01-01', '2025-01-15', 'member@example.com', 'Design, Frontend'],
        ['Setup Database', 'Configure MySQL database and create tables', 'In Progress', '2025-01-02', '2025-01-10', 'dev@example.com', 'Backend'],
        ['Write Documentation', 'Create user guide and API documentation', 'To Do', '2025-01-05', '2025-01-20', '', 'Documentation'],
    ];
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="planify_import_sample.csv"');
        
        $output = fopen('php://output', 'w');
        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    } else {
        // For XLSX, we'll provide CSV with xlsx extension info
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="planify_import_sample.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}

// ========================================
// HANDLE POST REQUEST - Import Tasks
// ========================================
if ($method === 'POST') {
    try {
        // Get board ID
        $boardId = filter_input(INPUT_POST, 'board_id', FILTER_VALIDATE_INT);
        
        if (!$boardId) {
            throw new Exception('Board ID is required');
        }
        
        // Check if board exists
        $stmt = $conn->prepare("SELECT id, name FROM boards WHERE id = ? AND is_archived = 0");
        $stmt->bind_param("i", $boardId);
        $stmt->execute();
        $board = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$board) {
            throw new Exception('Board not found');
        }
        
        // Check user permission (uses canEditBoard from functions.php)
        if (!canEditBoard($conn, $userId, $boardId)) {
            http_response_code(403);
            throw new Exception('You do not have permission to import tasks to this board');
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            ];
            $errorCode = $_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new Exception($uploadErrors[$errorCode] ?? 'File upload failed');
        }
        
        $file = $_FILES['import_file'];
        
        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File size exceeds maximum limit of 5MB');
        }
        
        // Validate file extension
        $fileName = $file['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, ALLOWED_EXTENSIONS)) {
            throw new Exception('Invalid file type. Only CSV and XLSX files are allowed');
        }
        
        // Parse file based on type
        $filePath = $file['tmp_name'];
        
        if ($fileExt === 'csv') {
            $rows = parseCSV($filePath);
        } else {
            $rows = parseXLSX($filePath);
        }
        
        // Check if file has data
        if (count($rows) < 2) {
            throw new Exception('File is empty or contains only headers');
        }
        
        // Validate headers
        $headers = $rows[0];
        $headerErrors = validateHeaders($headers);
        
        if (!empty($headerErrors)) {
            throw new Exception("Invalid column headers:\n" . implode("\n", $headerErrors));
        }
        
        // Process data rows
        $dataRows = array_slice($rows, 1);
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'created_lists' => [],
            'created_labels' => [],
            'warnings' => []
        ];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Cache for lists and labels
            $listCache = [];
            $labelCache = [];
            
            foreach ($dataRows as $rowIndex => $row) {
                $rowNum = $rowIndex + 2; // Account for header row and 0-based index
                
                try {
                    // Extract and validate data
                    // Note: Priority is NOT imported - it's calculated automatically based on due date
                    $taskTitle = trim((string)($row[0] ?? ''));
                    $taskDescription = trim((string)($row[1] ?? ''));
                    $listName = trim((string)($row[2] ?? ''));
                    $startDate = trim((string)($row[3] ?? ''));
                    $dueDate = trim((string)($row[4] ?? ''));
                    $assigneeEmail = trim((string)($row[5] ?? ''));
                    $labelsStr = trim((string)($row[6] ?? ''));
                    
                    // Validate required fields
                    if (empty($taskTitle)) {
                        throw new Exception("Task Title is required");
                    }
                    
                    if (empty($listName)) {
                        throw new Exception("Task List Name is required");
                    }
                    
                    // Get or create list
                    if (!isset($listCache[$listName])) {
                        $listId = getOrCreateList($conn, $boardId, $listName, $userId);
                        if (!$listId) {
                            throw new Exception("Failed to create list '$listName'");
                        }
                        $listCache[$listName] = $listId;
                        if (!in_array($listName, $results['created_lists'])) {
                            $results['created_lists'][] = $listName;
                        }
                    }
                    $listId = $listCache[$listName];
                    
                    // Parse dates
                    $parsedStartDate = parseDate($startDate);
                    $parsedDueDate = parseDate($dueDate);
                    
                    if (!empty($startDate) && !$parsedStartDate) {
                        $results['warnings'][] = "Row $rowNum: Could not parse start date '$startDate'";
                    }
                    
                    if (!empty($dueDate) && !$parsedDueDate) {
                        $results['warnings'][] = "Row $rowNum: Could not parse due date '$dueDate'";
                    }
                    
                    // Get next position for the card
                    $stmt = $conn->prepare("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM cards WHERE list_id = ?");
                    $stmt->bind_param("i", $listId);
                    $stmt->execute();
                    $position = $stmt->get_result()->fetch_assoc()['next_pos'];
                    $stmt->close();
                    
                    // Create the card (priority is calculated automatically based on due date, not stored)
                    $stmt = $conn->prepare("
                        INSERT INTO cards (list_id, title, description, position, start_date, due_date, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("ississi", 
                        $listId, 
                        $taskTitle, 
                        $taskDescription, 
                        $position, 
                        $parsedStartDate, 
                        $parsedDueDate, 
                        $userId
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to create task: " . $stmt->error);
                    }
                    
                    $cardId = $conn->insert_id;
                    $stmt->close();
                    
                    // Assign user if email provided
                    if (!empty($assigneeEmail)) {
                        $assignee = getBoardMemberByEmail($conn, $boardId, $assigneeEmail);
                        if ($assignee) {
                            $stmt = $conn->prepare("
                                INSERT INTO card_assignees (card_id, user_id, assigned_by) 
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE assigned_at = NOW()
                            ");
                            $stmt->bind_param("iii", $cardId, $assignee['id'], $userId);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            $results['warnings'][] = "Row $rowNum: User '$assigneeEmail' is not a board member, skipping assignment";
                        }
                    }
                    
                    // Add labels if provided
                    if (!empty($labelsStr)) {
                        $labelNames = array_map('trim', explode(',', $labelsStr));
                        foreach ($labelNames as $labelName) {
                            if (empty($labelName)) continue;
                            
                            if (!isset($labelCache[$labelName])) {
                                $labelId = getOrCreateLabel($conn, $boardId, $labelName);
                                $labelCache[$labelName] = $labelId;
                                if ($labelId && !in_array($labelName, $results['created_labels'])) {
                                    $results['created_labels'][] = $labelName;
                                }
                            }
                            
                            $labelId = $labelCache[$labelName];
                            if ($labelId) {
                                $stmt = $conn->prepare("
                                    INSERT INTO card_labels (card_id, label_id) 
                                    VALUES (?, ?)
                                    ON DUPLICATE KEY UPDATE card_id = card_id
                                ");
                                $stmt->bind_param("ii", $cardId, $labelId);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                    }
                    
                    // Log activity
                    logActivity($conn, $boardId, $userId, 'card_created', "imported task \"$taskTitle\"", $cardId);
                    
                    $results['success']++;
                    
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Row $rowNum: " . $e->getMessage();
                }
            }
            
            // Commit transaction if at least one task was created
            if ($results['success'] > 0) {
                $conn->commit();
            } else {
                $conn->rollback();
                throw new Exception('No tasks were imported. Please check the file format and data.');
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
        // Build response message
        $message = $results['success'] . ' task(s) imported successfully';
        if ($results['failed'] > 0) {
            $message .= ', ' . $results['failed'] . ' failed';
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $results
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Invalid request method
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);

