<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$email = trim($_POST['email'] ?? '');
if (empty($email)) die(json_encode(["success" => false, "message" => "Email is required"]));

if (empty($_FILES['picture']['tmp_name'])) {
    die(json_encode(["success" => false, "message" => "No file uploaded"]));
}

$file    = $_FILES['picture'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$maxSize = 5 * 1024 * 1024;

if (!in_array($file['type'], $allowed)) {
    die(json_encode(["success" => false, "message" => "Only JPG, PNG, WebP or GIF allowed"]));
}
if ($file['size'] > $maxSize) {
    die(json_encode(["success" => false, "message" => "File must be under 5MB"]));
}

// Delete old avatar if it's a local file
$stmt = $pdo->prepare("SELECT picture FROM users WHERE email = ?");
$stmt->execute([$email]);
$old = $stmt->fetchColumn();
if ($old && !str_starts_with($old, 'http')) {
    $oldPath = __DIR__ . '/../../backend/' . $old;
    if (file_exists($oldPath)) unlink($oldPath);
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('avatar_', true) . '.' . $ext;
$dir      = __DIR__ . '/../../backend/uploads/avatars/';
$path     = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    die(json_encode(["success" => false, "message" => "Failed to save file"]));
}

$relative = 'uploads/avatars/' . $filename;
$pdo->prepare("UPDATE users SET picture = ? WHERE email = ?")->execute([$relative, $email]);

echo json_encode([
    "success" => true,
    "picture" => '/api/' . $relative,
]);
