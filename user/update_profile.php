<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

// Suppress notices/warnings that would corrupt JSON output
error_reporting(0);
header('Content-Type: application/json');

$raw  = file_get_contents("php://input");
$body = json_decode($raw, true);

if ($body === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid request body — JSON parse error: " . json_last_error_msg()]));
}

$email       = trim($body['email']       ?? '');
$given_name  = trim($body['given_name']  ?? '');
$family_name = trim($body['family_name'] ?? '');
$dob         = trim($body['dob']         ?? '');
$picture     = $body['picture']          ?? null; // base64 data URL or null

if (empty($email))      { http_response_code(400); die(json_encode(["success" => false, "message" => "Email is required"])); }
if (empty($given_name)) { http_response_code(400); die(json_encode(["success" => false, "message" => "First name is required"])); }

// Validate DOB if provided
if ($dob) {
    $d = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$d) { http_response_code(400); die(json_encode(["success" => false, "message" => "Invalid date of birth"])); }
    $age = $d->diff(new DateTime())->y;
    if ($age < 18) { http_response_code(400); die(json_encode(["success" => false, "message" => "You must be at least 18 years old"])); }
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if (!$stmt->fetch()) { http_response_code(404); die(json_encode(["success" => false, "message" => "User not found"])); }

$username = trim("$given_name $family_name");

if ($picture !== null) {
    $pdo->prepare("
        UPDATE users SET given_name = ?, family_name = ?, username = ?, dob = ?, picture = ?
        WHERE email = ?
    ")->execute([$given_name, $family_name, $username, $dob ?: null, $picture, $email]);
} else {
    $pdo->prepare("
        UPDATE users SET given_name = ?, family_name = ?, username = ?, dob = ?
        WHERE email = ?
    ")->execute([$given_name, $family_name, $username, $dob ?: null, $email]);
}

echo json_encode([
    "success"     => true,
    "message"     => "Profile updated successfully",
    "given_name"  => $given_name,
    "family_name" => $family_name,
    "username"    => $username,
    "dob"         => $dob ?: null,
]);
