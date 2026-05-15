<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Correctly locate the autoloader
$autoload = file_exists(__DIR__ . '/vendor/autoload.php')
    ? __DIR__ . '/vendor/autoload.php'
    : __DIR__ . '/../vendor/autoload.php';

require_once $autoload;

// 2. Ensure mail.php (containing your MAIL_HOST constants) is loaded
require_once __DIR__ . '/mail.php';

/**
 * Send email via Resend API (HTTPS — works on Render free tier).
 * Falls back to PHPMailer SMTP if RESEND_API_KEY is not set.
 */
function sendMail(string $to, string $subject, string $body): array
{
    $resendKey = getenv('RESEND_API_KEY');

    if ($resendKey) {
        return _sendViaResend($to, $subject, $body, $resendKey);
    }

    // Fallback: PHPMailer SMTP (works locally, blocked on Render free tier)
    return _sendViaSMTP($to, $subject, $body);
}

function _sendViaResend(string $to, string $subject, string $body, string $apiKey): array
{
    $payload = json_encode([
        'from'    => MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'to'      => [$to],
        'subject' => $subject,
        'text'    => $body,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("Resend curl error to $to: $curlErr");
        return ['sent' => false, 'error' => "Curl error: $curlErr"];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sent' => true, 'error' => null];
    }

    $err = $decoded['message'] ?? $decoded['name'] ?? $response;
    error_log("Resend API error to $to (HTTP $httpCode): $err");
    return ['sent' => false, 'error' => "Resend error ($httpCode): $err"];
}

function _sendViaSMTP(string $to, string $subject, string $body): array
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->Timeout    = 10;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        $mail->send();
        return ['sent' => true, 'error' => null];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo;
        error_log("Mailer SMTP error to $to: $err");
        return ['sent' => false, 'error' => $err];
    }
}
