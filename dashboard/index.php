<?php
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(!isset($_SESSION['admin'])){ header("Location: /"); exit; }
// EVENT SAVE
if(isset($_POST['save_event'])){
    $title = mysqli_real_escape_string($conn, $_POST['event_title']);
    $desc = mysqli_real_escape_string($conn, $_POST['event_desc']);
    $date = $_POST['event_date'];
    $start = $_POST['time_start'];
    $color = $_POST['event_color'];
    mysqli_query($conn, "INSERT INTO events (title, detail, event_date, time_start, color) VALUES ('$title', '$desc', '$date', '$start', '$color')");
    header("Location: /dashboard/"); exit;
}

// --- 1. USER & SECURITY ---
$user_logged = $_SESSION['admin'];
$role = $_SESSION['role'] ?? 'staff';
$is_super = ($role === 'super_admin');
$sensor_class = $is_super ? '' : 'sensor-text';

// Profile Image
$q_u = mysqli_query($conn, "SELECT photo FROM teams WHERE name = '$user_logged' LIMIT 1");
$u_data = mysqli_fetch_assoc($q_u);
$profile_img = ($u_data && $u_data['photo']) ? '/uploads/teams/'.$u_data['photo'] : null;

$quotes = [
    "Kalau kamu tidak siap gagal, kamu tidak siap sukses.",
    "Jangan pernah bertarung jika tidak perlu. Tapi kalau harus, pastikan kamu menang.",
    "Orang yang hanya punya rencana tidak akan pernah sampai ke mana-mana. Yang punya eksekusi, baru mulai.",
    "Inovasi tidak datang dari rasa nyaman. Inovasi datang dari keberanian untuk menghancurkan yang biasa.",
    "Mimpi besar itu gratis. Yang mahal adalah berani mulai.",
    "Kamu boleh tidur 8 jam, tapi ingat: kompetitormu mungkin hanya tidur 6 jam.",
    "Jika sesuatu penting, lakukan meskipun tidak populer.",
    "Kegagalan adalah pilihan. Berhenti juga pilihan. Pilih yang membuatmu lebih kuat.",
    "Tidak ada yang instan kecuali mie. Sukses butuh waktu, konsistensi, dan nyali.",
    "Jangan jadi penonton di hidupmu sendiri. Ambil kendali atau dikendalikan orang lain.",
    "Kalau kamu tidak membuat produkmu sendiri yang lebih baik, orang lain akan melakukannya.",
    "Orang skeptis akan selalu ada. Tugasmu bukan meyakinkan mereka, tapi membuktikan mereka salah.",
    "Jangan tunggu sempurna. Rilis, evaluasi, perbaiki. Ulangi.",
    "Hari ini lebih baik dari kemarin, besok lebih baik dari hari ini. Itu cukup.",
    "Orang sukses bukan yang tidak pernah gagal, tapi yang bangun lebih cepat setelah gagal.",
    "Kamu tidak perlu jadi genius. Kamu cukup jadi orang yang tidak pernah menyerah.",
    "Jangan bilang tidak bisa sebelum mencoba. Karena 'tidak bisa' hanya milik mereka yang berhenti.",
    "Fokus pada apa yang bisa kamu kendalikan. Sisanya? Jalan saja.",
    "Kalau kamu mengerjakan hal yang sama dengan orang lain, hasilmu akan biasa saja.",
    "Jangan pernah menjual mimpi. Jual solusi yang membuat mimpi itu tercapai.",
    "Tantangan bukan penghalang. Tantangan adalah cara alam menyaring yang lemah.",
    "Jangan biarkan ketakutan membuatmu memilih jalan aman. Jalan aman jarang membawa ke tempat besar.",
    "Kritik itu gratis. Tapi hasil harus dibayar dengan kerja keras.",
    "Setiap hari adalah kesempatan baru untuk mengalahkan versi dirimu yang kemarin.",
    "Jangan kaget jika mereka iri. Tunjukkan saja hasilnya, biarkan mereka belajar.",
    "Tidak ada yang mustahil. Yang ada adalah belum menemukan caranya.",
    "Kalau kamu bisa membayangkannya, kamu bisa membangunnya. Tapi hanya jika kamu mulai.",
    "Hidup bukan tentang berapa kali kamu jatuh. Tapi tentang berapa kali kamu memilih bangun.",
    "Orang biasa lihat rintangan. Orang luar biasa lihat rintangan lalu menerobosnya.",
    "Kamu tidak akan pernah tahu sekuat apa dirimu sampai kamu terpaksa menjadi kuat."
];
$today_quote = $quotes[array_rand($quotes)];

