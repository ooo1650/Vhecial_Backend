<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET — public, no auth needed
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM terms ORDER BY sort_order ASC, id ASC");
    echo json_encode(["success" => true, "terms" => $stmt->fetchAll()]);
    exit;
}

// All other methods require admin auth
require_once __DIR__ . '/../admin/admin_auth.php';

switch ($method) {

    case 'POST':
        $body    = json_decode(file_get_contents("php://input"), true);
        $title   = trim($body['title']   ?? '');
        $content = trim($body['content'] ?? '');
        if (empty($title) || empty($content)) {
            http_response_code(400);
            die(json_encode(["success" => false, "message" => "Title and content are required"]));
        }
        $order = (int)($pdo->query("SELECT COUNT(*) FROM terms")->fetchColumn());
        $pdo->prepare("INSERT INTO terms (title, content, sort_order) VALUES (?, ?, ?)")
            ->execute([$title, $content, $order]);
        echo json_encode(["success" => true, "id" => (int)$pdo->lastInsertId()]);
        break;

    case 'PUT':
        $body    = json_decode(file_get_contents("php://input"), true);
        $id      = (int)($body['id']      ?? 0);
        $title   = trim($body['title']   ?? '');
        $content = trim($body['content'] ?? '');
        if (!$id || empty($title) || empty($content)) {
            http_response_code(400);
            die(json_encode(["success" => false, "message" => "ID, title and content are required"]));
        }
        $pdo->prepare("UPDATE terms SET title = ?, content = ? WHERE id = ?")
            ->execute([$title, $content, $id]);
        echo json_encode(["success" => true]);
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); die(json_encode(["success" => false, "message" => "ID required"])); }
        $pdo->prepare("DELETE FROM terms WHERE id = ?")->execute([$id]);
        echo json_encode(["success" => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
}
