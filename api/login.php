<?php
require_once '../config.php';

// Start session after session cookie params have been configured in config.php
if (session_status() === PHP_SESSION_NONE) {
    // Use session defaults configured in config.php. Overriding cookie params
    // here (domain/secure) can break sessions during local development or
    // when running over plain HTTP. Change cookie params in config.php for
    // production if needed.
    session_start();
}

// Ensure no output before headers
if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/json');

// Check database connection
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection not established']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing username or password']);
        exit;
    }

    try {
        // Fetch user from database
        $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent fixation attacks
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            echo json_encode(['success' => true, 'redirect' => 'index.php']);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    } catch (Exception $e) {
        // Log error and return generic message
        error_log('Login error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'An unexpected error occurred']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}