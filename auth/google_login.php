<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/otp_helper.php';

$body = json_decode(file_get_contents("php://input"), true);
if (!$body) { http_response_code(400); die(json_encode(["success" => false, "message" => "Invalid JSON"])); }

$google_id   = trim($body['sub']         ?? '');
$email       = trim($body['email']       ?? '');
$given_name  = trim($body['given_name']  ?? '');
$family_name = trim($body['family_name'] ?? '');
$picture     = trim($body['picture']     ?? '');

if (empty($google_id) || empty($email)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid Google token"]));
}

try {
    // Check if user already exists (by google_id or email)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ? LIMIT 1");
    $stmt->execute([$google_id, $email]);
    $user = $stmt->fetch();

    if ($user) {
        // ── Returning user ── update picture, link google_id if missing
        $pdo->prepare("
            UPDATE users SET last_login = CURRENT_TIMESTAMP, picture = COALESCE(picture, ?),
            google_id = COALESCE(google_id, ?)
            WHERE email = ?
        ")->execute([$picture, $google_id, $email]);

        // Send OTP for sign-in
        $result = sendOtp($pdo, $email, $user['given_name']);
        if (!$result['success']) {
            http_response_code(429);
            die(json_encode($result));
        }

        echo json_encode([
            "success"      => true,
            "is_new_user"  => false,
            "has_password" => !empty($user['password']),
            "email"        => $email,
            "given_name"   => $user['given_name'],
            "message"      => $result['message'],
        ]);

    } else {
        // ── New user ── tell frontend to go to sign-up form (pre-filled)
        echo json_encode([
            "success"     => true,
            "is_new_user" => true,
            "email"       => $email,
            "given_name"  => $given_name,
            "family_name" => $family_name,
            "picture"     => $picture,
            "google_id"   => $google_id,
            "message"     => "Please complete your profile to continue",
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}
