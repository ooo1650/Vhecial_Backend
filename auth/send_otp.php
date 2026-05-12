<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

$body     = json_decode(file_get_contents("php://input"), true);
$email      = trim($body['email']      ?? '');
$first_name = trim($body['first_name'] ?? '');
$last_name  = trim($body['last_name']  ?? '');
$password   = trim($body['password']   ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Valid email is required"]));
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    if (empty($first_name)) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => "First name is required"]));
    }
    if (strlen($password) < 8) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => "Password must be at least 8 characters"]));
    }
    $username = trim("$first_name $last_name");
    $hashed   = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare("
        INSERT INTO users (email, username, given_name, family_name, password, auth_provider, email_verified)
        VALUES (?, ?, ?, ?, ?, 'email', 0)
    ")->execute([$email, $username, $first_name, $last_name, $hashed]);

} else {
    // Existing user — verify password
    if (empty($password)) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => "Password is required"]));
    }
    if (!$user['password'] || !password_verify($password, $user['password'])) {
        http_response_code(401);
        die(json_encode(["success" => false, "message" => "Incorrect password"]));
    }
}

// Generate and send OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$pdo->prepare("DELETE FROM otp_tokens WHERE email = ?")->execute([$email]);
$pdo->prepare("INSERT INTO otp_tokens (email, otp, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 10 MINUTE)")
    ->execute([$email, $otp]);

$subject = "Your Mero Gadi verification code";
$message = "Hi,\n\nYour one-time verification code is:\n\n    $otp\n\nExpires in 10 minutes.\n\n— Mero Gadi Team";
$result = sendMail($email, $subject, $message);

if (!$result['sent']) {
    // Roll back the OTP so user can retry
    $pdo->prepare("DELETE FROM otp_tokens WHERE email = ?")->execute([$email]);
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "message" => "Failed to send OTP email.",
        "detail"  => $result['error'],  // shown in dev, remove in prod
    ]));
}

echo json_encode(["success" => true, "message" => "OTP sent to $email"]);
