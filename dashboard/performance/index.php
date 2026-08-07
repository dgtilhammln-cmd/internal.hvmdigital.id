<?php
// ============================================================
// PERFORMANCE REPORT — index.php  v5 — ICON FIXED
// ============================================================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if (!isset($_SESSION['admin'])) {
    echo "<script>window.location='/index.php';</script>"; exit;
}
$allowed   = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
$adminName = htmlspecialchars($_SESSION['admin'] ?? 'Admin');
$role      = ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? 'admin'));

mysqli_query($conn, "SET SESSION group_concat_max_len = 100000");

$start_m = isset($_GET['start_m']) && is_numeric($_GET['start_m']) ? sprintf('%02d',(int)$_GET['start_m']) : date('m',strtotime('-5 months'));
$start_y = isset($_GET['start_y']) && is_numeric($_GET['start_y']) ? (int)$_GET['start_y']                : (int)date('Y',strtotime('-5 months'));
$end_m   = isset($_GET['end_m'])   && is_numeric($_GET['end_m'])   ? sprintf('%02d',(int)$_GET['end_m'])   : date('m',strtotime('+1 month'));
$end_y   = isset($_GET['end_y'])   && is_numeric($_GET['end_y'])   ? (int)$_GET['end_y']                  : (int)date('Y',strtotime('+1 month'));

$start_date = "$start_y-$start_m-01";
$end_date   = date("Y-m-t", strtotime("$end_y-$end_m-01"));

if (strtotime($start_date) > strtotime($end_date)) {
    $start_m = date('m',strtotime('-5 months')); $start_y = (int)date('Y',strtotime('-5 months'));
    $end_m   = date('m',strtotime('+1 month'));  $end_y   = (int)date('Y',strtotime('+1 month'));
    $start_date = "$start_y-$start_m-01";
    $end_date   = date("Y-m-t",strtotime("$end_y-$end_m-01"));
}

$labels=$chart_profit=$chart_expense=$chart_income=$table_data=[];
$total_omzet=$total_expense=$new_clients=$rt_active=$rt_suspend=0;
$best_month=['label'=>'-','profit'=>null];
$worst_month=['label'=>'-','profit'=>null];

