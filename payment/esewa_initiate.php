<?php
/**
 * POST /api/payment/esewa_initiate.php
 *
 * Creates a booking record (status=pending) and returns the signed
 * eSewa ePay V2 form parameters so the frontend can redirect the user
 * to the eSewa sandbox checkout page.
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// ── Config ────────────────────────────────────────────────────────────────
$productCode = getenv('ESEWA_PRODUCT_CODE') ?: 'EPAYTEST';
$gatewayUrl  = getenv('ESEWA_GATEWAY_URL')  ?: 'https://uat.esewa.com.np/api/epay/main/v2/form';
$appUrl      = rtrim(getenv('APP_URL') ?: 'http://localhost:5173', '/');
$secretKey   = '8gBm/:&EnhH.1/q';
// ── Parse request body ────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$email            = trim($body['email']             ?? '');
$vehicle_id       = (int)($body['vehicle_id']       ?? 0);
$start_date       = trim($body['start_date']        ?? '');
$end_date         = trim($body['end_date']          ?? '');
$pickup_location  = trim($body['pickup_location']   ?? '');
$dropoff_location = trim($body['dropoff_location']  ?? '');
$contact_phone    = trim($body['contact_phone']     ?? '');
$total_price      = (float)($body['total_price']    ?? 0);

// ── Validate ──────────────────────────────────────────────────────────────
$errors = [];
if (!$email)           $errors[] = 'Email is required';
if (!$vehicle_id)      $errors[] = 'Vehicle is required';
if (!$start_date)      $errors[] = 'Start date is required';
if (!$end_date)        $errors[] = 'End date is required';
if (!$pickup_location) $errors[] = 'Pick-up location is required';
if (!$contact_phone)   $errors[] = 'Contact phone is required';
if ($total_price <= 0) $errors[] = 'Invalid total price';

if ($start_date && $end_date) {
    $diff = (int)(new DateTime($start_date))->diff(new DateTime($end_date))->days;
    if ($diff > 45) $errors[] = 'Rental period cannot exceed 45 days';
    if ($diff <= 0) $errors[] = 'End date must be after start date';
}

if ($errors) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => implode('. ', $errors)]));
}

// ── Resolve user ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'User not found']));
}

// ── Check vehicle availability ────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id FROM vehicles WHERE id = ? AND available = 1');
$stmt->execute([$vehicle_id]);
if (!$stmt->fetch()) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Vehicle is not available']));
}

// ── Create booking (status = pending, payment_status = unpaid) ───────────
$pdo->prepare("
    INSERT INTO bookings
        (user_id, vehicle_id, start_date, end_date, total_price,
         pickup_location, dropoff_location, contact_phone, payment_method, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'esewa', 'pending')
")->execute([
    $user['id'], $vehicle_id, $start_date, $end_date, $total_price,
    $pickup_location, $dropoff_location ?: null, $contact_phone,
]);
$bookingId = (int)$pdo->lastInsertId();

// ── Unique transaction UUID ───────────────────────────────────────────────
// Keep it short and alphanumeric — no hyphens in the UUID part confuse some parsers
$transactionUuid = 'BK' . $bookingId . 'T' . time();

// ── Create pending payment record ─────────────────────────────────────────
// Wrapped in try/catch — if payments table doesn't exist yet, we still redirect
try {
    $pdo->prepare("
        INSERT INTO payments (booking_id, user_id, amount, payment_method, status, transaction_uuid)
        VALUES (?, ?, ?, 'esewa', 'pending', ?)
    ")->execute([$bookingId, $user['id'], $total_price, $transactionUuid]);
} catch (PDOException $e) {
    // Log but don't block — payments table may not exist yet
    error_log('payments insert failed: ' . $e->getMessage());
}

// ── Build HMAC-SHA256 signature ───────────────────────────────────────────
// eSewa V2 signed message format (exact field order matters):
//   "total_amount=<X>,transaction_uuid=<Y>,product_code=<Z>"
// Amount must be formatted as a plain decimal, no thousands separator.
$totalAmount = number_format($total_price, 2, '.', '');

$signatureMessage = "total_amount={$totalAmount},transaction_uuid={$transactionUuid},product_code={$productCode}";
$signature        = base64_encode(hash_hmac('sha256', $signatureMessage, $secretKey, true));

// ── Debug info (remove in production) ────────────────────────────────────
// Uncomment temporarily if signature keeps failing:
// error_log("eSewa sig msg: $signatureMessage");
// error_log("eSewa secret:  $secretKey");
// error_log("eSewa sig:     $signature");

// ── Return params to frontend ─────────────────────────────────────────────
echo json_encode([
    'success'     => true,
    'booking_id'  => $bookingId,
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
