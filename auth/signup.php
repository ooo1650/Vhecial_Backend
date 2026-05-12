<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/otp_helper.php';

// Accepts multipart/form-data (for picture upload) or JSON
$isMultipart = isset($_FILES['picture']) || !empty($_POST);

if ($isMultipart) {
    $given_name  = trim($_POST['given_name']  ?? '');
    $family_name = trim($_POST['family_name'] ?? '');
    $email       = trim($_POST['email']       ?? '');
    $dob         = trim($_POST['dob']         ?? '');
    $google_id   = trim($_POST['google_id']   ?? '');
} else {
    $body        = json_decode(file_get_contents("php://input"), true) ?? [];
    $given_name  = trim($body['given_name']  ?? '');
    $family_name = trim($body['family_name'] ?? '');
    $email       = trim($body['email']       ?? '');
    $dob         = trim($body['dob']         ?? '');
    $google_id   = trim($body['google_id']   ?? '');
}

// ── Validate required fields ──────────────────────────────────────────────
$errors = [];
if (empty($given_name))  $errors[] = "First name is required";
if (empty($email))       $errors[] = "Email is required";
if (empty($dob))         $errors[] = "Date of birth is required";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address";

if (!empty($errors)) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => implode('. ', $errors)]));
}

// ── Age check (must be 18+) ───────────────────────────────────────────────
$birthDate = DateTime::createFromFormat('Y-m-d', $dob);
if (!$birthDate) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "Invalid date of birth format (use YYYY-MM-DD)"]));
}
$age = $birthDate->diff(new DateTime())->y;
if ($age < 18) {
    http_response_code(400);
    die(json_encode(["success" => false, "message" => "You must be at least 18 years old to register"]));
}

// ── Check if email already exists ────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id, google_id FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing && empty($google_id)) {
    http_response_code(409);
    die(json_encode(["success" => false, "message" => "An account with this email already exists. Please sign in."]));
}

// ── Handle profile picture upload ────────────────────────────────────────
$picture_path = null;
if (!empty($_FILES['picture']['tmp_name'])) {
    $file     = $_FILES['picture'];
    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize  = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed)) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => "Profile picture must be JPG, PNG, WebP or GIF"]));
    }
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        die(json_encode(["success" => false, "message" => "Profile picture must be under 5MB"]));
    }

    $ext          = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename     = uniqid('avatar_', true) . '.' . $ext;
    $uploadDir    = __DIR__ . '/../../backend/uploads/avatars/';
    $uploadPath   = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        http_response_code(500);
        die(json_encode(["success" => false, "message" => "Failed to save profile picture"]));
    }
    $picture_path = 'uploads/avatars/' . $filename;
}

// ── Google picture fallback ───────────────────────────────────────────────
if (!$picture_path && !empty($isMultipart ? $_POST['google_picture'] : ($body['google_picture'] ?? ''))) {
    $picture_path = $isMultipart ? trim($_POST['google_picture']) : trim($body['google_picture']);
}

$username = trim("$given_name $family_name");

// ── Create or update user ─────────────────────────────────────────────────
if ($existing && !empty($google_id)) {
    // Google user completing sign-up — update DOB and picture
    $pdo->prepare("
        UPDATE users SET dob = ?, given_name = ?, family_name = ?, username = ?,
        picture = COALESCE(?, picture), google_id = ?
        WHERE email = ?
    ")->execute([$dob, $given_name, $family_name, $username, $picture_path, $google_id, $email]);
} else {
    // Brand new user — insert without password (set in next step)
    $pdo->prepare("
        INSERT INTO users (email, given_name, family_name, username, dob, picture, google_id, auth_provider, email_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
    ")->execute([
        $email, $given_name, $family_name, $username, $dob,
        $picture_path,
        $google_id ?: null,
        $google_id ? 'google' : 'email'
    ]);
}

// ── Send OTP ──────────────────────────────────────────────────────────────
$result = sendOtp($pdo, $email, $given_name);
if (!$result['success']) {
    http_response_code(429);
    die(json_encode($result));
}

echo json_encode(["success" => true, "message" => $result['message'], "email" => $email]);
