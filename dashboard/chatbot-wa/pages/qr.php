<?php
/* ==========================================================================
   NEBULA TITAN CONNECTIVITY V8 — OBSIDIAN LINK PRIME
   QR Authentication | Session Management | System Heartbeat
   Auto-Polling | Live Stats | Mobile-First | Anti-Error
   HVM Digital © 2025
   ========================================================================== */

if (!defined('DB_NAME')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
}

// ── AUTO-MIGRATE: Pastikan tabel & kolom ada ─────────────────────
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS bot_settings (
    `key`   VARCHAR(100) PRIMARY KEY,
    `value` TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── STATUS API (dipanggil oleh JS polling) ──────────────────────
if (isset($_GET['check_status'])) {
    header('Content-Type: application/json');

    $qrPath    = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/chatbot-wa/qrcode.png';
    $hasQr     = file_exists($qrPath);
    $qrAge     = $hasQr ? (time() - filemtime($qrPath)) : 9999;

    $settings  = [];
    $sr = @mysqli_query($conn, "SELECT `key`, `value` FROM bot_settings");
    if ($sr) while ($r = mysqli_fetch_assoc($sr)) $settings[$r['key']] = $r['value'];

    $status    = $settings['bot_status'] ?? 'offline';
    $loggedIn  = ($status === 'online' && !$hasQr);

    echo json_encode([
        'logged_in'  => $loggedIn,
        'has_qr'     => $hasQr,
        'qr_age'     => $qrAge,
        'bot_status' => $status,
        'battery'    => (int)($settings['battery']     ?? 0),
        'charging'   => (int)($settings['is_charging'] ?? 0),
        'device'     => $settings['device']            ?? 'Unknown',
        'uptime'     => $settings['uptime']            ?? '-',
        'ts'         => time(),
    ]);
    exit;
}

// ── QR REFRESH: Paksa generate QR baru ──────────────────────────
if (isset($_GET['refresh_qr'])) {
    header('Content-Type: application/json');
    // Signal ke restart.lock agar bot.js generate ulang QR
    $lockPath = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/chatbot-wa/restart.lock';
    @file_put_contents($lockPath, 'restart');
    echo json_encode(['ok' => true, 'msg' => 'QR refresh signal sent']);
    exit;
}

// ── LOAD SETTINGS ───────────────────────────────────────────────
$qrPath       = $_SERVER['DOCUMENT_ROOT'] . '/dashboard/chatbot-wa/qrcode.png';
$isWaitingScan = file_exists($qrPath);
$qrAge         = $isWaitingScan ? (time() - filemtime($qrPath)) : 0;
$qrExpired     = $qrAge > 55; // QR WA expire ~60 detik

$settings = [];
$sr = @mysqli_query($conn, "SELECT `key`, `value` FROM bot_settings");
if ($sr) while ($r = mysqli_fetch_assoc($sr)) $settings[$r['key']] = $r['value'];

$botStatus  = $settings['bot_status']  ?? 'offline';
$battery    = (int)($settings['battery']     ?? 0);
$isCharging = (int)($settings['is_charging'] ?? 0);
$device     = $settings['device']     ?? 'Titan PC-Server';
$uptime     = $settings['uptime']     ?? '-';

// Battery icon logic
$batIcon = $battery >= 80 ? 'full' : ($battery >= 50 ? 'three-quarters' : ($battery >= 25 ? 'half' : ($battery >= 10 ? 'quarter' : 'empty')));

// Session info from DB
$totalRooms = (int)(@mysqli_fetch_row(@mysqli_query($conn, "SELECT COUNT(DISTINCT sender_wa) FROM chat_memories"))[0] ?? 0);
$totalMsgs  = (int)(@mysqli_fetch_row(@mysqli_query($conn, "SELECT COUNT(*) FROM chat_memories"))[0] ?? 0);
$autoTotal  = (int)(@mysqli_fetch_row(@mysqli_query($conn, "SELECT COUNT(*) FROM automation_tasks"))[0] ?? 0);
$autoPending = (int)(@mysqli_fetch_row(@mysqli_query($conn, "SELECT COUNT(*) FROM automation_tasks WHERE status='pending'"))[0] ?? 0);
$todayMsgs  = (int)(@mysqli_fetch_row(@mysqli_query($conn, "SELECT COUNT(*) FROM chat_memories WHERE DATE(created_at)=CURDATE()"))[0] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="theme-color" content="#030306">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ================================================================
   TITAN CONNECTIVITY V8 — SIGNAL NOIR
   Aesthetic: Deep-space terminal with organic neon veins
   Font: Outfit (display) + DM Mono (data)
   Motif: Encrypted pulse, circuit skin, living connection
   ================================================================ */
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=DM+Mono:wght@400;500;600&display=swap');

/* ── TOKENS ────────────────────────────────────────────── */
:root {
    --g:     #a1ff5a;
    --c:     #4efdc4;
    --r:     #ff5a72;
    --a:     #ffb547;
    --v:     #9b7cff;
    --b:     #3d8eff;

    --ink:   #020206;
    --s1:    rgba(255,255,255,0.025);
    --s2:    rgba(255,255,255,0.05);
    --s3:    rgba(255,255,255,0.085);
    --glass: rgba(5,5,12,0.9);
    --border:  rgba(255,255,255,0.065);
    --border2: rgba(255,255,255,0.12);

    --text:  #dce2ea;
    --dim:   #424752;
    --mid:   #7a8090;

    --grad:  linear-gradient(135deg, #a1ff5a, #4efdc4);
    --grad2: linear-gradient(135deg, #ff5a72, #ffb547);
    --grad3: linear-gradient(135deg, #9b7cff, #4efdc4);

    --sh:    0 24px 64px rgba(0,0,0,0.7);

    --rx:    32px;
    --rl:    22px;
    --rm:    14px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Outfit', sans-serif;
    color: var(--text);
    background: transparent;
    -webkit-font-smoothing: antialiased;
}

::-webkit-scrollbar       { width: 3px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius:3px; }

/* ── VIEWPORT ──────────────────────────────────────────── */
.vp {
    padding: 12px 28px 60px 108px;
    animation: vpIn 0.7s cubic-bezier(0.16,1,0.3,1) both;
}

@keyframes vpIn { from{opacity:0;transform:translateY(16px);} to{opacity:1;transform:translateY(0);} }

/* ── PAGE HEADER ───────────────────────────────────────── */
.pg-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 14px;
}

.pg-title h1 {
    font-size: 2.1rem;
    font-weight: 900;
    letter-spacing: -1.5px;
    background: var(--grad);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
}

.pg-title p {
    font-size: 0.78rem;
    color: var(--dim);
    font-weight: 500;
    margin-top: 5px;
}

/* LIVE CLOCK */
.pg-clock {
    font-family: 'DM Mono', monospace;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--c);
    letter-spacing: -0.5px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.pg-clock small {
    font-size: 0.58rem;
    color: var(--dim);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-top: 2px;
}

/* ── QUICK STATS ───────────────────────────────────────── */
.qs-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    margin-bottom: 24px;
}

.qs-card {
    background: var(--s1);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: border-color 0.3s, background 0.3s;
    position: relative;
    overflow: hidden;
}

.qs-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--grad);
    opacity: 0;
    transition: opacity 0.3s;
    border-radius: inherit;
}

.qs-card:hover { border-color: var(--border2); }
.qs-card:hover::before { opacity: 0.025; }

.qs-icon {
    width: 40px; height: 40px;
    border-radius: 12px;
    display: flex; align-items:center; justify-content:center;
    font-size: 1rem;
    flex-shrink: 0;
    position: relative; z-index: 1;
}

.qs-body { position: relative; z-index: 1; }

.qs-label {
    display: block;
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--dim);
    margin-bottom: 2px;
}