// --- 2. NOTIFIKASI SYSTEM ---
$q_notif = mysqli_query($conn, "SELECT * FROM notifications WHERE type != 'system' ORDER BY created_at DESC LIMIT 10");
$unread_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM notifications WHERE is_read=0"))['c'];

// --- 3. DASHBOARD STATS ---
$target_bulanan = 40000000;
$bulan_ini = date('m'); $tahun_ini = date('Y');

$q_achieve = mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE MONTH(payment_date) = '$bulan_ini' AND YEAR(payment_date) = '$tahun_ini'");
$achieved = mysqli_fetch_assoc($q_achieve)['total'] ?? 0;
$persen_target = ($target_bulanan > 0) ? ($achieved / $target_bulanan) * 100 : 0;
$sisa_target = max(0, $target_bulanan - $achieved);

// 3 Level Target
if ($achieved < 10000000) {
    $level_label = 'MALES';
    $level_emoji = '😴';
    $level_color = '#ff5a5a';
    $level_glow  = 'rgba(255,90,90,0.4)';
    $level_bg    = 'rgba(255,90,90,0.08)';
    $level_icon  = 'fa-moon';
    $level_msg   = 'PEMALAS!';
    $tier        = 'males';
} elseif ($achieved < 40000000) {
    $level_label = 'BIASA AJA';
    $level_emoji = '😐';
    $level_color = '#f5c518';
    $level_glow  = 'rgba(245,197,24,0.4)';
    $level_bg    = 'rgba(245,197,24,0.08)';
    $level_icon  = 'fa-bolt';
    $level_msg   = 'Mulai panas. Tapi kalau cuma segini mah kurang. Gas terus!';
    $tier        = 'biasa';
} else {
    $level_label = 'GACOR';
    $level_emoji = '🔥';
    $level_color = '#a1ff5a';
    $level_glow  = 'rgba(161,255,90,0.45)';
    $level_bg    = 'rgba(161,255,90,0.08)';
    $level_icon  = 'fa-fire';
    $level_msg   = 'Ilham Kamu Berbahaya🔥';
    $tier        = 'gacor';
}

// Clients
$q_new = mysqli_query($conn, "SELECT COUNT(*) as c FROM clients WHERE MONTH(created_at) = '$bulan_ini' AND YEAR(created_at) = '$tahun_ini'");
$new_clients = mysqli_fetch_assoc($q_new)['c'];

// Services count + client lists
function getSvc($conn, $k) {
    $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM clients WHERE status='Active' AND contract_type LIKE '%$k%'");
    return mysqli_fetch_assoc($r)['c'];
}
function getClientList($conn, $k) {
    $r = mysqli_query($conn, "SELECT company_name FROM clients WHERE status='Active' AND contract_type LIKE '%$k%' ORDER BY created_at DESC LIMIT 8");
    $out = [];
    while($row = mysqli_fetch_assoc($r)) $out[] = $row['company_name'];
    return $out;
}
$web  = getSvc($conn, 'Web');   $web_clients  = getClientList($conn, 'Web');
$soc  = getSvc($conn, 'Social'); $soc_clients  = getClientList($conn, 'Social');
$seo  = getSvc($conn, 'SEO');   $seo_clients  = getClientList($conn, 'SEO');
$cont = getSvc($conn, 'Content'); $cont_clients = getClientList($conn, 'Content');

