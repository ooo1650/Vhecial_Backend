<?php
/**
 * OTP Helper
 *
 * Rules:
 * - Password must be verified BEFORE calling this (done in signin.php)
 * - Max 3 OTP *requests* per email in any rolling 10-minute window
 *   (counts all rows created in last 10 min, regardless of used status)
 * - Old unused OTPs are deleted before issuing a new one
 */

require_once __DIR__ . '/../config/mailer.php';

function sendOtp(PDO $pdo, string $email, string $name = 'there'): array {

    // Count how many OTPs were *requested* for this email in the last 10 minutes
    // This prevents spamming even if previous ones were used/expired
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM otp_tokens
        WHERE email = ?
          AND created_at > UTC_TIMESTAMP() - INTERVAL 10 MINUTE
    ");
    $stmt->execute([$email]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= 3) {
        // Tell the user exactly when they can try again
        $stmt = $pdo->prepare("
            SELECT created_at FROM otp_tokens
            WHERE email = ?
            ORDER BY created_at ASC LIMIT 1
        ");
        $stmt->execute([$email]);
        $oldest = $stmt->fetchColumn();
        $retry  = $oldest
            ? date('H:i', strtotime($oldest . ' +10 minutes'))
            : 'a few minutes';

        return [
            'success' => false,
            'message' => "Too many verification attempts. You can request a new code after $retry.",
        ];
    }

    // Delete any old unused OTPs for this email (clean up)
    $pdo->prepare("DELETE FROM otp_tokens WHERE email = ? AND used = 0")->execute([$email]);

    // Generate 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $pdo->prepare("
        INSERT INTO otp_tokens (email, otp, expires_at)
        VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 10 MINUTE)
    ")->execute([$email, $otp]);

    // Send email
    $subject = "Your Mero Gadi verification code";
    $message = "Hi $name,\n\nYour one-time verification code is:\n\n    $otp\n\nThis code expires in 10 minutes. Do not share it with anyone.\n\n— Mero Gadi Team";
    $result  = sendMail($email, $subject, $message);

    if (!$result['sent']) {
        // Roll back so this failed attempt doesn't count toward the limit
        $pdo->prepare("DELETE FROM otp_tokens WHERE email = ? AND used = 0")->execute([$email]);
        return [
            'success' => false,
            'message' => 'Failed to send verification email. Please try again.',
            'detail'  => $result['error'],
        ];
    }

    return ['success' => true, 'message' => "Verification code sent to $email"];
}
