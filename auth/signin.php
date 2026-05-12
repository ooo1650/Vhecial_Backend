<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/otp_helper.php';

$body     = json_decode(file_get_contents("php://input"), true);
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

// Validate inputs
if (empty($email))    die(json_encode(["success" => false, "message" => "Email is required"]));
if (empty($password)) die(json_encode(["success" => false, "message" => "Password is required"]));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) die(json_encode(["success" => false, "message" => "Invalid email address"]));

// Find user
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    die(json_encode(["success" => false, "message" => "No account found with this email. Please sign up."]));
}

if (!$user['password']) {
    http_response_code(401);
    die(json_encode(["success" => false, "message" => "This account uses Google sign-in. Please use Continue with Google."]));
}

if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    die(json_encode(["success" => false, "message" => "Incorrect password"]));
}

// Send OTP
$result = sendOtp($pdo, $email, $user['given_name']);
if (!$result['success']) {
    http_response_code(429);
    die(json_encode($result));
}

echo json_encode(["success" => true, "message" => $result['message']]);
