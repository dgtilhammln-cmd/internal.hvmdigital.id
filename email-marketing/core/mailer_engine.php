<?php
/* ==========================================================================
   TITAN MAILER ENGINE V8 - THE GHOST PROTOCOL (ULTIMATE BYPASS)
   --------------------------------------------------------------------------
   - Multi-Stage Failover: SMTP SSL (465) -> SMTP TLS (587) -> NATIVE RELAY
   - Humanoid Mimicry: Random micro-sleeps & randomized Message-ID
   - High-Deliverability Headers: Meniru Microsoft Outlook 16.0
   - Automated Tracker Injection: Stealth Open Pixel & Click Proxy
   - Hostinger Firewall Bypass: Envelope Sender Authentication (-f flag)
   ========================================================================== */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Dependencies
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Load Configs
require_once __DIR__ . '/../config/smtp_config.php';
require_once __DIR__ . '/../templates/layout.php';

/**
 * CORE FUNCTION: TRANSMIT EMAIL
 * @return array [status, method, error_log]
 */
function sendTitanEmail($to_email, $to_name, $subject, $body_text, $queue_id) {
    $mail = new PHPMailer(true);
    $base_url = "https://internal.hvmdigital.id/email-marketing";
    
    // 1. PRE-PROCESSING: HUMAN CONTENT GENERATION
    $html = get_email_template($subject, $body_text, $to_name, $queue_id);
    
    // Injeksi Stealth Tracking Pixel (Buka Email)
    $pixel_tag = "<img src='$base_url/open.php?id=$queue_id' width='1' height='1' style='display:none !important; visibility:hidden; opacity:0;'>";
    $html = str_replace("</body>", $pixel_tag . "</body>", $html);
    
    // Injeksi Intelligent Link Tracking (Klik Tombol)
    $track_url = "$base_url/track.php?id=$queue_id";
    $html = str_replace('href="https://hvmdigital.id"', 'href="'.$track_url.'"', $html);

    // TRIK LICIK: Micro-sleep acak (1-3 detik) agar tidak terlihat seperti bom robot
    sleep(rand(1, 3));

    try {
        // ════════════════════════════════════════════════════════════
        // STAGE 1: PRIMARY SMTP (SSL - PORT 465)
        // ════════════════════════════════════════════════════════════
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = 465;
        $mail->Timeout    = 25; // Lebih sabar menghadapi server sibuk
        
        // Hostinger Bypass Options
        $mail->SMTPAutoTLS = false;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom(MAIL_FROM, MAIL_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(MAIL_REPLY_TO, MAIL_NAME);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($body_text);

        // Custom Corporate Headers (Anti-Spam)
        $mail->addCustomHeader("X-Entity-Ref-ID", uniqid());
        $mail->addCustomHeader("X-Priority", "3");
        $mail->addCustomHeader("X-MSMail-Priority", "Normal");
        $mail->addCustomHeader("X-Mailer", "Microsoft Outlook 16.0");

        if($mail->send()) return ['status' => true, 'method' => 'TITAN_SMTP_SSL'];

    } catch (Exception $e) {
        
        try {
            // ════════════════════════════════════════════════════════
            // STAGE 2: SECONDARY SMTP (TLS - PORT 587)
            // ════════════════════════════════════════════════════════
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            if($mail->send()) return ['status' => true, 'method' => 'TITAN_SMTP_TLS'];

        } catch (Exception $e2) {
            
            // ════════════════════════════════════════════════════════
            // STAGE 3: THE GHOST PROTOCOL (PHP NATIVE BYPASS)
            // Jalur Terakhir: Menggunakan Relay Internal Server Hostinger
            // ════════════════════════════════════════════════════════
            return titanSovereignNativeRelay($to_email, $to_name, $subject, $html, $body_text, $queue_id);
        }
    }
}

/**
 * FALLBACK NATIVE: Menerobos Firewall dengan Parameter Envelope Sender
 */
function titanSovereignNativeRelay($to, $name, $sub, $html, $text, $qid) {
    // Generate Unique Message-ID agar dianggap email asli oleh Gmail
    $msg_id = "<" . time() . "." . uniqid() . "@hvmdigital.id>";
    
    // Build Complex Headers
    $headers   = array();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=UTF-8";
    $headers[] = "From: " . MAIL_NAME . " <" . MAIL_FROM . ">";
    $headers[] = "Reply-To: " . MAIL_REPLY_TO;
    $headers[] = "Message-ID: " . $msg_id;
    $headers[] = "X-Mailer: Microsoft Outlook 16.0";
    $headers[] = "X-Auto-Response-Suppress: All";
    $headers[] = "List-Unsubscribe: <https://hvmdigital.id/unsub>";

    // Parameter -f adalah 'Kartu Sakti' agar server Hostinger meloloskan email
    $envelope_sender = "-f " . MAIL_FROM;

    // Kirim menggunakan fungsi asli PHP dengan proteksi @ (suppress error)
    $is_sent = @mail($to, $sub, $html, implode("\r\n", $headers), $envelope_sender);

    if ($is_sent) {
        return ['status' => true, 'method' => 'TITAN_GHOST_RELAY'];
    } else {
        return [
            'status' => false, 
            'msg' => 'Firewall Hostinger memblokir seluruh jalur pengiriman. Silakan hubungi support atau istirahatkan mesin 30 menit.'
        ];
    }
}