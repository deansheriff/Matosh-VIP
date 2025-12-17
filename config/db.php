<?php
// Database Configuration
// ----------------------
// To keep your credentials secure, it's recommended to use environment variables.
// Create a .env file in the root of your project and add the following:
//
// DB_HOST=localhost
// DB_NAME=matosh_pos
// DB_USER=root
// DB_PASS=
//
// You can then use a library like `vlucas/phpdotenv` to load them.
// For this example, we'll use getenv() which works with web server configs,
// or you can define them directly for simplicity.

// Helper function to safely get env variables
function get_env_var($key, $default = null) {
    return getenv($key) ?: ($_ENV[$key] ?? ($_SERVER[$key] ?? $default));
}

// Define database credentials with fallbacks
define('DB_HOST', get_env_var('DB_HOST', '127.0.0.1')); // Default to TCP loopback, not socket
define('DB_NAME', get_env_var('DB_NAME', 'hellosil_res'));
define('DB_USER', get_env_var('DB_USER', 'hellosil_res'));
define('DB_PASS', get_env_var('DB_PASS', 'Donjazzy123?'));

// Define currency symbol
// define('CURRENCY_SYMBOL', '₦'); // Defined in header.php from DB settings

// Set the default timezone
// A list of supported timezones can be found here: https://www.php.net/manual/en/timezones.php
date_default_timezone_set('Africa/Lagos');

// DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

// PDO Options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Trigger exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
];

try {
    // Create a new PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // If connection fails, stop the script and show an error.
    error_log("Database Connection Error: " . $e->getMessage());
    // SHOWING ERROR FOR DEBUGGING
    die("Database connection failed: " . $e->getMessage() . 
        " <br>Host: " . DB_HOST . 
        " <br>User: " . DB_USER . 
        " <br>(Check Env Vars in Coolify/Docker)"); 
}

// The $pdo object is now available for use in other scripts.