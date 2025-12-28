<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config/db.php';

// Check database connection
function checkDatabaseConnection() {
    global $conn;
    
    echo "<h2>Database Connection Test</h2>";
    
    if ($conn->connect_error) {
        die("<p style='color: red;'>Connection failed: " . $conn->connect_error . "</p>");
    } else {
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
    }
    
    // Check if database exists
    $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'planify'");
    if ($result->num_rows === 0) {
        die("<p style='color: red;'>❌ Database 'planify' does not exist. Please import the database from database.sql</p>");
    } else {
        echo "<p style='color: green;'>✅ Database 'planify' exists</p>";
    }
}

// Check if tables exist
function checkTables() {
    global $conn;
    
    echo "<h2>Checking Required Tables</h2>";
    
    $tables = ['users', 'boards', 'lists', 'cards', 'comments', 'activities'];
    $missingTables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missingTables[] = $table;
            echo "<p style='color: red;'>❌ Table '$table' is missing</p>";
        } else {
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
        }
    }
    
    if (!empty($missingTables)) {
        echo "<div style='background: #ffebee; padding: 10px; border-left: 4px solid #f44336; margin: 10px 0;'>";
        echo "<h3>Missing Tables Detected</h3>";
        echo "<p>The following tables are missing from your database. Please import the database schema from <code>database.sql</code> or run the following SQL commands:</p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;'>";
        
        // Generate CREATE TABLE statements for missing tables
        if (in_array('comments', $missingTables)) {
            echo "-- Create comments table\n";
            echo "CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `card_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `card_id` (`card_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        }
        
        if (in_array('activities', $missingTables)) {
            echo "-- Create activities table\n";
            echo "CREATE TABLE IF NOT EXISTS `activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `board_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `card_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `board_id` (`board_id`),
  KEY `user_id` (`user_id`),
  KEY `card_id` (`card_id`),
  CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activities_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activities_ibfk_3` FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        }
        
        echo "</pre>";
        echo "<p>You can run these SQL commands in phpMyAdmin or using the MySQL command line.</p>";
        echo "</div>";
    }
}

// Check database permissions
function checkPermissions() {
    global $conn;
    
    echo "<h2>Checking Database Permissions</h2>";
    
    // Check if user has SELECT permission on comments table
    $result = $conn->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'planify' AND TABLE_NAME = 'comments'");
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        try {
            $test = $conn->query("SELECT 1 FROM comments LIMIT 1");
            echo "<p style='color: green;'>✅ User has SELECT permission on comments table</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ User does not have SELECT permission on comments table: " . $conn->error . "</p>";
        }
    }
    
    // Check if user has SELECT permission on activities table
    $result = $conn->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'planify' AND TABLE_NAME = 'activities'");
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        try {
            $test = $conn->query("SELECT 1 FROM activities LIMIT 1");
            echo "<p style='color: green;'>✅ User has SELECT permission on activities table</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ User does not have SELECT permission on activities table: " . $conn->error . "</p>";
        }
    }
}

// Check PHP version
function checkPhpVersion() {
    echo "<h2>PHP Version Check</h2>";
    $requiredVersion = '7.4.0';
    $currentVersion = phpversion();
    
    if (version_compare($currentVersion, $requiredVersion, '>=')) {
        echo "<p style='color: green;'>✅ PHP version $currentVersion is compatible (>= $requiredVersion required)</p>";
    } else {
        echo "<p style='color: red;'>❌ PHP version $currentVersion is not compatible. Required: >= $requiredVersion</p>";
    }
}

// Run all checks
?>
<!DOCTYPE html>
<html>
<head>
    <title>Planify Database Check</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
        h1 { color: #333; }
        h2 { color: #444; margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Planify Database Check</h1>
    
    <?php
    checkPhpVersion();
    checkDatabaseConnection();
    checkTables();
    checkPermissions();
    ?>
    
    <h2>Next Steps</h2>
    <ul>
        <li>If you see any red error messages, please fix the issues mentioned above.</li>
        <li>Make sure the database user has proper permissions to access and modify the database.</li>
        <li>If tables are missing, import the <code>database.sql</code> file or run the SQL commands provided above.</li>
        <li>Check your web server's error log for any additional error messages.</li>
    </ul>
</body>
</html>
