<?php
// Environment & Error Handling
define('ENVIRONMENT_DEV', true);

if (ENVIRONMENT_DEV) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Africa/Nairobi');

// System Information & URLs
define('APP_NAME', 'AgriConnect Kenya');
define('APP_URL', 'http://localhost/agriconnect');

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'danfam_market'); 
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


//  DATABASE CONNECTION ($pdo)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    if (ENVIRONMENT_DEV) {
        die("Database Connection Error: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}
?>