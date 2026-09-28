<?php
// 1. INIT
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
if(!isset($_SESSION['admin'])){ echo "<script>window.location='/index.php';</script>"; exit; }
$allowed  = (isset($_SESSION['role']) && ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin'));

// 2. AUTO-FIX DATABASE
function fixCol($conn, $t, $c, $d){
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    if(mysqli_num_rows($q) == 0) mysqli_query($conn, "ALTER TABLE `$t` ADD COLUMN `$c` $d");
}

// Create table if not exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `prospects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL,
    `pic` VARCHAR(255) DEFAULT NULL,
    `jabatan` VARCHAR(255) DEFAULT NULL,
    `wa` VARCHAR(50) DEFAULT NULL,
    `alamat` TEXT DEFAULT NULL,
    `domain` VARCHAR(255) DEFAULT NULL,
    `link_deck` TEXT DEFAULT NULL,
    `catatan` TEXT DEFAULT NULL,
    `tier` ENUM('UMKM','Perusahaan Menengah','Korporasi','Instansi / Pemerintah') DEFAULT 'UMKM',
    `status` ENUM('Prospecting','Follow Up','Negotiation','Deal','Lost') DEFAULT 'Prospecting',
    `is_synced` TINYINT(1) DEFAULT 0,
    `deal_status` ENUM('Deal','Gak Deal','Ghosting') DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Migrate old tier values
$q_tier_chk = mysqli_query($conn, "SHOW COLUMNS FROM prospects LIKE 'tier'");
$r_tier_chk = mysqli_fetch_assoc($q_tier_chk);
if($r_tier_chk && strpos($r_tier_chk['Type'], 'UMKM') === false) {
    mysqli_query($conn, "ALTER TABLE prospects MODIFY COLUMN tier ENUM('B2B Kecil','B2B Menengah','B2B Besar','UMKM','Perusahaan Menengah','Korporasi','Instansi / Pemerintah') DEFAULT 'UMKM'");
    mysqli_query($conn, "UPDATE prospects SET tier='UMKM' WHERE tier='B2B Kecil' OR tier IS NULL");
    mysqli_query($conn, "UPDATE prospects SET tier='Perusahaan Menengah' WHERE tier='B2B Menengah'");
    mysqli_query($conn, "UPDATE prospects SET tier='Korporasi' WHERE tier='B2B Besar'");
    mysqli_query($conn, "ALTER TABLE prospects MODIFY COLUMN tier ENUM('UMKM','Perusahaan Menengah','Korporasi','Instansi / Pemerintah') DEFAULT 'UMKM'");
} else {
    fixCol($conn, 'prospects', 'tier', "ENUM('UMKM','Perusahaan Menengah','Korporasi','Instansi / Pemerintah') DEFAULT 'UMKM' AFTER catatan");
}
fixCol($conn, 'prospects', 'is_synced', "TINYINT(1) DEFAULT 0 AFTER status");

// Auto-migrate old statuses
$q_chk = mysqli_query($conn, "SHOW COLUMNS FROM prospects LIKE 'status'");
$r_chk = mysqli_fetch_assoc($q_chk);
if (strpos($r_chk['Type'], 'Cold') !== false) {
    mysqli_query($conn, "ALTER TABLE prospects MODIFY COLUMN status ENUM('Cold','Warm','Hot','Closed','Prospecting','Follow Up','Negotiation','Deal','Lost') DEFAULT 'Prospecting'");
    mysqli_query($conn, "UPDATE prospects SET status='Prospecting' WHERE status='Cold'");
    mysqli_query($conn, "UPDATE prospects SET status='Follow Up' WHERE status='Warm'");
    mysqli_query($conn, "UPDATE prospects SET status='Negotiation' WHERE status='Hot'");
    mysqli_query($conn, "UPDATE prospects SET status='Deal' WHERE status='Closed'");
    mysqli_query($conn, "ALTER TABLE prospects MODIFY COLUMN status ENUM('Prospecting','Follow Up','Negotiation','Deal','Lost') DEFAULT 'Prospecting'");
}

fixCol($conn, 'prospects', 'deal_status', "ENUM('Deal','Gak Deal','Ghosting') DEFAULT NULL AFTER `status`");

// Ensure events target_id is VARCHAR so it can store '0012' client_ids properly
$q_ev = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE 'target_id'");
if($q_ev && mysqli_num_rows($q_ev) > 0) {
    mysqli_query($conn, "ALTER TABLE `events` MODIFY COLUMN `target_id` VARCHAR(50) DEFAULT NULL");
}

// 3. AJAX ACTIONS
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];

    if($act === 'get') {
        $id = intval($_POST['id'] ?? 0);
        $q = mysqli_query($conn, "SELECT * FROM prospects WHERE id=$id");
        $row = mysqli_fetch_assoc($q);
        if($row) {
            // Get meetings
            $row['meetings'] = [];
            $q_m = mysqli_query($conn, "SELECT * FROM events WHERE target_id='$id' AND target_type='Prospect' ORDER BY event_date DESC");
            if($q_m) while($m = mysqli_fetch_assoc($q_m)) $row['meetings'][] = $m;

            // Get invoices
            $row['invoices_hist'] = [];
            $q_i = mysqli_query($conn, "SELECT * FROM invoices WHERE client_ref_id='$id' AND client_ref_type='Prospect' ORDER BY inv_date DESC");
            if($q_i) while($inv = mysqli_fetch_assoc($q_i)) $row['invoices_hist'][] = $inv;

            echo json_encode($row);
        } else {
            echo json_encode(null);
        }
        exit;
    }

    if($act === 'save') {
        $id          = intval($_POST['id'] ?? 0);
        $company     = mysqli_real_escape_string($conn, trim($_POST['company_name'] ?? ''));
        $pic         = mysqli_real_escape_string($conn, trim($_POST['pic'] ?? ''));
        $jabatan     = mysqli_real_escape_string($conn, trim($_POST['jabatan'] ?? ''));
        $wa          = mysqli_real_escape_string($conn, trim($_POST['wa'] ?? ''));
        $alamat      = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));
        $domain      = mysqli_real_escape_string($conn, trim($_POST['domain'] ?? ''));
        $link_deck   = mysqli_real_escape_string($conn, trim($_POST['link_deck'] ?? ''));
        $catatan     = mysqli_real_escape_string($conn, trim($_POST['catatan'] ?? ''));
        $status      = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Prospecting');
        $tier        = mysqli_real_escape_string($conn, $_POST['tier'] ?? 'UMKM');
        $deal_raw    = isset($_POST['deal_status']) ? trim($_POST['deal_status']) : '';

        // Auto-synchronize status and deal_status to prevent counting "Gak Deal" as "Deal"
        if($deal_raw === 'Gak Deal' || $deal_raw === 'Ghosting') {
            $status = 'Lost';
        } elseif($deal_raw === 'Deal') {
            $status = 'Deal';
        } elseif($status === 'Lost' && empty($deal_raw)) {
            $deal_raw = 'Gak Deal';
        } elseif($status === 'Deal' && empty($deal_raw)) {
            $deal_raw = 'Deal';
        }

        $deal_status = !empty($deal_raw) ? "'".mysqli_real_escape_string($conn, $deal_raw)."'" : "NULL";

        if(empty($company)) { echo json_encode(['ok'=>false,'msg'=>'Nama perusahaan wajib diisi.']); exit; }

        $new_id = 0;
        if($id > 0) {
            mysqli_query($conn, "UPDATE prospects SET company_name='$company', pic='$pic', jabatan='$jabatan', wa='$wa', alamat='$alamat', domain='$domain', link_deck='$link_deck', catatan='$catatan', status='$status', tier='$tier', deal_status=$deal_status WHERE id=$id");
            $new_id = $id;
        } else {
            mysqli_query($conn, "INSERT INTO prospects (company_name, pic, jabatan, wa, alamat, domain, link_deck, catatan, status, tier, deal_status) VALUES ('$company','$pic','$jabatan','$wa','$alamat','$domain','$link_deck','$catatan','$status','$tier',$deal_status)");
            $new_id = mysqli_insert_id($conn);
        }

        // Auto-sync to clients if status is Deal and not yet synced
        $client_id_new = null;
        if($status === 'Deal') {
            $q_syn = mysqli_query($conn, "SELECT is_synced FROM prospects WHERE id=$new_id");
            $r_syn = mysqli_fetch_assoc($q_syn);
            if($r_syn && !$r_syn['is_synced']) {
                $q_id = mysqli_query($conn,"SELECT MAX(CAST(client_id AS UNSIGNED)) as max_id FROM clients WHERE client_id NOT LIKE 'cli_%'");
                $r_id = mysqli_fetch_assoc($q_id);
                $client_id_new = str_pad((int)($r_id['max_id'] ?? 0) + 1, 4, "0", STR_PAD_LEFT);
                
                $notes_esc = mysqli_real_escape_string($conn, $_POST['catatan'] ?? '');
                $dom_esc   = mysqli_real_escape_string($conn, $_POST['domain'] ?? '');
                mysqli_query($conn, "INSERT INTO clients (client_id, company_name, pic_name, pic_position, whatsapp, address, notes, link_other, status, services_data, credentials_data) VALUES ('$client_id_new', '$company', '$pic', '$jabatan', '$wa', '$alamat', '$notes_esc', '$dom_esc', 'Active', '[]', '[]')");
                mysqli_query($conn, "UPDATE prospects SET is_synced=1 WHERE id=$new_id");
                mysqli_query($conn, "UPDATE events SET target_id='$client_id_new', target_type='Client', target_name='$company' WHERE target_id='$new_id' AND target_type='Prospect'");
                mysqli_query($conn, "UPDATE invoices SET client_ref_id='$client_id_new', client_ref_type='Client', client_name='$company' WHERE client_ref_id='$new_id' AND client_ref_type='Prospect'");
            }
        }

        echo json_encode(['ok'=>true, 'auto_synced'=> ($client_id_new ? true : false), 'client_id'=> $client_id_new]);
        exit;
    }

    if($act === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        mysqli_query($conn, "DELETE FROM prospects WHERE id=$id");
        echo json_encode(['ok'=>true]);
        exit;
    }

    if($act === 'sync_client') {
        $id = intval($_POST['id'] ?? 0);
        $q = mysqli_query($conn, "SELECT * FROM prospects WHERE id=$id AND is_synced=0");
        $row = mysqli_fetch_assoc($q);
        if($row) {
            $q_id = mysqli_query($conn,"SELECT MAX(CAST(client_id AS UNSIGNED)) as max_id FROM clients WHERE client_id NOT LIKE 'cli_%'");
            $r_id = mysqli_fetch_assoc($q_id);
            $client_id = str_pad((int)($r_id['max_id'] ?? 0) + 1, 4, "0", STR_PAD_LEFT);
            
            $c_company = mysqli_real_escape_string($conn, $row['company_name']);
            $c_pic     = mysqli_real_escape_string($conn, $row['pic'] ?? '');
            $c_jab     = mysqli_real_escape_string($conn, $row['jabatan'] ?? '');
            $c_wa      = mysqli_real_escape_string($conn, $row['wa'] ?? '');
            $c_alamat  = mysqli_real_escape_string($conn, $row['alamat'] ?? '');
            $c_notes   = mysqli_real_escape_string($conn, $row['catatan'] ?? '');
            $c_dom     = mysqli_real_escape_string($conn, $row['domain'] ?? '');
            
            mysqli_query($conn, "INSERT INTO clients (client_id, company_name, pic_name, pic_position, whatsapp, address, notes, link_other, status, services_data, credentials_data) VALUES ('$client_id', '$c_company', '$c_pic', '$c_jab', '$c_wa', '$c_alamat', '$c_notes', '$c_dom', 'Active', '[]', '[]')");
            mysqli_query($conn, "UPDATE prospects SET is_synced=1, status='Deal', deal_status='Deal' WHERE id=$id");
            mysqli_query($conn, "UPDATE events SET target_id='$client_id', target_type='Client', target_name='$c_company' WHERE target_id='$id' AND target_type='Prospect'");
            mysqli_query($conn, "UPDATE invoices SET client_ref_id='$client_id', client_ref_type='Client', client_name='$c_company' WHERE client_ref_id='$id' AND client_ref_type='Prospect'");

            echo json_encode(['ok'=>true, 'client_id'=>$client_id]);
        } else {
            echo json_encode(['ok'=>false, 'msg'=>'Prospek tidak ditemukan atau sudah disinkronkan.']);
        }
        exit;
    }
}

