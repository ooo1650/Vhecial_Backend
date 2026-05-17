<?php
/**
 * POST /api/payment/esewa_topup.php
 *
 * Initiates an eSewa payment for an existing booking that has no payment
 * or whose total_price changed after editing (additional payment required).
 *
 * Body (JSON):
 *   email, booking_id
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$productCode = getenv('ESEWA_PRODUCT_CODE') ?: 'EPAYTEST';
$secretKey   = '8gBm/:&EnhH.1/q';
$gatewayUrl  = getenv('ESEWA_GATEWAY_URL')  ?: 'https://rc-epay.esewa.com.np/api/epay/main/v2/form';
$appUrl      = rtrim(getenv('APP_URL') ?: 'http://localhost:5173', '/');

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$email      = trim($body['email']      ?? '');
$booking_id = (int)($body['booking_id'] ?? 0);

if (!$email || !$booking_id) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Email and booking_id required']));
}

// ── Verify booking belongs to user ────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT b.*, u.id AS user_id
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ? AND u.email = ?
");
$stmt->execute([$booking_id, $email]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Booking not found']));
}

// ── Calculate amount due ──────────────────────────────────────────────────
// Sum all completed payments for this booking
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_paid
    FROM payments WHERE booking_id = ? AND status = 'completed'
");
$stmt->execute([$booking_id]);
$totalPaid   = (float)$stmt->fetchColumn();
$totalPrice  = (float)$booking['total_price'];
$amountDue   = round($totalPrice - $totalPaid, 2);

if ($amountDue <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'No additional payment required']));
}

// ── Create new pending payment record ────────────────────────────────────
$transactionUuid = 'BK' . $booking_id . 'U' . time();

$pdo->prepare("
    INSERT INTO payments (booking_id, user_id, amount, payment_method, status, transaction_uuid)
    VALUES (?, ?, ?, 'esewa', 'pending', ?)
")->execute([$booking_id, $booking['user_id'], $amountDue, $transactionUuid]);

// ── Build signature ───────────────────────────────────────────────────────
$totalAmount      = number_format($amountDue, 2, '.', '');
$signatureMessage = "total_amount={$totalAmount},transaction_uuid={$transactionUuid},product_code={$productCode}";
$signature        = base64_encode(hash_hmac('sha256', $signatureMessage, $secretKey, true));

echo json_encode([
    'success'     => true,
    'booking_id'  => $booking_id,
    'amount_due'  => $amountDue,
    'gateway_url' => $gatewayUrl,
    'params'      => [
        'amount'                  => $totalAmount,
        'tax_amount'              => '0',
        'total_amount'            => $totalAmount,
        'transaction_uuid'        => $transactionUuid,
        'product_code'            => $productCode,
        'product_service_charge'  => '0',
        'product_delivery_charge' => '0',
        'success_url'             => $appUrl . '/payment/esewa/success',
        'failure_url'             => $appUrl . '/payment/esewa/failure',
        'signed_field_names'      => 'total_amount,transaction_uuid,product_code',
        'signature'               => $signature,
    ],
]);
