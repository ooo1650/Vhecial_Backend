<?php
/**
 * POST /api/user/upload_avatar.php
 *
 * Stores the avatar as a base64 data URL in the users.picture column.
 * This avoids filesystem issues on ephemeral hosts like Render.
 *
 * FormData fields:
 *   email   - user email
 *   picture - image file (JPG/PNG/WebP/GIF, max 2MB)
 */
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$email = trim($_POST['email'] ?? '');
if (empty($email)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Email is required"]));
}

if (empty($_FILES['picture']['tmp_name'])) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "No file uploaded"]));
}

$file    = $_FILES['picture'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$maxSize = 2 * 1024 * 1024; // 2MB — keep base64 size reasonable

if (!in_array($file['type'], $allowed)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Only JPG, PNG, WebP or GIF allowed"]));
}
if ($file['size'] > $maxSize) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "File must be under 2MB"]));
}

// Read file and encode as base64 data URL
$imageData = file_get_contents($file['tmp_name']);
if ($imageData === false) {
    http_response_code(500);
    die(json_encode(["success" => false, "message" => "Failed to read uploaded file"]));
}

$base64  = base64_encode($imageData);
$dataUrl = 'data:' . $file['type'] . ';base64,' . $base64;

// Save to DB
$stmt = $pdo->prepare("UPDATE users SET picture = ? WHERE email = ?");
$stmt->execute([$dataUrl, $email]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    die(json_encode(["success" => false, "message" => "User not found"]));
}

echo json_encode([
    "success" => true,
    "picture" => $dataUrl,
]);