// 4. LOAD DATA
$search = trim($_GET['s'] ?? '');
$status_filter = trim($_GET['st'] ?? '');
$period_filter = trim($_GET['period'] ?? '');
$start_date    = trim($_GET['start_date'] ?? '');
$end_date      = trim($_GET['end_date'] ?? '');

$where = "1=1";
if($search) $where .= " AND (company_name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR pic LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR domain LIKE '%".mysqli_real_escape_string($conn,$search)."%')";
if($status_filter) $where .= " AND status='".mysqli_real_escape_string($conn,$status_filter)."'";

// Period filter
$period_days = 0;
if($period_filter === '7d') $period_days = 7;
elseif($period_filter === '30d') $period_days = 30;
elseif($period_filter === '1y' || $period_filter === '365d') $period_days = 365;

if($period_days > 0) {
    $where .= " AND updated_at >= DATE_SUB(NOW(), INTERVAL $period_days DAY)";
} elseif($period_filter === 'custom' && !empty($start_date) && !empty($end_date)) {
    $s = mysqli_real_escape_string($conn, $start_date . ' 00:00:00');
    $e = mysqli_real_escape_string($conn, $end_date . ' 23:59:59');
    $where .= " AND updated_at BETWEEN '$s' AND '$e'";
}

$q = mysqli_query($conn, "SELECT * FROM prospects WHERE $where ORDER BY FIELD(status,'Negotiation','Follow Up','Prospecting','Deal','Lost'), updated_at DESC");
$prospects = [];
while($row = mysqli_fetch_assoc($q)) $prospects[] = $row;