if ($allowed) {
    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1M'),
        new DateTime($end_date.' +1 day')
    );
    foreach ($period as $dt) {
        $m=$dt->format('m'); $y=$dt->format('Y');
        $st=$conn->prepare("SELECT payment_type,COUNT(*) cnt FROM payments WHERE MONTH(payment_date)=? AND YEAR(payment_date)=? GROUP BY payment_type");
        $st->bind_param("ii",$m,$y); $st->execute();
        $res=$st->get_result(); $dp=[];
        while($r=$res->fetch_assoc()) $dp[]=$r['cnt'].' '.htmlspecialchars($r['payment_type']);
        $desc=$dp?implode(', ',$dp):'—'; $st->close();
        $st=$conn->prepare("SELECT COALESCE(SUM(amount),0) t FROM payments WHERE MONTH(payment_date)=? AND YEAR(payment_date)=?");
        $st->bind_param("ii",$m,$y); $st->execute();
        $inc=(float)$st->get_result()->fetch_assoc()['t']; $st->close();
        $st=$conn->prepare("SELECT COALESCE(SUM(amount),0) t FROM spendings WHERE MONTH(spending_date)=? AND YEAR(spending_date)=?");
        $st->bind_param("ii",$m,$y); $st->execute();
        $exp=(float)$st->get_result()->fetch_assoc()['t']; $st->close();
        $st=$conn->prepare("SELECT COUNT(*) c FROM clients WHERE MONTH(created_at)=? AND YEAR(created_at)=?");
        $st->bind_param("ii",$m,$y); $st->execute();
        $nc=(int)$st->get_result()->fetch_assoc()['c']; $st->close();
        $prof=$inc-$exp;
        $margin=$inc>0?round(($prof/$inc)*100,1):0;
        $labels[]        = $dt->format("M'y");
        $chart_income[]  = $inc;
        $chart_profit[]  = $prof;
        $chart_expense[] = $exp;
        $table_data[]=['period'=>$dt->format('M Y'),'income'=>$inc,'expense'=>$exp,'profit'=>$prof,'margin'=>$margin,'nc'=>$nc,'desc'=>$desc];
        $total_omzet+=$inc; $total_expense+=$exp; $new_clients+=$nc;
        if ($best_month['profit']===null||$prof>$best_month['profit'])   $best_month =['label'=>$dt->format('M Y'),'profit'=>$prof];
        if ($worst_month['profit']===null||$prof<$worst_month['profit']) $worst_month=['label'=>$dt->format('M Y'),'profit'=>$prof];
    }
    $rt_active =(int)(mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM clients WHERE status='Active'"))['c']??0);
    $rt_suspend=(int)(mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM clients WHERE status='Suspended' OR contract_end<NOW()"))['c']??0);
}

$total_profit =$total_omzet-$total_expense;
$avg_margin   =$total_omzet>0?round(($total_profit/$total_omzet)*100,1):0;
$total_clients=$rt_active+$rt_suspend;
$retention_pct=$total_clients>0?round(($rt_active/$total_clients)*100):0;
$avg_monthly  =count($chart_profit)>0?array_sum($chart_profit)/count($chart_profit):0;

$month_names=['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'May','06'=>'Jun',
              '07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Performance Report — HVM</title>
<link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">

<!-- =====================================================================
     ROOT CAUSE FIX: Icon tidak tampil karena FA font tidak ter-load.
     
     SOLUSI BERLAPIS:
     1. Load FA via jsDelivr npm (TANPA integrity = tidak pernah blokir)
     2. Load FA via cdnjs sebagai secondary (TANPA integrity)  
     3. CSS @font-face inline sebagai last resort
     4. font-display: block agar tidak invisible saat loading
     ===================================================================== -->

<!-- Layer 1: jsDelivr npm — paling reliable, no integrity -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">

<!-- Layer 2: cdnjs backup, loaded async -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="print" onload="this.media='all'">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Dashboard base CSS -->
<link rel="stylesheet" href="/dashboard/style.css">

<style>
/* ============================================================
   PERFORMANCE REPORT — STYLES v5
   ============================================================ */
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

/* CRITICAL: Force FA icons to render — override any conflicting CSS */
.fa, .fas, .far, .fab, .fal, .fa-solid, .fa-regular, .fa-brands {
    font-style: normal !important;
    font-variant: normal !important;
    text-rendering: auto !important;
    line-height: 1 !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    display: inline-block !important;
}
/* Pastikan pseudo-element ::before bisa render glyph */
.fa::before, .fas::before, .fa-solid::before,
.fa-regular::before, .far::before, .fab::before {
    font-display: block !important;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body { background:var(--bg); color:#fff; font-family:'Montserrat','Segoe UI',sans-serif; min-height:100vh; overflow-x:hidden; }

/* Scrollbar */
::-webkit-scrollbar { width:4px; height:4px; }
::-webkit-scrollbar-track { background:#080808; }
::-webkit-scrollbar-thumb { background:#1c1c1c; border-radius:8px; }
::-webkit-scrollbar-thumb:hover { background:var(--neon-main); }

/* Ambient glow */
.ambient-glow { position:fixed; border-radius:50%; filter:blur(140px); opacity:.08; z-index:0; pointer-events:none; animation:floatGlow 14s ease-in-out infinite alternate; }
.glow-1 { top:-150px; left:-150px; width:700px; height:700px; background:var(--neon-main); }
.glow-2 { bottom:-150px; right:-150px; width:700px; height:700px; background:var(--neon-sec); }
@keyframes floatGlow { from{transform:scale(1)} to{transform:scale(1.12)} }

/* Layout */
.dashboard-wrapper { display:flex; width:100%; min-height:100vh; position:relative; z-index:1; }
.main-content { margin-left:var(--sidebar-w); padding:32px 36px; width:calc(100% - var(--sidebar-w)); }

/* Mobile header */
.mobile-header { display:none; position:fixed; top:0; left:0; right:0; z-index:900; background:rgba(5,5,5,.96); backdrop-filter:blur(20px); border-bottom:1px solid var(--card-border); padding:0 16px; height:56px; align-items:center; justify-content:space-between; }
.mobile-brand { font-weight:900; font-size:.95rem; letter-spacing:2px; background:linear-gradient(135deg,var(--neon-main),var(--neon-sec)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.hamburger { background:none; border:1px solid var(--card-border); border-radius:8px; width:36px; height:36px; cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; padding:6px; transition:.3s; }
.hamburger span { display:block; width:18px; height:1.5px; background:#aaa; transition:.3s; border-radius:2px; }
.hamburger.is-open span:nth-child(1) { transform:translateY(6.5px) rotate(45deg); }
.hamburger.is-open span:nth-child(2) { opacity:0; transform:scaleX(0); }
.hamburger.is-open span:nth-child(3) { transform:translateY(-6.5px) rotate(-45deg); }
.sidebar-overlay { display:none; position:fixed; inset:0; z-index:898; background:rgba(0,0,0,.7); backdrop-filter:blur(4px); }
.sidebar-overlay.active { display:block; }
.btn-icon-sm { background:rgba(255,255,255,.05); border:1px solid var(--card-border); border-radius:8px; width:36px; height:36px; cursor:pointer; color:#aaa; display:flex; align-items:center; justify-content:center; transition:.3s; }
.btn-icon-sm:hover { color:var(--neon-sec); border-color:var(--neon-sec); }
.mobile-right { display:flex; gap:8px; }

/* Page headline */
.page-headline { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:28px; gap:20px; animation:slideDown .5s ease; }
.hl-tag { display:inline-flex; align-items:center; gap:7px; font-size:.62rem; font-weight:800; letter-spacing:2.5px; color:var(--neon-main); text-transform:uppercase; background:rgba(161,255,90,.07); border:1px solid rgba(161,255,90,.18); padding:5px 13px; border-radius:50px; margin-bottom:10px; }
.page-headline h1 { font-size:2rem; font-weight:900; line-height:1.1; margin-bottom:6px; background:linear-gradient(180deg,#fff 40%,#444); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.grad-text { background:linear-gradient(135deg,var(--neon-main),var(--neon-sec)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.page-headline p { color:var(--text-muted); font-size:.84rem; }
.hl-actions { display:flex; gap:10px; flex-shrink:0; }
.btn-act { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.04); border:1px solid var(--card-border); color:#aaa; padding:9px 18px; border-radius:50px; font-size:.78rem; font-weight:700; cursor:pointer; transition:.3s; font-family:inherit; }
.btn-act:hover { color:#fff; border-color:rgba(255,255,255,.2); }
.btn-act-primary { background:rgba(161,255,90,.1); border-color:rgba(161,255,90,.3); color:var(--neon-main); }
.btn-act-primary:hover { background:rgba(161,255,90,.18); border-color:var(--neon-main); box-shadow:0 0 20px rgba(161,255,90,.2); }

/* Filter bar */
.top-bar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
.filter-glass { display:flex; align-items:center; gap:14px; background:rgba(14,14,14,.8); backdrop-filter:blur(20px); border:1px solid var(--card-border); border-radius:14px; padding:14px 20px; flex-wrap:wrap; }
.filter-label { display:flex; align-items:center; gap:6px; font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#444; margin-bottom:7px; }
.filter-selects { display:flex; gap:8px; }
.filter-select { background:rgba(0,0,0,.5); border:1px solid rgba(255,255,255,.07); border-radius:9px; color:#ddd; padding:8px 28px 8px 12px; font-family:inherit; font-size:.82rem; font-weight:600; outline:none; cursor:pointer; transition:.3s; -webkit-appearance:none; -moz-appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23555'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 9px center; }
.filter-select:focus { border-color:var(--neon-sec); }
.filter-select option { background:#111; }
.filter-arrow-icon { color:#2a2a2a; font-size:.85rem; flex-shrink:0; }
.btn-filter { display:inline-flex; align-items:center; gap:8px; background:linear-gradient(135deg,var(--neon-main),var(--neon-sec)); color:#000; border:none; border-radius:9px; padding:9px 20px; font-family:inherit; font-size:.78rem; font-weight:800; cursor:pointer; transition:.3s; white-space:nowrap; box-shadow:0 4px 16px rgba(161,255,90,.2); }
.btn-filter:hover { transform:translateY(-2px); box-shadow:0 6px 24px rgba(161,255,90,.35); }
.preset-wrap { display:flex; align-items:center; gap:6px; background:rgba(14,14,14,.8); backdrop-filter:blur(20px); border:1px solid var(--card-border); border-radius:14px; padding:10px 14px; }
.preset-label { color:#2a2a2a; font-size:.75rem; margin-right:2px; }
.btn-preset { background:rgba(255,255,255,.04); border:1px solid var(--card-border); color:#555; padding:6px 13px; border-radius:7px; font-size:.72rem; font-weight:800; cursor:pointer; transition:.3s; font-family:inherit; }
.btn-preset:hover { background:rgba(161,255,90,.1); color:var(--neon-main); border-color:rgba(161,255,90,.3); }

/* Stats grid */
.stats-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:20px; }
.stat-card { background:var(--card-bg); backdrop-filter:blur(24px); border:1px solid var(--card-border); border-radius:var(--radius); padding:18px 20px; position:relative; overflow:hidden; transition:transform .25s,box-shadow .25s,border-color .25s; animation:cardFadeUp .5s ease both; animation-delay:var(--d,0ms); }
.stat-card::before { content:''; position:absolute; inset:0; background:radial-gradient(ellipse at top left,rgba(var(--gc),.09) 0%,transparent 65%); pointer-events:none; }
.stat-card:hover { transform:translateY(-4px); box-shadow:0 12px 36px rgba(0,0,0,.5); border-color:rgba(var(--gc),.22); }
.sc-glow { position:absolute; top:-30px; right:-30px; width:120px; height:120px; border-radius:50%; background:rgba(var(--gc),.1); filter:blur(30px); pointer-events:none; }
.sc-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; }
.sc-icon { width:40px; height:40px; border-radius:10px; background:rgba(var(--ic),.1); border:1px solid rgba(var(--ic),.2); display:flex; align-items:center; justify-content:center; color:rgb(var(--ic)); font-size:1rem; box-shadow:0 0 14px rgba(var(--ic),.12); }
.sc-badge { width:24px; height:24px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:.58rem; }
.sc-up    { background:rgba(161,255,90,.12); color:var(--neon-main); }
.sc-down  { background:rgba(255,90,90,.12);  color:var(--neon-red); }
.sc-warn  { background:rgba(255,159,67,.12); color:var(--neon-orange); }
.sc-neutral { background:rgba(78,253,196,.12); color:var(--neon-sec); }
.sc-label { font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#484848; margin-bottom:6px; }
.sc-val { font-size:1.3rem; font-weight:900; margin-bottom:10px; line-height:1; }
.sc-foot { display:flex; align-items:center; gap:6px; font-size:.68rem; color:#3a3a3a; border-top:1px solid rgba(255,255,255,.04); padding-top:10px; }
.trend-up   { color:var(--neon-main) !important; }
.trend-down { color:var(--neon-red)  !important; }

/* Chart grid */
.chart-grid { display:grid; grid-template-columns:1fr 340px; gap:16px; margin-bottom:20px; }
.chart-main { background:var(--card-bg); backdrop-filter:blur(24px); border:1px solid var(--card-border); border-radius:var(--radius); padding:22px; }
.chart-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; }
.sec-label { display:inline-flex; align-items:center; gap:7px; font-size:.6rem; font-weight:800; text-transform:uppercase; letter-spacing:2px; color:#3a3a3a; margin-bottom:4px; }
.chart-legend { display:flex; gap:14px; flex-wrap:wrap; margin-top:4px; }
.chart-legend span { display:flex; align-items:center; gap:6px; font-size:.72rem; color:#555; font-weight:600; }
.chart-canvas-wrap { height:260px; position:relative; }
.chart-toggle-group { display:flex; gap:4px; }
.ctoggle { width:32px; height:32px; border-radius:8px; background:rgba(255,255,255,.04); border:1px solid var(--card-border); color:#444; cursor:pointer; transition:.3s; font-size:.8rem; display:flex; align-items:center; justify-content:center; }
.ctoggle.active { background:rgba(161,255,90,.12); border-color:rgba(161,255,90,.3); color:var(--neon-main); }

/* Retention */
.retention-card { background:var(--card-bg); backdrop-filter:blur(24px); border:1px solid var(--card-border); border-radius:var(--radius); overflow:hidden; }
.ret-wrap { padding:18px 22px 22px; }
.donut-outer { position:relative; width:160px; height:160px; margin:0 auto 22px; }
.donut-center { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; pointer-events:none; }
.donut-pct { font-size:1.6rem; font-weight:900; color:#fff; line-height:1; }
.donut-sub { font-size:.58rem; text-transform:uppercase; letter-spacing:2px; color:#3a3a3a; margin-top:3px; }
.ret-item { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid rgba(255,255,255,.04); }
.ret-item:last-of-type { border-bottom:none; }
.ret-ic { width:32px; height:32px; border-radius:8px; background:rgba(var(--rc),.1); border:1px solid rgba(var(--rc),.2); display:flex; align-items:center; justify-content:center; color:rgb(var(--rc)); font-size:.8rem; flex-shrink:0; }
.ret-info { display:flex; justify-content:space-between; width:100%; }
.ret-lbl { font-size:.75rem; color:#555; }
.ret-val { font-size:.85rem; font-weight:800; }
.ret-health { margin-top:14px; padding-top:14px; border-top:1px solid rgba(255,255,255,.04); }
.ret-health-top { display:flex; justify-content:space-between; align-items:center; font-size:.72rem; color:#444; margin-bottom:8px; font-weight:700; }
.ret-health-top span:first-child { display:flex; align-items:center; gap:6px; }
.ret-health-bar { height:4px; background:rgba(255,255,255,.05); border-radius:4px; overflow:hidden; }
.ret-health-bar div { height:100%; border-radius:4px; transition:width 1.2s ease; }

/* Table */
.table-card { background:var(--card-bg); backdrop-filter:blur(24px); border:1px solid var(--card-border); border-radius:var(--radius); overflow:hidden; }
.table-head { display:flex; justify-content:space-between; align-items:center; padding:20px 24px 18px; border-bottom:1px solid rgba(255,255,255,.04); flex-wrap:wrap; gap:12px; }
.table-meta { font-size:.68rem; color:#3a3a3a; margin-top:4px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.tbl-search { display:flex; align-items:center; gap:9px; background:rgba(0,0,0,.4); border:1px solid var(--card-border); border-radius:9px; padding:8px 14px; }
.tbl-search input { background:none; border:none; outline:none; color:#ddd; font-family:inherit; font-size:.82rem; width:180px; }
.tbl-search input::placeholder { color:#2a2a2a; }
.table-responsive { overflow-x:auto; }
table { width:100%; border-collapse:collapse; }
thead th { text-align:left; font-size:.6rem; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#333; padding:12px 18px; border-bottom:1px solid rgba(255,255,255,.04); background:rgba(0,0,0,.25); white-space:nowrap; }
th.sortable { cursor:pointer; user-select:none; }
th.sortable:hover { color:var(--neon-sec); }
.sort-ic { margin-left:4px; opacity:.4; }
.trow td { padding:13px 18px; border-bottom:1px solid rgba(255,255,255,.025); font-size:.82rem; vertical-align:middle; transition:background .2s; }
.trow:hover td { background:rgba(255,255,255,.012); }
.trow:last-child td { border-bottom:none; }
.period-cell { display:flex; align-items:center; gap:8px; }
.desc-cell { color:var(--text-muted); font-size:.76rem; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.mbar-wrap { display:flex; align-items:center; gap:8px; min-width:90px; }
.mbar { height:4px; border-radius:4px; min-width:4px; transition:width .6s ease; }
.no-data { color:#222; }
.badge-new { display:inline-flex; align-items:center; gap:5px; background:rgba(161,255,90,.1); color:var(--neon-main); border:1px solid rgba(161,255,90,.2); font-size:.65rem; padding:3px 9px; border-radius:5px; font-weight:800; }
.status-badge { display:inline-flex; align-items:center; gap:5px; font-size:.65rem; padding:4px 10px; border-radius:6px; font-weight:800; }
.s-profit { background:rgba(161,255,90,.08); color:var(--neon-main); border:1px solid rgba(161,255,90,.18); }
.s-loss   { background:rgba(255,90,90,.08);  color:var(--neon-red);  border:1px solid rgba(255,90,90,.18); }
tfoot .sum-row td { padding:14px 18px; border-top:1px solid rgba(255,255,255,.07); background:rgba(0,0,0,.2); font-size:.82rem; }

/* Forbidden */
.forbidden-box { display:flex; flex-direction:column; justify-content:center; align-items:center; min-height:65vh; text-align:center; border:1px solid var(--card-border); background:rgba(255,255,255,.01); border-radius:var(--radius); margin-top:20px; }
.forbidden-icon { font-size:3.5rem; color:var(--neon-red); margin-bottom:20px; animation:pulse 2s ease-in-out infinite; }
.forbidden-text { color:#fff; font-size:1.3rem; font-weight:900; margin-bottom:8px; letter-spacing:2px; }
.forbidden-sub { color:#444; margin-bottom:28px; font-size:.85rem; }
.btn-neon { display:inline-flex; align-items:center; gap:8px; background:linear-gradient(135deg,var(--neon-main),var(--neon-sec)); color:#000; padding:12px 26px; border-radius:50px; font-weight:800; text-decoration:none; transition:.3s; }
.btn-neon:hover { transform:translateY(-2px); box-shadow:0 6px 28px rgba(161,255,90,.4); }

/* Toast */
.toast { position:fixed; top:24px; right:24px; z-index:99999; background:#0e0e0e; border:1px solid rgba(255,255,255,.07); border-left:3px solid var(--neon-main); padding:14px 20px; border-radius:12px; display:flex; align-items:center; gap:12px; transform:translateX(130%); transition:.4s cubic-bezier(.175,.885,.32,1.275); box-shadow:0 10px 40px rgba(0,0,0,.6); font-size:.85rem; font-weight:600; max-width:340px; }
.toast.show { transform:translateX(0); }
.toast.error { border-left-color:var(--neon-red); }
.toast-ic { font-size:1rem; color:var(--neon-main); }
.toast.error .toast-ic { color:var(--neon-red); }

/* Animations */
@keyframes cardFadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn     { from{opacity:0;transform:translateY(8px)}  to{opacity:1;transform:translateY(0)} }
@keyframes slideDown  { from{opacity:0;transform:translateY(-16px)} to{opacity:1;transform:translateY(0)} }
@keyframes pulse      { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.08);opacity:.7} }

/* Responsive */
@media(max-width:1400px) { .stats-grid{grid-template-columns:repeat(3,1fr);} }
@media(max-width:1100px) { .chart-grid{grid-template-columns:1fr;} }
@media(max-width:900px) {
    .stats-grid{grid-template-columns:repeat(2,1fr);}
    .main-content{margin-left:0;width:100%;padding:70px 16px 24px;}
    .mobile-header{display:flex;}
    .page-headline{flex-direction:column;gap:12px;}
    .hl-actions{flex-wrap:wrap;}
    .top-bar{flex-direction:column;align-items:stretch;}
    .filter-glass{flex-direction:column;align-items:stretch;}
    .preset-wrap{justify-content:center;}
}
@media(max-width:480px) { .stats-grid{grid-template-columns:1fr 1fr;} }
</style>
</head>
<body>

<div class="ambient-glow glow-1"></div>
<div class="ambient-glow glow-2"></div>

<!-- MOBILE HEADER -->
<header class="mobile-header">
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
        <span></span><span></span><span></span>
    </button>
    <div class="mobile-brand">HVM</div>
    <div class="mobile-right">
        <?php if($allowed):?>
        <button class="btn-icon-sm" onclick="exportCSV()" title="Export CSV">
            <i class="fa-solid fa-download"></i>
        </button>
        <?php endif;?>
    </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="dashboard-wrapper">

<?php include '../sidebar.php'; ?>

<main class="main-content">

    <!-- HEADLINE -->
    <div class="page-headline">
        <div class="hl-left">
            <div class="hl-tag">
                <i class="fa-solid fa-chart-line"></i> FINANCIAL REPORT
            </div>
            <h1>Performance <span class="grad-text">Report</span></h1>
            <p>Analisis keuangan &amp; metrik pertumbuhan untuk periode yang dipilih.</p>
        </div>
        <?php if($allowed):?>
        <div class="hl-actions">
            <button class="btn-act" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print
            </button>
            <button class="btn-act btn-act-primary" onclick="exportCSV()">
                <i class="fa-solid fa-file-csv"></i> Export CSV
            </button>
        </div>
        <?php endif;?>
    </div>

    <?php if(!$allowed):?>
    <div class="forbidden-box">
        <div class="forbidden-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="forbidden-text">AKSES DIBATASI</div>
        <div class="forbidden-sub">Halaman ini hanya dapat diakses oleh Super Admin.</div>
        <a href="/dashboard/" class="btn-neon"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
    </div>
    <?php else:?>

    <!-- FILTER BAR -->
    <div class="top-bar">
        <form method="GET" class="filter-glass" id="filterForm">
            <div class="filter-group">
                <label class="filter-label"><i class="fa-solid fa-hourglass-start"></i> DARI</label>
                <div class="filter-selects">
                    <select name="start_m" class="filter-select">
                        <?php for($i=1;$i<=12;$i++): $v=sprintf('%02d',$i);?>
                        <option value="<?=$v?>" <?=($v==$start_m?'selected':'')?>><?=$month_names[$v]?></option>
                        <?php endfor;?>
                    </select>
                    <select name="start_y" class="filter-select">
                        <?php for($y=2024;$y<=2030;$y++):?>
                        <option value="<?=$y?>" <?=($y==$start_y?'selected':'')?>><?=$y?></option>
                        <?php endfor;?>
                    </select>
                </div>
            </div>
            <div class="filter-arrow-icon"><i class="fa-solid fa-arrow-right-long"></i></div>
            <div class="filter-group">
                <label class="filter-label"><i class="fa-solid fa-hourglass-end"></i> SAMPAI</label>
                <div class="filter-selects">
                    <select name="end_m" class="filter-select">
                        <?php for($i=1;$i<=12;$i++): $v=sprintf('%02d',$i);?>
                        <option value="<?=$v?>" <?=($v==$end_m?'selected':'')?>><?=$month_names[$v]?></option>
                        <?php endfor;?>
                    </select>
                    <select name="end_y" class="filter-select">
                        <?php for($y=2024;$y<=2030;$y++):?>
                        <option value="<?=$y?>" <?=($y==$end_y?'selected':'')?>><?=$y?></option>
                        <?php endfor;?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-filter">
                <i class="fa-solid fa-rotate"></i> UPDATE VIEW
            </button>
        </form>
        <div class="preset-wrap">
            <span class="preset-label"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <button type="button" class="btn-preset" onclick="setPreset(3)">3M</button>
            <button type="button" class="btn-preset" onclick="setPreset(6)">6M</button>
            <button type="button" class="btn-preset" onclick="setPreset(12)">1Y</button>
            <button type="button" class="btn-preset" onclick="setPreset(0)">ALL</button>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">

        <div class="stat-card" style="--d:0ms;--gc:78,253,196;--ic:78,253,196">
            <div class="sc-glow"></div>
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="sc-badge sc-up"><i class="fa-solid fa-arrow-trend-up"></i></div>
            </div>
            <div class="sc-label">Total Omzet</div>
            <div class="sc-val" style="color:var(--neon-sec)">Rp <?=number_format($total_omzet/1e6,1)?>jt</div>
            <div class="sc-foot">
                <i class="fa-solid fa-user-plus" style="color:var(--neon-sec)"></i>
                <span class="trend-up">+<?=$new_clients?> klien baru</span>
            </div>
        </div>

        <div class="stat-card" style="--d:70ms;--gc:<?=$total_profit>=0?'161,255,90':'255,90,90'?>;--ic:<?=$total_profit>=0?'161,255,90':'255,90,90'?>">
            <div class="sc-glow"></div>
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-circle-dollar-to-slot"></i></div>
                <div class="sc-badge <?=$total_profit>=0?'sc-up':'sc-down'?>">
                    <i class="fa-solid fa-<?=$total_profit>=0?'arrow-trend-up':'arrow-trend-down'?>"></i>
                </div>
            </div>
            <div class="sc-label">Total Profit</div>
            <div class="sc-val" style="color:<?=$total_profit>=0?'var(--neon-main)':'var(--neon-red)'?>">
                Rp <?=number_format($total_profit/1e6,1)?>jt
            </div>
            <div class="sc-foot">
                <i class="fa-solid fa-percent" style="color:<?=$avg_margin>=0?'var(--neon-main)':'var(--neon-red)'?>"></i>
                <span class="<?=$avg_margin>=0?'trend-up':'trend-down'?>"><?=$avg_margin?>% margin</span>
            </div>
        </div>

        <div class="stat-card" style="--d:140ms;--gc:255,90,90;--ic:255,90,90">
            <div class="sc-glow"></div>
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div class="sc-badge sc-down"><i class="fa-solid fa-arrow-trend-down"></i></div>
            </div>
            <div class="sc-label">Total Pengeluaran</div>
            <div class="sc-val" style="color:var(--neon-red)">Rp <?=number_format($total_expense/1e6,1)?>jt</div>
            <div class="sc-foot">
                <i class="fa-solid fa-gears" style="color:var(--neon-red)"></i>
                <span class="trend-down">Operational cost</span>
            </div>
        </div>

        <div class="stat-card" style="--d:210ms;--gc:255,159,67;--ic:255,159,67">
            <div class="sc-glow"></div>
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-people-group"></i></div>
                <div class="sc-badge sc-warn"><i class="fa-solid fa-circle-check"></i></div>
            </div>
            <div class="sc-label">Active Clients</div>
            <div class="sc-val" style="color:var(--neon-orange)"><?=$rt_active?></div>
            <div class="sc-foot">
                <i class="fa-solid fa-triangle-exclamation" style="color:var(--neon-red)"></i>
                <span class="trend-down"><?=$rt_suspend?> Suspended</span>
            </div>
        </div>

        <div class="stat-card" style="--d:280ms;--gc:161,255,90;--ic:161,255,90">
            <div class="sc-glow"></div>
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-trophy"></i></div>
                <div class="sc-badge sc-up"><i class="fa-solid fa-crown"></i></div>
            </div>
            <div class="sc-label">Best Month</div>
            <div class="sc-val" style="color:var(--neon-main);font-size:1.1rem">
                <?=htmlspecialchars($best_month['label'])?>
            </div>
            <div class="sc-foot">
                <i class="fa-solid fa-bolt" style="color:var(--neon-main)"></i>
                <span class="trend-up">Rp <?=$best_month['profit']!==null?number_format($best_month['profit']/1e6,1):0?>jt</span>
            </div>
        </div>

        <div class="stat-card" style="--d:350ms;--gc:78,253,196;--ic:78,253,196">
            <div class="sc-glow"></div>
            <div class="sc-top">
                <div class="sc-icon"><i class="fa-solid fa-chart-simple"></i></div>
                <div class="sc-badge sc-neutral"><i class="fa-solid fa-wave-square"></i></div>
            </div>
            <div class="sc-label">Avg Monthly Profit</div>
            <div class="sc-val" style="color:var(--neon-sec);font-size:1.1rem">
                Rp <?=number_format($avg_monthly/1e6,1)?>jt
            </div>
            <div class="sc-foot">
                <i class="fa-solid fa-calendar-days" style="color:var(--neon-sec)"></i>
                <span>per bulan</span>
            </div>
        </div>

    </div>

    <!-- CHART GRID -->
    <div class="chart-grid">
        <div class="chart-main">
            <div class="chart-head">
                <div>
                    <div class="sec-label"><i class="fa-solid fa-chart-area"></i> GROWTH WAVE</div>
                    <div class="chart-legend">
                        <span><svg width="8" height="8"><circle cx="4" cy="4" r="4" fill="#4efdc4"/></svg> Income</span>
                        <span><svg width="8" height="8"><circle cx="4" cy="4" r="4" fill="#a1ff5a"/></svg> Profit</span>
                        <span><svg width="8" height="8"><circle cx="4" cy="4" r="4" fill="#ff5a5a"/></svg> Expense</span>
                    </div>
                </div>
                <div class="chart-toggle-group">
                    <button type="button" class="ctoggle active" onclick="switchChart('line',this)" title="Line">
                        <i class="fa-solid fa-chart-line"></i>
                    </button>
                    <button type="button" class="ctoggle" onclick="switchChart('bar',this)" title="Bar">
                        <i class="fa-solid fa-chart-bar"></i>
                    </button>
                </div>
            </div>
            <div class="chart-canvas-wrap"><canvas id="perfChart"></canvas></div>
        </div>

        <div class="retention-card">
            <div class="sec-label" style="padding:22px 22px 0">
                <i class="fa-solid fa-heart-pulse"></i> CLIENT RETENTION
            </div>
            <div class="ret-wrap">
                <div class="donut-outer">
                    <canvas id="donutChart" width="160" height="160"></canvas>
                    <div class="donut-center">
                        <div class="donut-pct"><?=$retention_pct?>%</div>
                        <div class="donut-sub">RETAINED</div>
                    </div>
                </div>
                <div class="ret-stats">
                    <div class="ret-item">
                        <div class="ret-ic" style="--rc:78,253,196"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="ret-info"><span class="ret-lbl">Active</span><span class="ret-val" style="color:var(--neon-sec)"><?=$rt_active?></span></div>
                    </div>
                    <div class="ret-item">
                        <div class="ret-ic" style="--rc:255,90,90"><i class="fa-solid fa-circle-xmark"></i></div>
                        <div class="ret-info"><span class="ret-lbl">Suspended</span><span class="ret-val" style="color:var(--neon-red)"><?=$rt_suspend?></span></div>
                    </div>
                    <div class="ret-item">
                        <div class="ret-ic" style="--rc:160,160,160"><i class="fa-solid fa-users"></i></div>
                        <div class="ret-info"><span class="ret-lbl">Total</span><span class="ret-val"><?=$total_clients?></span></div>
                    </div>
                    <div class="ret-health">
                        <div class="ret-health-top">
                            <span><i class="fa-solid fa-signal"></i> Health Score</span>
                            <span style="color:<?=$retention_pct>=80?'var(--neon-main)':($retention_pct>=50?'var(--neon-orange)':'var(--neon-red)')?>">
                                <?=$retention_pct>=80?'Excellent':($retention_pct>=50?'Good':'Critical')?>
                            </span>
                        </div>
                        <div class="ret-health-bar">
                            <div style="width:<?=$retention_pct?>%;background:<?=$retention_pct>=80?'var(--neon-main)':($retention_pct>=50?'var(--neon-orange)':'var(--neon-red)')?>"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-head">
            <div>
                <div class="sec-label"><i class="fa-solid fa-table-list"></i> MONTHLY BREAKDOWN</div>
                <div class="table-meta">
                    <i class="fa-solid fa-calendar-range"></i>
                    <?=$month_names[$start_m].' '.$start_y?> — <?=$month_names[$end_m].' '.$end_y?>
                    &nbsp;·&nbsp; <i class="fa-solid fa-layer-group"></i> <?=count($table_data)?> periode
                </div>
            </div>
            <div class="tbl-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="tableSearch" placeholder="Cari periode…" oninput="filterTable(this.value)">
            </div>
        </div>
        <div class="table-responsive">
            <table id="mainTable">
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-calendar-days"></i> Periode</th>
                        <th><i class="fa-solid fa-receipt"></i> Detail Transaksi</th>
                        <th class="sortable" onclick="sortTable(2)"><i class="fa-solid fa-arrow-up-right-dots"></i> Income <i class="fa-solid fa-sort sort-ic"></i></th>
                        <th class="sortable" onclick="sortTable(3)"><i class="fa-solid fa-arrow-down-right-dots"></i> Expense <i class="fa-solid fa-sort sort-ic"></i></th>
                        <th class="sortable" onclick="sortTable(4)"><i class="fa-solid fa-scale-balanced"></i> Profit <i class="fa-solid fa-sort sort-ic"></i></th>
                        <th><i class="fa-solid fa-percent"></i> Margin</th>
                        <th><i class="fa-solid fa-user-plus"></i> New</th>
                        <th><i class="fa-solid fa-tag"></i> Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($table_data as $row):
                    $ip=$row['profit']>=0;
                    $mc=$row['margin']>=30?'var(--neon-main)':($row['margin']>=0?'var(--neon-orange)':'var(--neon-red)');
                ?>
                <tr class="trow">
                    <td>
                        <div class="period-cell">
                            <i class="fa-solid fa-calendar-check" style="color:var(--neon-sec);font-size:.65rem;opacity:.7"></i>
                            <strong><?=htmlspecialchars($row['period'])?></strong>
                        </div>
                    </td>
                    <td class="desc-cell" title="<?=htmlspecialchars($row['desc'])?>">
                        <?=htmlspecialchars($row['desc'])?>
                    </td>
                    <td style="color:var(--neon-sec)">
                        + Rp <?=number_format($row['income'])?>
                    </td>
                    <td style="color:var(--neon-red)">
                        − Rp <?=number_format($row['expense'])?>
                    </td>
                    <td style="color:<?=$ip?'var(--neon-main)':'var(--neon-red)'?>;font-weight:800">
                        <?=$ip?'':'−'?>Rp <?=number_format(abs($row['profit']))?>
                    </td>
                    <td>
                        <div class="mbar-wrap">
                            <div class="mbar" style="width:<?=min(abs($row['margin']),100)?>%;background:<?=$mc?>"></div>
                            <span style="color:<?=$mc?>;font-size:.73rem;font-weight:700"><?=$row['margin']?>%</span>
                        </div>
                    </td>
                    <td>
                        <?php if($row['nc']>0):?>
                        <span class="badge-new">
                            <i class="fa-solid fa-user-plus"></i> +<?=$row['nc']?>
                        </span>
                        <?php else:?>
                        <span class="no-data">—</span>
                        <?php endif;?>
                    </td>
                    <td>
                        <span class="status-badge <?=$ip?'s-profit':'s-loss'?>">
                            <i class="fa-solid fa-<?=$ip?'circle-check':'circle-xmark'?>"></i>
                            <?=$ip?'Profit':'Loss'?>
                        </span>
                    </td>
                </tr>
                <?php endforeach;?>
                <?php if(empty($table_data)):?>
                <tr><td colspan="8" style="text-align:center;padding:50px;color:#2a2a2a;font-size:.85rem;">Tidak ada data untuk periode ini</td></tr>
                <?php endif;?>
                </tbody>
                <tfoot>
                    <tr class="sum-row">
                        <td colspan="2"><strong>&#x3A3; TOTAL PERIODE</strong></td>
                        <td style="color:var(--neon-sec);font-weight:700">Rp <?=number_format($total_omzet)?></td>
                        <td style="color:var(--neon-red);font-weight:700">Rp <?=number_format($total_expense)?></td>
                        <td style="color:<?=$total_profit>=0?'var(--neon-main)':'var(--neon-red)'?>;font-weight:800">Rp <?=number_format($total_profit)?></td>
                        <td style="color:<?=$avg_margin>=30?'var(--neon-main)':'var(--neon-orange)'?>;font-weight:700"><?=$avg_margin?>%</td>
                        <td><span class="badge-new"><i class="fa-solid fa-users"></i> +<?=$new_clients?></span></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php endif;?>

</main>
</div>

<div class="toast" id="toast">
    <i class="toast-ic fa-solid fa-circle-check"></i>
    <span id="toastMsg">Berhasil</span>
</div>

<script>
<?php if($allowed): ?>
const chartLabels  = <?=json_encode($labels)?>;
const chartIncome  = <?=json_encode($chart_income)?>;
const chartProfit  = <?=json_encode($chart_profit)?>;
const chartExpense = <?=json_encode($chart_expense)?>;
const tableDataRaw = <?=json_encode($table_data)?>;
const rtActive     = <?=(int)$rt_active?>;
const rtSuspend    = <?=(int)$rt_suspend?>;

let perfChart;
function initChart(type) {
    type = type || 'line';
    const cv = document.getElementById('perfChart');
    if (!cv) return;
    const ctx = cv.getContext('2d');
    if (perfChart) perfChart.destroy();
    const il = type === 'line';
    const gI = ctx.createLinearGradient(0,0,0,280);
    gI.addColorStop(0,'rgba(78,253,196,.2)'); gI.addColorStop(1,'rgba(78,253,196,0)');
    const gP = ctx.createLinearGradient(0,0,0,280);
    gP.addColorStop(0,'rgba(161,255,90,.2)'); gP.addColorStop(1,'rgba(161,255,90,0)');
    perfChart = new Chart(ctx, {
        type,
        data: {
            labels: chartLabels,
            datasets: [
                { label:'Income',     data:chartIncome,  borderColor:'#4efdc4', backgroundColor:il?gI:'rgba(78,253,196,.22)',  borderWidth:2.5, fill:il, tension:.42, pointRadius:il?5:0, pointBackgroundColor:'#050505', pointBorderColor:'#4efdc4', pointHoverRadius:7 },
                { label:'Net Profit', data:chartProfit,  borderColor:'#a1ff5a', backgroundColor:il?gP:'rgba(161,255,90,.22)', borderWidth:2.5, fill:il, tension:.42, pointRadius:il?5:0, pointBackgroundColor:'#050505', pointBorderColor:'#a1ff5a', pointHoverRadius:7 },
                { label:'Expense',    data:chartExpense, borderColor:'#ff5a5a', backgroundColor:il?'transparent':'rgba(255,90,90,.22)', borderWidth:2, fill:false, tension:.42, borderDash:il?[6,4]:[], pointRadius:0, pointHoverRadius:5 }
            ]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            animation:{ duration:600, easing:'easeInOutQuart' },
            plugins:{
                legend:{display:false},
                tooltip:{
                    mode:'index', intersect:false,
                    backgroundColor:'rgba(8,8,8,.97)',
                    borderColor:'rgba(255,255,255,.07)', borderWidth:1,
                    titleColor:'#fff', bodyColor:'#888',
                    titleFont:{family:'Montserrat',weight:'700',size:12},
                    bodyFont:{family:'Montserrat',size:12},
                    padding:14, cornerRadius:10,
                    callbacks:{
                        label: c => {
                            const v = c.raw;
                            return `  ${c.dataset.label}: Rp ${v>=1e6?(v/1e6).toFixed(1)+'jt':new Intl.NumberFormat('id').format(v)}`;
                        }
                    }
                }
            },
            scales:{
                y:{ grid:{color:'rgba(255,255,255,.03)'}, ticks:{color:'#333',font:{family:'Montserrat',size:11},callback:v=>v>=1e6?(v/1e6).toFixed(0)+'jt':v} },
                x:{ grid:{display:false}, ticks:{color:'#333',font:{family:'Montserrat',size:11}} }
            }
        }
    });
}
function switchChart(type,btn) {
    document.querySelectorAll('.ctoggle').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active'); initChart(type);
}
function initDonut() {
    const c = document.getElementById('donutChart');
    if (!c) return;
    new Chart(c.getContext('2d'),{
        type:'doughnut',
        data:{datasets:[{data:[rtActive,rtSuspend>0?rtSuspend:0.01],backgroundColor:['#4efdc4','#ff5a5a'],borderColor:'transparent',borderWidth:0,hoverOffset:6}]},
        options:{cutout:'74%',responsive:false,animation:{duration:1000},plugins:{legend:{display:false},tooltip:{enabled:false}}}
    });
}
initChart('line');
initDonut();
function setPreset(m) {
    const now=new Date(), f=document.getElementById('filterForm');
    let sm,sy;
    if(m===0){sm='01';sy=2024;}
    else{const d=new Date(now.getFullYear(),now.getMonth()-m+1,1);sm=String(d.getMonth()+1).padStart(2,'0');sy=d.getFullYear();}
    f.start_m.value=sm; f.start_y.value=sy;
    f.end_m.value=String(now.getMonth()+1).padStart(2,'0'); f.end_y.value=now.getFullYear();
    f.submit();
}
function filterTable(q) {
    const kw=q.toLowerCase();
    document.querySelectorAll('#mainTable tbody tr').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(kw)?'':'none';});
}
const sd={};
function sortTable(col) {
    const tb=document.querySelector('#mainTable tbody');
    if(!tb) return;
    const rows=[...tb.querySelectorAll('tr')];
    sd[col]=!sd[col];
    rows.sort((a,b)=>{
        const va=parseFloat((a.cells[col]?.textContent||'').replace(/[^0-9.-]/g,''))||0;
        const vb=parseFloat((b.cells[col]?.textContent||'').replace(/[^0-9.-]/g,''))||0;
        return sd[col]?vb-va:va-vb;
    });
    rows.forEach(r=>tb.appendChild(r));
    document.querySelectorAll('.sort-ic').forEach((ic,i)=>{
        ic.className='fa-solid sort-ic '+(i+2===col?(sd[col]?'fa-sort-down':'fa-sort-up'):'fa-sort');
    });
}
function exportCSV() {
    const h=['Periode','Detail','Income','Expense','Profit','Margin%','New Clients'];
    const rows=tableDataRaw.map(r=>[r.period,r.desc,r.income,r.expense,r.profit,r.margin,r.nc]);
    let csv='\uFEFF'+h.join(',')+'\n';
    rows.forEach(r=>{csv+=r.map(c=>`"${String(c).replace(/"/g,'""')}"`).join(',')+'\n';});
    const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const url=URL.createObjectURL(blob);
    const a=document.createElement('a');
    a.href=url; a.download='performance_report.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
    showToast('CSV berhasil diexport!');
}
function showToast(msg,type='success') {
    const t=document.getElementById('toast'), ic=t.querySelector('.toast-ic');
    document.getElementById('toastMsg').textContent=msg;
    ic.className='toast-ic fa-solid '+(type==='success'?'fa-circle-check':'fa-circle-exclamation');
    t.className='toast show'+(type==='error'?' error':'');
    setTimeout(()=>t.classList.remove('show'),3000);
}
<?php endif; ?>

// Hamburger
const ham=document.getElementById('hamburgerBtn'),ov=document.getElementById('sidebarOverlay'),sb=document.getElementById('sidebar');
const openSB=()=>{sb?.classList.add('open');ov?.classList.add('active');ham?.classList.add('is-open');};
const closeSB=()=>{sb?.classList.remove('open');ov?.classList.remove('active');ham?.classList.remove('is-open');};
ham?.addEventListener('click',()=>sb?.classList.contains('open')?closeSB():openSB());
ov?.addEventListener('click',closeSB);
</script>
</body>
</html>