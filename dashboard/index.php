<?php
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(!isset($_SESSION['admin'])){ header("Location: /"); exit; }
// EVENT SAVE
if(isset($_POST['save_event'])){
    $cols = [
        'meeting_type' => "VARCHAR(100) DEFAULT NULL",
        'meeting_mode' => "VARCHAR(20) DEFAULT NULL",
        'target_type'  => "VARCHAR(20) DEFAULT NULL",
        'target_name'  => "VARCHAR(255) DEFAULT NULL",
        'location'     => "TEXT DEFAULT NULL"
    ];
    foreach($cols as $col => $def){
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE '$col'");
        if(mysqli_num_rows($chk) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `$col` $def");
    }
    $title       = mysqli_real_escape_string($conn, $_POST['event_title'] ?? '');
    $date        = $_POST['event_date'] ?? date('Y-m-d');
    $start       = $_POST['time_start'] ?? '00:00';
    $color       = mysqli_real_escape_string($conn, $_POST['event_color'] ?? 'green');
    $meet_type   = mysqli_real_escape_string($conn, $_POST['meeting_type'] ?? '');
    $meet_mode   = mysqli_real_escape_string($conn, $_POST['meeting_mode'] ?? 'Online');
    $target_type = mysqli_real_escape_string($conn, $_POST['target_type'] ?? '');
    $target_name = mysqli_real_escape_string($conn, $_POST['target_name'] ?? '');
    $location    = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    if($meet_type && $target_name) $title = "Meeting $meet_type $target_name";
    $desc = "[$meet_mode] $title";
    if($location) $desc .= " | Lokasi: $location";
    mysqli_query($conn, "INSERT INTO events (title, detail, event_date, time_start, color, meeting_type, meeting_mode, target_type, target_name, location) VALUES ('$title', '$desc', '$date', '$start', '$color', '$meet_type', '$meet_mode', '$target_type', '$target_name', '$location')");
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
    $level_label = 'BELOW TARGET';
    $level_color = '#ff9f43';
    $level_bg    = 'rgba(255,159,67,0.08)';
    $level_icon  = 'fa-arrow-down';
    $level_msg   = 'Membutuhkan lebih banyak usaha untuk mencapai target bulan ini.';
    $tier        = 'males';
} elseif ($achieved < 40000000) {
    $level_label = 'ON PROGRESS';
    $level_color = '#60a5fa';
    $level_bg    = 'rgba(96,165,250,0.08)';
    $level_icon  = 'fa-chart-line';
    $level_msg   = 'Sedang dalam proses (On Track). Pertahankan kinerjanya.';
    $tier        = 'biasa';
} else {
    $level_label = 'TARGET REACHED';
    $level_color = '#a1ff5a';
    $level_bg    = 'rgba(161,255,90,0.08)';
    $level_icon  = 'fa-check-circle';
    $level_msg   = 'Luar biasa. Target bulanan berhasil dicapai dengan baik.';
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

// --- 4. UPCOMING DEADLINE (from services_data JSON) ---
$q_clients_all = mysqli_query($conn, "SELECT client_id, company_name, services_data FROM clients WHERE status='Active' AND services_data IS NOT NULL AND services_data != '' AND services_data != '[]'");
$upcoming_deadlines = [];
while($cl = mysqli_fetch_assoc($q_clients_all)){
    $svcs = json_decode($cl['services_data'], true);
    if(!is_array($svcs)) continue;
    foreach($svcs as $svc){
        if(empty($svc['end']) || ($svc['status'] ?? 'Active') !== 'Active') continue;
        $sisa = (int)floor((strtotime($svc['end']) - time()) / 86400);
        if($sisa >= 0 && $sisa <= 30){
            $upcoming_deadlines[] = [
                'company' => $cl['company_name'],
                'type'    => $svc['type'] ?? 'Service',
                'sisa'    => $sisa,
                'end'     => $svc['end'],
            ];
        }
    }
}
usort($upcoming_deadlines, fn($a,$b) => $a['sisa'] <=> $b['sisa']);
$upcoming_deadlines = array_slice($upcoming_deadlines, 0, 6);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HVM Dashboard</title>
    <link rel="shortcut icon" href="/uploads/icon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
/* =========================================
   DASHBOARD - LUXURY NEON (UPGRADED)
   ========================================= */
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

:root {
    --bg-dark: #050505;
    --card-bg: rgba(20, 20, 20, 0.65);
    --card-border: rgba(255, 255, 255, 0.08);
    --neon-main: #a1ff5a;
    --neon-sec: #4efdc4;
    --neon-red: #ff5a5a;
    --neon-yellow: #f5c518;
    --neon-wa: #25D366;
    --grad-main: linear-gradient(135deg, var(--neon-main), var(--neon-sec));
    --text-white: #ffffff;
    --text-muted: #a0a0a0;
}

* { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat', sans-serif; }
body { background: var(--bg-dark); color: var(--text-white); min-height: 100vh; overflow-x: hidden; }

/* AMBIENT GLOW */
.ambient-glow { position:fixed; border-radius:50%; filter:blur(150px); opacity:0.12; z-index:-1; animation:floatGlow 10s infinite alternate; }
.glow-1 { top:-100px; left:-100px; width:600px; height:600px; background:var(--neon-main); }
.glow-2 { bottom:-100px; right:-100px; width:600px; height:600px; background:var(--neon-sec); }
@keyframes floatGlow { from{transform:scale(1);} to{transform:scale(1.1);} }

/* SCROLLBAR */
::-webkit-scrollbar { width:6px; height:6px; }
::-webkit-scrollbar-track { background:#0a0a0a; }
::-webkit-scrollbar-thumb { background:#333; border-radius:10px; border:1px solid var(--neon-main); }

/* LAYOUT */
.dashboard-wrapper { display:flex; width:100%; min-height:100vh; }
.sidebar { width:260px; background:rgba(10,10,10,0.85); border-right:1px solid var(--card-border); backdrop-filter:blur(20px); padding:30px 20px; display:flex; flex-direction:column; position:fixed; height:100vh; z-index:100; }
.main-content { margin-left:260px; padding:30px 40px; width:calc(100% - 260px); }

/* SIDEBAR */
.brand { font-size:1.5rem; font-weight:800; margin-bottom:50px; letter-spacing:1px; }
.nav-links a { display:flex; align-items:center; padding:12px 15px; color:var(--text-muted); text-decoration:none; margin-bottom:5px; border-radius:10px; transition:0.3s; font-weight:600; font-size:0.9rem; }
.nav-links a:hover { color:#fff; background:rgba(255,255,255,0.05); }
.nav-links a.active { background:var(--grad-main); color:#000; box-shadow:0 0 20px rgba(161,255,90,0.25); }
.btn-logout { color:var(--neon-red) !important; text-decoration:none; display:flex; align-items:center; gap:10px; font-weight:600; }

/* SENSOR */
.sensor-text { filter:blur(6px); user-select:none; opacity:0.7; transition:0.3s; background:rgba(255,255,255,0.1); border-radius:4px; }
.sensor-text:hover { filter:blur(3px); opacity:1; }

/* ══ HEADER ══ */
.main-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; padding-bottom:15px; border-bottom:1px solid var(--card-border); animation:slideDown 0.6s ease; }
.page-headline h1 { font-size:2rem; font-weight:800; margin:0; color:#fff; }
.page-headline p { font-size:0.9rem; color:var(--text-muted); margin-top:5px; }
.headline h1 { font-size:1.7rem; font-weight:800; }
.headline h1 span { background:var(--grad-main); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }

.header-right { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }

/* AI Buttons */
.btn-ai {
    padding:9px 16px; border-radius:50px; font-weight:700; font-size:0.82rem;
    text-decoration:none; display:flex; align-items:center; gap:7px; transition:0.3s;
    border:1.5px solid; white-space:nowrap;
}
.btn-wa {
    background:rgba(37,211,102,0.1); border-color:var(--neon-wa); color:var(--neon-wa);
}
.btn-wa:hover { background:var(--neon-wa); color:#000; box-shadow:0 0 20px rgba(37,211,102,0.5); }
.btn-email {
    background:rgba(78,253,196,0.1); border-color:var(--neon-sec); color:var(--neon-sec);
}
.btn-email:hover { background:var(--neon-sec); color:#000; box-shadow:0 0 20px rgba(78,253,196,0.5); }

.btn-workspace {
    padding:10px 20px; border-radius:50px; background:rgba(255,255,255,0.05); border:1px solid var(--card-border);
    color:#fff; font-weight:600; font-size:0.85rem; text-decoration:none; display:flex; align-items:center; gap:8px; transition:0.3s;
}
.btn-workspace:hover { background:var(--grad-main); color:#000; border-color:transparent; }
.profile-box img { width:45px; height:45px; border-radius:50%; border:2px solid var(--neon-main); padding:2px; }

/* NOTIFICATION */
.notif-wrapper { position:relative; cursor:pointer; }
.notif-icon { font-size:1.4rem; color:#fff; width:45px; height:45px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.05); border:1px solid var(--card-border); transition:0.3s; }
.notif-icon:hover { background:var(--neon-main); color:#000; box-shadow:0 0 15px var(--neon-main); }
.notif-badge { position:absolute; top:-2px; right:-2px; background:var(--neon-red); color:#fff; font-size:0.65rem; font-weight:800; padding:2px 6px; border-radius:50%; border:2px solid #000; animation:pulse 2s infinite; }
.notif-dropdown { position:absolute; top:60px; right:-10px; width:360px; background:#0a0a0a; border:1px solid var(--neon-sec); border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.9); z-index:999; display:none; overflow:hidden; }
.notif-dropdown.active { display:block; animation:scaleIn 0.2s ease; }
.notif-header { padding:15px 20px; border-bottom:1px solid var(--card-border); font-weight:700; color:#fff; background:rgba(255,255,255,0.03); display:flex; justify-content:space-between; }
.notif-list { max-height:350px; overflow-y:auto; }
.notif-item { padding:15px 20px; border-bottom:1px solid rgba(255,255,255,0.05); display:flex; gap:15px; align-items:flex-start; transition:0.2s; }
.notif-item:hover { background:rgba(255,255,255,0.03); }
.n-icon { width:35px; height:35px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; background:rgba(255,255,255,0.05); }
.n-income { color:var(--neon-main); } .n-expense { color:var(--neon-red); } .n-client { color:var(--neon-sec); }
.n-info h5 { margin:0 0 3px 0; font-size:0.85rem; color:#fff; }
.n-info p { margin:0; font-size:0.75rem; color:#aaa; line-height:1.4; }
.n-time { font-size:0.65rem; color:#666; display:block; margin-top:5px; font-weight:600; text-transform:uppercase; }

/* ══ TOP DECK ══ */
.top-deck { display:grid; grid-template-columns:1.2fr 1fr; gap:25px; margin-bottom:25px; animation:fadeIn 0.8s ease; }

.apple-widget {
    background:linear-gradient(145deg, rgba(255,255,255,0.03), rgba(0,0,0,0.8));
    backdrop-filter:blur(20px); border:1px solid var(--card-border);
    border-radius:24px; padding:30px; display:flex; justify-content:space-between; align-items:center;
    position:relative; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.5);
}
.apple-widget::after { content:''; position:absolute; top:-50px; right:-50px; width:150px; height:150px; background:var(--neon-main); filter:blur(90px); opacity:0.2; }
.widget-time { display:flex; align-items:baseline; gap:5px; }
.time-text { font-size:3rem; font-weight:800; color:#fff; line-height:1; text-shadow:0 0 20px rgba(161,255,90,0.2); }
.time-sec { font-size:1.2rem; font-weight:600; color:var(--neon-main); }
.date-day { font-size:1.4rem; font-weight:700; color:#fff; margin-bottom:2px; }
.date-full { font-size:0.85rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; }

.upcoming-card { background:var(--card-bg); border:1px solid var(--card-border); border-radius:20px; padding:20px 22px; backdrop-filter:blur(20px); overflow-y:auto; max-height:160px; }
.uc-header { font-size:0.68rem; font-weight:800; color:rgba(255,255,255,0.4); text-transform:uppercase; letter-spacing:2px; margin-bottom:12px; display:flex; align-items:center; gap:6px; }
.uc-item { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding:8px 12px; background:rgba(255,255,255,0.03); border-radius:10px; border-left:2px solid rgba(255,255,255,0.08); }
.uc-name { font-size:0.82rem; color:#e0e0e0; font-weight:600; }
.uc-days { font-size:0.7rem; color:#aaa; font-weight:700; background:rgba(255,255,255,0.06); padding:3px 10px; border-radius:20px; border:1px solid rgba(255,255,255,0.1); white-space:nowrap; }

/* ══ MONTHLY TARGET PREMIUM ══ */
.target-premium-card {
    background: rgba(12,12,14,0.95);
    backdrop-filter: blur(40px);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 20px;
    padding: 18px 22px 14px;
    margin-bottom: 15px;
    position: relative;
    overflow: hidden;
}
.target-premium-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent);
    pointer-events: none;
}

.tp-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
.tp-label { font-size:0.65rem; font-weight:800; letter-spacing:2.5px; text-transform:uppercase; color:rgba(255,255,255,0.35); }
.tp-badge {
    display: flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 50px;
    font-size: 0.65rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.5);
    border: 1px solid rgba(255,255,255,0.1);
}

/* Amount */
.tp-amount-row { display:flex; align-items:baseline; gap:10px; margin-bottom:16px; }
.tp-achieved { font-size:2rem; font-weight:900; color:#fff; line-height:1; letter-spacing:-1px; }
.tp-separator { font-size:1rem; font-weight:300; color:rgba(255,255,255,0.2); }
.tp-goal { font-size:1rem; font-weight:600; color:rgba(255,255,255,0.35); }
.tp-unit { font-size:0.75rem; font-weight:600; margin-left:2px; color:rgba(255,255,255,0.5); }

/* Track */
.tp-track-wrap { margin-bottom:8px; }
.tp-track {
    position: relative;
    height: 4px;
    border-radius: 2px;
    background: rgba(255,255,255,0.07);
    overflow: visible;
    margin-bottom: 0;
}
.tp-zone { display:none; }

.tp-fill {
    position: absolute; top: 0; left: 0; height: 100%;
    background: linear-gradient(90deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.8) 100%);
    border-radius: 2px;
    width: 0%;
    z-index: 1;
    transition: width 1.2s cubic-bezier(0.4,0,0.2,1);
}
.tp-thumb {
    position: absolute; top: 50%; transform: translate(-50%, -50%);
    width: 14px; height: 14px; border-radius: 50%;
    background: #fff;
    border: 2px solid rgba(255,255,255,0.3);
    z-index: 2;
    box-shadow: 0 0 12px rgba(255,255,255,0.3);
    display: flex; align-items: center; justify-content: center;
}
.tp-thumb-inner { width:5px; height:5px; border-radius:50%; background:rgba(0,0,0,0.5); }

/* Milestones */
.tp-milestones { position:relative; height:30px; margin-top:8px; }
.tp-milestone {
    position: absolute;
    transform: translateX(-50%);
    display: flex; flex-direction: column; align-items: center; gap: 4px;
}
.ms-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15);
    transition: 0.3s;
}
.ms-dot.ms-start { background:rgba(255,255,255,0.15); }
.ms-dot.ms-end   { width:8px; height:8px; border-color:rgba(255,255,255,0.3); }
.ms-dot.ms-done  { background:#fff; border-color:#fff; box-shadow:0 0 6px rgba(255,255,255,0.4); }
.ms-dot.ms-red-empty   { border-color:rgba(255,255,255,0.2); }
.ms-dot.ms-yellow-empty { border-color:rgba(255,255,255,0.2); }
.ms-dot.ms-gacor { background:#fff; border-color:#fff; box-shadow:0 0 12px rgba(255,255,255,0.5); }

.ms-info { display:flex; flex-direction:column; align-items:center; }
.ms-label { font-size:0.62rem; color:rgba(255,255,255,0.35); font-weight:700; }
.ms-tag   { font-size:0.55rem; font-weight:800; letter-spacing:0.5px; margin-top:1px; color:rgba(255,255,255,0.25); }

/* Footer */
.tp-footer { display:flex; justify-content:space-between; align-items:center; padding-top:10px; border-top:1px solid rgba(255,255,255,0.05); margin-top:4px; }
.tp-stat { display:flex; flex-direction:column; gap:2px; }
.tp-stat-label { font-size:0.6rem; color:rgba(255,255,255,0.3); font-weight:700; text-transform:uppercase; letter-spacing:1px; }
.tp-stat-val { font-size:0.9rem; font-weight:900; color:#fff; }
.tp-stat-center { text-align:center; }
.tp-msg { font-size:0.72rem; color:rgba(255,255,255,0.4); font-weight:600; font-style:italic; }

/* ══ STATS ROW ══ */
.stats-row { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:20px; margin-bottom:25px; animation:fadeIn 1s ease; }
.glass-card {
    background:var(--card-bg); backdrop-filter:blur(20px); border:1px solid var(--card-border);
    border-radius:20px; padding:22px; position:relative; overflow:hidden; transition:0.3s;
}
.glass-card:hover { border-color:var(--neon-main); transform:translateY(-4px); box-shadow:0 10px 40px rgba(0,0,0,0.6); }
.stat-mini { padding:20px; }
.card-label { font-size:0.7rem; font-weight:800; color:var(--neon-main); letter-spacing:1.5px; text-transform:uppercase; margin-bottom:10px; }
.small-val { font-size:2.2rem; font-weight:900; margin:6px 0; }
.text-green { color:var(--neon-main); } .text-cyan { color:var(--neon-sec); }

/* AI CTA Cards */
.ai-cta-card { cursor:pointer; position:relative; }
.ai-cta-card:hover { transform:translateY(-6px); }
.ai-cta-icon {
    width:44px; height:44px; border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.4rem;
}
.wa-icon { background:rgba(37,211,102,0.15); color:var(--neon-wa); border:1px solid rgba(37,211,102,0.3); }
.email-icon { background:rgba(78,253,196,0.15); color:var(--neon-sec); border:1px solid rgba(78,253,196,0.3); }
.ai-cta-arrow {
    position:absolute; bottom:18px; right:18px;
    width:28px; height:28px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    background:rgba(255,255,255,0.06); color:#555; font-size:0.8rem; transition:0.3s;
}
.ai-cta-card:hover .ai-cta-arrow { background:var(--neon-sec); color:#000; }
.ai-cta-card.wa-icon-cta:hover .ai-cta-arrow { background:var(--neon-wa); }

/* ══ SERVICES SECTION ══ */
.services-section { animation:fadeIn 1.1s ease; }
.section-title {
    font-size:0.72rem; font-weight:800; letter-spacing:2px; text-transform:uppercase;
    color:var(--neon-sec); margin-bottom:16px; padding-left:4px;
    border-left:3px solid var(--neon-sec); padding-left:12px;
}

.services-grid-v2 { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; }

.svc-card-v2 {
    background:var(--card-bg); backdrop-filter:blur(20px);
    border:1px solid var(--card-border); border-radius:12px;
    padding:12px; transition:0.3s; overflow:hidden; position:relative;
}
.svc-card-v2::before {
    content:''; position:absolute; top:-30px; right:-30px;
    width:80px; height:80px; border-radius:50%; filter:blur(40px); opacity:0.15;
}
.svc-web::before    { background:#a1ff5a; }
.svc-social::before { background:#4efdc4; }
.svc-seo::before    { background:#f5c518; }
.svc-content::before { background:#ff8c5a; }

.svc-web:hover    { border-color:#a1ff5a; box-shadow:0 10px 30px rgba(161,255,90,0.15); transform:translateY(-4px); }
.svc-social:hover { border-color:#4efdc4; box-shadow:0 10px 30px rgba(78,253,196,0.15); transform:translateY(-4px); }
.svc-seo:hover    { border-color:#f5c518; box-shadow:0 10px 30px rgba(245,197,24,0.15); transform:translateY(-4px); }
.svc-content:hover { border-color:#ff8c5a; box-shadow:0 10px 30px rgba(255,140,90,0.15); transform:translateY(-4px); }

.svc-head { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.svc-icon-wrap {
    width:32px; height:32px; border-radius:10px;
    display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0;
}
.web-icon-clr     { background:rgba(161,255,90,0.12); color:#a1ff5a; border:1px solid rgba(161,255,90,0.25); }
.social-icon-clr  { background:rgba(78,253,196,0.12); color:#4efdc4; border:1px solid rgba(78,253,196,0.25); }
.seo-icon-clr     { background:rgba(245,197,24,0.12); color:#f5c518; border:1px solid rgba(245,197,24,0.25); }
.content-icon-clr { background:rgba(255,140,90,0.12); color:#ff8c5a; border:1px solid rgba(255,140,90,0.25); }

.svc-name  { font-size:0.85rem; font-weight:800; color:#fff; letter-spacing:1px; }
.svc-count { font-size:0.7rem; color:#777; font-weight:600; margin-top:0px; }

.svc-client-list { display:flex; flex-direction:column; gap:4px; max-height:140px; overflow-y:auto; }
.svc-client-item {
    display:flex; align-items:center; gap:6px;
    padding:5px 8px; border-radius:8px;
    background:rgba(255,255,255,0.025);
    transition:0.2s;
}
.svc-client-item:hover { background:rgba(255,255,255,0.06); }
.svc-dot {
    width:7px; height:7px; border-radius:50%; flex-shrink:0;
}
.web-dot     { background:#a1ff5a; box-shadow:0 0 6px rgba(161,255,90,0.6); }
.social-dot  { background:#4efdc4; box-shadow:0 0 6px rgba(78,253,196,0.6); }
.seo-dot     { background:#f5c518; box-shadow:0 0 6px rgba(245,197,24,0.6); }
.content-dot { background:#ff8c5a; box-shadow:0 0 6px rgba(255,140,90,0.6); }

.svc-client-name { font-size:0.75rem; font-weight:600; color:#ccc; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.svc-empty { font-size:0.75rem; color:#555; text-align:center; padding:10px 0; font-style:italic; }

/* ══ BROADCAST ITEM (existing compat) ══ */
.bc-item { border-left:3px solid var(--neon-main) !important; background:rgba(161,255,90,0.05) !important; display:flex; justify-content:space-between; align-items:center; transition:0.3s ease; }
.bc-content { display:flex; align-items:center; flex:1; overflow:hidden; }
.bc-text { font-size:0.85rem; color:#eee; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding-right:10px; }
.btn-bc-done { background:rgba(255,255,255,0.1); border:none; color:#888; width:24px; height:24px; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s; }
.btn-bc-done:hover { background:var(--neon-main); color:#000; }

/* ══ ANIMATIONS ══ */
@keyframes slideDown { from{opacity:0;transform:translateY(-30px);}to{opacity:1;transform:translateY(0);} }
@keyframes fadeIn    { from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);} }
@keyframes scaleIn   { from{opacity:0;transform:scale(0.9);}to{opacity:1;transform:scale(1);} }
@keyframes pulse     { 0%{transform:scale(1);opacity:1;} 50%{transform:scale(1.1);opacity:0.7;} 100%{transform:scale(1);opacity:1;} }

/* ══ RESPONSIVE ══ */
@media (max-width:1280px) {
    .stats-row { grid-template-columns:1fr 1fr; }
    .services-grid-v2 { grid-template-columns:1fr 1fr; }
}
@media (max-width:1024px) {
    .top-deck { grid-template-columns:1fr; }
    .stats-row { grid-template-columns:1fr 1fr; }
}
@media (max-width:768px) {
    .sidebar { display:none; }
    .main-content { margin-left:0; width:100%; padding:20px; }
    .main-header { flex-direction:column; align-items:flex-start; gap:15px; }
    .header-right { width:100%; justify-content:flex-start; flex-wrap:wrap; }
    .btn-workspace span, .btn-ai span { display:none; }
    .notif-dropdown { width:300px; right:-50px; }
    .stats-row { grid-template-columns:1fr 1fr; }
    .services-grid-v2 { grid-template-columns:1fr 1fr; }
    .tp-amount-row { flex-direction:column; gap:4px; }
    .tp-footer { flex-direction:column; gap:12px; text-align:center; }
}
@media (max-width:480px) {
    .stats-row { grid-template-columns:1fr; }
    .services-grid-v2 { grid-template-columns:1fr; }
}
        /* --- PLANNER / CALENDAR STYLES --- */
        .zenith-grid-layout { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 0px; margin-top: 0px; }
        .planner-deck { padding: 15px !important; position: relative; width: 100%; display: flex; flex-direction: column; gap: 10px; }
        .panel-header-v30 { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .ph-left h2 { font-size: 1.8rem; font-weight: 900; margin: 0; }
        .ph-nav-group { display: flex; align-items: center; gap: 15px; margin-top: 10px; }
        .btn-today-v30 { background: #fff; color: #000; padding: 8px 20px; border-radius: 50px; font-weight: 800; font-size: 0.7rem; cursor: pointer; border: none; }
        .nav-arrow-v30 { width: 35px; height: 35px; border-radius: 50%; background: rgba(255,255,255,0.05); color: #fff; border: 1px solid var(--card-border); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .nav-arrow-v30:hover { background: var(--neon-main); color: #000; }
        .arrow-nav-v30 { display: flex; gap: 10px; }
        .mode-switch-v30 { background: rgba(255,255,255,0.05); padding: 5px; border-radius: 15px; display: flex; }
        .mode-switch-v30 button { background: none; border: none; color: #888; padding: 8px 15px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; cursor: pointer; }
        .mode-switch-v30 button.active { background: #fff; color: #000; }
        .planner-viewport { flex: 1; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 12px; padding: 0px; border: 1px solid var(--card-border); position: relative; max-height: none; }
        .add-event-fab { position: absolute; bottom: 15px; right: 15px; width: 45px; height: 45px; border-radius: 50%; background: var(--neon-main); color: #000; font-size: 1.3rem; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 0 20px var(--neon-main); transition: 0.3s; z-index: 10; border: none; }
        .add-event-fab:hover { transform: scale(1.1) rotate(90deg); box-shadow: 0 0 40px var(--neon-main); }
        /* --- CALENDAR GRIDS --- */
        .cal-grid-month, .cal-grid-week { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; min-width: 600px; }
        .cal-day-header { text-align: center; font-weight: 700; color: #666; margin-bottom: 5px; font-size: 0.75rem; }
        .cal-day-cell { min-height: 75px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid var(--card-border); padding: 6px; cursor: pointer; transition: 0.3s; display: flex; flex-direction: column; gap: 3px; }
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
        .modal-content { background: #0c0c0e; border: 1px solid rgba(255,255,255,0.07); width: 500px; max-width: 95%; padding: 30px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.8); max-height: 90vh; overflow-y: auto; position: relative; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; color: #aaa; margin-bottom: 5px; font-size: 0.8rem; }
        .form-input { width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--card-border); color: #fff; border-radius: 8px; outline: none; font-size: 0.9rem; }
        .modal-top-actions { position: absolute; top: 20px; right: 20px; display: flex; gap: 15px; }
        .btn-close-x { background: none; border: none; color: #555; font-size: 1.5rem; cursor: pointer; transition: 0.3s; }
        .btn-close-x:hover { color: #fff; }
        .btn-save-center { width: 60px; height: 60px; border-radius: 50%; background: var(--grad-main); color: #000; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; margin: 20px auto 0 auto; box-shadow: 0 0 20px var(--neon-main); transition: 0.3s; }
        .btn-save-center:hover { transform: scale(1.1); box-shadow: 0 0 40px var(--neon-main); }
        .detail-row { margin-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px; }
        .detail-label { font-size: 0.65rem; color: #777; text-transform: uppercase; letter-spacing: 2px; display: block; margin-bottom: 6px; font-weight: 700; }
        .detail-val { font-size: 1rem; color: #eaeaea; font-weight: 600; line-height: 1.4; }
        .detail-desc { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; color: #aaa; font-size: 0.85rem; line-height: 1.5; }
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
                    <div class="uc-header"><i class="fas fa-clock"></i> UPCOMING DEADLINE</div>
                    <?php if(count($upcoming_deadlines) > 0): ?>
                        <?php foreach($upcoming_deadlines as $ud): ?>
                        <?php
                            $urgency_color = $ud['sisa'] <= 7 ? '#ff5a5a' : ($ud['sisa'] <= 14 ? '#ff9f43' : '#f5c518');
                        ?>
                        <div class="uc-item">
                            <div style="flex:1;min-width:0;">
                                <div class="uc-name"><?php echo htmlspecialchars($ud['company']); ?></div>
                                <div style="font-size:0.65rem;color:rgba(255,255,255,0.25);margin-top:2px;"><?php echo htmlspecialchars($ud['type']); ?> · Berakhir <?php echo date('d M Y', strtotime($ud['end'])); ?></div>
                            </div>
                            <span class="uc-days">H-<?php echo $ud['sisa']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="color:#333;font-size:0.82rem;text-align:center;padding:20px 0;"><i class="fas fa-check-circle" style="color:#2a2a2a;font-size:1.5rem;display:block;margin-bottom:8px;"></i>Semua aman. Tidak ada layanan yang hampir habis.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ══ MONTHLY TARGET PREMIUM CARD ══ -->
            <div class="target-premium-card tier-<?php echo $tier; ?>"
                 style="--tier-color:<?php echo $level_color;?>;">

                <div class="tp-header">
                    <div class="tp-label">MONTHLY TARGET</div>
                    <div class="tp-badge">
                        <i class="fas <?php echo $level_icon; ?>"></i>
                        <span><?php echo $level_label; ?></span>
                    </div>
                </div>

                <div class="tp-amount-row">
                    <div class="tp-achieved">
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
                                <span class="ms-tag">Target 1</span>
                            </div>
                        </div>
                        <div class="tp-milestone" style="left:50%">
                            <div class="ms-dot <?php echo ($achieved>=20000000)?'ms-done ms-yellow':'ms-yellow-empty'; ?>"></div>
                            <div class="ms-info">
                                <span class="ms-label">20jt</span>
                                <span class="ms-tag">Target 2</span>
                            </div>
                        </div>
                        <div class="tp-milestone" style="left:100%">
                            <div class="ms-dot ms-end <?php echo ($achieved>=40000000)?'ms-done ms-gacor':''; ?>">
                            </div>
                            <div class="ms-info">
                                <span class="ms-label">40jt</span>
                                <span class="ms-tag">Reached</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tp-footer">
                    <div class="tp-stat">
                        <span class="tp-stat-label">Progress</span>
                        <span class="tp-stat-val"><?php echo number_format(min(100,$persen_target),1); ?>%</span>
                    </div>
                    <div class="tp-stat-center">
                        <span class="tp-msg"><?php echo $level_msg; ?></span>
                    </div>
                    <div class="tp-stat" style="text-align:right;">
                        <span class="tp-stat-label">Gap ke Target</span>
                        <span class="tp-stat-val">
                            <?php echo $sisa_target > 0 ? 'Rp '.number_format($sisa_target/1000000,1).'jt' : 'DONE!'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ══ PLANNER / CALENDAR ══ -->
            <div class="zenith-grid-layout">
                <!-- PANEL 1: PLANNER -->
                <div class="zenith-panel glass-card planner-deck animate-slide-up" style="background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; position: relative;">
                    <div class="panel-header-v30">
                        <div class="ph-left">
                            <label style="position:relative; display:inline-block; margin:0; cursor:pointer;" title="Ubah Bulan/Tahun">
                                <h2 id="plannerTitle" style="margin:0;">...</h2>
                                <input type="month" id="monthPicker" onchange="jumpToMonth(this.value)" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer; font-size:0; padding:0; border:none; z-index:10;">
                            </label>
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

    <!-- MODAL ADD MEETING -->
    <div class="modal-overlay" id="eventModal">
        <div class="modal-content" style="max-width:580px;">
            <div class="modal-top-actions">
                <button class="btn-close-x" onclick="document.getElementById('eventModal').classList.remove('active')">&times;</button>
            </div>
            <h3 style="color:#fff; margin-bottom:20px; font-weight:800; font-size:1.3rem;"><i class="fas fa-calendar-plus" style="color:#a1ff5a;margin-right:8px;"></i>Buat Meeting</h3>

            <style>
            .meet-type-chip { display:inline-flex;align-items:center;padding:6px 14px;border-radius:20px;font-size:0.75rem;font-weight:700;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);color:#888;cursor:pointer;transition:all 0.2s;user-select:none; }
            input[type=radio]:checked + .meet-type-chip { background:rgba(161,255,90,0.12);border-color:rgba(161,255,90,0.4);color:#a1ff5a; }
            .meet-mode-chip { display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:10px;font-size:0.8rem;font-weight:700;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#888;cursor:pointer;transition:all 0.2s; }
            .meet-mode-chip.active { background:rgba(78,253,196,0.1);border-color:rgba(78,253,196,0.3);color:#4efdc4; }
            .target-type-btn { padding:7px 16px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);color:#888;font-family:'Montserrat',sans-serif;font-size:0.78rem;font-weight:600;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:6px; }
            .target-type-btn.active { background:rgba(161,255,90,0.1);border-color:rgba(161,255,90,0.3);color:#a1ff5a; }
            </style>

            <form method="POST" id="eventForm">
                <input type="hidden" name="save_event" value="1">

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Jenis Meeting</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <?php foreach(['Prospek','Maintenance','After Sales','Internal','Presentasi'] as $mt): ?>
                        <label style="cursor:pointer;">
                            <input type="radio" name="meeting_type" value="<?= $mt ?>" style="display:none;" onchange="dashUpdateTitle()">
                            <span class="meet-type-chip"><?= $mt ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Mode Meeting</label>
                    <div style="display:flex;gap:8px;">
                        <label style="cursor:pointer;">
                            <input type="radio" name="meeting_mode" value="Online" checked style="display:none;" onchange="dashToggleLoc(this.value)">
                            <span class="meet-mode-chip active" id="d-chip-online"><i class="fas fa-video"></i> Online</span>
                        </label>
                        <label style="cursor:pointer;">
                            <input type="radio" name="meeting_mode" value="Offline" style="display:none;" onchange="dashToggleLoc(this.value)">
                            <span class="meet-mode-chip" id="d-chip-offline"><i class="fas fa-map-marker-alt"></i> Offline</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Perusahaan</label>
                    <div style="display:flex;gap:8px;margin-bottom:8px;">
                        <button type="button" class="target-type-btn active" id="d-btn-client" onclick="dashSwitchTarget('Client')"><i class="fas fa-users"></i> Clients</button>
                        <button type="button" class="target-type-btn" id="d-btn-prospect" onclick="dashSwitchTarget('Prospect')"><i class="fas fa-binoculars"></i> Prospects</button>
                    </div>
                    <input type="hidden" name="target_type" id="d-target-type" value="Client">
                    <select name="target_name" id="d-company-sel" class="form-input" style="background:#111;" onchange="dashUpdateTitle()">
                        <option value="">-- Pilih Perusahaan --</option>
                        <?php
                        $qc = mysqli_query($conn, "SELECT company_name FROM clients ORDER BY company_name ASC");
                        while($r = mysqli_fetch_assoc($qc)) echo "<option value='".htmlspecialchars($r['company_name'])."' data-type='Client'>".htmlspecialchars($r['company_name'])."</option>";
                        $qp_chk = mysqli_query($conn, "SHOW TABLES LIKE 'prospects'");
                        if(mysqli_num_rows($qp_chk) > 0){
                            $qp = mysqli_query($conn, "SELECT company_name FROM prospects ORDER BY company_name ASC");
                            while($r = mysqli_fetch_assoc($qp)) echo "<option value='".htmlspecialchars($r['company_name'])."' data-type='Prospect' class='opt-prospect' style='display:none'>".htmlspecialchars($r['company_name'])."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div class="form-group">
                        <label style="color:#888;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Tanggal</label>
                        <input type="date" name="event_date" id="formDate" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label style="color:#888;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;">Jam</label>
                        <input type="time" name="time_start" class="form-input">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;" id="d-loc-label">Link Meeting (Google Meet / Zoom)</label>
                    <input type="text" name="location" class="form-input" id="d-loc-input" placeholder="https://meet.google.com/...">
                </div>

                <div id="d-title-preview" style="display:none;background:rgba(161,255,90,0.06);border:1px solid rgba(161,255,90,0.2);border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:0.85rem;color:#a1ff5a;"></div>

                <input type="hidden" name="event_color" value="green">

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px;">
                    <button type="button" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#888;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:0.82rem;cursor:pointer;" onclick="document.getElementById('eventModal').classList.remove('active')">Batal</button>
                    <button type="submit" style="background:linear-gradient(135deg,#a1ff5a,#4efdc4);border:none;color:#000;border-radius:10px;padding:9px 22px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;"><i class="fas fa-check" style="margin-right:6px;"></i>Simpan Meeting</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="detailModal">
        <div class="modal-content">
            <div class="modal-top-actions">
                <button class="btn-close-x" onclick="closeModal('detailModal')">&times;</button>
            </div>
            <h2 class="modal-title" style="color:#fff; margin-bottom:20px; font-size:1.3rem; font-weight:700;"><i class="fas fa-file-alt" style="margin-right:8px; color:#888;"></i>Project Detail</h2>
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
            
            const monthPicker = document.getElementById('monthPicker');
            if(monthPicker) {
                const y = currentDate.getFullYear();
                const m = String(currentDate.getMonth() + 1).padStart(2, '0');
                monthPicker.value = `${y}-${m}`;
            }
            
            try {
                const res = await fetch(`/dashboard/workspace/planner_logic_v28.php?date=${dStr}&mode=${curMode}`);
                vp.innerHTML = await res.text();
                if (curMode === 'month') {
                    fetchHolidays(currentDate.getFullYear(), currentDate.getMonth() + 1);
                }
            } catch(e) {
                vp.innerHTML = "Error loading calendar.";
            }
        }

        let holidayCache = {};
        async function fetchHolidays(year, month) {
            if (!holidayCache[year]) {
                try {
                    const res = await fetch(`https://date.nager.at/api/v3/PublicHolidays/${year}/ID`);
                    if (res.ok) {
                        holidayCache[year] = await res.json();
                    } else { holidayCache[year] = []; }
                } catch(e) { holidayCache[year] = []; }
            }
            
            const hols = holidayCache[year];
            if (!hols || hols.length === 0) return;
            
            // Loop thru all days in calendar
            document.querySelectorAll('.cal-day-cell').forEach(cell => {
                const d = cell.getAttribute('data-date');
                if (d) {
                    const found = hols.find(h => h.date === d);
                    if (found) {
                        const lbl = cell.querySelector('.holiday-label-container');
                        if(lbl) lbl.innerText = found.localName || found.name;
                        cell.style.borderColor = 'rgba(255, 90, 90, 0.4)';
                        const num = cell.querySelector('.cal-day-num');
                        if(num) num.style.color = 'var(--neon-red)';
                    }
                }
            });
        }

        function setMode(m, btn) { curMode = m; document.querySelectorAll('.mode-switch-v30 button').forEach(el => el.classList.remove('active')); btn.classList.add('active'); refreshPlanner(); }
        function navigatePlanner(dir) {
            if(curMode === 'month') currentDate.setMonth(currentDate.getMonth() + dir);
            else if(curMode === 'week') currentDate.setDate(currentDate.getDate() + (dir*7));
            else if(curMode === 'day') currentDate.setDate(currentDate.getDate() + dir);
            refreshPlanner();
        }

        function jumpToMonth(val) {
            if(!val) return;
            const parts = val.split('-');
            if(parts.length === 2) {
                currentDate = new Date(parts[0], parseInt(parts[1])-1, 1);
                refreshPlanner();
            }
        }

        function openMonthPicker() {
            const picker = document.getElementById('monthPicker');
            if(picker) {
                try {
                    if(typeof picker.showPicker === 'function') picker.showPicker();
                    else picker.focus();
                } catch(e) { picker.click(); }
            }
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

        function dashToggleLoc(mode) {
            const online = document.getElementById('d-chip-online');
            const offline = document.getElementById('d-chip-offline');
            if(online) online.classList.toggle('active', mode === 'Online');
            if(offline) offline.classList.toggle('active', mode === 'Offline');
            const lbl = document.getElementById('d-loc-label');
            const inp = document.getElementById('d-loc-input');
            if(!lbl || !inp) return;
            if(mode === 'Offline') {
                lbl.textContent = 'Lokasi / Alamat Meeting';
                inp.placeholder = 'Jl. Contoh No. 1, Kota...';
            } else {
                lbl.textContent = 'Link Meeting (Google Meet / Zoom)';
                inp.placeholder = 'https://meet.google.com/...';
            }
        }

        function dashSwitchTarget(type) {
            document.getElementById('d-target-type').value = type;
            document.getElementById('d-btn-client').classList.toggle('active', type === 'Client');
            document.getElementById('d-btn-prospect').classList.toggle('active', type === 'Prospect');
            const sel = document.getElementById('d-company-sel');
            Array.from(sel.options).forEach(opt => {
                if(!opt.value) { opt.style.display = ''; return; }
                opt.style.display = (opt.dataset.type === type) ? '' : 'none';
            });
            sel.value = '';
            dashUpdateTitle();
        }

        function dashUpdateTitle() {
            const typeEl = document.querySelector('input[name=meeting_type]:checked');
            const company = document.getElementById('d-company-sel')?.value;
            const preview = document.getElementById('d-title-preview');
            if(!preview) return;
            if(typeEl && company) {
                preview.style.display = 'block';
                preview.innerHTML = '<i class="fas fa-eye" style="margin-right:6px;"></i>Judul: <strong>Meeting ' + typeEl.value + ' ' + company + '</strong>';
            } else {
                preview.style.display = 'none';
            }
        }

        function showEventDetail(title, date, time, desc, color) {
            const container = document.getElementById('detailContent');
            const dateObj = new Date(date);
            const dateNice = dateObj.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            
            container.innerHTML = `
                <div class="detail-row"><span class="detail-label">NAMA PROJECT</span><div class="detail-val">${title}</div></div>
                <div class="detail-row"><span class="detail-label">WAKTU</span><div class="detail-val">${dateNice} &bull; ${time || 'Seharian'}</div></div>
                <div class="detail-row" style="border:none; margin-bottom:0; padding-bottom:0;"><span class="detail-label">DETAIL LOG</span><div class="detail-desc">${desc}</div></div>
            `;
            document.getElementById('detailModal').classList.add('active');
        }
        
        window.addEventListener('load', function() { refreshPlanner(); });
    </script>
</body>
</html>