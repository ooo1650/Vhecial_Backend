<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   http_response_code(405);
   die(json_encode([
      "success" => false,
      "message" => "Method not allowed"
   ]));
}

$body = json_decode(file_get_contents("php://input"), true);
$username = trim($body['username'] ?? '');
$password = trim($body['password'] ?? '');

if (empty($username) || empty($password)) {
   http_response_code(400);
   die(json_encode([
      "success" => false,
      "message" => "Username and password are required"
   ]));
}

$stmt = $pdo->prepare(
   "SELECT * FROM admin WHERE username = ?"
);
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password'])) {
   http_response_code(401);
   die(json_encode([
      "success" => false,
      "message" => "Invalid credentials"
   ]));
}

echo json_encode([
    "success" => true,
    "message" => "Login successful",
    "admin" => [
       "id" => $admin['id'],
       "username" => $admin['username']
   ]
]);
