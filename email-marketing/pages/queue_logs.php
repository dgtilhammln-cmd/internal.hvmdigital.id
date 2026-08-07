<?php
/* ==========================================================================
   TITAN QUEUE INTELLIGENCE - SOVEREIGN MONITOR (V5.0)
   - Real-time Drip-Feed Surveillance
   - Global Delivery Analytics & Success Ratios
   - Smart Action Center (Retry, Pause, Purge)
   - Zero-Refresh Data Polling Engine
   ========================================================================== */

if(!defined('DB_NAME')) { 
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php'; 
}

// --- 1. AJAX ENGINE: DATA PROVIDER (REAL-TIME) ---
if (isset($_GET['api']) && $_GET['api'] == 'fetch_stats') {
    header('Content-Type: application/json');
    
    // Global Stats
    $st['pending'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM email_queue WHERE status='pending'"))['t'];
    $st['sent']    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM email_queue WHERE status='sent'"))['t'];
    $st['failed']  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as t FROM email_queue WHERE status='failed'"))['t'];
    $st['total']   = $st['pending'] + $st['sent'] + $st['failed'];
    
    // Success Rate
    $st['ratio']   = ($st['total'] > 0) ? round(($st['sent'] / $st['total']) * 100, 1) : 0;
    
    // Fetch Logs
    $filter = isset($_GET['filter']) && $_GET['filter'] != 'all' ? "WHERE q.status = '".$_GET['filter']."'" : "";
    $q_logs = "SELECT q.*, c.subject, con.email, con.name 
               FROM email_queue q
               JOIN email_campaigns c ON q.campaign_id = c.id
               JOIN email_contacts con ON q.contact_id = con.id
               $filter
               ORDER BY q.scheduled_at DESC LIMIT 30";
    $res = mysqli_query($conn, $q_logs);
    $logs = [];
    while($r = mysqli_fetch_assoc($res)) {
        $r['scheduled_f'] = date('H:i | d M', strtotime($r['scheduled_at']));
        $r['sent_f'] = $r['sent_at'] ? date('H:i', strtotime($r['sent_at'])) : '-';
        $logs[] = $r;
    }

    echo json_encode(['stats' => $st, 'logs' => $logs]);
    exit;
}

// --- 2. LOGIKA ACTIONS (Retry, Clear) ---
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'retry_all') {
        mysqli_query($conn, "UPDATE email_queue SET status='pending', attempts=0 WHERE status='failed'");
    }
    if ($_GET['action'] == 'purge_completed') {
        mysqli_query($conn, "DELETE FROM email_queue WHERE status='sent'");
    }
    header("Location: ?page=queue_logs");
    exit;
}
?>