// --- 4. UPCOMING DEADLINE ---
$q_dead = mysqli_query($conn, "SELECT company_name, DATEDIFF(contract_end, NOW()) as sisa FROM clients WHERE status='Active' HAVING sisa BETWEEN 0 AND 14 ORDER BY sisa ASC LIMIT 5");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HVM Dashboard</title>
    <link rel="shortcut icon" href="/uploads/icon.png">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* --- PLANNER / CALENDAR STYLES --- */
        .zenith-grid-layout { display: grid; grid-template-columns: 1fr; gap: 25px; margin-bottom: 25px; }
        .planner-deck { min-height: 400px; padding: 25px; position: relative; width: 100%; display: flex; flex-direction: column; gap: 15px; }
        .panel-header-v30 { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .ph-left h2 { font-size: 1.8rem; font-weight: 900; margin: 0; }
        .ph-nav-group { display: flex; align-items: center; gap: 15px; margin-top: 10px; }
        .btn-today-v30 { background: #fff; color: #000; padding: 8px 20px; border-radius: 50px; font-weight: 800; font-size: 0.7rem; cursor: pointer; border: none; }
        .nav-arrow-v30 { width: 35px; height: 35px; border-radius: 50%; background: rgba(255,255,255,0.05); color: #fff; border: 1px solid var(--card-border); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .nav-arrow-v30:hover { background: var(--neon-main); color: #000; }
        .arrow-nav-v30 { display: flex; gap: 10px; }
        .mode-switch-v30 { background: rgba(255,255,255,0.05); padding: 5px; border-radius: 15px; display: flex; }
        .mode-switch-v30 button { background: none; border: none; color: #888; padding: 8px 15px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; cursor: pointer; }
        .mode-switch-v30 button.active { background: #fff; color: #000; }
        .planner-viewport { flex: 1; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 16px; padding: 15px; border: 1px solid var(--card-border); position: relative; min-height: 400px; max-height: 600px; }
        .add-event-fab { position: absolute; bottom: 25px; right: 25px; width: 55px; height: 55px; border-radius: 50%; background: var(--neon-main); color: #000; font-size: 1.6rem; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 0 30px var(--neon-main); transition: 0.3s; z-index: 10; border: none; }
        .add-event-fab:hover { transform: scale(1.1) rotate(90deg); box-shadow: 0 0 50px var(--neon-main); }
        /* --- CALENDAR GRIDS --- */
        .cal-grid-month, .cal-grid-week { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; min-width: 800px; }
        .cal-day-header { text-align: center; font-weight: 700; color: #666; margin-bottom: 10px; font-size: 0.8rem; }
        .cal-day-cell { min-height: 90px; background: rgba(255,255,255,0.02); border-radius: 10px; border: 1px solid var(--card-border); padding: 8px; cursor: pointer; transition: 0.3s; display: flex; flex-direction: column; gap: 4px; }
        .cal-day-cell:hover { background: rgba(255,255,255,0.05); border-color: var(--neon-main); }
        .cal-day-num { font-weight: 800; font-size: 1rem; color: #aaa; margin-bottom: 5px; }
        .cal-day-cell.is-sunday .cal-day-num { color: var(--neon-red); }
        .cal-day-cell.is-today { background: rgba(161, 255, 90, 0.05); border-color: var(--neon-main); }
        .cal-day-cell.is-today .cal-day-num { color: var(--neon-main); }
        .cal-event { font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; background: var(--neon-sec); color: #000; font-weight: 700; cursor: pointer; border-left: 3px solid rgba(0,0,0,0.3); transition: 0.2s; }
        .cal-event:hover { transform: scale(1.05); z-index: 5; }
        .cal-event.blue { background: #4efdc4; }
        .cal-event.purple { background: #a55eea; color: #fff; }
        .cal-event.green { background: #a1ff5a; color: #000; }
        .cal-event.red { background: #ff5a5a; color: #fff; }
        .cal-grid-day { display: flex; flex-direction: column; gap: 10px; }
        .cal-hour-row { display: flex; gap: 15px; padding: 15px; border-bottom: 1px solid var(--card-border); align-items: center; }
        .cal-time { width: 60px; font-weight: 700; color: var(--neon-main); }
        .cal-task-area { flex: 1; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 8px; }
        /* MODALS */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 100000; display: none; justify-content: center; align-items: center; backdrop-filter: blur(10px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.3s; }
        .modal-content { background: #0a0a0a; border: 1px solid var(--neon-main); width: 500px; max-width: 95%; padding: 30px; border-radius: 24px; box-shadow: 0 0 60px rgba(161, 255, 90, 0.15); max-height: 90vh; overflow-y: auto; position: relative; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; color: #aaa; margin-bottom: 5px; font-size: 0.8rem; }
        .form-input { width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--card-border); color: #fff; border-radius: 8px; outline: none; font-size: 0.9rem; }
        .modal-top-actions { position: absolute; top: 20px; right: 20px; display: flex; gap: 15px; }
        .btn-close-x { background: none; border: none; color: #555; font-size: 1.5rem; cursor: pointer; transition: 0.3s; }
        .btn-close-x:hover { color: var(--neon-red); transform: rotate(90deg); }
        .btn-save-center { width: 60px; height: 60px; border-radius: 50%; background: var(--grad-main); color: #000; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; margin: 20px auto 0 auto; box-shadow: 0 0 20px var(--neon-main); transition: 0.3s; }
        .btn-save-center:hover { transform: scale(1.1); box-shadow: 0 0 40px var(--neon-main); }
        .detail-row { margin-bottom: 15px; border-bottom: 1px solid var(--card-border); padding-bottom: 10px; }
        .detail-label { font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 5px; }
        .detail-val { font-size: 1.1rem; color: #fff; font-weight: 700; }
        .detail-desc { background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; color: #ccc; font-size: 0.9rem; line-height: 1.6; white-space: pre-line; border-left: 3px solid var(--neon-sec); }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    </style>
</head>
<body>

    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="dashboard-wrapper">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">

            <!-- ══ HEADER ══ -->
            <div class="main-header">
                <div class="headline">
                    <h1>Selamat Datang, <span><?php echo htmlspecialchars($user_logged); ?>!</span></h1>
                    <div class="page-headline" style="margin:0; animation:none;"><p>"<?php echo $today_quote; ?>"</p></div>
                </div>

                <div class="header-right">
                    <!-- Notification Bell -->
                    <div class="notif-wrapper" onclick="toggleNotif()">
                        <div class="notif-icon"><i class="fas fa-bell"></i></div>
                        <?php if($unread_count > 0): ?><span class="notif-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-header"><h4>Recent Activity</h4><button onclick="markRead()" class="btn-mark-read" style="background:none;border:none;color:var(--neon-main);cursor:pointer;">Mark Read</button></div>
                            <div class="notif-list">
                                <?php if(mysqli_num_rows($q_notif) == 0): ?>
                                    <div style="padding:20px;text-align:center;color:#666;">Tidak ada notifikasi.</div>
                                <?php else: ?>
                                    <?php while($nt = mysqli_fetch_assoc($q_notif)):
                                        $typeClass=''; $icon='';
                                        if($nt['type']=='income'){$typeClass='n-income';$icon='fa-arrow-down';}
                                        elseif($nt['type']=='expense'){$typeClass='n-expense';$icon='fa-arrow-up';}
                                        else{$typeClass='n-client';$icon='fa-user-plus';}
                                        $msg_class = $is_super ? '' : 'sensor-text';
                                    ?>
                                    <div class="notif-item <?php echo $nt['is_read']?'is-read':''; ?>">
                                        <div class="n-icon <?php echo $typeClass; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                                        <div class="n-info">
                                            <h5><?php echo ucfirst($nt['type']); ?></h5>
                                            <p class="<?php echo $msg_class; ?>"><?php echo htmlspecialchars($nt['message']); ?></p>
                                            <span class="n-time"><?php echo date('d M H:i', strtotime($nt['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- AI Tool Buttons -->
                    <a href="/dashboard/chatbot-wa/" class="btn-ai btn-wa">
                        <i class="fab fa-whatsapp"></i><span>WA Bot</span>
                    </a>
                    <a href="/email-marketing/" class="btn-ai btn-email">
                        <i class="fas fa-paper-plane"></i><span>AI Email</span>
                    </a>

                    <a href="/dashboard/workspace/" class="btn-workspace">
                        <i class="fas fa-briefcase"></i> <span>Workspace</span>
                    </a>

                    <div class="profile-box">
                        <img src="<?= $profile_img ?? 'https://ui-avatars.com/api/?name='.$user_logged ?>" alt="User">
                    </div>
                </div>
            </div>

            <!-- ══ TOP DECK ══ -->
            <div class="top-deck">
                <div class="apple-widget">
                    <div class="widget-time">
                        <span id="clock" class="time-text">00:00</span>
                        <span id="seconds" class="time-sec">00</span>
                    </div>
                    <div class="widget-date">
                        <div id="dayName" class="date-day">Minggu</div>
                        <div id="fullDate" class="date-full">01 Januari 2025</div>
                    </div>
                </div>
                <div class="upcoming-card">
                    <div class="uc-header">UPCOMING DEADLINE</div>
                    <?php if(mysqli_num_rows($q_dead) > 0): ?>
                        <?php while($d = mysqli_fetch_assoc($q_dead)): ?>
                        <div class="uc-item">
                            <span class="uc-name"><?php echo htmlspecialchars($d['company_name']); ?></span>
                            <span class="uc-days">H-<?php echo $d['sisa']; ?></span>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color:#666;font-size:0.85rem;text-align:center;padding:15px 0;">No urgent deadlines.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ MONTHLY TARGET PREMIUM CARD ══ -->
            <div class="target-premium-card tier-<?php echo $tier; ?>"
                 style="--tier-color:<?php echo $level_color;?>;--tier-glow:<?php echo $level_glow;?>;--tier-bg:<?php echo $level_bg;?>;">

                <div class="tp-header">
                    <div class="tp-label">MONTHLY TARGET</div>
                    <div class="tp-badge" style="background:<?php echo $level_bg;?>;border-color:<?php echo $level_color;?>;color:<?php echo $level_color;?>;">
                        <i class="fas <?php echo $level_icon;?>"></i>&nbsp;<?php echo $level_emoji; ?> <?php echo $level_label;?>
                    </div>
                </div>

                <div class="tp-amount-row">
                    <div class="tp-achieved <?php echo $sensor_class; ?>">
                        Rp <?php echo number_format($achieved/1000000, 1); ?><span class="tp-unit">jt</span>
                    </div>
                    <div class="tp-separator">/</div>
                    <div class="tp-goal">
                        Rp <?php echo number_format($target_bulanan/1000000, 0); ?><span class="tp-unit">jt</span>
                    </div>
                </div>

                <!-- 3-Level Track -->
                <div class="tp-track-wrap">
                    <div class="tp-track">
                        <div class="tp-zone zone-red"    style="width:25%;left:0"></div>
                        <div class="tp-zone zone-yellow" style="width:25%;left:25%"></div>
                        <div class="tp-zone zone-green"  style="width:50%;left:50%"></div>
                        <div class="tp-fill" id="tpFill" style="width:0%"></div>
                        <div class="tp-thumb" id="tpThumb" style="left:0%">
                            <div class="tp-thumb-inner"></div>
                        </div>
                    </div>
                    <div class="tp-milestones">
                        <div class="tp-milestone" style="left:0%">
                            <div class="ms-dot ms-start"></div>
                            <div class="ms-info"><span class="ms-label">0</span></div>
                        </div>
                        <div class="tp-milestone" style="left:25%">
                            <div class="ms-dot <?php echo ($achieved>=10000000)?'ms-done ms-red':'ms-red-empty'; ?>"></div>
                            <div class="ms-info">
                                <span class="ms-label">10jt</span>
                                <span class="ms-tag" style="color:#ff5a5a;">Males</span>
                            </div>
                        </div>
                        <div class="tp-milestone" style="left:50%">
                            <div class="ms-dot <?php echo ($achieved>=20000000)?'ms-done ms-yellow':'ms-yellow-empty'; ?>"></div>
                            <div class="ms-info">
                                <span class="ms-label">20jt</span>
                                <span class="ms-tag" style="color:#f5c518;">Biasa</span>
                            </div>
                        </div>
                        <div class="tp-milestone" style="left:100%">
                            <div class="ms-dot ms-end <?php echo ($achieved>=40000000)?'ms-done ms-gacor':''; ?>">
                                <?php if($achieved>=40000000): ?><i class="fas fa-fire" style="font-size:0.55rem;color:#000;"></i><?php endif; ?>
                            </div>
                            <div class="ms-info">
                                <span class="ms-label">40jt</span>
                                <span class="ms-tag" style="color:#a1ff5a;">GACOR</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tp-footer">
                    <div class="tp-stat">
                        <span class="tp-stat-label">Progress</span>
                        <span class="tp-stat-val" style="color:<?php echo $level_color;?>"><?php echo number_format(min(100,$persen_target),1); ?>%</span>
                    </div>
                    <div class="tp-stat-center">
                        <span class="tp-msg"><?php echo $level_msg; ?></span>
                    </div>
                    <div class="tp-stat" style="text-align:right;">
                        <span class="tp-stat-label">Gap ke Target</span>
                        <span class="tp-stat-val <?php echo $sensor_class; ?>" style="color:var(--neon-red)">
                            <?php echo $sisa_target > 0 ? 'Rp '.number_format($sisa_target/1000000,1).'jt' : 'DONE! 🎉'; ?>
                        </span>
                    </div>
                </div>
                </div>
            </div>

            <!-- ══ PLANNER / CALENDAR ══ -->
            <div class="zenith-grid-layout" style="margin-top: 25px;">
                <!-- PANEL 1: PLANNER -->
                <div class="zenith-panel glass-card planner-deck animate-slide-up" style="background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 30px; padding: 30px; position: relative;">
                    <div class="panel-header-v30">
                        <div class="ph-left">
                            <h2 id="plannerTitle">...</h2>
                            <div class="ph-nav-group">
                                <button class="btn-today-v30" onclick="goToday()">TODAY</button>
                                <div class="arrow-nav-v30">
                                    <button onclick="navigatePlanner(-1)" class="nav-arrow-v30"><i class="fas fa-chevron-left"></i></button>
                                    <button onclick="navigatePlanner(1)" class="nav-arrow-v30"><i class="fas fa-chevron-right"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="ph-right">
                            <div class="mode-switch-v30">
                                <button id="btn-month" class="active" onclick="setMode('month', this)">Month</button>
                                <button id="btn-week" onclick="setMode('week', this)">Week</button>
                                <button id="btn-day" onclick="setMode('day', this)">Day</button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="calendarViewport" class="planner-viewport" style="width: 100%; display: flex; flex-direction: column; gap: 10px;"></div>
                    
                    <!-- Floating Add Button -->
                    <button class="add-event-fab" onclick="openEventModal()"><i class="fas fa-plus"></i></button>
                </div>
            </div>

            <!-- ══ STATS ROW ══ -->
            <div class="stats-row">
                <div class="glass-card stat-mini">
                    <div class="card-label">NEW CLIENTS</div>
                    <div class="small-val text-green">+<?php echo $new_clients; ?></div>
                    <p style="font-size:0.8rem;color:#aaa;">Converted this month</p>
                </div>
                <div class="glass-card stat-mini">
                    <div class="card-label">RETENTION RATE</div>
                    <div class="small-val text-cyan">98.4%</div>
                    <p style="font-size:0.8rem;color:#aaa;">High trust clients</p>
                </div>
                <!-- WA Bot CTA -->
                <div class="glass-card stat-mini ai-cta-card" onclick="window.location='/dashboard/wa-bot/'">
                    <div class="ai-cta-icon wa-icon"><i class="fab fa-whatsapp"></i></div>
                    <div class="card-label" style="color:#25D366;margin-top:8px;">WA CHATBOT</div>
                    <p style="font-size:0.78rem;color:#aaa;margin-top:3px;">Auto reply &amp; broadcast</p>
                    <div class="ai-cta-arrow"><i class="fas fa-arrow-right"></i></div>
                </div>
                <!-- AI Email CTA -->
                <div class="glass-card stat-mini ai-cta-card" onclick="window.location='/dashboard/ai-email/'">
                    <div class="ai-cta-icon email-icon"><i class="fas fa-paper-plane"></i></div>
                    <div class="card-label" style="color:#4efdc4;margin-top:8px;">AI EMAIL BLAST</div>
                    <p style="font-size:0.78rem;color:#aaa;margin-top:3px;">Smart email marketing</p>
                    <div class="ai-cta-arrow"><i class="fas fa-arrow-right"></i></div>
                </div>
            </div>

            <!-- ══ SERVICES + CLIENT LIST ══ -->
            <div class="services-section">
                <div class="section-title">ACTIVE CLIENTS BY SERVICE</div>
                <div class="services-grid-v2">

                    <!-- WEB -->
                    <div class="svc-card-v2 svc-web">
                        <div class="svc-head">
                            <div class="svc-icon-wrap web-icon-clr"><i class="fas fa-globe"></i></div>
                            <div>
                                <div class="svc-name">WEB</div>
                                <div class="svc-count"><?php echo $web; ?> klien aktif</div>
                            </div>
                        </div>
                        <div class="svc-client-list">
                            <?php if(empty($web_clients)): ?>
                                <div class="svc-empty">Belum ada klien aktif</div>
                            <?php else: foreach($web_clients as $cn): ?>
                                <div class="svc-client-item">
                                    <span class="svc-dot web-dot"></span>
                                    <span class="svc-client-name"><?php echo htmlspecialchars($cn); ?></span>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- SOCIAL -->
                    <div class="svc-card-v2 svc-social">
                        <div class="svc-head">
                            <div class="svc-icon-wrap social-icon-clr"><i class="fas fa-hashtag"></i></div>
                            <div>
                                <div class="svc-name">SOCIAL</div>
                                <div class="svc-count"><?php echo $soc; ?> klien aktif</div>
                            </div>
                        </div>
                        <div class="svc-client-list">
                            <?php if(empty($soc_clients)): ?>
                                <div class="svc-empty">Belum ada klien aktif</div>
                            <?php else: foreach($soc_clients as $cn): ?>
                                <div class="svc-client-item">
                                    <span class="svc-dot social-dot"></span>
                                    <span class="svc-client-name"><?php echo htmlspecialchars($cn); ?></span>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- SEO -->
                    <div class="svc-card-v2 svc-seo">
                        <div class="svc-head">
                            <div class="svc-icon-wrap seo-icon-clr"><i class="fas fa-search"></i></div>
                            <div>
                                <div class="svc-name">SEO</div>
                                <div class="svc-count"><?php echo $seo; ?> klien aktif</div>
                            </div>
                        </div>
                        <div class="svc-client-list">
                            <?php if(empty($seo_clients)): ?>
                                <div class="svc-empty">Belum ada klien aktif</div>
                            <?php else: foreach($seo_clients as $cn): ?>
                                <div class="svc-client-item">
                                    <span class="svc-dot seo-dot"></span>
                                    <span class="svc-client-name"><?php echo htmlspecialchars($cn); ?></span>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- CONTENT -->
                    <div class="svc-card-v2 svc-content">
                        <div class="svc-head">
                            <div class="svc-icon-wrap content-icon-clr"><i class="fas fa-camera"></i></div>
                            <div>
                                <div class="svc-name">CONTENT</div>
                                <div class="svc-count"><?php echo $cont; ?> klien aktif</div>
                            </div>
                        </div>
                        <div class="svc-client-list">
                            <?php if(empty($cont_clients)): ?>
                                <div class="svc-empty">Belum ada klien aktif</div>
                            <?php else: foreach($cont_clients as $cn): ?>
                                <div class="svc-client-item">
                                    <span class="svc-dot content-dot"></span>
                                    <span class="svc-client-name"><?php echo htmlspecialchars($cn); ?></span>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                </div>
            </div>

        </main>
    </div>

    <!-- MODAL ADD EVENT -->
    <div class="modal-overlay" id="eventModal">
        <div class="modal-content">
            <div class="modal-top-actions">
                <button class="btn-close-x" onclick="document.getElementById('eventModal').classList.remove('active')">&times;</button>
            </div>
            <h3 style="color:#fff; margin-bottom:20px; font-weight:800; font-size:1.5rem;">Add Event</h3>
            
            <form method="POST">
                <input type="hidden" name="save_event" value="1">
                <div class="form-group"><label>Event Title</label><input type="text" name="event_title" class="form-input" required></div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Date</label><input type="date" name="event_date" id="formDate" class="form-input" required></div>
                    <div class="form-group"><label>Time</label><input type="time" name="time_start" class="form-input"></div>
                </div>
                
                <div class="form-group"><label>Description</label><textarea name="event_desc" class="form-input" rows="3" style="resize:none;"></textarea></div>
                
                <div class="form-group"><label>Color Label</label>
                    <select name="event_color" class="form-input" style="background:#000;">
                        <option value="blue">Cyan (Work)</option>
                        <option value="purple">Purple (Urgent)</option>
                        <option value="green">Neon Green (Meeting)</option>
                        <option value="red">Red (Deadline)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-save-center"><i class="fas fa-check"></i></button>
            </form>
        </div>
    </div>

    <!-- MODAL VIEW DETAIL -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-content" style="border-color: var(--neon-sec);">
            <div class="modal-top-actions">
                <button class="btn-close-x" onclick="closeModal('detailModal')">&times;</button>
            </div>
            <h2 class="modal-title" style="color:var(--neon-sec); margin-bottom:20px;">Project Detail</h2>
            <div id="detailContent"></div>
        </div>
    </div>

    <script>
        // Clock
        function updateClock() {
            const now = new Date();
            const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            const h = String(now.getHours()).padStart(2,'0');
            const m = String(now.getMinutes()).padStart(2,'0');
            const s = String(now.getSeconds()).padStart(2,'0');
            document.getElementById('clock').innerText = `${h}:${m}`;
            document.getElementById('seconds').innerText = s;
            document.getElementById('dayName').innerText = days[now.getDay()];
            document.getElementById('fullDate').innerText = `${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Animate progress bar on load
        window.addEventListener('load', function() {
            var pct = <?php echo min(100, round($persen_target, 2)); ?>;
            var fill  = document.getElementById('tpFill');
            var thumb = document.getElementById('tpThumb');
            if (fill && thumb) {
                setTimeout(function() {
                    fill.style.transition  = 'width 1.5s cubic-bezier(0.4,0,0.2,1)';
                    thumb.style.transition = 'left 1.5s cubic-bezier(0.4,0,0.2,1)';
                    fill.style.width  = pct + '%';
                    thumb.style.left  = Math.min(pct, 98) + '%';
                }, 400);
            }
        });

        // Notifications
        function toggleNotif() {
            document.getElementById('notifDropdown').classList.toggle('active');
        }
        function markRead() {
            fetch('/dashboard/includes/mark_read.php', {
                method:'POST', body:'action=mark_read',
                headers:{'Content-Type':'application/x-www-form-urlencoded'}
            }).then(function(){ location.reload(); });
        }
        window.onclick = function(e) {
            if (!e.target.closest('.notif-wrapper')) {
                const drop = document.getElementById('notifDropdown');
                if(drop) drop.classList.remove('active');
            }
        }

        // --- PLANNER LOGIC ---
        let currentDate = new Date();
        let curMode = 'month';

        async function refreshPlanner() {
            const vp = document.getElementById('calendarViewport');
            const mt = document.getElementById('plannerTitle');
            if(!vp || !mt) return;
            const dStr = currentDate.toISOString().split('T')[0];
            const months = ["JANUARI","FEBRUARI","MARET","APRIL","MEI","JUNI","JULI","AGUSTUS","SEPTEMBER","OKTOBER","NOVEMBER","DESEMBER"];
            mt.innerText = months[currentDate.getMonth()] + " " + currentDate.getFullYear();
            
            try {
                const res = await fetch(`/dashboard/workspace/planner_logic_v28.php?date=${dStr}&mode=${curMode}`);
                vp.innerHTML = await res.text();
            } catch(e) {
                vp.innerHTML = "Error loading calendar.";
            }
        }

        function setMode(m, btn) { curMode = m; document.querySelectorAll('.mode-switch-v30 button').forEach(el => el.classList.remove('active')); btn.classList.add('active'); refreshPlanner(); }
        function navigatePlanner(dir) {
            if(curMode === 'month') currentDate.setMonth(currentDate.getMonth() + dir);
            else currentDate.setDate(currentDate.getDate() + (dir * 7));
            refreshPlanner();
        }
        function goToday() { currentDate = new Date(); refreshPlanner(); }
        
        function openEventModal(dateStr = '') {
            const mod = document.getElementById('eventModal');
            if(mod) mod.classList.add('active');
            if(dateStr) document.getElementById('formDate').value = dateStr;
            else document.getElementById('formDate').value = currentDate.toISOString().split('T')[0];
        }

        function closeModal(id) { 
            const mod = document.getElementById(id);
            if(mod) mod.classList.remove('active'); 
        }

        function showEventDetail(title, date, time, desc, color) {
            const container = document.getElementById('detailContent');
            const dateObj = new Date(date);
            const dateNice = dateObj.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            
            container.innerHTML = `
                <div class="detail-row"><span class="detail-label">NAMA PROJECT</span><div class="detail-val" style="color:var(--${color=='blue'?'neon-sec':'neon-main'})">${title}</div></div>
                <div class="detail-row"><span class="detail-label">WAKTU</span><div class="detail-val">${dateNice} • ${time || 'Seharian'}</div></div>
                <div class="detail-row"><span class="detail-label">DETAIL LOG</span><div class="detail-desc">${desc}</div></div>
            `;
            document.getElementById('detailModal').classList.add('active');
        }
        
        window.addEventListener('load', function() { refreshPlanner(); });
    </script>
</body>
</html>