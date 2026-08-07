<?php
/* ╔═══════════════════════════════════════════════════════════════════════════════════════════════╗
   ║  NEBULA LEAD INTERCEPTOR V13.5 — THE SOVEREIGN TITAN DASHBOARD                                ║
   ║  -------------------------------------------------------------------------------------------  ║
   ║  ULTIMATE PREMIUM EDITION | DESIGNED FOR HVM DIGITAL SOLUTIONS                                ║
   ║  -------------------------------------------------------------------------------------------  ║
   ║  SYSTEM ARCHITECTURE:                                                                         ║
   ║  - REAL-TIME NEURAL POLLING (AJAX ENGINE)                                                     ║
   ║  - DYNAMIC LEAD TEMPERATURE SCORING                                                           ║
   ║  - TITAN WA DISPATCHER INTEGRATION                                                            ║
   ║  - GLASSMORPHISM LEVEL 4 (DEEP BLUR)                                                          ║
   ╚═══════════════════════════════════════════════════════════════════════════════════════════════╝ */

// 1. SYSTEM INITIALIZATION
if (!defined('DB_NAME')) { 
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php'; 
}
date_default_timezone_set('Asia/Jakarta');

if (!isset($conn) || !$conn) {
    die("<div class='fatal-error'>TITAN CORE OFFLINE: DATABASE SYNC FAILED</div>");
}

