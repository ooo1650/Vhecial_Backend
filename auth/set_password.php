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
if (strlen($password) < 8) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Password must be at least 8 characters"]));
}
if (!preg_match('/[A-Z]/', $password)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Password must contain at least one uppercase letter"]));
}
if (!preg_match('/[0-9]/', $password)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Password must contain at least one number"]));
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if (!$stmt->fetch()) {
    http_response_code(404);
    die(json_encode(["success" => false, "message" => "User not found"]));
}

$hashed = password_hash($password, PASSWORD_BCRYPT);
$pdo->prepare("UPDATE users SET password = ?, email_verified = 1 WHERE email = ?")->execute([$hashed, $email]);

// Return full user for localStorage
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

echo json_encode([
    "success" => true,
    "message" => "Password set successfully",
    "user" => [
        "id"          => (int) $user['id'],
        "email"       => $user['email'],
        "given_name"  => $user['given_name'],
        "family_name" => $user['family_name'],
        "username"    => $user['username'],
        "dob"         => $user['dob'],
        "picture"     => $user['picture'],
        "auth_provider" => $user['auth_provider'],
    ]
]);
