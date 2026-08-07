<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(!isset($_SESSION['admin'])){ header("Location: /"); exit; }
$user_logged = $_SESSION['admin'];

// SINKRONISASI DATA PERSONAL
$q_u = mysqli_query($conn, "SELECT name, photo FROM teams WHERE name = '$user_logged' LIMIT 1");
$u_data = mysqli_fetch_assoc($q_u);
$full_name = $u_data['name'] ?? $user_logged;
$profile_img = ($u_data && $u_data['photo']) ? '/uploads/teams/'.$u_data['photo'] : null;

// EVENT SAVE
if(isset($_POST['save_event'])){
    // Auto-add columns if missing
    $cols = [
        'meeting_type'   => "VARCHAR(100) DEFAULT NULL",
        'meeting_mode'   => "VARCHAR(20) DEFAULT NULL",
        'target_type'    => "VARCHAR(20) DEFAULT NULL",
        'target_name'    => "VARCHAR(255) DEFAULT NULL",
        'location'       => "TEXT DEFAULT NULL"
    ];
    foreach($cols as $col => $def){
        $chk = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE '$col'");
        if(mysqli_num_rows($chk) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `$col` $def");
    }

    $title       = mysqli_real_escape_string($conn, $_POST['event_title'] ?? '');
    $date        = $_POST['event_date'] ?? date('Y-m-d');
    $start       = $_POST['time_start'] ?? '00:00';
    $color       = mysqli_real_escape_string($conn, $_POST['event_color'] ?? 'blue');
    $meet_type   = mysqli_real_escape_string($conn, $_POST['meeting_type'] ?? '');
    $meet_mode   = mysqli_real_escape_string($conn, $_POST['meeting_mode'] ?? 'Online');
    $target_type = mysqli_real_escape_string($conn, $_POST['target_type'] ?? '');
    $target_name = mysqli_real_escape_string($conn, $_POST['target_name'] ?? '');
    $location    = mysqli_real_escape_string($conn, $_POST['location'] ?? '');

    // Auto-generate label: e.g. "Meeting Prospek PT XX"
    if($meet_type && $target_name) {
        $title = "Meeting $meet_type $target_name";
    }

    $desc = "[$meet_mode] $title";
    if($location) $desc .= " | Lokasi: $location";

    mysqli_query($conn, "INSERT INTO events (title, detail, event_date, time_start, color, meeting_type, meeting_mode, target_type, target_name, location) VALUES ('$title', '$desc', '$date', '$start', '$color', '$meet_type', '$meet_mode', '$target_type', '$target_name', '$location')");
    header("Location: index.php"); exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>HVM | WorkSpace</title>
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    <link rel="stylesheet" href="/dashboard/workspace/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
    /* Meeting Modal Chips */
    .meet-type-chip {
        display:inline-flex; align-items:center; padding:6px 14px;
        border-radius:20px; font-size:0.75rem; font-weight:700;
        background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12);
        color:#888; cursor:pointer; transition:all 0.2s; user-select:none;
    }
    input[type=radio]:checked + .meet-type-chip {
        background:rgba(161,255,90,0.12); border-color:rgba(161,255,90,0.4); color:#a1ff5a;
    }
    .meet-mode-chip {
        display:inline-flex; align-items:center; gap:6px; padding:8px 18px;
        border-radius:10px; font-size:0.8rem; font-weight:700;
        background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
        color:#888; cursor:pointer; transition:all 0.2s;
    }
    .meet-mode-chip.active { background:rgba(78,253,196,0.1); border-color:rgba(78,253,196,0.3); color:#4efdc4; }
    .target-type-btn {
        padding:7px 16px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);
        background:rgba(255,255,255,0.04); color:#888; font-family:'Montserrat',sans-serif;
        font-size:0.78rem; font-weight:600; cursor:pointer; transition:all 0.2s;
        display:flex; align-items:center; gap:6px;
    }
    .target-type-btn.active { background:rgba(161,255,90,0.1); border-color:rgba(161,255,90,0.3); color:#a1ff5a; }
    </style>
</head>
<body class="custom-scrollbar-page">
    <div class="cyber-overlay"></div>
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="zenith-wrapper">
        <!-- SIDEBAR -->
        <aside class="zenith-sidebar animate-slide-right">
            <div class="sidebar-capsule-v30">
                <div class="sb-top">
                    <button class="sb-btn" onclick="location.href='/dashboard/'" title="Back to Dashboard"><i class="fas fa-home"></i></button>
                    <div class="sb-divider"></div>
                </div>
                <!-- Realtime Presence -->
                <div class="collaborator-stack" id="realtimePresence"></div>
                <div class="sb-bottom">
                    <button class="btn-nebula-trigger pulse-glow" onclick="toggleNebulaAI()"></button>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="zenith-main-deck">
            <header class="workspace-top-header animate-reveal">
                <div class="nav-left">
                    <div class="brand-v30">
                        <img src="/uploads/icon.png" class="brand-icon-big pulse-glow">
                        <div class="headline-group">
                            <h1 class="headline-v30">WorkSpace <b class="text-neon-gradient">HVM</b></h1>
                            <div class="subheadline-v30">INTEGRATED PRODUCTIVITY SUITE</div>
                        </div>
                    </div>
                </div>
                <div class="nav-right">
                    <div class="system-status-v30">CORE_V34 • ONLINE</div>
                </div>
            </header>

            <div class="zenith-grid-layout">
                <!-- PANEL 1: PLANNER -->
                <div class="zenith-panel glass-card planner-deck animate-slide-up">
                    <div class="panel-header-v30">
                        <div class="ph-left">
                            <h2 id="plannerTitle">...</h2>
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
                    
                    <div id="calendarViewport" class="planner-viewport"></div>
                    
                    <!-- Floating Add Button -->
                    <button class="add-event-fab" onclick="openEventModal()"><i class="fas fa-plus"></i></button>
                </div>
            </div>

            <!-- SECTION 3: NEBULA KEEP (FULL FEATURES) -->
            <section class="nebula-keep-section animate-slide-up" style="animation-delay: 0.2s;">
                <div class="keep-layout">
                    <aside class="keep-sidebar">
                        <button class="keep-side-btn active" id="btn-view-notes" onclick="switchKeepView('notes')"><i class="far fa-lightbulb"></i> <span>Catatan</span></button>
                        <button class="keep-side-btn" id="btn-view-reminders" onclick="switchKeepView('reminders')"><i class="far fa-bell"></i> <span>Pengingat</span></button>
                        <button class="keep-side-btn" id="btn-view-trash" onclick="switchKeepView('trash')"><i class="far fa-trash-alt"></i> <span>Sampah</span></button>
                    </aside>

                    <div class="keep-main">
                        <div class="keep-input-wrapper">
                            <div class="keep-input-card glass-container" id="keepInputBox">
                                <input type="text" id="keepTitle" placeholder="Judul" style="display:none;">
                                <textarea id="keepNote" placeholder="Buat catatan..." onfocus="expandKeep()"></textarea>
                                
                                <div class="keep-toolbar" id="keepTools" style="display:none;">
                                    <div class="tool-icons">
                                        <i class="fas fa-palette" onclick="toggleColorPalette()" title="Warna"></i>
                                        <i class="fas fa-thumbtack" title="Sematkan" id="pinToggle" onclick="this.classList.toggle('active')"></i>
                                    </div>
                                    <button class="btn-keep-close" onclick="saveKeep()">Simpan</button>
                                    
                                    <!-- Color Picker Popup -->
                                    <div class="color-palette" id="colorPalette" style="display:none;">
                                        <div class="color-circle" style="background:transparent;border:1px solid #fff;" onclick="setNoteColor('default')"></div>
                                        <div class="color-circle" style="background:#5c2b29;" onclick="setNoteColor('red')"></div>
                                        <div class="color-circle" style="background:#614a19;" onclick="setNoteColor('orange')"></div>
                                        <div class="color-circle" style="background:#635d19;" onclick="setNoteColor('yellow')"></div>
                                        <div class="color-circle" style="background:#345920;" onclick="setNoteColor('green')"></div>
                                        <div class="color-circle" style="background:#16504b;" onclick="setNoteColor('teal')"></div>
                                        <div class="color-circle" style="background:#2d555e;" onclick="setNoteColor('blue')"></div>
                                        <div class="color-circle" style="background:#42275e;" onclick="setNoteColor('purple')"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="keep-label-group" id="pinnedLabel" style="display:none;">DISEMATKAN</div>
                        <div class="keep-grid" id="pinnedGrid"></div>
                        
                        <div class="keep-label-group" style="margin-top:20px;">LAINNYA</div>
                        <div class="keep-grid" id="notesGrid"></div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- MODAL EDIT NOTE (FULL FEATURE) -->
    <div class="modal-overlay" id="editNoteModal">
        <div class="modal-content" style="border-color:var(--neon);">
            <div class="modal-top-actions">
                <button class="btn-close-x" onclick="document.getElementById('editNoteModal').classList.remove('active')">&times;</button>
            </div>
            
            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <input type="text" id="editTitle" placeholder="Judul" style="background:none; border:none; color:#fff; font-size:1.4rem; font-weight:800; width:90%; outline:none;">
                    <i class="fas fa-thumbtack" id="editPinBtn" style="cursor:pointer; color:#555; font-size:1.2rem;" onclick="toggleEditPin()"></i>
                </div>
            </div>
            
            <textarea id="editContent" style="width:100%; height:250px; background:none; border:none; color:#ccc; resize:none; outline:none; font-family:'Montserrat'; line-height:1.6; font-size:1rem;"></textarea>
            
            <div class="form-group" style="margin-top:20px; border-top:1px solid var(--border); padding-top:15px;">
                <label style="color:var(--cyan); font-weight:bold;">Pengingat:</label>
                <input type="datetime-local" id="editReminder" class="form-input">
            </div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
                <i class="fas fa-trash-alt" style="cursor:pointer; color:var(--red); font-size:1.2rem;" onclick="trashCurrentNote()" title="Buang ke Sampah"></i>
                <button class="btn-save-center" onclick="saveEditedNote()"><i class="fas fa-check"></i></button>
            </div>
            
            <input type="hidden" id="editNoteId">
            <input type="hidden" id="editColor">
        </div>
    </div>

    <!-- MODAL ADD EVENT / MEETING -->
    <div class="modal-overlay" id="eventModal">
        <div class="modal-content" style="max-width:580px;">
            <div class="modal-top-actions">
                <button class="btn-close-x" onclick="document.getElementById('eventModal').classList.remove('active')">&times;</button>
            </div>
            <h3 style="color:#fff; margin-bottom:20px; font-weight:800; font-size:1.3rem;"><i class="fas fa-calendar-plus" style="color:#a1ff5a;margin-right:8px;"></i>Buat Meeting</h3>
            
            <form method="POST" id="eventForm">
                <input type="hidden" name="save_event" value="1">

                <!-- KATEGORI MEETING -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">Jenis Meeting</label>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <?php foreach(['Prospek','Maintenance','After Sales','Internal','Presentasi'] as $mt): ?>
                        <label style="cursor:pointer;">
                            <input type="radio" name="meeting_type" value="<?= $mt ?>" style="display:none;" onchange="updateMeetingTitle()">
                            <span class="meet-type-chip"><?= $mt ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- MODE: ONLINE / OFFLINE -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">Mode Meeting</label>
                    <div style="display:flex; gap:8px;">
                        <label style="cursor:pointer;">
                            <input type="radio" name="meeting_mode" value="Online" checked style="display:none;" onchange="toggleLocationField(this.value)">
                            <span class="meet-mode-chip active" id="chip-online"><i class="fas fa-video"></i> Online</span>
                        </label>
                        <label style="cursor:pointer;">
                            <input type="radio" name="meeting_mode" value="Offline" style="display:none;" onchange="toggleLocationField(this.value)">
                            <span class="meet-mode-chip" id="chip-offline"><i class="fas fa-map-marker-alt"></i> Offline</span>
                        </label>
                    </div>
                </div>

                <!-- TARGET: CLIENT / PROSPECT -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">Perusahaan</label>
                    <div style="display:flex; gap:8px; margin-bottom:8px;">
                        <button type="button" class="target-type-btn active" id="btn-client" onclick="switchTargetType('Client')"><i class="fas fa-users"></i> Clients</button>
                        <button type="button" class="target-type-btn" id="btn-prospect" onclick="switchTargetType('Prospect')"><i class="fas fa-binoculars"></i> Prospects</button>
                    </div>
                    <input type="hidden" name="target_type" id="target_type_val" value="Client">
                    <select name="target_name" id="companySelect" class="form-input" style="background:#111;" onchange="updateMeetingTitle()">
                        <option value="">-- Pilih Perusahaan --</option>
                        <?php
                        $qc = mysqli_query($conn, "SELECT company_name FROM clients ORDER BY company_name ASC");
                        while($r = mysqli_fetch_assoc($qc)) echo "<option value='".htmlspecialchars($r['company_name'])."' data-type='Client'>".htmlspecialchars($r['company_name'])."</option>";
                        // Prospects
                        $qp_chk = mysqli_query($conn, "SHOW TABLES LIKE 'prospects'");
                        if(mysqli_num_rows($qp_chk) > 0){
                            $qp = mysqli_query($conn, "SELECT company_name FROM prospects ORDER BY company_name ASC");
                            while($r = mysqli_fetch_assoc($qp)) echo "<option value='".htmlspecialchars($r['company_name'])."' data-type='Prospect' class='opt-prospect' style='display:none'>".htmlspecialchars($r['company_name'])."</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- TANGGAL & JAM -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div class="form-group">
                        <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">Tanggal</label>
                        <input type="date" name="event_date" id="formDate" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">Jam</label>
                        <input type="time" name="time_start" class="form-input">
                    </div>
                </div>

                <!-- LOKASI -->
                <div class="form-group" id="locationField" style="margin-bottom:14px;">
                    <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;" id="locationLabel">Link Meeting (Google Meet / Zoom)</label>
                    <input type="text" name="location" class="form-input" id="locationInput" placeholder="https://meet.google.com/...">
                </div>

                <!-- PREVIEW JUDUL -->
                <div id="titlePreview" style="display:none; background:rgba(161,255,90,0.06); border:1px solid rgba(161,255,90,0.2); border-radius:10px; padding:10px 14px; margin-bottom:14px; font-size:0.85rem; color:#a1ff5a;"></div>

                <!-- COLOR (hidden, auto based on type) -->
                <input type="hidden" name="event_color" value="green">

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:6px;">
                    <button type="button" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#888;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:0.82rem;cursor:pointer;" onclick="document.getElementById('eventModal').classList.remove('active')">Batal</button>
                    <button type="submit" style="background:linear-gradient(135deg,#a1ff5a,#4efdc4);border:none;color:#000;border-radius:10px;padding:9px 22px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;"><i class="fas fa-check" style="margin-right:6px;"></i>Simpan Meeting</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL VIEW DETAIL (NEW) -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-content" style="border-color: var(--cyan);">
            <div class="modal-top-actions">
                <button class="btn-close-x" onclick="closeModal('detailModal')">&times;</button>
            </div>
            <h2 class="modal-title" style="color:var(--cyan); margin-bottom:20px;">Project Detail</h2>
            <div id="detailContent"></div>
        </div>
    </div>

    <!-- AI POPUP & JS -->
<div id="nebulaPopup" class="ai-popup-overlay">
    <div class="ai-popup-content">
        <div class="ai-popup-header">
            <div class="ai-header-brand">
                <div class="nebula-avatar-ring">
                    <img src="/uploads/nebula.png" onerror="this.src='https://ui-avatars.com/api/?name=N&background=a1ff5a&color=000'">
                </div>
                <div class="ai-title-group">
                    <h4>Nebula AI</h4>
                    <div class="ai-status">SYSTEM ACTIVE</div>
                </div>
            </div>
            <button class="btn-close-ai" style="background:none; border:none; color:#555; font-size:1.5rem; cursor:pointer;" onclick="toggleNebulaAI()">&times;</button>
        </div>
        
        <div id="aiChatBox" class="ai-popup-body custom-scrollbar-v28">
            <div class="ai-msg">Halo <b><?= $full_name ?></b>, Core Intelligence siap membantu. Silahkan ketik perintah atau pertanyaan strategis kamu.</div>
        </div>

        <div class="ai-popup-footer">
            <!-- FITUR DEEPTHINK -->
            <div class="deep-think-toggle" id="deepThinkBtn" onclick="toggleDeepThink()">
                <i class="fas fa-brain"></i>
                <span>DEEPTHINK MODE</span>
            </div>

            <div class="footer-input-box">
                <input type="text" id="aiMsgInput" placeholder="Ketik perintah neural...">
                <button class="btn-send-v30" onclick="sendNebulaChat()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

    <script>
        let currentDate = new Date();
        let curMode = 'month';
        let noteColor = 'default';
        let currentKeepView = 'notes';

        // 1. PLANNER LOGIC
        async function refreshPlanner() {
            const vp = document.getElementById('calendarViewport');
            const mt = document.getElementById('plannerTitle');
            const dStr = currentDate.toISOString().split('T')[0];
            const months = ["JANUARI","FEBRUARI","MARET","APRIL","MEI","JUNI","JULI","AGUSTUS","SEPTEMBER","OKTOBER","NOVEMBER","DESEMBER"];
            mt.innerText = months[currentDate.getMonth()] + " " + currentDate.getFullYear();
            
            try {
                const res = await fetch(`planner_logic_v28.php?date=${dStr}&mode=${curMode}`);
                vp.innerHTML = await res.text();
            } catch(e) {
                vp.innerHTML = "Error loading calendar.";
            }
        }

        function setMode(m, btn) { curMode = m; document.querySelectorAll('.mode-switch-v30 button').forEach(el => el.classList.remove('active')); btn.classList.add('active'); refreshPlanner(); }
        function navigatePlanner(dir) {
            if(curMode === 'month') currentDate.setMonth(currentDate.getMonth() + dir);
            else currentDate.setDate(currentDate.getDate() + (dir * 7));
            refreshPlanner();
        }
        function goToday() { currentDate = new Date(); refreshPlanner(); }
        
        function openEventModal(dateStr = '') {
            document.getElementById('eventModal').classList.add('active');
            if(dateStr) document.getElementById('formDate').value = dateStr;
            else document.getElementById('formDate').value = currentDate.toISOString().split('T')[0];
        }

        // Meeting Modal JS
        function toggleLocationField(mode) {
            document.getElementById('chip-online').classList.toggle('active', mode === 'Online');
            document.getElementById('chip-offline').classList.toggle('active', mode === 'Offline');
            const lbl = document.getElementById('locationLabel');
            const inp = document.getElementById('locationInput');
            if(mode === 'Offline') {
                lbl.textContent = 'Lokasi / Alamat Meeting';
                inp.placeholder = 'Jl. Contoh No. 1, Kota...';
            } else {
                lbl.textContent = 'Link Meeting (Google Meet / Zoom)';
                inp.placeholder = 'https://meet.google.com/...';
            }
        }

        function switchTargetType(type) {
            document.getElementById('target_type_val').value = type;
            document.getElementById('btn-client').classList.toggle('active', type === 'Client');
            document.getElementById('btn-prospect').classList.toggle('active', type === 'Prospect');
            const sel = document.getElementById('companySelect');
            Array.from(sel.options).forEach(opt => {
                if(!opt.value) { opt.style.display = ''; return; }
                const optType = opt.dataset.type;
                opt.style.display = (optType === type) ? '' : 'none';
            });
            sel.value = '';
            updateMeetingTitle();
        }

        function updateMeetingTitle() {
            const typeEl = document.querySelector('input[name=meeting_type]:checked');
            const company = document.getElementById('companySelect').value;
            const preview = document.getElementById('titlePreview');
            if(typeEl && company) {
                preview.style.display = 'block';
                preview.innerHTML = '<i class="fas fa-eye" style="margin-right:6px;"></i>Judul: <strong>Meeting ' + typeEl.value + ' ' + company + '</strong>';
            } else {
                preview.style.display = 'none';
            }
        }

        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        // FUNGSI TAMPIL DETAIL (POP UP)
        function showEventDetail(title, date, time, desc, color) {
            const container = document.getElementById('detailContent');
            const dateObj = new Date(date);
            const dateNice = dateObj.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            
            container.innerHTML = `
                <div class="detail-row"><span class="detail-label">NAMA PROJECT</span><div class="detail-val" style="color:var(--${color=='blue'?'cyan':'neon'})">${title}</div></div>
                <div class="detail-row"><span class="detail-label">WAKTU</span><div class="detail-val">${dateNice} • ${time || 'Seharian'}</div></div>
                <div class="detail-row"><span class="detail-label">DETAIL LOG</span><div class="detail-desc">${desc}</div></div>
            `;
            document.getElementById('detailModal').classList.add('active');
        }

        // 2. NEBULA AI & KEEP (Logika tetap sama)
        function toggleNebulaAI() { document.getElementById('nebulaPopup').classList.toggle('active'); }
        async function sendNebulaChat() {
    const input = document.getElementById('aiMsgInput');
    const box = document.getElementById('aiChatBox');
    const userMsg = input.value.trim();

    if (!userMsg) return;

    // 1. Tampilkan pesan User ke chatbox
    box.innerHTML += `<div class="msg user" style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 10px; margin-bottom: 10px; align-self: flex-end; border: 1px solid rgba(255,255,255,0.1); font-size: 0.9rem;">${userMsg}</div>`;
    input.value = "";
    box.scrollTop = box.scrollHeight;

    // 2. Tampilkan Loading Nebula
    const loadingId = "typing-" + Date.now();
    box.innerHTML += `<div class="ai-msg" id="${loadingId}"><i class="fas fa-circle-notch fa-spin"></i> Nebula is thinking...</div>`;
    box.scrollTop = box.scrollHeight;

    try {
        // Menembak ke ai-handler.php
        const response = await fetch('ai-handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                message: userMsg,
                deepThink: false 
            })
        });

        const result = await response.json();
        
        // 3. Tampilkan Jawaban dari AI
        if (result.choices && result.choices[0].message.content) {
            document.getElementById(loadingId).innerHTML = `<b>Nebula:</b><br>${result.choices[0].message.content}`;
        } else {
            throw new Error("Format response error");
        }

    } catch (error) {
        console.error(error);
        document.getElementById(loadingId).innerHTML = `<span style="color:#ff5a5a;"><b>Nebula:</b> Honestly, neural link lagi bermasalah. Coba cek koneksi atau database kamu ya!</span>`;
    }
    box.scrollTop = box.scrollHeight;
}

