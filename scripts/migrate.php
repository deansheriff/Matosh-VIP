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

$dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Retry connection logic
$max_retries = 30;
$retry_delay = 2; // seconds
$pdo = null;

echo "Starting database migration...\n";

for ($i = 0; $i < $max_retries; $i++) {
    try {
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
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "Table 'users' not found. Creating...\n";
        
        $sql = "CREATE TABLE `users` (
          `id` int NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `email` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `role` enum('Admin','Cashier','Waiter','Kitchen') NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($sql);
        echo "Table 'users' created successfully.\n";

        // Insert default users from dump
        echo "Seeding users table...\n";
        $insertSql = "INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
            (2, 'Cashier User', 'cashier@example.com', '\$2y\$10\$N9.gBY.G.bVbT9CqK.E2.O0R0M.lI.H/GAg5j/1xO.D.C.S.a.s.Q', 'Cashier'),
            (5, 'Sheriff', 'sherpackage@gmail.com', '\$2y\$10\$tOg7FEG.3N/4xN1jTJRk9OedNymycxGNXu01xfIB27LLlz/dBjNhi', 'Admin'),
            (6, 'iya fatuuu', 'matosh@gmail.com', '\$2y\$10\$NNw.s2GDGF5tCgAA2TmP1.uakQgcv3WyKJzAdYHmmfI81w1amFBhK', 'Cashier'),
            (7, 'Cashier 1', 'cashier@gmail.com', '\$2y\$10\$1ZvoqysJcur1juZfiZ5eUOBPhUpGGxV5nW9UrmNMDLA9XKYYVHbOG', 'Cashier')";
        
        $pdo->exec($insertSql);
        echo "Users seeded successfully.\n";

    } else {
        echo "Table 'users' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration completed successfully.\n";
