<?php
// Copy this file to config.php and fill with your real values. Do NOT commit config.php.

// Configure session cookie parameters (same as original project).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        // 'domain' => 'your.domain.tld',
        // 'secure' => true, // enable on HTTPS
        'httponly' => true,
    ]);
}

// Database configuration (placeholder values)
define('DB_DRIVER', 'pgsql');
define('DB_HOST', 'your_db_host');
define('DB_PORT', '5432');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
// Change this to a strong random value before using init_db.php
define('INIT_SECRET', 'change_this_to_a_random_secret');

// Logging: keep error.log ignored in .gitignore
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Note: the example does not open a DB connection. Copy to config.php if you
// want the real connection to be created; config.php will be excluded from the
// repository by .gitignore.

?>
