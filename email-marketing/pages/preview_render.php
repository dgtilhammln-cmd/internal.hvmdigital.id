<?php
require_once __DIR__ . '/../templates/layout.php';

// Ambil data dari tombol preview via POST
$subject = $_POST['subject'] ?? "Judul Email Sample";
$content = $_POST['content'] ?? "Ini adalah contoh isi pesan yang akan diterima oleh klien Bapak. Pastikan kalimatnya sopan dan profesional.";
$name    = "Bapak Contoh"; // Dummy name untuk preview

// Tampilkan template HTML secara utuh
echo get_email_template($subject, $content, $name);
?>