<?php
if(isset($_FILES['qr'])) {
    if(move_uploaded_file($_FILES['qr']['tmp_name'], 'qrcode.png')) {
        echo "Upload Sukses";
    }
}
?>