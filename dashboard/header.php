<?php
// Pastikan koneksi database ada
if(!isset($conn)) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
}

// --- LOGIKA TRANSLASI BAHASA INDONESIA ---
$hariInggris = date('l');
$bulanInggris = date('F');

$hariIndo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];

$bulanIndo = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];

// 1. LOGIKA HITUNG MUNDUR KE BULAN DEPAN
$today = new DateTime();
$nextMonth = new DateTime('first day of next month');
$diff = $today->diff($nextMonth);
$daysLeft = $diff->days;

$nextMonthEng = $nextMonth->format('F');
$nextMonthIndo = $bulanIndo[$nextMonthEng];

// 2. QUERY NOTIFIKASI (Ditambahkan filter untuk 'new_client')
$r_notif = mysqli_query($conn, "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
$r_unread = mysqli_query($conn, "SELECT COUNT(*) as total FROM notifications WHERE is_read = 0");
$count_unread = ($r_unread) ? mysqli_fetch_assoc($r_unread)['total'] : 0;

// 3. QUERY KONTRAK
$r_contract = mysqli_query($conn, "SELECT company_name, DATEDIFF(contract_end, CURDATE()) as days FROM clients 
               WHERE status='Active' HAVING days <= 30 ORDER BY days ASC");
$count_contract = ($r_contract) ? mysqli_num_rows($r_contract) : 0;

$total_badge = $count_unread + $count_contract;
?>

<!-- FontAwesome & CSS Tetap Sama -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="/dashboard/header.style.css">

<div class="main-header">
    <div class="header-left">
        <h2 class="page-name">Dashboard Overview</h2>
        <p class="date-display">
            <?php echo $hariIndo[$hariInggris] . ", " . date('d') . " " . $bulanIndo[$bulanInggris] . " " . date('Y'); ?> 
            <span style="color:var(--neon-cyan); font-weight:600;"> | (<?php echo $daysLeft; ?> hari lagi bulan <?php echo $nextMonthIndo; ?>)</span>
        </p>
    </div>

    <div class="header-right">
        <div class="notif-wrapper">
            <div class="notif-trigger" onclick="toggleDropdown('notifDropdown')">
                <i class="fas fa-bell notif-icon <?php echo ($total_badge > 0) ? 'shake-anim' : ''; ?>"></i>
                <?php if($total_badge > 0): ?>
                    <span class="notif-badge" id="mainBadge"><?php echo $total_badge; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="dropdown-glass notif-dropdown" id="notifDropdown">
                <div class="dropdown-header">
                    <span>Notifications</span>
                    <div style="display:flex; gap:12px; align-items:center;">
                        <button onclick="markAllReadHeader(event)" class="btn-mark-read">Mark all read</button>
                        <i class="fas fa-times close-dropdown" onclick="toggleDropdown('notifDropdown')"></i>
                    </div>
                </div>
                <div class="notif-body">
                    <?php if(($r_notif && mysqli_num_rows($r_notif) == 0) && $count_contract == 0): ?>
                        <div class="notif-empty">Tidak ada aktifitas</div>
                    <?php else: ?>
                        
                        <?php if($r_notif): ?>
                            <?php while($row = mysqli_fetch_assoc($r_notif)): ?>
                                <div class="notif-item <?php echo $row['is_read'] ? 'is-read' : ''; ?>">
                                    <div class="notif-title">
                                        <?php 
                                            // LOGIKA BARU: Cek Tipe Notifikasi agar lebih akurat
                                            if($row['type'] == 'income') {
                                                echo '<span style="color:#a1ff5a">💰 Pemasukan</span>';
                                            } elseif($row['type'] == 'expense') {
                                                echo '<span style="color:#ff9f43">💸 Pengeluaran</span>';
                                            } elseif($row['type'] == 'new_client') {
                                                echo '<span style="color:#4efdc4">👤 Client Baru</span>';
                                            } else {
                                                echo '📢 Info';
                                            }
                                        ?>
                                    </div>
                                    <div class="notif-desc"><?php echo $row['message']; ?></div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if($r_contract): ?>
                            <?php while($c = mysqli_fetch_assoc($r_contract)): ?>
                                <div class="notif-item">
                                    <div class="notif-title" style="color:#ff5a5a">⚠️ Alert Kontrak</div>
                                    <div class="notif-desc"><b><?php echo $c['company_name']; ?></b> habis dlm <?php echo $c['days']; ?> hari</div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bagian User Profile ke bawah tetap sama sesuai kode Anda sebelumnya -->
        <div class="user-profile" onclick="toggleDropdown('profileDropdown')">
            <div class="user-info">
                <span class="name"><?php echo $_SESSION['admin'] ?? 'Ilhammaulana'; ?></span>
                <span class="role">Super Admin</span>
            </div>
            <div class="user-avatar">
                <i class="fas fa-user-astronaut"></i>
            </div>
            <div class="dropdown-glass profile-dropdown" id="profileDropdown">
                <div class="profile-header">
                    <i class="fas fa-circle-user"></i>
                    <span>Detail Profil</span>
                </div>
                <a href="/logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> LOGOUT
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDropdown(id) {
    const target = document.getElementById(id);
    if(!target) return;
    const allDropdowns = document.querySelectorAll('.dropdown-glass');
    allDropdowns.forEach(d => { if(d.id !== id) d.classList.remove('active'); });
    target.classList.toggle('active');
}

function markAllReadHeader(e) {
    e.stopPropagation();
    fetch('/dashboard/includes/mark_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_read'
    }).then(response => {
        if(response.ok) {
            document.querySelectorAll('.notif-item').forEach(item => item.classList.add('is-read'));
            const badge = document.getElementById('mainBadge');
            if(badge) badge.style.display = 'none';
            const icon = document.querySelector('.notif-icon');
            if(icon) icon.classList.remove('shake-anim');
        }
    });
}

// Menutup dropdown saat klik di luar
window.addEventListener('click', function(e) {
    if (!e.target.closest('.notif-wrapper') && !e.target.closest('.user-profile')) {
        document.querySelectorAll('.dropdown-glass').forEach(d => d.classList.remove('active'));
    }
});
</script>