<?php

// Check if running in CLI
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

function get_env_var($key, $default = null) {
    return getenv($key) ?: ($_ENV[$key] ?? ($_SERVER[$key] ?? $default));
}

// Configuration
$is_docker = file_exists('/.dockerenv');
$host = get_env_var('DB_HOST', $is_docker ? 'db' : '127.0.0.1');
$name = get_env_var('DB_NAME', 'hellosil_res');
$user = get_env_var('DB_USER', $is_docker ? 'root' : 'hellosil_res');
$pass = get_env_var('DB_PASS', $is_docker ? 'root' : 'Donjazzy123?');

// Retry connection logic - connect without database first
$max_retries = 30;
$retry_delay = 2; // seconds
$pdo = null;

echo "Starting database migration...\n";
echo "Host: $host, Database: $name, User: $user\n";

for ($i = 0; $i < $max_retries; $i++) {
    try {
        // First connect without specifying the database
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        echo "Database connection successful.\n";
        break;
    } catch (PDOException $e) {
        echo "Connection failed (attempt " . ($i + 1) . "/$max_retries): " . $e->getMessage() . "\n";
        sleep($retry_delay);
    }
}

if (!$pdo) {
    echo "Could not connect to database after $max_retries attempts. Exiting.\n";
    exit(1);
}

// Migration logic
try {
    // Check if database exists, create if not
    $stmt = $pdo->query("SHOW DATABASES LIKE '$name'");
    if ($stmt->rowCount() == 0) {
        echo "Database '$name' not found. Creating...\n";
        $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        echo "Database '$name' created.\n";
    }

    // Select the database
    $pdo->exec("USE `$name`");

    // Check if users table exists (critical table for the app)
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "Table 'users' not found. Importing full schema...\n";
        
        // Read the SQL file
        $sqlFile = __DIR__ . '/../db_init.sql';
        if (!file_exists($sqlFile)) {
            echo "ERROR: db_init.sql not found at $sqlFile\n";
            exit(1);
        }
        
        echo "Reading SQL file: $sqlFile\n";
        $sql = file_get_contents($sqlFile);
        echo "SQL file size: " . strlen($sql) . " bytes\n";
        
        // Remove comments and split by semicolons, but be careful with string content
        // First, remove single-line comments
        $sql = preg_replace('/^--.*$/m', '', $sql);
        // Remove multi-line comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split into statements by semicolon followed by newline
        $statements = preg_split('/;\s*[\r\n]+/', $sql);
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            
            // Skip empty statements and certain control statements
            if (empty($statement)) continue;
            if (preg_match('/^(SET|START TRANSACTION|COMMIT|\/\*!)/i', $statement)) {
                // These are usually safe to execute
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Ignore errors on SET statements
                }
                continue;
            }
            
            try {
                $pdo->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                $errorCount++;
                // Only log significant errors
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "Warning: " . substr($e->getMessage(), 0, 100) . "\n";
                }
            }
        }
        
        echo "Executed $successCount statements successfully";
        if ($errorCount > 0) {
            echo " ($errorCount warnings/errors)";
        }
        echo "\n";
        
        // Verify critical tables exist
        $criticalTables = ['users', 'company_settings', 'menu_items', 'orders', 'tables'];
        $missingTables = [];
        foreach ($criticalTables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                $missingTables[] = $table;
            }
        }
        
        if (!empty($missingTables)) {
            echo "ERROR: Missing critical tables: " . implode(', ', $missingTables) . "\n";
            exit(1);
        }
        
        echo "All critical tables verified.\n";
    } else {
        echo "Tables already exist. Skipping import.\n";
    }

} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration completed successfully.\n";
