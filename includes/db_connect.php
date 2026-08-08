<?php
// Larang akses langsung ke file ini
if (!defined('ABSPATH') && basename($_SERVER['PHP_SELF']) === 'db_connect.php') {
    http_response_code(403);
    die('Akses Ditolak.');
}
// Matikan error reporting di layar agar user tidak lihat error aneh
// error_reporting(0); 

$host = "localhost";
$user = "u664715641_INTERNAL";
$pass = "#Ilhammaulana23";
$db   = "u664715641_INTERNAL";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi Gagal: " . mysqli_connect_error());
}
date_default_timezone_set('Asia/Jakarta');
?>