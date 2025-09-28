<?php
require_once __DIR__ . '/../config.php';

// Start session after config sets cookie params
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function is_admin_user($pdo) {
    if (!isset($_SESSION['user_id'])) return false;
    $uid = (int)$_SESSION['user_id'];

    try {
        // Check if users table has is_admin column
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'is_admin'");
        $stmt->execute();
        $hasIsAdmin = (bool)$stmt->fetchColumn();

        if ($hasIsAdmin) {
            $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            return !empty($row) && ($row['is_admin'] == 't' || $row['is_admin'] == 1 || $row['is_admin'] === true);
        }
    } catch (Exception $e) {
        // if anything goes wrong, fall back to username check below
    }

    // Fallback: treat user with username 'admin' as administrator
    return isset($_SESSION['username']) && $_SESSION['username'] === 'admin';
}

if (!is_admin_user($pdo)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        // Create a new user
        $data = json_decode(file_get_contents('php://input'), true);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $email = trim($data['email'] ?? '');
        $class_group = trim($data['class_group'] ?? '');

        if (!$username || !$password || !$email) {
            http_response_code(400);
            echo json_encode(['error' => 'username, password and email are required']);
            exit;
        }

        // Basic validation
        if (strlen($username) > 255 || strlen($email) > 255) {
            http_response_code(400);
            echo json_encode(['error' => 'username/email too long']);
            exit;
        }

        // Hash password using PHP's password_hash (bcrypt/argon2 depending on PHP)
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('INSERT INTO users (username, email, password, class_group) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $email, $hash, $class_group ?: null]);

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'stats') {
            // total users
            $stmt = $pdo->query('SELECT COUNT(*)::int AS total FROM users');
            $total = (int)$stmt->fetchColumn();

            // users by class_group
            $stmt = $pdo->query("SELECT COALESCE(class_group, '') AS class_group, COUNT(*)::int AS cnt FROM users GROUP BY class_group ORDER BY cnt DESC");
            $by_group = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // signups last 30 days
            $stmt = $pdo->query("SELECT DATE(created_at) AS day, COUNT(*)::int AS cnt FROM users WHERE created_at >= now() - INTERVAL '30 days' GROUP BY day ORDER BY day");
            $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['total' => $total, 'by_group' => $by_group, 'trend' => $trend]);
            exit;
        }

        // default: list users (no passwords)
        $limit = 200;
        $stmt = $pdo->prepare('SELECT id, username, email, class_group, created_at FROM users ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    error_log('Admin users error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

