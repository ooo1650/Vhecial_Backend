<?php
/**
 * Mail diagnostic endpoint — DELETE after debugging.
 * Hit: https://your-render-url/test_mail.php
 */
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/config/mail.php';

// 1. Show what env vars are actually loaded
$config = [
    'MAIL_HOST'      => MAIL_HOST,
    'MAIL_PORT'      => MAIL_PORT,
    'MAIL_USERNAME'  => MAIL_USERNAME,
    'MAIL_FROM'      => MAIL_FROM,
    'MAIL_FROM_NAME' => MAIL_FROM_NAME,
    'MAIL_PASSWORD'  => empty(MAIL_PASSWORD) ? '(empty)' : '(set, length=' . strlen(MAIL_PASSWORD) . ')',
];

// 2. Check vendor autoload exists
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];
$autoloadFound = null;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        $autoloadFound = $path;
        break;
    }
}

if (!$autoloadFound) {
    echo json_encode([
        'step'    => 'autoload_check',
        'error'   => 'vendor/autoload.php not found — composer install may not have run',
        'checked' => $autoloadPaths,
        'config'  => $config,
    ]);
    exit;
}

require_once $autoloadFound;

// 3. Try sending a real email
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER; // verbose output
    $mail->Debugoutput = function($str, $level) use (&$debugLog) {
        $debugLog[] = trim($str);
    };

    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;
    $mail->Timeout    = 15;

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_USERNAME); // send test to yourself
    $mail->Subject = 'Mero Gadi — SMTP Test';
    $mail->Body    = 'If you receive this, SMTP is working correctly on Render.';
    $mail->isHTML(false);

    $mail->send();

    echo json_encode([
        'success'      => true,
        'message'      => 'Test email sent successfully',
        'config'       => $config,
        'autoload'     => $autoloadFound,
        'smtp_log'     => $debugLog ?? [],
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success'      => false,
        'error'        => $mail->ErrorInfo,
        'config'       => $config,
        'autoload'     => $autoloadFound,
        'smtp_log'     => $debugLog ?? [],
    ], JSON_PRETTY_PRINT);
}
