<?php
/**
 * ID Encryption Helper for Planify
 * 
 * Provides secure encryption and decryption of database IDs for URLs.
 * Uses AES-128-CBC for shorter output while maintaining security.
 * 
 * Usage:
 *   require_once 'helpers/IdEncrypt.php';
 *   
 *   // Encrypt an ID for URL
 *   $ref = encryptId(123);
 *   // Result: Short URL-safe string like "a1B2c3D4e5F6g7H8"
 *   
 *   // Decrypt an ID from URL
 *   $id = decryptId($ref);
 *   // Result: 123 (integer) or false on failure
 */

// Ensure environment is loaded
if (!class_exists('Env')) {
    require_once __DIR__ . '/../config/env.php';
    Env::load();
}

/**
 * Get the encryption key from environment
 * 
 * @return string The binary encryption key (16 bytes for AES-128)
 */
function getEncryptionKey(): string {
    static $key = null;
    
    if ($key !== null) {
        return $key;
    }
    
    $appKey = Env::get('APP_KEY', '');
    
    if (empty($appKey)) {
        // In production, fail hard instead of using fallback
        if (Env::isProduction()) {
            throw new RuntimeException('CRITICAL: APP_KEY must be set in production environment. Generate one with: php -r "echo base64_encode(random_bytes(32));"');
        }
        // Development fallback with clear warning
        error_log('CRITICAL: APP_KEY not set. Using insecure fallback key. DO NOT USE IN PRODUCTION!');
        $appKey = 'planify_dev_fallback_' . php_uname('n') . '_unsafe';
    }
    
    // Handle base64 encoded keys
    if (str_starts_with($appKey, 'base64:')) {
        $decoded = base64_decode(substr($appKey, 7));
        $key = substr($decoded, 0, 16); // Use first 16 bytes for AES-128
    } else {
        // Use MD5 hash for consistent 16-byte key
        $key = md5($appKey, true);
    }
    
    return $key;
}

/**
 * Encrypt an ID for use in URLs - produces short output
 * 
 * @param int|string $id The database ID to encrypt
 * @return string URL-safe encrypted string (typically 22-32 chars)
 */
