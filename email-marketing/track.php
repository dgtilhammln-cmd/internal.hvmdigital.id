<?php
include_once 'config/database.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // 1. Catat waktu klik di database
    mysqli_query($conn, "UPDATE email_queue SET clicked_at = NOW() WHERE id = $id");

    // 2. Ambil Link Proposal/Compro dari database
    $res = mysqli_query($conn, "SELECT c.attachment_link FROM email_queue q 
                                JOIN email_campaigns c ON q.campaign_id = c.id 
                                WHERE q.id = $id");
    $data = mysqli_fetch_assoc($res);
    $url = (!empty($data['attachment_link'])) ? $data['attachment_link'] : "https://hvmdigital.id";

    // 3. Lempar klien ke link asli
    header("Location: " . $url);
    exit();
}