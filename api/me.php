<?php
require_once '../config.php';

// Start session after session cookie params have been configured in config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Debugging: Log session details
error_log('Session ID: ' . session_id());
error_log('Session Data: ' . print_r($_SESSION, true));

if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    $user = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
    ];
    // Optionally include a class/group if the application stores it in session
    if (!empty($_SESSION['class_group'])) {
        $user['class_group'] = $_SESSION['class_group'];
    }
    echo json_encode(['user' => $user]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
}