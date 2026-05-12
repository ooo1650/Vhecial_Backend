<?php
require_once __DIR__ . '/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(["success" => false, "message" => "Method not allowed"]));
}

$total_users    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_vehicles = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

$total_revenue = $pdo->query("
    SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE status = 'completed'
")->fetchColumn();

$recent_bookings = $pdo->query("
    SELECT b.id, u.username as user_name, v.name as vehicle_name,
           b.start_date, b.end_date, b.status, b.total_price
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN vehicles v ON b.vehicle_id = v.id
    ORDER BY b.created_at DESC
    LIMIT 5
")->fetchAll();

echo json_encode([
    "success" => true,
    "data" => [
        "counts" => [
            "users"    => (int) $total_users,
            "vehicles" => (int) $total_vehicles,
            "bookings" => (int) $total_bookings,
        ],
        "revenue"          => (float) $total_revenue,
        "recent_bookings"  => $recent_bookings,
    ]
]);
