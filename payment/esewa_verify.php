<?php
/**
 * POST /api/payment/esewa_verify.php
 *
 * Called by the frontend after eSewa redirects back with a base64-encoded
 * response in the query string (?data=<base64>).
 *
 * Body (JSON):
 *   data  - the raw base64 string from eSewa's success redirect
 *
 * Flow:
 *   1. Decode & parse eSewa response
 *   2. Verify HMAC signature from eSewa
 *   3. Call eSewa status API to double-check
 *   4. Mark payment + booking as confirmed on success
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// ── Load .env ─────────────────────────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

$productCode = $_ENV['ESEWA_PRODUCT_CODE'] ?? 'EPAYTEST';
$secretKey   = $_ENV['ESEWA_SECRET_KEY']   ?? '8gBm/:&EnhH.';
$verifyUrl   = rtrim($_ENV['ESEWA_VERIFY_URL'] ?? 'https://rc.esewa.com.np/api/epay/transaction/status/', '/');

// ── Parse body ────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$encodedData = trim($body['data'] ?? '');

if (!$encodedData) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing eSewa response data']));
}

// ── Decode eSewa response ─────────────────────────────────────────────────
$decoded = base64_decode($encodedData);
if (!$decoded) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid base64 data']));
}

$esewaData = json_decode($decoded, true);
if (!$esewaData) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid JSON in eSewa response']));
}

// Expected fields from eSewa
$transactionCode = $esewaData['transaction_code']  ?? '';
$status          = $esewaData['status']             ?? '';
$totalAmount     = $esewaData['total_amount']       ?? '';
$transactionUuid = $esewaData['transaction_uuid']   ?? '';
$productCode_r   = $esewaData['product_code']       ?? '';
$signedFields    = $esewaData['signed_field_names']  ?? '';
$receivedSig     = $esewaData['signature']           ?? '';

// ── Verify eSewa's signature ──────────────────────────────────────────────
$fieldNames = explode(',', $signedFields);
$parts = [];
foreach ($fieldNames as $field) {
    $field = trim($field);
    $parts[] = "{$field}=" . ($esewaData[$field] ?? '');
}
$signatureMessage = implode(',', $parts);
$expectedSig = base64_encode(hash_hmac('sha256', $signatureMessage, $secretKey, true));

if (!hash_equals($expectedSig, $receivedSig)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Signature verification failed']));
}

// ── Look up payment by transaction_uuid ───────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM payments WHERE transaction_uuid = ?');
$stmt->execute([$transactionUuid]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Payment record not found']));
}

if ($payment['status'] === 'completed') {
    // Already verified (duplicate callback)
    echo json_encode(['success' => true, 'message' => 'Payment already verified', 'booking_id' => $payment['booking_id']]);
    exit;
}

// ── Double-check with eSewa status API ────────────────────────────────────
$statusApiUrl = "{$verifyUrl}/?product_code={$productCode}&transaction_uuid={$transactionUuid}&total_amount={$totalAmount}";

$ch = curl_init($statusApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$apiResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$apiData = json_decode($apiResponse, true);

// eSewa returns status "COMPLETE" on success
$esewaStatus = strtoupper($apiData['status'] ?? '');

if ($httpCode !== 200 || $esewaStatus !== 'COMPLETE') {
    // Mark payment as failed
    $pdo->prepare("UPDATE payments SET status = 'failed', transaction_id = ? WHERE id = ?")
        ->execute([$transactionCode, $payment['id']]);

    http_response_code(402);
    die(json_encode([
        'success' => false,
        'message' => 'eSewa payment verification failed',
        'esewa_status' => $esewaStatus,
    ]));
}

// ── Mark payment as completed ─────────────────────────────────────────────
$pdo->prepare("
    UPDATE payments
    SET status = 'completed',
        transaction_id = ?,
        esewa_ref_id = ?,
        paid_at = NOW()
    WHERE id = ?
")->execute([$transactionCode, $transactionCode, $payment['id']]);

// ── Confirm the booking ───────────────────────────────────────────────────
$pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")
    ->execute([$payment['booking_id']]);

echo json_encode([
    'success'    => true,
    'message'    => 'Payment verified and booking confirmed',
    'booking_id' => $payment['booking_id'],
]);
