<?php
// dashboard/chatbot-wa/toggle_bot.php
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if ($_POST['chat_id']) {
    $chat_id = mysqli_real_escape_string($conn, $_POST['chat_id']);
    $status = (int)$_POST['status'];
    
    mysqli_query($conn, "INSERT INTO chat_controls (chat_id, bot_enabled) 
                         VALUES ('$chat_id', $status) 
                         ON DUPLICATE KEY UPDATE bot_enabled = $status");
    echo "success";
}
?>