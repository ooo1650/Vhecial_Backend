<?php
require_once __DIR__ . '/admin_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list all bookings ────────────────────────────────────────────────
if ($method === 'GET') {
    $status = trim($_GET['status'] ?? '');
    $sql = "
        SELECT b.*,
               u.given_name, u.family_name,
               CONCAT(u.given_name, ' ', u.family_name) AS user_name,
               u.email AS user_email,
               v.name AS vehicle_name, v.type AS vehicle_type,
               (SELECT image_path FROM vehicle_images
                WHERE vehicle_id = v.id AND is_primary = 1 LIMIT 1) AS vehicle_image
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN vehicles v ON b.vehicle_id = v.id
    ";
    $params = [];
    if ($status) { $sql .= " WHERE b.status = ?"; $params[] = $status; }
    $sql .= " ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();

    foreach ($bookings as &$b) {
        if ($b['vehicle_image'] && !str_starts_with($b['vehicle_image'], 'http'))
            $b['vehicle_image'] = '/api/' . $b['vehicle_image'];
    }

    echo json_encode(["success" => true, "data" => $bookings]);
    exit;
}

// ── PUT: admin updates booking status ─────────────────────────────────────
if ($method === 'PUT') {
    $body   = json_decode(file_get_contents("php://input"), true);
    $id     = (int)($body['id']         ?? 0);
    $status = trim($body['status']      ?? '');
    $note   = trim($body['admin_note']  ?? '');

    if (!$id || !$status) {
        http_response_code(400);
        die(json_encode(["success"=>false,"message"=>"ID and status required"]));
    }

    $valid = ['pending','confirmed','cancelled','completed','pending_review'];
    if (!in_array($status, $valid)) {
        http_response_code(400);
        die(json_encode(["success"=>false,"message"=>"Invalid status"]));
    }

    $pdo->prepare("UPDATE bookings SET status = ?, admin_note = ? WHERE id = ?")
        ->execute([$status, $note ?: null, $id]);

    echo json_encode(["success" => true, "message" => "Booking updated"]);
    exit;
}

// ── DELETE: remove a booking ──────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        die(json_encode(["success"=>false,"message"=>"Booking ID required"]));
    }
    $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$id]);
    echo json_encode(["success" => true, "message" => "Booking deleted"]);
    exit;
}

http_response_code(405);
echo json_encode(["success" => false, "message" => "Method not allowed"]);
