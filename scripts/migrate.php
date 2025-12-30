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

for ($i = 0; $i < $max_retries; $i++) {
    try {
        // First connect without specifying the database
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true, // Enable emulated prepares for multi-statement execution
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci",
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
    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '$name'");
    if ($stmt->rowCount() == 0) {
        echo "Database '$name' not found. Creating...\n";
        $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        echo "Database '$name' created.\n";
    }

    // Select the database
    $pdo->exec("USE `$name`");

    // Check if company_settings table exists (as a proxy for whether the schema is initialized)
    $stmt = $pdo->query("SHOW TABLES LIKE 'company_settings'");
    if ($stmt->rowCount() == 0) {
        echo "Tables not found. Importing db_init.sql...\n";
        
        // Read the SQL file
        $sqlFile = __DIR__ . '/../db_init.sql';
        if (!file_exists($sqlFile)) {
            echo "ERROR: db_init.sql not found at $sqlFile\n";
            exit(1);
        }
        
        // Use mysql command line to import (more reliable for multi-statement SQL with special chars)
        $cmd = "mysql -h " . escapeshellarg($host) . 
               " -u " . escapeshellarg($user) . 
               " -p" . escapeshellarg($pass) . 
               " " . escapeshellarg($name) . 
               " < " . escapeshellarg($sqlFile) . " 2>&1";
        
        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            echo "MySQL import failed. Trying PDO fallback...\n";
            echo implode("\n", $output) . "\n";
            
            // Fallback to PDO multi-statement execution
            $sql = file_get_contents($sqlFile);
            
            // Split into individual statements and execute
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        // Skip errors on comments or empty statements
                        if (strpos($e->getMessage(), 'syntax error') === false) {
                            echo "Warning: " . $e->getMessage() . "\n";
                        }
                    }
                }
            }
        }
        
        echo "Database schema imported successfully.\n";
    } else {
        echo "Tables already exist. Skipping import.\n";
    }

} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration completed successfully.\n";
