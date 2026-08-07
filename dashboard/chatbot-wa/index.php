<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(!isset($_SESSION['admin'])){ header("Location: /index.php"); exit; }

$page = $_GET['page'] ?? 'command'; // Halaman default
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nebula OS - <?= strtoupper($page) ?></title>
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    <link rel="stylesheet" href="../style.css"> <!-- CSS Utama HVM -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Standarisasi Jarak agar tidak mepet */
        .main-content { margin-left: 90px; padding: 40px; transition: 0.4s ease; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 20px; padding-bottom: 120px; } }
        
        /* Animasi Transisi Halaman */
        .page-container { animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body style="background:#050505; color:#fff;">
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <!-- PANGGIL SIDEBAR MODULAR -->
    <?php include 'sidebar-chatbot.php'; ?>

    <main class="main-content">
        <div class="page-container">
            <?php 
            // Routing Halaman
            $file_path = "pages/" . $page . ".php";
            if (file_exists($file_path)) {
                include $file_path;
            } else {
                echo "<h1>Halaman Tidak Ditemukan</h1>";
            }
            ?>
        </div>
    </main>

    <!-- POPUP GLOBAL -->
    <div id="popup" class="popup">
        <i class="fas fa-check-circle"></i>
        <span id="popupMsg"></span>
    </div>

    <script>
        function showPopup(type, msg) {
            const p = document.getElementById('popup');
            document.getElementById('popupMsg').innerText = msg;
            p.className = 'popup ' + type + ' show';
            setTimeout(() => { p.classList.remove('show'); }, 3000);
        }
    </script>
</body>
</html>