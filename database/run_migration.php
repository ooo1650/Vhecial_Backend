<?php
/**
 * One-time migration runner — access via browser through Apache.
 * http://localhost/backend/database/run_migration.php
 *
 * DELETE this file after running it.
 */

// Simple protection — change or remove after use
$secret = $_GET['secret'] ?? '';
if ($secret !== 'run_now') {
    http_response_code(403);
    die('Access denied. Append ?secret=run_now to the URL.');
}

require_once __DIR__ . '/../config/db.php'; // uses the same PDO from your app

$sql = "
CREATE TABLE IF NOT EXISTS payments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    booking_id       INT NOT NULL,
    user_id          INT NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    payment_method   VARCHAR(20) NOT NULL DEFAULT 'esewa',
    status           ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    transaction_uuid VARCHAR(100) DEFAULT NULL,
    transaction_id   VARCHAR(200) DEFAULT NULL,
    esewa_ref_id     VARCHAR(200) DEFAULT NULL,
    paid_at          DATETIME DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (user_id)    REFERENCES users(id)
);
";

try {
    $pdo->exec($sql);
    echo "<pre>✅ payments table created (or already exists).\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in DB:\n  " . implode("\n  ", $tables);
    echo "\n\n⚠️  Delete this file now: backend/database/run_migration.php</pre>";
} catch (PDOException $e) {
    http_response_code(500);
    echo "<pre>❌ Migration failed: " . htmlspecialchars($e->getMessage()) . "</pre>";
}
