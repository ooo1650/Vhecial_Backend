<?php
// Use getenv() for Render, with hardcoded fallbacks to test connectivity
$host = getenv('DB_HOST') ?: "mysql-75611d1-sudipdahal887-3c3f.h.aivencloud.com";
$port = getenv('DB_PORT') ?: "11111";
$user = getenv('DB_USER') ?: "avnadmin";
$pass = getenv('DB_PASS') ?: "AVNS_sh8B7SXCJqe5cLZUTDI";
$db = getenv('DB_NAME') ?: "defaultdb"; // Use the schema from your logs

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // This forces SSL but skips strict path verification of the CA file
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

} catch (PDOException $e) {
    die(json_encode([
        "success" => false,
        "message" => "Connection failed. Error: " . $e->getMessage()
    ]));
}