<?php
/**
 * Environment Variable Loader for Planify
 * 
 * This class loads environment variables from a .env file and provides
 * secure access to configuration values throughout the application.
 * 
 * Usage:
 *   Env::load();           // Load .env file (call once at app start)
 *   Env::get('DB_HOST');   // Get a value (returns null if not found)
 *   Env::get('DB_HOST', 'localhost');  // Get with default value
 *   Env::require('DB_HOST');  // Get required value (throws exception if missing)
 */

class Env
{
    private static bool $loaded = false;
    private static array $variables = [];
    private static array $requiredVars = [
        'APP_ENV',
        'APP_URL',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'APP_KEY'
    ];

    /**
     * Load environment variables from .env file
     * 
     * @param string|null $path Path to .env file (defaults to project root)
     * @return void
     */
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $envPath = $path ?? dirname(__DIR__) . '/.env';

        if (!file_exists($envPath)) {
            // In production, environment variables might be set at server level
            // Log warning but don't fail
            error_log("Warning: .env file not found at {$envPath}. Using server environment variables.");
            self::loadFromServer();
            self::$loaded = true;
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = self::parseValue(trim($value));

                self::$variables[$key] = $value;

                // Also set in $_ENV and putenv for compatibility
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        self::$loaded = true;
    }

    /**
     * Load variables from server environment (for production)
     */
    private static function loadFromServer(): void
    {
        foreach ($_ENV as $key => $value) {
            self::$variables[$key] = $value;
        }

        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && !isset(self::$variables[$key])) {
                self::$variables[$key] = $value;
            }
        }
    }

    /**
     * Parse a value, handling quotes and special values
     * 
     * @param string $value Raw value from .env file
     * @return mixed Parsed value
     */
    private static function parseValue(string $value): mixed
    {
        // Remove surrounding quotes
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        // Handle special values
        $lowerValue = strtolower($value);
        
        if ($lowerValue === 'true' || $lowerValue === '(true)') {
            return true;
        }
        
        if ($lowerValue === 'false' || $lowerValue === '(false)') {
            return false;
        }
        
        if ($lowerValue === 'null' || $lowerValue === '(null)') {
            return null;
        }
        
        if ($lowerValue === 'empty' || $lowerValue === '(empty)') {
            return '';
        }

        // Handle numeric values
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }

        return $value;
    }

    /**
     * Get an environment variable
     * 
     * @param string $key Variable name
     * @param mixed $default Default value if not found
     * @return mixed Variable value or default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load();
        }

        // Check our loaded variables first
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        // Fall back to getenv() for server-level variables
        $value = getenv($key);
        if ($value !== false) {
            return self::parseValue($value);
        }

        // Check $_ENV
        if (isset($_ENV[$key])) {
            return self::parseValue($_ENV[$key]);
        }

        return $default;
    }

    /**
     * Get a required environment variable (throws exception if missing)
     * 
     * @param string $key Variable name
     * @return mixed Variable value
     * @throws RuntimeException If variable is not set
     */
    public static function require(string $key): mixed
    {
        $value = self::get($key);

        if ($value === null) {
            throw new RuntimeException(
                "Required environment variable '{$key}' is not set. " .
                "Please check your .env file or server configuration."
            );
        }

        return $value;
    }

    /**
     * Check if an environment variable exists
     * 
     * @param string $key Variable name
     * @return bool True if variable exists
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Validate that all required environment variables are set
     * 
     * @param array|null $required List of required variable names (uses default if null)
     * @return array List of missing variables (empty if all present)
     */
    public static function validate(?array $required = null): array
    {
        if (!self::$loaded) {
            self::load();
        }

        $required = $required ?? self::$requiredVars;
        $missing = [];

        foreach ($required as $key) {
            if (!self::has($key)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Validate and throw exception if any required variables are missing
     * 
     * @param array|null $required List of required variable names
     * @throws RuntimeException If any required variables are missing
     */
    public static function validateOrFail(?array $required = null): void
    {
        $missing = self::validate($required);

        if (!empty($missing)) {
            throw new RuntimeException(
                "Missing required environment variables: " . implode(', ', $missing) . ". " .
                "Please check your .env file."
            );
        }
    }

    /**
     * Get the current environment (development, production, testing)
     * 
     * @return string Environment name
     */
    public static function environment(): string
    {
        return self::get('APP_ENV', 'production');
    }

    /**
     * Check if running in development environment
     * 
     * @return bool True if development
     */
    public static function isDevelopment(): bool
    {
        return in_array(self::environment(), ['development', 'dev', 'local']);
    }

    /**
     * Check if running in production environment
     * 
     * @return bool True if production
     */
    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    /**
     * Check if debug mode is enabled
     * 
     * @return bool True if debug mode is on
     */
    public static function isDebug(): bool
    {
        return self::get('APP_DEBUG', false) === true;
    }

    /**
     * Get all loaded environment variables (for debugging only)
     * WARNING: Do not expose this in production!
     * 
     * @return array All loaded variables
     */
    public static function all(): array
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$variables;
    }

    /**
     * Generate a secure random key for APP_KEY
     * 
     * @param int $length Key length in bytes (default 32)
     * @return string Base64 encoded key
     */
    public static function generateKey(int $length = 32): string
    {
        return 'base64:' . base64_encode(random_bytes($length));
    }
}

/**
 * Helper function to get environment variable
 * 
 * @param string $key Variable name
 * @param mixed $default Default value
 * @return mixed Variable value or default
 */
function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

