<?php
// add_knowledge.php - Panel Admin untuk "Mengajar" Nebula
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

$message = "";

// PROSES SIMPAN DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $topic = mysqli_real_escape_string($conn, $_POST['topic']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $keywords = mysqli_real_escape_string($conn, $_POST['keywords']);

    $sql = "INSERT INTO knowledge_base (topic, content, keywords) VALUES ('$topic', '$content', '$keywords')";
    
    if (mysqli_query($conn, $sql)) {
        $message = "<div style='color:green;'>✅ Data berhasil ditambahkan! Nebula sudah belajar hal baru.</div>";
    } else {
        $message = "<div style='color:red;'>❌ Gagal: " . mysqli_error($conn) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nebula Knowledge Trainer</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f9; }
        .card { background: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        input, textarea { width: 100%; margin: 10px 0; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        button { background: #5c67f2; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🧠 Train Nebula</h2>
        <?= $message ?>
        <form method="POST">
            <label>Topik:</label>
            <input type="text" name="topic" placeholder="Contoh: Harga Paket Desain" required>
            
            <label>Pengetahuan (SOP/Info Lengkap):</label>
            <textarea name="content" rows="5" placeholder="Contoh: Paket desain kami mulai dari 500rb..." required></textarea>
            
            <label>Keywords (Pisahkan dengan koma):</label>
            <input type="text" name="keywords" placeholder="Contoh: harga, biaya, murah, paket" required>
            
            <button type="submit">Simpan ke Memori</button>
        </form>
        <hr>
        <h3>Data yang sudah dipelajari:</h3>
        <ul>
            <?php
            $res = mysqli_query($conn, "SELECT topic FROM knowledge_base");
            while($row = mysqli_fetch_assoc($res)) echo "<li>{$row['topic']}</li>";
            ?>
        </ul>
    </div>
</body>
</html>