.qs-val {
    font-size: 1.2rem;
    font-weight: 900;
    color: #fff;
    line-height: 1;
    font-family: 'DM Mono', monospace;
}

/* ── MAIN GRID ─────────────────────────────────────────── */
.main-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
}

/* ── PANEL ─────────────────────────────────────────────── */
.panel {
    background: var(--glass);
    backdrop-filter: blur(50px) saturate(1.4);
    border: 1px solid var(--border);
    border-radius: var(--rx);
    overflow: hidden;
    box-shadow: var(--sh);
}

/* ── LEFT: QR AUTHENTICATION PANEL ────────────────────── */
.auth-panel {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 50px 40px;
    text-align: center;
    position: relative;
    min-height: 480px;
}

/* GLOW AMBIENT */
.auth-panel::before {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle,
        <?= $isWaitingScan ? 'rgba(255,90,114,0.05)' : 'rgba(161,255,90,0.05)' ?>,
        transparent 70%);
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    pointer-events: none;
    animation: ambientPulse 4s ease-in-out infinite;
}

@keyframes ambientPulse {
    0%,100% { transform: translate(-50%,-50%) scale(1); opacity:0.6; }
    50%      { transform: translate(-50%,-50%) scale(1.1); opacity:1; }
}

/* STATUS BADGE */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 9px 22px;
    border-radius: 50px;
    font-size: 0.68rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 32px;
    position: relative;
    z-index: 1;
}

.status-badge.online  {
    background: rgba(161,255,90,0.08);
    border: 1px solid rgba(161,255,90,0.25);
    color: var(--g);
}

.status-badge.waiting {
    background: rgba(255,90,114,0.08);
    border: 1px solid rgba(255,90,114,0.3);
    color: var(--r);
    animation: badgePulse 2s ease-in-out infinite;
}

.status-badge.offline {
    background: rgba(255,181,71,0.08);
    border: 1px solid rgba(255,181,71,0.25);
    color: var(--a);
}

@keyframes badgePulse { 0%,100%{opacity:1;} 50%{opacity:0.65;} }

.status-badge .pulse-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: currentColor;
    animation: dotPulse 1.5s ease-in-out infinite;
}

@keyframes dotPulse {
    0%,100% { box-shadow: 0 0 0 0 currentColor; }
    50%      { box-shadow: 0 0 0 5px transparent; }
}

/* QR FRAME */
.qr-frame {
    position: relative;
    margin-bottom: 32px;
    z-index: 1;
}

.qr-border-wrap {
    padding: 22px;
    background: #000;
    border-radius: 32px;
    display: inline-block;
    border: 2px solid <?= $isWaitingScan ? ($qrExpired ? 'var(--a)' : 'var(--r)') : 'var(--g)' ?>;
    box-shadow: 0 0 50px <?= $isWaitingScan ? ($qrExpired ? 'rgba(255,181,71,0.2)' : 'rgba(255,90,114,0.2)') : 'rgba(161,255,90,0.15)' ?>;
    transition: all 0.5s cubic-bezier(0.175,0.885,0.32,1.275);
    position: relative;
}

/* Corner decorations */
.qr-border-wrap::before,
.qr-border-wrap::after {
    content: '';
    position: absolute;
    width: 20px; height: 20px;
    border-color: <?= $isWaitingScan ? 'var(--r)' : 'var(--g)' ?>;
    border-style: solid;
    transition: border-color 0.5s;
}

.qr-border-wrap::before { top: -1px; left: -1px; border-width: 3px 0 0 3px; border-radius: 4px 0 0 0; }
.qr-border-wrap::after  { bottom: -1px; right: -1px; border-width: 0 3px 3px 0; border-radius: 0 0 4px 0; }

