<?php
/**
 * Little Stars Pre School — Email OTP Helper
 *
 * Requires PHPMailer (already installed via Composer):
 *   composer require phpmailer/phpmailer
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Generate a random numeric OTP code.
 */
function generateOTP(int $length = OTP_LENGTH): string
{
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

/**
 * Send an OTP code to the given email address via Gmail SMTP.
 *
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendOTPEmail(string $toEmail, string $toName, string $otp): array
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = '🔐 Your Little Stars Login Code';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto;'>
                <h2 style='color:#F06292;'>🌟 Little Stars Pre School</h2>
                <p>Hi {$toName},</p>
                <p>Your one-time login code is:</p>
                <div style='font-size: 32px; font-weight: 900; letter-spacing: 6px;
                            background:#F0F7FF; padding: 16px; text-align:center;
                            border-radius: 12px; color:#2D3A4A;'>
                    {$otp}
                </div>
                <p style='color:#8A9BB0; font-size: 13px;'>
                    This code will expire in " . (OTP_EXPIRY_SECONDS / 60) . " minutes.
                    If you didn't request this, you can safely ignore this email.
                </p>
            </div>
        ";
        $mail->AltBody = "Your Little Stars login code is: {$otp} (expires in " . (OTP_EXPIRY_SECONDS / 60) . " minutes)";

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}