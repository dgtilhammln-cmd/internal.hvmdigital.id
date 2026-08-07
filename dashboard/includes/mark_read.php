<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(isset($_POST['action']) && $_POST['action'] == 'mark_read') {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    echo "success";
}
?>