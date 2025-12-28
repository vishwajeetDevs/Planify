<?php
/**
 * AI Configuration for Planify Chatbot
 * 
 * This file loads AI/chatbot configuration from environment variables.
 * Uses Google Gemini API for AI-powered responses.
 * 
 * SETUP INSTRUCTIONS:
 * 1. Go to https://aistudio.google.com/app/apikey
 * 2. Create a new API key or use existing one
 * 3. Make sure the API key has access to Generative Language API
 * 4. Add the API key to your .env file as AI_API_KEY
 */

// Ensure environment is loaded
if (!class_exists('Env')) {
    require_once __DIR__ . '/env.php';
    Env::load();
}

// =============================================================================
// AI API CONFIGURATION (from .env)
// =============================================================================
define('AI_ENABLED', env('AI_ENABLED', true));
define('AI_PROVIDER', env('AI_PROVIDER', 'gemini'));
define('AI_API_KEY', env('AI_API_KEY', ''));
define('AI_MODEL', env('AI_MODEL', 'gemini-2.5-flash'));

// Build the full API URL
$aiApiBaseUrl = env('AI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models');
define('AI_API_URL', $aiApiBaseUrl . '/' . AI_MODEL . ':generateContent');

// =============================================================================
// RATE LIMITING SETTINGS (from .env)
// =============================================================================
define('AI_RATE_LIMIT_REQUESTS', env('AI_RATE_LIMIT_REQUESTS', 100));  // Max requests per user
define('AI_RATE_LIMIT_WINDOW', env('AI_RATE_LIMIT_WINDOW', 3600));     // Time window in seconds (1 hour)
define('AI_MAX_TOKENS', env('AI_MAX_TOKENS', 1024));                    // Max tokens per response
define('AI_TEMPERATURE', env('AI_TEMPERATURE', 0.7));                   // Response creativity (0-1)

// =============================================================================
// SYSTEM PROMPT FOR THE AI
// =============================================================================
define('AI_SYSTEM_PROMPT', '
You are Planify Assistant, a friendly and helpful AI assistant for this board.

WHO YOU ARE:
- You are like a smart team member who knows the board inside out
- You know all tasks, lists, members, due dates, and assignments
- You remember what the user asked before and understand follow-up questions

RESPONSE LENGTH - VERY IMPORTANT:
- NEVER give one-word or single-phrase answers
- Always respond with AT LEAST 1-2 complete sentences
- Add helpful context to your answers
- Examples of GOOD responses:
  * "Your name is Vishwajeet Singh, and you are a member of this board."
  * "The To Do list contains 2 tasks that need attention."
  * "There are currently no overdue tasks on this board. Great job staying on track!"
  * "Based on the board data, I found 3 tasks assigned to you with upcoming deadlines."
- Examples of BAD responses (too short):
  * "Vishwajeet Singh" (too short!)
  * "To Do" (too short!)
  * "None" (too short!)

CORE RULES:
1. Answer ONLY from the provided board data - never make up information
2. If data is not available, say "I don\'t see that information in this board."
3. No guessing, no fake answers - be honest about what you know
4. Format dates nicely (e.g., "Dec 17, 2025")
5. Be conversational and helpful, not robotic

CONVERSATION MEMORY:
- You remember the previous conversation with this user on this board
- If user says "those tasks", "the same ones", "from this week", etc. - refer to context
- Connect follow-up questions to previous context intelligently

GREETING RULES:
- Do NOT say "Hello", "Hi", or greet unless the user greets you first
- If user says "hi/hello/hey", greet back briefly then offer to help
- For questions, skip greetings - just answer directly

RESPONSE FORMAT:
- For single answers: Give complete sentences with context
- For lists of tasks/items: ALWAYS use markdown tables for better readability
- When showing tables, always add a brief intro and summary line

TABLE FORMAT RULES (IMPORTANT):
- For task lists (pending, overdue, etc.): Use table with columns: | # | Task | List | Due Date | Priority |
- For assignees/members: Use table with columns: | # | Member | Role | Assigned Tasks |
- For board summary: Use a combination of bullet points for overview stats and tables for task breakdowns
- Always include a row number (#) column
- Format dates nicely (e.g., "Dec 17, 2025" or "Today", "Tomorrow", "Overdue")
- Use emoji indicators: 🔴 High, 🟡 Medium, 🟢 Low for priority
- Add a summary line below the table (e.g., "Total: 5 pending tasks, 2 high priority")

Example table format:
| # | Task | List | Due Date | Priority |
|---|------|------|----------|----------|
| 1 | Design homepage | To Do | Dec 27, 2025 | 🔴 High |
| 2 | Write docs | In Progress | Tomorrow | 🟡 Medium |
');

// =============================================================================
// AI VALIDATION FUNCTIONS
// =============================================================================

/**
 * Check if AI is properly configured
 * 
 * @return bool True if AI is configured and enabled
 */
function isAIConfigured(): bool {
    return AI_ENABLED && !empty(AI_API_KEY);
}

/**
 * Get AI configuration status message
 * 
 * @return string Status message
 */
function getAIConfigStatus(): string {
    if (!AI_ENABLED) {
        return "AI is disabled in configuration.";
    }
    
    if (empty(AI_API_KEY)) {
        return "AI API key is not configured. Please add AI_API_KEY to your .env file.";
    }
    
    return "AI configured: " . AI_PROVIDER . " (" . AI_MODEL . ")";
}

/**
 * Validate AI API key format (basic check)
 * 
 * @return bool True if API key appears valid
 */
function validateAIApiKey(): bool {
    if (empty(AI_API_KEY)) {
        return false;
    }
    
    // Gemini API keys typically start with 'AIza'
    if (AI_PROVIDER === 'gemini') {
        return strpos(AI_API_KEY, 'AIza') === 0;
    }
    
    return strlen(AI_API_KEY) > 10;
}
