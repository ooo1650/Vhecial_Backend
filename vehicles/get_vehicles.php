<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

// Get all available vehicles with their primary image
$stmt = $pdo->query("
    SELECT v.*,
           (SELECT image_path FROM vehicle_images
            WHERE vehicle_id = v.id AND is_primary = 1
            ORDER BY sort_order ASC LIMIT 1) AS primary_image
    FROM vehicles v
    WHERE v.available = 1
    ORDER BY v.type, v.price_per_day
");
$vehicles = $stmt->fetchAll();

// Prefix local image paths
foreach ($vehicles as &$v) {
    if ($v['primary_image'] && !str_starts_with($v['primary_image'], 'http')) {
        $v['primary_image'] = '/api/' . $v['primary_image'];
    }
    $v['features'] = $v['features'] ? json_decode($v['features'], true) : [];
}

echo json_encode(["success" => true, "vehicles" => $vehicles]);
