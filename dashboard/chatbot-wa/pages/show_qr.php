<!DOCTYPE html>
<html>
<head><title>Nebula QR Scanner</title></head>
<body style="text-align:center; font-family:sans-serif; margin-top:50px;">
    <h3>Nebula QR Scanner</h3>
    <?php
    $qrFile = 'qrcode.png';
    if (file_exists($qrFile)) {
        echo '<img src="qrcode.png?t='.time().'" width="300" style="border: 2px solid #ccc;">';
    } else {
        echo '<div style="padding:20px; color:green;">✅ Bot sudah terhubung & Login! <br> Tidak perlu scan QR saat ini.</div>';
    }
    ?>
    <p><small>Halaman ini refresh otomatis setiap 5 detik</small></p>
    <script>
        setInterval(() => { window.location.reload(); }, 5000);
    </script>
</body>
</html>