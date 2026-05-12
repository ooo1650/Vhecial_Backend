<?php
$host   = "127.0.0.1";  // use IP not "localhost" — avoids socket issues on Linux
$dbname = "vehicle_rental";
$dbuser = "root";
$dbpass = "";  // XAMPP default is empty, WAMP may use "root"

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
