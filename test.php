<?php
// Visit http://localhost:8000/test.php in your browser to diagnose the connection
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/config/db.php';

// If we get here, DB connected successfully
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    "success" => true,
    "message" => "Database connected!",
    "database" => "vehicle_rental",
    "tables"  => $tables
]);
