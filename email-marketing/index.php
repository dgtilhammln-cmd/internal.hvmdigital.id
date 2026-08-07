<?php
/* =========================================
   TITAN EMAIL OS - MAIN FRAME (MODULAR)
   ========================================= */
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

// Proteksi Login
if(!isset($_SESSION['admin'])){ 
    header("Location: /index.php"); exit; 
}

$page = $_GET['page'] ?? 'campaign'; // Halaman default adalah campaign
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Titan Email OS - <?= strtoupper($page) ?></title>
    
    <!-- Favicon & Styles -->
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    <link rel="stylesheet" href="../dashboard/style.css"> <!-- Mengambil CSS Dashboard Utama -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Standarisasi Jarak Titan Email */
        .main-content { 
            margin-left: 100px; 
            padding: 40px; 
            transition: 0.4s ease; 
        }
        
        .glass-card {
            background: rgba(15, 15, 15, 0.7);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 30px;
            padding: 35px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; padding-bottom: 120px; }
        }

        .ambient-glow { 
            position: fixed; border-radius: 50%; filter: blur(120px); opacity: 0.15; z-index: -1; 
        }
        .glow-1 { top: -100px; left: -100px; width: 600px; height: 600px; background: #a1ff5a; }
        .glow-2 { bottom: -100px; right: -100px; width: 600px; height: 600px; background: #4efdc4; }
    </style>
</head>
<body style="background:#050505; color:#fff;">
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <!-- Panggil Sidebar yang sudah kita modifikasi nanti -->
    <?php include 'sidebar-email.php'; ?>

    <main class="main-content">
        <div class="page-container" style="animation: fadeIn 0.8s ease;">
            <?php 
            $file_path = "pages/" . $page . ".php";
            if (file_exists($file_path)) {
                include $file_path;
            } else {
                echo "<div class='glass-card'><h1>404</h1><p>Halaman Titan tidak ditemukan di orbit.</p></div>";
            }
            ?>
        </div>
    </main>

    <script>
        // Fungsi Notifikasi Popup ala HVM
        function showPopup(type, msg) {
            alert(msg); // Sementara pakai alert, nanti kita samakan dengan script popup Bapak
        }
    </script>
</body>
</html>