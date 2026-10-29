<?php
// ============================================================
// HVM PERFORMANCE — NERACA KEUANGAN (BALANCE SHEET) v3.0
// Exact Harmony with Performance Report Standards
// ============================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if (!isset($_SESSION['admin'])) {
    echo "<script>window.location='/index.php';</script>"; exit;
}
$allowed   = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
$adminName = htmlspecialchars($_SESSION['admin'] ?? 'Admin');

// Auto-create table for balance sheet accounts
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `balance_sheet_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `period_year` INT NOT NULL,
    `period_type` VARCHAR(20) NOT NULL DEFAULT 'annual',
    `account_code` VARCHAR(50) NOT NULL,
    `account_name` VARCHAR(150) NOT NULL,
    `account_category` VARCHAR(50) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `notes` TEXT NULL,
    `is_auto` TINYINT(1) DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_period_account` (`period_year`, `period_type`, `account_code`)
)");

// Selected Period
$selected_year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_type = isset($_GET['type']) && in_array($_GET['type'], ['annual', 'q1', 'q2', 'q3', 'q4']) ? $_GET['type'] : 'annual';

// Auto-sync real-time data from database
$unpaid_invoices_total = 0;
$q_unpaid = mysqli_query($conn, "SELECT COALESCE(SUM(total), 0) AS total_unpaid FROM invoices WHERE status IN ('Pending', 'Overdue')");
if ($q_unpaid) {
    $r_unpaid = mysqli_fetch_assoc($q_unpaid);
    $unpaid_invoices_total = (float)($r_unpaid['total_unpaid'] ?? 0);
}

// Total current net profit for the year
$current_net_profit = 0;
$q_inc = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total_inc FROM payments WHERE YEAR(payment_date) = $selected_year");
$q_exp = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total_exp FROM spendings WHERE YEAR(spending_date) = $selected_year");
$inc_val = ($q_inc) ? (float)mysqli_fetch_assoc($q_inc)['total_inc'] : 0;
$exp_val = ($q_exp) ? (float)mysqli_fetch_assoc($q_exp)['total_exp'] : 0;
$current_net_profit = $inc_val - $exp_val;

// Handle Save / Update Account Item via POST
$msg_success = '';
if ($allowed && isset($_POST['save_account_item'])) {
    $code     = mysqli_real_escape_string($conn, trim($_POST['account_code'] ?? ''));
    $name     = mysqli_real_escape_string($conn, trim($_POST['account_name'] ?? ''));
    $category = mysqli_real_escape_string($conn, trim($_POST['account_category'] ?? ''));
    $amount   = (float)str_replace(['.', ','], ['', '.'], $_POST['amount'] ?? 0);
    $notes    = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
    $yr       = (int)($_POST['period_year'] ?? $selected_year);
    $ptype    = mysqli_real_escape_string($conn, $_POST['period_type'] ?? $selected_type);

    if ($code && $name && $category) {
        $sql_save = "INSERT INTO balance_sheet_records (period_year, period_type, account_code, account_name, account_category, amount, notes)
                     VALUES ($yr, '$ptype', '$code', '$name', '$category', $amount, '$notes')
                     ON DUPLICATE KEY UPDATE account_name='$name', account_category='$category', amount=$amount, notes='$notes'";
        mysqli_query($conn, $sql_save);
        $msg_success = "Akun neraca '$name' berhasil disimpan!";
    }
}

