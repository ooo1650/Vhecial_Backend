<?php
/**
 * Seed sample data for vehicles, bookings, and payments.
 * Run AFTER setup.sql and migration_v2.sql have been executed.
 *
 * Usage: php backend/database/seed_data.php
 */

require_once __DIR__ . '/../config/db.php';

// ── Seed Vehicles ─────────────────────────────────────────
$vehicles = [
    ['Toyota Corolla', 'car', 'Toyota', 'Corolla', 2023, 'White', 'BA 1 PA 2023', 3500.00, 'available', '', 'Reliable sedan for city and highway driving'],
    ['Honda Dio',      'scooter', 'Honda', 'Dio', 2024, 'Red', 'BA 2 PA 1001', 800.00, 'available', '', 'Fuel-efficient scooter for daily commute'],
    ['Hyundai Creta',  'suv', 'Hyundai', 'Creta', 2024, 'Black', 'BA 1 JA 5050', 5500.00, 'available', '', 'Spacious SUV for family trips'],
    ['Yamaha FZ',      'bike', 'Yamaha', 'FZ-S V3', 2023, 'Blue', 'BA 3 PA 7777', 1200.00, 'available', '', 'Sporty bike with great mileage'],
    ['Suzuki Ertiga',  'van', 'Suzuki', 'Ertiga', 2022, 'Silver', 'BA 1 CHA 3030', 4000.00, 'available', '', '7-seater MPV for group travel'],
];

$stmt = $pdo->prepare("
    INSERT IGNORE INTO vehicles (name, type, brand, model, year, color, plate_number, daily_rate, status, image_url, description)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($vehicles as $v) {
    $stmt->execute($v);
}

echo "Seeded " . count($vehicles) . " vehicles.\n";
echo "Seed complete.\n";
