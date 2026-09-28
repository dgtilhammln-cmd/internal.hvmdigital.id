<?php
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(!isset($_SESSION['admin'])){ header("Location: /"); exit; }
if(isset($_SESSION['admin']) && isset($_POST['ajax_action'])){
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];

    // Return clients + prospects for dropdown
    if($act === 'get_targets') {
        $clients = []; $prospects = [];
        $qcl = mysqli_query($conn, "SELECT client_id as id, company_name FROM clients ORDER BY company_name ASC");
        if($qcl) while($r=mysqli_fetch_assoc($qcl)) $clients[] = ['id'=>$r['id'],'name'=>$r['company_name'],'type'=>'Client'];
        $qpr_chk = mysqli_query($conn, "SHOW TABLES LIKE 'prospects'");
        if(mysqli_num_rows($qpr_chk) > 0) {
            $qpr = mysqli_query($conn, "SELECT id, company_name FROM prospects ORDER BY company_name ASC");
            if($qpr) while($r=mysqli_fetch_assoc($qpr)) $prospects[] = ['id'=>$r['id'],'name'=>$r['company_name'],'type'=>'Prospect'];
        }
        echo json_encode(['clients'=>$clients,'prospects'=>$prospects]); exit;
    }

    // Update meeting log_hasil
    if($act === 'update_log') {
        $eid = intval($_POST['event_id'] ?? 0);
        $log = mysqli_real_escape_string($conn, $_POST['log_hasil'] ?? '');
        // Ensure column exists
        $chk_log = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'log_hasil'");
        if(mysqli_num_rows($chk_log) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `log_hasil` TEXT DEFAULT NULL");
        $ok = mysqli_query($conn, "UPDATE events SET log_hasil='$log' WHERE id=$eid");
        echo json_encode(['ok'=>(bool)$ok]); exit;
    }

    // Get meetings for a specific entity
    if($act === 'get_meetings') {
        $tid   = intval($_POST['target_id'] ?? 0);
        $ttype = mysqli_real_escape_string($conn, $_POST['target_type'] ?? '');
        $chk_log = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'log_hasil'");
        if(mysqli_num_rows($chk_log) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `log_hasil` TEXT DEFAULT NULL");
        $chk_tid = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'target_id'");
        if(mysqli_num_rows($chk_tid) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `target_id` INT DEFAULT NULL");
        $rows = [];
        $q = mysqli_query($conn, "SELECT id, title, event_date, time_start, meeting_type, meeting_mode, location, log_hasil FROM events WHERE target_id=$tid AND target_type='$ttype' ORDER BY event_date DESC");
        if($q) while($r=mysqli_fetch_assoc($q)) $rows[] = $r;
        echo json_encode($rows); exit;
    }

    // Get meetings for interactive map & gallery
    if($act === 'get_map_meetings') {
        $period = $_POST['period'] ?? 'month';
        $is_gallery = ($_POST['is_gallery'] ?? '0') === '1';
        $where = "1=1";
        if($period === '7d') {
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $where = "event_date >= '$startDate'";
        } else if($period === '30d') {
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $where = "event_date >= '$startDate'";
        } else if($period === 'month') {
            $m = date('m'); $y = date('Y');
            $where = "MONTH(event_date) = '$m' AND YEAR(event_date) = '$y'";
        } else if($period === 'all') {
            $where = "1=1";
        }
        
        $chk_lat = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'lat'");
        if(mysqli_num_rows($chk_lat) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `lat` FLOAT DEFAULT NULL");
        $chk_lng = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'lng'");
        if(mysqli_num_rows($chk_lng) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `lng` FLOAT DEFAULT NULL");
        $chk_cr = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'coords_raw'");
        if(mysqli_num_rows($chk_cr) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `coords_raw` VARCHAR(255) DEFAULT NULL");
        $chk_ph = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'photos'");
        if(mysqli_num_rows($chk_ph) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `photos` TEXT DEFAULT NULL");

        $loc_condition = $is_gallery ? "" : "AND (location IS NOT NULL AND TRIM(location) != '')";

        $rows = [];
        $q = mysqli_query($conn, "SELECT id, title, target_name, location, lat, lng, coords_raw, event_date, time_start, meeting_type, meeting_mode, log_hasil, photos FROM events WHERE $where $loc_condition ORDER BY event_date DESC, time_start DESC");
        if($q) while($r=mysqli_fetch_assoc($q)) $rows[] = $r;
        echo json_encode($rows); exit;
    }

    // Save geocoded coordinates back to DB
    if($act === 'update_meeting_coords') {
        $id = intval($_POST['event_id'] ?? 0);
        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);
        if($id > 0 && ($lat != 0 || $lng != 0)) {
            $chk_lat = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'lat'");
            if(mysqli_num_rows($chk_lat) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `lat` FLOAT DEFAULT NULL");
            $chk_lng = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'lng'");
            if(mysqli_num_rows($chk_lng) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `lng` FLOAT DEFAULT NULL");
            mysqli_query($conn, "UPDATE events SET lat=$lat, lng=$lng WHERE id=$id");
        }
        echo json_encode(['ok'=>true]); exit;
    }

    exit;
}

// EVENT SAVE
if(isset($_POST['save_event'])){
    $cols = [
        'meeting_type' => "VARCHAR(100) DEFAULT NULL",
        'meeting_mode' => "VARCHAR(20) DEFAULT NULL",
        'target_type'  => "VARCHAR(20) DEFAULT NULL",
        'target_name'  => "VARCHAR(255) DEFAULT NULL",
        'target_id'    => "INT DEFAULT NULL",
        'location'     => "TEXT DEFAULT NULL",
        'log_hasil'    => "TEXT DEFAULT NULL",
        'lat'          => "FLOAT DEFAULT NULL",
        'lng'          => "FLOAT DEFAULT NULL",
        'coords_raw'   => "VARCHAR(255) DEFAULT NULL",
        'photos'       => "TEXT DEFAULT NULL"
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
    $target_id   = intval($_POST['target_id'] ?? 0);
    $location    = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $coords_raw  = trim($_POST['coords'] ?? '');
    $coords_esc  = mysqli_real_escape_string($conn, $coords_raw);
    $lat = "NULL";
    $lng = "NULL";
    if($coords_raw !== '') {
        // Only parse decimal lat,lng — do NOT auto-geocode (preserves user input)
        $parts = explode(',', $coords_raw);
        if(count($parts) >= 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
            $lat = floatval(trim($parts[0]));
            $lng = floatval(trim($parts[1]));
        }
        // If Plus Code or text: lat/lng stays NULL, coords_raw saved as-is for Google Maps link
    }
    $teams_raw   = $_POST['teams_involved'] ?? [];
    $teams_str   = mysqli_real_escape_string($conn, implode(',', $teams_raw));
    if($meet_type && $target_name) $title = "Meeting $meet_type $target_name";
    $desc = "[$meet_mode] $title";
    if($location) $desc .= " | Lokasi: $location";

    // Handle photo uploads for both Online and Offline meetings
    $photos_sql = "NULL";
    if(!empty($_FILES['event_photos']['name'][0])) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/visits/';
        if(!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
        $new_photos = [];
        foreach($_FILES['event_photos']['tmp_name'] as $idx => $tmpName) {
            if(!empty($tmpName) && is_uploaded_file($tmpName)) {
                $ext = strtolower(pathinfo($_FILES['event_photos']['name'][$idx], PATHINFO_EXTENSION));
                $fname = 'visit_' . time() . '_' . $idx . ($ext === 'webp' ? '.webp' : '.jpg');
                $targetFile = $upload_dir . $fname;
                if(move_uploaded_file($tmpName, $targetFile)) {
                    $new_photos[] = '/uploads/visits/' . $fname;
                }
            }
        }
        if(!empty($new_photos)) {
            $photos_sql = "'" . mysqli_real_escape_string($conn, json_encode(array_values($new_photos))) . "'";
        }
    }

    mysqli_query($conn, "INSERT INTO events (title, detail, event_date, time_start, color, meeting_type, meeting_mode, target_type, target_name, target_id, location, lat, lng, coords_raw, teams_involved, photos) VALUES ('$title', '$desc', '$date', '$start', '$color', '$meet_type', '$meet_mode', '$target_type', '$target_name', '$target_id', '$location', $lat, $lng, '$coords_esc', '$teams_str', $photos_sql)");
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

// Meetings
$chk_ev = mysqli_query($conn, "SHOW TABLES LIKE 'events'");
if (mysqli_num_rows($chk_ev) > 0) {
    $q_meet = mysqli_query($conn, "SELECT COUNT(*) as c FROM events WHERE MONTH(event_date) = '$bulan_ini' AND YEAR(event_date) = '$tahun_ini'");
    $meetings_done = mysqli_fetch_assoc($q_meet)['c'] ?? 0;
} else {
    $meetings_done = 0;
}
$meeting_target = 12;
$meeting_persen = min(100, ($meetings_done / $meeting_target) * 100);

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
    <link rel="shortcut icon" href="/uploads/icon.png?v=<?= time() ?>">
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
::-webkit-scrollbar-track { background:rgba(255,255,255,0.03); }
::-webkit-scrollbar-thumb { background:#666675; border-radius:10px; border:1px solid rgba(255,255,255,0.1); }
::-webkit-scrollbar-thumb:hover { background:#aaaaaa; }
* { scrollbar-width: thin; scrollbar-color: #666675 rgba(255,255,255,0.03); }

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
.sensor-blur { transition: filter 0.3s ease, opacity 0.3s ease; }
body.sensor-active .sensor-blur { filter: blur(6px) !important; user-select: none !important; pointer-events: none !important; opacity: 0.6; }
.panel-card-box { height: 580px; box-sizing: border-box; display: flex; flex-direction: column; overflow: hidden; border-radius: 16px; background: rgba(18, 18, 18, 0.6); border: 1px solid rgba(255,255,255,0.06); padding: 14px; }

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

/* SHORTCUT MENU */
.shortcut-menu { position:relative; }
.shortcut-btn {
    padding:9px 16px; border-radius:50px; font-weight:700; font-size:0.82rem;
    text-decoration:none; display:flex; align-items:center; gap:7px; transition:0.3s;
    border:1.5px solid var(--neon-sec); background:rgba(78,253,196,0.1); color:var(--neon-sec);
    cursor:pointer; white-space:nowrap;
}
.shortcut-btn:hover { background:var(--neon-sec); color:#000; box-shadow:0 0 20px rgba(78,253,196,0.5); }
.shortcut-dropdown {
    position:absolute; top:calc(100% + 10px); right:0;
    background:#0a0a0a; border:1px solid var(--card-border);
    border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.9);
    z-index:999; display:none; min-width:200px; overflow:hidden;
}
.shortcut-dropdown.active { display:block; animation:scaleIn 0.2s ease; }
.shortcut-dropdown-header {
    padding:12px 18px; border-bottom:1px solid var(--card-border);
    font-size:0.72rem; color:var(--text-muted); letter-spacing:2px; font-weight:700; text-transform:uppercase;
}
.shortcut-item {
    display:flex; align-items:center; gap:12px;
    padding:13px 18px; text-decoration:none; color:#fff;
    transition:0.2s; border-bottom:1px solid rgba(255,255,255,0.04);
    font-size:0.85rem; font-weight:600;
}
.shortcut-item:last-child { border-bottom:none; }
.shortcut-item:hover { background:rgba(255,255,255,0.05); color:var(--neon-sec); }
.shortcut-item i { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; }
.si-green { background:rgba(161,255,90,0.15); color:var(--neon-main); }
.si-cyan { background:rgba(78,253,196,0.15); color:var(--neon-sec); }
.si-gold { background:rgba(255,215,0,0.15); color:#ffd700; }
.si-red { background:rgba(255,90,90,0.15); color:var(--neon-red); }

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
        .nav-arrow-v30:hover { background: #ffffff; color: #000; }
        .arrow-nav-v30 { display: flex; gap: 10px; }
        .mode-switch-v30 { background: rgba(255,255,255,0.05); padding: 5px; border-radius: 15px; display: flex; }
        .mode-switch-v30 button { background: none; border: none; color: #888; padding: 8px 15px; border-radius: 10px; font-weight: 700; font-size: 0.8rem; cursor: pointer; }
        .mode-switch-v30 button.active { background: #fff; color: #000; }
        .planner-viewport { flex: 1; overflow: hidden; background: rgba(0,0,0,0.2); border-radius: 12px; padding: 8px; border: 1px solid var(--card-border); position: relative; display: flex; flex-direction: column; }
        .planner-viewport > * { flex: 1; min-height: 0; }
        .add-event-fab { position: absolute; bottom: 15px; right: 15px; width: 45px; height: 45px; border-radius: 50%; background: #ffffff; color: #000; font-size: 1.3rem; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 15px rgba(255,255,255,0.25); transition: 0.3s; z-index: 10; border: none; }
        .add-event-fab:hover { transform: scale(1.1) rotate(90deg); box-shadow: 0 0 25px rgba(255,255,255,0.4); }
        /* --- CALENDAR GRIDS --- */
        .cal-grid-month, .cal-grid-week { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; width: 100%; box-sizing: border-box; }
        .cal-day-header { text-align: center; font-weight: 800; color: #666; margin-bottom: 4px; font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 3px 0; white-space: nowrap; overflow: hidden; }
        .cal-day-cell { min-height: 60px; box-sizing: border-box; background: rgba(255,255,255,0.03); border-radius: 7px; border: 1px solid rgba(255,255,255,0.07); padding: 5px 6px; cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
        .cal-day-cell:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.3); }
        .cal-day-num { font-weight: 800; font-size: 0.85rem; color: #888; margin-bottom: 0; }
        .cal-day-cell.is-sunday .cal-day-num { color: #888; }
        .cal-day-cell.is-today { background: rgba(255, 255, 255, 0.12); border-color: #ffffff; }
        .cal-day-cell.is-today .cal-day-num { color: #ffffff; font-weight: 900; }
        .cal-event { font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; background: rgba(255,255,255,0.1); color: #fff; font-weight: 700; cursor: pointer; border-left: 3px solid #ffffff; transition: 0.2s; }
        .cal-event:hover { transform: scale(1.03); z-index: 5; background: rgba(255,255,255,0.2); }
        /* Week columns: same proportional height as month */
        .cal-week-col { background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid var(--card-border); min-height: 350px; cursor: pointer; transition: 0.3s; display: flex; flex-direction: column; }
        .cal-week-col:hover { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.3); }
        .cal-week-col .cal-week-header.today { background: rgba(255,255,255,0.12) !important; }
        /* Day view: same card height */
        .cal-grid-day { display: flex; flex-direction: column; gap: 10px; min-height: 350px; padding: 20px; }
        .cal-hour-row { display: flex; gap: 15px; padding: 15px; border-bottom: 1px solid var(--card-border); align-items: center; }
        .cal-time { width: 60px; font-weight: 700; color: #ffffff; }
        .cal-task-area { flex: 1; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 8px; }
        /* MODALS */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 100000; display: none; justify-content: center; align-items: center; backdrop-filter: blur(10px); }
        .modal-overlay.active { display: flex; animation: fadeIn 0.3s; }
        .modal-content { background: #0c0c0e; border: 1px solid rgba(255,255,255,0.1); width: 500px; max-width: 95%; padding: 30px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.9); max-height: 90vh; overflow-y: auto; position: relative; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; color: #aaa; margin-bottom: 5px; font-size: 0.8rem; }
        .form-input { width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--card-border); color: #fff; border-radius: 8px; outline: none; font-size: 0.9rem; }
        .modal-top-actions { position: absolute; top: 20px; right: 20px; display: flex; gap: 15px; }
        .btn-close-x { background: none; border: none; color: #555; font-size: 1.5rem; cursor: pointer; transition: 0.3s; }
        .btn-close-x:hover { color: #fff; }
        .btn-save-center { width: 60px; height: 60px; border-radius: 50%; background: #ffffff; color: #000; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; margin: 20px auto 0 auto; box-shadow: 0 0 20px rgba(255,255,255,0.3); transition: 0.3s; }
        .btn-save-center:hover { transform: scale(1.1); box-shadow: 0 0 35px rgba(255,255,255,0.5); }
        .detail-row { margin-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px; }
        .detail-label { font-size: 0.65rem; color: #777; text-transform: uppercase; letter-spacing: 2px; display: block; margin-bottom: 6px; font-weight: 700; }
        .detail-val { font-size: 1rem; color: #eaeaea; font-weight: 600; line-height: 1.4; }
        .detail-desc { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; color: #aaa; font-size: 0.85rem; line-height: 1.5; }
        /* --- LEAFLET DARK MAP STYLES --- */
        .leaflet-container { background: #0b0b0d !important; font-family: 'Montserrat', sans-serif !important; }
        .leaflet-popup-content-wrapper { background: rgba(14, 14, 14, 0.95) !important; border: 1px solid rgba(255,255,255,0.2) !important; color: #fff !important; border-radius: 12px !important; box-shadow: 0 10px 30px rgba(0,0,0,0.8) !important; }
        .leaflet-popup-tip { background: rgba(14, 14, 14, 0.95) !important; border: 1px solid rgba(255,255,255,0.2) !important; }
        .map-marker-pin { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%; background: #ffffff; color: #000; font-weight: 800; font-size: 0.75rem; border: 2px solid #333; box-shadow: 0 0 12px rgba(255,255,255,0.4); }

        /* ══ SPLIT LAYOUT SYSTEM ══ */
        .split-layout-bar {
            display: flex; gap: 3px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px; padding: 3px;
        }
        .split-layout-btn {
            display: flex; align-items: center; gap: 5px;
            padding: 6px 11px; border-radius: 7px;
            border: none; font-size: 0.75rem; font-weight: 700;
            cursor: pointer; transition: all 0.2s ease;
            background: transparent; color: rgba(255,255,255,0.35);
            white-space: nowrap; font-family: inherit;
            position: relative; overflow: hidden;
        }
        .split-layout-btn:hover { color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.06); }
        .split-layout-btn.active {
            background: #ffffff; color: #000;
            box-shadow: 0 0 10px rgba(255,255,255,0.2);
        }
        /* SVG grid icons for split buttons */
        .split-icon { display: flex; gap: 1.5px; align-items: center; }
        .split-icon-1 { width: 14px; height: 14px; background: currentColor; border-radius: 2px; }
        .split-icon-2 { display: flex; gap: 1.5px; }
        .split-icon-2 span { width: 6px; height: 14px; background: currentColor; border-radius: 2px; }
        .split-icon-3 { display: flex; gap: 1.5px; }
        .split-icon-3 span { width: 4px; height: 14px; background: currentColor; border-radius: 2px; }

        /* SENSOR PRIVACY BLUR MODE */
        body.sensor-active .sensor-blur {
            filter: blur(5.5px) !important;
            user-select: none !important;
            pointer-events: none !important;
            transition: filter 0.3s ease;
        }

        /* Panels Container Layout Transitions */
        #panelsContainer {
            transition: grid-template-columns 0.35s cubic-bezier(0.4,0,0.2,1), gap 0.35s ease;
            align-items: stretch !important;
        }

        /* Panel wrappers — equal height card containers */
        .panel-card-box {
            display: flex; flex-direction: column;
            height: 580px; max-height: 580px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px; padding: 12px;
            box-sizing: border-box; min-width: 0;
        }

        .panel-split-label {
            display: none;
            font-size: 0.62rem; font-weight: 800; letter-spacing: 2px;
            text-transform: uppercase; color: rgba(255,255,255,0.4);
            padding: 0 2px 8px 2px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 10px;
        }
        .split-mode-active .panel-split-label { display: block; }

        /* Calendar panel: always allow scroll, never collapse */
        #panelCalendar { min-height: 480px; }

        /* Map panel: fixed height container */
        #panelMap .map-container-inner {
            position: relative; width: 100%;
            border-radius: 14px; overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
        }
        #panelMap .map-container-inner #meetingMap {
            width: 100%; height: 100%;
            background: #0c0c0c; z-index: 1;
        }

        /* Gallery panel scroll */
        #panelGallery { overflow-y: auto; padding-right: 4px; }

        /* Responsive: force single column on small/medium screens */
        @media (max-width: 1100px) {
            #panelsContainer {
                grid-template-columns: 1fr !important;
            }
            #panelCalendar, #panelMap, #panelGallery {
                display: block !important;
            }
            .split-layout-btn[data-mode="2"],
            .split-layout-btn[data-mode="3"] {
                opacity: 0.4;
                pointer-events: none;
            }
        }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

                    <!-- AI Tool Buttons (chatbot-wa removed) -->
                    <!-- Shortcut Menu -->
                    <div class="shortcut-menu" id="shortcutMenuWrap">
                        <button class="shortcut-btn" onclick="toggleShortcutMenu()">
                            <i class="fas fa-grip-vertical"></i><span>Menu Cepat</span><i class="fas fa-chevron-down" style="font-size:0.7rem;"></i>
                        </button>
                        <div class="shortcut-dropdown" id="shortcutDropdown">
                            <div class="shortcut-dropdown-header">⚡ Akses Cepat</div>
                            <a href="/recap2025/" class="shortcut-item">
                                <i class="fas fa-chart-bar si-green"></i>
                                <span>Recap 2025</span>
                            </a>
                            <a href="/dashboard/invoice/" class="shortcut-item">
                                <i class="fas fa-file-invoice si-cyan"></i>
                                <span>Invoice</span>
                            </a>
                            <a href="/dashboard/clients/" class="shortcut-item">
                                <i class="fas fa-users si-gold"></i>
                                <span>Klien</span>
                            </a>
                            <a href="/email-marketing/" class="shortcut-item">
                                <i class="fas fa-paper-plane si-red"></i>
                                <span>AI Email</span>
                            </a>
                        </div>
                    </div>

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

            <!-- ══ TARGETS DECK ══ -->
            <div class="top-deck">
                
                <!-- REVENUE TARGET CARD -->
                <div class="target-premium-card tier-<?php echo $tier; ?>"
                     style="--tier-color:<?php echo $level_color;?>; margin-bottom:0;">

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

            <!-- MEETING TARGET CARD -->
            <div class="target-premium-card tier-<?php echo $tier; ?>"
                 style="--tier-color:<?php echo $level_color;?>; margin-bottom:0;">
                
                <div class="tp-header">
                    <div class="tp-label">MEETING TARGET</div>
                    <div class="tp-badge">
                        <i class="fas fa-handshake"></i>
                        <span>SINKRON</span>
                    </div>
                </div>

                <div class="tp-amount-row">
                    <div class="tp-achieved">
                        <?php echo $meetings_done; ?><span class="tp-unit">meet</span>
                    </div>
                    <div class="tp-separator">/</div>
                    <div class="tp-goal">
                        12<span class="tp-unit">meet</span>
                    </div>
                </div>

                <!-- 3-Level Track -->
                <div class="tp-track-wrap">
                    <div class="tp-track">
                        <div class="tp-zone zone-red"    style="width:33.3%;left:0"></div>
                        <div class="tp-zone zone-yellow" style="width:33.3%;left:33.3%"></div>
                        <div class="tp-zone zone-green"  style="width:33.4%;left:66.6%"></div>
                        <div class="tp-fill" id="tpFillMeeting" style="width:<?php echo $meeting_persen; ?>%; background:linear-gradient(90deg, #4efdc4, #a1ff5a);"></div>
                        <div class="tp-thumb" id="tpThumbMeeting" style="left:<?php echo $meeting_persen; ?>%">
                            <div class="tp-thumb-inner"></div>
                        </div>
                    </div>
                    <div class="tp-milestones">
                        <div class="tp-milestone" style="left:0%">
                            <div class="ms-dot ms-start"></div>
                            <div class="ms-info"><span class="ms-label">0</span></div>
                        </div>
                        <div class="tp-milestone" style="left:33.3%">
                            <div class="ms-dot <?php echo ($meetings_done>=4)?'ms-done ms-red':'ms-red-empty'; ?>"></div>
                            <div class="ms-info">
                                <span class="ms-label">4</span>
                                <span class="ms-tag">Target 1</span>
                            </div>
                        </div>
                        <div class="tp-milestone" style="left:66.6%">
                            <div class="ms-dot <?php echo ($meetings_done>=8)?'ms-done ms-yellow':'ms-yellow-empty'; ?>"></div>
                            <div class="ms-info">
                                <span class="ms-label">8</span>
                                <span class="ms-tag">Target 2</span>
                            </div>
                        </div>
                        <div class="tp-milestone" style="left:100%">
                            <div class="ms-dot ms-end <?php echo ($meetings_done>=12)?'ms-done ms-gacor':''; ?>"></div>
                            <div class="ms-info">
                                <span class="ms-label">12</span>
                                <span class="ms-tag">Reached</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tp-footer" style="margin-top:10px;">
                    <div class="tp-stat">
                        <span class="tp-stat-label">Progress</span>
                        <span class="tp-stat-val"><?php echo number_format(min(100,$meeting_persen),1); ?>%</span>
                    </div>
                    <div class="tp-stat-center">
                        <span class="tp-msg"><?php echo ($meetings_done>=12) ? 'Excellent Sync!' : 'Perlu lebih banyak meeting!'; ?></span>
                    </div>
                    <div class="tp-stat" style="text-align:right;">
                        <span class="tp-stat-label">Gap ke Target</span>
                        <span class="tp-stat-val">
                            <?php echo (12 - $meetings_done) > 0 ? (12 - $meetings_done).' meet' : 'DONE!'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

            <!-- PLANNER / CALENDAR + PETA (TABBED) -->
            <div class="zenith-grid-layout">
                <div class="zenith-panel glass-card planner-deck animate-slide-up" style="background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; position: relative;">

                    <!-- ── TIER 1: MAIN CARD HEADER (TABS, SENSOR TOGGLE & SPLIT SWITCHER) ── -->
                    <div class="planner-deck-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding: 4px 4px 10px 4px; border-bottom:1px solid rgba(255,255,255,0.06); margin-bottom:4px;">
                        <!-- Left: Tab Switcher -->
                        <div id="plannerTabBar" style="display:flex; gap:4px; background:rgba(255,255,255,0.05); border-radius:12px; padding:4px;">
                            <button id="tabBtnCalendar" onclick="switchPlannerTab('calendar')" style="display:flex;align-items:center;gap:7px; padding:7px 18px; border-radius:9px; border:none; font-size:0.82rem; font-weight:700; cursor:pointer; transition:all .22s; background:#ffffff; color:#000;"><i class="fas fa-calendar-alt"></i> Kalender</button>
                            <button id="tabBtnMap" onclick="switchPlannerTab('map')" style="display:flex;align-items:center;gap:7px; padding:7px 18px; border-radius:9px; border:none; font-size:0.82rem; font-weight:700; cursor:pointer; transition:all .22s; background:transparent; color:#888;"><i class="fas fa-map-marked-alt"></i> Peta</button>
                            <button id="tabBtnGallery" onclick="switchPlannerTab('gallery')" style="display:flex;align-items:center;gap:7px; padding:7px 18px; border-radius:9px; border:none; font-size:0.82rem; font-weight:700; cursor:pointer; transition:all .22s; background:transparent; color:#888;"><i class="fas fa-images"></i> Dokumentasi Meeting</button>
                        </div>

                        <!-- Right: Sensor Toggle & Split Layout Switcher -->
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <!-- Privacy Sensor Toggle -->
                            <button id="btnSensorToggle" onclick="toggleSensorMode()" style="display:flex; align-items:center; gap:6px; padding:6px 14px; border-radius:8px; border:1px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.05); color:#888; font-size:0.75rem; font-weight:700; cursor:pointer; transition:all 0.2s; font-family:inherit;" title="Toggle Sensor Mode (Blur Nama Perusahaan & Lokasi)">
                                <i id="sensorIcon" class="fas fa-eye-slash"></i> <span id="sensorText">Sensor: OFF</span>
                            </button>

                            <!-- Split Layout Switcher -->
                            <div id="splitModeBar" class="split-layout-bar" title="Layout Panel">
                                <button id="btnSplit1" class="split-layout-btn active" data-mode="1" onclick="setSplitLayout(1)" title="1 Panel – Full Width">
                                    <span class="split-icon"><span class="split-icon-1"></span></span>
                                    <span class="split-btn-label">Full</span>
                                </button>
                                <button id="btnSplit2" class="split-layout-btn" data-mode="2" onclick="setSplitLayout(2)" title="2 Panel – Split 50/50">
                                    <span class="split-icon split-icon-2"><span></span><span></span></span>
                                    <span class="split-btn-label">Split 2</span>
                                </button>
                                <button id="btnSplit3" class="split-layout-btn" data-mode="3" onclick="setSplitLayout(3)" title="3 Panel – Semua Tab Tampil">
                                    <span class="split-icon split-icon-3"><span></span><span></span><span></span></span>
                                    <span class="split-btn-label">Split 3</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── TIER 2: PANELS CONTAINER (DYNAMIC GRID SPLIT) ── -->
                    <div id="panelsContainer" style="display:grid; grid-template-columns:1fr; gap:16px; width:100%; padding-top:4px; align-items:stretch;">

                        <!-- PANEL 1: KALENDER -->
                        <div id="panelCalendar" class="panel-card-box" style="display:flex; flex-direction:column;">
                            <div class="panel-split-label"><i class="fas fa-calendar-alt" style="margin-right:6px; color:#ffffff;"></i>Kalender</div>
                            
                            <!-- Calendar Panel Dedicated Subtoolbar -->
                            <div class="panel-subtoolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); padding:8px 14px; border-radius:12px; margin-bottom:12px;">
                                <div class="ph-left" style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                                    <label style="position:relative; display:inline-flex; align-items:center; margin:0; cursor:pointer;" title="Ubah Bulan/Tahun">
                                        <h2 id="plannerTitle" style="margin:0; font-size:1.05rem; font-weight:800; color:#ffffff; white-space:nowrap; display:flex; align-items:center; gap:6px;">
                                            ... <i class="fas fa-chevron-down" style="font-size:0.7rem; color:#888;"></i>
                                        </h2>
                                        <input type="month" id="monthPicker" onchange="jumpToMonth(this.value)" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer; font-size:0; padding:0; border:none; z-index:10;">
                                    </label>
                                    <div class="ph-nav-group" style="display:flex; align-items:center; gap:6px;">
                                        <button class="btn-today-v30" onclick="goToday()" style="background:#fff; color:#000; padding:6px 14px; border-radius:50px; font-weight:800; font-size:0.68rem; cursor:pointer; border:none;">TODAY</button>
                                        <div class="arrow-nav-v30" style="display:flex; gap:4px;">
                                            <button onclick="navigatePlanner(-1)" class="nav-arrow-v30" style="width:30px; height:30px; border-radius:50%; background:rgba(255,255,255,0.05); color:#fff; border:1px solid rgba(255,255,255,0.1); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.75rem;"><i class="fas fa-chevron-left"></i></button>
                                            <button onclick="navigatePlanner(1)" class="nav-arrow-v30" style="width:30px; height:30px; border-radius:50%; background:rgba(255,255,255,0.05); color:#fff; border:1px solid rgba(255,255,255,0.1); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.75rem;"><i class="fas fa-chevron-right"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="ph-right">
                                    <div class="mode-switch-v30" style="background:rgba(255,255,255,0.05); padding:4px; border-radius:10px; display:flex; gap:2px;">
                                        <button id="btn-month" class="active" onclick="setMode('month', this)">Month</button>
                                        <button id="btn-week" onclick="setMode('week', this)">Week</button>
                                        <button id="btn-day" onclick="setMode('day', this)">Day</button>
                                    </div>
                                </div>
                            </div>

                            <div id="calendarViewport" class="planner-viewport" style="width:100%; flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:10px;"></div>
                        </div>

                        <!-- PANEL 2: PETA -->
                        <div id="panelMap" class="panel-card-box" style="display:none; flex-direction:column;">
                            <div class="panel-split-label"><i class="fas fa-map-marked-alt" style="margin-right:6px; color:#ffffff;"></i>Peta Kunjungan</div>
                            
                            <!-- Map Panel Dedicated Subtoolbar -->
                            <div class="panel-subtoolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); padding:8px 12px; border-radius:12px; margin-bottom:10px;">
                                <div style="font-size:0.85rem; font-weight:800; color:#fff; display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-map-marked-alt" style="color:#aaa;"></i> Peta Kunjungan
                                </div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <select id="mapFilterPeriod" onchange="loadMapMeetings(this.value)" style="padding:6px 12px; font-size:0.78rem; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:#fff; outline:none;">
                                        <option value="7d" style="background:#111;color:#fff;">7 Hari Terakhir</option>
                                        <option value="30d" style="background:#111;color:#fff;">30 Hari Terakhir</option>
                                        <option value="month" selected style="background:#111;color:#fff;">Bulan Ini</option>
                                        <option value="all" style="background:#111;color:#fff;">Semua Kunjungan</option>
                                    </select>
                                    <button type="button" onclick="loadMapMeetings()" style="background:#ffffff; border:none; color:#000; padding:6px 12px; border-radius:8px; font-size:0.75rem; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:6px;" title="Refresh Peta"><i class="fas fa-sync-alt"></i> Refresh</button>
                                </div>
                            </div>

                            <div class="map-container-inner" style="flex:1; width:100%; min-height:0; position:relative; border-radius:12px; overflow:hidden;">
                                <div id="meetingMap" style="width:100%; height:100%;"></div>
                            </div>
                        </div>

                        <!-- PANEL 3: DOKUMENTASI MEETING -->
                        <div id="panelGallery" class="panel-card-box" style="display:none; flex-direction:column;">
                            <div class="panel-split-label"><i class="fas fa-images" style="margin-right:6px; color:#ffffff;"></i>Dokumentasi Meeting</div>
                            
                            <!-- Gallery Panel Dedicated Subtoolbar -->
                            <div class="panel-subtoolbar" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); padding:8px 12px; border-radius:12px; margin-bottom:10px;">
                                <div style="font-size:0.85rem; font-weight:800; color:#fff; display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-images" style="color:#aaa;"></i> Dokumentasi Meeting
                                </div>
                                <button type="button" onclick="loadGalleryVisits()" style="background:#ffffff; border:none; color:#000; padding:6px 12px; border-radius:8px; font-size:0.75rem; font-weight:800; cursor:pointer; display:flex; align-items:center; gap:6px;" title="Refresh Galeri"><i class="fas fa-sync-alt"></i> Refresh</button>
                            </div>

                            <div id="galleryGrid" style="flex:1; overflow-y:auto; display:grid; grid-template-columns:repeat(2, 1fr); gap:10px; padding-right:4px;"></div>
                        </div>

                    </div>

                    <!-- Floating Add Button -->
                    <button class="add-event-fab" onclick="openEventModal()"><i class="fas fa-plus"></i></button>
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

            <form method="POST" id="eventForm" enctype="multipart/form-data">
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
                    <input type="hidden" name="target_id" id="d-target-id" value="">
                    <select name="target_name" id="d-company-sel" class="form-input" style="background:#111;" onchange="dashOnSelectChange()">
                        <option value="">-- Pilih Perusahaan --</option>
                        <?php
                        $qc = mysqli_query($conn, "SELECT client_id, company_name FROM clients ORDER BY company_name ASC");
                        while($r = mysqli_fetch_assoc($qc)) echo "<option value='".htmlspecialchars($r['company_name'])."' data-type='Client' data-id='".htmlspecialchars($r['client_id'])."'>".htmlspecialchars($r['company_name'])."</option>";
                        $qp_chk = mysqli_query($conn, "SHOW TABLES LIKE 'prospects'");
                        if(mysqli_num_rows($qp_chk) > 0){
                            $qp = mysqli_query($conn, "SELECT id, company_name FROM prospects ORDER BY company_name ASC");
                            while($r = mysqli_fetch_assoc($qp)) echo "<option value='".htmlspecialchars($r['company_name'])."' data-type='Prospect' data-id='".htmlspecialchars($r['id'])."' class='opt-prospect' style='display:none'>".htmlspecialchars($r['company_name'])."</option>";
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

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div class="form-group">
                        <label style="color:#888;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;" id="d-loc-label"><i class="fas fa-building" style="margin-right:4px;"></i>Nama Lokasi / Tempat</label>
                        <input type="text" name="location" class="form-input" id="d-loc-input" placeholder="contoh: Tomorrow Coffee Graha Pena">
                    </div>
                    <div class="form-group">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <label style="color:#888;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.5px;margin:0;"><i class="fas fa-map-marker-alt" style="margin-right:4px;color:#ff9f43;"></i>Koordinat (Opsional)</label>
                            <span id="geoStatusMsg" style="font-size:0.68rem;color:#a1ff5a;display:none;"><i class="fas fa-check"></i></span>
                        </div>
                        <input type="text" name="coords" class="form-input" id="d-coords-input" placeholder="contoh: -7.3164, 112.7342 atau Plus Code">
                        <input type="hidden" name="lat" id="d-lat-input" value="">
                        <input type="hidden" name="lng" id="d-lng-input" value="">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;"><i class="fas fa-users" style="margin-right:4px;"></i>Tim yang Hadir</label>
                    <div id="teamCheckboxList" style="display:flex; flex-wrap:wrap; gap:8px;">
                        <?php
                        $qt = mysqli_query($conn, "SELECT team_id, name, photo FROM teams ORDER BY name ASC");
                        while($tm = mysqli_fetch_assoc($qt)):
                            $photo_url = $tm['photo'] ? '/uploads/teams/'.$tm['photo'] : null;
                        ?>
                        <label style="cursor:pointer; display:flex; align-items:center; gap:6px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:8px; padding:6px 10px; transition:0.2s;" class="team-check-label">
                            <input type="checkbox" name="teams_involved[]" value="<?= htmlspecialchars($tm['name']) ?>" style="display:none;" class="team-cb" onchange="this.parentElement.style.background = this.checked ? 'rgba(161,255,90,0.1)' : 'rgba(255,255,255,0.04)'; this.parentElement.style.borderColor = this.checked ? 'rgba(161,255,90,0.4)' : 'rgba(255,255,255,0.08)';">
                            <?php if($photo_url): ?>
                                <img src="<?= $photo_url ?>" style="width:22px;height:22px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                                <span style="width:22px;height:22px;border-radius:50%;background:rgba(161,255,90,0.15);display:flex;align-items:center;justify-content:center;font-size:0.6rem;color:#a1ff5a;font-weight:700;"><?= strtoupper(substr($tm['name'],0,1)) ?></span>
                            <?php endif; ?>
                            <span style="font-size:0.8rem;color:#ccc;"><?= htmlspecialchars($tm['name']) ?></span>
                        </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;"><i class="fas fa-camera" style="margin-right:4px; color:#a1ff5a;"></i>Foto Dokumentasi Meeting (Online & Offline)</label>
                    <input type="file" name="event_photos[]" accept="image/*" multiple class="form-input" style="background:#111; padding:6px; font-size:0.8rem; color:#ccc;">
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
        <div class="modal-content" style="background:#0c0c0e; border:1px solid rgba(255,255,255,0.1); width:600px; max-width:96%; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,0.9); position:relative; max-height:92vh; display:flex; flex-direction:column; overflow:hidden;">
            <div style="padding:20px 24px; border-bottom:1px solid rgba(255,255,255,0.07); display:flex; justify-content:space-between; align-items:center;">
                <h2 id="detailModalTitle" style="color:#fff; font-size:1.1rem; font-weight:800;"><i class="fas fa-calendar-check" style="color:#a1ff5a; margin-right:8px;"></i>Detail Meeting</h2>
                <button class="btn-close-x" onclick="closeModal('detailModal')">&times;</button>
            </div>
            <div id="detailContent" style="overflow-y:auto; padding:24px; flex:1;"></div>
            <div id="detailFooter" style="padding:16px 24px; border-top:1px solid rgba(255,255,255,0.07); display:flex; justify-content:flex-end; gap:10px;"></div>
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

        function dashOnSelectChange() {
            const sel = document.getElementById('d-company-sel');
            const opt = sel.options[sel.selectedIndex];
            document.getElementById('d-target-id').value = opt ? (opt.dataset.id || '') : '';
            dashUpdateTitle();
        }

        function dashSwitchTarget(type) {
            document.getElementById('d-target-type').value = type;
            document.getElementById('d-target-id').value = '';
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

        function handleDayClick(dateStr, events) {
            if(!events || !Array.isArray(events) || events.length === 0) {
                openEventModal(dateStr);
                return;
            }
            if(events.length === 1) {
                const ev = events[0];
                showEventDetail(ev.title || 'Meeting', dateStr, ev.time_start || '', ev.detail || '', 'white', ev.id || 0);
                return;
            }
            showDayEventsList(dateStr, events);
        }

        function showDayEventsList(dateStr, events) {
            const container = document.getElementById('detailContent');
            const footer    = document.getElementById('detailFooter');
            const dateObj   = new Date(dateStr + 'T00:00:00');
            const dateNice  = dateObj.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

            let itemsHtml = '';
            events.forEach(ev => {
                const title = escHtml(ev.title || 'Meeting');
                const time = ev.time_start ? ev.time_start : 'Seharian';
                const evId = ev.id || 0;
                const safeTitle = (ev.title || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const safeDesc = (ev.detail || '').replace(/'/g, "\\'").replace(/\n/g, "\\n");

                itemsHtml += `
                    <div onclick="showEventDetail('${safeTitle}', '${dateStr}', '${ev.time_start||''}', '${safeDesc}', 'white', ${evId})" style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:12px; margin-bottom:8px; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='rgba(255,255,255,0.04)'">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.9rem; font-weight:800; color:#fff;">${title}</span>
                            <span style="font-size:0.72rem; color:#aaa; font-weight:600;"><i class="far fa-clock" style="margin-right:4px;"></i>${time}</span>
                        </div>
                        ${ev.detail ? `<div style="font-size:0.78rem; color:#888; margin-top:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escHtml(ev.detail)}</div>` : ''}
                    </div>
                `;
            });

            container.innerHTML = `
                <div style="margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-size:0.65rem; color:#888; text-transform:uppercase; letter-spacing:1.5px; font-weight:700;">MEETING TANGGAL</div>
                        <div style="font-size:1.1rem; color:#fff; font-weight:800;">${dateNice}</div>
                    </div>
                    <button onclick="openEventModal('${dateStr}')" style="background:#ffffff; color:#000; border:none; padding:6px 14px; border-radius:8px; font-size:0.75rem; font-weight:800; cursor:pointer;"><i class="fas fa-plus"></i> Tambah</button>
                </div>
                <div style="max-height:350px; overflow-y:auto;">
                    ${itemsHtml}
                </div>
            `;

            footer.innerHTML = `
                <button onclick="openEventModal('${dateStr}')" style="background:#ffffff; border:none; color:#000; border-radius:10px; padding:8px 20px; font-family:inherit; font-size:0.82rem; font-weight:800; cursor:pointer;"><i class="fas fa-plus"></i> + Tambah Meeting Baru</button>
            `;

            document.getElementById('detailModal').classList.add('active');
        }

        function showEventDetail(title, date, time, desc, color, eventId = 0) {
            const container = document.getElementById('detailContent');
            const footer    = document.getElementById('detailFooter');
            const dateObj = new Date(date + 'T00:00:00');
            const dateNice = dateObj.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

            // Tampilkan view dulu
            container.innerHTML = `
                <div style="margin-bottom:16px;">
                    <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:2px;font-weight:700;margin-bottom:5px;">JUDUL MEETING</div>
                    <div style="font-size:1.05rem;color:#fff;font-weight:700;">${title}</div>
                </div>
                <div style="margin-bottom:16px;">
                    <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:2px;font-weight:700;margin-bottom:5px;">WAKTU</div>
                    <div style="font-size:0.9rem;color:#ccc;">${dateNice} &bull; ${time || 'Seharian'}</div>
                </div>
                <div style="margin-bottom:16px;">
                    <div style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:2px;font-weight:700;margin-bottom:5px;">DETAIL LOG</div>
                    <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);padding:12px;border-radius:10px;color:#aaa;font-size:0.85rem;line-height:1.6;white-space:pre-wrap;">${desc.replace(/(^|\n)\*\s+([^\n]+)/g, '$1<div style="display:flex; margin-bottom:4px;"><span style="color:#ffffff;margin-right:8px;">&bull;</span><span style="color:#e0e0e0;">$2</span></div>')}</div>
                </div>
                <div id="extraEventDetail" style="color:#888;font-size:0.8rem;">Memuat detail...</div>
            `;
            footer.innerHTML = `
                <button onclick="deleteEvent(${eventId})" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);color:#888;border-radius:10px;padding:8px 16px;font-family:inherit;font-size:0.82rem;cursor:pointer;"><i class="fas fa-trash"></i> Hapus</button>
                <button onclick="openEditEvent(${eventId})" style="background:#ffffff;border:none;color:#000;border-radius:10px;padding:8px 20px;font-family:inherit;font-size:0.82rem;font-weight:800;cursor:pointer;"><i class="fas fa-edit"></i> Edit Meeting</button>
            `;
            document.getElementById('detailModal').classList.add('active');

            // Load extra detail via AJAX if id is valid
            if(eventId > 0) {
                fetch(`/dashboard/workspace/index.php?get_event=1&id=${eventId}`)
                    .then(r => r.json())
                    .then(res => {
                        const ev = res.event;
                        if(!ev) return;
                        let extra = '';
                        if(ev.meeting_type) extra += `<div style="margin-bottom:10px;"><span style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">JENIS & MODE</span><div style="margin-top:4px;">${ev.meeting_type} &bull; <span style="color:#ffffff;">${ev.meeting_mode}</span></div></div>`;
                        if(ev.target_name) extra += `<div style="margin-bottom:10px;"><span style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">PERUSAHAAN</span><div style="margin-top:4px;color:#fff;font-weight:600;">${ev.target_type}: ${ev.target_name}</div></div>`;
                        if(ev.location) extra += `<div style="margin-bottom:10px;"><span style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">LOKASI</span><div style="margin-top:4px;"><a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(ev.location)}" target="_blank" style="color:#ffffff;text-decoration:underline;">${ev.location} <i class="fas fa-external-link-alt" style="font-size:0.7rem;"></i></a></div></div>`;
                        if(ev.teams_involved) { const tms=ev.teams_involved.split(',').filter(t=>t.trim()); if(tms.length>0) extra += `<div style="margin-bottom:10px;"><span style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">TIM HADIR</span><div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;">${tms.map(t=>`<span style="font-size:0.72rem;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.18);color:#ffffff;padding:2px 8px;border-radius:20px;">${t.trim()}</span>`).join('')}</div></div>`; }
                        if(ev.log_hasil) extra += `<div style="margin-bottom:10px;"><span style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">LOG HASIL</span><div style="margin-top:4px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);padding:10px;border-radius:8px;color:#ccc;font-size:0.83rem;white-space:pre-wrap;">${ev.log_hasil}</div></div>`;
                        document.getElementById('extraEventDetail').innerHTML = extra || '';
                    }).catch(()=>{ document.getElementById('extraEventDetail').innerHTML = ''; });
            } else {
                document.getElementById('extraEventDetail').innerHTML = '';
            }
        }

        function deleteEvent(id) {
            if(!confirm('Yakin hapus meeting ini?')) return;
            const fd = new FormData();
            fd.append('delete_event', 1);
            fd.append('event_id', id);
            fetch('/dashboard/workspace/index.php', {method:'POST', body:fd})
                .then(r=>r.json())
                .then(()=>{ closeModal('detailModal'); refreshPlanner(); })
                .catch(()=>alert('Gagal hapus.'));
        }

        let existingPhotos = [];
        let newPhotoBlobs = [];

        async function compressImageToWebP(file, maxDimension = 1200, quality = 0.82) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        let width = img.width;
                        let height = img.height;
                        if(width > maxDimension || height > maxDimension) {
                            if(width > height) {
                                height = Math.round((height * maxDimension) / width);
                                width = maxDimension;
                            } else {
                                width = Math.round((width * maxDimension) / height);
                                height = maxDimension;
                            }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        canvas.toBlob(blob => {
                            resolve(blob);
                        }, 'image/webp', quality);
                    };
                    img.onerror = reject;
                    img.src = e.target.result;
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }

        async function handlePhotoUpload(input) {
            if(!input.files || input.files.length === 0) return;
            const previewWrap = document.getElementById('photoPreviewWrap');
            for(let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                try {
                    const webpBlob = await compressImageToWebP(file, 1200, 0.82);
                    newPhotoBlobs.push(webpBlob);
                    const url = URL.createObjectURL(webpBlob);
                    const thumbIdx = newPhotoBlobs.length - 1;
                    const thumbDiv = document.createElement('div');
                    thumbDiv.className = 'photo-thumb-item';
                    thumbDiv.style.cssText = 'position:relative; width:70px; height:70px; border-radius:8px; overflow:hidden; border:1px solid rgba(161,255,90,0.4);';
                    thumbDiv.innerHTML = `
                        <img src="${url}" style="width:100%; height:100%; object-fit:cover;">
                        <button type="button" onclick="removeNewPhoto(${thumbIdx}, this)" style="position:absolute; top:2px; right:2px; background:rgba(0,0,0,0.7); border:none; color:#ff5a5a; border-radius:50%; width:20px; height:20px; font-size:10px; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
                    `;
                    previewWrap.appendChild(thumbDiv);
                } catch(e) {
                    console.error("Failed to compress image", e);
                }
            }
            input.value = '';
        }

        function removeExistingPhoto(photoUrl, btnEl) {
            existingPhotos = existingPhotos.filter(p => p !== photoUrl);
            btnEl.closest('.photo-thumb-item').remove();
        }

        function removeNewPhoto(idx, btnEl) {
            newPhotoBlobs[idx] = null;
            btnEl.closest('.photo-thumb-item').remove();
        }

        async function openEditEvent(id) {
            const res = await fetch(`/dashboard/workspace/index.php?get_event=1&id=${id}`).then(r=>r.json());
            const ev = res.event;
            const teams = res.teams || [];
            if(!ev) return;

            newPhotoBlobs = [];
            try {
                existingPhotos = ev.photos ? (typeof ev.photos==='string' ? JSON.parse(ev.photos) : ev.photos) : [];
            } catch(e) { existingPhotos = []; }

            document.getElementById('detailModalTitle').innerHTML = '<i class="fas fa-edit" style="color:#a1ff5a;margin-right:8px;"></i>Edit Meeting';
            
            let existingPhotosHtml = existingPhotos.map(p => `
                <div class="photo-thumb-item" style="position:relative; width:70px; height:70px; border-radius:8px; overflow:hidden; border:1px solid rgba(255,255,255,0.15);">
                    <img src="${p}" style="width:100%; height:100%; object-fit:cover;" onclick="openPhotoLightbox('${p}')">
                    <button type="button" onclick="removeExistingPhoto('${p}', this)" style="position:absolute; top:2px; right:2px; background:rgba(0,0,0,0.7); border:none; color:#ff5a5a; border-radius:50%; width:20px; height:20px; font-size:10px; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
                </div>
            `).join('');

            document.getElementById('detailContent').innerHTML = `
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Jenis Meeting</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;" id="editMeetTypes">
                        ${['Prospek','Maintenance','After Sales','Internal','Presentasi'].map(mt=>`<label style="cursor:pointer;"><input type="radio" name="em_meet_type" value="${mt}" ${ev.meeting_type===mt?'checked':''} style="display:none;"><span class="meet-type-chip" style="padding:4px 10px;font-size:0.8rem;border:1px solid rgba(255,255,255,0.1);border-radius:20px;${ev.meeting_type===mt?'background:rgba(161,255,90,0.2);border-color:rgba(161,255,90,0.6);color:#a1ff5a;':'color:#ccc;'}">${mt}</span></label>`).join('')}
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                    <div>
                        <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Tanggal</label>
                        <input type="date" id="em_date" value="${ev.event_date}" style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;padding:8px;font-family:inherit;">
                    </div>
                    <div>
                        <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Jam</label>
                        <input type="time" id="em_time" value="${ev.time_start}" style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;padding:8px;font-family:inherit;">
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Mode</label>
                    <div style="display:flex;gap:8px;">
                        <label style="cursor:pointer;"><input type="radio" name="em_mode" value="Online" ${ev.meeting_mode!=='Offline'?'checked':''} style="display:none;"><span class="meet-mode-chip" style="padding:4px 10px;font-size:0.8rem;border:1px solid rgba(255,255,255,0.1);border-radius:20px;${ev.meeting_mode!=='Offline'?'background:rgba(161,255,90,0.2);border-color:rgba(161,255,90,0.6);color:#a1ff5a;':'color:#ccc;'}"><i class="fas fa-video"></i> Online</span></label>
                        <label style="cursor:pointer;"><input type="radio" name="em_mode" value="Offline" ${ev.meeting_mode==='Offline'?'checked':''} style="display:none;"><span class="meet-mode-chip" style="padding:4px 10px;font-size:0.8rem;border:1px solid rgba(255,255,255,0.1);border-radius:20px;${ev.meeting_mode==='Offline'?'background:rgba(161,255,90,0.2);border-color:rgba(161,255,90,0.6);color:#a1ff5a;':'color:#ccc;'}"><i class="fas fa-map-marker-alt"></i> Offline</span></label>
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Perusahaan</label>
                    <input type="text" id="em_target_name" value="${ev.target_name||''}" style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;padding:8px;font-family:inherit;">
                    <input type="hidden" id="em_target_type" value="${ev.target_type||'Client'}">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                    <div>
                        <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;"><i class="fas fa-building" style="margin-right:4px;"></i>Nama Lokasi / Tempat</label>
                        <input type="text" id="em_location" value="${ev.location||''}" placeholder="contoh: Tomorrow Coffee Graha Pena" style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;padding:8px;font-family:inherit;">
                    </div>
                    <div>
                        <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;"><i class="fas fa-map-marker-alt" style="margin-right:4px;color:#ff9f43;"></i>Koordinat (Opsional)</label>
                        <input type="text" id="em_coords" value="${ev.coords_raw || ((ev.lat && ev.lng) ? (ev.lat + ', ' + ev.lng) : '')}" placeholder="contoh: -7.3164, 112.7342 atau Plus Code" style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;padding:8px;font-family:inherit;">
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:8px;"><i class="fas fa-users" style="margin-right:4px;"></i>Tim yang Hadir</label>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        ${teams.map(tm=>{ const checked=(ev.teams_involved||'').split(',').map(s=>s.trim()).includes(tm); return `<label style="cursor:pointer;display:flex;align-items:center;gap:5px;background:${checked?'rgba(161,255,90,0.1)':'rgba(255,255,255,0.04)'};border:1px solid ${checked?'rgba(161,255,90,0.4)':'rgba(255,255,255,0.08)'};border-radius:8px;padding:5px 10px;transition:0.2s;" class="team-check-label"><input type="checkbox" name="em_teams[]" value="${tm}" ${checked?'checked':''} style="display:none;" class="team-cb" onchange="this.parentElement.style.background = this.checked ? 'rgba(161,255,90,0.1)' : 'rgba(255,255,255,0.04)'; this.parentElement.style.borderColor = this.checked ? 'rgba(161,255,90,0.4)' : 'rgba(255,255,255,0.08)';"><span style="font-size:0.8rem;color:#ccc;">${tm}</span></label>`; }).join('')}
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Log Hasil Meeting</label>
                    <textarea id="em_log" rows="3" style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;padding:10px;font-family:inherit;resize:none;">${ev.log_hasil||''}</textarea>
                </div>

                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:6px;"><i class="fas fa-camera" style="margin-right:4px;color:var(--neon-main);"></i>Foto Visit / Dokumentasi (Auto Compress WebP)</label>
                    <input type="file" id="em_photos_input" accept="image/*" multiple style="display:none;" onchange="handlePhotoUpload(this)">
                    <div id="photoPreviewWrap" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
                        ${existingPhotosHtml}
                    </div>
                    <button type="button" onclick="document.getElementById('em_photos_input').click()" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); color:#ccc; padding:7px 14px; border-radius:8px; font-size:0.78rem; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:6px;"><i class="fas fa-upload" style="color:#4efdc4;"></i> Upload Foto Visit</button>
                </div>
            `;
            document.getElementById('detailFooter').innerHTML = `
                <button onclick="showEventDetail('${ev.title}','${ev.event_date}','${ev.time_start}','${ev.detail||''}','${ev.color}',${id})" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#888;border-radius:10px;padding:8px 16px;font-family:inherit;font-size:0.82rem;cursor:pointer;">← Kembali</button>
                <button onclick="saveEditEvent(${id})" style="background:linear-gradient(135deg,#a1ff5a,#4efdc4);border:none;color:#000;border-radius:10px;padding:8px 20px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;"><i class="fas fa-save"></i> Simpan</button>
            `;
            document.querySelectorAll('#detailContent input[name=em_meet_type]').forEach(r=>r.addEventListener('change', function(){ document.querySelectorAll('#editMeetTypes .meet-type-chip').forEach(s=>{s.style.background='';s.style.borderColor='rgba(255,255,255,0.1)';s.style.color='#ccc';}); this.nextElementSibling.style.background='rgba(161,255,90,0.2)'; this.nextElementSibling.style.borderColor='rgba(161,255,90,0.6)'; this.nextElementSibling.style.color='#a1ff5a'; }));
            document.querySelectorAll('#detailContent input[name=em_mode]').forEach(r=>r.addEventListener('change', function(){ document.querySelectorAll('#detailContent .meet-mode-chip').forEach(s=>{s.style.background='';s.style.borderColor='rgba(255,255,255,0.1)';s.style.color='#ccc';}); this.nextElementSibling.style.background='rgba(161,255,90,0.2)'; this.nextElementSibling.style.borderColor='rgba(161,255,90,0.6)'; this.nextElementSibling.style.color='#a1ff5a'; }));

            document.getElementById('detailModal').classList.add('active');
        }

        function saveEditEvent(id) {
            const meet_type = document.querySelector('#detailContent input[name=em_meet_type]:checked')?.value || '';
            const meet_mode = document.querySelector('#detailContent input[name=em_mode]:checked')?.value || 'Online';
            const target_name = document.getElementById('em_target_name').value;
            const target_type = document.getElementById('em_target_type').value;
            const event_date  = document.getElementById('em_date').value;
            const time_start  = document.getElementById('em_time').value;
            const location    = document.getElementById('em_location').value;
            const coords      = document.getElementById('em_coords').value;
            const log_hasil   = document.getElementById('em_log').value;
            const teams = Array.from(document.querySelectorAll('#detailContent input[name="em_teams[]"]:checked')).map(c=>c.value);

            const fd = new FormData();
            fd.append('update_event', 1);
            fd.append('event_id', id);
            fd.append('meeting_type', meet_type);
            fd.append('meeting_mode', meet_mode);
            fd.append('target_name', target_name);
            fd.append('target_type', target_type);
            fd.append('event_date', event_date);
            fd.append('time_start', time_start);
            fd.append('location', location);
            fd.append('coords', coords);
            fd.append('log_hasil', log_hasil);
            fd.append('existing_photos', JSON.stringify(existingPhotos));
            teams.forEach(t => fd.append('teams_involved[]', t));

            newPhotoBlobs.forEach((blob, idx) => {
                if(blob) fd.append('event_photos[]', blob, `photo_${idx}.webp`);
            });

            fetch('/dashboard/workspace/index.php', {method:'POST', body:fd})
                .then(r=>r.json())
                .then(res=>{ if(res.ok){ closeModal('detailModal'); refreshPlanner(); loadMapMeetings(); if(document.getElementById('panelGallery')?.style.display==='block') loadGalleryVisits(); } })
                .catch(()=>alert('Gagal simpan.'));
        }

        
        window.addEventListener('load', function() { 
            refreshPlanner(); 
            initMeetingMap();
        });

        // --- INTERACTIVE OPENSTREETMAP / LEAFLET LOGIC ---
        let _leafletMap = null;
        let _mapMarkersLayer = null;
        let _mapPolylineLayer = null;

        function initMeetingMap() {
            const mapEl = document.getElementById('meetingMap');
            if(!mapEl || _leafletMap) return;
            
            // Center default: Surabaya (-7.2575, 112.7521)
            _leafletMap = L.map('meetingMap', { zoomControl: true }).setView([-7.2575, 112.7521], 12);
            
            // OpenStreetMap — 100% gratis, no API key
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(_leafletMap);

            _mapMarkersLayer = L.layerGroup().addTo(_leafletMap);
            _mapPolylineLayer = L.layerGroup().addTo(_leafletMap);

            loadMapMeetings('month');
        }

        // ── Sensor Privacy Toggle Logic ──
        let sensorModeActive = localStorage.getItem('hvm_sensor_mode') === 'true';

        function updateSensorUI() {
            const btn = document.getElementById('btnSensorToggle');
            const icon = document.getElementById('sensorIcon');
            const text = document.getElementById('sensorText');
            if (sensorModeActive) {
                document.body.classList.add('sensor-active');
                if (btn) {
                    btn.style.background = 'rgba(255, 90, 90, 0.15)';
                    btn.style.borderColor = '#ff5a5a';
                    btn.style.color = '#ff5a5a';
                }
                if (icon) icon.className = 'fas fa-eye';
                if (text) text.textContent = 'Sensor: ON';
            } else {
                document.body.classList.remove('sensor-active');
                if (btn) {
                    btn.style.background = 'rgba(255, 255, 255, 0.05)';
                    btn.style.borderColor = 'rgba(255, 255, 255, 0.15)';
                    btn.style.color = '#888';
                }
                if (icon) icon.className = 'fas fa-eye-slash';
                if (text) text.textContent = 'Sensor: OFF';
            }
        }

        function toggleSensorMode() {
            sensorModeActive = !sensorModeActive;
            localStorage.setItem('hvm_sensor_mode', sensorModeActive);
            updateSensorUI();
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateSensorUI();
        });

        // ── Tab switcher & Split Layout System ──
        let currentSplitMode = 1;
        let currentActiveTab = 'calendar';

        // ── Split Layout Manager ──
        function setSplitLayout(mode) {
            currentSplitMode = mode;
            const container = document.getElementById('panelsContainer');
            const panelCal  = document.getElementById('panelCalendar');
            const panelMap  = document.getElementById('panelMap');
            const panelGal  = document.getElementById('panelGallery');
            const mapInner  = document.querySelector('.map-container-inner');

            // Update split buttons active state
            document.querySelectorAll('.split-layout-btn').forEach(btn => {
                const bMode = parseInt(btn.getAttribute('data-mode'));
                btn.classList.toggle('active', bMode === mode);
            });

            // Toggle split label visibility on container
            container.classList.toggle('split-mode-active', mode > 1);

            // Responsive guard: if viewport < 1100px, stay at 1 column
            const isWide = window.innerWidth >= 1100;

            if (mode === 3 && isWide) {
                container.style.gridTemplateColumns = '1fr 1fr 1fr';
                panelCal.style.display = 'flex';
                panelMap.style.display = 'flex';
                panelGal.style.display = 'flex';
                panelCal.style.height = '580px';
                panelMap.style.height = '580px';
                panelGal.style.height = '580px';
                if(mapInner) mapInner.style.height = '100%';
                loadGalleryVisits();
                setTimeout(() => {
                    if (!_leafletMap) initMeetingMap();
                    else _leafletMap.invalidateSize();
                }, 200);

            } else if (mode === 2 && isWide) {
                container.style.gridTemplateColumns = '1fr 1fr';
                panelCal.style.display = 'flex';
                panelMap.style.display = 'flex';
                panelGal.style.display = 'none';
                panelCal.style.height = '580px';
                panelMap.style.height = '580px';
                if(mapInner) mapInner.style.height = '100%';
                setTimeout(() => {
                    if (!_leafletMap) initMeetingMap();
                    else _leafletMap.invalidateSize();
                }, 200);

            } else {
                // Mode 1: single tab, reset
                currentSplitMode = 1;
                container.style.gridTemplateColumns = '1fr';
                panelCal.style.display = 'flex';
                panelMap.style.display = 'none';
                panelGal.style.display = 'none';
                panelCal.style.height = '580px';
                if(mapInner) mapInner.style.height = '100%';
                document.querySelectorAll('.split-layout-btn').forEach(btn => {
                    btn.classList.toggle('active', btn.getAttribute('data-mode') === '1');
                });
                switchPlannerTab(currentActiveTab);
            }
        }

        // ── Tab Switcher (mode 1 only) ──
        function switchPlannerTab(tab) {
            currentActiveTab = tab;
            if(currentSplitMode !== 1) {
                setSplitLayout(currentSplitMode);
                return;
            }

            const panelCal   = document.getElementById('panelCalendar');
            const panelMap   = document.getElementById('panelMap');
            const panelGal   = document.getElementById('panelGallery');
            const btnCal     = document.getElementById('tabBtnCalendar');
            const btnMap     = document.getElementById('tabBtnMap');
            const btnGal     = document.getElementById('tabBtnGallery');

            // Reset tab buttons
            [btnCal, btnMap, btnGal].forEach(b => { if(b) { b.style.background = 'transparent'; b.style.color = '#888'; } });
            // Hide all panels & set fixed equal height
            [panelCal, panelMap, panelGal].forEach(p => { if(p) { p.style.display = 'none'; p.style.height = '580px'; } });

            if (tab === 'map') {
                panelMap.style.display  = 'flex';
                btnMap.style.background = '#ffffff';
                btnMap.style.color      = '#000';
                setTimeout(() => {
                    if (!_leafletMap) initMeetingMap();
                    else _leafletMap.invalidateSize();
                }, 80);
            } else if (tab === 'gallery') {
                panelGal.style.display  = 'flex';
                btnGal.style.background = '#ffffff';
                btnGal.style.color      = '#000';
                loadGalleryVisits();
            } else {
                panelCal.style.display  = 'flex';
                btnCal.style.background = '#ffffff';
                btnCal.style.color      = '#000';
            }
        }

        // Re-apply layout on window resize to handle responsive breakpoints
        let _resizeDebounce;
        window.addEventListener('resize', () => {
            clearTimeout(_resizeDebounce);
            _resizeDebounce = setTimeout(() => setSplitLayout(currentSplitMode), 250);
        });

        async function loadGalleryVisits() {
            const grid = document.getElementById('galleryGrid');
            if(!grid) return;
            grid.innerHTML = '<div style="color:#888; font-size:0.85rem; padding:40px; text-align:center; grid-column:1/-1;"><i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i>Memuat Dokumentasi Meeting...</div>';
            
            const fd = new FormData();
            fd.append('ajax_action', 'get_map_meetings');
            fd.append('period', 'all');
            
            try {
                const meetings = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
                if(!meetings || meetings.length === 0) {
                    grid.innerHTML = '<div style="color:#888; font-size:0.85rem; padding:60px; text-align:center; grid-column:1/-1;"><i class="fas fa-images" style="font-size:2.5rem; display:block; margin-bottom:12px; opacity:0.3;"></i>Belum ada data dokumentasi meeting.</div>';
                    return;
                }
                
                let html = '';
                meetings.slice().reverse().forEach((m, idx) => {
                    const dateNice = new Date(m.event_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                    let photosArr = [];
                    try { photosArr = m.photos ? (typeof m.photos === 'string' ? JSON.parse(m.photos) : m.photos) : []; } catch(e){}
                    
                    let photoBanner = '';
                    if(photosArr && photosArr.length > 0) {
                        photoBanner = `
                            <div style="position:relative; width:100%; aspect-ratio:1/1; overflow:hidden; border-radius:12px 12px 0 0; background:#000; cursor:pointer;" onclick="openPhotoLightbox('${photosArr[0]}')">
                                <img src="${photosArr[0]}" style="width:100%; height:100%; object-fit:cover; transition:transform 0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                <div style="position:absolute; bottom:8px; right:8px; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); padding:3px 8px; border-radius:6px; font-size:0.68rem; color:#fff; display:flex; align-items:center; gap:4px;"><i class="fas fa-camera" style="color:#ffffff;"></i> ${photosArr.length} Foto</div>
                            </div>
                        `;
                    } else {
                        photoBanner = `
                            <div style="width:100%; aspect-ratio:1/1; background:rgba(255,255,255,0.03); border-radius:12px 12px 0 0; display:flex; align-items:center; justify-content:center; color:#888; font-size:0.8rem; font-style:italic; padding:10px; text-align:center;">
                                <div><i class="fas fa-map-marker-alt" style="margin-right:6px; color:#cccccc;"></i> <span class="sensor-blur">${escHtml(m.location || 'Dokumentasi Meeting')}</span></div>
                            </div>
                        `;
                    }
                    
                    let gmapsQuery = m.coords_raw ? encodeURIComponent(m.coords_raw.trim()) : (m.lat && m.lng ? `${m.lat},${m.lng}` : encodeURIComponent(m.location || ''));
                    let gmapsUrl = gmapsQuery ? `https://www.google.com/maps/search/?api=1&query=${gmapsQuery}` : '';

                    html += `
                        <div style="background:rgba(18,18,18,0.8); border:1px solid rgba(255,255,255,0.08); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; transition:transform 0.2s, border-color 0.2s;" onmouseover="this.style.borderColor='rgba(255,255,255,0.3)'" onmouseout="this.style.borderColor='rgba(255,255,255,0.08)'">
                            ${photoBanner}
                            <div style="padding:14px; flex:1; display:flex; flex-direction:column;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <span style="font-size:0.68rem; color:#ffffff; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">${escHtml(m.meeting_type || 'MEETING')}</span>
                                    <span style="font-size:0.7rem; color:#888;"><i class="far fa-calendar-alt" style="margin-right:4px;"></i>${dateNice}</span>
                                </div>
                                <div style="font-size:0.95rem; font-weight:800; color:#fff; margin-bottom:4px; line-height:1.3;"><span class="sensor-blur">${escHtml(m.title || m.target_name || 'Meeting')}</span></div>
                                ${m.target_name ? `<div style="font-size:0.75rem; color:#cccccc; font-weight:600; margin-bottom:6px;"><i class="fas fa-building" style="margin-right:4px;"></i><span class="sensor-blur">${escHtml(m.target_name)}</span></div>` : ''}
                                ${m.location ? `<div style="font-size:0.73rem; color:#aaa; margin-bottom:8px;"><i class="fas fa-map-marker-alt" style="color:#aaa; margin-right:4px;"></i><span class="sensor-blur">${escHtml(m.location)}</span></div>` : ''}
                                ${m.log_hasil ? `<div style="font-size:0.73rem; color:#888; background:rgba(255,255,255,0.02); padding:8px; border-radius:6px; border:1px solid rgba(255,255,255,0.04); margin-bottom:10px; line-height:1.3;">${escHtml(m.log_hasil)}</div>` : ''}
                                
                                <div style="margin-top:auto; display:flex; gap:6px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.06);">
                                    <button type="button" onclick="focusMeetingOnMap(${m.lat}, ${m.lng}, ${m.id})" style="flex:1; background:#ffffff; border:none; color:#000000; padding:6px 8px; border-radius:8px; font-size:0.72rem; font-weight:800; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; justify-content:center; gap:4px;"><i class="fas fa-map-marked-alt"></i> Peta</button>
                                    ${gmapsUrl ? `<a href="${gmapsUrl}" target="_blank" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#ffffff; padding:6px 10px; border-radius:8px; font-size:0.72rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:4px;"><i class="fas fa-directions"></i> GMaps</a>` : ''}
                                    <button type="button" onclick="openEditEvent(${m.id})" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#ccc; padding:6px 10px; border-radius:8px; font-size:0.72rem; cursor:pointer; font-family:inherit;"><i class="fas fa-edit"></i></button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                grid.innerHTML = html;
            } catch(e) {
                console.error(e);
                grid.innerHTML = '<div style="color:#888; font-size:0.85rem; padding:40px; text-align:center; grid-column:1/-1;">Gagal memuat dokumentasi meeting.</div>';
            }
        }

        function focusMeetingOnMap(lat, lng, eventId) {
            switchPlannerTab('map');
            setTimeout(() => {
                if(_leafletMap && !isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
                    _leafletMap.setView([lat, lng], 16);
                }
            }, 200);
        }

        async function loadMapMeetings(period) {
            const p = period || document.getElementById('mapFilterPeriod')?.value || 'month';
            if(!_leafletMap) { initMeetingMap(); return; }
            
            const fd = new FormData();
            fd.append('ajax_action', 'get_map_meetings');
            fd.append('period', p);
            
            try {
                const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
                renderMapMeetings(res);
            } catch(e) { console.error("Failed to load map meetings", e); }
        }

        async function renderMapMeetings(meetings) {
            if(!_mapMarkersLayer || !_mapPolylineLayer) return;
            _mapMarkersLayer.clearLayers();
            _mapPolylineLayer.clearLayers();

            if(!meetings || meetings.length === 0) return;

            const points = [];
            const bounds = L.latLngBounds();
            let displayedCount = 0;
            const usedCoords = {};

            for(let i = 0; i < meetings.length; i++) {
                const m = meetings[i];
                let lat = parseFloat(m.lat);
                let lng = parseFloat(m.lng);

                // Try coords_raw first (Plus Code, decimal text, etc)
                if((isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) && m.coords_raw) {
                    const coords = await geocodeAddress(m.coords_raw);
                    if(coords) { lat = coords.lat; lng = coords.lng; }
                    await new Promise(r => setTimeout(r, 150));
                }
                // Fallback to location name
                if((isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) && m.location) {
                    const coords = await geocodeAddress(m.location);
                    if(coords) { lat = coords.lat; lng = coords.lng; }
                    await new Promise(r => setTimeout(r, 200));
                }

                // Last fallback: offset around Surabaya center
                if((isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) && m.location) {
                    lat = -7.2575 + ((i % 5) * 0.008) - 0.015;
                    lng = 112.7521 + (Math.floor(i / 5) * 0.008) - 0.015;
                }

                if(!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
                    // Spiral offset for duplicate/overlapping coordinates so all markers remain visible
                    const _dKey = `${lat.toFixed(5)},${lng.toFixed(5)}`;
                    if(usedCoords[_dKey] !== undefined) {
                        const _n = usedCoords[_dKey];
                        const _angle = _n * 2.3999632; // golden angle (~137.5 deg)
                        const _r = 0.00018 * Math.sqrt(_n); // ~20m step spiral offset
                        lat = lat + Math.cos(_angle) * _r;
                        lng = lng + Math.sin(_angle) * _r;
                        usedCoords[_dKey]++;
                    } else {
                        usedCoords[_dKey] = 1;
                    }

                    displayedCount++;
                    const latLng = [lat, lng];
                    points.push(latLng);
                    bounds.extend(latLng);

                    const pinHtml = `<div class="map-marker-pin">${displayedCount}</div>`;
                    const customIcon = L.divIcon({
                        html: pinHtml,
                        className: '',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    });

                    const dateNice = new Date(m.event_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                    let gmapsQuery = '';
                    if(m.coords_raw && m.coords_raw.trim() !== '') {
                        gmapsQuery = encodeURIComponent(m.coords_raw.trim());
                    } else if(!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                        gmapsQuery = `${lat},${lng}`;
                    } else if(m.location) {
                        gmapsQuery = encodeURIComponent(m.location);
                    }
                    const gmapsUrl = gmapsQuery ? `https://www.google.com/maps/search/?api=1&query=${gmapsQuery}` : '';

                    let photosArr = [];
                    try { photosArr = m.photos ? (typeof m.photos === 'string' ? JSON.parse(m.photos) : m.photos) : []; } catch(e){}

                    let photoHtml = '';
                    if(photosArr && photosArr.length > 0) {
                        photoHtml = `
                            <div style="width:100px; height:90px; flex-shrink:0; border-radius:8px; overflow:hidden; border:1px solid rgba(255,255,255,0.12); cursor:pointer;" onclick="openPhotoLightbox('${photosArr[0]}')">
                                <img src="${photosArr[0]}" style="width:100%; height:100%; object-fit:cover; display:block;">
                            </div>
                        `;
                    }

                    const popupContent = `
                        <div style="padding: 2px; min-width: 280px; max-width: 330px;">
                            <div style="display:flex; gap:10px; align-items:flex-start;">
                                ${photoHtml}
                                <div style="flex:1; min-width:0;">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <span style="font-size: 0.62rem; color: #ffffff; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">KUNJUNGAN #${displayedCount}</span>
                                        ${photosArr.length > 1 ? `<span style="font-size:0.6rem; background:rgba(255,255,255,0.15); color:#ffffff; padding:1px 5px; border-radius:4px;"><i class="fas fa-camera"></i> ${photosArr.length}</span>` : ''}
                                    </div>
                                    <div style="font-size: 0.88rem; font-weight: 800; color: #fff; margin-top: 2px; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escHtml(m.title || m.target_name || 'Meeting')}"><span class="sensor-blur">${escHtml(m.title || m.target_name || 'Meeting')}</span></div>
                                    <div style="font-size: 0.72rem; color: #aaa; margin-top: 3px;"><i class="far fa-calendar-alt" style="margin-right:4px; color:#ccc;"></i>${dateNice} ${m.time_start ? '&bull; ' + m.time_start : ''}</div>
                                    <div style="font-size: 0.72rem; color: #ccc; margin-top: 3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><i class="fas fa-map-marker-alt" style="color:#aaa;margin-right:4px;"></i><span class="sensor-blur">${escHtml(m.location || 'Lokasi')}</span></div>
                                </div>
                            </div>
                            ${m.log_hasil ? `<div style="font-size: 0.7rem; color: #888; margin-top: 8px; padding-top: 6px; border-top: 1px solid rgba(255,255,255,0.08); line-height: 1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${escHtml(m.log_hasil)}</div>` : ''}
                            
                            <div style="display:flex; gap:6px; margin-top:8px; padding-top:6px; border-top:1px solid rgba(255,255,255,0.08);">
                                ${gmapsUrl ? `<a href="${gmapsUrl}" target="_blank" style="flex:1; display:inline-flex; align-items:center; justify-content:center; gap:5px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#ffffff; padding:5px 8px; border-radius:7px; font-size:0.7rem; font-weight:700; text-decoration:none;"><i class="fas fa-directions"></i> Google Maps</a>` : ''}
                                <button onclick="openEditEvent(${m.id})" style="display:inline-flex; align-items:center; justify-content:center; gap:5px; background:#ffffff; border:none; color:#000000; padding:5px 10px; border-radius:7px; font-size:0.7rem; font-weight:800; cursor:pointer; font-family:inherit;"><i class="fas fa-edit"></i> Edit</button>
                            </div>
                        </div>
                    `;

                    const marker = L.marker(latLng, { icon: customIcon }).bindPopup(popupContent).addTo(_mapMarkersLayer);
                    marker.on('mouseover', function() { this.openPopup(); });
                }
            }


            if(points.length > 1) {
                L.polyline(points, {
                    color: '#000000',
                    weight: 3.5,
                    opacity: 0.9,
                    dashArray: '6, 6'
                }).addTo(_mapPolylineLayer);
            }

            if(points.length > 0) {
                _leafletMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
            }
        }

        async function geocodeAddress(query) {
            if(!query) return null;
            let cleanQuery = query.trim();
            if(cleanQuery.startsWith('http://') || cleanQuery.startsWith('https://')) return null;

            // 1. Direct lat, lng coordinate string (e.g. "-7.2575, 112.7521")
            const coordMatch = cleanQuery.match(/^(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)$/);
            if(coordMatch) {
                return { lat: parseFloat(coordMatch[1]), lng: parseFloat(coordMatch[2]) };
            }

            // Helper fetch function to query Nominatim
            const tryFetch = async (q) => {
                if(!q || q.length < 3) return null;
                try {
                    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`;
                    const res = await fetch(url).then(r => r.json());
                    if(res && res.length > 0) {
                        return { lat: parseFloat(res[0].lat), lng: parseFloat(res[0].lon) };
                    }
                } catch(e) {}
                return null;
            };

            // 2. Direct try full query
            let res = await tryFetch(cleanQuery);
            if(res) return res;

            // 3. Strip Plus Code prefix (e.g. "PPVV+PP Ketabang, Surabaya" -> "Ketabang, Surabaya")
            let stripped = cleanQuery.replace(/^[A-Z0-9]{4,8}\+[A-Z0-9]{2,4}\s*,?\s*/i, '').trim();
            if(stripped && stripped !== cleanQuery) {
                res = await tryFetch(stripped);
                if(res) return res;
            }

            const targetText = stripped || cleanQuery;

            // 4. Strip business/cafe/brand prefix words (e.g. "tomorrow coffe graha pena surabaya" -> "graha pena surabaya")
            let strippedVenue = targetText.replace(/^(tomorrow\s+coffe[e]?|coffee|coffe|cafe|café|kopi|warung|resto|restaurant|toko|shop|outlet|office|kantor|gedung|pt|ud|cv)\s+/i, '').trim();
            if(strippedVenue && strippedVenue !== targetText) {
                res = await tryFetch(strippedVenue);
                if(res) return res;
            }

            // 5. Try progressive word trimming from start (e.g. "tomorrow coffe graha pena surabaya" -> "graha pena surabaya" -> "pena surabaya" -> "surabaya")
            const words = targetText.split(/\s+/).filter(Boolean);
            if(words.length > 2) {
                // Try last 3 words
                res = await tryFetch(words.slice(-3).join(' '));
                if(res) return res;
                // Try last 2 words
                res = await tryFetch(words.slice(-2).join(' '));
                if(res) return res;
                // Try last word (e.g. city)
                res = await tryFetch(words[words.length - 1]);
                if(res) return res;
            } else if(words.length === 2) {
                res = await tryFetch(words[1]);
                if(res) return res;
            }

            // 6. Comma fallback if present
            if(targetText.includes(',')) {
                const parts = targetText.split(',').map(s=>s.trim()).filter(Boolean);
                if(parts.length > 1) {
                    res = await tryFetch(parts.slice(1).join(', '));
                    if(res) return res;
                }
            }

            return null;
        }

        function saveCoordsToDB(eventId, lat, lng) {
            const fd = new FormData();
            fd.append('ajax_action', 'update_meeting_coords');
            fd.append('event_id', eventId);
            fd.append('lat', lat);
            fd.append('lng', lng);
            fetch('', { method: 'POST', body: fd });
        }

        async function dashGeocodeLocation() {
            const locInp = document.getElementById('d-loc-input');
            const msg = document.getElementById('geoStatusMsg');
            const latInp = document.getElementById('d-lat-input');
            const lngInp = document.getElementById('d-lng-input');
            if(!locInp || !locInp.value.trim()) { if(msg) msg.style.display='none'; return; }
            
            const coords = await geocodeAddress(locInp.value.trim());
            if(coords) {
                if(latInp) latInp.value = coords.lat;
                if(lngInp) lngInp.value = coords.lng;
                if(msg) {
                    msg.style.display = 'inline';
                    msg.innerHTML = '<i class="fas fa-check-circle"></i> Koordinat terdeteksi (' + coords.lat.toFixed(4) + ', ' + coords.lng.toFixed(4) + ')';
                }
            } else {
                if(msg) msg.style.display = 'none';
            }
        }

        // Shortcut Menu Toggle
        function toggleShortcutMenu() {
            const dd = document.getElementById('shortcutDropdown');
            if(dd) dd.classList.toggle('active');
        }
        document.addEventListener('click', function(e) {
            const wrap = document.getElementById('shortcutMenuWrap');
            const dd = document.getElementById('shortcutDropdown');
            if(wrap && dd && !wrap.contains(e.target)) dd.classList.remove('active');
        });

        // Photo Lightbox
        function openPhotoLightbox(src) {
            const modal = document.getElementById('imageLightboxModal');
            const img = document.getElementById('lightboxImage');
            if(modal && img) {
                img.src = src;
                modal.style.display = 'flex';
            }
        }
        function closePhotoLightbox() {
            const modal = document.getElementById('imageLightboxModal');
            if(modal) modal.style.display = 'none';
        }
    </script>

    <!-- Photo Lightbox Modal -->
    <div id="imageLightboxModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.92); backdrop-filter:blur(10px); align-items:center; justify-content:center; padding:20px;" onclick="closePhotoLightbox()">
        <img id="lightboxImage" src="" style="max-width:92vw; max-height:88vh; border-radius:12px; border:1px solid rgba(255,255,255,0.2); box-shadow:0 25px 60px rgba(0,0,0,0.8); object-fit:contain;">
        <button type="button" style="position:absolute; top:20px; right:25px; background:rgba(255,255,255,0.12); border:none; color:#fff; border-radius:50%; width:42px; height:42px; font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center;" onclick="closePhotoLightbox()">&times;</button>
    </div>
</body>
</html>
