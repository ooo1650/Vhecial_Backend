<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$body     = json_decode(file_get_contents("php://input"), true);
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');
$username = trim($body['username'] ?? '');

if (empty($email) || empty($password) || empty($username)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Name, email and password are required"]));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid email address"]));
}

if (strlen($password) < 6) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Password must be at least 6 characters"]));
}

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    die(json_encode(["success" => false, "message" => "An account with this email already exists"]));
}

$hashed = password_hash($password, PASSWORD_BCRYPT);

$pdo->prepare("
    INSERT INTO users (email, username, given_name, password, auth_provider, email_verified)
    VALUES (?, ?, ?, ?, 'email', 0)
")->execute([$email, $username, $username, $hashed]);

$user_id = $pdo->lastInsertId();

echo json_encode([
    "success" => true,
    "message" => "Account created successfully",
    "user" => [
        "id"         => (int) $user_id,
        "email"      => $email,
        "username"   => $username,
        "given_name" => $username,
        "picture"    => null,
    ]
]);
