<?php
/* ==========================================================
   TITAN EMAIL ENGINE - MAILER CORE (PHPMailer Wrapper)
   ========================================================== */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load library PHPMailer (Pastikan folder PHPMailer ada di folder core)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Include Konfigurasi
require_once __DIR__ . '/../config/smtp_config.php';
require_once __DIR__ . '/../templates/layout.php';

/**
 * FUNGSI SAKTI: KIRIM EMAIL TITAN
 */
function sendTitanEmail($to_email, $to_name, $subject, $body_text) {
    $mail = new PHPMailer(true);

    try {
        // --- Server Settings ---
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // --- Recipients ---
        $mail->setFrom(MAIL_FROM, MAIL_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(MAIL_REPLY_TO, MAIL_NAME);

        // --- Content ---
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // Gabungkan Body dengan Template HTML Luxury
        $mail->Body    = get_email_template($subject, $body_text, $to_name);
        $mail->AltBody = strip_tags($body_text); // Versi teks biasa (untuk anti-spam)

        $mail->send();
        return ['status' => true, 'msg' => 'Email terkirim.'];

    } catch (Exception $e) {
        return ['status' => false, 'msg' => "Gagal kirim: {$mail->ErrorInfo}"];
    }
}
?>