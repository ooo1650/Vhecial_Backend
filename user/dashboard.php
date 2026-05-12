<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["success" => false, "message" => "Method not allowed"]));
}

$body = json_decode(file_get_contents("php://input"), true);
$google_id = trim($body['google_id'] ?? '');

if (empty($google_id)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Missing google_id"]));
}

// Get user record
$stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
$stmt->execute([$google_id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die(json_encode(["success" => false, "message" => "User not found"]));
}

// Count bookings by status
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'confirmed') AS confirmed,
        SUM(status = 'pending')   AS pending,
        SUM(status = 'completed') AS completed
    FROM bookings WHERE user_id = ?
");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

echo json_encode([
    "success" => true,
    "stats" => [
        "total_bookings"     => (int) $stats['total'],
        "confirmed_bookings" => (int) $stats['confirmed'],
        "pending_bookings"   => (int) $stats['pending'],
        "completed_bookings" => (int) $stats['completed'],
    ],
    "member_since" => date('F Y', strtotime($user['created_at'])),
]);