// Supaya bisa kirim pesan pakai tombol Enter
document.getElementById('aiMsgInput').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') sendNebulaChat();
});

        function expandKeep() { document.getElementById('keepTitle').style.display = 'block'; document.getElementById('keepTools').style.display = 'flex'; document.getElementById('keepInputBox').classList.add('expanded'); }
        function collapseKeep() { document.getElementById('keepTitle').style.display = 'none'; document.getElementById('keepTools').style.display = 'none'; document.getElementById('keepInputBox').classList.remove('expanded'); }
        
        function saveKeep() {
            const t = document.getElementById('keepTitle').value;
            const c = document.getElementById('keepNote').value;
            const isPinned = document.getElementById('pinToggle').classList.contains('active') ? 1 : 0;
            
            if(t || c) {
                const fd = new FormData(); fd.append('action','save_note'); fd.append('title',t); fd.append('content',c); fd.append('color',noteColor); fd.append('is_pinned',isPinned);
                fetch('ajax.php', { method:'POST', body:fd }).then(r=>r.json()).then(d=>{ if(d.status==='success') loadNotes(); });
            }
            collapseKeep();
            document.getElementById('keepTitle').value = '';
            document.getElementById('keepNote').value = '';
        }

        function switchKeepView(view) {
            currentKeepView = view;
            document.querySelectorAll('.keep-side-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('btn-view-'+view).classList.add('active');
            if(view === 'trash') document.querySelector('.keep-input-wrapper').style.display = 'none';
            else document.querySelector('.keep-input-wrapper').style.display = 'flex';
            loadNotes();
        }

        function loadNotes() {
            fetch(`ajax.php?action=get_notes&view=${currentKeepView}`).then(r=>r.json()).then(notes => {
                let pinnedHtml = '', otherHtml = '';
                const colorMap = { 'red': 'rgba(92, 43, 41, 0.6)', 'orange': 'rgba(97, 74, 25, 0.6)', 'yellow': 'rgba(99, 93, 25, 0.6)', 'green': 'rgba(52, 89, 32, 0.6)', 'teal': 'rgba(22, 80, 75, 0.6)', 'blue': 'rgba(45, 85, 94, 0.6)', 'purple': 'rgba(66, 39, 94, 0.6)', 'default': 'rgba(255,255,255,0.03)' };

                if (notes.length === 0) {
                    document.getElementById('notesGrid').innerHTML = '<div style="color:#555; text-align:center; grid-column:span 3;">Tidak ada catatan.</div>';
                    document.getElementById('pinnedGrid').innerHTML = '';
                    document.getElementById('pinnedLabel').style.display = 'none';
                    return;
                }

                notes.forEach(n => {
                    const nData = encodeURIComponent(JSON.stringify(n));
                    const bg = colorMap[n.color] || colorMap['default'];
                    const pinClass = n.is_pinned == 1 ? 'nc-pin-icon active' : 'nc-pin-icon';
                    const reminderHtml = n.reminder_date ? `<div class="nc-reminder"><i class="far fa-clock"></i> ${n.reminder_date.substr(0,16)}</div>` : '';

                    const card = `
                        <div class="note-card" style="background:${bg}; border-color:${bg!=='rgba(255,255,255,0.03)'?'transparent':'var(--border)'}" onclick="openEditNote('${nData}')">
                            <i class="fas fa-thumbtack ${pinClass}"></i>
                            <div class="nc-title">${n.title}</div>
                            <div class="nc-body">${n.content}</div>
                            ${reminderHtml}
                        </div>`;
                    
                    if(n.is_pinned == 1 && currentKeepView !== 'trash') pinnedHtml += card; else otherHtml += card;
                });
                
                document.getElementById('pinnedGrid').innerHTML = pinnedHtml;
                document.getElementById('notesGrid').innerHTML = otherHtml;
                const pinLbl = document.getElementById('pinnedLabel');
                if(pinnedHtml && currentKeepView === 'notes') { pinLbl.style.display = 'block'; } else { pinLbl.style.display = 'none'; }
            });
        }

        // EDIT NOTE
        function openEditNote(json) {
            const data = JSON.parse(decodeURIComponent(json));
            const modal = document.getElementById('editNoteModal');
            document.getElementById('editTitle').value = data.title;
            document.getElementById('editContent').value = data.content;
            document.getElementById('editNoteId').value = data.id;
            document.getElementById('editColor').value = data.color;
            document.getElementById('editReminder').value = data.reminder_date ? data.reminder_date.replace(' ', 'T') : '';
            
            const pinBtn = document.getElementById('editPinBtn');
            if(data.is_pinned == 1) { pinBtn.style.color = 'var(--neon)'; pinBtn.classList.add('active'); }
            else { pinBtn.style.color = '#555'; pinBtn.classList.remove('active'); }
            
            const colorMap = { 'red': '#5c2b29', 'orange': '#614a19', 'yellow': '#635d19', 'green': '#345920', 'teal': '#16504b', 'blue': '#2d555e', 'purple': '#42275e', 'default': '#0a0a0a' };
            modal.querySelector('.modal-content').style.background = colorMap[data.color] || '#0a0a0a';
            modal.classList.add('active');
        }

        function saveEditedNote() {
            const id = document.getElementById('editNoteId').value;
            const t = document.getElementById('editTitle').value;
            const c = document.getElementById('editContent').value;
            const col = document.getElementById('editColor').value;
            const isP = document.getElementById('editPinBtn').classList.contains('active') ? 1 : 0;
            const rem = document.getElementById('editReminder').value;
            
            const fd = new FormData();
            fd.append('action', 'save_note'); fd.append('id', id); fd.append('title', t);
            fd.append('content', c); fd.append('color', col); fd.append('is_pinned', isP); fd.append('reminder', rem);
            fetch('ajax.php', { method:'POST', body:fd }).then(() => {
                document.getElementById('editNoteModal').classList.remove('active');
                loadNotes();
            });
        }
        
        function trashCurrentNote() {
            const id = document.getElementById('editNoteId').value;
            const action = currentKeepView === 'trash' ? 'delete_forever' : 'trash_note';
            const fd = new FormData(); fd.append('action', action); fd.append('id', id);
            fetch('ajax.php', { method:'POST', body:fd }).then(() => { document.getElementById('editNoteModal').classList.remove('active'); loadNotes(); });
        }
        
        function toggleEditPin() { document.getElementById('editPinBtn').classList.toggle('active'); }

        function toggleColorPalette() {
            const p = document.getElementById('colorPalette');
            p.style.display = p.style.display === 'flex' ? 'none' : 'flex';
        }
        function setNoteColor(color) {
            noteColor = color;
            const colorMap = {
                'red': '#5c2b29', 'orange': '#614a19', 'yellow': '#635d19', 'green': '#345920', 
                'teal': '#16504b', 'blue': '#2d555e', 'purple': '#42275e', 'default': '#000'
            };
            document.getElementById('keepInputBox').style.background = colorMap[color];
            toggleColorPalette();
        }

        // INIT
        refreshPlanner();
        loadNotes();
        document.getElementById('realtimePresence').innerHTML = `<div class="avatar-box-v31 active"><img src="<?= $profile_img ?: 'https://ui-avatars.com/api/?name='.$user_logged ?>"><div class="pulse-dot"></div></div>`;
    </script>
</body>
</html>