<!-- START TITAN QUEUE STYLESHEET -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

    :root {
        --t-green: #a1ff5a;
        --t-cyan: #4efdc4;
        --t-red: #ff4560;
        --t-orange: #ffab2e;
        --t-glass: rgba(15, 15, 15, 0.7);
        --t-border: rgba(255, 255, 255, 0.08);
        --t-grad: linear-gradient(135deg, #a1ff5a 0%, #4efdc4 100%);
    }

    .titan-queue-viewport {
        padding-left: 110px; /* SYNC DENGAN SIDEBAR DASHBOARD */
        padding-right: 30px;
        padding-top: 15px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: #fff;
        animation: titanFadeIn 0.8s ease-out;
    }

    @keyframes titanFadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

    /* STATS GRID LUXURY */
    .titan-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 35px;
    }

    .stat-luxury-card {
        background: var(--t-glass);
        backdrop-filter: blur(30px);
        border: 1px solid var(--t-border);
        border-radius: 30px;
        padding: 30px;
        position: relative;
        overflow: hidden;
        transition: 0.4s;
    }
    .stat-luxury-card:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.2); }

    .stat-luxury-card i {
        position: absolute; right: -10px; bottom: -10px;
        font-size: 5rem; opacity: 0.03; color: #fff;
    }

    .stat-label { display: block; font-size: 0.7rem; font-weight: 800; color: #666; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
    .stat-value { font-size: 2.2rem; font-weight: 800; display: block; letter-spacing: -1px; }

    /* DELIVERY HEALTH BAR */
    .health-bar-container {
        background: rgba(255,255,255,0.02);
        border: 1px solid var(--t-border);
        border-radius: 40px;
        padding: 30px 40px;
        margin-bottom: 35px;
        display: flex;
        align-items: center;
        gap: 40px;
    }

    .progress-radial {
        width: 80px; height: 80px; border-radius: 50%;
        background: conic-gradient(var(--t-green) 0%, #111 0%);
        display: flex; align-items: center; justify-content: center;
        position: relative; transition: 1s;
    }
    .progress-radial::after {
        content: attr(data-pct);
        position: absolute; width: 64px; height: 64px; background: #050505;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 0.9rem; color: var(--t-green);
    }

    /* TAB CONTROL */
    .titan-tab-box {
        display: flex; gap: 10px; margin-bottom: 25px;
        background: rgba(0,0,0,0.2); padding: 8px; border-radius: 50px; width: fit-content;
        border: 1px solid var(--t-border);
    }
    .tab-btn {
        padding: 10px 25px; border-radius: 50px; border: none; background: transparent;
        color: #666; font-weight: 700; font-size: 0.75rem; cursor: pointer; transition: 0.3s;
    }
    .tab-btn.active { background: var(--t-grad); color: #000; box-shadow: 0 5px 15px rgba(161, 255, 90, 0.2); }

    /* LOG TABLE LUXURY */
    .titan-table-container {
        background: var(--t-glass);
        backdrop-filter: blur(40px);
        border: 1px solid var(--t-border);
        border-radius: 35px;
        overflow: hidden;
        box-shadow: 0 40px 80px rgba(0,0,0,0.6);
    }

    .titan-table { width: 100%; border-collapse: collapse; text-align: left; }
    .titan-table th { padding: 25px; background: rgba(255,255,255,0.02); color: #555; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; border-bottom: 1px solid var(--t-border); }
    .titan-table td { padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 0.85rem; vertical-align: middle; }

    .target-box b { color: #fff; font-size: 0.95rem; display: block; margin-bottom: 4px; }
    .target-box small { color: #666; font-weight: 600; }

    .status-pill {
        padding: 6px 14px; border-radius: 50px; font-size: 0.65rem; font-weight: 800;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .pill-pending { background: rgba(255,255,255,0.05); color: #888; border: 1px solid rgba(255,255,255,0.1); }
    .pill-sent { background: rgba(161, 255, 90, 0.1); color: var(--t-green); border: 1px solid rgba(161, 255, 90, 0.2); }
    .pill-failed { background: rgba(255, 69, 96, 0.1); color: var(--t-red); border: 1px solid rgba(255, 69, 96, 0.2); }

    .intel-icons { display: flex; gap: 10px; margin-top: 5px; }
    .intel-tag { font-size: 0.6rem; font-weight: 800; padding: 2px 8px; border-radius: 4px; }
    .tag-open { background: rgba(78, 253, 196, 0.1); color: var(--t-cyan); border: 1px solid rgba(78, 253, 196, 0.2); }
    .tag-click { background: rgba(161, 255, 90, 0.1); color: var(--t-green); border: 1px solid rgba(161, 255, 90, 0.2); }

    /* ACTION BUTTONS */
    .btn-action-titan {
        padding: 12px 25px; border-radius: 50px; font-weight: 800; font-size: 0.75rem;
        cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px;
        text-decoration: none; border: 1px solid var(--t-border); color: #fff;
    }
    .btn-action-titan:hover { background: rgba(255,255,255,0.05); border-color: #fff; transform: translateY(-2px); }

    @media (max-width: 1024px) {
        .titan-queue-viewport { padding-left: 20px; padding-bottom: 120px; }
        .health-bar-container { flex-direction: column; text-align: center; }
    }
</style>

<div class="titan-queue-viewport">
    
    <div class="page-headline">
        <h1 style="font-weight: 800; font-size: 2.8rem; letter-spacing: -2px;">Queue Intelligence</h1>
        <p style="color: #666;">Surveillance engine for corporate drip-feed transmissions.</p>
    </div>

    <!-- STATS LUXURY GRID -->
    <div class="titan-stats-grid">
        <div class="stat-luxury-card">
            <i class="fas fa-hourglass-half"></i>
            <span class="stat-label">Pending Queue</span>
            <span class="stat-value" id="stat-pending">0</span>
        </div>
        <div class="stat-luxury-card">
            <i class="fas fa-paper-plane"></i>
            <span class="stat-label">Dispatched</span>
            <span class="stat-value" id="stat-sent" style="color: var(--t-green);">0</span>
        </div>
        <div class="stat-luxury-card">
            <i class="fas fa-exclamation-circle"></i>
            <span class="stat-label">Failed Delivery</span>
            <span class="stat-value" id="stat-failed" style="color: var(--t-red);">0</span>
        </div>
    </div>

    <!-- DELIVERY HEALTH & ACTIONS -->
    <div class="health-bar-container">
        <div class="progress-radial" id="health-radial" data-pct="0%"></div>
        <div style="flex: 1;">
            <h3 style="margin: 0; font-weight: 800; font-size: 1.2rem;">Transmission Health</h3>
            <p style="margin: 5px 0 0 0; color: #555; font-size: 0.85rem;">Sistem memantau rasio keberhasilan pengiriman dari total kampanye aktif.</p>
        </div>
        <div style="display:flex; gap:12px;">
            <a href="?page=queue_logs&action=retry_all" class="btn-action-titan"><i class="fas fa-redo"></i> Retry Failed</a>
            <a href="?page=queue_logs&action=purge_completed" class="btn-action-titan" style="color:var(--t-red);"><i class="fas fa-trash-alt"></i> Purge Sent</a>
        </div>
    </div>

    <!-- TAB CONTROL -->
    <div class="titan-tab-box">
        <button class="tab-btn active" onclick="setFilter('all', this)">ALL SESSIONS</button>
        <button class="tab-btn" onclick="setFilter('pending', this)">WAITING</button>
        <button class="tab-btn" onclick="setFilter('sent', this)">COMPLETED</button>
        <button class="tab-btn" onclick="setFilter('failed', this)">ERRORS</button>
    </div>

    <!-- DATA TABLE -->
    <div class="titan-table-container">
        <table class="titan-table">
            <thead>
                <tr>
                    <th>Target Entity</th>
                    <th>Intelligence Data</th>
                    <th>Scheduled Window</th>
                    <th>State</th>
                </tr>
            </thead>
            <tbody id="log-body">
                <!-- Data injected via AJAX -->
                <tr>
                    <td colspan="4" style="text-align:center; padding: 100px; opacity:0.3;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<!-- TITAN POLLING ENGINE -->
<script>
    let currentFilter = 'all';

    function setFilter(f, btn) {
        currentFilter = f;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        fetchIntelligence();
    }

    async function fetchIntelligence() {
        try {
            const res = await fetch(`pages/queue_logs.php?api=fetch_stats&filter=${currentFilter}`);
            const data = await res.json();
            
            // Update Stats
            document.getElementById('stat-pending').innerText = data.stats.pending;
            document.getElementById('stat-sent').innerText = data.stats.sent;
            document.getElementById('stat-failed').innerText = data.stats.failed;
            
            // Update Radial
            const radial = document.getElementById('health-radial');
            radial.style.background = `conic-gradient(var(--t-green) ${data.stats.ratio}%, #111 0%)`;
            radial.setAttribute('data-pct', data.stats.ratio + '%');

            // Update Table
            const body = document.getElementById('log-body');
            if (data.logs.length === 0) {
                body.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:50px; color:#444;">No operational records found.</td></tr>';
                return;
            }

            let html = '';
data.logs.forEach(l => {
    const statusClass = 'pill-' + l.status;
    const openedTag = l.opened_at ? '<span class="intel-tag tag-open">OPENED</span>' : '';
    const clickedTag = l.clicked_at ? '<span class="intel-tag tag-click">CLICKED</span>' : '';
    
    // TAMPILKAN PESAN ERROR JIKA GAGAL
    const errorNote = (l.status === 'failed') ? `<div style="color:red; font-size:10px; margin-top:5px;">⚠️ ${l.error_msg}</div>` : '';

    html += `
    <tr>
        <td>
            <div class="target-box">
                <b>${l.name}</b>
                <small>${l.email}</small>
            </div>
        </td>
        <td>
            <div style="color: #aaa; font-size: 0.8rem; margin-bottom:5px;">${l.subject}</div>
            <div class="intel-icons">${openedTag} ${clickedTag}</div>
            ${errorNote}
        </td>
        <td>
            <div style="font-weight:700; color:#fff;">${l.scheduled_f}</div>
            <small style="color:#444;">ID: #${l.id}</small>
        </td>
        <td>
            <span class="status-pill ${statusClass}">● ${l.status.toUpperCase()}</span>
        </td>
    </tr>`;
});
            body.innerHTML = html;

        } catch (e) { console.error("Titan Sync Failed"); }
    }

    // Polling setiap 4 detik
    fetchIntelligence();
    setInterval(fetchIntelligence, 4000);
</script>