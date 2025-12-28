<?php
/**
 * AI Text Improvement API Endpoint
 * 
 * Improves user text to be more professional, clear, and grammatically correct.
 * Features:
 * - Rephrases sentences professionally
 * - Fixes grammar, spelling, and punctuation
 * - Maintains original meaning and intent
 * - Rate limited to prevent abuse
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress errors in output
error_reporting(0);
ini_set('display_errors', 0);

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
    echo json_encode(['success' => false, 'message' => 'Please login to use the writing assistant']);
    exit;
}

$userId = $_SESSION['user_id'];

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$text = trim($input['text'] ?? '');
$context = trim($input['context'] ?? 'general'); // task_title, task_description, comment, note, general

// Validate input
if (empty($text)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Text is required']);
    exit;
}

// Minimum text length check
if (strlen($text) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Text is too short to improve']);
    exit;
}

// Maximum text length check (prevent abuse)
if (strlen($text) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Text is too long. Maximum 5000 characters allowed.']);
    exit;
}

// Check rate limiting (using a separate counter for text improvements)
if (!checkTextImprovementRateLimit($conn, $userId)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a moment before trying again.']);
    exit;
}

try {
    // Check if AI is configured
    if (!isAIConfigured()) {
        echo json_encode([
            'success' => false,
            'message' => 'AI is not configured. Please add your Gemini API key in the .env file.'
        ]);
        exit;
    }
    
    // Call AI to improve the text
    $result = improveTextWithAI($text, $context);
    
    if ($result['success']) {
        // Log the request for rate limiting
        logTextImprovementRequest($conn, $userId);
        
        echo json_encode([
            'success' => true,
            'original' => $text,
            'improved' => $result['improved_text']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to improve text. Please try again.'
        ]);
    }
} catch (Exception $e) {
    error_log("AI Text Improvement Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

// ============================================================
// RATE LIMITING FUNCTIONS
// ============================================================

/**
 * Check if user has exceeded rate limit for text improvements
 * More restrictive than chat rate limits (30 requests per 10 minutes)
 */
function checkTextImprovementRateLimit($conn, $userId) {
    $windowStart = date('Y-m-d H:i:s', time() - 600); // 10 minute window
    $maxRequests = 30; // Max 30 improvements per 10 minutes
    
    // Create table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS ai_text_improvement_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_time (user_id, created_at)
        )
    ");
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM ai_text_improvement_logs 
        WHERE user_id = ? AND created_at > ?
    ");
    
    if (!$stmt) {
        return true; // Allow if table doesn't exist yet
    }
    
    $stmt->bind_param("is", $userId, $windowStart);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return ($result['count'] ?? 0) < $maxRequests;
}

/**
 * Log text improvement request for rate limiting
 */
function logTextImprovementRequest($conn, $userId) {
    $stmt = $conn->prepare("INSERT INTO ai_text_improvement_logs (user_id) VALUES (?)");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }
    
    // Clean up old logs (keep only last 24 hours)
    $conn->query("DELETE FROM ai_text_improvement_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

// ============================================================
// AI TEXT IMPROVEMENT FUNCTION
// ============================================================

/**
 * Call Gemini AI to improve the text with retry logic
 */
function improveTextWithAI($text, $context = 'general', $retryCount = 0) {
    $maxRetries = 2;
    $url = AI_API_URL . '?key=' . AI_API_KEY;
    
    // Context-specific instructions
    $contextInstructions = [
        'task_title' => 'This is a task/todo title. Keep it concise but clear.',
        'task_description' => 'This is a task description. Make it clear and actionable.',
        'comment' => 'This is a comment on a task. Keep the tone conversational but professional.',
        'note' => 'This is a personal note. Maintain the original style but improve clarity.',
        'general' => 'This is general text. Improve it while maintaining its purpose.'
    ];
    
    $contextHint = $contextInstructions[$context] ?? $contextInstructions['general'];
    
    // Build the prompt
    $prompt = "You are a professional writing assistant. Your task is to improve the following text to make it more professional, clear, and grammatically correct.

IMPORTANT RULES:
1. Keep the SAME meaning and intent as the original
2. Fix any grammar, spelling, and punctuation errors
3. Improve clarity and readability
4. Use professional but natural language
5. Keep the length similar - don't add unnecessary words
6. Do NOT add any extra information that wasn't in the original
7. Do NOT remove important details from the original
8. Return ONLY the improved text - no explanations, no quotes, no formatting markers

CONTEXT: {$contextHint}

ORIGINAL TEXT:
{$text}

IMPROVED TEXT:";

    $requestBody = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3, // Lower temperature for more consistent output
            'maxOutputTokens' => 1024,
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
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestBody),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15, // Shorter timeout for quick responses
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Gemini API cURL Error: " . $error);
        if (strpos($error, 'resolve') !== false || strpos($error, 'connect') !== false || strpos($error, 'timed out') !== false) {
            return ['success' => false, 'error' => 'AI service is not available. Please try again later.'];
        }
        return ['success' => false, 'error' => 'Connection error. Please try again.'];
    }
    
    if (empty($response) && $httpCode === 0) {
        return ['success' => false, 'error' => 'AI service is not available.'];
    }
    
    if ($httpCode !== 200) {
        error_log("Gemini API HTTP Error: " . $httpCode . " - " . $response);
        $errorData = json_decode($response, true);
        
        if ($httpCode === 429 || (isset($errorData['error']['message']) && (
            strpos($errorData['error']['message'], 'quota') !== false ||
            strpos($errorData['error']['message'], 'rate') !== false ||
            strpos($errorData['error']['message'], 'limit') !== false ||
            strpos($errorData['error']['message'], 'RESOURCE_EXHAUSTED') !== false
        ))) {
            // Get retry delay from response (default to 30 seconds)
            $retryDelay = 30;
            if (isset($errorData['error']['details'])) {
                foreach ($errorData['error']['details'] as $detail) {
                    if (isset($detail['retryDelay'])) {
                        // Parse "22s" to integer 22
                        $retryDelay = intval($detail['retryDelay']);
                        break;
                    }
                }
            }
            
            // Only retry once with the proper delay (max 45 seconds wait)
            if ($retryCount < 1 && $retryDelay <= 45) {
                sleep($retryDelay);
                return improveTextWithAI($text, $context, $retryCount + 1);
            }
            return ['success' => false, 'error' => 'AI is temporarily busy. Please try again in ' . $retryDelay . ' seconds.'];
        }
        
        if ($httpCode === 404 || (isset($errorData['error']['message']) && strpos($errorData['error']['message'], 'not found') !== false)) {
            return ['success' => false, 'error' => 'AI model not available. Please check your configuration.'];
        }
        
        return ['success' => false, 'error' => 'AI service temporarily unavailable. Please try again in a moment.'];
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Gemini API Invalid Response: " . $response);
        return ['success' => false, 'error' => 'Invalid response from AI. Please try again.'];
    }
    
    $improvedText = trim($data['candidates'][0]['content']['parts'][0]['text']);
    
    // Clean up the response - remove any quotes or markdown that AI might have added
    $improvedText = preg_replace('/^["\']|["\']$/', '', $improvedText);
    $improvedText = preg_replace('/^```[\s\S]*?\n|```$/m', '', $improvedText);
    $improvedText = trim($improvedText);
    
    // If AI returned empty or too short text, return original
    if (strlen($improvedText) < 2) {
        return ['success' => false, 'error' => 'Could not improve the text. Please try again.'];
    }
    
    return [
        'success' => true,
        'improved_text' => $improvedText
    ];
}