// 2. AUTO-PATCHING DATABASE INTEGRITY (Anti-Error 500)
$patch_sql = [
    "ALTER TABLE email_contacts ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(25) DEFAULT NULL",
    "ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS opened_at DATETIME DEFAULT NULL",
    "ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS clicked_at DATETIME DEFAULT NULL",
    "ALTER TABLE email_queue ADD COLUMN IF NOT EXISTS wa_followed_up TINYINT(1) DEFAULT 0",
    "CREATE TABLE IF NOT EXISTS `automation_tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `target_wa` VARCHAR(50) NOT NULL,
        `message` TEXT NOT NULL,
        `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        `requested_by` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];
foreach ($patch_sql as $sql) { @mysqli_query($conn, $sql); }

// 3. NEURAL API ROUTER (BACKEND LOGIC)
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];

    // API: FETCH INTELLIGENCE DATA
    if ($action === 'fetch_intelligence') {
        $filter = $_GET['filter'] ?? 'all';
        $where = "eq.status = 'sent' AND (eq.opened_at IS NOT NULL OR eq.clicked_at IS NOT NULL)";
        
        if ($filter === 'hot') $where .= " AND eq.clicked_at IS NOT NULL";
        if ($filter === 'warm') $where .= " AND eq.clicked_at IS NULL";
        if ($filter === 'followed') $where .= " AND eq.wa_followed_up = 1";

        $main_q = "SELECT eq.id as qid, ec.id as cid, ec.name, ec.email, ec.whatsapp, 
                          eq.opened_at, eq.clicked_at, eq.wa_followed_up, ecamp.subject 
                   FROM email_queue eq
                   JOIN email_contacts ec ON eq.contact_id = ec.id
                   JOIN email_campaigns ecamp ON eq.campaign_id = ecamp.id
                   WHERE $where
                   ORDER BY eq.clicked_at DESC, eq.opened_at DESC LIMIT 150";
        
        $res = mysqli_query($conn, $main_q);
        $leads = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $is_hot = !empty($r['clicked_at']);
            $r['temp_score'] = $is_hot ? 'HOT' : 'WARM';
            $r['timestamp_f'] = $is_hot ? date('H:i | d M', strtotime($r['clicked_at'])) : date('H:i | d M', strtotime($r['opened_at']));
            $leads[] = $r;
        }

        // Stats for Dashboard Metrics
        $stats_q = mysqli_fetch_assoc(mysqli_query($conn, "SELECT 
            SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as h,
            SUM(CASE WHEN clicked_at IS NULL AND opened_at IS NOT NULL THEN 1 ELSE 0 END) as w,
            SUM(wa_followed_up) as f
            FROM email_queue WHERE status='sent'"));

        echo json_encode(['leads' => $leads, 'stats' => $stats_q]);
        exit;
    }

    // API: DISPATCH NEBULA MISSION (WA AUTOMATION)
    if ($action === 'deploy_mission') {
        $qid = (int)$_POST['qid'];
        $cid = (int)$_POST['cid'];
        $wa  = mysqli_real_escape_string($conn, trim($_POST['wa']));
        $msg = mysqli_real_escape_string($conn, trim($_POST['msg']));
        
        // WhatsApp ID Normalizer
        $wa_clean = preg_replace('/\D/', '', $wa);
        if (substr($wa_clean, 0, 1) === '0') $wa_clean = '62' . substr($wa_clean, 1);
        $full_wa_id = $wa_clean . '@c.us';

        mysqli_query($conn, "UPDATE email_contacts SET whatsapp = '$wa_clean' WHERE id = $cid");
        $sql_task = "INSERT INTO automation_tasks (target_wa, message, status, requested_by) 
                     VALUES ('$full_wa_id', '$msg', 'pending', '6285895029511')";
        
        if (mysqli_query($conn, $sql_task)) {
            mysqli_query($conn, "UPDATE email_queue SET wa_followed_up = 1 WHERE id = $qid");
            echo json_encode(['status' => 'success', 'msg' => 'NEBULA DEPLOYED: Mission Successfully Transmitted!']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'NEURAL LINK ERROR: Task Insertion Failed.']);
        }
        exit;
    }
}
?>

<!-- =========================================================================================
     PREMIUM FRONTEND: TITAN OBSIDIAN DESIGN SYSTEM
     ========================================================================================= -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    /* TITAN LUXURY CORE CSS */
    :root {
        --t-neon: #a1ff5a;
        --t-cyan: #4efdc4;
        --t-hot: #ff4560;
        --t-warm: #ffab2e;
        --t-bg: #050505;
        --t-card: rgba(18, 18, 18, 0.7);
        --t-border: rgba(255, 255, 255, 0.08);
        --t-grad: linear-gradient(135deg, #a1ff5a 0%, #4efdc4 100%);
    }

    .titan-leads-viewport {
        padding-left: 115px; /* SAFE MARGIN FROM SIDEBAR */
        padding-right: 35px;
        padding-top: 20px;
        font-family: 'Plus Jakarta Sans', 'Montserrat', sans-serif;
        color: #fff;
    }

    /* HEADER & TITLE */
    .titan-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        animation: slideDown 0.8s ease-out;
    }
    .titan-header h1 { font-weight: 900; font-size: 3.2rem; margin: 0; letter-spacing: -2.5px; line-height: 1; }
    .titan-header p { color: #555; margin-top: 10px; font-size: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; }

    /* METRICS CARDS GRID */
    .metric-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
        margin-bottom: 40px;
    }

    .lux-card {
        background: var(--t-card);
        backdrop-filter: blur(30px);
        border: 1px solid var(--t-border);
        border-radius: 35px;
        padding: 30px;
        position: relative;
        overflow: hidden;
        transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .lux-card:hover { transform: translateY(-10px); border-color: var(--t-neon); box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
    
    .lux-card i { position: absolute; right: -10px; bottom: -10px; font-size: 6rem; opacity: 0.03; color: #fff; transform: rotate(-15deg); }
    .lux-card .m-title { display: block; font-size: 0.7rem; font-weight: 800; color: #555; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 12px; }
    .lux-card .m-value { font-size: 2.8rem; font-weight: 900; margin: 0; line-height: 1; }

    .card-hot { border-bottom: 5px solid var(--t-hot); }
    .card-warm { border-bottom: 5px solid var(--t-warm); }
    .card-action { border-bottom: 5px solid var(--t-neon); }

    /* MAIN CONTENT SPLIT */
    .titan-content-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 30px;
        height: calc(100vh - 450px);
    }

    /* TABLE DESIGN */
    .titan-table-container {
        background: var(--t-card);
        backdrop-filter: blur(40px);
        border: 1px solid var(--t-border);
        border-radius: 40px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 40px 100px rgba(0,0,0,0.6);
    }

    .table-top-strip {
        padding: 25px 35px;
        background: rgba(255,255,255,0.02);
        border-bottom: 1px solid var(--t-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .titan-filter-group { display: flex; gap: 10px; background: rgba(0,0,0,0.3); padding: 6px; border-radius: 50px; border: 1px solid var(--t-border); }
    .t-filter-btn {
        padding: 8px 22px; border-radius: 50px; border: none; background: transparent;
        color: #666; font-size: 0.7rem; font-weight: 800; cursor: pointer; transition: 0.3s;
    }
    .t-filter-btn.active { background: var(--t-grad); color: #000; box-shadow: 0 5px 15px rgba(161, 255, 90, 0.2); }

    .titan-scroll { flex: 1; overflow-y: auto; }
    .titan-scroll::-webkit-scrollbar { width: 6px; }
    .titan-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 20px; }

    .titan-table { width: 100%; border-collapse: collapse; }
    .titan-table th { padding: 25px 35px; text-align: left; font-size: 0.7rem; font-weight: 900; color: #444; text-transform: uppercase; letter-spacing: 2px; border-bottom: 1px solid var(--t-border); position: sticky; top: 0; background: #080808; z-index: 10; }
    .titan-table td { padding: 25px 35px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; transition: 0.3s; }
    .titan-table tr:hover td { background: rgba(161, 255, 90, 0.03); }

    .lead-main-info b { display: block; font-size: 1.1rem; font-weight: 800; color: #fff; margin-bottom: 4px; }
    .lead-main-info span { font-size: 0.8rem; color: #555; font-weight: 600; }

    .score-badge {
        padding: 6px 16px; border-radius: 50px; font-size: 0.65rem; font-weight: 900; letter-spacing: 1px;
        display: inline-flex; align-items: center; gap: 8px; border: 1px solid transparent;
    }
    .badge-hot { background: rgba(255, 69, 96, 0.12); color: var(--t-hot); border-color: rgba(255, 69, 96, 0.3); box-shadow: 0 0 15px rgba(255, 69, 96, 0.1); }
    .badge-warm { background: rgba(255, 171, 46, 0.12); color: var(--t-warm); border-color: rgba(255, 171, 46, 0.3); }

    /* ACTION BUTTONS */
    .btn-titan-action {
        background: var(--t-grad); color: #000; font-weight: 900; font-size: 0.7rem;
        padding: 12px 24px; border-radius: 50px; border: none; cursor: pointer;
        transition: 0.4s; text-transform: uppercase; letter-spacing: 1px;
        display: flex; align-items: center; gap: 10px;
    }
    .btn-titan-action:hover { transform: scale(1.05); box-shadow: 0 10px 25px rgba(161, 255, 90, 0.4); }
    .btn-titan-action.disabled { background: rgba(255,255,255,0.05); color: #444; border: 1px solid var(--t-border); pointer-events: none; }

    /* CHART PANEL */
    .titan-chart-panel {
        background: var(--t-card); backdrop-filter: blur(30px);
        border: 1px solid var(--t-border); border-radius: 40px;
        padding: 35px; display: flex; flex-direction: column;
        box-shadow: 0 30px 60px rgba(0,0,0,0.4);
    }

    /* PREMIUM MODAL LUXURY */
    .titan-modal {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.95); z-index: 1000000; backdrop-filter: blur(25px);
        align-items: center; justify-content: center;
    }
    .titan-modal.active { display: flex; animation: titanZoom 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
    @keyframes titanZoom { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }

    .modal-content-lux {
        width: 95%; max-width: 600px; background: #080808; border: 1px solid var(--t-neon);
        border-radius: 45px; padding: 50px; box-shadow: 0 0 100px rgba(161, 255, 90, 0.2);
    }
    .modal-input-lux {
        width: 100%; background: rgba(255,255,255,0.02); border: 1px solid var(--t-border);
        padding: 20px 25px; border-radius: 20px; color: #fff; font-family: inherit; font-size: 0.95rem;
        margin-bottom: 25px; outline: none; transition: 0.4s;
    }
    .modal-input-lux:focus { border-color: var(--t-neon); background: rgba(161,255,90,0.03); box-shadow: 0 0 20px rgba(161,255,90,0.1); }

    /* ANIMATIONS */
    @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 1300px) {
        .titan-content-grid { grid-template-columns: 1fr; height: auto; }
        .metric-row { grid-template-columns: repeat(2, 1fr); }
        .titan-leads-viewport { padding-left: 25px; }
    }
</style>

<div class="titan-leads-viewport">
    
    <!-- TITAN TOP HEADER -->
    <div class="titan-header">
        <div>
            <h1>Lead Interceptor</h1>
            <p><i class="fas fa-satellite-dish" style="color:var(--t-neon)"></i> HVM Neural Surveillance Engine Active</p>
        </div>
        <div style="display:flex; gap:15px;">
            <div style="background:rgba(255,255,255,0.03); border:1px solid var(--t-border); padding:12px 25px; border-radius:50px; font-size:0.7rem; font-weight:900; letter-spacing:1.5px;">
                SERVER STATUS: <span style="color:var(--t-neon)">ENCRYPTED</span>
            </div>
        </div>
    </div>

    <!-- METRICS GRID -->
    <div class="metric-row">
        <div class="lux-card">
            <i class="fas fa-signal"></i>
            <span class="m-title">Total Interaction</span>
            <h2 class="m-value" id="m-total">0</h2>
        </div>
        <div class="lux-card card-hot">
            <i class="fas fa-fire" style="color:var(--t-hot)"></i>
            <span class="m-title">Hot Leads (Clicked)</span>
            <h2 class="m-value" id="m-hot" style="color:var(--t-hot)">0</h2>
        </div>
        <div class="lux-card card-warm">
            <i class="fas fa-temperature-arrow-up" style="color:var(--t-warm)"></i>
            <span class="m-title">Warm Leads (Opened)</span>
            <h2 class="m-value" id="m-warm" style="color:var(--t-warm)">0</h2>
        </div>
        <div class="lux-card card-action">
            <i class="fas fa-robot" style="color:var(--t-neon)"></i>
            <span class="m-title">AI Dispatched</span>
            <h2 class="m-value" id="m-followed" style="color:var(--t-neon)">0</h2>
        </div>
    </div>

    <!-- MAIN OS INTERFACE -->
    <div class="titan-content-grid">
        
        <!-- LEAD REPOSITORY TABLE -->
        <div class="titan-table-container">
            <div class="table-top-strip">
                <h3 style="margin:0; font-weight:800; font-size:1.1rem; letter-spacing:-0.5px;">Intelligence Feed</h3>
                <div class="titan-filter-group">
                    <button class="t-filter-btn active" onclick="setLeadFilter('all', this)">ALL LEADS</button>
                    <button class="t-filter-btn" onclick="setLeadFilter('hot', this)">HOT TARGETS</button>
                    <button class="t-filter-btn" onclick="setLeadFilter('followed', this)">ARCHIVED</button>
                </div>
            </div>

            <div class="titan-scroll">
                <table class="titan-table">
                    <thead>
                        <tr>
                            <th>Prospect Identity</th>
                            <th>Intelligence Score</th>
                            <th>Engagement Context</th>
                            <th>Mission Control</th>
                        </tr>
                    </thead>
                    <tbody id="titan-lead-feed">
                        <!-- AJAX STREAMING DATA -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CONVERSION ANALYTICS -->
        <div class="titan-chart-panel">
            <div style="margin-bottom:30px;">
                <h3 style="margin:0; font-weight:800; font-size:1.2rem; letter-spacing:-0.5px;">Conversion Radar</h3>
                <p style="margin:5px 0 0 0; color:#555; font-size:0.75rem; font-weight:700;">Neural distribution of lead temperatures.</p>
            </div>
            
            <div style="flex:1; position:relative; width:100%; min-height:220px;">
                <canvas id="titanDoughnutChart"></canvas>
            </div>

            <div style="margin-top:40px; background:rgba(161, 255, 90, 0.04); border:1px dashed var(--t-neon); padding:25px; border-radius:30px;">
                <h4 style="margin:0 0 10px 0; color:var(--t-neon); font-size:0.8rem; text-transform:uppercase; letter-spacing:1px;"><i class="fas fa-brain"></i> Strategic Advice</h4>
                <p style="margin:0; font-size:0.75rem; color:#888; line-height:1.7;">
                    Sistem mendeteksi <b>HOT LEADS</b> adalah prioritas utama pengiriman Nebula AI. Klik <b>DEPLOY NEBULA</b> untuk memulai percakapan humanoid secara otomatis.
                </p>
            </div>
        </div>

    </div>
</div>

<!-- LUXURY MISSION MODAL -->
<div class="titan-modal" id="missionModal">
    <div class="modal-content-lux">
        <h2 style="margin:0 0 10px 0; font-weight:900; font-size:2rem; letter-spacing:-1.5px;"><i class="fas fa-rocket" style="color:var(--t-neon);"></i> Mission Dispatch</h2>
        <p style="margin:0 0 35px 0; color:#555; font-weight:700; font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Targeting: <span id="missionTargetName" style="color:#fff;">-</span></p>
        
        <input type="hidden" id="task_qid">
        <input type="hidden" id="task_cid">

        <div style="margin-bottom:25px;">
            <label class="titan-label" style="display:block; color:#666; font-size:0.7rem; font-weight:800; margin-bottom:10px; letter-spacing:2px;">VERIFIED CONTACT NUMBER</label>
            <input type="text" id="task_wa" class="modal-input-lux" placeholder="e.g. 62812345678">
        </div>

        <div style="margin-bottom:35px;">
            <label class="titan-label" style="display:block; color:#666; font-size:0.7rem; font-weight:800; margin-bottom:10px; letter-spacing:2px;">NEURAL SCRIPT INJECTION</label>
            <textarea id="task_msg" class="modal-input-lux" rows="6" style="resize:none;"></textarea>
        </div>

        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:15px;">
            <button class="btn-titan-main" onclick="executeTitanMission()" id="btn-deploy-mission">CONFIRM & DEPLOY</button>
            <button class="btn-titan-outline" onclick="abortMission()" style="border-radius:50px;">ABORT</button>
        </div>
    </div>
</div>

<!-- =========================================================================================
     TITAN NEURAL SCRIPTS
     ========================================================================================= -->
<script>
    let titanFilter = 'all';
    let titanChartInstance = null;

    // 1. ENGINE: FETCH REAL-TIME INTELLIGENCE
    async function syncTitanIntelligence() {
        try {
            const response = await fetch(`pages/warm_leads.php?api=fetch_intelligence&filter=${titanFilter}`);
            const data = await response.json();
            
            // Update Metrics
            const h = parseInt(data.stats.total_hot) || 0;
            const w = parseInt(data.stats.total_warm) || 0;
            const f = parseInt(data.stats.total_followed) || 0;

            document.getElementById('m-total').innerText = h + w;
            document.getElementById('m-hot').innerText = h;
            document.getElementById('m-warm').innerText = w;
            document.getElementById('m-followed').innerText = f;

            updateTitanChart(h, w);
            renderTitanTable(data.leads);

        } catch (e) { console.error("Neural Sync Error: Core Lost."); }
    }

    // 2. UI: RENDER LOG TABLE
    function renderTitanTable(leads) {
        const body = document.getElementById('titan-lead-feed');
        if (leads.length === 0) {
            body.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:100px; color:#444; font-weight:800; letter-spacing:1px; opacity:0.3;">NO INTELLIGENCE RECORDED</td></tr>`;
            return;
        }

        let html = '';
        leads.forEach(l => {
            const isHot = l.temp_score === 'HOT';
            const badgeClass = isHot ? 'badge-hot' : 'badge-warm';
            const isDone = parseInt(l.wa_followed_up) === 1;

            const btnHtml = isDone 
                ? `<button class="btn-titan-action disabled"><i class="fas fa-check-double"></i> DISPATCHED</button>`
                : `<button class="btn-titan-action" onclick="prepareMission(${l.qid}, ${l.cid}, '${l.name.replace(/'/g, "\\'")}', '${l.whatsapp}', '${l.subject.replace(/'/g, "\\'")}')"><i class="fas fa-rocket"></i> DEPLOY NEBULA</button>`;

            html += `
            <tr>
                <td><div class="lead-main-info"><b>${l.name}</b><span>${l.email}</span></div></td>
                <td><span class="score-badge ${badgeClass}"><i class="fas ${isHot ? 'fa-fire' : 'fa-temperature-half'}"></i> ${l.temp_score} LEAD</span></td>
                <td><div style="font-size:0.75rem; color:#888; margin-bottom:5px;">${l.subject.substring(0,40)}...</div><b style="font-size:0.8rem; color:#aaa;"><i class="fas fa-history" style="color:var(--t-neon)"></i> ${l.timestamp_f}</b></td>
                <td>${btnHtml}</td>
            </tr>`;
        });
        body.innerHTML = html;
    }

    // 3. UI: CHARTING ENGINE
    function updateTitanChart(hot, warm) {
        const ctx = document.getElementById('titanDoughnutChart').getContext('2d');
        if (titanChartInstance) titanChartInstance.destroy();

        titanChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['HOT TARGETS', 'WARM PROSPECTS'],
                datasets: [{
                    data: [hot, warm],
                    backgroundColor: ['#ff4560', '#ffab2e'],
                    borderWidth: 0,
                    hoverOffset: 20
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '85%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#555', font: { family: 'Plus Jakarta Sans', size: 10, weight: '900' }, padding: 20, usePointStyle: true } }
                },
                animation: { animateRotate: true, duration: 1500 }
            }
        });
    }

    // 4. ACTION: MISSION CONTROL (MODAL)
    function prepareMission(qid, cid, name, wa, subject) {
        document.getElementById('task_qid').value = qid;
        document.getElementById('task_cid').value = cid;
        document.getElementById('missionTargetName').innerText = name;
        document.getElementById('task_wa').value = wa;
        
        const script = `Selamat siang Bapak/Ibu ${name},\n\nSalam dari HVM Digital. Saya Nebula, asisten cerdas Pak Ilham. Saya melihat Anda baru saja meninjau proposal kami terkait "${subject}".\n\nApakah ada detail spesifik yang ingin Bapak/Ibu tanyakan atau diskusikan bersama tim kami?`;
        document.getElementById('task_msg').value = script;
        
        document.getElementById('missionModal').classList.add('active');
    }

    function abortMission() { document.getElementById('missionModal').classList.remove('active'); }

    async function executeTitanMission() {
        const btn = document.getElementById('btn-deploy-mission');
        btn.innerText = "TRANSMITTING..."; btn.style.opacity = "0.5";

        const fd = new FormData();
        fd.append('qid', document.getElementById('task_qid').value);
        fd.append('cid', document.getElementById('task_cid').value);
        fd.append('wa', document.getElementById('task_wa').value);
        fd.append('msg', document.getElementById('task_msg').value);

        try {
            const r = await fetch(`pages/warm_leads.php?api=deploy_mission`, { method: 'POST', body: fd });
            const res = await r.json();
            if (res.status === 'success') {
                showPopup('success', res.msg);
                abortMission();
                syncTitanIntelligence();
            }
        } catch (e) { showPopup('error', "Neural Transmission Failed."); }
        finally { btn.innerText = "CONFIRM & DEPLOY"; btn.style.opacity = "1"; }
    }

    function setLeadFilter(f, btn) {
        titanFilter = f;
        document.querySelectorAll('.t-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        syncTitanIntelligence();
    }

    // LAUNCH ALL SYSTEMS
    syncTitanIntelligence();
    setInterval(syncTitanIntelligence, 10000); // Polling every 10 seconds

</script>