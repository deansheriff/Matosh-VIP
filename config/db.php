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

// Define database credentials with fallbacks
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'hellosil_res');
define('DB_USER', getenv('DB_USER') ?: 'hellosil_res');
define('DB_PASS', getenv('DB_PASS') ?: 'Donjazzy123?');

// Define currency symbol
define('CURRENCY_SYMBOL', '₦');

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
    // In a production environment, you'd want to log this error, not display it.
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

// The $pdo object is now available for use in other scripts.