.qr-img {
    display: block;
    border-radius: 18px;
    width: 260px; height: 260px;
    object-fit: contain;
    transition: opacity 0.4s;
}

.qr-img.expired { opacity: 0.35; filter: grayscale(0.5); }

/* QR Timer ring */
.qr-timer-wrap {
    position: absolute;
    top: -16px; right: -16px;
    width: 48px; height: 48px;
    display: <?= $isWaitingScan ? 'flex' : 'none' ?>;
    align-items: center;
    justify-content: center;
}

.qr-timer-ring {
    position: absolute;
    width: 48px; height: 48px;
    border-radius: 50%;
    background: conic-gradient(var(--a) 0%, rgba(255,181,71,0.1) 0%);
    transition: background 0.5s;
}

.qr-timer-val {
    font-family: 'DM Mono', monospace;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--a);
    position: relative; z-index: 1;
}

/* Offline icon */
.offline-icon {
    font-size: 9rem;
    background: var(--grad);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    width: 260px; height: 260px;
    align-items: center;
    justify-content: center;
}

.auth-title {
    font-size: 1.6rem;
    font-weight: 900;
    letter-spacing: -0.5px;
    margin-bottom: 8px;
    position: relative; z-index: 1;
}

.auth-desc {
    font-size: 0.82rem;
    color: var(--mid);
    line-height: 1.65;
    max-width: 320px;
    margin-bottom: 28px;
    position: relative; z-index: 1;
}

/* BUTTONS */
.btn-row {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    max-width: 290px;
    position: relative; z-index: 1;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 14px 24px;
    border-radius: 18px;
    font-family: 'Outfit', sans-serif;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    transition: all 0.28s cubic-bezier(0.16,1,0.3,1);
    border: none;
    outline: none;
    position: relative;
    overflow: hidden;
}

.btn::after {
    content: '';
    position: absolute;
    inset: 0;
    background: #fff;
    opacity: 0;
    transition: opacity 0.2s;
}

.btn:active::after { opacity: 0.08; }
.btn:disabled { opacity: 0.4; pointer-events: none; }

.btn-primary {
    background: var(--grad);
    color: #000;
    box-shadow: 0 8px 24px rgba(78,253,196,0.25);
}

.btn-primary:hover {
    transform: translateY(-2px) scale(1.01);
    box-shadow: 0 14px 32px rgba(78,253,196,0.4);
}

.btn-outline {
    background: transparent;
    color: var(--mid);
    border: 1px solid var(--border2);
}

.btn-outline:hover {
    background: var(--s2);
    border-color: rgba(255,255,255,0.2);
    color: var(--text);
    transform: translateY(-1px);
}

.btn-danger {
    background: transparent;
    color: var(--r);
    border: 1px solid rgba(255,90,114,0.25);
}

.btn-danger:hover {
    background: rgba(255,90,114,0.08);
    border-color: var(--r);
    transform: translateY(-1px);
}

.btn-loading .btn-icon { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── RIGHT PANEL ───────────────────────────────────────── */
.right-col {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* SYSTEM HEARTBEAT */
.heartbeat-card {
    padding: 0;
    overflow: hidden;
}

.hb-header {
    padding: 20px 22px 14px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.hb-title {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--dim);
    display: flex;
    align-items: center;
    gap: 6px;
}

.hb-live {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.6rem;
    font-weight: 700;
    color: var(--g);
}

.hb-live-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--g);
    animation: blink 1.4s ease-in-out infinite;
}

@keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0.3;} }

/* Battery */
.bat-section {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
}

.bat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.bat-label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--mid);
    display: flex;
    align-items: center;
    gap: 7px;
}

.bat-pct {
    font-family: 'DM Mono', monospace;
    font-size: 0.9rem;
    font-weight: 600;
    color: #fff;
}

.bat-track {
    height: 6px;
    background: rgba(255,255,255,0.06);
    border-radius: 50px;
    overflow: hidden;
    position: relative;
}

.bat-fill {
    height: 100%;
    border-radius: 50px;
    background: var(--grad);
    transition: width 0.8s cubic-bezier(0.16,1,0.3,1);
    width: <?= min($battery, 100) ?>%;
    position: relative;
}

.bat-fill::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: batShine 2s ease-in-out infinite;
}

@keyframes batShine {
    from { transform: translateX(-100%); }
    to   { transform: translateX(100%); }
}

/* charging override */
<?php if ($isCharging): ?>
.bat-fill { background: linear-gradient(90deg, var(--c), var(--b)); }
<?php endif; ?>

/* Charging badge */
.charging-badge {
    display: <?= $isCharging ? 'inline-flex' : 'none' ?>;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: rgba(61,142,255,0.1);
    border: 1px solid rgba(61,142,255,0.25);
    border-radius: 50px;
    color: var(--b);
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-left: 6px;
}

/* STAT ROWS */
.stat-rows { padding: 8px 0; }

.stat-row {
    padding: 12px 22px;
    display: flex;
    align-items: center;
    gap: 13px;
    transition: background 0.22s;
    border-bottom: 1px solid var(--border);
}

.stat-row:last-child { border-bottom: none; }
.stat-row:hover { background: var(--s1); }

.stat-row-icon {
    width: 38px; height: 38px;
    border-radius: 12px;
    display: flex; align-items:center; justify-content:center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.stat-row-body { flex: 1; }

.stat-row-label {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--dim);
    display: block;
    margin-bottom: 2px;
}

.stat-row-val {
    font-size: 0.9rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 6px;
}

.stat-row-val .chip {
    font-size: 0.55rem;
    font-weight: 800;
    padding: 1px 7px;
    border-radius: 50px;
    text-transform: uppercase;
}

