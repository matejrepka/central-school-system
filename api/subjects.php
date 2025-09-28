<?php
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $mode = $_GET['mode'] ?? 'user'; // 'user' | 'mandatory' | 'combined'
        if ($mode === 'mandatory') {
            $stmt = $pdo->query('SELECT id, code, name, description FROM povinne_predmety ORDER BY name');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // attach links and schedule for each mandatory subject
            foreach ($rows as &$r) {
                $lid = (int)$r['id'];
                $lstmt = $pdo->prepare('SELECT title, url, position FROM povinne_links WHERE povinne_id = ? ORDER BY position');
                $lstmt->execute([$lid]);
                $r['links'] = $lstmt->fetchAll(PDO::FETCH_ASSOC);

                $sstmt = $pdo->prepare('SELECT day, start_time, end_time, type, class_group, position FROM povinne_schedule WHERE povinne_id = ? ORDER BY position');
                $sstmt->execute([$lid]);
                $r['schedule'] = $sstmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode($rows);
            exit;
        }

        if ($mode === 'combined') {
            // Fetch user's own subjects
            $stmt = $pdo->prepare('SELECT id, name, class_group, NULL AS code, "user" AS source FROM subjects WHERE user_id = ? ORDER BY id DESC');
            $stmt->execute([$user_id]);
            $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch mandatory subjects
            $stmt = $pdo->query('SELECT id, name, code, description FROM povinne_predmety ORDER BY name');
            $mandRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // attach links and schedule for mandatory rows and normalize shape
            foreach ($mandRows as &$m) {
                $mid = (int)$m['id'];
                $lstmt = $pdo->prepare('SELECT title, url, position FROM povinne_links WHERE povinne_id = ? ORDER BY position');
                $lstmt->execute([$mid]);
                $links = $lstmt->fetchAll(PDO::FETCH_ASSOC);

                $sstmt = $pdo->prepare('SELECT day, start_time, end_time, type, class_group, position FROM povinne_schedule WHERE povinne_id = ? ORDER BY position');
                $sstmt->execute([$mid]);
                $schedule = $sstmt->fetchAll(PDO::FETCH_ASSOC);

                $m = [
                    'id' => 'p_' . $m['id'], // prefixed id to avoid clash
                    'name' => $m['name'],
                    'code' => $m['code'],
                    'source' => 'mandatory',
                    'description' => $m['description'] ?? null,
                    'links' => $links,
                    'schedule' => $schedule,
                ];
            }

            // attach links and schedule to user rows and mark source
            foreach ($userRows as &$u) {
                $u['source'] = 'user';
                $u['code'] = null;
                $sid = (int)$u['id'];
                $l = $pdo->prepare('SELECT title, url, position FROM subject_links WHERE subject_id = ? ORDER BY position');
                $l->execute([$sid]);
                $u['links'] = $l->fetchAll(PDO::FETCH_ASSOC);

                $s = $pdo->prepare('SELECT day, start_time, end_time, type, class_group, position FROM subject_schedule WHERE subject_id = ? ORDER BY position');
                $s->execute([$sid]);
                $u['schedule'] = $s->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(array_merge($mandRows, $userRows));
            exit;
        }

        // default: return user-specific subjects (include links/schedule)
        $stmt = $pdo->prepare('SELECT id, name, class_group FROM subjects WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $sid = (int)$r['id'];
            $l = $pdo->prepare('SELECT title, url, position FROM subject_links WHERE subject_id = ? ORDER BY position');
            $l->execute([$sid]);
            $r['links'] = $l->fetchAll(PDO::FETCH_ASSOC);

            $s = $pdo->prepare('SELECT day, start_time, end_time, type, class_group, position FROM subject_schedule WHERE subject_id = ? ORDER BY position');
            $s->execute([$sid]);
            $r['schedule'] = $s->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($rows);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        // Support actions: create (user subject) or assign (assign mandatory to user)
        $action = $data['action'] ?? 'create';
        if ($action === 'create') {
            $name = trim($data['name'] ?? '');
            if ($name === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Missing name']);
                exit;
            }
            $class_group = isset($data['class_group']) ? trim($data['class_group']) : null;

            // Use a transaction so subject + links + schedule are atomic
            $pdo->beginTransaction();
            try {
                // Use RETURNING id to get the new id in Postgres
                $istmt = $pdo->prepare('INSERT INTO subjects (user_id, name, class_group) VALUES (?, ?, ?) RETURNING id');
                $istmt->execute([$user_id, $name, $class_group]);
                $newId = $istmt->fetchColumn();

                // Insert links if provided
                if (!empty($data['links']) && is_array($data['links'])) {
                    $lstmt = $pdo->prepare('INSERT INTO subject_links (subject_id, title, url, position) VALUES (?, ?, ?, ?)');
                    $pos = 0;
                    foreach ($data['links'] as $ln) {
                        $lstmt->execute([$newId, $ln['title'] ?? null, $ln['url'] ?? '', $ln['position'] ?? $pos]);
                        $pos++;
                    }
                }

                // Insert schedule if provided
                if (!empty($data['schedule']) && is_array($data['schedule'])) {
                    $sstmt = $pdo->prepare('INSERT INTO subject_schedule (subject_id, day, start_time, end_time, type, class_group, position) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $pos = 0;
                    foreach ($data['schedule'] as $entry) {
                        // Expect day, start_time, end_time, type, optional class_group
                        $sstmt->execute([
                            $newId,
                            $entry['day'] ?? '',
                            $entry['start_time'] ?? '00:00',
                            $entry['end_time'] ?? '00:00',
                            $entry['type'] ?? 'lecture',
                            $entry['class_group'] ?? null,
                            $entry['position'] ?? $pos,
                        ]);
                        $pos++;
                    }
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'id' => $newId]);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Subjects create error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create subject']);
                exit;
            }
        }

        if ($action === 'assign') {
            $povinne_id = intval($data['povinne_id'] ?? 0);
            if ($povinne_id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing povinne_id']);
                exit;
            }
            // Insert into user_povinne_predmety (idempotent)
            $stmt = $pdo->prepare('INSERT INTO user_povinne_predmety (user_id, povinne_predmety_id) VALUES (?, ?) ON CONFLICT DO NOTHING');
            $stmt->execute([$user_id, $povinne_id]);
            echo json_encode(['success' => true]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing id']);
            exit;
        }
        // Ensure subject belongs to the user
        $stmt = $pdo->prepare('DELETE FROM subjects WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    error_log('Subjects API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred']);
}
