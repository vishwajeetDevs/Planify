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
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors">
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

