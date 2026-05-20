<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$body     = json_decode(file_get_contents("php://input"), true);
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

// Validate inputs
if (empty($email))    { http_response_code(400); die(json_encode(["success" => false, "message" => "Email is required"])); }
if (empty($password)) { http_response_code(400); die(json_encode(["success" => false, "message" => "Password is required"])); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid email address"]));
}

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

// Update last login
$pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

$picture = $user['picture'];
if ($picture && !str_starts_with($picture, 'http')) {
    $picture = '/api/' . $picture;
}

// Return user directly — no OTP for sign in
echo json_encode([
    "success" => true,
    "message" => "Login successful",
    "user"    => [
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
