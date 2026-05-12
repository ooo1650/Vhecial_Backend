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
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid email address"]));
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
$pdo->prepare("UPDATE users SET password = ?, email_verified = 1 WHERE email = ?")
    ->execute([$hashed, $email]);

echo json_encode(["success" => true, "message" => "Password reset successfully. You can now sign in."]);
