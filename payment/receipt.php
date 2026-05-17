<?php
/**
 * GET /api/payment/receipt.php?booking_id=X&email=Y
 * Returns booking + payment details for receipt generation.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$booking_id = (int)($_GET['booking_id'] ?? 0);
$email      = trim($_GET['email'] ?? '');

if (!$booking_id || !$email) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'booking_id and email required']));
}

$stmt = $pdo->prepare("
    SELECT b.*,
           u.given_name, u.family_name, u.email AS user_email,
           v.name AS vehicle_name, v.type AS vehicle_type,
           v.fuel_type, v.seats, v.price_per_day,
           (SELECT image_path FROM vehicle_images
            WHERE vehicle_id = v.id AND is_primary = 1 LIMIT 1) AS vehicle_image
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.id = ? AND u.email = ?
");
$stmt->execute([$booking_id, $email]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Booking not found']));
}

// Get all completed payments
$stmt = $pdo->prepare("
    SELECT * FROM payments
    WHERE booking_id = ? AND status = 'completed'
    ORDER BY paid_at ASC
");
$stmt->execute([$booking_id]);
$payments = $stmt->fetchAll();

if ($booking['vehicle_image'] && !str_starts_with($booking['vehicle_image'], 'http')) {
    $booking['vehicle_image'] = '/api/' . $booking['vehicle_image'];
}

echo json_encode([
    'success'  => true,
    'booking'  => $booking,
    'payments' => $payments,
]);
