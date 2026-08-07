<?php
session_start();
// Pastikan output selalu JSON dan tidak disimpan di cache browser
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

// Jika tidak ada session, kirim array kosong
if(!isset($_SESSION['admin'])) { 
    echo json_encode([]); 
    exit; 
}

$my_name = mysqli_real_escape_string($conn, $_SESSION['admin']);

// 1. Pastikan kolom last_seen ada dengan tipe yang benar
$check = mysqli_query($conn, "SHOW COLUMNS FROM teams LIKE 'last_seen'");
if(mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "ALTER TABLE teams ADD COLUMN last_seen DATETIME");
}

// 2. Update waktu user ini (Gunakan CURRENT_TIMESTAMP agar sinkron dengan database)
mysqli_query($conn, "UPDATE teams SET last_seen = CURRENT_TIMESTAMP WHERE name = '$my_name'");

// 3. Ambil SEMUA user yang aktif dalam 60 detik terakhir (kita perlonggar sedikit agar lebih stabil)
// Menggunakan DATE_SUB agar lebih kompatibel dengan berbagai versi MySQL
$q = mysqli_query($conn, "SELECT name, photo, role 
                          FROM teams 
                          WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 60 SECOND) 
                          ORDER BY last_seen DESC");

$online_users = [];
if ($q) {
    while($row = mysqli_fetch_assoc($q)){
        // Jika foto kosong, berikan placeholder default
        if(empty($row['photo'])) $row['photo'] = 'default-profile.png';
        
        $online_users[] = [
            'name' => $row['name'],
            'photo' => $row['photo'],
            'role' => $row['role'],
            'is_me' => ($row['name'] === $_SESSION['admin']) // Penanda jika ini adalah akun sendiri
        ];
    }
}

// Kembalikan hasil sebagai JSON
echo json_encode($online_users);
?>