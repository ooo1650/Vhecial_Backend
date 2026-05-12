<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/otp_helper.php';

$body  = json_decode(file_get_contents("php://input"), true);
$email = trim($body['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "A valid email address is required"]));
}

// Check user exists
$stmt = $pdo->prepare("SELECT id, given_name, auth_provider, password FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die(json_encode(["success" => false, "message" => "No account found with this email address."]));
}

if ($user['auth_provider'] === 'google' && empty($user['password'])) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "This account uses Google sign-in. Please use 'Continue with Google' to log in."]));
}

// Send OTP via shared helper
$result = sendOtp($pdo, $email, $user['given_name'] ?: 'there');

if (!$result['success']) {
    http_response_code(429);
    die(json_encode($result));
}

echo json_encode(["success" => true, "message" => "A password reset code has been sent to $email"]);
