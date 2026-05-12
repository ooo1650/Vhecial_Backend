<?php
$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$dbuser = $_ENV['DB_USER'];
$dbpass = $_ENV['DB_PASS'];
$port = $_ENV['DB_PORT'];

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    // Show the real error so you can debug it
    die(json_encode([
        "success" => false,
        "message" => "Database connection failed: " . $e->getMessage()
    ]));
}