.chip-ok { background: rgba(161,255,90,0.1); color: var(--g); }
.chip-warn { background: rgba(255,181,71,0.1); color: var(--a); }
.chip-off { background: rgba(255,90,114,0.1); color: var(--r); }

/* SESSION INFO CARD */
.session-card {
    overflow: hidden;
}

.session-header {
    padding: 16px 20px 12px;
    border-bottom: 1px solid var(--border);
}

.session-body { padding: 8px; }

.session-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 12px;
    border-radius: 12px;
    transition: background 0.2s;
    gap: 10px;
}

.session-item:hover { background: var(--s1); }

.session-key {
    font-size: 0.62rem;
    color: var(--dim);
    font-weight: 600;
    flex-shrink: 0;
}

.session-val {
    font-size: 0.72rem;
    color: var(--text);
    font-weight: 700;
    text-align: right;
    word-break: break-all;
    max-width: 180px;
    font-family: 'DM Mono', monospace;
}

/* TIP CARD */
.tip-card {
    background: rgba(161,255,90,0.04);
    border: 1px dashed rgba(161,255,90,0.2);
    border-radius: var(--rl);
    padding: 16px 18px;
}

.tip-header {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--g);
    margin-bottom: 8px;
}

.tip-text {
    font-size: 0.72rem;
    color: var(--mid);
    line-height: 1.65;
}

/* ── LAST ACTIVITY LOG ─────────────────────────────────── */
.activity-section {
    margin-top: 20px;
}

.activity-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.activity-title {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--dim);
    display: flex;
    align-items: center;
    gap: 6px;
}

.activity-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
}

.activity-item {
    background: var(--s1);
    border: 1px solid var(--border);
    border-radius: var(--rm);
    padding: 12px 14px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    transition: border-color 0.22s;
}

.activity-item:hover { border-color: var(--border2); }

.act-icon {
    width: 32px; height: 32px;
    border-radius: 10px;
    display: flex; align-items:center; justify-content:center;
    font-size: 0.78rem;
    flex-shrink: 0;
    background: rgba(78,253,196,0.08);
    color: var(--c);
}

.act-body {}
.act-label { font-size: 0.62rem; color: var(--dim); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.act-val   { font-size: 0.82rem; font-weight: 800; color: #fff; margin-top: 1px; }

/* ── TOAST ─────────────────────────────────────────────── */
#toasts {
    position: fixed;
    bottom: 20px; right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column-reverse;
    gap: 8px;
    pointer-events: none;
}

.toast {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: rgba(6,6,16,0.97);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border2);
    border-radius: 18px;
    min-width: 250px;
    max-width: 350px;
    box-shadow: 0 16px 40px rgba(0,0,0,0.5);
    animation: tIn 0.35s cubic-bezier(0.34,1.56,0.64,1) both;
    pointer-events: all;
    position: relative;
    overflow: hidden;
}

@keyframes tIn  { from{opacity:0;transform:translateX(30px)scale(0.9);} to{opacity:1;transform:translateX(0)scale(1);} }
@keyframes tOut { to{opacity:0;transform:translateX(30px);} }
.toast.out { animation: tOut 0.25s ease forwards; }

.toast::after {
    content:''; position:absolute; bottom:0; left:0;
    height:2px; width:100%;
    animation: tbar 3.5s linear forwards;
}

.toast.s::after { background:var(--g); }
.toast.e::after { background:var(--r); }
.toast.i::after { background:var(--c); }
.toast.w::after { background:var(--a); }
@keyframes tbar { from{width:100%;} to{width:0;} }

.toast i { font-size:0.9rem; flex-shrink:0; }
.toast.s i { color:var(--g); }
.toast.e i { color:var(--r); }
.toast.i i { color:var(--c); }
.toast.w i { color:var(--a); }

.toast-body { flex:1; }
.toast-title { font-weight:800; font-size:0.76rem; }
.toast-msg   { font-size:0.65rem; color:var(--dim); margin-top:1px; }

/* ── MODAL ─────────────────────────────────────────────── */
#modal-bg {
    position: fixed; inset:0;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(12px);
    z-index: 9000;
    display: flex; align-items:center; justify-content:center;
    opacity:0; pointer-events:none;
    transition: opacity 0.28s;
}

#modal-bg.open { opacity:1; pointer-events:all; }

.modal {
    background: rgba(8,8,18,0.98);
    border: 1px solid var(--border2);
    border-radius: 28px;
    padding: 36px;
    max-width: 400px;
    width: 92%;
    box-shadow: 0 40px 80px rgba(0,0,0,0.7);
    transform: scale(0.9);
    transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
    text-align: center;
}

#modal-bg.open .modal { transform: scale(1); }

.modal-icon {
    width: 60px; height: 60px;
    border-radius: 18px;
    display: flex; align-items:center; justify-content:center;
    font-size: 1.6rem;
    margin: 0 auto 18px;
}

.modal-icon.danger { background: rgba(255,90,114,0.1); color: var(--r); }
.modal-icon.warn   { background: rgba(255,181,71,0.1);  color: var(--a); }

.modal h3 { font-weight:900; font-size:1.2rem; margin-bottom:8px; letter-spacing:-0.3px; }
.modal p  { font-size:0.78rem; color:var(--mid); line-height:1.65; margin-bottom:24px; }
.modal-btns { display:flex; gap:10px; }
.modal-btns .btn { flex:1; border-radius:14px; }

/* ── PROGRESS RING (QR Countdown) ──────────────────────── */
.qr-countdown {
    display: <?= $isWaitingScan ? 'flex' : 'none' ?>;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: -16px;
    margin-bottom: 16px;
    font-size: 0.68rem;
    color: var(--a);
    font-weight: 700;
    font-family: 'DM Mono', monospace;
}

