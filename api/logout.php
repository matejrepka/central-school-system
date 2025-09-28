<?php
// logout.php - allow both AJAX POST (returns JSON) and browser GET (redirect)
// This script doesn't need database access, so don't require config.php.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper to destroy session and cookie
function destroy_session()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'] ?? '',
            $params['secure'] ?? false, $params['httponly'] ?? true
        );
    }
    session_destroy();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (strtoupper($method) === 'POST') {
    // AJAX/Fetch logout: return JSON
    header('Content-Type: application/json');
    destroy_session();
    echo json_encode(['success' => true]);
    exit;
} else {
    // Browser GET -> destroy session and redirect to login page
    destroy_session();
    // Use a safe redirect target
    $redirect = '/login.php';
    header('Location: ' . $redirect);
    exit;
}
