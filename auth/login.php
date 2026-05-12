<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$body     = json_decode(file_get_contents("php://input"), true);
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

if (empty($email) || empty($password)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Email and password are required"]));
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND auth_provider = 'email'");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    die(json_encode(["success" => false, "message" => "Invalid email or password"]));
}

// Update last login
$pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

echo json_encode([
    "success" => true,
    "message" => "Login successful",
    "user" => [
        "id"         => (int) $user['id'],
        "email"      => $user['email'],
        "username"   => $user['username'],
        "given_name" => $user['given_name'],
        "picture"    => $user['picture'],
    ]
]);