/* ── MOBILE ────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .main-grid { grid-template-columns: 1fr; }
    .right-col { flex-direction: row; flex-wrap: wrap; }
    .right-col > * { flex: 1 1 300px; }
}

@media (max-width: 900px) {
    .vp { padding: 10px 16px 80px 16px; }
    .qs-row { grid-template-columns: repeat(3, 1fr); }
    .auth-panel { padding: 36px 24px; min-height: auto; }
    .qr-img { width: 220px; height: 220px; }
    .offline-icon { width: 220px; height: 220px; font-size: 7rem; }
    .pg-header { flex-direction: column; align-items: flex-start; }
    .right-col { flex-direction: column; }
}

@media (max-width: 600px) {
    .qs-row { grid-template-columns: repeat(2, 1fr); }
    .qs-row .qs-card:last-child { display: none; }
    .activity-list { grid-template-columns: 1fr 1fr; }
    .btn-row { max-width: 100%; }
    .main-grid { gap: 14px; }
}
</style>

<!-- ═══ MARKUP ═══════════════════════════════════════════════════ -->
<div class="vp">

    <!-- PAGE HEADER -->
    <div class="pg-header">
        <div class="pg-title">
            <h1>Titan Connectivity</h1>
            <p>Nebula Core session management · Encrypted WhatsApp link · HVM Digital</p>
        </div>
        <div class="pg-clock">
            <span id="pgClock">--:--:--</span>
            <small id="pgDate">Loading...</small>
        </div>
    </div>

    <!-- QUICK STATS -->
    <div class="qs-row">
        <div class="qs-card">
            <div class="qs-icon" style="background:rgba(161,255,90,0.08); color:var(--g);">
                <i class="fas fa-comments"></i>
            </div>
            <div class="qs-body">
                <span class="qs-label">Rooms</span>
                <div class="qs-val" id="qsTotalRooms"><?= number_format($totalRooms) ?></div>
            </div>
        </div>
        <div class="qs-card">
            <div class="qs-icon" style="background:rgba(78,253,196,0.08); color:var(--c);">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="qs-body">
                <span class="qs-label">Total Msgs</span>
                <div class="qs-val" id="qsTotalMsgs"><?= number_format($totalMsgs) ?></div>
            </div>
        </div>
        <div class="qs-card">
            <div class="qs-icon" style="background:rgba(255,181,71,0.08); color:var(--a);">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="qs-body">
                <span class="qs-label">Hari Ini</span>
                <div class="qs-val" id="qsToday"><?= number_format($todayMsgs) ?></div>
            </div>
        </div>
        <div class="qs-card">
            <div class="qs-icon" style="background:rgba(155,124,255,0.08); color:var(--v);">
                <i class="fas fa-robot"></i>
            </div>
            <div class="qs-body">
                <span class="qs-label">Automation</span>
                <div class="qs-val" id="qsAuto"><?= number_format($autoTotal) ?></div>
            </div>
        </div>
        <div class="qs-card">
            <div class="qs-icon" style="background:rgba(61,142,255,0.08); color:var(--b);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="qs-body">
                <span class="qs-label">Pending</span>
                <div class="qs-val" id="qsPending" style="<?= $autoPending > 0 ? 'color:var(--a)' : '' ?>"><?= $autoPending ?></div>
            </div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="main-grid">

        <!-- LEFT: AUTH PANEL -->
        <div class="panel auth-panel">

            <?php if (!$isWaitingScan && $botStatus === 'online'): ?>
            <!-- ── STATE: ONLINE ── -->
            <div class="status-badge online">
                <span class="pulse-dot"></span>
                Nebula Core Connected
            </div>
            <div class="qr-frame">
                <div class="qr-border-wrap">
                    <div class="offline-icon"><i class="fas fa-robot"></i></div>
                </div>
            </div>
            <h2 class="auth-title">System Operational</h2>
            <p class="auth-desc">
                Sesi WhatsApp Bapak <strong>Ilham</strong> telah terenkripsi dan terhubung sempurna ke Nebula Core.
                Bot aktif dan berjalan.
            </p>
            <div class="btn-row">
                <button class="btn btn-outline" onclick="doRefreshStatus()">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh Status
                </button>
                <a href="?page=command" class="btn btn-primary" style="text-decoration:none;">
                    <i class="fas fa-terminal"></i> Buka Command Center
                </a>
                <button class="btn btn-danger" onclick="openResetModal()">
                    <i class="fas fa-sign-out-alt"></i> Putuskan & Reset Sesi
                </button>
            </div>

            <?php elseif ($isWaitingScan): ?>
            <!-- ── STATE: WAITING SCAN ── -->
            <div class="status-badge <?= $qrExpired ? 'offline' : 'waiting' ?>">
                <span class="pulse-dot"></span>
                <?= $qrExpired ? 'QR Expired — Harap Refresh' : 'Menunggu Scan WhatsApp' ?>
            </div>

            <div class="qr-frame">
                <div class="qr-border-wrap" id="qrBorderWrap">
                    <img class="qr-img <?= $qrExpired ? 'expired' : '' ?>"
                         id="qrImg"
                         src="qrcode.png?t=<?= time() ?>"
                         alt="QR Code Nebula"
                         width="260" height="260">
                    <div class="qr-timer-wrap">
                        <div class="qr-timer-ring" id="qrRing"></div>
                        <span class="qr-timer-val" id="qrTimerVal"><?= max(0, 60 - $qrAge) ?>s</span>
                    </div>
                </div>
            </div>

            <div class="qr-countdown" id="qrCountdown">
                <i class="fas fa-clock"></i>
                QR expire dalam <span id="qrCountdownVal" style="color:var(--r)"><?= max(0, 60 - $qrAge) ?></span> detik
            </div>

            <h2 class="auth-title">Scan to Connect</h2>
            <p class="auth-desc">
                Buka WhatsApp Bisnis → <strong>Perangkat Tertaut</strong> → <strong>Tautkan Perangkat</strong>
                lalu arahkan kamera ke kode QR di atas.
            </p>
            <div class="btn-row">
                <button class="btn btn-primary" onclick="doRefreshQR()" id="refreshQrBtn">
                    <i class="fas fa-qrcode" id="refreshQrIcon"></i> Generate QR Baru
                </button>
                <button class="btn btn-outline" onclick="doRefreshStatus()">
                    <i class="fas fa-sync-alt"></i> Cek Status Manual
                </button>
            </div>

            <?php else: ?>
            <!-- ── STATE: OFFLINE ── -->
            <div class="status-badge offline">
                <span class="pulse-dot"></span>
                Bot Offline
            </div>
            <div class="qr-frame">
                <div class="qr-border-wrap">
                    <div class="offline-icon"><i class="fas fa-power-off"></i></div>
                </div>
            </div>
            <h2 class="auth-title">Nebula Offline</h2>
            <p class="auth-desc">
                Bot tidak aktif. Jalankan <code style="background:rgba(255,255,255,0.05);padding:2px 6px;border-radius:5px;font-size:0.85em;">pm2 start bot.js</code>
                di server, atau restart engine melalui panel di bawah.
            </p>
            <div class="btn-row">
                <button class="btn btn-primary" onclick="doRefreshStatus()">
                    <i class="fas fa-sync-alt"></i> Cek Status
                </button>
                <button class="btn btn-danger" onclick="openResetModal()">
                    <i class="fas fa-power-off"></i> Reset & Restart Engine
                </button>
            </div>
            <?php endif; ?>

        </div>

        <!-- RIGHT COL -->
        <div class="right-col">

            <!-- SYSTEM HEARTBEAT CARD -->
            <div class="panel heartbeat-card">
                <div class="hb-header">
                    <div class="hb-title"><i class="fas fa-heartbeat"></i> System Heartbeat</div>
                    <div class="hb-live"><span class="hb-live-dot"></span> Live</div>
                </div>

                <!-- Battery -->
                <div class="bat-section">
                    <div class="bat-row">
                        <div class="bat-label">
                            <i class="fas fa-battery-<?= $batIcon ?>"></i>
                            Power Status
                            <span class="charging-badge"><i class="fas fa-bolt"></i> Charging</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div class="bat-pct" id="batPct"><?= $battery ?>%</div>
                        </div>
                    </div>
                    <div class="bat-track">
                        <div class="bat-fill" id="batFill" style="width:<?= min($battery,100) ?>%"></div>
                    </div>
                </div>

                <!-- Stat Rows -->
                <div class="stat-rows">
                    <div class="stat-row">
                        <div class="stat-row-icon" style="background:rgba(61,142,255,0.08);color:var(--b);">
                            <i class="fas fa-mobile-android"></i>
                        </div>
                        <div class="stat-row-body">
                            <span class="stat-row-label">Active Device</span>
                            <div class="stat-row-val" id="srDevice"><?= htmlspecialchars($device) ?></div>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-row-icon" style="background:rgba(161,255,90,0.08);color:var(--g);">
                            <i class="fas fa-wifi"></i>
                        </div>
                        <div class="stat-row-body">
                            <span class="stat-row-label">Connection</span>
                            <div class="stat-row-val">
                                Encrypted
                                <span class="chip chip-ok">TLS</span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-row-icon" style="background:rgba(78,253,196,0.08);color:var(--c);">
                            <i class="fas fa-server"></i>
                        </div>
                        <div class="stat-row-body">
                            <span class="stat-row-label">Bot Engine</span>
                            <div class="stat-row-val">
                                <span id="srBotStatus"><?= ucfirst($botStatus) ?></span>
                                <span class="chip <?= $botStatus === 'online' ? 'chip-ok' : 'chip-off' ?>" id="srBotChip">
                                    <?= $botStatus === 'online' ? 'Running' : 'Stopped' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-row-icon" style="background:rgba(155,124,255,0.08);color:var(--v);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-row-body">
                            <span class="stat-row-label">Uptime</span>
                            <div class="stat-row-val" id="srUptime"><?= htmlspecialchars($uptime ?: '-') ?></div>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-row-icon" style="background:rgba(255,181,71,0.08);color:var(--a);">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div class="stat-row-body">
                            <span class="stat-row-label">Session</span>
                            <div class="stat-row-val">
                                <?= $isWaitingScan ? 'Awaiting Auth' : ($botStatus === 'online' ? 'Active' : 'None') ?>
                                <span class="chip <?= (!$isWaitingScan && $botStatus === 'online') ? 'chip-ok' : 'chip-warn' ?>">
                                    <?= (!$isWaitingScan && $botStatus === 'online') ? 'Secure' : 'Pending' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SESSION INFO CARD -->
            <div class="panel session-card">
                <div class="session-header">
                    <div class="hb-title"><i class="fas fa-key"></i> Session Info</div>
                </div>
                <div class="session-body" id="sessionInfo">
                    <div class="session-item">
                        <span class="session-key">Bot Number</span>
                        <span class="session-val">6285179982373</span>
                    </div>
                    <div class="session-item">
                        <span class="session-key">Founder</span>
                        <span class="session-val">6285895029511</span>
                    </div>
                    <div class="session-item">
                        <span class="session-key">PA (Via)</span>
                        <span class="session-val">6282131947213</span>
                    </div>
                    <div class="session-item">
                        <span class="session-key">Model AI</span>
                        <span class="session-val">gemini-2.0-flash</span>
                    </div>
                    <div class="session-item">
                        <span class="session-key">QR Status</span>
                        <span class="session-val" id="siQrStatus">
                            <?= $isWaitingScan ? '⏳ Waiting' : '✅ Linked' ?>
                        </span>
                    </div>
                    <div class="session-item">
                        <span class="session-key">Last Check</span>
                        <span class="session-val" id="siLastCheck"><?= date('H:i:s') ?></span>
                    </div>
                </div>
            </div>

            <!-- TIP CARD -->
            <div class="tip-card">
                <div class="tip-header"><i class="fas fa-lightbulb"></i> Tips Koneksi</div>
                <div class="tip-text">
                    <b style="color:var(--g)">Bot lambat?</b> Gunakan <b>Restart Engine</b> di Command Center.
                    QR expire tiap 60 detik — klik <b>Generate QR Baru</b> jika kode mati.
                    Pastikan laptop server <b>tidak dalam mode sleep</b>.
                </div>
            </div>

        </div>
    </div>

    <!-- ACTIVITY LOG SECTION -->
    <div class="activity-section">
        <div class="activity-header">
            <div class="activity-title"><i class="fas fa-chart-line"></i> System Activity</div>
            <button class="btn btn-outline" style="padding:6px 14px;font-size:0.65rem;" onclick="loadActivity()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
        <div class="activity-list" id="activityList">
            <div class="activity-item">
                <div class="act-icon"><i class="fas fa-comments"></i></div>
                <div class="act-body">
                    <div class="act-label">Total Rooms</div>
                    <div class="act-val" id="actRooms"><?= number_format($totalRooms) ?></div>
                </div>
            </div>
            <div class="activity-item">
                <div class="act-icon" style="background:rgba(161,255,90,0.08);color:var(--g);"><i class="fas fa-envelope"></i></div>
                <div class="act-body">
                    <div class="act-label">Total Pesan</div>
                    <div class="act-val" id="actMsgs"><?= number_format($totalMsgs) ?></div>
                </div>
            </div>
            <div class="activity-item">
                <div class="act-icon" style="background:rgba(255,181,71,0.08);color:var(--a);"><i class="fas fa-calendar-day"></i></div>
                <div class="act-body">
                    <div class="act-label">Pesan Hari Ini</div>
                    <div class="act-val" id="actToday"><?= number_format($todayMsgs) ?></div>
                </div>
            </div>
            <div class="activity-item">
                <div class="act-icon" style="background:rgba(155,124,255,0.08);color:var(--v);"><i class="fas fa-robot"></i></div>
                <div class="act-body">
                    <div class="act-label">Automation Sent</div>
                    <div class="act-val" id="actAuto"><?= number_format($autoTotal) ?></div>
                </div>
            </div>
            <div class="activity-item">
                <div class="act-icon" style="background:rgba(61,142,255,0.08);color:var(--b);"><i class="fas fa-hourglass-half"></i></div>
                <div class="act-body">
                    <div class="act-label">Pending Tasks</div>
                    <div class="act-val" id="actPending" style="color:<?= $autoPending > 0 ? 'var(--a)' : 'inherit' ?>"><?= $autoPending ?></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- MODAL RESET KONFIRMASI -->
<div id="modal-bg" onclick="if(event.target===this)closeModal()">
    <div class="modal">
        <div class="modal-icon danger"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Reset Sesi Nebula?</h3>
        <p>Ini akan <strong>memutuskan WhatsApp</strong> dan menghapus sesi dari server.
           Bot akan offline selama ±30 detik, lalu QR baru akan muncul untuk scan ulang.</p>
        <div class="modal-btns">
            <button class="btn btn-outline" onclick="closeModal()">Batal</button>
            <button class="btn btn-danger" onclick="doResetSession()">
                <i class="fas fa-sign-out-alt"></i> Ya, Reset Sekarang
            </button>
        </div>
    </div>
</div>

<!-- TOAST CONTAINER -->
<div id="toasts"></div>

<!-- ═══ JAVASCRIPT ════════════════════════════════════════════════ -->
<script>
/* ================================================================
   TITAN CONNECTIVITY ENGINE V8
   ================================================================ */

