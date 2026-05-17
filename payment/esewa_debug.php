<?php
/**
 * GET /api/payment/esewa_debug.php?secret=debug123&amount=5000
 * Shows exactly what signature is being generated so you can compare with eSewa docs.
 * DELETE after debugging.
 */
require_once __DIR__ . '/../config/cors.php';

if (($_GET['secret'] ?? '') !== 'debug123') {
    http_response_code(403);
    die('<h2>Forbidden</h2>');
}

$productCode = getenv('ESEWA_PRODUCT_CODE') ?: 'EPAYTEST';
// Sandbox key hardcoded as fallback — same logic as esewa_initiate.php
$secretKeyEnv = getenv('ESEWA_SECRET_KEY');
$secretKey    = ($secretKeyEnv && strlen($secretKeyEnv) >= 10) ? $secretKeyEnv : '8gBm/:&EnhH.';
$gatewayUrl  = getenv('ESEWA_GATEWAY_URL')  ?: 'https://rc-epay.esewa.com.np/api/epay/main/v2/form';
$appUrl      = rtrim(getenv('APP_URL') ?: 'http://localhost:5173', '/');

$amount          = number_format((float)($_GET['amount'] ?? 100), 2, '.', '');
$transactionUuid = 'BK1T' . time();

$signatureMessage = "total_amount={$amount},transaction_uuid={$transactionUuid},product_code={$productCode}";
$signature        = base64_encode(hash_hmac('sha256', $signatureMessage, $secretKey, true));

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
  <title>eSewa Debug</title>
  <style>
    body { font-family: monospace; padding: 30px; background: #0f172a; color: #e2e8f0; }
    h2   { color: #60bb46; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    td, th { border: 1px solid #334155; padding: 10px 14px; text-align: left; }
    th { background: #1e293b; color: #94a3b8; }
    td:first-child { color: #94a3b8; width: 220px; }
    td:last-child { color: #f1f5f9; word-break: break-all; }
    .sig { color: #60bb46; font-weight: bold; }
    form { margin-top: 30px; }
    input[type=submit] {
      background: #60bb46; color: #fff; border: none;
      padding: 12px 28px; font-size: 15px; border-radius: 8px; cursor: pointer;
    }
    .warn { background: #7c2d12; border: 1px solid #ea580c; padding: 12px; border-radius: 8px; margin: 16px 0; }
  </style>
</head>
<body>
  <h2>eSewa Signature Debug</h2>
  <div class="warn">⚠️ Delete this file after debugging: <code>backend/payment/esewa_debug.php</code></div>

  <table>
    <tr><th>Field</th><th>Value</th></tr>
    <tr><td>product_code</td><td><?= htmlspecialchars($productCode) ?></td></tr>
    <tr><td>secret_key (raw env)</td><td><?= htmlspecialchars($secretKeyEnv ?: '(not set — using hardcoded fallback)') ?></td></tr>
    <tr><td>secret_key (used)</td><td><?= htmlspecialchars($secretKey) ?></td></tr>
    <tr><td>secret_key length</td><td><?= strlen($secretKey) ?> chars (expected: 13)</td></tr>
    <tr><td>total_amount</td><td><?= htmlspecialchars($amount) ?></td></tr>
    <tr><td>transaction_uuid</td><td><?= htmlspecialchars($transactionUuid) ?></td></tr>
    <tr><td>gateway_url</td><td><?= htmlspecialchars($gatewayUrl) ?></td></tr>
    <tr><td>success_url</td><td><?= htmlspecialchars($appUrl . '/payment/esewa/success') ?></td></tr>
    <tr><td>failure_url</td><td><?= htmlspecialchars($appUrl . '/payment/esewa/failure') ?></td></tr>
    <tr><td>signed_field_names</td><td>total_amount,transaction_uuid,product_code</td></tr>
    <tr><td>signature_message</td><td><?= htmlspecialchars($signatureMessage) ?></td></tr>
    <tr><td>signature</td><td class="sig"><?= htmlspecialchars($signature) ?></td></tr>
  </table>

  <p>Click below to submit a live test form to eSewa sandbox:</p>

  <form method="POST" action="<?= htmlspecialchars($gatewayUrl) ?>">
    <input type="hidden" name="amount"                   value="<?= htmlspecialchars($amount) ?>">
    <input type="hidden" name="tax_amount"               value="0">
    <input type="hidden" name="total_amount"             value="<?= htmlspecialchars($amount) ?>">
    <input type="hidden" name="transaction_uuid"         value="<?= htmlspecialchars($transactionUuid) ?>">
    <input type="hidden" name="product_code"             value="<?= htmlspecialchars($productCode) ?>">
    <input type="hidden" name="product_service_charge"   value="0">
    <input type="hidden" name="product_delivery_charge"  value="0">
    <input type="hidden" name="success_url"              value="<?= htmlspecialchars($appUrl . '/payment/esewa/success') ?>">
    <input type="hidden" name="failure_url"              value="<?= htmlspecialchars($appUrl . '/payment/esewa/failure') ?>">
    <input type="hidden" name="signed_field_names"       value="total_amount,transaction_uuid,product_code">
    <input type="hidden" name="signature"                value="<?= htmlspecialchars($signature) ?>">
    <input type="submit" value="→ Test Submit to eSewa Sandbox">
  </form>
</body>
</html>
