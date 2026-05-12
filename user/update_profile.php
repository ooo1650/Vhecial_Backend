<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$body        = json_decode(file_get_contents("php://input"), true);
$email       = trim($body['email']       ?? '');
$given_name  = trim($body['given_name']  ?? '');
$family_name = trim($body['family_name'] ?? '');
$dob         = trim($body['dob']         ?? '');

if (empty($email))      die(json_encode(["success" => false, "message" => "Email is required"]));
if (empty($given_name)) die(json_encode(["success" => false, "message" => "First name is required"]));

// Validate DOB if provided
if ($dob) {
    $d = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$d) die(json_encode(["success" => false, "message" => "Invalid date of birth"]));
    $age = $d->diff(new DateTime())->y;
    if ($age < 18) die(json_encode(["success" => false, "message" => "You must be at least 18 years old"]));
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if (!$stmt->fetch()) die(json_encode(["success" => false, "message" => "User not found"]));

$username = trim("$given_name $family_name");

$pdo->prepare("
    UPDATE users SET given_name = ?, family_name = ?, username = ?, dob = ?
    WHERE email = ?
")->execute([$given_name, $family_name, $username, $dob ?: null, $email]);

echo json_encode([
    "success"     => true,
    "message"     => "Profile updated successfully",
    "given_name"  => $given_name,
    "family_name" => $family_name,
    "username"    => $username,
    "dob"         => $dob ?: null,
]);