const API_BASE = 'pages/qr.php';

let qrCountdown    = <?= max(0, 60 - $qrAge) ?>;
let isWaiting      = <?= $isWaitingScan ? 'true' : 'false' ?>;
let qrExpired      = <?= $qrExpired ? 'true' : 'false' ?>;
let pollTimer      = null;
let countdownTimer = null;
let clockTimer     = null;

// ── UTILS ────────────────────────────────────────────────

function toast(type, title, msg, dur=3200) {
    const ic = { s:'fas fa-check-circle', e:'fas fa-times-circle', i:'fas fa-info-circle', w:'fas fa-exclamation-triangle' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<i class="${ic[type]||ic.i}"></i>
        <div class="toast-body">
            <div class="toast-title">${title}</div>
            <div class="toast-msg">${msg}</div>
        </div>`;
    document.getElementById('toasts').prepend(el);
    setTimeout(() => { el.classList.add('out'); setTimeout(()=>el.remove(),300); }, dur);
}

function $ (id) { return document.getElementById(id); }

// ── CLOCK ────────────────────────────────────────────────

function tickClock() {
    const n = new Date();
    const t = [n.getHours(), n.getMinutes(), n.getSeconds()]
        .map(v => String(v).padStart(2,'0')).join(':');
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    const d = `${days[n.getDay()]}, ${n.getDate()} ${months[n.getMonth()]} ${n.getFullYear()}`;
    const cl = $('pgClock');
    const dt = $('pgDate');
    if (cl) cl.textContent = t;
    if (dt) dt.textContent = d;
}

tickClock();
clockTimer = setInterval(tickClock, 1000);

// ── QR COUNTDOWN ─────────────────────────────────────────

function startQrCountdown(seconds) {
    clearInterval(countdownTimer);
    qrCountdown = seconds;

    function tick() {
        const cv  = $('qrCountdownVal');
        const tv  = $('qrTimerVal');
        const ring = $('qrRing');

        if (cv) cv.textContent = qrCountdown;
        if (tv) tv.textContent = qrCountdown + 's';

        // Update ring
        const pct = Math.max(0, qrCountdown / 60 * 100);
        if (ring) {
            const c = qrCountdown > 20 ? 'var(--a)' : 'var(--r)';
            ring.style.background = `conic-gradient(${c} ${pct}%, rgba(255,255,255,0.05) 0%)`;
        }

        // Color warning
        if (cv) cv.style.color = qrCountdown <= 15 ? 'var(--r)' : 'var(--a)';

        if (qrCountdown <= 0) {
            clearInterval(countdownTimer);
            // Mark QR as expired
            const img = $('qrImg');
            if (img) img.classList.add('expired');
            toast('w', 'QR Expired', 'Kode QR telah expire. Klik Generate QR Baru.');
        } else {
            qrCountdown--;
        }
    }

    tick();
    countdownTimer = setInterval(tick, 1000);
}

// ── STATUS POLLING ────────────────────────────────────────

async function pollStatus() {
    try {
        const r = await fetch(`${API_BASE}?check_status=1`);
        const d = await r.json();

        // Update last check
        const lc = $('siLastCheck');
        if (lc) lc.textContent = new Date().toLocaleTimeString('id-ID');

        // Update battery
        if (d.battery !== undefined) {
            const bf = $('batFill');
            const bp = $('batPct');
            if (bf) bf.style.width = Math.min(d.battery, 100) + '%';
            if (bp) bp.textContent = d.battery + '%';
        }

        // Update device
        const dv = $('srDevice');
        if (dv && d.device) dv.textContent = d.device;

        // Update uptime
        const up = $('srUptime');
        if (up && d.uptime) up.textContent = d.uptime;

        // Update bot status chip
        const bs = $('srBotStatus');
        const bc = $('srBotChip');
        if (bs) bs.textContent = d.bot_status.charAt(0).toUpperCase() + d.bot_status.slice(1);
        if (bc) {
            bc.className = 'chip ' + (d.bot_status === 'online' ? 'chip-ok' : 'chip-off');
            bc.textContent = d.bot_status === 'online' ? 'Running' : 'Stopped';
        }

        // QR status
        const qrs = $('siQrStatus');
        if (qrs) qrs.textContent = d.has_qr ? '⏳ Waiting' : '✅ Linked';

        // If logged in → reload page
        if (d.logged_in && isWaiting) {
            clearInterval(pollTimer);
            clearInterval(countdownTimer);
            toast('s', 'Terhubung!', 'WhatsApp berhasil di-scan. Halaman akan refresh...');
            setTimeout(() => location.reload(), 1500);
        }

    } catch(_) {}
}

// ── ACTIONS ──────────────────────────────────────────────

async function doRefreshStatus() {
    const ic = $('refreshIcon');
    if (ic) ic.className = 'fas fa-spinner fa-spin';
    await pollStatus();
    if (ic) ic.className = 'fas fa-sync-alt';
    toast('i', 'Status Diperbarui', 'Data terbaru berhasil dimuat.');
}

async function doRefreshQR() {
    const btn = $('refreshQrBtn');
    const ic  = $('refreshQrIcon');
    if (btn) btn.disabled = true;
    if (ic)  ic.className  = 'fas fa-spinner fa-spin';

    try {
        const r = await fetch(`${API_BASE}?refresh_qr=1`);
        const d = await r.json();
        if (d.ok) {
            toast('i', 'Generating QR...', 'Bot sedang membuat kode baru. Tunggu 5-10 detik.');
            startQrCountdown(60);

            // Refresh QR image setelah delay
            setTimeout(() => {
                const img = $('qrImg');
                if (img) {
                    img.classList.remove('expired');
                    img.src = 'qrcode.png?t=' + Date.now();
                }
            }, 8000);
        } else {
            toast('e', 'Gagal', 'Tidak bisa mengirim sinyal refresh QR');
        }
    } catch(e) {
        toast('e', 'Error', e.message);
    } finally {
        if (btn) btn.disabled = false;
        if (ic)  ic.className = 'fas fa-qrcode';
    }
}

function openResetModal() {
    $('modal-bg').classList.add('open');
}

function closeModal() {
    $('modal-bg').classList.remove('open');
}

async function doResetSession() {
    closeModal();
    const fd = new FormData();
    fd.append('action', 'reset_session');

    try {
        await fetch('index.php', { method: 'POST', body: fd });
        toast('w', 'Sesi Direset', 'Bot sedang disconnect. QR baru akan muncul dalam ±30 detik.');
        setTimeout(() => location.reload(), 32000);
    } catch(e) {
        toast('e', 'Error', 'Gagal mengirim sinyal reset: ' + e.message);
    }
}

// ── ACTIVITY RELOAD ───────────────────────────────────────

async function loadActivity() {
    // Simple: reload stats via PHP is done by polling.
    // Just refresh the status
    await pollStatus();
    toast('s', 'Refreshed', 'Data aktivitas diperbarui.');
}

// ── INIT ─────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Start QR countdown if waiting
    if (isWaiting && !qrExpired) {
        startQrCountdown(qrCountdown);
    }

    // Auto-poll status setiap 8 detik
    pollTimer = setInterval(pollStatus, 8000);

    // Jika sedang menunggu scan, poll lebih sering
    if (isWaiting) {
        setInterval(pollStatus, 5000);
    }
});
</script>