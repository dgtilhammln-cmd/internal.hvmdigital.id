<?php
// ============================================================
// HVM FINANCIAL VAULT — NERACA KEUANGAN (BALANCE SHEET) v1.0
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
    // 1. Sync Piutang Usaha
    mysqli_query($conn, "INSERT INTO balance_sheet_records (period_year, period_type, account_code, account_name, account_category, amount, notes, is_auto)
        VALUES ($selected_year, '$selected_type', 'piutang_usaha', 'Piutang Usaha Klien (Unpaid Invoices)', 'asset_current', $unpaid_invoices_total, 'Sinkronisasi otomatis dari tagihan Invoice berstatus Pending/Overdue', 1)
        ON DUPLICATE KEY UPDATE amount=$unpaid_invoices_total, is_auto=1");
    
    // 2. Sync Laba Bersih Berjalan
    mysqli_query($conn, "INSERT INTO balance_sheet_records (period_year, period_type, account_code, account_name, account_category, amount, notes, is_auto)
        VALUES ($selected_year, '$selected_type', 'laba_berjalan', 'Laba Bersih Tahun Berjalan (Net Income)', 'equity', $current_net_profit, 'Sinkronisasi otomatis dari omzet pembayaran - pengeluaran tahun $selected_year', 1)
        ON DUPLICATE KEY UPDATE amount=$current_net_profit, is_auto=1");

    $msg_success = "Data piutang (Rp " . number_format($unpaid_invoices_total, 0, ',', '.') . ") dan laba berjalan (Rp " . number_format($current_net_profit, 0, ',', '.') . ") berhasil disinkronkan!";
}

// Check if default records exist for this period, if empty, seed baseline accounts
$q_check = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM balance_sheet_records WHERE period_year = $selected_year AND period_type = '$selected_type'");
$count_records = (int)mysqli_fetch_assoc($q_check)['cnt'];

if ($count_records === 0 && $allowed) {
    $baseline_accounts = [
        // ASET LANCAR
        ['kas_bank', 'Kas & Rekening Operasional (BCA/Mandiri)', 'asset_current', 75000000, 'Saldo likuid pada rekening bank perusahaan'],
        ['piutang_usaha', 'Piutang Usaha Klien (Unpaid Invoices)', 'asset_current', $unpaid_invoices_total, 'Tagihan invoice yang belum terbayar oleh klien'],
        ['biaya_dimuka', 'Biaya Dibayar di Muka (Prepaid Server & Tools)', 'asset_current', 12500000, 'Sewa hosting, lisensi software, & tools SEO tahunan'],
        ['kas_kecil', 'Kas Kecil / Petty Cash Operasional', 'asset_current', 5000000, 'Kas darurat operasional kantor HVM'],
        
        // ASET TETAP
        ['peralatan_kantor', 'Perangkat Kerja (MacBook, PC Editing & Studio)', 'asset_fixed', 65000000, 'Nilai perolehan laptop, workstation tim, dan kamera'],
        ['depresiasi', 'Akumulasi Penyusutan Aset Tetap (-)', 'asset_fixed', -15000000, 'Penyusutan nilai perangkat kerja akibat pemakaian'],
        ['aset_digital', 'Aset Digital & Lisensi Lifetime', 'asset_fixed', 25000000, 'Portofolio domain, website resmi, dan lisensi plugin'],

        // KEWAJIBAN LANCAR
        ['utang_usaha', 'Utang Vendor & Freelancer', 'asset_current' => 'liability_current', 8500000, 'Kewajiban pembayaran ke freelancer / vendor luar'],
        ['pendapatan_dimuka', 'Pendapatan Diterima di Muka (Unearned Retainer)', 'liability_current', 22000000, 'DP & kontrak berjalan klien yang jasanya belum selesai penuh'],
        ['utang_pajak', 'Estimasi Utang Pajak Usaha (PPh/PPN)', 'liability_current', 4500000, 'Pajak penghasilan dan PPN yang belum disetorkan'],
        ['beban_akrual', 'Beban Akrual (Gaji & Listrik Berjalan)', 'liability_current', 7500000, 'Beban operasional yang jatuh tempo di akhir periode'],

        // KEWAJIBAN JANGKA PANJANG
        ['utang_investasi', 'Pinjaman Modal Kerja / Perbankan', 'liability_long', 0, 'Kewajiban jangka panjang dengan tenor > 1 tahun'],

        // EKUITAS
        ['modal_disetor', 'Modal Awal Disetor Pemilik (Founder Capital)', 'equity', 50000000, 'Penyertaan modal awal pendiri HVM Digital'],
        ['laba_ditahan', 'Laba Ditahan Periode Lalu (Retained Earnings)', 'equity', 45000000, 'Akumulasi keuntungan tahun-tahun sebelumnya yang disimpan'],
        ['laba_berjalan', 'Laba Bersih Tahun Berjalan (Net Income)', 'equity', $current_net_profit, 'Keuntungan bersih tahun berjalan dari modul Finance'],
        ['prive', 'Prive / Penarikan Modal Pemilik (-)', 'equity', 0, 'Penarikan dana oleh founder untuk kepentingan di luar bisnis']
    ];

    foreach ($baseline_accounts as $acc) {
        $c_cat = is_array($acc) ? ($acc[2] ?? 'asset_current') : 'asset_current';
        if ($acc[0] === 'utang_usaha') $c_cat = 'liability_current';
        $sql_seed = "INSERT IGNORE INTO balance_sheet_records (period_year, period_type, account_code, account_name, account_category, amount, notes)
                     VALUES ($selected_year, '$selected_type', '{$acc[0]}', '{$acc[1]}', '{$c_cat}', {$acc[3]}, '{$acc[4]}')";
        mysqli_query($conn, $sql_seed);
    }
}

// Fetch all balance sheet records for current view
$records = [];
$q_recs = mysqli_query($conn, "SELECT * FROM balance_sheet_records WHERE period_year = $selected_year AND period_type = '$selected_type' ORDER BY id ASC");
if ($q_recs) {
    while ($r = mysqli_fetch_assoc($q_recs)) {
        $records[$r['account_category']][] = $r;
    }
}

// Calculate Group Totals
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
$is_balanced = ($diff_balance < 1); // Toleransi pembulatan

// Financial Ratios
$current_ratio = ($total_liability_current > 0) ? round($total_asset_current / $total_liability_current, 2) : 0;
$debt_to_equity = ($total_equity > 0) ? round($total_liabilities / $total_equity, 2) : 0;
$net_working_capital = $total_asset_current - $total_liability_current;
$kas_val = 0;
if (!empty($records['asset_current'])) {
    foreach ($records['asset_current'] as $it) {
        if ($it['account_code'] === 'kas_bank' || $it['account_code'] === 'kas_kecil') $kas_val += (float)$it['amount'];
    }
}
$cash_ratio = ($total_liability_current > 0) ? round($kas_val / $total_liability_current, 2) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Neraca Keuangan — HVM Digital</title>
<link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">

<!-- Font Awesome Vector Icons (NO EMOJIS) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<!-- Sidebar style -->
<link rel="stylesheet" href="/dashboard/sidebar.style.css">

<style>
:root {
    --bg: #050505;
    --card-bg: rgba(18, 18, 18, 0.75);
    --card-border: rgba(255, 255, 255, 0.08);
    --card-hover: rgba(255, 255, 255, 0.16);
    --neon-main: #a1ff5a;
    --neon-sec: #4efdc4;
    --neon-red: #ff5a5a;
    --neon-orange: #ff9f43;
    --text-muted: #888;
    --sidebar-w: 260px;
}

*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Montserrat', sans-serif; }
body { background: var(--bg); color: #fff; min-height: 100vh; overflow-x: hidden; display: flex; }

.main-content {
    margin-left: var(--sidebar-w);
    padding: 36px 40px;
    width: calc(100% - var(--sidebar-w));
    min-height: 100vh;
}
@media(max-width: 768px) {
    .main-content { margin-left: 0; width: 100%; padding: 20px 18px 120px; }
}

/* Ambient glow */
.ambient-glow { position: fixed; border-radius: 50%; filter: blur(140px); opacity: .07; z-index: 0; pointer-events: none; }
.glow-1 { top: -150px; left: -150px; width: 700px; height: 700px; background: var(--neon-main); }
.glow-2 { bottom: -150px; right: -150px; width: 700px; height: 700px; background: var(--neon-sec); }

/* Headline */
.page-headline { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; gap: 20px; flex-wrap: wrap; }
.hl-tag { display: inline-flex; align-items: center; gap: 8px; font-size: .62rem; font-weight: 800; letter-spacing: 2.5px; color: var(--neon-main); text-transform: uppercase; background: rgba(161,255,90,.07); border: 1px solid rgba(161,255,90,.2); padding: 6px 14px; border-radius: 50px; margin-bottom: 10px; }
.page-headline h1 { font-size: 2.1rem; font-weight: 900; line-height: 1.15; margin-bottom: 6px; background: linear-gradient(180deg, #fff 40%, #555); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.grad-text { background: linear-gradient(135deg, var(--neon-main), var(--neon-sec)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.page-headline p { color: var(--text-muted); font-size: .86rem; max-width: 650px; line-height: 1.5; }

/* Action Buttons */
.hl-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-act { 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    background: rgba(255,255,255,.04); 
    border: 1px solid var(--card-border); 
    color: #bbb; 
    padding: 10px 18px; 
    border-radius: 50px; 
    font-size: .78rem; 
    font-weight: 700; 
    cursor: pointer; 
    transition: all .25s ease; 
    font-family: inherit; 
    text-decoration: none;
}
.btn-act:hover { color: #fff; border-color: rgba(255,255,255,.3); background: rgba(255,255,255,.08); transform: translateY(-2px); }
.btn-act-primary { background: rgba(161,255,90,.12); border-color: rgba(161,255,90,.35); color: var(--neon-main); }
.btn-act-primary:hover { background: rgba(161,255,90,.22); border-color: var(--neon-main); box-shadow: 0 0 25px rgba(161,255,90,.25); }
.btn-act-pdf { background: rgba(255,90,90,.1); border-color: rgba(255,90,90,.3); color: #ff8585; }
.btn-act-pdf:hover { background: rgba(255,90,90,.2); border-color: var(--neon-red); box-shadow: 0 0 20px rgba(255,90,90,.2); color: #fff; }

/* Submenu Switcher */
.sub-nav-tabs {
    display: inline-flex;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--card-border);
    border-radius: 14px;
    padding: 4px;
    gap: 6px;
    margin-bottom: 24px;
}
.sub-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s ease;
    color: #888;
}
.sub-tab-btn.active {
    background: linear-gradient(135deg, rgba(161,255,90,0.16), rgba(78,253,196,0.08));
    border: 1px solid rgba(161,255,90,0.3);
    color: var(--neon-main);
    box-shadow: 0 4px 15px rgba(161,255,90,0.1);
}
.sub-tab-btn:hover:not(.active) { color: #fff; background: rgba(255,255,255,0.05); }

/* Filter glass bar */
.filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(16,16,16,0.8);
    backdrop-filter: blur(20px);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    padding: 14px 22px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.filter-group { display: flex; align-items: center; gap: 12px; }
.filter-label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: #666; display: flex; align-items: center; gap: 6px; }
.filter-select {
    background: rgba(0,0,0,0.5);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 9px;
    color: #eee;
    padding: 8px 30px 8px 14px;
    font-family: inherit;
    font-size: 0.82rem;
    font-weight: 600;
    outline: none;
    cursor: pointer;
    transition: 0.25s;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23666'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
}
.filter-select:focus { border-color: var(--neon-main); }
.filter-select option { background: #111; color: #fff; }

/* Interactive Balance Check Banner */
.balance-banner {
    border-radius: 16px;
    padding: 18px 24px;
    margin-bottom: 26px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    backdrop-filter: blur(20px);
    transition: all 0.3s ease;
}
.balance-banner.balanced {
    background: linear-gradient(135deg, rgba(161,255,90,0.08), rgba(78,253,196,0.04));
    border: 1px solid rgba(161,255,90,0.3);
    box-shadow: 0 0 30px rgba(161,255,90,0.06);
}
.balance-banner.unbalanced {
    background: linear-gradient(135deg, rgba(255,159,67,0.1), rgba(255,90,90,0.05));
    border: 1px solid rgba(255,159,67,0.35);
    box-shadow: 0 0 30px rgba(255,159,67,0.06);
}
.bb-left { display: flex; align-items: center; gap: 16px; }
.bb-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.balanced .bb-icon { background: rgba(161,255,90,0.15); color: var(--neon-main); border: 1px solid rgba(161,255,90,0.3); }
.unbalanced .bb-icon { background: rgba(255,159,67,0.15); color: var(--neon-orange); border: 1px solid rgba(255,159,67,0.3); }
.bb-text h3 { font-size: 1rem; font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
.bb-text p { font-size: 0.78rem; color: #888; }
.bb-eq { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 800; font-family: monospace; }
.bb-pill { background: rgba(255,255,255,0.06); padding: 5px 12px; border-radius: 8px; border: 1px solid var(--card-border); }

/* 4 Metrics KPI Grid */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
@media(max-width: 1024px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 600px) { .kpi-grid { grid-template-columns: 1fr; } }

.kpi-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 18px;
    padding: 22px;
    backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
    transition: transform 0.25s, border-color 0.25s;
}
.kpi-card:hover { transform: translateY(-3px); border-color: var(--card-hover); }
.kpi-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; }
.kpi-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
}
.kpi-badge { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.5px; }
.kpi-title { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: #666; margin-bottom: 8px; }
.kpi-val { font-size: 1.45rem; font-weight: 900; line-height: 1; margin-bottom: 8px; }
.kpi-sub { font-size: 0.72rem; color: #555; display: flex; align-items: center; gap: 6px; }

/* 2-Column Balance Sheet Layout (Aktiva vs Pasiva) */
.balance-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}
@media(max-width: 950px) { .balance-grid { grid-template-columns: 1fr; } }

.section-sheet {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 26px;
    backdrop-filter: blur(20px);
    display: flex;
    flex-direction: column;
}
.sheet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 16px;
    margin-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sheet-title {
    font-size: 0.95rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sheet-title i { font-size: 1.15rem; color: var(--neon-main); }
.sheet-total-badge {
    font-size: 0.92rem;
    font-weight: 800;
    font-family: monospace;
    color: #fff;
    background: rgba(255,255,255,0.06);
    padding: 4px 12px;
    border-radius: 8px;
    border: 1px solid var(--card-border);
}

/* Category Block */
.category-block {
    background: rgba(255,255,255,0.015);
    border: 1px solid rgba(255,255,255,0.04);
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 18px;
}
.cat-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #999;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px dashed rgba(255,255,255,0.06);
}
.cat-heading span { display: flex; align-items: center; gap: 7px; }

/* Table Item Rows */
.account-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 10px;
    border-radius: 8px;
    transition: background 0.2s;
    font-size: 0.82rem;
}
.account-row:hover { background: rgba(255,255,255,0.035); }
.acc-info { display: flex; flex-direction: column; gap: 3px; }
.acc-name { font-weight: 600; color: #ddd; display: flex; align-items: center; gap: 8px; }
.acc-note { font-size: 0.68rem; color: #555; }
.acc-auto-tag {
    font-size: 0.58rem;
    padding: 1px 6px;
    border-radius: 4px;
    background: rgba(78,253,196,0.1);
    color: var(--neon-sec);
    border: 1px solid rgba(78,253,196,0.2);
    font-weight: 700;
}
.acc-val-wrap { display: flex; align-items: center; gap: 10px; }
.acc-val { font-weight: 700; font-family: monospace; font-size: 0.88rem; color: #fff; }
.acc-val.negative { color: var(--neon-red); }
.btn-edit-acc {
    background: transparent;
    border: none;
    color: #555;
    cursor: pointer;
    font-size: 0.75rem;
    padding: 4px;
    border-radius: 4px;
    transition: 0.2s;
}
.btn-edit-acc:hover { color: var(--neon-main); background: rgba(255,255,255,0.06); }

/* Subtotal Row */
.subtotal-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 10px 2px;
    margin-top: 8px;
    border-top: 1px solid rgba(255,255,255,0.06);
    font-size: 0.76rem;
    font-weight: 800;
    color: #888;
    text-transform: uppercase;
}
.subtotal-val { font-family: monospace; font-size: 0.88rem; color: #ddd; }

/* Grand Total Final Row */
.grand-total-row {
    margin-top: auto;
    background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.gt-label { font-size: 0.85rem; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
.gt-val { font-size: 1.25rem; font-weight: 900; font-family: monospace; }
.gt-val.highlight-green { color: var(--neon-main); text-shadow: 0 0 15px rgba(161,255,90,0.3); }
.gt-val.highlight-cyan { color: var(--neon-sec); text-shadow: 0 0 15px rgba(78,253,196,0.3); }

/* Ratio & Analysis Section */
.analysis-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 26px;
    margin-bottom: 30px;
    backdrop-filter: blur(20px);
}
.ratio-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-top: 20px;
}
@media(max-width: 900px) { .ratio-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 550px) { .ratio-grid { grid-template-columns: 1fr; } }

.ratio-box {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 14px;
    padding: 18px;
    transition: 0.25s;
}
.ratio-box:hover { border-color: rgba(255,255,255,0.15); background: rgba(255,255,255,0.035); }
.rb-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.rb-title { font-size: 0.72rem; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: 0.5px; }
.rb-score { font-size: 1.4rem; font-weight: 900; color: #fff; margin-bottom: 6px; font-family: monospace; }
.rb-bar-wrap { width: 100%; height: 6px; background: rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden; margin-bottom: 8px; }
.rb-bar { height: 100%; border-radius: 10px; }
.rb-desc { font-size: 0.68rem; color: #666; line-height: 1.4; }

/* Interactive Guide Accordion (No Emojis, clean icons) */
.guide-section {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 26px;
    backdrop-filter: blur(20px);
    margin-bottom: 40px;
}
.accordion-item {
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 12px;
    margin-bottom: 10px;
    overflow: hidden;
    background: rgba(255,255,255,0.015);
    transition: all 0.25s;
}
.accordion-item:last-child { margin-bottom: 0; }
.accordion-header {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
    font-size: 0.88rem;
    font-weight: 700;
    color: #ccc;
    transition: 0.2s;
}
.accordion-header:hover { color: #fff; background: rgba(255,255,255,0.03); }
.accordion-header i.acc-chevron { transition: transform 0.25s ease; color: #666; font-size: 0.8rem; }
.accordion-item.active .accordion-header i.acc-chevron { transform: rotate(180deg); color: var(--neon-main); }
.accordion-item.active { border-color: rgba(161,255,90,0.25); background: rgba(161,255,90,0.02); }
.accordion-content {
    display: none;
    padding: 0 20px 20px;
    font-size: 0.82rem;
    color: #888;
    line-height: 1.7;
    border-top: 1px solid rgba(255,255,255,0.04);
    padding-top: 16px;
}
.accordion-item.active .accordion-content { display: block; animation: fadeIn 0.3s; }

/* Modal Editor */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(8px);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; animation: fadeIn 0.2s ease; }
.modal-box {
    background: #111;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 20px;
    width: 100%;
    max-width: 520px;
    padding: 28px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.8);
    position: relative;
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.modal-title { font-size: 1.05rem; font-weight: 800; display: flex; align-items: center; gap: 10px; color: #fff; }
.modal-close { background: transparent; border: none; color: #777; font-size: 1.2rem; cursor: pointer; transition: 0.2s; }
.modal-close:hover { color: #fff; }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.72rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.form-input {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 11px 14px;
    color: #fff;
    font-size: 0.88rem;
    font-family: inherit;
    outline: none;
    transition: 0.2s;
}
.form-input:focus { border-color: var(--neon-main); background: rgba(255,255,255,0.06); }
.form-input option { background: #161616; color: #fff; }
.btn-save-acc {
    width: 100%;
    padding: 13px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, var(--neon-main), var(--neon-sec));
    color: #000;
    font-size: 0.88rem;
    font-weight: 800;
    cursor: pointer;
    transition: 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-family: inherit;
    margin-top: 10px;
}
.btn-save-acc:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(161,255,90,0.3); }

/* Print styling for PDF export */
@media print {
    .sidebar, .ambient-glow, .hl-actions, .filter-bar, .sub-nav-tabs, .btn-edit-acc, .guide-section { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
    body { background: #fff !important; color: #000 !important; }
    .section-sheet, .kpi-card, .balance-banner, .analysis-card { background: #fff !important; border: 1px solid #ccc !important; color: #000 !important; }
    .acc-name, .kpi-val, .sheet-title, .gt-label, .gt-val { color: #000 !important; text-shadow: none !important; }
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
</head>
<body>

<div class="ambient-glow glow-1"></div>
<div class="ambient-glow glow-2"></div>

<?php include '../sidebar.php'; ?>

<main class="main-content">

    <!-- PAGE HEADLINE -->
    <div class="page-headline">
        <div>
            <div class="hl-tag">
                <i class="fa-solid fa-vault"></i> HVM FINANCIAL VAULT • NERACA KEUANGAN
            </div>
            <h1>Posisi Neraca <span class="grad-text">Perusahaan</span></h1>
            <p>Laporan komprehensif posisi Aset (Aktiva), Kewajiban, dan Ekuitas HVM Digital untuk memastikan stabilitas dan solvabilitas bisnis.</p>
        </div>

        <div class="hl-actions">
            <!-- Download Panduan PDF Button -->
            <a href="/dashboard/performance/download_panduan.php" class="btn-act btn-act-pdf" target="_blank" title="Unduh Buku Panduan Resmi Neraca Keuangan HVM (PDF)">
                <i class="fa-solid fa-file-pdf"></i> Download Panduan (PDF)
            </a>

            <!-- Sync Live Data -->
            <form method="POST" style="display:inline;">
                <button type="submit" name="sync_live_data" class="btn-act btn-act-primary" title="Sinkronkan Piutang Unpaid & Laba Berjalan">
                    <i class="fa-solid fa-rotate"></i> Sinkronisasi Data
                </button>
            </form>

            <!-- Export / Print -->
            <button type="button" class="btn-act" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print / Cetak
            </button>

            <!-- Add / Edit Item -->
            <?php if($allowed): ?>
            <button type="button" class="btn-act" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Tambah Akun
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUBMENU NAVIGATION (Seragam dengan Performance) -->
    <div class="sub-nav-tabs">
        <a href="/dashboard/performance/" class="sub-tab-btn">
            <i class="fa-solid fa-chart-pie"></i> Laba Rugi &amp; Growth
        </a>
        <a href="/dashboard/performance/neraca.php" class="sub-tab-btn active">
            <i class="fa-solid fa-scale-balanced"></i> Neraca Keuangan (Balance Sheet)
        </a>
    </div>

    <?php if(!empty($msg_success)): ?>
    <div style="background:rgba(161,255,90,0.1); border:1px solid rgba(161,255,90,0.3); color:var(--neon-main); padding:12px 18px; border-radius:12px; margin-bottom:20px; font-size:0.85rem; font-weight:600; display:flex; align-items:center; gap:8px;">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_success) ?>
    </div>
    <?php endif; ?>

    <!-- FILTER PERIODE -->
    <div class="filter-bar">
        <div class="filter-group">
            <label class="filter-label"><i class="fa-solid fa-calendar-days"></i> Periode Tahun</label>
            <select class="filter-select" onchange="location.href='?year='+this.value+'&type=<?= $selected_type ?>'">
                <?php for($y = (int)date('Y') + 1; $y >= 2024; $y--): ?>
                <option value="<?= $y ?>" <?= ($y === $selected_year) ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <label class="filter-label" style="margin-left:8px;"><i class="fa-solid fa-clock"></i> Format</label>
            <select class="filter-select" onchange="location.href='?year=<?= $selected_year ?>&type='+this.value">
                <option value="annual" <?= ($selected_type === 'annual') ? 'selected' : '' ?>>Tahunan (Annual Closing)</option>
                <option value="q1" <?= ($selected_type === 'q1') ? 'selected' : '' ?>>Kuartal I (Q1)</option>
                <option value="q2" <?= ($selected_type === 'q2') ? 'selected' : '' ?>>Kuartal II (Q2)</option>
                <option value="q3" <?= ($selected_type === 'q3') ? 'selected' : '' ?>>Kuartal III (Q3)</option>
                <option value="q4" <?= ($selected_type === 'q4') ? 'selected' : '' ?>>Kuartal IV (Q4)</option>
            </select>
        </div>

        <div style="font-size:0.75rem; color:#666; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-shield-halved" style="color:var(--neon-sec)"></i> Akses Terbatas Super Admin
        </div>
    </div>

    <!-- INTERACTIVE BALANCE CHECK BANNER -->
    <div class="balance-banner <?= ($is_balanced) ? 'balanced' : 'unbalanced' ?>">
        <div class="bb-left">
            <div class="bb-icon">
                <?php if($is_balanced): ?>
                <i class="fa-solid fa-scale-balanced"></i>
                <?php else: ?>
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?php endif; ?>
            </div>
            <div class="bb-text">
                <h3>
                    <?php if($is_balanced): ?>
                    <i class="fa-solid fa-circle-check" style="color:var(--neon-main);font-size:0.9rem;"></i> Neraca Seimbang (Balanced)
                    <?php else: ?>
                    <i class="fa-solid fa-circle-exclamation" style="color:var(--neon-orange);font-size:0.9rem;"></i> Terdapat Selisih Neraca: Rp <?= number_format($diff_balance, 0, ',', '.') ?>
                    <?php endif; ?>
                </h3>
                <p>
                    <?php if($is_balanced): ?>
                    Prinsip fundamental akuntansi terpenuhi: Total Aktiva (Aset) sama persis dengan Total Pasiva (Kewajiban + Ekuitas).
                    <?php else: ?>
                    Periksa kembali penyesuaian modal disetor, penarikan prive, atau pencatatan utang berjalan untuk merekonsiliasi selisih.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="bb-eq">
            <div class="bb-pill" title="Total Aset"><i class="fa-solid fa-coins" style="color:var(--neon-main)"></i> Aset: Rp <?= number_format($total_assets, 0, ',', '.') ?></div>
            <span>=</span>
            <div class="bb-pill" title="Total Kewajiban"><i class="fa-solid fa-hand-holding-dollar" style="color:var(--neon-red)"></i> Utang: Rp <?= number_format($total_liabilities, 0, ',', '.') ?></div>
            <span>+</span>
            <div class="bb-pill" title="Total Ekuitas"><i class="fa-solid fa-landmark" style="color:var(--neon-sec)"></i> Ekuitas: Rp <?= number_format($total_equity, 0, ',', '.') ?></div>
        </div>
    </div>

    <!-- 4 KEY KPI CARDS -->
    <div class="kpi-grid">
        <!-- Total Aset -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:rgba(161,255,90,0.12); color:var(--neon-main); border:1px solid rgba(161,255,90,0.25);">
                    <i class="fa-solid fa-vault"></i>
                </div>
                <span class="kpi-badge" style="background:rgba(161,255,90,0.1); color:var(--neon-main);">Total Aktiva</span>
            </div>
            <div class="kpi-title">Total Aset HVM</div>
            <div class="kpi-val" style="color:var(--neon-main);">Rp <?= number_format($total_assets, 0, ',', '.') ?></div>
            <div class="kpi-sub"><i class="fa-solid fa-layer-group"></i> Lancar: Rp <?= number_format($total_asset_current, 0, ',', '.') ?></div>
        </div>

        <!-- Total Kewajiban -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:rgba(255,90,90,0.12); color:var(--neon-red); border:1px solid rgba(255,90,90,0.25);">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <span class="kpi-badge" style="background:rgba(255,90,90,0.1); color:var(--neon-red);">Liabilitas</span>
            </div>
            <div class="kpi-title">Total Kewajiban (Utang)</div>
            <div class="kpi-val" style="color:#ff8585;">Rp <?= number_format($total_liabilities, 0, ',', '.') ?></div>
            <div class="kpi-sub"><i class="fa-solid fa-clock-rotate-left"></i> Jangka Pendek: Rp <?= number_format($total_liability_current, 0, ',', '.') ?></div>
        </div>

        <!-- Total Ekuitas -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:rgba(78,253,196,0.12); color:var(--neon-sec); border:1px solid rgba(78,253,196,0.25);">
                    <i class="fa-solid fa-landmark"></i>
                </div>
                <span class="kpi-badge" style="background:rgba(78,253,196,0.1); color:var(--neon-sec);">Net Worth</span>
            </div>
            <div class="kpi-title">Ekuitas Bersih (Modal)</div>
            <div class="kpi-val" style="color:var(--neon-sec);">Rp <?= number_format($total_equity, 0, ',', '.') ?></div>
            <div class="kpi-sub"><i class="fa-solid fa-chart-line"></i> Laba Berjalan: Rp <?= number_format($current_net_profit, 0, ',', '.') ?></div>
        </div>

        <!-- Current Ratio -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-icon" style="background:rgba(255,159,67,0.12); color:var(--neon-orange); border:1px solid rgba(255,159,67,0.25);">
                    <i class="fa-solid fa-gauge-high"></i>
                </div>
                <span class="kpi-badge" style="background:rgba(255,159,67,0.1); color:var(--neon-orange);">Rasio Likuiditas</span>
            </div>
            <div class="kpi-title">Current Ratio</div>
            <div class="kpi-val"><?= $current_ratio ?>x</div>
            <div class="kpi-sub">
                <?php if($current_ratio >= 1.5): ?>
                <i class="fa-solid fa-circle-check" style="color:var(--neon-main)"></i> Kondisi Finansial Prima
                <?php elseif($current_ratio >= 1.0): ?>
                <i class="fa-solid fa-circle-info" style="color:var(--neon-orange)"></i> Likuiditas Cukup Aman
                <?php else: ?>
                <i class="fa-solid fa-circle-xmark" style="color:var(--neon-red)"></i> Perlu Tambahan Kas
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2-COLUMN BALANCE SHEET (AKTIVA VS PASIVA) -->
    <div class="balance-grid">

        <!-- ==========================================
             KOLOM KIRI: AKTIVA (ASET)
             ========================================== -->
        <div class="section-sheet">
            <div class="sheet-header">
                <div class="sheet-title">
                    <i class="fa-solid fa-wallet"></i> AKTIVA (ASET HVM)
                </div>
                <div class="sheet-total-badge">
                    Rp <?= number_format($total_assets, 0, ',', '.') ?>
                </div>
            </div>

            <!-- 1. Aset Lancar -->
            <div class="category-block">
                <div class="cat-heading">
                    <span><i class="fa-solid fa-money-bill-wave" style="color:var(--neon-main)"></i> 1. Aset Lancar (Current Assets)</span>
                    <span style="font-family:monospace;color:#bbb;">Rp <?= number_format($total_asset_current, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['asset_current'])): ?>
                    <?php foreach($records['asset_current'] as $acc): ?>
                    <div class="account-row">
                        <div class="acc-info">
                            <div class="acc-name">
                                <?= htmlspecialchars($acc['account_name']) ?>
                                <?php if($acc['is_auto']): ?>
                                <span class="acc-auto-tag"><i class="fa-solid fa-bolt"></i> Auto-Sync</span>
                                <?php endif; ?>
                            </div>
                            <?php if($acc['notes']): ?>
                            <div class="acc-note"><?= htmlspecialchars($acc['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="acc-val-wrap">
                            <div class="acc-val">Rp <?= number_format($acc['amount'], 0, ',', '.') ?></div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-edit-acc" onclick='editAccount(<?= json_encode($acc) ?>)' title="Ubah Akun">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="font-size:0.75rem; color:#555; padding:8px;">Belum ada akun aset lancar.</div>
                <?php endif; ?>

                <div class="subtotal-row">
                    <span>Total Aset Lancar</span>
                    <span class="subtotal-val">Rp <?= number_format($total_asset_current, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- 2. Aset Tetap & Tak Berwujud -->
            <div class="category-block">
                <div class="cat-heading">
                    <span><i class="fa-solid fa-laptop-code" style="color:var(--neon-sec)"></i> 2. Aset Tetap &amp; Digital (Fixed Assets)</span>
                    <span style="font-family:monospace;color:#bbb;">Rp <?= number_format($total_asset_fixed, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['asset_fixed'])): ?>
                    <?php foreach($records['asset_fixed'] as $acc): ?>
                    <div class="account-row">
                        <div class="acc-info">
                            <div class="acc-name">
                                <?= htmlspecialchars($acc['account_name']) ?>
                            </div>
                            <?php if($acc['notes']): ?>
                            <div class="acc-note"><?= htmlspecialchars($acc['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="acc-val-wrap">
                            <div class="acc-val <?= ($acc['amount'] < 0) ? 'negative' : '' ?>">
                                <?= ($acc['amount'] < 0) ? '(Rp ' . number_format(abs($acc['amount']), 0, ',', '.') . ')' : 'Rp ' . number_format($acc['amount'], 0, ',', '.') ?>
                            </div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-edit-acc" onclick='editAccount(<?= json_encode($acc) ?>)' title="Ubah Akun">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="font-size:0.75rem; color:#555; padding:8px;">Belum ada akun aset tetap.</div>
                <?php endif; ?>

                <div class="subtotal-row">
                    <span>Total Aset Tetap Bersih</span>
                    <span class="subtotal-val">Rp <?= number_format($total_asset_fixed, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- Grand Total Aktiva -->
            <div class="grand-total-row">
                <div class="gt-label">
                    <i class="fa-solid fa-coins" style="color:var(--neon-main)"></i> TOTAL AKTIVA (ASET)
                </div>
                <div class="gt-val highlight-green">
                    Rp <?= number_format($total_assets, 0, ',', '.') ?>
                </div>
            </div>
        </div>

        <!-- ==========================================
             KOLOM KANAN: PASIVA (KEWAJIBAN & EKUITAS)
             ========================================== -->
        <div class="section-sheet">
            <div class="sheet-header">
                <div class="sheet-title">
                    <i class="fa-solid fa-building-columns"></i> PASIVA (KEWAJIBAN &amp; EKUITAS)
                </div>
                <div class="sheet-total-badge">
                    Rp <?= number_format($total_pasiva, 0, ',', '.') ?>
                </div>
            </div>

            <!-- 1. Kewajiban Lancar (Jangka Pendek) -->
            <div class="category-block">
                <div class="cat-heading">
                    <span><i class="fa-solid fa-hourglass-half" style="color:var(--neon-red)"></i> 1. Kewajiban Lancar (Current Liabilities)</span>
                    <span style="font-family:monospace;color:#bbb;">Rp <?= number_format($total_liability_current, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['liability_current'])): ?>
                    <?php foreach($records['liability_current'] as $acc): ?>
                    <div class="account-row">
                        <div class="acc-info">
                            <div class="acc-name"><?= htmlspecialchars($acc['account_name']) ?></div>
                            <?php if($acc['notes']): ?>
                            <div class="acc-note"><?= htmlspecialchars($acc['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="acc-val-wrap">
                            <div class="acc-val">Rp <?= number_format($acc['amount'], 0, ',', '.') ?></div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-edit-acc" onclick='editAccount(<?= json_encode($acc) ?>)' title="Ubah Akun">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="subtotal-row">
                    <span>Total Kewajiban Lancar</span>
                    <span class="subtotal-val">Rp <?= number_format($total_liability_current, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- 2. Kewajiban Jangka Panjang -->
            <div class="category-block">
                <div class="cat-heading">
                    <span><i class="fa-solid fa-shield-halved" style="color:#ff9f43"></i> 2. Kewajiban Jangka Panjang (Long-Term)</span>
                    <span style="font-family:monospace;color:#bbb;">Rp <?= number_format($total_liability_long, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['liability_long'])): ?>
                    <?php foreach($records['liability_long'] as $acc): ?>
                    <div class="account-row">
                        <div class="acc-info">
                            <div class="acc-name"><?= htmlspecialchars($acc['account_name']) ?></div>
                            <?php if($acc['notes']): ?>
                            <div class="acc-note"><?= htmlspecialchars($acc['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="acc-val-wrap">
                            <div class="acc-val">Rp <?= number_format($acc['amount'], 0, ',', '.') ?></div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-edit-acc" onclick='editAccount(<?= json_encode($acc) ?>)' title="Ubah Akun">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="font-size:0.75rem; color:#555; padding:8px;">Tidak ada utang jangka panjang aktif (Bebas Utang).</div>
                <?php endif; ?>

                <div class="subtotal-row">
                    <span>Total Utang Jangka Panjang</span>
                    <span class="subtotal-val">Rp <?= number_format($total_liability_long, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- 3. Ekuitas Pemilik (Modal) -->
            <div class="category-block">
                <div class="cat-heading">
                    <span><i class="fa-solid fa-landmark" style="color:var(--neon-sec)"></i> 3. Ekuitas Pemilik (Owner's Equity)</span>
                    <span style="font-family:monospace;color:#bbb;">Rp <?= number_format($total_equity, 0, ',', '.') ?></span>
                </div>

                <?php if(!empty($records['equity'])): ?>
                    <?php foreach($records['equity'] as $acc): ?>
                    <div class="account-row">
                        <div class="acc-info">
                            <div class="acc-name">
                                <?= htmlspecialchars($acc['account_name']) ?>
                                <?php if($acc['is_auto']): ?>
                                <span class="acc-auto-tag"><i class="fa-solid fa-bolt"></i> Auto-Sync</span>
                                <?php endif; ?>
                            </div>
                            <?php if($acc['notes']): ?>
                            <div class="acc-note"><?= htmlspecialchars($acc['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="acc-val-wrap">
                            <div class="acc-val <?= ($acc['amount'] < 0) ? 'negative' : '' ?>">
                                <?= ($acc['amount'] < 0) ? '(Rp ' . number_format(abs($acc['amount']), 0, ',', '.') . ')' : 'Rp ' . number_format($acc['amount'], 0, ',', '.') ?>
                            </div>
                            <?php if($allowed): ?>
                            <button type="button" class="btn-edit-acc" onclick='editAccount(<?= json_encode($acc) ?>)' title="Ubah Akun">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="subtotal-row">
                    <span>Total Ekuitas Bersih</span>
                    <span class="subtotal-val">Rp <?= number_format($total_equity, 0, ',', '.') ?></span>
                </div>
            </div>

            <!-- Grand Total Pasiva -->
            <div class="grand-total-row">
                <div class="gt-label">
                    <i class="fa-solid fa-scale-balanced" style="color:var(--neon-sec)"></i> TOTAL PASIVA (UTANG + MODAL)
                </div>
                <div class="gt-val highlight-cyan">
                    Rp <?= number_format($total_pasiva, 0, ',', '.') ?>
                </div>
            </div>
        </div>

    </div>

    <!-- RASIO KESEHATAN KEUANGAN (ANALISIS BISNIS) -->
    <div class="analysis-card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <div style="font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:var(--neon-main); margin-bottom:4px;">
                    <i class="fa-solid fa-chart-simple"></i> Evaluasi Kesehatan Finansial
                </div>
                <h3 style="font-size:1.15rem; font-weight:800; color:#fff;">Rasio &amp; Metrik Likuiditas HVM Digital</h3>
            </div>
            <div style="font-size:0.75rem; color:#666;">
                <i class="fa-solid fa-circle-info"></i> Indikator acuan untuk kestabilan modal kerja agensi.
            </div>
        </div>

        <div class="ratio-grid">
            <!-- Current Ratio -->
            <div class="ratio-box">
                <div class="rb-top">
                    <span class="rb-title">Current Ratio</span>
                    <i class="fa-solid fa-shield-halved" style="color:var(--neon-main);font-size:0.85rem;"></i>
                </div>
                <div class="rb-score"><?= $current_ratio ?>x</div>
                <div class="rb-bar-wrap">
                    <div class="rb-bar" style="width:<?= min(100, $current_ratio * 35) ?>%; background:var(--neon-main);"></div>
                </div>
                <div class="rb-desc">Kemampuan melunasi kewajiban jangka pendek dengan aset lancar. Standar sehat agensi: <strong>&gt; 1.5x</strong>.</div>
            </div>

            <!-- Debt to Equity Ratio (DER) -->
            <div class="ratio-box">
                <div class="rb-top">
                    <span class="rb-title">Debt to Equity (DER)</span>
                    <i class="fa-solid fa-percent" style="color:var(--neon-sec);font-size:0.85rem;"></i>
                </div>
                <div class="rb-score"><?= $debt_to_equity ?>x</div>
                <div class="rb-bar-wrap">
                    <div class="rb-bar" style="width:<?= min(100, $debt_to_equity * 50) ?>%; background:var(--neon-sec);"></div>
                </div>
                <div class="rb-desc">Rasio beban utang terhadap modal bersih. Nilai <strong>&lt; 0.5x</strong> menandakan perusahaan sangat mandiri tanpa risiko utang.</div>
            </div>

            <!-- Net Working Capital -->
            <div class="ratio-box">
                <div class="rb-top">
                    <span class="rb-title">Net Working Capital</span>
                    <i class="fa-solid fa-briefcase" style="color:#ffc107;font-size:0.85rem;"></i>
                </div>
                <div class="rb-score" style="font-size:1.15rem;">Rp <?= number_format($net_working_capital, 0, ',', '.') ?></div>
                <div class="rb-bar-wrap">
                    <div class="rb-bar" style="width:85%; background:#ffc107;"></div>
                </div>
                <div class="rb-desc">Modal kerja riil bersih (Aset Lancar dikurangi Utang Lancar) yang siap dipakai operasional harian.</div>
            </div>

            <!-- Cash Ratio -->
            <div class="ratio-box">
                <div class="rb-top">
                    <span class="rb-title">Cash Ratio</span>
                    <i class="fa-solid fa-money-bill-transfer" style="color:#4efdc4;font-size:0.85rem;"></i>
                </div>
                <div class="rb-score"><?= $cash_ratio ?>x</div>
                <div class="rb-bar-wrap">
                    <div class="rb-bar" style="width:<?= min(100, $cash_ratio * 40) ?>%; background:#4efdc4;"></div>
                </div>
                <div class="rb-desc">Kesiapan kas cair untuk melunasi kewajiban secara langsung tanpa harus menunggu pelunasan invoice klien.</div>
            </div>
        </div>
    </div>

    <!-- PANDUAN LENGKAP NERACA KEUANGAN (INTERAKTIF & EDUKATIF) -->
    <div class="guide-section">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
            <div>
                <div style="font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:var(--neon-main); margin-bottom:4px;">
                    <i class="fa-solid fa-book-open-reader"></i> Executive Knowledge Base
                </div>
                <h3 style="font-size:1.15rem; font-weight:800; color:#fff;">Panduan &amp; SOP Neraca Keuangan HVM Digital</h3>
            </div>
            <a href="/dashboard/performance/download_panduan.php" class="btn-act btn-act-pdf" target="_blank">
                <i class="fa-solid fa-download"></i> Unduh Buku Panduan Resmi (PDF)
            </a>
        </div>

        <!-- Accordion 1 -->
        <div class="accordion-item active">
            <div class="accordion-header" onclick="toggleAcc(this)">
                <span><i class="fa-solid fa-circle-question" style="color:var(--neon-main);margin-right:8px;"></i> 1. Mengapa Neraca Keuangan Sangat Penting bagi HVM Digital?</span>
                <i class="fa-solid fa-chevron-down acc-chevron"></i>
            </div>
            <div class="accordion-content">
                Di bisnis agensi digital, laporan laba rugi bulanan hanya memperlihatkan aliran uang masuk dan keluar pada satu rentang waktu. Namun, laba yang besar tidak menjamin perusahaan aman jika uang muka (retainer) klien terpakai habis sebelum pekerjaan selesai. <strong>Neraca Keuangan</strong> menyajikan potret utuh: berapa kas likuid yang benar-benar milik perusahaan, berapa piutang yang masih tertahan di klien, dan berapa kewajiban masa depan yang harus diantisipasi. Dokumen ini juga menjadi prasyarat mutlak ketika HVM mengikuti tender korporasi, membuka rekening giro fasilitas pembiayaan bank, atau valuasi perusahaan.
            </div>
        </div>

        <!-- Accordion 2 -->
        <div class="accordion-item">
            <div class="accordion-header" onclick="toggleAcc(this)">
                <span><i class="fa-solid fa-calculator" style="color:var(--neon-sec);margin-right:8px;"></i> 2. Bagaimana Memahami Persamaan: Aset = Kewajiban + Ekuitas?</span>
                <i class="fa-solid fa-chevron-down acc-chevron"></i>
            </div>
            <div class="accordion-content">
                Semua kekayaan yang dimiliki HVM (Aset) pasti didanai oleh dua sumber: <strong>Utang pihak luar (Kewajiban)</strong> atau <strong>Modal sendiri (Ekuitas)</strong>.<br>
                - <strong>Aset Lancar:</strong> Kas di bank, piutang invoice dari klien, dan deposit saldo iklan/tools.<br>
                - <strong>Aset Tetap:</strong> Laptop Mac, workstation PC, kamera studio, dan lisensi software.<br>
                - <strong>Kewajiban:</strong> Utang ke freelancer/vendor luar serta DP klien yang jasanya belum selesai dikerjakan.<br>
                - <strong>Ekuitas:</strong> Modal awal disetor founder ditambah akumulasi laba bersih yang ditahan untuk ekspansi bisnis.
            </div>
        </div>

        <!-- Accordion 3 -->
        <div class="accordion-item">
            <div class="accordion-header" onclick="toggleAcc(this)">
                <span><i class="fa-solid fa-chart-line" style="color:#ffc107;margin-right:8px;"></i> 3. Cara Menilai Kesehatan Keuangan dari Rasio Likuiditas</span>
                <i class="fa-solid fa-chevron-down acc-chevron"></i>
            </div>
            <div class="accordion-content">
                - <strong>Current Ratio &gt; 1.5x:</strong> Posisi sangat aman. Setiap Rp 1 utang jangka pendek dijamin oleh Rp 1,5 aset lancar.<br>
                - <strong>Debt to Equity Ratio (DER) &lt; 0.5x:</strong> Struktur permodalan sangat sehat. HVM tidak tergantung pada utang luar.<br>
                - <strong>Cash Ratio &gt; 0.5x:</strong> Kas likuid mencukupi untuk melunasi biaya darurat tanpa harus menunggu klien melunasi tagihan invoice.
            </div>
        </div>

        <!-- Accordion 4 -->
        <div class="accordion-item">
            <div class="accordion-header" onclick="toggleAcc(this)">
                <span><i class="fa-solid fa-calendar-check" style="color:#4efdc4;margin-right:8px;"></i> 4. SOP Rekonsiliasi &amp; Sinkronisasi Rutin HVM</span>
                <i class="fa-solid fa-chevron-down acc-chevron"></i>
            </div>
            <div class="accordion-content">
                1. Setiap akhir bulan, Super Admin menekan tombol <strong>"Sinkronisasi Data"</strong> untuk memperbarui piutang klien otomatis dari invoice Unpaid/Overdue.<br>
                2. Lakukan rekonsiliasi saldo kas bank riil (BCA/Mandiri) melalui tombol edit akun pada baris Kas &amp; Bank.<br>
                3. Pastikan status Neraca menunjukkan tanda <strong>"Neraca Seimbang (Balanced)"</strong>.<br>
                4. Klik tombol <strong>"Download Panduan (PDF)"</strong> atau <strong>"Print / Cetak"</strong> untuk mengarsipkan laporan keuangan berkala.
            </div>
        </div>
    </div>

</main>

<!-- MODAL FORM EDIT / TAMBAH AKUN NERACA -->
<div class="modal-overlay" id="accModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fa-solid fa-sliders" style="color:var(--neon-main)"></i>
                <span id="modalHeaderTitle">Penyesuaian Akun Neraca</span>
            </div>
            <button type="button" class="modal-close" onclick="closeAccModal()">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="period_year" value="<?= $selected_year ?>">
            <input type="hidden" name="period_type" value="<?= $selected_type ?>">

            <div class="form-group">
                <label>Kode Akun Unik</label>
                <input type="text" name="account_code" id="modal_code" class="form-input" required placeholder="cth: kas_bank, piutang_usaha">
            </div>

            <div class="form-group">
                <label>Nama Akun Neraca</label>
                <input type="text" name="account_name" id="modal_name" class="form-input" required placeholder="cth: Kas Rekening BCA Operasional">
            </div>

            <div class="form-group">
                <label>Kategori Akun</label>
                <select name="account_category" id="modal_cat" class="form-input" required>
                    <option value="asset_current">Aset Lancar (Current Asset)</option>
                    <option value="asset_fixed">Aset Tetap (Fixed Asset)</option>
                    <option value="liability_current">Kewajiban Lancar (Current Liability)</option>
                    <option value="liability_long">Kewajiban Jangka Panjang (Long-Term Liability)</option>
                    <option value="equity">Ekuitas Pemilik (Owner's Equity)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Nominal Saldo (Rp) <span style="font-size:0.65rem;color:#666;">(Gunakan tanda minus untuk akun pengurang/depresiasi)</span></label>
                <input type="number" step="any" name="amount" id="modal_amount" class="form-input" required placeholder="cth: 75000000">
            </div>

            <div class="form-group">
                <label>Catatan / Keterangan Tambahan</label>
                <textarea name="notes" id="modal_notes" class="form-input" style="min-height:75px;" placeholder="Catatan sumber data atau rincian rekening..."></textarea>
            </div>

            <button type="submit" name="save_account_item" class="btn-save-acc">
                <i class="fa-solid fa-floppy-disk"></i> Simpan Akun Neraca
            </button>
        </form>
    </div>
</div>

<script>
// Accordion Toggle
function toggleAcc(header) {
    const item = header.parentElement;
    item.classList.toggle('active');
}

// Open Edit Modal
function editAccount(acc) {
    document.getElementById('modalHeaderTitle').textContent = 'Edit Akun: ' + acc.account_name;
    document.getElementById('modal_code').value = acc.account_code;
    document.getElementById('modal_name').value = acc.account_name;
    document.getElementById('modal_cat').value = acc.account_category;
    document.getElementById('modal_amount').value = acc.amount;
    document.getElementById('modal_notes').value = acc.notes || '';
    document.getElementById('accModal').classList.add('active');
}

// Open Add Modal
function openAddModal() {
    document.getElementById('modalHeaderTitle').textContent = 'Tambah Akun Neraca Baru';
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

// Close modal on click outside
window.addEventListener('click', e => {
    const modal = document.getElementById('accModal');
    if (e.target === modal) closeAccModal();
});
</script>
</body>
</html>
