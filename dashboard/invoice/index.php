<?php include '../sidebar.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice Generator - HVM Digital</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --bg-dark:     #050505;
    --card-bg:     rgba(20,20,20,0.6);
    --card-border: rgba(255,255,255,0.08);
    --neon-main:   #a1ff5a;
    --neon-sec:    #4efdc4;
    --neon-red:    #ff5a5a;
    --neon-orange: #ff9f43;
    --neon-purple: #c084fc;
    --grad-main:   linear-gradient(135deg, #a1ff5a, #4efdc4);
    --text-white:  #ffffff;
    --text-muted:  #a0a0a0;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif; }
body { background:var(--bg-dark); color:var(--text-white); min-height:100vh; overflow-x:hidden; }

.ambient-glow { position:fixed; border-radius:50%; filter:blur(120px); opacity:0.12; z-index:-1; animation:floatGlow 10s infinite alternate; pointer-events:none; }
.glow-1 { top:-100px; left:-100px; width:600px; height:600px; background:var(--neon-main); }
.glow-2 { bottom:-100px; right:-100px; width:600px; height:600px; background:var(--neon-sec); }
@keyframes floatGlow { from{transform:scale(1);}to{transform:scale(1.1);} }

::-webkit-scrollbar { width:6px; height:6px; }
::-webkit-scrollbar-track { background:#0a0a0a; }
::-webkit-scrollbar-thumb { background:#333; border-radius:10px; border:1px solid var(--neon-main); }
::-webkit-scrollbar-thumb:hover { background:var(--neon-main); }

/* ===================== LAYOUT ===================== */
.dashboard-wrapper { display:block; width:100%; min-height:100vh; }

.main-content {
    padding: 32px 40px;
    max-width: 1400px;
    margin: 0 auto;
}

.page-headline { margin-bottom: 28px; }
.page-headline h1 {
    font-size: 2rem; font-weight: 800;
    background: var(--grad-main); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.page-headline p { color: var(--text-muted); font-size: 0.9rem; margin-top: 4px; }

/* STAT CARDS */
.stat-cards-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 16px; padding: 20px 24px;
    display: flex; align-items: center; gap: 16px;
    position: relative; overflow: hidden;
    backdrop-filter: blur(10px);
}
.stat-icon { width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.stat-num { font-size:1.6rem;font-weight:800;line-height:1; }
.stat-label { font-size:0.72rem;color:var(--text-muted);margin-top:4px;font-weight:500;text-transform:uppercase;letter-spacing:0.5px; }
.stat-deco { position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:3rem;font-weight:900;color:rgba(255,255,255,0.03);letter-spacing:-2px; }

/* ACTION BAR */
.action-area { display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap; }
.action-left { display:flex;gap:10px;flex-wrap:wrap; }
.search-glass {
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.08);
    border-radius:10px; padding:10px 16px;
    color:#fff; font-family:inherit; font-size:0.85rem;
    outline:none; width:240px;
    transition: border-color 0.2s;
}
.search-glass:focus { border-color:var(--neon-main); }
.filter-select {
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.08);
    border-radius:10px; padding:10px 14px;
    color:#fff; font-family:inherit; font-size:0.85rem;
    outline:none; cursor:pointer;
}
.btn-neon {
    background: var(--grad-main); color:#000; border:none; border-radius:10px;
    padding:10px 20px; font-family:inherit; font-size:0.85rem; font-weight:700;
    cursor:pointer; display:flex;align-items:center;gap:8px;
    transition: opacity 0.2s, transform 0.1s;
}
.btn-neon:hover { opacity:0.9; transform:translateY(-1px); }
.btn-outline {
    background:transparent; color:var(--neon-main);
    border:1px solid var(--neon-main); border-radius:10px;
    padding:10px 18px; font-family:inherit; font-size:0.85rem; font-weight:600;
    cursor:pointer; display:flex;align-items:center;gap:8px;
    transition: background 0.2s;
}
.btn-outline:hover { background:rgba(161,255,90,0.08); }

/* TABLE */
.invoice-table-wrap { background:var(--card-bg); border:1px solid var(--card-border); border-radius:16px; overflow:hidden; backdrop-filter:blur(10px); }
.invoice-table { width:100%; border-collapse:collapse; }
.invoice-table thead tr { background:rgba(161,255,90,0.05); border-bottom:1px solid rgba(255,255,255,0.06); }
.invoice-table th { padding:14px 18px; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--neon-main); text-align:left; }
.invoice-table tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.15s; }
.invoice-table tbody tr:last-child { border-bottom:none; }
.invoice-table tbody tr:hover { background:rgba(255,255,255,0.03); }
.invoice-table td { padding:14px 18px; font-size:0.83rem; vertical-align:middle; }
.inv-number { font-weight:700; color:var(--neon-main); font-size:0.85rem; }
.inv-client { font-weight:600; }
.inv-client small { display:block; color:var(--text-muted); font-size:0.72rem; font-weight:400; margin-top:2px; }
.inv-date { color:var(--text-muted); font-size:0.8rem; }
.inv-amount { font-weight:700; color:#fff; }
.status-badge { display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:0.72rem;font-weight:700;letter-spacing:0.3px; }
.status-paid    { background:rgba(161,255,90,0.1); color:var(--neon-main); border:1px solid rgba(161,255,90,0.2); }
.status-pending { background:rgba(255,159,67,0.1); color:var(--neon-orange); border:1px solid rgba(255,159,67,0.2); }
.status-dp      { background:rgba(78,253,196,0.1); color:var(--neon-sec); border:1px solid rgba(78,253,196,0.2); }
.status-overdue { background:rgba(255,90,90,0.1); color:var(--neon-red); border:1px solid rgba(255,90,90,0.2); }
.action-btns { display:flex;gap:6px; }
.tbl-btn { width:32px;height:32px;border-radius:8px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.8rem;transition:all 0.2s; }
.tbl-btn.view   { background:rgba(161,255,90,0.08); color:var(--neon-main); }
.tbl-btn.edit   { background:rgba(78,253,196,0.08); color:var(--neon-sec); }
.tbl-btn.print  { background:rgba(192,132,252,0.08); color:var(--neon-purple); }
.tbl-btn.del    { background:rgba(255,90,90,0.08); color:var(--neon-red); }
.tbl-btn:hover  { transform:scale(1.1); filter:brightness(1.3); }
.empty-state { text-align:center; padding:60px 20px; font-size:4rem; }

/* ===================== MODAL ===================== */
.modal-overlay {
    position:fixed; inset:0; z-index:1000;
    background:rgba(0,0,0,0.85); backdrop-filter:blur(8px);
    display:none; align-items:center; justify-content:center; padding:20px;
}
.modal-overlay.active { display:flex; }
.modal-content {
    background:#0f0f0f; border:1px solid rgba(255,255,255,0.08);
    border-radius:20px; width:100%; max-width:860px; max-height:90vh;
    display:flex; flex-direction:column; overflow:hidden;
    box-shadow:0 40px 80px rgba(0,0,0,0.6);
}
.modal-header { display:flex;justify-content:space-between;align-items:center;padding:20px 28px;border-bottom:1px solid rgba(255,255,255,0.06); }
.modal-title { display:flex;align-items:center;gap:10px;font-weight:700;font-size:1rem;color:var(--neon-main); }
.close-modal { background:none;border:none;color:#666;font-size:1.5rem;cursor:pointer;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;transition:color 0.2s; }
.close-modal:hover { color:#fff; }
.modal-body { overflow-y:auto; flex:1; padding:28px; }
.modal-footer { padding:16px 28px;border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px; }
.modal-footer-left { display:flex;gap:8px; }

/* FORM */
.inv-form-wrap { display:flex;flex-direction:column;gap:24px; }
.form-section { background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:14px;padding:20px; }
.form-section-title { font-size:0.78rem;font-weight:700;color:var(--neon-main);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:16px;display:flex;align-items:center;gap:8px; }
.form-grid { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
.form-group { display:flex;flex-direction:column;gap:6px; }
.form-group.full { grid-column:1/-1; }
.form-group label { font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.5px; }
.form-input {
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.08);
    border-radius:10px; padding:10px 14px;
    color:#fff; font-family:inherit; font-size:0.85rem;
    outline:none; transition:border-color 0.2s;
    width:100%;
}
.form-input:focus { border-color:var(--neon-main); }
.form-select { cursor:pointer; }
.form-textarea { min-height:80px; resize:vertical; }

/* ITEMS */
.items-section { display:flex;flex-direction:column;gap:8px; }
.items-head { display:grid;grid-template-columns:1fr 80px 120px 120px 36px;gap:8px;padding:6px 10px; }
.items-head span { font-size:0.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px; }
.item-row { display:grid;grid-template-columns:1fr 80px 120px 120px 36px;gap:8px;align-items:start;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:10px;padding:10px; }
.item-desc-wrap { display:flex;flex-direction:column;gap:4px; }
.item-desc-input { background:transparent;border:none;border-bottom:1px solid rgba(255,255,255,0.08);color:#fff;font-family:inherit;font-size:0.83rem;padding:2px 0;outline:none;width:100%; }
.item-desc-input:focus { border-bottom-color:var(--neon-main); }
.item-num-input { background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:8px;padding:6px 10px;color:#fff;font-family:inherit;font-size:0.83rem;outline:none;width:100%;text-align:right; }
.item-num-input:focus { border-color:var(--neon-main); }
.item-total { font-size:0.8rem;font-weight:700;color:var(--neon-sec);text-align:right;padding-top:6px; }
.btn-remove-item { background:rgba(255,90,90,0.08);border:1px solid rgba(255,90,90,0.15);color:var(--neon-red);border-radius:8px;width:32px;height:32px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.8rem;margin-top:2px; }
.btn-remove-item:hover { background:rgba(255,90,90,0.2); }
.btn-add-item { background:rgba(161,255,90,0.05);border:1px dashed rgba(161,255,90,0.2);color:var(--neon-main);border-radius:10px;padding:10px;width:100%;font-family:inherit;font-size:0.82rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;margin-top:6px;transition:background 0.2s; }
.btn-add-item:hover { background:rgba(161,255,90,0.1); }

/* TOTALS */
.totals-row { display:flex;justify-content:flex-end;margin-top:16px; }
.totals-box { width:320px;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:16px; }
.totals-line { display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;font-size:0.82rem; }
.tl-label { color:var(--text-muted); }
.tl-val { font-weight:600; }
.totals-divider { border:none;border-top:1px solid rgba(255,255,255,0.08);margin:10px 0; }
.totals-grand { display:flex;justify-content:space-between;align-items:center; }
.tg-label { font-weight:700;color:var(--neon-main);font-size:0.9rem; }
.tg-val { font-weight:800;color:var(--neon-main);font-size:1.1rem; }

/* PAYMENT */
.payment-info-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px; }
.payment-toggle-group { display:flex;gap:8px;flex-wrap:wrap; }
.payment-toggle { background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:#666;border-radius:8px;padding:8px 14px;font-family:inherit;font-size:0.8rem;font-weight:600;cursor:pointer;transition:all 0.2s; }
.payment-toggle.active { background:rgba(161,255,90,0.1);border-color:var(--neon-main);color:var(--neon-main); }
.dp-section { background:rgba(78,253,196,0.03);border:1px solid rgba(78,253,196,0.1);border-radius:10px;padding:14px;margin-top:12px; }
.dp-section-title { font-size:0.72rem;font-weight:700;color:var(--neon-sec);margin-bottom:10px;display:flex;align-items:center;gap:6px; }
.dp-grid { display:grid;grid-template-columns:1fr 1fr;gap:10px; }

/* FOOTER BTNS */
.btn-ghost { background:transparent;border:1px solid rgba(255,255,255,0.1);color:#888;border-radius:10px;padding:9px 16px;font-family:inherit;font-size:0.82rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.2s; }
.btn-ghost:hover { border-color:rgba(255,255,255,0.25);color:#fff; }
.btn-preview { background:rgba(192,132,252,0.1);border:1px solid rgba(192,132,252,0.3);color:var(--neon-purple);border-radius:10px;padding:9px 18px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px; }
.btn-save-inv { background:rgba(78,253,196,0.1);border:1px solid rgba(78,253,196,0.3);color:var(--neon-sec);border-radius:10px;padding:9px 18px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px; }
.btn-print-inv { background:var(--grad-main);border:none;color:#000;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px; }

/* ===================== PREVIEW MODAL ===================== */
.preview-modal-overlay {
    position:fixed; inset:0; z-index:2000;
    background:#111; display:none; flex-direction:column; align-items:center;
    overflow-y:auto; padding:20px;
}
.preview-modal-overlay.active { display:flex; }
.preview-actions { display:flex;gap:10px;margin-bottom:20px; }

/* ===================== INVOICE PAPER ===================== */
.invoice-paper {
    background:#fff; color:#222; width:720px; max-width:100%;
    padding:40px 48px; border-radius:12px;
    font-family:'Montserrat',sans-serif;
    box-shadow:0 20px 60px rgba(0,0,0,0.5);
}
.inv-paper-header { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px; }
.inv-paper-logo { font-size:1.6rem;font-weight:900;color:#222; }
.inv-paper-meta { text-align:right; }
.inv-paper-title { font-size:2rem;font-weight:900;color:#a1ff5a;letter-spacing:-1px; }
.inv-paper-num { font-size:0.85rem;font-weight:700;color:#444;margin-top:4px; }
.inv-paper-date { font-size:0.8rem;color:#888; }
.inv-paper-to { background:#f8f8f8;border-left:4px solid #a1ff5a;border-radius:0 8px 8px 0;padding:14px 20px;margin-bottom:24px; }
.inv-paper-to-label { font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:4px; }
.inv-paper-to-name { font-size:1rem;font-weight:800;color:#111; }
.inv-paper-table { width:100%;border-collapse:collapse;margin-bottom:20px; }
.inv-paper-table thead tr { background:#111;color:#a1ff5a; }
.inv-paper-table th { padding:10px 14px;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;text-align:left; }
.inv-paper-table tbody tr { border-bottom:1px solid #f0f0f0; }
.inv-paper-table tbody tr:last-child { border-bottom:2px solid #eee; }
.inv-paper-table td { padding:12px 14px;font-size:0.83rem;vertical-align:top; }
.inv-item-name { font-weight:700;color:#111;margin-bottom:4px; }
.inv-item-subs { font-size:0.74rem;color:#888;line-height:1.7; }
.inv-paper-totals { display:flex;justify-content:flex-end;margin-bottom:24px; }
.inv-paper-totals-box { width:260px; }
.inv-paper-totals-line { display:flex;justify-content:space-between;font-size:0.82rem;color:#555;margin-bottom:6px; }
.inv-paper-totals-div { border:none;border-top:2px solid #222;margin:8px 0; }
.inv-paper-totals-grand { display:flex;justify-content:space-between;font-size:1rem;font-weight:800;color:#111; }
.inv-paper-payment { background:#f8f8f8;border-radius:10px;padding:16px 20px;margin-bottom:16px; }
.inv-paper-payment-title { font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#888;margin-bottom:6px; }
.inv-paper-bank { font-size:0.85rem;color:#333;margin-bottom:4px;font-weight:600; }
.inv-paper-bank span { font-weight:800;color:#111; }
.inv-paper-note { font-size:0.78rem;color:#666;border-left:3px solid #a1ff5a;padding-left:12px;margin-bottom:20px;line-height:1.7; }
.inv-paper-footer { display:flex;justify-content:space-between;align-items:flex-end;margin-top:32px;padding-top:20px;border-top:1px solid #eee; }
.inv-paper-thanks { font-size:1.1rem;font-weight:900;color:#111;font-style:italic; }
.inv-paper-sign { text-align:right; }
.inv-paper-sign-name { font-weight:800;font-size:0.9rem;color:#111; }
.inv-paper-sign-role { font-size:0.75rem;color:#888;margin-top:2px; }
.inv-paper-contact { text-align:center;margin-top:20px;font-size:0.75rem;color:#999;padding-top:14px;border-top:1px solid #f0f0f0; }

/* ===================== POPUP ===================== */
.popup {
    position:fixed; bottom:24px; right:24px; z-index:9999;
    background:#161616; border:1px solid rgba(161,255,90,0.3);
    color:var(--neon-main); padding:12px 20px;
    border-radius:12px; font-size:0.83rem; font-weight:600;
    display:flex; align-items:center; gap:10px;
    transform:translateY(80px); opacity:0;
    transition:transform 0.3s, opacity 0.3s;
    pointer-events:none;
    box-shadow:0 8px 32px rgba(0,0,0,0.4);
}
.popup.show { transform:translateY(0); opacity:1; }
.popup.error { border-color:rgba(255,90,90,0.3); color:var(--neon-red); }

/* PRINT */
@media print {
    body * { visibility:hidden; }
    .invoice-paper, .invoice-paper * { visibility:visible; }
    .invoice-paper { position:absolute; left:0; top:0; width:100%; box-shadow:none; border-radius:0; }
}

@media (max-width: 768px) {
    .main-content { padding:20px 16px; }
    .stat-cards-row { grid-template-columns:1fr 1fr; }
    .form-grid { grid-template-columns:1fr; }
    .payment-info-grid { grid-template-columns:1fr; }
    .items-head { display:none; }
    .item-row { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<div class="ambient-glow glow-1"></div>
<div class="ambient-glow glow-2"></div>

<div class="dashboard-wrapper">
    <main class="main-content">

        <div class="page-headline">
            <h1>Invoice Generator</h1>
            <p>Buat, kelola, dan cetak invoice untuk semua klien.</p>
        </div>

        <!-- STAT CARDS -->
        <div class="stat-cards-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(161,255,90,0.08);color:var(--neon-main);"><i class="fas fa-file-invoice"></i></div>
                <div>
                    <div class="stat-num" style="color:var(--neon-main);" id="statTotal">5</div>
                    <div class="stat-label">Total Invoice</div>
                </div>
                <div class="stat-deco">INV</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(78,253,196,0.08);color:var(--neon-sec);"><i class="fas fa-check-double"></i></div>
                <div>
                    <div class="stat-num" style="color:var(--neon-sec);" id="statPaid">2</div>
                    <div class="stat-label">Lunas</div>
                </div>
                <div class="stat-deco">OK</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(255,159,67,0.08);color:var(--neon-orange);"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-num" style="color:var(--neon-orange);" id="statPending">2</div>
                    <div class="stat-label">Pending / DP</div>
                </div>
                <div class="stat-deco">DP</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(192,132,252,0.08);color:var(--neon-purple);"><i class="fas fa-wallet"></i></div>
                <div>
                    <div class="stat-num" style="color:var(--neon-purple);font-size:1.2rem;" id="statRevenue">67,8 Jt</div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-deco">Rp</div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-area">
            <div class="action-left">
                <input class="search-glass" type="text" id="searchInput" placeholder="Cari nomor / klien..." oninput="filterInvoices()">
                <select class="filter-select" id="filterStatus" onchange="filterInvoices()">
                    <option value="">Semua Status</option>
                    <option value="Lunas">Lunas</option>
                    <option value="Pending">Pending</option>
                    <option value="DP">DP</option>
                    <option value="Overdue">Overdue</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;">
                <button class="btn-outline" onclick="exportCSV()"><i class="fas fa-download"></i> Export CSV</button>
                <button class="btn-neon" onclick="openCreateModal()"><i class="fas fa-plus"></i> Buat Invoice</button>
            </div>
        </div>

        <!-- INVOICE TABLE -->
        <div class="invoice-table-wrap">
            <table class="invoice-table" id="invoiceTable">
                <thead>
                    <tr>
                        <th>No. Invoice</th>
                        <th>Klien</th>
                        <th>Layanan</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="invoiceBody"></tbody>
            </table>
            <div class="empty-state" id="emptyState" style="display:none;">
                <i class="fas fa-file-invoice" style="color:#222;"></i>
                <p style="color:#333;">Belum ada invoice. Buat invoice pertama Anda.</p>
            </div>
        </div>

    </main>
</div>

<!-- CREATE/EDIT MODAL -->
<div class="modal-overlay" id="invModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-file-invoice"></i> <span id="modalTitleText">Buat Invoice Baru</span></div>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="inv-form-wrap">
                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-info-circle"></i> Informasi Invoice</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>No. Invoice</label>
                            <input type="text" class="form-input" id="f_invNo" placeholder="Cth: 0980526">
                        </div>
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" class="form-input" id="f_invDate">
                        </div>
                        <div class="form-group full">
                            <label>Nama Klien / Perusahaan</label>
                            <input type="text" class="form-input" id="f_clientName" placeholder="PT. Global Indo Power">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-list"></i> Detail Item / Layanan</div>
                    <div class="items-section">
                        <div class="items-head">
                            <span>Deskripsi</span>
                            <span style="text-align:center;">QTY</span>
                            <span style="text-align:right;">Harga</span>
                            <span style="text-align:right;">Subtotal</span>
                            <span></span>
                        </div>
                        <div id="itemsBody"></div>
                        <button type="button" class="btn-add-item" onclick="addItem()">
                            <i class="fas fa-plus"></i> Tambah Item
                        </button>
                    </div>
                    <div class="totals-row">
                        <div class="totals-box">
                            <div class="totals-line"><span class="tl-label">Sub Total</span><span class="tl-val" id="tSubtotal">Rp 0</span></div>
                            <div class="totals-line">
                                <span class="tl-label">PPN (%)</span>
                                <input type="number" id="ppnInput" class="form-input" style="width:80px;padding:4px 8px;font-size:0.8rem;text-align:right;" min="0" max="100" value="11" oninput="recalcTotals()">
                            </div>
                            <div class="totals-line"><span class="tl-label">Nilai PPN</span><span class="tl-val" id="tPPN">Rp 0</span></div>
                            <hr class="totals-divider">
                            <div class="totals-grand">
                                <span class="tg-label">TOTAL</span>
                                <span class="tg-val" id="tTotal">Rp 0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-university"></i> Info Pembayaran</div>
                    <div class="payment-info-grid">
                        <div>
                            <div class="form-group">
                                <label>Bank Tujuan</label>
                                <input type="text" class="form-input" id="f_bank" value="BANK BCA">
                            </div>
                            <div class="form-group">
                                <label>No. Rekening</label>
                                <input type="text" class="form-input" id="f_rekening" value="342-999-3629">
                            </div>
                            <div class="form-group">
                                <label>Atas Nama</label>
                                <input type="text" class="form-input" id="f_atasNama" value="PT. ARAH SUKSES BERSAMA">
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>Jenis Pembayaran</label>
                                <div class="payment-toggle-group" id="payToggleGroup">
                                    <button type="button" class="payment-toggle active" data-val="Lunas" onclick="setPayType(this)">Lunas</button>
                                    <button type="button" class="payment-toggle" data-val="DP" onclick="setPayType(this)">2x (DP)</button>
                                    <button type="button" class="payment-toggle" data-val="Pending" onclick="setPayType(this)">Pending</button>
                                </div>
                            </div>
                            <div class="dp-section" id="dpSection" style="display:none;">
                                <div class="dp-section-title"><i class="fas fa-info-circle"></i> Skema DP</div>
                                <div class="dp-grid">
                                    <div class="form-group">
                                        <label>DP 1 (%)</label>
                                        <input type="number" class="form-input" id="f_dp1Pct" value="50" min="1" max="100" oninput="recalcDP()">
                                    </div>
                                    <div class="form-group">
                                        <label>DP 1 (Rp)</label>
                                        <input type="text" class="form-input" id="f_dp1Rp" placeholder="Rp 0" readonly style="color:var(--neon-sec);">
                                    </div>
                                    <div class="form-group">
                                        <label>Pelunasan (%)</label>
                                        <input type="text" class="form-input" id="f_dp2Pct" value="50%" readonly style="color:#555;">
                                    </div>
                                    <div class="form-group">
                                        <label>Pelunasan (Rp)</label>
                                        <input type="text" class="form-input" id="f_dp2Rp" placeholder="Rp 0" readonly style="color:var(--neon-orange);">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:12px;">
                                <label>Status Invoice</label>
                                <select class="form-input form-select" id="f_status">
                                    <option value="Pending">Pending</option>
                                    <option value="DP">DP (50% Dibayar)</option>
                                    <option value="Lunas">Lunas</option>
                                    <option value="Overdue">Overdue</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><i class="fas fa-sticky-note"></i> Tanda Tangan & Catatan</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nama Penandatangan</label>
                            <input type="text" class="form-input" id="f_sigName" value="Casandra">
                        </div>
                        <div class="form-group">
                            <label>Jabatan</label>
                            <input type="text" class="form-input" id="f_sigRole" value="Finance Dept.">
                        </div>
                        <div class="form-group">
                            <label>No. HP / WA</label>
                            <input type="text" class="form-input" id="f_contact" value="0851-6261-2373">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-input" id="f_email" value="bisnis@hvmdigital.id">
                        </div>
                        <div class="form-group full">
                            <label>Catatan Invoice</label>
                            <textarea class="form-input form-textarea" id="f_note">Mohon konfirmasi setelah melakukan pembayaran, untuk kami lanjut ke tahap selanjutnya untuk proses optimalisasi.</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <div class="modal-footer-left">
                <button class="btn-ghost" onclick="closeModal()"><i class="fas fa-times"></i> Batal</button>
                <button class="btn-ghost" onclick="resetForm()"><i class="fas fa-undo"></i> Reset</button>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="btn-preview" onclick="previewInvoice()"><i class="fas fa-eye"></i> Preview</button>
                <button class="btn-save-inv" onclick="saveInvoice()"><i class="fas fa-save"></i> Simpan</button>
                <button class="btn-print-inv" onclick="previewInvoice(true)"><i class="fas fa-print"></i> Print / PDF</button>
            </div>
        </div>
    </div>
</div>

<!-- PREVIEW MODAL -->
<div class="preview-modal-overlay" id="previewModal">
    <div class="preview-actions">
        <button class="btn-neon" onclick="window.print()"><i class="fas fa-print"></i> Print / PDF</button>
        <button class="btn-ghost" style="border-color:rgba(255,255,255,0.15);color:#bbb;" onclick="closePreview()"><i class="fas fa-times"></i> Tutup</button>
    </div>
    <div class="invoice-paper" id="invoicePaper"></div>
</div>

<!-- POPUP -->
<div id="popup" class="popup"><i class="fas fa-check-circle"></i> <span id="popupMsg">Berhasil</span></div>

<script>
let invoices = [
    { id:'INV-001', no:'0980526', client:'PT. Global Indo Power', service:'Website Growth', date:'2026-05-22', subtotal:6143242, ppn:11, total:6779000, status:'Pending', bank:'BANK BCA', rekening:'342-999-3629', atasNama:'PT. ARAH SUKSES BERSAMA', payType:'DP', dp1Pct:50, sigName:'Casandra', sigRole:'Finance Dept.', contact:'0851-6261-2373', email:'bisnis@hvmdigital.id', note:'Mohon konfirmasi setelah melakukan pembayaran, untuk kami lanjut ke tahap selanjutnya untuk proses optimalisasi.', items:[{name:'Website Growth', subs:'Website Company Profile\nSLL Domain Protection\nOptimasi SEO & AI (Google Search)\nOptimasi Kecepatan Website\nOptimasi Mobile Friendly Exclusive\nDesign Fitur Tracking Visitor\nBonus SEO 5 Keyword Garansi\nMaintenance (1 Tahun)', qty:1, price:6143242}] },
    { id:'INV-002', no:'0980424', client:'CV. Maju Bersama', service:'Social Media', date:'2026-04-20', subtotal:4500000, ppn:11, total:4995000, status:'Lunas', bank:'BANK BCA', rekening:'342-999-3629', atasNama:'PT. ARAH SUKSES BERSAMA', payType:'Lunas', dp1Pct:100, sigName:'Casandra', sigRole:'Finance Dept.', contact:'0851-6261-2373', email:'bisnis@hvmdigital.id', note:'', items:[{name:'Social Media Management', subs:'Konten Instagram & TikTok\nScheduling & Posting\nMonthly Report', qty:1, price:4500000}] },
    { id:'INV-003', no:'0980323', client:'PT. Teknologi Nusantara', service:'SEO & Branding', date:'2026-03-15', subtotal:8200000, ppn:11, total:9102000, status:'DP', bank:'BANK BCA', rekening:'342-999-3629', atasNama:'PT. ARAH SUKSES BERSAMA', payType:'DP', dp1Pct:50, sigName:'Casandra', sigRole:'Finance Dept.', contact:'0851-6261-2373', email:'bisnis@hvmdigital.id', note:'', items:[{name:'SEO Full Package', subs:'Riset Keyword\nOptimasi On-Page & Off-Page\n20 Artikel Konten', qty:1, price:6000000},{name:'Branding Identity', subs:'Logo & Brand Guidelines', qty:1, price:2200000}] },
    { id:'INV-004', no:'0980222', client:'UD. Surya Gemilang', service:'Web Dev', date:'2026-02-10', subtotal:3000000, ppn:11, total:3330000, status:'Lunas', bank:'BANK BCA', rekening:'342-999-3629', atasNama:'PT. ARAH SUKSES BERSAMA', payType:'Lunas', dp1Pct:100, sigName:'Casandra', sigRole:'Finance Dept.', contact:'0851-6261-2373', email:'bisnis@hvmdigital.id', note:'', items:[{name:'Landing Page', subs:'Desain & Development\nDomain 1 Tahun', qty:1, price:3000000}] },
    { id:'INV-005', no:'0980120', client:'PT. Artha Prima', service:'Content Creator', date:'2026-01-08', subtotal:5400000, ppn:11, total:5994000, status:'Overdue', bank:'BANK BCA', rekening:'342-999-3629', atasNama:'PT. ARAH SUKSES BERSAMA', payType:'Pending', dp1Pct:50, sigName:'Casandra', sigRole:'Finance Dept.', contact:'0851-6261-2373', email:'bisnis@hvmdigital.id', note:'', items:[{name:'Content Creator Package', subs:'12 Video Reels/bulan\nEditing & Caption\nPublishing', qty:1, price:5400000}] },
];
let editingId = null;
let payType = 'Lunas';

function renderTable(data){
    const tbody = document.getElementById('invoiceBody');
    const empty = document.getElementById('emptyState');
    tbody.innerHTML = '';
    if(!data || data.length===0){ empty.style.display='block'; return; }
    empty.style.display='none';
    data.forEach(inv => {
        const statusClass = {Lunas:'status-paid',Pending:'status-pending',DP:'status-dp',Overdue:'status-overdue'}[inv.status]||'status-pending';
        const statusDot = {Lunas:'●',Pending:'○',DP:'◐',Overdue:'✕'}[inv.status]||'○';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><span class="inv-number">#${inv.no}</span></td>
            <td><div class="inv-client">${inv.client}<small>${inv.service}</small></div></td>
            <td><span class="inv-date" style="font-size:0.75rem;color:#555;">${inv.service}</span></td>
            <td class="inv-date">${fmtDate(inv.date)}</td>
            <td class="inv-amount">${fmtRp(inv.total)}</td>
            <td><span class="status-badge ${statusClass}">${statusDot} ${inv.status}</span></td>
            <td>
                <div class="action-btns">
                    <button class="tbl-btn view" title="Preview" onclick="viewInvoice('${inv.id}')"><i class="fas fa-eye"></i></button>
                    <button class="tbl-btn edit" title="Edit" onclick="editInvoice('${inv.id}')"><i class="fas fa-edit"></i></button>
                    <button class="tbl-btn print" title="Print" onclick="printInvoice('${inv.id}')"><i class="fas fa-print"></i></button>
                    <button class="tbl-btn del" title="Hapus" onclick="deleteInvoice('${inv.id}')"><i class="fas fa-trash-alt"></i></button>
                </div>
            </td>`;
        tbody.appendChild(tr);
    });
    updateStats();
}

function filterInvoices(){
    const q = document.getElementById('searchInput').value.toLowerCase();
    const s = document.getElementById('filterStatus').value;
    const filtered = invoices.filter(inv =>
        (!q || inv.no.toLowerCase().includes(q) || inv.client.toLowerCase().includes(q)) &&
        (!s || inv.status === s)
    );
    renderTable(filtered);
}

function updateStats(){
    document.getElementById('statTotal').innerText = invoices.length;
    document.getElementById('statPaid').innerText = invoices.filter(i=>i.status==='Lunas').length;
    document.getElementById('statPending').innerText = invoices.filter(i=>i.status==='Pending'||i.status==='DP').length;
    const total = invoices.reduce((a,b)=>a+b.total,0);
    document.getElementById('statRevenue').innerText = total>=1000000 ? (total/1000000).toFixed(1)+' Jt' : fmtRp(total);
}

function addItem(name='', subs='', qty=1, price=0){
    const tbody = document.getElementById('itemsBody');
    const div = document.createElement('div');
    div.className = 'item-row';
    div.innerHTML = `
        <div class="item-desc-wrap">
            <input type="text" class="item-desc-input" placeholder="Nama layanan / produk..." value="${esc(name)}" oninput="recalcTotals()">
            <textarea class="item-sub-input" placeholder="Detail (opsional)..." rows="3" style="resize:none;width:100%;background:transparent;border:none;border-bottom:1px dashed rgba(255,255,255,0.05);color:#555;font-size:0.72rem;font-family:inherit;padding:2px 0;outline:none;line-height:1.6;">${esc(subs)}</textarea>
        </div>
        <input type="number" class="item-num-input" value="${qty}" min="1" oninput="recalcTotals()">
        <input type="number" class="item-num-input" value="${price}" min="0" step="1000" placeholder="0" oninput="recalcTotals()">
        <div class="item-total">${fmtRp(qty*price)}</div>
        <button type="button" class="btn-remove-item" onclick="removeItem(this)"><i class="fas fa-times"></i></button>`;
    tbody.appendChild(div);
    recalcTotals();
}

function removeItem(btn){ btn.closest('.item-row').remove(); recalcTotals(); }

function recalcTotals(){
    let sub = 0;
    document.querySelectorAll('#itemsBody .item-row').forEach(row => {
        const qty = parseFloat(row.querySelectorAll('.item-num-input')[0].value)||0;
        const price = parseFloat(row.querySelectorAll('.item-num-input')[1].value)||0;
        const t = qty*price;
        row.querySelector('.item-total').innerText = fmtRp(t);
        sub += t;
    });
    const ppnPct = parseFloat(document.getElementById('ppnInput').value)||0;
    const ppnVal = sub*(ppnPct/100);
    const total = sub+ppnVal;
    document.getElementById('tSubtotal').innerText = fmtRp(sub);
    document.getElementById('tPPN').innerText = fmtRp(ppnVal);
    document.getElementById('tTotal').innerText = fmtRp(total);
    recalcDP();
}

function recalcDP(){
    const totalStr = document.getElementById('tTotal').innerText;
    const total = parseRp(totalStr);
    const dp1Pct = parseFloat(document.getElementById('f_dp1Pct').value)||50;
    const dp1 = total*(dp1Pct/100);
    const dp2 = total-dp1;
    document.getElementById('f_dp1Rp').value = fmtRp(dp1);
    document.getElementById('f_dp2Pct').value = (100-dp1Pct)+'%';
    document.getElementById('f_dp2Rp').value = fmtRp(dp2);
}

function setPayType(btn){
    document.querySelectorAll('.payment-toggle').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    payType = btn.dataset.val;
    document.getElementById('dpSection').style.display = payType==='DP' ? 'block' : 'none';
}

function openCreateModal(){
    editingId = null;
    document.getElementById('modalTitleText').innerText = 'Buat Invoice Baru';
    resetForm();
    document.getElementById('f_invDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('f_invNo').value = String(parseInt(Date.now().toString().slice(-6))).padStart(7,'0');
    document.getElementById('invModal').classList.add('active');
    if(document.getElementById('itemsBody').children.length===0) addItem();
}

function closeModal(){ document.getElementById('invModal').classList.remove('active'); }

function resetForm(){
    ['f_clientName','f_invNo','f_invDate'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('itemsBody').innerHTML='';
    recalcTotals();
    document.getElementById('ppnInput').value='11';
    document.getElementById('f_bank').value='BANK BCA';
    document.getElementById('f_rekening').value='342-999-3629';
    document.getElementById('f_atasNama').value='PT. ARAH SUKSES BERSAMA';
    document.getElementById('f_sigName').value='Casandra';
    document.getElementById('f_sigRole').value='Finance Dept.';
    document.getElementById('f_contact').value='0851-6261-2373';
    document.getElementById('f_email').value='bisnis@hvmdigital.id';
    document.getElementById('f_note').value='Mohon konfirmasi setelah melakukan pembayaran, untuk kami lanjut ke tahap selanjutnya untuk proses optimalisasi.';
    document.getElementById('f_status').value='Pending';
    document.querySelectorAll('.payment-toggle').forEach((b,i)=>{b.classList.remove('active');if(i===0)b.classList.add('active');});
    payType='Lunas';
    document.getElementById('dpSection').style.display='none';
    addItem();
}

function editInvoice(id){
    const inv = invoices.find(i=>i.id===id);
    if(!inv) return;
    editingId = id;
    document.getElementById('modalTitleText').innerText = 'Edit Invoice #'+inv.no;
    document.getElementById('f_invNo').value = inv.no;
    document.getElementById('f_invDate').value = inv.date;
    document.getElementById('f_clientName').value = inv.client;
    document.getElementById('ppnInput').value = inv.ppn;
    document.getElementById('f_bank').value = inv.bank;
    document.getElementById('f_rekening').value = inv.rekening;
    document.getElementById('f_atasNama').value = inv.atasNama;
    document.getElementById('f_sigName').value = inv.sigName;
    document.getElementById('f_sigRole').value = inv.sigRole;
    document.getElementById('f_contact').value = inv.contact;
    document.getElementById('f_email').value = inv.email;
    document.getElementById('f_note').value = inv.note;
    document.getElementById('f_status').value = inv.status;
    payType = inv.payType;
    document.querySelectorAll('.payment-toggle').forEach(b=>{b.classList.remove('active');if(b.dataset.val===inv.payType)b.classList.add('active');});
    document.getElementById('dpSection').style.display = inv.payType==='DP' ? 'block' : 'none';
    document.getElementById('f_dp1Pct').value = inv.dp1Pct;
    document.getElementById('itemsBody').innerHTML='';
    inv.items.forEach(item=>addItem(item.name,item.subs,item.qty,item.price));
    document.getElementById('invModal').classList.add('active');
}

function saveInvoice(){
    const no = document.getElementById('f_invNo').value.trim();
    const client = document.getElementById('f_clientName').value.trim();
    if(!no||!client){ showPopup('error','Isi No. Invoice dan Nama Klien terlebih dahulu.'); return; }
    const items = [];
    let sub = 0;
    document.querySelectorAll('#itemsBody .item-row').forEach(row => {
        const name = row.querySelector('.item-desc-input').value;
        const subs = row.querySelector('textarea').value;
        const qty = parseFloat(row.querySelectorAll('.item-num-input')[0].value)||0;
        const price = parseFloat(row.querySelectorAll('.item-num-input')[1].value)||0;
        items.push({name,subs,qty,price}); sub += qty*price;
    });
    const ppn = parseFloat(document.getElementById('ppnInput').value)||0;
    const total = sub + sub*(ppn/100);
    const inv = {
        id: editingId || ('INV-'+String(Date.now()).slice(-6)),
        no, client,
        service: items[0]?.name || 'Layanan',
        date: document.getElementById('f_invDate').value || new Date().toISOString().split('T')[0],
        subtotal: sub, ppn, total,
        status: document.getElementById('f_status').value,
        bank: document.getElementById('f_bank').value,
        rekening: document.getElementById('f_rekening').value,
        atasNama: document.getElementById('f_atasNama').value,
        payType, dp1Pct: parseFloat(document.getElementById('f_dp1Pct').value)||50,
        sigName: document.getElementById('f_sigName').value,
        sigRole: document.getElementById('f_sigRole').value,
        contact: document.getElementById('f_contact').value,
        email: document.getElementById('f_email').value,
        note: document.getElementById('f_note').value,
        items,
    };
    if(editingId){ invoices[invoices.findIndex(i=>i.id===editingId)] = inv; showPopup('success','Invoice berhasil diperbarui!'); }
    else { invoices.unshift(inv); showPopup('success','Invoice berhasil disimpan!'); }
    closeModal(); filterInvoices();
}

function deleteInvoice(id){
    if(!confirm('Hapus invoice ini?')) return;
    invoices = invoices.filter(i=>i.id!==id);
    filterInvoices(); showPopup('success','Invoice dihapus.');
}

function buildInvoiceHTML(inv){
    const ppnVal = inv.subtotal*(inv.ppn/100);
    let itemsHtml = inv.items.map(item => {
        const subsHtml = item.subs ? item.subs.split('\n').filter(Boolean).map(s=>`<div>${s}</div>`).join('') : '';
        return `<tr>
            <td><div class="inv-item-name">${esc(item.name)}</div>${subsHtml?`<div class="inv-item-subs">${subsHtml}</div>`:''}</td>
            <td>${item.qty}</td><td>${fmtRp(item.price)}</td><td>${fmtRp(item.qty*item.price)}</td>
        </tr>`;
    }).join('');
    let dpNoteHtml = '';
    if(inv.payType==='DP'){
        dpNoteHtml = `<div style="font-size:0.78rem;color:#888;margin-top:8px;">Harga Khusus: ${fmtRp(inv.total)} Inc PPN<br>Pembayaran 2x (DP ${inv.dp1Pct}% &amp; ${100-inv.dp1Pct}% Lunas)</div>`;
    }
    return `
        <div class="inv-paper-header">
            <div>
                <div class="inv-paper-logo">HVM<span style="color:#000;background:#a1ff5a;padding:2px 6px;border-radius:4px;">Digital</span></div>
                <div style="font-size:0.72rem;color:#aaa;margin-top:6px;">${esc(inv.contact)} | ${esc(inv.email)}</div>
            </div>
            <div class="inv-paper-meta">
                <div class="inv-paper-title">Invoice</div>
                <div class="inv-paper-num">No: ${esc(inv.no)}</div>
                <div class="inv-paper-date">Date: ${fmtDateShort(inv.date)}</div>
            </div>
        </div>
        <div class="inv-paper-to">
            <div class="inv-paper-to-label">To:</div>
            <div class="inv-paper-to-name">${esc(inv.client)}</div>
        </div>
        <table class="inv-paper-table">
            <thead><tr><th>Item Description</th><th>QTY</th><th>Price</th><th>Subtotal</th></tr></thead>
            <tbody>${itemsHtml}</tbody>
        </table>
        ${dpNoteHtml}
        <div class="inv-paper-totals">
            <div class="inv-paper-totals-box">
                <div class="inv-paper-totals-line"><span>SUB-TOTAL</span><span>${fmtRp(inv.subtotal)}</span></div>
                ${inv.ppn?`<div class="inv-paper-totals-line"><span>PPN (${inv.ppn}%)</span><span>${fmtRp(ppnVal)}</span></div>`:''}
                <hr class="inv-paper-totals-div">
                <div class="inv-paper-totals-grand"><span>TOTAL</span><span>${fmtRp(inv.total)}</span></div>
            </div>
        </div>
        <div class="inv-paper-payment">
            <div class="inv-paper-payment-title">Pembayaran :</div>
            <div style="font-size:0.85rem;color:#555;margin-bottom:8px;">Silahkan melakukan pembayaran</div>
            <div class="inv-paper-bank">-${esc(inv.bank)}</div>
            <div class="inv-paper-bank">NO : <span>${esc(inv.rekening)}</span></div>
            <div class="inv-paper-bank">A/N : <span>${esc(inv.atasNama)}</span></div>
        </div>
        ${inv.note?`<div class="inv-paper-note"><b>Catatan :</b><br>${esc(inv.note)}</div>`:''}
        <div class="inv-paper-footer">
            <div class="inv-paper-thanks"><i>TerimaKasih</i></div>
            <div class="inv-paper-sign">
                <div class="inv-paper-sign-name">${esc(inv.sigName)}</div>
                <div class="inv-paper-sign-role">${esc(inv.sigRole)}</div>
            </div>
        </div>
        <div class="inv-paper-contact"><b>Terima Kasih</b><br>${esc(inv.contact)} | ${esc(inv.email)}</div>`;
}

function previewInvoice(doPrint){
    const no = document.getElementById('f_invNo').value||'—';
    const client = document.getElementById('f_clientName').value||'—';
    const items = []; let sub = 0;
    document.querySelectorAll('#itemsBody .item-row').forEach(row => {
        const name = row.querySelector('.item-desc-input').value;
        const subs = row.querySelector('textarea').value;
        const qty = parseFloat(row.querySelectorAll('.item-num-input')[0].value)||0;
        const price = parseFloat(row.querySelectorAll('.item-num-input')[1].value)||0;
        items.push({name,subs,qty,price}); sub += qty*price;
    });
    const ppn = parseFloat(document.getElementById('ppnInput').value)||0;
    const inv = { no, client, date: document.getElementById('f_invDate').value||new Date().toISOString().split('T')[0],
        subtotal: sub, ppn, total: sub+sub*(ppn/100),
        bank: document.getElementById('f_bank').value, rekening: document.getElementById('f_rekening').value,
        atasNama: document.getElementById('f_atasNama').value, payType, dp1Pct: parseFloat(document.getElementById('f_dp1Pct').value)||50,
        sigName: document.getElementById('f_sigName').value, sigRole: document.getElementById('f_sigRole').value,
        contact: document.getElementById('f_contact').value, email: document.getElementById('f_email').value,
        note: document.getElementById('f_note').value, items };
    document.getElementById('invoicePaper').innerHTML = buildInvoiceHTML(inv);
    document.getElementById('previewModal').classList.add('active');
    if(doPrint) setTimeout(()=>window.print(), 500);
}

function viewInvoice(id){ const inv=invoices.find(i=>i.id===id); if(!inv)return; document.getElementById('invoicePaper').innerHTML=buildInvoiceHTML(inv); document.getElementById('previewModal').classList.add('active'); }
function printInvoice(id){ viewInvoice(id); setTimeout(()=>window.print(),600); }
function closePreview(){ document.getElementById('previewModal').classList.remove('active'); }

function exportCSV(){
    if(!invoices.length){ showPopup('error','Tidak ada data untuk diekspor.'); return; }
    const header = ['No. Invoice','Klien','Layanan','Tanggal','Subtotal','PPN%','Total','Status'];
    const rows = invoices.map(i=>[i.no,i.client,i.service,i.date,i.subtotal,i.ppn,i.total,i.status]);
    const csv = [header,...rows].map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a'); a.href=URL.createObjectURL(blob);
    a.download='invoices_hvm_'+new Date().toISOString().slice(0,10)+'.csv'; a.click();
    showPopup('success','CSV berhasil diexport!');
}

function fmtRp(n){ return 'Rp '+Math.round(n||0).toLocaleString('id-ID'); }
function parseRp(s){ return parseFloat(String(s).replace(/[^0-9]/g,''))||0; }
function fmtDate(d){ if(!d)return'-'; return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}); }
function fmtDateShort(d){ if(!d)return'-'; const dt=new Date(d); return dt.getDate().toString().padStart(2,'0')+'/'+(dt.getMonth()+1).toString().padStart(2,'0')+'/'+String(dt.getFullYear()).slice(2); }
function esc(s){ const d=document.createElement('div');d.appendChild(document.createTextNode(s||''));return d.innerHTML; }
function showPopup(type,msg){
    const p=document.getElementById('popup');
    document.getElementById('popupMsg').innerText=msg;
    p.className='popup'+(type==='error'?' error':'');
    p.querySelector('i').className=type==='error'?'fas fa-exclamation-triangle':'fas fa-check-circle';
    p.classList.add('show'); clearTimeout(p._t);
    p._t=setTimeout(()=>p.classList.remove('show'),3500);
}

document.getElementById('invModal').addEventListener('click',e=>{ if(e.target.id==='invModal') closeModal(); });
document.getElementById('previewModal').addEventListener('click',e=>{ if(e.target.id==='previewModal') closePreview(); });

renderTable(invoices);
</script>
</body>
</html>