// Fetch last visit date from events per prospect
$last_visit_map = [];
$q_lv = mysqli_query($conn, "SELECT target_id, MAX(event_date) as last_date FROM events WHERE target_type='Prospect' AND target_id IS NOT NULL GROUP BY target_id");
if($q_lv) while($lv = mysqli_fetch_assoc($q_lv)) $last_visit_map[$lv['target_id']] = $lv['last_date'];

$counts = ['all'=>0,'Prospecting'=>0,'Follow Up'=>0,'Negotiation'=>0,'Deal'=>0,'Lost'=>0];
$tier_counts = ['UMKM'=>0,'Perusahaan Menengah'=>0,'Korporasi'=>0,'Instansi / Pemerintah'=>0];
$q_c = mysqli_query($conn, "SELECT status, COUNT(*) as c FROM prospects GROUP BY status");
while($r = mysqli_fetch_assoc($q_c)) { $counts[$r['status']] = ($counts[$r['status']] ?? 0) + $r['c']; $counts['all'] += $r['c']; }
$q_t = mysqli_query($conn, "SELECT tier, COUNT(*) as c FROM prospects GROUP BY tier");
while($r = mysqli_fetch_assoc($q_t)) { $tier_counts[$r['tier']] = $r['c']; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Prospects - HVM Digital</title>

<!-- FAVICON -->
<link rel="icon" type="image/png" href="/uploads/icon.png">
<link rel="shortcut icon" href="/uploads/icon.png">
<link rel="apple-touch-icon" href="/uploads/icon.png">

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --bg:      #050505;
    --card:    rgba(18,18,18,0.8);
    --border:  rgba(255,255,255,0.08);
    --green:   #a1ff5a;
    --teal:    #4efdc4;
    --muted:   #888;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif; }
body { background:var(--bg); color:#fff; min-height:100vh; }

/* ambient */
.ambient-glow { position:fixed; border-radius:50%; filter:blur(150px); opacity:0.05; z-index:-1; pointer-events:none; }
.glow-1 { top:-100px; left:40px; width:500px; height:500px; background:var(--green); }
.glow-2 { bottom:-100px; right:40px; width:400px; height:400px; background:var(--teal); }

.main-content { padding:32px 40px; margin-left:260px; width:calc(100% - 260px); }
@media (max-width:768px) { .main-content { margin-left:0; width:100%; padding:20px 16px 110px; } }

/* Page header */
.page-top { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
.page-top h1 { font-size:1.8rem; font-weight:800; color:#fff; }
.page-top p { color:var(--muted); font-size:0.88rem; margin-top:4px; }
.btn-add { background:linear-gradient(135deg,var(--green),var(--teal)); border:none; color:#000; border-radius:10px; padding:11px 22px; font-family:inherit; font-size:0.88rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:8px; transition:transform 0.2s,box-shadow 0.2s; }
.btn-add:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(161,255,90,0.25); }

/* Stat cards */
.stat-row { display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap; }
.stat-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:16px 22px; min-width:120px; cursor:pointer; transition:border-color 0.2s,background 0.2s; }
.stat-card:hover, .stat-card.active { border-color:rgba(161,255,90,0.35); background:rgba(161,255,90,0.06); }
.stat-card .val { font-size:1.6rem; font-weight:800; color:var(--green); }
.stat-card .lbl { font-size:0.7rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }

/* Search & filter */
.toolbar { display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.search-wrap { position:relative; flex:1; min-width:200px; }
.search-wrap i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:0.85rem; }
.search-input { width:100%; background:var(--card); border:1px solid var(--border); color:#fff; border-radius:10px; padding:10px 14px 10px 38px; font-family:inherit; font-size:0.85rem; outline:none; transition:border-color 0.2s; }
.search-input:focus { border-color:rgba(161,255,90,0.4); }
.search-input::placeholder { color:var(--muted); }
.period-chips { display:flex; gap:8px; flex-wrap:wrap; }
.period-chip { padding:7px 14px; border-radius:20px; border:1px solid var(--border); background:var(--card); color:var(--muted); font-size:0.72rem; font-weight:700; cursor:pointer; transition:all 0.2s; letter-spacing:0.3px; }
.period-chip:hover { border-color:rgba(161,255,90,0.3); color:#fff; }
.period-chip.active { background:rgba(161,255,90,0.12); border-color:rgba(161,255,90,0.35); color:var(--green); }

/* Table */
.table-wrap { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; overflow-x:auto; }
.ptable { width:100%; border-collapse:collapse; min-width:700px; }
.ptable thead tr { background:rgba(255,255,255,0.025); border-bottom:1px solid var(--border); }
.ptable th { padding:11px 16px; font-size:0.67rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--muted); text-align:left; white-space:nowrap; }
.ptable tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.15s; }
.ptable tbody tr:last-child { border-bottom:none; }
.ptable tbody tr:hover { background:rgba(255,255,255,0.03); }
.ptable td { padding:12px 16px; font-size:0.82rem; vertical-align:middle; }
.company-name { font-weight:700; color:#fff; }
.company-sub  { font-size:0.72rem; color:var(--muted); margin-top:2px; }
.last-visit-tag { font-size:0.72rem; color:#888; }
.last-visit-tag.recent { color:var(--teal); }
.last-visit-tag.none { color:#555; font-style:italic; }

.badge { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:20px; font-size:0.68rem; font-weight:700; letter-spacing:0.3px; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:#ccc; }
.b-deal { background:rgba(161,255,90,0.12); color:var(--green); border-color:rgba(161,255,90,0.3); }
.b-lost { background:rgba(255,255,255,0.04); color:#777; border-color:rgba(255,255,255,0.08); }

.act-btns { display:flex; gap:6px; }
.act-btn { width:30px; height:30px; border-radius:8px; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.78rem; transition:all 0.2s; background:rgba(255,255,255,0.05); color:#aaa; }
.act-btn:hover { background:rgba(161,255,90,0.12); color:var(--green); transform:scale(1.1); }
.act-btn.del:hover { background:rgba(255,255,255,0.1); color:#ff6b6b; }
.act-btn.wa-btn { color:var(--teal); }
.act-btn.wa-btn:hover { background:rgba(78,253,196,0.12); color:#fff; }

.empty-state { text-align:center; padding:60px; color:var(--muted); }
.empty-state i { font-size:3rem; margin-bottom:12px; opacity:0.3; display:block; }

/* Scrollbar */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

/* Modal */
.modal-overlay { position:fixed; inset:0; z-index:2000; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); display:none; align-items:center; justify-content:center; padding:20px; }
.modal-overlay.open { display:flex; }
.modal-box { background:#0d0d0d; border:1px solid rgba(255,255,255,0.1); border-radius:20px; width:100%; max-width:620px; max-height:92vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 40px 80px rgba(0,0,0,0.7); }
.modal-head { display:flex; justify-content:space-between; align-items:center; padding:20px 24px 0 24px; }
.modal-head h3 { font-size:1.1rem; font-weight:700; margin-bottom:15px; }
.btn-close { background:rgba(255,255,255,0.06); border:none; color:#888; border-radius:8px; width:32px; height:32px; cursor:pointer; font-size:1.1rem; transition:all 0.2s; display:flex; align-items:center; justify-content:center; margin-bottom:15px; }
.btn-close:hover { background:rgba(255,255,255,0.12); color:#fff; }
.modal-body { overflow-y:auto; padding:0; flex:1; }

.m-tabs { display:flex; gap:20px; border-bottom:1px solid var(--border); padding:0 24px; margin-bottom:20px; }
.m-tab { background:none; border:none; color:var(--muted); padding:10px 0; font-size:0.85rem; font-weight:700; cursor:pointer; border-bottom:2px solid transparent; transition:all 0.2s; }
.m-tab:hover { color:#fff; }
.m-tab.active { color:var(--green); border-bottom-color:var(--green); }

.m-tab-content { display:none; padding:0 24px 20px; }
.m-tab-content.active { display:block; }

.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-grp { margin-bottom:14px; }
.form-grp label { display:block; font-size:0.7rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
.form-grp input, .form-grp textarea, .form-grp select {
    width:100%; background:rgba(255,255,255,0.04); border:1px solid var(--border); color:#fff;
    border-radius:10px; padding:10px 14px; font-family:inherit; font-size:0.85rem; outline:none;
    transition:border-color 0.2s; resize:none;
}
.form-grp input:focus, .form-grp textarea:focus, .form-grp select:focus { border-color:rgba(161,255,90,0.35); }
.form-grp select option { background:#111; }

.modal-foot { padding:16px 24px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }
.btn-cancel { background:rgba(255,255,255,0.05); border:1px solid var(--border); color:#888; border-radius:10px; padding:9px 20px; font-family:inherit; font-size:0.85rem; cursor:pointer; transition:all 0.2s; }
.btn-cancel:hover { color:#fff; background:rgba(255,255,255,0.08); }
.btn-save { background:linear-gradient(135deg,var(--green),var(--teal)); border:none; color:#000; border-radius:10px; padding:9px 22px; font-family:inherit; font-size:0.85rem; font-weight:700; cursor:pointer; transition:all 0.2s; }
.btn-save:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(161,255,90,0.2); }

/* Toast */
.toast { position:fixed; bottom:24px; right:24px; background:#111; border:1px solid var(--green); color:var(--green); padding:12px 20px; border-radius:10px; font-size:0.85rem; font-weight:600; z-index:9999; display:none; animation:fadeIn 0.3s; }
@keyframes fadeIn { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;} }
</style>
</head>
<body>

<?php include '../sidebar.php'; ?>

<div class="ambient-glow glow-1"></div>
<div class="ambient-glow glow-2"></div>
<div class="main-content">

    <div class="page-top">
        <div>
            <h1><i class="fas fa-binoculars" style="color:var(--green);margin-right:10px;font-size:1.4rem;vertical-align:middle;"></i>Prospects</h1>
            <p>Kelola data calon klien dan pantau status pipeline Anda</p>
        </div>
        <button class="btn-add" onclick="openModal()">
            <i class="fas fa-plus"></i> Tambah Prospek
        </button>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-row">
        <div class="stat-card <?= !$status_filter?'active':'' ?>" onclick="filterStatus('')">
            <div class="val"><?= $counts['all'] ?></div>
            <div class="lbl">Semua</div>
        </div>
        <div class="stat-card <?= $status_filter=='Negotiation'?'active':'' ?>" onclick="filterStatus('Negotiation')">
            <div class="val"><?= $counts['Negotiation'] ?? 0 ?></div>
            <div class="lbl"><i class="fas fa-handshake"></i> Negosiasi</div>
        </div>
        <div class="stat-card <?= $status_filter=='Follow Up'?'active':'' ?>" onclick="filterStatus('Follow Up')">
            <div class="val"><?= $counts['Follow Up'] ?? 0 ?></div>
            <div class="lbl"><i class="fas fa-phone-alt"></i> Follow Up</div>
        </div>
        <div class="stat-card <?= $status_filter=='Prospecting'?'active':'' ?>" onclick="filterStatus('Prospecting')">
            <div class="val"><?= $counts['Prospecting'] ?? 0 ?></div>
            <div class="lbl"><i class="fas fa-crosshairs"></i> Prospecting</div>
        </div>
        <div class="stat-card <?= $status_filter=='Deal'?'active':'' ?>" onclick="filterStatus('Deal')">
            <div class="val"><?= $counts['Deal'] ?? 0 ?></div>
            <div class="lbl"><i class="fas fa-check-circle"></i> Deal</div>
        </div>
        <div class="stat-card <?= $status_filter=='Lost'?'active':'' ?>" onclick="filterStatus('Lost')">
            <div class="val" style="color:#aaa;"><?= $counts['Lost'] ?? 0 ?></div>
            <div class="lbl"><i class="fas fa-times-circle"></i> Lost</div>
        </div>
    </div>

    <!-- TIER CHIPS -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;align-items:center;">
        <span style="font-size:0.72rem;color:var(--muted);font-weight:700;">SKALA USAHA:</span>
        <span class="badge"><i class="fas fa-store" style="margin-right:4px;color:var(--teal);"></i>UMKM: <?= $tier_counts['UMKM'] ?></span>
        <span class="badge"><i class="fas fa-building" style="margin-right:4px;color:var(--teal);"></i>Menengah: <?= $tier_counts['Perusahaan Menengah'] ?></span>
        <span class="badge"><i class="fas fa-city" style="margin-right:4px;color:var(--green);"></i>Korporasi: <?= $tier_counts['Korporasi'] ?></span>
        <span class="badge"><i class="fas fa-landmark" style="margin-right:4px;color:var(--green);"></i>Instansi: <?= $tier_counts['Instansi / Pemerintah'] ?></span>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Cari nama perusahaan, PIC, domain..." value="<?= htmlspecialchars($search) ?>" oninput="debounceSearch(this.value)">
        </div>
        <div class="period-chips">
            <div class="period-chip <?= !$period_filter?'active':'' ?>" onclick="filterPeriod('')">Semua</div>
            <div class="period-chip <?= $period_filter==='7d'?'active':'' ?>" onclick="filterPeriod('7d')">7 Hari</div>
            <div class="period-chip <?= $period_filter==='30d'?'active':'' ?>" onclick="filterPeriod('30d')">30 Hari</div>
            <div class="period-chip <?= $period_filter==='1y'?'active':'' ?>" onclick="filterPeriod('1y')">1 Tahun</div>
            <div class="period-chip <?= $period_filter==='custom'?'active':'' ?>" onclick="toggleCustomDate()">Custom</div>
        </div>
    </div>

    <!-- CUSTOM DATE RANGE INPUTS -->
    <div id="customDateWrap" style="display:<?= $period_filter==='custom'?'flex':'none' ?>; align-items:center; gap:8px; margin-bottom:16px; flex-wrap:wrap; background:rgba(255,255,255,0.02); padding:10px 14px; border-radius:10px; border:1px solid var(--border);">
        <span style="font-size:0.75rem; color:var(--muted); font-weight:600;"><i class="fas fa-calendar-alt" style="margin-right:6px; color:var(--green);"></i>Periode Custom:</span>
        <input type="date" id="startDateInput" value="<?= htmlspecialchars($start_date) ?>" style="background:rgba(0,0,0,0.5); border:1px solid var(--border); color:#fff; padding:6px 12px; border-radius:8px; font-size:0.78rem; outline:none;">
        <span style="font-size:0.75rem; color:var(--muted);">s/d</span>
        <input type="date" id="endDateInput" value="<?= htmlspecialchars($end_date) ?>" style="background:rgba(0,0,0,0.5); border:1px solid var(--border); color:#fff; padding:6px 12px; border-radius:8px; font-size:0.78rem; outline:none;">
        <button type="button" onclick="applyCustomDate()" style="background:linear-gradient(135deg,var(--green),var(--teal)); color:#000; border:none; padding:6px 14px; border-radius:8px; font-size:0.78rem; font-weight:700; cursor:pointer;">Filter</button>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
        <table class="ptable">
            <thead>
                <tr>
                    <th>Perusahaan / PIC</th>
                    <th>Kontak</th>
                    <th>Domain</th>
                    <th>Terakhir Visit</th>
                    <th>Tier / Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php if(empty($prospects)): ?>
                <tr><td colspan="6" class="empty-state">
                    <i class="fas fa-binoculars"></i>
                    <div>Belum ada data prospek.</div>
                    <div style="font-size:0.8rem;margin-top:6px;">Klik "Tambah Prospek" untuk mulai menambahkan.</div>
                </td></tr>
            <?php else: foreach($prospects as $p):
                $badgeClass = ($p['status']==='Deal') ? 'b-deal' : (($p['status']==='Lost') ? 'b-lost' : '');
                $waLink = $p['wa'] ? 'https://wa.me/'.preg_replace('/[^0-9]/','',$p['wa']) : '#';
                $lv_date = $last_visit_map[$p['id']] ?? null;
                $lv_class = 'none';
                $lv_text = 'Belum ada visit';
                if($lv_date) {
                    $lv_diff = (new DateTime())->diff(new DateTime($lv_date));
                    if($lv_diff->days == 0) $lv_text = 'Hari ini';
                    elseif($lv_diff->days == 1) $lv_text = 'Kemarin';
                    else $lv_text = $lv_diff->days . ' hari lalu';
                    $lv_class = ($lv_diff->days <= 7) ? 'recent' : '';
                }
            ?>
                <tr>
                    <td>
                        <div class="company-name"><?= htmlspecialchars($p['company_name']) ?></div>
                        <div class="company-sub"><?= htmlspecialchars($p['pic'] ?? '') ?><?= $p['jabatan'] ? ' ('.htmlspecialchars($p['jabatan']).')' : '' ?></div>
                    </td>
                    <td>
                        <?php if($p['wa']): ?>
                        <a href="<?= $waLink ?>" target="_blank" style="color:var(--teal);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                            <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($p['wa']) ?>
                        </a>
                        <?php else: ?>
                        <span style="color:#555;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($p['domain']): ?>
                        <a href="https://<?= preg_replace('/^https?:\/\//','',$p['domain']) ?>" target="_blank" style="color:#aaa;text-decoration:none;">
                            <?= htmlspecialchars($p['domain']) ?> <i class="fas fa-external-link-alt" style="font-size:0.68rem;"></i>
                        </a>
                        <?php else: ?>
                        <span style="color:#555;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="last-visit-tag <?= $lv_class ?>">
                            <i class="fas fa-calendar-day" style="margin-right:3px;"></i><?= $lv_text ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;flex-direction:column;align-items:flex-start;">
                            <span class="badge"><?= htmlspecialchars($p['tier'] ?? 'UMKM') ?></span>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($p['status']) ?><?= ($p['deal_status'] ? ' ('.$p['deal_status'].')' : '') ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="act-btns">
                            <?php if($p['wa']): ?>
                            <a href="<?= $waLink ?>" target="_blank" class="act-btn wa-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                            <?php if($p['link_deck']): ?>
                            <a href="<?= htmlspecialchars($p['link_deck']) ?>" target="_blank" class="act-btn" title="Deck"><i class="fas fa-file-powerpoint"></i></a>
                            <?php endif; ?>
                            <button class="act-btn" onclick="editProspect(<?= $p['id'] ?>)" title="Detail &amp; Riwayat"><i class="fas fa-eye"></i></button>
                            <?php if($p['status'] === 'Deal' && !$p['is_synced']): ?>
                            <button class="act-btn" style="color:var(--teal);border-color:rgba(78,253,196,0.3);" onclick="syncToClient(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['company_name'])) ?>')" title="Sinkron ke Client"><i class="fas fa-user-plus"></i></button>
                            <?php endif; ?>
                            <button class="act-btn del" onclick="deleteProspect(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['company_name'])) ?>')" title="Hapus"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ADD/EDIT -->
<div class="modal-overlay" id="prospectModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="modalTitle">Tambah Prospek</h3>
            <button class="btn-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="m-tabs" id="modalTabsGroup">
                <button class="m-tab active" onclick="switchModalTab('data', this)"><i class="fas fa-building" style="margin-right:6px;"></i>Data Prospek</button>
                <button class="m-tab" onclick="switchModalTab('meetings', this)" id="tab-btn-meetings" style="display:none;"><i class="fas fa-calendar-check" style="margin-right:6px;"></i>Riwayat Meeting</button>
                <button class="m-tab" onclick="switchModalTab('invoices', this)" id="tab-btn-invoices" style="display:none;"><i class="fas fa-file-invoice" style="margin-right:6px;"></i>Invoice (Lunas)</button>
            </div>
            
            <!-- TAB 1: DATA -->
            <div id="mTab-data" class="m-tab-content active">
                <input type="hidden" id="f_id">
                <div class="form-row">
                    <div class="form-grp" style="grid-column:span 2;">
                        <label>Nama Perusahaan *</label>
                        <input type="text" id="f_company" placeholder="PT. Contoh Maju Bersama">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-grp">
                        <label>PIC (Person In Charge)</label>
                        <input type="text" id="f_pic" placeholder="Nama kontak">
                    </div>
                    <div class="form-grp">
                        <label>Jabatan</label>
                        <input type="text" id="f_jabatan" placeholder="Marketing Manager">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-grp">
                        <label>No. WhatsApp</label>
                        <input type="text" id="f_wa" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="form-grp">
                        <label><i class="fas fa-route" style="margin-right:6px;color:var(--green);"></i>Status Pipeline</label>
                        <select id="f_status" onchange="onStatusSelectChange()">
                            <option value="Prospecting">Prospecting</option>
                            <option value="Follow Up">Follow Up</option>
                            <option value="Negotiation">Negosiasi</option>
                            <option value="Deal">Deal</option>
                            <option value="Lost">Lost</option>
                        </select>
                    </div>
                    <div class="form-grp">
                        <label><i class="fas fa-layer-group" style="margin-right:6px;color:var(--green);"></i>Skala Usaha</label>
                        <select id="f_tier">
                            <option value="UMKM">UMKM (Usaha Mikro, Kecil, Menengah)</option>
                            <option value="Perusahaan Menengah">Perusahaan Menengah</option>
                            <option value="Korporasi">Korporasi / Enterprise</option>
                            <option value="Instansi / Pemerintah">Instansi / Pemerintah</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-grp" style="grid-column:span 2;">
                        <label>Hasil Akhir (Deal Status)</label>
                        <div style="display:flex; gap:10px; margin-top:4px;" id="dealStatusGroup">
                            <label style="cursor:pointer; display:flex; align-items:center; justify-content:center; padding:8px 14px; border:1px solid rgba(255,255,255,0.1); border-radius:10px; transition:0.3s;" class="deal-chip">
                                <input type="radio" name="f_deal_status" value="" checked style="display:none;" onchange="onDealRadioChange()">
                                <span style="font-size:0.75rem; color:#aaa; font-weight:600;"><i class="fas fa-minus-circle" style="margin-right:6px;"></i>Belum Ditentukan</span>
                            </label>
                            <label style="cursor:pointer; display:flex; align-items:center; justify-content:center; padding:8px 14px; border:1px solid rgba(255,255,255,0.1); border-radius:10px; transition:0.3s;" class="deal-chip">
                                <input type="radio" name="f_deal_status" value="Deal" style="display:none;" onchange="onDealRadioChange()">
                                <span style="font-size:0.75rem; color:var(--green); font-weight:600;"><i class="fas fa-handshake" style="margin-right:6px;"></i>Deal</span>
                            </label>
                            <label style="cursor:pointer; display:flex; align-items:center; justify-content:center; padding:8px 14px; border:1px solid rgba(255,255,255,0.1); border-radius:10px; transition:0.3s;" class="deal-chip">
                                <input type="radio" name="f_deal_status" value="Gak Deal" style="display:none;" onchange="onDealRadioChange()">
                                <span style="font-size:0.75rem; color:#aaa; font-weight:600;"><i class="fas fa-times-circle" style="margin-right:6px;"></i>Gak Deal</span>
                            </label>
                            <label style="cursor:pointer; display:flex; align-items:center; justify-content:center; padding:8px 14px; border:1px solid rgba(255,255,255,0.1); border-radius:10px; transition:0.3s;" class="deal-chip">
                                <input type="radio" name="f_deal_status" value="Ghosting" style="display:none;" onchange="onDealRadioChange()">
                                <span style="font-size:0.75rem; color:#aaa; font-weight:600;"><i class="fas fa-ghost" style="margin-right:6px;"></i>Ghosting</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-grp">
                        <label>Domain / Website</label>
                        <input type="text" id="f_domain" placeholder="contoh.com">
                    </div>
                    <div class="form-grp">
                        <label>Link Deck / Proposal</label>
                        <input type="text" id="f_deck" placeholder="https://...">
                    </div>
                </div>
                <div class="form-grp">
                    <label>Alamat</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="f_alamat" placeholder="Jl. Contoh No. 1, Kota" style="flex:1;">
                        <button type="button" onclick="openMaps('f_alamat')" title="Buka di Google Maps" style="width:42px; background:rgba(255,255,255,0.05); border:1px solid var(--border); border-radius:10px; color:var(--teal); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.1rem; transition:0.2s;" onmouseover="this.style.background='rgba(78,253,196,0.1)';this.style.color='#fff';" onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.color='var(--teal)';"><i class="fas fa-map-marked-alt"></i></button>
                    </div>
                </div>
                <div class="form-grp">
                    <label>Catatan</label>
                    <textarea id="f_catatan" rows="3" placeholder="Catatan meeting, kebutuhan, dll..."></textarea>
                </div>
            </div>

            <!-- TAB 2: MEETINGS -->
            <div id="mTab-meetings" class="m-tab-content">
                <div id="pMeetingsList"></div>
            </div>

            <!-- TAB 3: INVOICES -->
            <div id="mTab-invoices" class="m-tab-content">
                <div id="pInvoicesList"></div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-cancel" onclick="closeModal()">Batal</button>
            <button class="btn-save" onclick="saveProspect()"><i class="fas fa-save" style="margin-right:6px;"></i>Simpan</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
let searchTimer;
function debounceSearch(val) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        url.searchParams.set('s', val);
        window.location = url;
    }, 500);
}

function filterStatus(st) {
    const url = new URL(window.location);
    if(st) url.searchParams.set('st', st);
    else url.searchParams.delete('st');
    window.location = url;
}

function filterPeriod(p) {
    const url = new URL(window.location);
    if(p) {
        url.searchParams.set('period', p);
        url.searchParams.delete('start_date');
        url.searchParams.delete('end_date');
    } else {
        url.searchParams.delete('period');
        url.searchParams.delete('start_date');
        url.searchParams.delete('end_date');
    }
    window.location = url;
}

function toggleCustomDate() {
    const wrap = document.getElementById('customDateWrap');
    if(wrap) {
        wrap.style.display = (wrap.style.display === 'none' || !wrap.style.display) ? 'flex' : 'none';
    }
}

function applyCustomDate() {
    const s = document.getElementById('startDateInput').value;
    const e = document.getElementById('endDateInput').value;
    if(!s || !e) return showToast('Pilih tanggal awal dan akhir!', true);
    const url = new URL(window.location);
    url.searchParams.set('period', 'custom');
    url.searchParams.set('start_date', s);
    url.searchParams.set('end_date', e);
    window.location = url;
}

function openMaps(inputId) {
    const val = document.getElementById(inputId).value.trim();
    if(!val) return showToast('Alamat masih kosong!', true);
    window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(val), '_blank');
}

function onDealRadioChange() {
    let selected = '';
    document.getElementsByName('f_deal_status').forEach(r => { if(r.checked) selected = r.value; });
    const statusSelect = document.getElementById('f_status');
    if(selected === 'Gak Deal' || selected === 'Ghosting') {
        statusSelect.value = 'Lost';
    } else if(selected === 'Deal') {
        statusSelect.value = 'Deal';
    }
    updateDealChips();
}

function onStatusSelectChange() {
    const val = document.getElementById('f_status').value;
    const dealRadios = document.getElementsByName('f_deal_status');
    if(val === 'Lost') {
        dealRadios.forEach(r => { if(r.value === 'Gak Deal') r.checked = true; });
    } else if(val === 'Deal') {
        dealRadios.forEach(r => { if(r.value === 'Deal') r.checked = true; });
    } else {
        dealRadios[0].checked = true; // Belum ditentukan
    }
    updateDealChips();
}

function openModal(data = null) {
    document.getElementById('f_id').value = data ? data.id : '';
    document.getElementById('f_company').value = data ? (data.company_name||'') : '';
    document.getElementById('f_pic').value = data ? (data.pic||'') : '';
    document.getElementById('f_jabatan').value = data ? (data.jabatan||'') : '';
    document.getElementById('f_wa').value = data ? (data.wa||'') : '';
    document.getElementById('f_domain').value = data ? (data.domain||'') : '';
    document.getElementById('f_deck').value = data ? (data.link_deck||'') : '';
    document.getElementById('f_alamat').value = data ? (data.alamat||'') : '';
    document.getElementById('f_catatan').value = data ? (data.catatan||'') : '';
    document.getElementById('f_status').value = data ? (data.status||'Prospecting') : 'Prospecting';
    document.getElementById('f_tier').value = data ? (data.tier||'UMKM') : 'UMKM';
    
    const dealRadios = document.getElementsByName('f_deal_status');
    dealRadios[0].checked = true;
    if(data && data.deal_status) {
        dealRadios.forEach(r => { if(r.value === data.deal_status) r.checked = true; });
    }
    
    document.getElementById('modalTitle').textContent = data ? 'Edit Prospek' : 'Tambah Prospek';
    
    updateDealChips();

    // Render History Section
    if(data && data.id) {
        switchModalTab('data', document.querySelector('.m-tab'));
    
        // Meetings
        document.getElementById('tab-btn-meetings').style.display = 'block';
        document.getElementById('tab-btn-invoices').style.display = 'block';

        const pMeet = document.getElementById('pMeetingsList');
        pMeet.innerHTML = '';
        if(data.meetings && data.meetings.length>0) {
            data.meetings.forEach(m => {
                const dateStr = new Date(m.event_date).toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'});
                const timeStr = m.time_start || '';
                let logHtml = m.log_hasil ? `<div style="font-size:0.75rem;color:#ccc;background:rgba(255,255,255,0.03);padding:8px;border-radius:6px;border:1px solid rgba(255,255,255,0.05);white-space:pre-wrap;margin-bottom:8px;">${m.log_hasil}</div>` : `<div style="font-size:0.75rem;color:#666;font-style:italic;margin-bottom:8px;">Belum ada log/catatan.</div>`;
                const editBtn = `<button type="button" onclick="editMeetingLog(${m.id})" style="padding:4px 8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:var(--green);border-radius:4px;font-size:0.7rem;cursor:pointer;"><i class="fas fa-edit"></i> Edit Log Hasil</button>`;
                const locHtml = m.location ? `<div style="font-size:0.72rem;color:#aaa;margin-bottom:8px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;"><i class="fas fa-map-marker-alt" style="color:var(--teal);flex-shrink:0;"></i><a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(m.location)}" target="_blank" style="color:#aaa;text-decoration:none;flex:1;" onmouseover="this.style.color='var(--teal)'" onmouseout="this.style.color='#aaa'">${m.location}</a></div>` : '';
                const teamsHtml = m.teams_involved ? `<div style="margin-bottom:8px;display:flex;flex-wrap:wrap;gap:4px;">${m.teams_involved.split(',').filter(t=>t.trim()).map(t=>`<span style="font-size:0.68rem;background:rgba(161,255,90,0.08);border:1px solid rgba(161,255,90,0.2);color:#a1ff5a;padding:2px 8px;border-radius:20px;font-weight:600;"><i class="fas fa-user" style="margin-right:3px;font-size:0.6rem;"></i>${t.trim()}</span>`).join('')}</div>` : '';
                pMeet.innerHTML += `
                    <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:8px;padding:12px;margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <div style="font-weight:700;font-size:0.85rem;color:#fff;">${m.title}</div>
                            <div style="font-size:0.75rem;color:var(--muted);">${m.meeting_mode}</div>
                        </div>
                        <div style="font-size:0.75rem;color:var(--teal);margin-bottom:8px;">${dateStr} ${timeStr} | ${m.meeting_type}</div>
                        ${locHtml}
                        ${teamsHtml}
                        ${logHtml}
                        ${editBtn}
                        <div id="log-edit-form-${m.id}" style="display:none;margin-top:8px;">
                            <textarea id="log-val-${m.id}" style="width:100%;height:80px;background:rgba(0,0,0,0.5);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:6px;padding:8px;font-size:0.8rem;margin-bottom:6px;">${m.log_hasil||''}</textarea>
                            <button type="button" onclick="saveMeetingLog(${m.id})" style="padding:5px 12px;background:var(--green);color:#000;border:none;border-radius:4px;font-size:0.75rem;cursor:pointer;font-weight:700;">Simpan</button>
                            <button type="button" onclick="document.getElementById('log-edit-form-${m.id}').style.display='none'" style="padding:5px 12px;background:transparent;color:var(--muted);border:none;cursor:pointer;font-size:0.75rem;">Batal</button>
                        </div>
                    </div>
                `;
            });
        } else {
            pMeet.innerHTML = '<div style="font-size:0.8rem;color:var(--muted);font-style:italic;padding:20px;text-align:center;">Belum ada riwayat meeting.</div>';
        }

        // Invoices
        const pInv = document.getElementById('pInvoicesList');
        pInv.innerHTML = '';
        if(data.invoices_hist && data.invoices_hist.length>0) {
            data.invoices_hist.forEach(inv => {
                const dateStr = new Date(inv.inv_date).toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'});
                const totalStr = 'Rp ' + parseInt(inv.total).toLocaleString('id-ID');
                pInv.innerHTML += `
                    <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:8px;padding:12px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-weight:700;font-size:0.85rem;color:#fff;margin-bottom:4px;">Invoice #${inv.inv_no}</div>
                            <div style="font-size:0.75rem;color:var(--muted);">${dateStr} | ${inv.service_label}</div>
                        </div>
                        <div style="font-weight:700;color:var(--green);font-size:0.9rem;">${totalStr}</div>
                    </div>
                `;
            });
        } else {
            pInv.innerHTML = '<div style="font-size:0.8rem;color:var(--muted);font-style:italic;padding:20px;text-align:center;">Belum ada invoice.</div>';
        }
    } else {
        document.getElementById('tab-btn-meetings').style.display = 'none';
        document.getElementById('tab-btn-invoices').style.display = 'none';
        switchModalTab('data', document.querySelector('.m-tab'));
    }

    document.getElementById('prospectModal').classList.add('open');
    setTimeout(() => document.getElementById('f_company').focus(), 100);
}

function switchModalTab(tabId, btnEl) {
    document.querySelectorAll('.m-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.m-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('mTab-' + tabId).classList.add('active');
    btnEl.classList.add('active');
}

function closeModal() { document.getElementById('prospectModal').classList.remove('open'); }

async function editProspect(id) {
    const fd = new FormData();
    fd.append('ajax_action', 'get');
    fd.append('id', id);
    const res = await fetch('', { method:'POST', body:fd });
    const data = await res.json();
    if(data) openModal(data);
}

async function saveProspect() {
    const company = document.getElementById('f_company').value.trim();
    if(!company) { showToast('Nama perusahaan wajib diisi!', true); return; }
    const fd = new FormData();
    fd.append('ajax_action', 'save');
    fd.append('id', document.getElementById('f_id').value || 0);
    fd.append('company_name', company);
    fd.append('pic', document.getElementById('f_pic').value);
    fd.append('jabatan', document.getElementById('f_jabatan').value);
    fd.append('wa', document.getElementById('f_wa').value);
    fd.append('domain', document.getElementById('f_domain').value);
    fd.append('link_deck', document.getElementById('f_deck').value);
    fd.append('alamat', document.getElementById('f_alamat').value);
    fd.append('catatan', document.getElementById('f_catatan').value);
    fd.append('status', document.getElementById('f_status').value);
    fd.append('tier', document.getElementById('f_tier').value);
    
    let dealStat = '';
    document.getElementsByName('f_deal_status').forEach(r => { if(r.checked) dealStat = r.value; });
    fd.append('deal_status', dealStat);

    const res = await fetch('', { method:'POST', body:fd });
    const data = await res.json();
    if(data.ok) {
        closeModal();
        if(data.auto_synced && data.client_id) {
            showToast('Deal! Data otomatis ditambahkan ke Clients. Mengalihkan...');
            setTimeout(() => {
                window.location = '/dashboard/clients/?highlight=' + data.client_id + '&edit=' + data.client_id;
            }, 1200);
        } else {
            showToast('Data berhasil disimpan!');
            setTimeout(() => location.reload(), 800);
        }
    } else {
        showToast(data.msg || 'Gagal menyimpan!', true);
    }
}

async function deleteProspect(id, name) {
    if(!confirm(`Hapus "${name}" dari daftar prospek?`)) return;
    const fd = new FormData();
    fd.append('ajax_action', 'delete');
    fd.append('id', id);
    await fetch('', { method:'POST', body:fd });
    showToast('Dihapus!');
    setTimeout(() => location.reload(), 700);
}

async function syncToClient(id, name) {
    if(!confirm(`Sinkronkan prospek "${name}" ke daftar Klien?\nData dasar (nama, PIC, WA, alamat) akan disalin.\nAnda dapat melengkapi data di halaman Clients.`)) return;
    const fd = new FormData();
    fd.append('ajax_action', 'sync_client');
    fd.append('id', id);
    const res = await fetch('', { method:'POST', body:fd });
    const data = await res.json();
    if(data.ok) {
        showToast(`${name} berhasil ditambahkan ke Clients!`);
        setTimeout(() => {
            window.location = '/dashboard/clients/?highlight=' + data.client_id;
        }, 1200);
    } else {
        showToast(data.msg || 'Gagal sinkronisasi!', true);
    }
}

function showToast(msg, isErr = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.borderColor = isErr ? '#ff6b6b' : 'var(--green)';
    t.style.color = isErr ? '#ff6b6b' : 'var(--green)';
    t.classList.add('show');
    setTimeout(() => { t.classList.remove('show'); }, 3000);
}

function updateDealChips() {
    const chips = document.querySelectorAll('.deal-chip');
    chips.forEach(chip => {
        const input = chip.querySelector('input');
        if (input.checked) {
            if(input.value === 'Deal') { chip.style.background = 'rgba(161,255,90,0.12)'; chip.style.borderColor = 'var(--green)'; }
            else if(input.value === 'Gak Deal') { chip.style.background = 'rgba(255,255,255,0.06)'; chip.style.borderColor = '#888'; }
            else if(input.value === 'Ghosting') { chip.style.background = 'rgba(255,255,255,0.06)'; chip.style.borderColor = '#888'; }
            else { chip.style.background = 'rgba(255,255,255,0.05)'; chip.style.borderColor = '#666'; }
        } else {
            chip.style.background = 'transparent';
            chip.style.borderColor = 'rgba(255,255,255,0.1)';
        }
    });
}
updateDealChips();

function editMeetingLog(id) {
    document.getElementById(`log-edit-form-${id}`).style.display = 'block';
}

function saveMeetingLog(id) {
    const val = document.getElementById(`log-val-${id}`).value;
    const fd = new FormData();
    fd.append('ajax_action', 'update_log');
    fd.append('event_id', id);
    fd.append('log_hasil', val);
    
    fetch('../index.php', { method:'POST', body:fd })
        .then(r=>r.json())
        .then(res => {
            if(res.ok) {
                showToast('Log meeting berhasil disimpan');
                editProspect(document.getElementById('f_id').value);
            } else {
                showToast('Gagal menyimpan log', true);
            }
        });
}

document.getElementById('prospectModal').addEventListener('click', function(e) {
    if(e.target === this) closeModal();
});
</script>
</body>
</html>