<?php
/* ==========================================================================
   TITAN EMAIL ENGINE - SMTP CONFIGURATION (100% HOSTINGER COMPLIANT)
   --------------------------------------------------------------------------
   - Hostinger Official SMTP Server
   - Secure Socket Layer (SSL) Port 465
   ========================================================================== */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) { die('Akses dilarang.'); }

// 1. KREDENSIAL SERVER (WAJIB MENGGUNAKAN SERVER PUSAT HOSTINGER)
define('SMTP_HOST',     'smtp.hostinger.com'); // JANGAN DIGANTI. Ini kunci utama Hostinger.
define('SMTP_USER',     'ilhammm@hvmdigital.id');
define('SMTP_PASS',     'HVM_Digital_2026!'); 
define('SMTP_PORT',     465);
define('SMTP_SECURE',   'ssl');

// 2. IDENTITAS KORPORAT (Harus sama persis dengan SMTP_USER)
define('MAIL_FROM',     'ilhammm@hvmdigital.id');
define('MAIL_NAME',     'HVM Digital Solutions'); // Dipersingkat agar tidak terpotong di layar HP klien
define('MAIL_REPLY_TO', 'ilhammm@hvmdigital.id');

// 3. ATURAN TRANSMISI (Anti-Spam)
define('MAX_EMAILS_PER_BATCH', 1);
define('RETRY_ATTEMPTS', 3);
?>