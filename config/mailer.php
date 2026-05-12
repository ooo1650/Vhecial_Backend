<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/mail.php';

/**
 * Send email via Gmail SMTP.
 * Returns ['sent' => bool, 'error' => string|null]
 */
function sendMail(string $to, string $subject, string $body): array {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->Timeout    = 10;   // fail after 10 seconds instead of hanging

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        $mail->send();
        return ['sent' => true, 'error' => null];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo;
        error_log("Mailer error to $to: $err");
        return ['sent' => false, 'error' => $err];
    }
}
