<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die(json_encode(["success"=>false,"message"=>"ID required"])); }

$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) { http_response_code(404); die(json_encode(["success"=>false,"message"=>"Not found"])); }

// Get all images
$imgs = $pdo->prepare("SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_primary DESC, sort_order ASC");
$imgs->execute([$id]);
$images = $imgs->fetchAll();

foreach ($images as &$img) {
    if (!str_starts_with($img['image_path'], 'http')) {
        $img['image_path'] = '/api/' . $img['image_path'];
    }
}

$v['images']   = $images;
$v['features'] = $v['features'] ? json_decode($v['features'], true) : [];

echo json_encode(["success" => true, "vehicle" => $v]);
