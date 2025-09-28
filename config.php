<?php
// Configure session cookie parameters. Other scripts may call session_start() again
// so we avoid forcing a session start here.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        // 'domain' => 'school.marep.sk', // optional: set to your domain in production
        //'secure' => true, // enable on HTTPS
        'httponly' => true,
    ]);
}

// Database configuration for PostgreSQL. Update these to match your Postgres server.
// NOTE: change INIT_SECRET before running init_db via web to avoid exposing initialization.
define('DB_DRIVER', 'pgsql');
define('DB_HOST', 'db.r6.websupport.sk');
define('DB_PORT', '5432'); // leave empty to use default 5432
define('DB_NAME', 'school_organiser');
define('DB_USER', 'adminmarep');
define('DB_PASS', 'Pa3wjooi4i');
// Secret used for one-time guarded database initialization via `init_db.php`.
// Change this to a strong random value before running initialization on a public server.
define('INIT_SECRET', 'change_me');

// Set error logging to a file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Database connection
try {
    $dsn = DB_DRIVER . ':host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // connection OK — do not echo HTML here, APIs expect clean JSON output
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage()); // Log error to file
    // Do not emit HTML here; fail fast for scripts that include config.php
    http_response_code(500);
    exit;
}
?>
