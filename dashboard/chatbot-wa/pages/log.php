<?php
// log.php - Mata Nebula
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Nebula Monitoring</title>
    <style>
        body { font-family: sans-serif; background: #1a1a2e; color: white; padding: 20px; }
        .chat-box { background: #16213e; padding: 15px; margin-bottom: 10px; border-radius: 8px; }
        .user { color: #4ecca3; font-weight: bold; }
        .assistant { color: #f9ed69; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Logs Chat Nebula</h1>
    <?php
    $logs = mysqli_query($conn, "SELECT * FROM chat_memories ORDER BY id DESC LIMIT 20");
    while($row = mysqli_fetch_assoc($logs)) {
        echo "<div class='chat-box'>";
        echo "<span class='". $row['role'] ."'>". strtoupper($row['role']) ."</span>: " . $row['message'];
        echo "<br><small style='color:gray;'>".$row['created_at']." | ".$row['sender_wa']."</small>";
        echo "</div>";
    }
    ?>
</body>
</html>