function encryptId(int|string $id): string {
    $id = (int) $id;
    
    if ($id <= 0) {
        return '';
    }
    
    $key = getEncryptionKey();
    
    // Pack ID as unsigned 32-bit integer (4 bytes)
    // Add 4 random bytes for salt (prevents same ID = same output)
    // Add 2-byte checksum for validation
    $salt = random_bytes(4);
    $data = pack('N', $id) . $salt;
    $checksum = substr(hash('crc32b', $data, true), 0, 2);
    $payload = $data . $checksum; // 10 bytes total
    
    // Use AES-128-ECB for shortest output (no IV needed)
    // ECB is safe here because each ID+salt is unique and short
    $encrypted = openssl_encrypt($payload, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
    
    if ($encrypted === false) {
        return '';
    }
    
    // URL-safe base64 encoding
    return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
}

/**
 * Decrypt an ID from a URL parameter
 * 
 * @param string $encrypted The encrypted string from URL
 * @return int|false The decrypted ID or false on failure
 */
function decryptId(string $encrypted): int|false {
    if (empty($encrypted) || strlen($encrypted) < 10) {
        return false;
    }
    
    try {
        $key = getEncryptionKey();
        
        // Decode URL-safe base64
        $remainder = strlen($encrypted) % 4;
        if ($remainder) {
            $encrypted .= str_repeat('=', 4 - $remainder);
        }
        $data = base64_decode(strtr($encrypted, '-_', '+/'));
        
        if ($data === false) {
            return false;
        }
        
        // Decrypt
        $decrypted = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        
        if ($decrypted === false || strlen($decrypted) < 10) {
            return false;
        }
        
        // Extract components
        $idData = substr($decrypted, 0, 4);
        $salt = substr($decrypted, 4, 4);
        $checksum = substr($decrypted, 8, 2);
        
        // Verify checksum
        $expectedChecksum = substr(hash('crc32b', $idData . $salt, true), 0, 2);
        if (!hash_equals($checksum, $expectedChecksum)) {
            return false;
        }
        
        // Unpack ID
        $unpacked = unpack('N', $idData);
        if (!$unpacked) {
            return false;
        }
        
        $id = $unpacked[1];
        
        // Validate ID is positive
        if ($id <= 0) {
            return false;
        }
        
        return $id;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Compact token: board + task (+ optional list) in one query param (~22 chars).
 *
 * @return array{board: int, card: int, list: int}|false
 */
function decryptOpenToken(string $encrypted): array|false {
    if ($encrypted === '' || strlen($encrypted) < 10) {
        return false;
    }

    try {
        $key = getEncryptionKey();
        $remainder = strlen($encrypted) % 4;
        if ($remainder) {
            $encrypted .= str_repeat('=', 4 - $remainder);
        }
        $data = base64_decode(strtr($encrypted, '-_', '+/'));
        if ($data === false) {
            return false;
        }

        $decrypted = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        if ($decrypted === false || strlen($decrypted) !== 16) {
            return false;
        }

        $body = substr($decrypted, 0, 14);
        $checksum = substr($decrypted, 14, 2);
        $expectedChecksum = substr(hash('crc32b', $body, true), 0, 2);
        if (!hash_equals($checksum, $expectedChecksum)) {
            return false;
        }

        $unpacked = unpack('Nboard/Ncard/Nlist', substr($body, 0, 12));
        if (!$unpacked) {
            return false;
        }

        $board = (int) $unpacked['board'];
        $card = (int) $unpacked['card'];
        $list = (int) $unpacked['list'];
        if ($board <= 0) {
            return false;
        }

        return ['board' => $board, 'card' => $card, 'list' => $list];
    } catch (Exception $e) {
        return false;
    }
}

/**
 * @param int|null $listId Pass 0 or null to omit list (derived from task when opening).
 */
function encryptOpenToken(int $boardId, int $cardId, ?int $listId = null): string {
    $boardId = (int) $boardId;
    $cardId = (int) $cardId;
    $listId = $listId !== null && $listId > 0 ? (int) $listId : 0;

    if ($boardId <= 0 || $cardId <= 0) {
        return '';
    }

    $key = getEncryptionKey();
    $salt = random_bytes(2);
    $data = pack('NNN', $boardId, $cardId, $listId) . $salt;
    $checksum = substr(hash('crc32b', $data, true), 0, 2);
    $payload = $data . $checksum;

    $encrypted = openssl_encrypt($payload, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
    if ($encrypted === false) {
        return '';
    }

    return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
}

/**
 * Create an encrypted URL for a resource
 * 
 * @param string $page The page name (e.g., 'board.php', 'workspace.php')
 * @param int $id The database ID
 * @param array $extraParams Additional URL parameters
 * @return string The complete URL with encrypted ID
 */
function encryptedUrl(string $page, int $id, array $extraParams = []): string {
    $params = ['ref' => encryptId($id)];
    $params = array_merge($params, $extraParams);
    
    return $page . '?' . http_build_query($params);
}

/**
 * Query parameters for deep-linking to a board and optional task/list.
 *
 * @return array<string, int|string>
 */
function boardDeepLinkQuery(int $boardId, ?int $cardId = null, ?int $listId = null): array {
    if ($cardId !== null && $cardId > 0) {
        return ['o' => encryptOpenToken($boardId, $cardId, null)];
    }
    if ($listId !== null && $listId > 0) {
        return ['ref' => encryptId($boardId), 'l' => encryptId($listId)];
    }
    return ['ref' => encryptId($boardId)];
}

/**
 * Decrypt a query parameter (e.g. c, card, l, list) with fallback to plain integer IDs.
 */
function getDecryptedQueryInt(string ...$paramNames): int|false {
    foreach ($paramNames as $paramName) {
        if (!isset($_GET[$paramName]) || $_GET[$paramName] === '') {
            continue;
        }

        $raw = (string) $_GET[$paramName];
        $decrypted = decryptId($raw);
        if ($decrypted !== false) {
            return $decrypted;
        }

        $plain = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($plain !== false) {
            return (int) $plain;
        }
    }

    return false;
}

/**
 * Absolute public URL to a board page (optionally opening a task).
 */
function taskPageUrl(int $boardId, int $cardId, ?int $listId = null): string {
    if (!defined('APP_URL')) {
        require_once __DIR__ . '/../config/env.php';
        Env::load();
        $appUrl = rtrim(planify_resolve_app_urls()['APP_URL'], '/');
    } else {
        $appUrl = rtrim(APP_URL, '/');
    }

    $query = boardDeepLinkQuery($boardId, $cardId, $listId);
    return $appUrl . '/public/board.php?' . http_build_query($query);
}

/**
 * Relative path (includes BASE_PATH) to the board page for in-app navigation.
 */
function boardPageHref(int $boardId, ?int $cardId = null, ?int $listId = null, array $extra = []): string {
    if (!defined('BASE_PATH')) {
        require_once __DIR__ . '/../config/env.php';
        Env::load();
        $basePath = planify_resolve_app_urls()['BASE_PATH'];
    } else {
        $basePath = BASE_PATH;
    }

    $query = array_merge(boardDeepLinkQuery($boardId, $cardId, $listId), $extra);
    return rtrim($basePath, '/') . '/public/board.php?' . http_build_query($query);
}

/**
 * Get decrypted ID from request with validation
 * 
 * @param string $paramName The URL parameter name (default: 'ref')
 * @return int|false The decrypted ID or false if invalid/missing
 */
function getDecryptedId(string $paramName = 'ref'): int|false {
    // First try encrypted 'ref' parameter
    if (isset($_GET[$paramName]) && !empty($_GET[$paramName])) {
        $decrypted = decryptId($_GET[$paramName]);
        if ($decrypted !== false) {
            return $decrypted;
        }
    }
    
    // Backward compatibility: check for plain 'id' parameter
    if (isset($_GET['id'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        return $id !== false && $id !== null ? $id : false;
    }
    
    return false;
}

/**
 * Show an error page for invalid/unauthorized access
 * 
 * @param string $message Error message to display
 * @param string|null $redirectUrl URL to redirect to (optional)
 * @return never
 */
function showInvalidAccessError(string $message = 'Invalid or unauthorized access', ?string $redirectUrl = null): never {
    http_response_code(403);
    
    // AJAX request - return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    // Regular request - show HTML error
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Planify</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mb-2">Access Denied</h1>
            <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($message); ?></p>
            <a href="<?php echo htmlspecialchars($redirectUrl ?? 'dashboard.php'); ?>" 
               class="inline-flex items-center px-4 py-2 bg-neutral-900 text-white font-medium rounded-lg hover:bg-neutral-800 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Go to Dashboard
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

