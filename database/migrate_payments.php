<?php
/**
 * Run this once to create the payments table on the live database.
 * Usage: php database/migrate_payments.php
 */

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'defaultdb';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opts);
    echo "Connected to $db on $host\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

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
    echo "payments table created (or already exists)\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables in $db: " . implode(', ', $tables) . "\n";
