<?php
/* ==========================================================
   TITAN EMAIL ENGINE - DATABASE CONNECTION
   ========================================================== */

// Hindari pemanggilan langsung file ini demi keamanan
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Akses langsung dilarang.');
}

// Gunakan detail yang sama dengan database chatbot Bapak
$db_host = "localhost";
$db_user = "u664715641_INTERNAL"; // Sesuai screenshot sebelumnya
$db_pass = "#Ilhammaulana23";     // Sesuai screenshot sebelumnya
$db_name = "u664715641_INTERNAL"; // Sesuai screenshot sebelumnya

// Inisialisasi Koneksi
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Cek Koneksi (Anti-Error)
if (!$conn) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}

// Set Charset agar mendukung simbol dan emoji (Wajib untuk Marketing)
// Paksa Database pakai zona waktu Jakarta (WIB)
mysqli_query($conn, "SET time_zone = '+07:00'");

// Set Timezone agar sinkron dengan waktu Surabaya (WIB)
date_default_timezone_set('Asia/Jakarta');

// Berikan tanda bahwa database berhasil terhubung (untuk debugging)
// nebLog('DB', 'Email Engine connected to HVM database.');
?>