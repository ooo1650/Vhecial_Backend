<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$body  = json_decode(file_get_contents("php://input"), true);
$email = trim($body['email'] ?? '');
$otp   = trim($body['otp']   ?? '');

if (empty($email)) die(json_encode(["success" => false, "message" => "Email is required"]));
if (empty($otp))   die(json_encode(["success" => false, "message" => "OTP is required"]));
if (strlen($otp) !== 6) die(json_encode(["success" => false, "message" => "OTP must be 6 digits"]));

// Find valid unused OTP
$stmt = $pdo->prepare("
    SELECT * FROM otp_tokens
    WHERE email = ? AND otp = ? AND used = 0 AND expires_at > UTC_TIMESTAMP()
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$email, $otp]);
$token = $stmt->fetch();

if (!$token) {
    http_response_code(401);
    die(json_encode(["success" => false, "message" => "Invalid or expired OTP. Please request a new one."]));
}

// Mark used
$pdo->prepare("UPDATE otp_tokens SET used = 1 WHERE id = ?")->execute([$token['id']]);

// Get user
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die(json_encode(["success" => false, "message" => "User not found"]));
}

// Update last_login FIRST, then fetch so the returned value is current
$pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP, email_verified = 1 WHERE email = ?")
    ->execute([$email]);

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();
$picture = $user['picture'];
if ($picture && !str_starts_with($picture, 'http')) {
    $picture = '/api/' . $picture;
}

echo json_encode([
    "success"        => true,
    "message"        => "Verified successfully",
    "needs_password" => empty($user['password']),
    "user" => [
        "id"           => (int) $user['id'],
        "email"        => $user['email'],
        "given_name"   => $user['given_name'],
        "family_name"  => $user['family_name'],
        "username"     => $user['username'],
        "dob"          => $user['dob'],
        "picture"      => $picture,
        "auth_provider"=> $user['auth_provider'],
        "last_login"   => $user['last_login'],
    ]
]);