// Handle Auto-Sync Request
if ($allowed && isset($_POST['sync_live_data'])) {
    mysqli_query($conn, "INSERT INTO balance_sheet_records (period_year, period_type, account_code, account_name, account_category, amount, notes, is_auto)
        VALUES ($selected_year, '$selected_type', 'piutang_usaha', 'Piutang Usaha Klien (Unpaid Invoices)', 'asset_current', $unpaid_invoices_total, 'Sinkronisasi otomatis dari tagihan Invoice berstatus Pending/Overdue', 1)
        ON DUPLICATE KEY UPDATE amount=$unpaid_invoices_total, is_auto=1");
    
    mysqli_query($conn, "INSERT INTO balance_sheet_records (period_year, period_type, account_code, account_name, account_category, amount, notes, is_auto)
        VALUES ($selected_year, '$selected_type', 'laba_berjalan', 'Laba Bersih Tahun Berjalan (Net Income)', 'equity', $current_net_profit, 'Sinkronisasi otomatis dari omzet pembayaran - pengeluaran tahun $selected_year', 1)
        ON DUPLICATE KEY UPDATE amount=$current_net_profit, is_auto=1");

    $msg_success = "Data piutang dan laba berjalan berhasil disinkronkan!";
}

// Handle Delete Account Item
if ($allowed && isset($_GET['del_account'])) {
    $del_id = (int)$_GET['del_account'];
    mysqli_query($conn, "DELETE FROM balance_sheet_records WHERE id = $del_id");
    header("Location: /dashboard/performance/neraca.php?year=$selected_year&type=$selected_type");
    exit;
}

// Seed baseline accounts if empty
$q_check = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM balance_sheet_records WHERE period_year = $selected_year AND period_type = '$selected_type'");
$count_records = (int)mysqli_fetch_assoc($q_check)['cnt'];

if ($count_records === 0 && $allowed) {
    $baseline_accounts = [
        ['kas_bank', 'Kas & Rekening Operasional (BCA/Mandiri)', 'asset_current', 75000000, 'Saldo likuid pada rekening bank perusahaan'],
        ['piutang_usaha', 'Piutang Usaha Klien (Unpaid Invoices)', 'asset_current', $unpaid_invoices_total, 'Tagihan invoice yang belum terbayar oleh klien'],
        ['biaya_dimuka', 'Biaya Dibayar di Muka (Prepaid Server & Tools)', 'asset_current', 12500000, 'Sewa hosting, lisensi software, & tools SEO tahunan'],
        ['kas_kecil', 'Kas Kecil / Petty Cash Operasional', 'asset_current', 5000000, 'Kas darurat operasional kantor HVM'],
        
        ['peralatan_kantor', 'Perangkat Kerja (MacBook, PC Editing & Studio)', 'asset_fixed', 65000000, 'Nilai perolehan laptop, workstation tim, dan kamera'],
        ['depresiasi', 'Akumulasi Penyusutan Aset Tetap (-)', 'asset_fixed', -15000000, 'Penyusutan nilai perangkat kerja akibat pemakaian'],
        ['aset_digital', 'Aset Digital & Lisensi Lifetime', 'asset_fixed', 25000000, 'Portofolio domain, website resmi, dan lisensi plugin'],

        ['utang_usaha', 'Utang Vendor & Freelancer', 'liability_current', 8500000, 'Kewajiban pembayaran ke freelancer / vendor luar'],
        ['pendapatan_dimuka', 'Pendapatan Diterima di Muka (Unearned Retainer)', 'liability_current', 22000000, 'DP & kontrak berjalan klien yang jasanya belum selesai penuh'],
        ['utang_pajak', 'Estimasi Utang Pajak Usaha (PPh/PPN)', 'liability_current', 4500000, 'Pajak penghasilan dan PPN yang belum disetorkan'],
        ['beban_akrual', 'Beban Akrual (Gaji & Listrik Berjalan)', 'liability_current', 7500000, 'Beban operasional yang jatuh tempo di akhir periode'],

        ['utang_investasi', 'Pinjaman Modal Kerja / Perbankan', 'liability_long', 0, 'Kewajiban jangka panjang dengan tenor > 1 tahun'],

        ['modal_disetor', 'Modal Awal Disetor Pemilik (Founder Capital)', 'equity', 50000000, 'Penyertaan modal awal pendiri HVM Digital'],
        ['laba_ditahan', 'Laba Ditahan Periode Lalu (Retained Earnings)', 'equity', 45000000, 'Akumulasi keuntungan tahun-tahun sebelumnya yang disimpan'],
        ['laba_berjalan', 'Laba Bersih Tahun Berjalan (Net Income)', 'equity', $current_net_profit, 'Keuntungan bersih tahun berjalan dari modul Finance'],
        ['prive', 'Prive / Penarikan Modal Pemilik (-)', 'equity', 0, 'Penarikan dana oleh founder untuk kepentingan di luar bisnis']
    ];

    foreach ($baseline_accounts as $acc) {
        $c_cat = $acc[2];
        $sql_seed = "INSERT IGNORE INTO balance_sheet_records (period_year, period_type, account_code, account_name, account_category, amount, notes)
                     VALUES ($selected_year, '$selected_type', '{$acc[0]}', '{$acc[1]}', '{$c_cat}', {$acc[3]}, '{$acc[4]}')";
        mysqli_query($conn, $sql_seed);
    }
}

// Fetch balance sheet records
$records = [];
$q_recs = mysqli_query($conn, "SELECT * FROM balance_sheet_records WHERE period_year = $selected_year AND period_type = '$selected_type' ORDER BY id ASC");
if ($q_recs) {
    while ($r = mysqli_fetch_assoc($q_recs)) {
        $records[$r['account_category']][] = $r;
    }
}

// Calculations
$total_asset_current = 0;
if (!empty($records['asset_current'])) {
    foreach ($records['asset_current'] as $item) $total_asset_current += (float)$item['amount'];
}

$total_asset_fixed = 0;
if (!empty($records['asset_fixed'])) {
    foreach ($records['asset_fixed'] as $item) $total_asset_fixed += (float)$item['amount'];
}
$total_assets = $total_asset_current + $total_asset_fixed;

$total_liability_current = 0;
if (!empty($records['liability_current'])) {
    foreach ($records['liability_current'] as $item) $total_liability_current += (float)$item['amount'];
}

$total_liability_long = 0;
if (!empty($records['liability_long'])) {
    foreach ($records['liability_long'] as $item) $total_liability_long += (float)$item['amount'];
}
$total_liabilities = $total_liability_current + $total_liability_long;

$total_equity = 0;
if (!empty($records['equity'])) {
    foreach ($records['equity'] as $item) $total_equity += (float)$item['amount'];
}

$total_pasiva = $total_liabilities + $total_equity;
$diff_balance = abs($total_assets - $total_pasiva);
$is_balanced = ($diff_balance < 1);

// Ratios
$current_ratio = ($total_liability_current > 0) ? round($total_asset_current / $total_liability_current, 2) : 0;
$debt_to_equity = ($total_equity > 0) ? round($total_liabilities / $total_equity, 2) : 0;
$net_working_capital = $total_asset_current - $total_liability_current;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Performance Neraca — HVM</title>
<link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">

<!-- Layer 1: jsDelivr npm font-awesome (Most reliable, no integrity lock) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
<!-- Layer 2: cdnjs backup -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="print" onload="this.media='all'">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
:root {
    --bg:          #050505;
    --card-bg:     rgba(16,16,16,0.72);
    --card-border: rgba(255,255,255,0.07);
    --neon-main:   #a1ff5a;
    --neon-sec:    #4efdc4;
    --neon-red:    #ff5a5a;
    --neon-orange: #ff9f43;
    --text-muted:  #888;
    --radius:      16px;
    --sidebar-w:   260px;
}

/* CRITICAL: Force FontAwesome icons to render without getting overridden */
.fa, .fas, .far, .fab, .fal, .fa-solid, .fa-regular, .fa-brands {
    font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands", "FontAwesome" !important;
    font-style: normal !important;
    font-variant: normal !important;
    text-rendering: auto !important;
    line-height: 1 !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    display: inline-block !important;
}
.fa::before, .fas::before, .fa-solid::before,
.fa-regular::before, .far::before, .fab::before {
    font-display: block !important;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body { background:var(--bg); color:#fff; font-family:'Montserrat','Segoe UI',sans-serif; min-height:100vh; overflow-x:hidden; }

/* Ambient glow */
.ambient-glow { position:fixed; border-radius:50%; filter:blur(140px); opacity:.08; z-index:0; pointer-events:none; }
.glow-1 { top:-150px; left:-150px; width:700px; height:700px; background:var(--neon-main); }
.glow-2 { bottom:-150px; right:-150px; width:700px; height:700px; background:var(--neon-sec); }

/* Layout */
.dashboard-wrapper { display:flex; width:100%; min-height:100vh; position:relative; z-index:1; }
.main-content { margin-left:var(--sidebar-w); padding:32px 36px; width:calc(100% - var(--sidebar-w)); }
@media(max-width:768px) { .main-content { margin-left:0; width:100%; padding:20px 16px 120px; } }

/* Page headline */
.page-headline { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; gap:20px; flex-wrap:wrap; }
.hl-tag { display:inline-flex; align-items:center; gap:7px; font-size:.62rem; font-weight:800; letter-spacing:2.5px; color:var(--neon-main); text-transform:uppercase; background:rgba(161,255,90,.07); border:1px solid rgba(161,255,90,.18); padding:5px 13px; border-radius:50px; margin-bottom:10px; }
.page-headline h1 { font-size:2rem; font-weight:900; line-height:1.1; margin-bottom:6px; background:linear-gradient(180deg,#fff 40%,#444); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.grad-text { background:linear-gradient(135deg,var(--neon-main),var(--neon-sec)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.page-headline p { color:var(--text-muted); font-size:.84rem; }

/* Headline Actions (Matching Performance Buttons) */
.hl-actions { display:flex; gap:10px; flex-wrap:wrap; }
.btn-act { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.04); border:1px solid var(--card-border); color:#aaa; padding:9px 18px; border-radius:50px; font-size:.78rem; font-weight:700; cursor:pointer; transition:.3s; font-family:inherit; text-decoration:none; }
.btn-act:hover { color:#fff; border-color:rgba(255,255,255,.2); }
.btn-act-primary { background:rgba(161,255,90,.1); border-color:rgba(161,255,90,.3); color:var(--neon-main); }
.btn-act-primary:hover { background:rgba(161,255,90,.18); border-color:var(--neon-main); box-shadow:0 0 20px rgba(161,255,90,.2); color:#fff; }

/* Filter Bar */
.top-bar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
.filter-glass { display:flex; align-items:center; gap:14px; background:rgba(14,14,14,.8); backdrop-filter:blur(20px); border:1px solid var(--card-border); border-radius:14px; padding:12px 20px; flex-wrap:wrap; }
.filter-label { display:flex; align-items:center; gap:6px; font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#555; }
.filter-select { background:rgba(0,0,0,.5); border:1px solid rgba(255,255,255,.07); border-radius:9px; color:#ddd; padding:8px 28px 8px 12px; font-family:inherit; font-size:.82rem; font-weight:600; outline:none; cursor:pointer; transition:.3s; -webkit-appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23555'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 9px center; }
.filter-select:focus { border-color:var(--neon-main); }
.filter-select option { background:#111; color:#fff; }

/* 6 Key Stats Grid (Exact match with performance/index.php) */
.stats-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:24px; }
@media(max-width:1200px) { .stats-grid { grid-template-columns:repeat(3,1fr); } }
@media(max-width:650px) { .stats-grid { grid-template-columns:repeat(2,1fr); } }

.stat-card { background:var(--card-bg); backdrop-filter:blur(24px); border:1px solid var(--card-border); border-radius:var(--radius); padding:18px 20px; position:relative; overflow:hidden; transition:transform .25s,box-shadow .25s,border-color .25s; }
.stat-card:hover { transform:translateY(-3px); border-color:rgba(255,255,255,.16); }
.sc-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
.sc-icon { width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); display:flex; align-items:center; justify-content:center; color:var(--neon-main); font-size:1rem; }
.sc-label { font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#555; margin-bottom:6px; }
.sc-val { font-size:1.25rem; font-weight:900; margin-bottom:8px; line-height:1; color:#fff; }
.sc-foot { display:flex; align-items:center; gap:6px; font-size:.68rem; color:#444; border-top:1px solid rgba(255,255,255,.04); padding-top:8px; }

/* 2-Column Balance Sheet Table Cards */
.balance-sheet-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
@media(max-width:950px) { .balance-sheet-grid { grid-template-columns:1fr; } }

.chart-main { background:var(--card-bg); backdrop-filter:blur(24px); border:1px solid var(--card-border); border-radius:var(--radius); padding:22px; display:flex; flex-direction:column; }
.chart-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid rgba(255,255,255,.05); }
.sec-label { display:inline-flex; align-items:center; gap:8px; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#777; }
.sec-label i { color:var(--neon-main); font-size:.85rem; }

/* Category Block inside sheet */
.category-subgroup { margin-bottom:18px; }
.category-sub-head { display:flex; justify-content:space-between; align-items:center; font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#555; padding-bottom:6px; border-bottom:1px dashed rgba(255,255,255,.05); margin-bottom:8px; }
.account-line { display:flex; justify-content:space-between; align-items:center; padding:7px 8px; border-radius:6px; transition:.2s; font-size:.82rem; }
.account-line:hover { background:rgba(255,255,255,.03); }
.acc-title-text { color:#ccc; font-weight:500; display:flex; align-items:center; gap:8px; }
.acc-tag-pill { font-size:.55rem; padding:1px 6px; border-radius:4px; background:rgba(161,255,90,.08); color:var(--neon-main); border:1px solid rgba(161,255,90,.2); font-weight:700; }
.acc-nominal-val { font-family:monospace; font-weight:700; color:#fff; font-size:.86rem; }
.acc-nominal-val.dim { color:#777; }

.sub-total-bar { display:flex; justify-content:space-between; align-items:center; padding:8px 8px 0; margin-top:6px; border-top:1px solid rgba(255,255,255,.04); font-size:.72rem; font-weight:700; color:#666; text-transform:uppercase; }
.sub-total-val { font-family:monospace; color:#ccc; font-size:.82rem; }

.grand-total-box { margin-top:auto; background:rgba(0,0,0,.4); border:1px solid var(--card-border); border-radius:12px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center; }
.gt-text { font-size:.82rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#888; display:flex; align-items:center; gap:8px; }
.gt-figure { font-size:1.25rem; font-weight:900; font-family:monospace; color:var(--neon-main); }

/* Financial Analysis Card */
.ratio-analysis-bar { background:var(--card-bg); border:1px solid var(--card-border); border-radius:var(--radius); padding:22px; margin-bottom:24px; }
.ratio-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-top:14px; }
@media(max-width:850px) { .ratio-row { grid-template-columns:repeat(2,1fr); } }
@media(max-width:500px) { .ratio-row { grid-template-columns:1fr; } }
.ratio-item { background:rgba(0,0,0,.35); border:1px solid rgba(255,255,255,.05); border-radius:10px; padding:14px 16px; }
.ri-label { font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#555; margin-bottom:6px; }
.ri-val { font-size:1.35rem; font-weight:900; color:#fff; font-family:monospace; margin-bottom:6px; }
.ri-bar { height:4px; background:rgba(255,255,255,.05); border-radius:4px; overflow:hidden; margin-bottom:8px; }
.ri-fill { height:100%; background:var(--neon-main); }
.ri-desc { font-size:.68rem; color:#444; line-height:1.4; }

/* Guide Accordion */
.guide-card { background:var(--card-bg); border:1px solid var(--card-border); border-radius:var(--radius); padding:22px; margin-bottom:30px; }
.acc-guide-item { border:1px solid rgba(255,255,255,.04); border-radius:10px; margin-bottom:8px; background:rgba(0,0,0,.25); }
.acc-guide-head { padding:14px 18px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; font-size:.82rem; font-weight:700; color:#aaa; transition:.2s; }
.acc-guide-head:hover { color:#fff; }
.acc-guide-head i.arrow { font-size:.72rem; color:#444; transition:transform .2s; }
.acc-guide-item.active .acc-guide-head i.arrow { transform:rotate(180deg); color:var(--neon-main); }
.acc-guide-body { display:none; padding:0 18px 16px; font-size:.78rem; color:#666; line-height:1.6; border-top:1px solid rgba(255,255,255,.03); padding-top:12px; }
.acc-guide-item.active .acc-guide-body { display:block; }

/* Modal Editor */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.8); backdrop-filter:blur(6px); z-index:10000; display:none; align-items:center; justify-content:center; padding:20px; }
.modal-overlay.active { display:flex; }
.modal-box { background:#0e0e0e; border:1px solid var(--card-border); border-radius:16px; width:100%; max-width:480px; padding:26px; }
.modal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
.modal-title { font-size:.95rem; font-weight:800; color:#fff; display:flex; align-items:center; gap:8px; }
.modal-close { background:transparent; border:none; color:#555; font-size:1.2rem; cursor:pointer; }
.modal-close:hover { color:#fff; }

.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:#666; margin-bottom:6px; }
.form-input { width:100%; background:#000; border:1px solid var(--card-border); border-radius:8px; padding:10px 14px; color:#fff; font-size:.82rem; font-family:inherit; outline:none; transition:.2s; }
.form-input:focus { border-color:var(--neon-main); }
.form-input option { background:#111; color:#fff; }
.btn-submit-modal { width:100%; padding:12px; border-radius:9px; border:none; background:linear-gradient(135deg,var(--neon-main),var(--neon-sec)); color:#000; font-size:.82rem; font-weight:800; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; margin-top:10px; }
.btn-submit-modal:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(161,255,90,.3); }

/* Print Mode */
@media print {
    .sidebar, .top-bar, .hl-actions, .sub-nav-tabs, .btn-icon-acc, .guide-card, .ambient-glow { display:none !important; }
    .main-content { margin-left:0 !important; width:100% !important; padding:0 !important; }
    body { background:#fff !important; color:#000 !important; }
    .chart-main, .stat-card, .ratio-analysis-bar { background:#fff !important; border:1px solid #ccc !important; color:#000 !important; }
    .gt-figure, .sc-val, .acc-nominal-val { color:#000 !important; }
}
</style>
</head>
<body>

<div class="ambient-glow glow-1"></div>
<div class="ambient-glow glow-2"></div>

<div class="dashboard-wrapper">

<?php include '../sidebar.php'; ?>

<main class="main-content">

    <!-- PAGE HEADLINE (Exact pattern from performance/index.php) -->
    <div class="page-headline">
        <div class="hl-left">
            <div class="hl-tag">
                <i class="fa-solid fa-vault"></i> HVM FINANCIAL VAULT
            </div>
            <h1>Performance <span class="grad-text">Neraca</span></h1>
            <p>Laporan komprehensif posisi Aset (Aktiva), Kewajiban, dan Ekuitas HVM Digital.</p>
        </div>

        <div class="hl-actions">
            <!-- Download Panduan PDF -->
            <a href="/dashboard/performance/download_panduan.php" class="btn-act" target="_blank">
                <i class="fa-solid fa-file-pdf"></i> Panduan PDF
            </a>

            <!-- Sync Live Data -->
            <form method="POST" style="display:inline;">
                <button type="submit" name="sync_live_data" class="btn-act btn-act-primary" title="Sinkronkan Piutang Unpaid & Laba Berjalan">
                    <i class="fa-solid fa-rotate"></i> Sinkronisasi Data
                </button>
            </form>

            <!-- Export / Print -->
            <button type="button" class="btn-act" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print
            </button>

            <!-- Add Item -->
            <?php if($allowed): ?>
            <button type="button" class="btn-act" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Tambah Akun
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUBMENU SWITCHER (Laba Rugi vs Neraca Keuangan) -->
    <div style="display:inline-flex; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:4px; gap:6px; margin-bottom:24px;">
        <a href="/dashboard/performance/" style="display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:10px; background:transparent; color:#888; font-weight:700; font-size:0.85rem; text-decoration:none;">
            <i class="fa-solid fa-chart-pie"></i> Laba Rugi &amp; Growth
        </a>
        <a href="/dashboard/performance/neraca.php" style="display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:10px; background:linear-gradient(135deg, rgba(161,255,90,0.15), rgba(78,253,196,0.08)); border:1px solid rgba(161,255,90,0.3); color:#a1ff5a; font-weight:700; font-size:0.85rem; text-decoration:none;">
            <i class="fa-solid fa-scale-balanced"></i> Neraca Keuangan
        </a>
    </div>

    <?php if(!empty($msg_success)): ?>
    <div style="background:rgba(161,255,90,.08); border:1px solid rgba(161,255,90,.25); color:var(--neon-main); padding:10px 16px; border-radius:10px; margin-bottom:20px; font-size:.82rem; font-weight:600; display:flex; align-items:center; gap:8px;">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_success) ?>
    </div>
    <?php endif; ?>

    <!-- FILTER BAR (Exact pattern from performance/index.php) -->
    <div class="top-bar">
        <div class="filter-glass">
            <div class="filter-label"><i class="fa-solid fa-calendar-days"></i> TAHUN</div>
            <select class="filter-select" onchange="location.href='?year='+this.value+'&type=<?= $selected_type ?>'">
                <?php for($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
                <option value="<?= $y ?>" <?= ($y === $selected_year) ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <div class="filter-label" style="margin-left:10px;"><i class="fa-solid fa-clock"></i> FORMAT</div>
            <select class="filter-select" onchange="location.href='?year=<?= $selected_year ?>&type='+this.value">
                <option value="annual" <?= ($selected_type === 'annual') ? 'selected' : '' ?>>Tahunan (Annual)</option>
                <option value="q1" <?= ($selected_type === 'q1') ? 'selected' : '' ?>>Kuartal I (Q1)</option>
                <option value="q2" <?= ($selected_type === 'q2') ? 'selected' : '' ?>>Kuartal II (Q2)</option>
                <option value="q3" <?= ($selected_type === 'q3') ? 'selected' : '' ?>>Kuartal III (Q3)</option>
                <option value="q4" <?= ($selected_type === 'q4') ? 'selected' : '' ?>>Kuartal IV (Q4)</option>
            </select>
        </div>

        <div style="font-size:.72rem; color:#444; display:flex; align-items:center; gap:6px;">
            <i class="fa-solid fa-lock"></i> Super Admin Only
        </div>
    </div>

    <!-- 6 KPI STAT CARDS (Exact match with performance/index.php) -->
    <div class="stats-grid">
        <!-- 1. Total Aset -->
        <div class="stat-card">
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-vault"></i></div>
            </div>
            <div class="sc-label">Total Aset (Aktiva)</div>
            <div class="sc-val">Rp <?= number_format($total_assets, 0, ',', '.') ?></div>
            <div class="sc-foot">Lancar: Rp <?= number_format($total_asset_current, 0, ',', '.') ?></div>
        </div>

        <!-- 2. Total Kewajiban -->
        <div class="stat-card">
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            </div>
            <div class="sc-label">Total Kewajiban</div>
            <div class="sc-val">Rp <?= number_format($total_liabilities, 0, ',', '.') ?></div>
            <div class="sc-foot">Lancar: Rp <?= number_format($total_liability_current, 0, ',', '.') ?></div>
        </div>

        <!-- 3. Ekuitas Bersih -->
        <div class="stat-card">
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-landmark"></i></div>
            </div>
            <div class="sc-label">Ekuitas Bersih</div>
            <div class="sc-val">Rp <?= number_format($total_equity, 0, ',', '.') ?></div>
            <div class="sc-foot">Laba Berjalan: Rp <?= number_format($current_net_profit, 0, ',', '.') ?></div>
        </div>

        <!-- 4. Current Ratio -->
        <div class="stat-card">
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-gauge-simple"></i></div>
            </div>
            <div class="sc-label">Current Ratio</div>
            <div class="sc-val"><?= $current_ratio ?>x</div>
            <div class="sc-foot"><span class="trend-up">Likuiditas Aman</span></div>
        </div>

        <!-- 5. Net Working Capital -->
        <div class="stat-card">
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-briefcase"></i></div>
            </div>
            <div class="sc-label">Working Capital</div>
            <div class="sc-val">Rp <?= number_format($net_working_capital, 0, ',', '.') ?></div>
            <div class="sc-foot">Modal Kerja Bersih</div>
        </div>

        <!-- 6. Status Neraca -->
        <div class="stat-card">
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-scale-balanced"></i></div>
            </div>
            <div class="sc-label">Status Neraca</div>
            <div class="sc-val" style="font-size:1.05rem;color:var(--neon-main);">
                <?= $is_balanced ? 'SEIMBANG' : 'SELISIH' ?>
            </div>
            <div class="sc-foot">
                <?= $is_balanced ? 'Aktiva = Pasiva' : 'Selisih Rp ' . number_format($diff_balance, 0, ',', '.') ?>
            </div>
        </div>
    </div>

    <!-- 2-COLUMN BALANCE SHEET TABLE (AKTIVA VS PASIVA) -->
    <div class="balance-sheet-grid">

        <!-- ==========================================
             KOLOM KIRI: AKTIVA (ASET HVM)
             ========================================== -->
        <div class="chart-main">
            <div class="chart-head">
                <div class="sec-label">
                    <i class="fa-solid fa-wallet"></i> AKTIVA (ASET HVM)
                </div>
                <div style="font-family:monospace; font-size:.85rem; color:#fff;">
                    Rp <?= number_format($total_assets, 0, ',', '.') ?>
                </div>
            </div>

            <!-- 1. Aset Lancar -->
            <div class="category-subgroup">
                <div class="category-sub-head">
                    <span>1. Aset Lancar (Current Assets)</span>
                    <span style="font-family:monospace; color:#888;">Rp <?= number_format($total_asset_current, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['asset_current'])): ?>
                    <?php foreach($records['asset_current'] as $acc): ?>
                    <div class="account-line">
                        <div>
                            <div class="acc-title-text">
                                <?= htmlspecialchars($acc['account_name']) ?>
                                <?php if($acc['is_auto']): ?>
                                <span class="acc-tag-pill">Auto</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="acc-nominal-val">Rp <?= number_format($acc['amount'], 0, ',', '.') ?></div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-icon-sm" style="width:26px;height:26px;font-size:.7rem;" onclick='editAccount(<?= json_encode($acc) ?>)' title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a href="?del_account=<?= $acc['id'] ?>&year=<?= $selected_year ?>" class="btn-icon-sm" style="width:26px;height:26px;font-size:.7rem;color:#666;" onclick="return confirm('Hapus akun ini?')" title="Hapus">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="sub-total-bar">
                    <span>Subtotal Aset Lancar</span>
                    <span class="sub-total-val">Rp <?= number_format($total_asset_current, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- 2. Aset Tetap -->
            <div class="category-subgroup">
                <div class="category-sub-head">
                    <span>2. Aset Tetap &amp; Digital (Fixed Assets)</span>
                    <span style="font-family:monospace; color:#888;">Rp <?= number_format($total_asset_fixed, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['asset_fixed'])): ?>
                    <?php foreach($records['asset_fixed'] as $acc): ?>
                    <div class="account-line">
                        <div>
                            <div class="acc-title-text">
                                <?= htmlspecialchars($acc['account_name']) ?>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="acc-nominal-val <?= ($acc['amount'] < 0) ? 'dim' : '' ?>">
                                <?= ($acc['amount'] < 0) ? '(Rp ' . number_format(abs($acc['amount']), 0, ',', '.') . ')' : 'Rp ' . number_format($acc['amount'], 0, ',', '.') ?>
                            </div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-icon-sm" style="width:26px;height:26px;font-size:.7rem;" onclick='editAccount(<?= json_encode($acc) ?>)' title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a href="?del_account=<?= $acc['id'] ?>&year=<?= $selected_year ?>" class="btn-icon-sm" style="width:26px;height:26px;font-size:.7rem;color:#666;" onclick="return confirm('Hapus akun ini?')" title="Hapus">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="sub-total-bar">
                    <span>Subtotal Aset Tetap</span>
                    <span class="sub-total-val">Rp <?= number_format($total_asset_fixed, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- Grand Total Aktiva -->
            <div class="grand-total-box">
                <div class="gt-text">
                    <i class="fa-solid fa-coins"></i> TOTAL AKTIVA (ASET)
                </div>
                <div class="gt-figure">
                    Rp <?= number_format($total_assets, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <!-- ==========================================
             KOLOM KANAN: PASIVA (KEWAJIBAN & EKUITAS)
             ========================================== -->
        <div class="chart-main">
            <div class="chart-head">
                <div class="sec-label">
                    <i class="fa-solid fa-building-columns"></i> PASIVA (KEWAJIBAN &amp; EKUITAS)
                </div>
                <div style="font-family:monospace; font-size:.85rem; color:#fff;">
                    Rp <?= number_format($total_pasiva, 0, ',', '.') ?>
                </div>
            </div>

            <!-- 1. Kewajiban Lancar -->
            <div class="category-subgroup">
                <div class="category-sub-head">
                    <span>1. Kewajiban Lancar (Current Liabilities)</span>
                    <span style="font-family:monospace; color:#888;">Rp <?= number_format($total_liability_current, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['liability_current'])): ?>
                    <?php foreach($records['liability_current'] as $acc): ?>
                    <div class="account-line">
                        <div>
                            <div class="acc-title-text"><?= htmlspecialchars($acc['account_name']) ?></div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="acc-nominal-val">Rp <?= number_format($acc['amount'], 0, ',', '.') ?></div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-icon-sm" style="width:26px;height:26px;font-size:.7rem;" onclick='editAccount(<?= json_encode($acc) ?>)' title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a href="?del_account=<?= $acc['id'] ?>&year=<?= $selected_year ?>" class="btn-icon-sm" style="width:26px;height:26px;font-size:.7rem;color:#666;" onclick="return confirm('Hapus akun ini?')" title="Hapus">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="sub-total-bar">
                    <span>Subtotal Kewajiban Lancar</span>
                    <span class="sub-total-val">Rp <?= number_format($total_liability_current, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- 2. Kewajiban Jangka Panjang -->
            <div class="category-subgroup">
                <div class="category-sub-head">
                    <span>2. Kewajiban Jangka Panjang</span>
                    <span style="font-family:monospace; color:#888;">Rp <?= number_format($total_liability_long, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['liability_long'])): ?>
                    <?php foreach($records['liability_long'] as $acc): ?>
                    <div class="account-line">
                        <div>
                            <div class="acc-title-text"><?= htmlspecialchars($acc['account_name']) ?></div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="acc-nominal-val">Rp <?= number_format($acc['amount'], 0, ',', '.') ?></div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-icon-sm" style="width:26px;height:26px;font-size:.7rem;" onclick='editAccount(<?= json_encode($acc) ?>)'>
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="font-size:.72rem; color:#444; padding:4px 8px;">Bebas utang jangka panjang.</div>
                <?php endif; ?>

                <div class="sub-total-bar">
                    <span>Subtotal Utang Panjang</span>
                    <span class="sub-total-val">Rp <?= number_format($total_liability_long, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- 3. Ekuitas Pemilik -->
            <div class="category-subgroup">
                <div class="category-sub-head">
                    <span>3. Ekuitas Pemilik (Owner's Equity)</span>
                    <span style="font-family:monospace; color:#888;">Rp <?= number_format($total_equity, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['equity'])): ?>
                    <?php foreach($records['equity'] as $acc): ?>
                    <div class="account-line">
                        <div>
                            <div class="acc-title-text">
                                <?= htmlspecialchars($acc['account_name']) ?>
                                <?php if($acc['is_auto']): ?>
                                <span class="acc-tag-pill">Auto</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="acc-nominal-val <?= ($acc['amount'] < 0) ? 'dim' : '' ?>">
                                <?= ($acc['amount'] < 0) ? '(Rp ' . number_format(abs($acc['amount']), 0, ',', '.') . ')' : 'Rp ' . number_format($acc['amount'], 0, ',', '.') ?>
                            </div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-icon-sm" style="width:26px;height:26px;font-size:.7rem;" onclick='editAccount(<?= json_encode($acc) ?>)' title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a href="?del_account=<?= $acc['id'] ?>&year=<?= $selected_year ?>" class="btn-icon-sm" style="width:26px;height:26px;font-size:.7rem;color:#666;" onclick="return confirm('Hapus akun ini?')" title="Hapus">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="sub-total-bar">
                    <span>Subtotal Ekuitas Bersih</span>
                    <span class="sub-total-val">Rp <?= number_format($total_equity, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- Grand Total Pasiva -->
            <div class="grand-total-box">
                <div class="gt-text">
                    <i class="fa-solid fa-scale-balanced"></i> TOTAL PASIVA (UTANG + MODAL)
                </div>
                <div class="gt-figure">
                    Rp <?= number_format($total_pasiva, 0, ',', '.') ?>
                </div>
            </div>
        </div>

    </div>

    <!-- RASIO KESEHATAN KEUANGAN -->
    <div class="ratio-analysis-bar">
        <div class="sec-label" style="margin-bottom:12px;">
            <i class="fa-solid fa-chart-simple"></i> RASIO &amp; KELAYAKAN FINANSIAL
        </div>

        <div class="ratio-row">
            <div class="ratio-item">
                <div class="ri-label">Current Ratio</div>
                <div class="ri-val"><?= $current_ratio ?>x</div>
                <div class="ri-bar">
                    <div class="ri-fill" style="width:<?= min(100, $current_ratio * 35) ?>%;"></div>
                </div>
                <div class="ri-desc">Likuiditas pelunasan jangka pendek. Standar aman &ge; 1.5x.</div>
            </div>

            <div class="ratio-item">
                <div class="ri-label">Debt to Equity (DER)</div>
                <div class="ri-val"><?= $debt_to_equity ?>x</div>
                <div class="ri-bar">
                    <div class="ri-fill" style="width:<?= min(100, $debt_to_equity * 50) ?>%;"></div>
                </div>
                <div class="ri-desc">Porsi utang dibanding modal. Standar &le; 0.5x sangat mandiri.</div>
            </div>

            <div class="ratio-item">
                <div class="ri-label">Working Capital</div>
                <div class="ri-val" style="font-size:1.15rem;">Rp <?= number_format($net_working_capital, 0, ',', '.') ?></div>
                <div class="ri-bar">
                    <div class="ri-fill" style="width:80%;"></div>
                </div>
                <div class="ri-desc">Cadangan modal kerja riil (Aset Lancar &minus; Utang Lancar).</div>
            </div>

            <div class="ratio-item">
                <div class="ri-label">Status Neraca</div>
                <div class="ri-val" style="font-size:1.05rem;color:var(--neon-main);">
                    <?= $is_balanced ? 'SEIMBANG' : 'SELISIH' ?>
                </div>
                <div class="ri-bar">
                    <div class="ri-fill" style="width:100%;"></div>
                </div>
                <div class="ri-desc"><?= $is_balanced ? 'Aktiva sama dengan Pasiva.' : 'Periksa rekonsiliasi akun.' ?></div>
            </div>
        </div>
    </div>

    <!-- PANDUAN RINGKAS -->
    <div class="guide-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <div class="sec-label">
                <i class="fa-solid fa-book-open"></i> PANDUAN EKSEKUTIF NERACA
            </div>
            <a href="/dashboard/performance/download_panduan.php" class="btn-act" target="_blank" style="padding:6px 14px;font-size:.72rem;">
                <i class="fa-solid fa-file-pdf"></i> Unduh PDF
            </a>
        </div>

        <div class="acc-guide-item active">
            <div class="acc-guide-head" onclick="toggleGuide(this)">
                <span>1. Mengapa Agensi Digital Wajib Punya Neraca?</span>
                <i class="fa-solid fa-chevron-down arrow"></i>
            </div>
            <div class="acc-guide-body">
                Laporan laba rugi bulanan hanya memperlihatkan omzet masuk dan biaya keluar. Namun pada agensi digital, klien sering membayar di awal (retainer 3-6 bulan). Tanpa neraca, uang tersebut terlihat seperti laba bebas, padahal sebagian adalah kewajiban jasa masa depan. Neraca memisahkan kas riil milik perusahaan dari beban masa depan.
            </div>
        </div>

        <div class="acc-guide-item">
            <div class="acc-guide-head" onclick="toggleGuide(this)">
                <span>2. Memahami Rumus: Aset = Kewajiban + Ekuitas</span>
                <i class="fa-solid fa-chevron-down arrow"></i>
            </div>
            <div class="acc-guide-body">
                Semua kekayaan perusahaan (Aset) didanai oleh dua sumber: Utang pihak luar (Kewajiban) atau Modal pendiri (Ekuitas). Aset Lancar mencakup kas bank dan piutang invoice klien. Aset Tetap mencakup workstation dan lisensi digital. Kewajiban mencakup utang vendor dan DP klien. Ekuitas adalah modal disetor dan akumulasi laba ditahan.
            </div>
        </div>

        <div class="acc-guide-item">
            <div class="acc-guide-head" onclick="toggleGuide(this)">
                <span>3. SOP Sinkronisasi &amp; Rekonsiliasi Rutin</span>
                <i class="fa-solid fa-chevron-down arrow"></i>
            </div>
            <div class="acc-guide-body">
                1. Setiap akhir bulan, Super Admin menekan tombol <strong>Sinkronisasi Data</strong> untuk mengagregasi piutang invoice otomatis.<br>
                2. Cocokkan saldo rekening bank resmi (BCA/Mandiri) melalui tombol edit akun pada baris Kas &amp; Bank.<br>
                3. Pastikan indikator status neraca menunjukkan <strong>SEIMBANG</strong>.<br>
                4. Klik tombol <strong>Panduan PDF</strong> atau Print untuk mengarsipkan laporan.
            </div>
        </div>
    </div>

</main>
</div>

<!-- MODAL FORM EDIT / TAMBAH AKUN -->
<div class="modal-overlay" id="accModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">
                <i class="fa-solid fa-sliders" style="color:var(--neon-main)"></i>
                <span id="modalHeaderTitle">Kelola Akun Neraca</span>
            </div>
            <button type="button" class="modal-close" onclick="closeAccModal()">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="period_year" value="<?= $selected_year ?>">
            <input type="hidden" name="period_type" value="<?= $selected_type ?>">

            <div class="form-group">
                <label>Kode Akun</label>
                <input type="text" name="account_code" id="modal_code" class="form-input" required placeholder="cth: kas_bank, piutang_usaha">
            </div>

            <div class="form-group">
                <label>Nama Akun Neraca</label>
                <input type="text" name="account_name" id="modal_name" class="form-input" required placeholder="cth: Kas Rekening Operasional BCA">
            </div>

            <div class="form-group">
                <label>Kategori</label>
                <select name="account_category" id="modal_cat" class="form-input" required>
                    <option value="asset_current">Aset Lancar (Current Asset)</option>
                    <option value="asset_fixed">Aset Tetap (Fixed Asset)</option>
                    <option value="liability_current">Kewajiban Lancar (Current Liability)</option>
                    <option value="liability_long">Kewajiban Jangka Panjang (Long-Term)</option>
                    <option value="equity">Ekuitas Pemilik (Owner's Equity)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Nominal Saldo (Rp)</label>
                <input type="number" step="any" name="amount" id="modal_amount" class="form-input" required placeholder="75000000">
            </div>

            <div class="form-group">
                <label>Catatan</label>
                <textarea name="notes" id="modal_notes" class="form-input" style="min-height:60px;" placeholder="Keterangan tambahan..."></textarea>
            </div>

            <button type="submit" name="save_account_item" class="btn-submit-modal">
                <i class="fa-solid fa-floppy-disk"></i> Simpan Akun
            </button>
        </form>
    </div>
</div>

<script>
function toggleGuide(head) {
    head.parentElement.classList.toggle('active');
}

function editAccount(acc) {
    document.getElementById('modalHeaderTitle').textContent = 'Edit: ' + acc.account_name;
    document.getElementById('modal_code').value = acc.account_code;
    document.getElementById('modal_name').value = acc.account_name;
    document.getElementById('modal_cat').value = acc.account_category;
    document.getElementById('modal_amount').value = acc.amount;
    document.getElementById('modal_notes').value = acc.notes || '';
    document.getElementById('accModal').classList.add('active');
}

function openAddModal() {
    document.getElementById('modalHeaderTitle').textContent = 'Tambah Akun Neraca';
    document.getElementById('modal_code').value = '';
    document.getElementById('modal_name').value = '';
    document.getElementById('modal_cat').value = 'asset_current';
    document.getElementById('modal_amount').value = '';
    document.getElementById('modal_notes').value = '';
    document.getElementById('accModal').classList.add('active');
}

function closeAccModal() {
    document.getElementById('accModal').classList.remove('active');
}

window.addEventListener('click', e => {
    const modal = document.getElementById('accModal');
    if (e.target === modal) closeAccModal();
});
</script>
</body>
</html>
