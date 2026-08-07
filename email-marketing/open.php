<?php
include_once 'config/database.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Update status dibuka di database
    mysqli_query($conn, "UPDATE email_queue SET opened_at = NOW() WHERE id = $id AND opened_at IS NULL");
}

// Kirim gambar transparan 1x1 pixel agar email tidak terlihat rusak
header('Content-Type: image/png');
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');
exit;