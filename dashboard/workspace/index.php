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

// AUTO-FIX COLUMNS (always run)
$_cols = [
    'meeting_type'   => "VARCHAR(100) DEFAULT NULL",
    'meeting_mode'   => "VARCHAR(20) DEFAULT NULL",
    'target_type'    => "VARCHAR(20) DEFAULT NULL",
    'target_name'    => "VARCHAR(255) DEFAULT NULL",
    'location'       => "TEXT DEFAULT NULL",
    'target_id'      => "INT DEFAULT NULL",
    'log_hasil'      => "TEXT DEFAULT NULL",
    'teams_involved' => "TEXT DEFAULT NULL",
];
foreach($_cols as $_col => $_def){
    $_chk = mysqli_query($conn, "SHOW COLUMNS FROM `events` LIKE '$_col'");
    if(mysqli_num_rows($_chk) == 0) mysqli_query($conn, "ALTER TABLE `events` ADD COLUMN `$_col` $_def");
}
// Sync target_id for old events
mysqli_query($conn, "UPDATE events e JOIN clients c ON e.target_name = c.company_name SET e.target_id = c.client_id WHERE e.target_type = 'Client' AND (e.target_id IS NULL OR e.target_id = 0)");
$_chk_pros = mysqli_query($conn, "SHOW TABLES LIKE 'prospects'");
if(mysqli_num_rows($_chk_pros) > 0) {
    mysqli_query($conn, "UPDATE events e JOIN prospects p ON e.target_name = p.company_name SET e.target_id = p.id WHERE e.target_type = 'Prospect' AND (e.target_id IS NULL OR e.target_id = 0)");
}

// AJAX: GET EVENT
if(isset($_GET['get_event']) && !empty($_GET['id'])) {
    header('Content-Type: application/json');
    $eid = intval($_GET['id']);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM events WHERE id=$eid"));
    // Also fetch team list
    $teams = [];
    $qt = mysqli_query($conn, "SELECT name FROM teams ORDER BY name ASC");
    while($t = mysqli_fetch_assoc($qt)) $teams[] = $t['name'];
    echo json_encode(['event' => $row, 'teams' => $teams]);
    exit;
}

// AJAX: UPDATE EVENT
if(isset($_POST['update_event'])) {
    header('Content-Type: application/json');
    $eid       = intval($_POST['event_id']);
    $meet_type = mysqli_real_escape_string($conn, $_POST['meeting_type'] ?? '');
    $meet_mode = mysqli_real_escape_string($conn, $_POST['meeting_mode'] ?? 'Online');
    $target_name = mysqli_real_escape_string($conn, $_POST['target_name'] ?? '');
    $target_type = mysqli_real_escape_string($conn, $_POST['target_type'] ?? '');
    $event_date  = mysqli_real_escape_string($conn, $_POST['event_date'] ?? '');
    $time_start  = mysqli_real_escape_string($conn, $_POST['time_start'] ?? '');
    $location    = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $log_hasil   = mysqli_real_escape_string($conn, $_POST['log_hasil'] ?? '');
    $teams_str   = mysqli_real_escape_string($conn, implode(',', $_POST['teams_involved'] ?? []));
    $title = $meet_type && $target_name ? "Meeting $meet_type $target_name" : mysqli_real_escape_string($conn, $_POST['title'] ?? '');
    $detail = "[$meet_mode] $title" . ($location ? " | Lokasi: $location" : '');

    $target_id = 0;
    if($target_type === 'Client') { $q2=mysqli_query($conn,"SELECT client_id FROM clients WHERE company_name='$target_name' LIMIT 1"); if($r2=mysqli_fetch_assoc($q2)) $target_id=(int)$r2['client_id']; }
    elseif($target_type === 'Prospect') { $q2=mysqli_query($conn,"SELECT id FROM prospects WHERE company_name='$target_name' LIMIT 1"); if($r2=mysqli_fetch_assoc($q2)) $target_id=(int)$r2['id']; }

    mysqli_query($conn, "UPDATE events SET title='$title', detail='$detail', event_date='$event_date', time_start='$time_start', meeting_type='$meet_type', meeting_mode='$meet_mode', target_type='$target_type', target_name='$target_name', location='$location', log_hasil='$log_hasil', teams_involved='$teams_str', target_id=$target_id WHERE id=$eid");
    echo json_encode(['ok' => true]);
    exit;
}

// AJAX: DELETE EVENT
if(isset($_POST['delete_event'])) {
    header('Content-Type: application/json');
    $eid = intval($_POST['event_id']);
    mysqli_query($conn, "DELETE FROM events WHERE id=$eid");
    echo json_encode(['ok' => true]);
    exit;
}

// EVENT SAVE
if(isset($_POST['save_event'])) {
    $title        = mysqli_real_escape_string($conn, $_POST['event_title'] ?? '');
    $date         = $_POST['event_date'] ?? date('Y-m-d');
    $start        = $_POST['time_start'] ?? '00:00';
    $color        = mysqli_real_escape_string($conn, $_POST['event_color'] ?? 'green');
    $meet_type    = mysqli_real_escape_string($conn, $_POST['meeting_type'] ?? '');
    $meet_mode    = mysqli_real_escape_string($conn, $_POST['meeting_mode'] ?? 'Online');
    $target_type  = mysqli_real_escape_string($conn, $_POST['target_type'] ?? '');
    $target_name  = mysqli_real_escape_string($conn, $_POST['target_name'] ?? '');
    $location     = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $teams_raw    = $_POST['teams_involved'] ?? [];
    $teams_str    = mysqli_real_escape_string($conn, implode(',', $teams_raw));

    $target_id = 0;
    if($target_type === 'Client') {
        $q_cli = mysqli_query($conn, "SELECT client_id FROM clients WHERE company_name='$target_name' LIMIT 1");
        if($r_cli = mysqli_fetch_assoc($q_cli)) $target_id = (int)$r_cli['client_id'];
    } elseif($target_type === 'Prospect') {
        $q_pro = mysqli_query($conn, "SELECT id FROM prospects WHERE company_name='$target_name' LIMIT 1");
        if($r_pro = mysqli_fetch_assoc($q_pro)) $target_id = (int)$r_pro['id'];
    }

    if($meet_type && $target_name) $title = "Meeting $meet_type $target_name";
    $desc = "[$meet_mode] $title";
    if($location) $desc .= " | Lokasi: $location";

    mysqli_query($conn, "INSERT INTO events (title, detail, event_date, time_start, color, meeting_type, meeting_mode, target_type, target_name, location, target_id, teams_involved) VALUES ('$title', '$desc', '$date', '$start', '$color', '$meet_type', '$meet_mode', '$target_type', '$target_name', '$location', $target_id, '$teams_str')");
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
        <!-- SIDEBAR (Pakai sidebar utama dashboard) -->
        <?php include $_SERVER['DOCUMENT_ROOT'] . '/dashboard/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="zenith-main-deck">
            <header class="workspace-top-header animate-reveal">
                <div class="nav-left">
                    <div class="brand-v30">
                        <img src="/uploads/icon.png" class="brand-icon-big pulse-glow">
                        <div class="headline-group">
                            <h1 class="headline-v30">WorkSpace <b class="text-neon-gradient">HVM</b></h1>
                            <div class="subheadline-v30">Ruang Kerja & Kolaborasi Tim</div>
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

                <!-- TEAM YANG IKUT MEETING -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;"><i class="fas fa-users" style="margin-right:4px;"></i>Tim yang Hadir</label>
                    <div id="teamCheckboxList" style="display:flex; flex-wrap:wrap; gap:8px;">
                        <?php
                        $qt = mysqli_query($conn, "SELECT team_id, name, photo FROM teams ORDER BY name ASC");
                        while($tm = mysqli_fetch_assoc($qt)):
                            $photo_url = $tm['photo'] ? '/uploads/teams/'.$tm['photo'] : null;
                        ?>
                        <label style="cursor:pointer; display:flex; align-items:center; gap:6px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:8px; padding:6px 10px; transition:0.2s;" class="team-check-label">
                            <input type="checkbox" name="teams_involved[]" value="<?= htmlspecialchars($tm['name']) ?>" style="display:none;" class="team-cb" onchange="updateTeamLabel(this)">
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

                <!-- LOKASI -->
                <div class="form-group" id="locationField" style="margin-bottom:14px;">
                    <label style="color:#888; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;" id="locationLabel">Link Meeting (Google Meet / Zoom)</label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" name="location" class="form-input" id="locationInput" placeholder="https://meet.google.com/..." style="flex:1;">
                        <button type="button" id="mapsBtnMeeting" onclick="openMapsFromInput()" title="Buka di Maps" style="display:none; width:42px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:10px; color:#4efdc4; font-size:1.1rem; cursor:pointer; transition:0.2s;" onmouseover="this.style.background='rgba(78,253,196,0.1)'"; onmouseout="this.style.background='rgba(255,255,255,0.05)'"><i class="fas fa-map-marked-alt"></i></button>
                    </div>
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

    <!-- MODAL DETAIL / EDIT EVENT -->
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

    <!-- MODAL EDIT CATATAN (KEEP) - Full Featured Like Google Keep -->
    <div class="modal-overlay" id="editNoteModal" style="z-index:10000;background:rgba(0,0,0,0.85);backdrop-filter:blur(16px);" onclick="if(event.target===this) saveEditedNote()">
        <div id="editNoteModalContent" style="
            background:#0c0c0e;
            border:1px solid rgba(255,255,255,0.1);
            width:900px; max-width:96vw;
            height:88vh; max-height:88vh;
            border-radius:20px;
            box-shadow:0 30px 80px rgba(0,0,0,0.95), 0 0 0 1px rgba(161,255,90,0.06);
            position:relative; display:flex; flex-direction:column; overflow:hidden;
            transition:transform 0.28s cubic-bezier(0.34,1.56,0.64,1), opacity 0.2s;
            transform:scale(0.95); opacity:0;">

            <!-- HEADER -->
            <div style="padding:18px 24px 14px;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:12px;flex-shrink:0;">
                <input type="text" id="editTitle" placeholder="Judul catatan..."
                    style="background:transparent;border:none;color:#fff;font-size:1.15rem;font-weight:700;font-family:inherit;outline:none;flex:1;letter-spacing:-0.3px;">
                <button id="editPinBtn" onclick="toggleEditPin()" title="Sematkan"
                    style="background:none;border:none;color:#555;font-size:1.15rem;cursor:pointer;transition:color 0.2s,transform 0.2s;padding:6px;"
                    onmouseover="this.style.transform='rotate(-20deg)'" onmouseout="this.style.transform=''">
                    <i class="fas fa-thumbtack"></i>
                </button>
                <button onclick="closeModal('editNoteModal')"
                    style="background:none;border:none;color:#555;font-size:1.3rem;cursor:pointer;padding:4px 8px;transition:color 0.2s;"
                    onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#555'">&times;</button>
            </div>

            <!-- BODY: 2 KOLOM (kiri: teks | kanan: gambar) -->
            <div style="flex:1;display:flex;overflow:hidden;min-height:0;">
                <!-- Kolom kiri: textarea -->
                <div style="flex:1;padding:20px 24px;display:flex;flex-direction:column;overflow-y:auto;border-right:1px solid rgba(255,255,255,0.05);">
                    <textarea id="editContent" placeholder="Tulis catatanmu di sini..."
                        style="flex:1;width:100%;background:transparent;border:none;color:#d0d0d0;
                               font-size:0.95rem;font-family:inherit;outline:none;resize:none;
                               line-height:1.8;min-height:300px;"></textarea>
                    <!-- Reminder -->
                    <div style="margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:8px;">
                        <i class="far fa-clock" style="color:#555;"></i>
                        <label style="font-size:0.72rem;color:#555;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Pengingat:</label>
                        <input type="datetime-local" id="editReminder"
                            style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#aaa;
                                   padding:5px 10px;border-radius:8px;font-family:inherit;font-size:0.8rem;outline:none;flex:1;">
                    </div>
                    <input type="hidden" id="editNoteId">
                    <input type="hidden" id="editColor">
                    <input type="hidden" id="imgRemoveFlag" value="0">
                </div>

                <!-- Kolom kanan: upload gambar -->
                <div style="width:280px;flex-shrink:0;padding:20px;display:flex;flex-direction:column;gap:14px;background:rgba(255,255,255,0.015);overflow-y:auto;">
                    <div style="font-size:0.7rem;color:#555;font-weight:700;text-transform:uppercase;letter-spacing:1px;">
                        <i class="fas fa-image" style="margin-right:6px;"></i>Lampiran Gambar
                    </div>

                    <!-- Preview gambar yang sudah ada -->
                    <div id="editImgPreviewWrap" style="display:none;position:relative;border-radius:10px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);">
                        <img id="editImgPreview" src="" style="width:100%;object-fit:cover;max-height:180px;display:block;">
                        <button onclick="removeNoteImage()" title="Hapus Gambar"
                            style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,0.8);border:none;color:#ff5a5a;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:0.8rem;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Preview gambar baru yang dipilih -->
                    <div id="newImgPreviewWrap" style="display:none;border-radius:10px;overflow:hidden;border:1px solid rgba(161,255,90,0.3);">
                        <img id="newImgPreview" src="" style="width:100%;object-fit:cover;max-height:180px;display:block;">
                        <div style="background:rgba(161,255,90,0.08);padding:6px 10px;font-size:0.68rem;color:#a1ff5a;text-align:center;">
                            <i class="fas fa-check-circle"></i> Siap diunggah
                        </div>
                    </div>

                    <!-- Upload trigger -->
                    <label for="editImgFile" style="cursor:pointer;">
                        <div style="border:2px dashed rgba(255,255,255,0.12);border-radius:12px;padding:28px 16px;text-align:center;transition:all 0.2s;"
                            onmouseover="this.style.borderColor='rgba(161,255,90,0.4)';this.style.background='rgba(161,255,90,0.04)'"
                            onmouseout="this.style.borderColor='rgba(255,255,255,0.12)';this.style.background=''">
                            <i class="fas fa-cloud-upload-alt" style="font-size:1.6rem;color:#555;margin-bottom:10px;display:block;"></i>
                            <div style="font-size:0.75rem;color:#666;font-weight:600;">Klik untuk pilih gambar</div>
                            <div style="font-size:0.65rem;color:#444;margin-top:4px;">JPG, PNG, WebP, GIF</div>
                        </div>
                    </label>
                    <input type="file" id="editImgFile" accept="image/*" style="display:none;" onchange="previewNewImage(this)">
                </div>
            </div>

            <!-- FOOTER -->
            <div style="padding:14px 24px;border-top:1px solid rgba(255,255,255,0.07);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
                <div style="display:flex;gap:4px;">
                    <button type="button" onclick="trashNote(document.getElementById('editNoteId').value)" title="Pindah ke Sampah"
                        style="background:transparent;border:none;color:#555;font-size:1rem;cursor:pointer;padding:8px;border-radius:8px;transition:all 0.2s;"
                        onmouseover="this.style.color='#ff5a5a';this.style.background='rgba(255,90,90,0.1)'"
                        onmouseout="this.style.color='#555';this.style.background='transparent'">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <span style="font-size:0.72rem;color:#444;" id="editAutoSaveStatus"></span>
                    <button type="button" onclick="closeModal('editNoteModal')"
                        style="background:transparent;border:1px solid rgba(255,255,255,0.1);color:#888;border-radius:10px;padding:8px 18px;font-family:inherit;font-size:0.82rem;cursor:pointer;transition:all 0.2s;"
                        onmouseover="this.style.color='#fff';this.style.borderColor='rgba(255,255,255,0.3)'"
                        onmouseout="this.style.color='#888';this.style.borderColor='rgba(255,255,255,0.1)'">Tutup</button>
                    <button type="button" onclick="saveEditedNote()"
                        style="background:linear-gradient(135deg,#a1ff5a,#4efdc4);border:none;color:#000;border-radius:10px;padding:8px 22px;font-family:inherit;font-size:0.82rem;font-weight:800;cursor:pointer;transition:transform 0.15s,box-shadow 0.15s;"
                        onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 20px rgba(161,255,90,0.4)'"
                        onmouseout="this.style.transform='';this.style.boxShadow=''">
                        <i class="fas fa-save" style="margin-right:6px;"></i>Simpan
                    </button>
                </div>
            </div>
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
                const res = await fetch(`planner_logic_v28.php?date=${dStr}&mode=${curMode}&_=${Date.now()}`, {cache: 'no-store'});
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
            const mapsBtn = document.getElementById('mapsBtnMeeting');
            if(mode === 'Offline') {
                lbl.textContent = 'Lokasi / Alamat Meeting';
                inp.placeholder = 'Jl. Contoh No. 1, Kota...';
                if(mapsBtn) mapsBtn.style.display = 'flex';
            } else {
                lbl.textContent = 'Link Meeting (Google Meet / Zoom)';
                inp.placeholder = 'https://meet.google.com/...';
                if(mapsBtn) mapsBtn.style.display = 'none';
            }
        }

        function updateTeamLabel(cb) {
            const label = cb.closest('.team-check-label');
            if(cb.checked) {
                label.style.background = 'rgba(161,255,90,0.1)';
                label.style.borderColor = 'rgba(161,255,90,0.4)';
            } else {
                label.style.background = 'rgba(255,255,255,0.04)';
                label.style.borderColor = 'rgba(255,255,255,0.08)';
            }
        }

        function openMapsFromInput() {
            const val = document.getElementById('locationInput').value.trim();
            if(!val) return;
            window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(val), '_blank');
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

        // FUNGSI TAMPIL DETAIL + EDIT (menggunakan event ID sebagai argumen ke-6 untuk backward compatibility cache)
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
                    <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);padding:12px;border-radius:10px;color:#aaa;font-size:0.85rem;line-height:1.6;white-space:pre-wrap;">${desc}</div>
                </div>
                <div id="extraEventDetail" style="color:#888;font-size:0.8rem;">Memuat detail...</div>
            `;
            footer.innerHTML = `
                <button onclick="deleteEvent(${eventId})" style="background:rgba(255,90,90,0.1);border:1px solid rgba(255,90,90,0.3);color:#ff5a5a;border-radius:10px;padding:8px 16px;font-family:inherit;font-size:0.82rem;cursor:pointer;"><i class="fas fa-trash"></i> Hapus</button>
                <button onclick="openEditEvent(${eventId})" style="background:linear-gradient(135deg,#a1ff5a,#4efdc4);border:none;color:#000;border-radius:10px;padding:8px 20px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;"><i class="fas fa-edit"></i> Edit Meeting</button>
            `;
            document.getElementById('detailModal').classList.add('active');

            // Load extra detail via AJAX if id is valid
            if(eventId > 0) {
                fetch(`index.php?get_event=1&id=${eventId}`)
                    .then(r => r.json())
                    .then(res => {
                        const ev = res.event;
                        if(!ev) return;
                        let extra = '';
                        if(ev.meeting_type) extra += `<div style="margin-bottom:10px;"><span style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">JENIS & MODE</span><div style="margin-top:4px;">${ev.meeting_type} &bull; <span style="color:#4efdc4;">${ev.meeting_mode}</span></div></div>`;
                        if(ev.target_name) extra += `<div style="margin-bottom:10px;"><span style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">PERUSAHAAN</span><div style="margin-top:4px;color:#fff;font-weight:600;">${ev.target_type}: ${ev.target_name}</div></div>`;
                        if(ev.location) extra += `<div style="margin-bottom:10px;"><span style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">LOKASI</span><div style="margin-top:4px;"><a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(ev.location)}" target="_blank" style="color:#ff9f43;text-decoration:none;">${ev.location} <i class="fas fa-external-link-alt" style="font-size:0.7rem;"></i></a></div></div>`;
                        if(ev.teams_involved) { const tms=ev.teams_involved.split(',').filter(t=>t.trim()); if(tms.length>0) extra += `<div style="margin-bottom:10px;"><span style="font-size:0.65rem;color:#666;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;">TIM HADIR</span><div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;">${tms.map(t=>`<span style="font-size:0.72rem;background:rgba(161,255,90,0.08);border:1px solid rgba(161,255,90,0.2);color:#a1ff5a;padding:2px 8px;border-radius:20px;">${t.trim()}</span>`).join('')}</div></div>`; }
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
            fetch('index.php', {method:'POST', body:fd})
                .then(r=>r.json())
                .then(()=>{ closeModal('detailModal'); refreshPlanner(); })
                .catch(()=>alert('Gagal hapus.'));
        }

        async function openEditEvent(id) {
            const res = await fetch(`index.php?get_event=1&id=${id}`).then(r=>r.json());
            const ev = res.event;
            const teams = res.teams || [];
            if(!ev) return;

            document.getElementById('detailModalTitle').innerHTML = '<i class="fas fa-edit" style="color:#a1ff5a;margin-right:8px;"></i>Edit Meeting';
            document.getElementById('detailContent').innerHTML = `
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Jenis Meeting</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;" id="editMeetTypes">
                        ${['Prospek','Maintenance','After Sales','Internal','Presentasi'].map(mt=>`<label style="cursor:pointer;"><input type="radio" name="em_meet_type" value="${mt}" ${ev.meeting_type===mt?'checked':''} style="display:none;"><span class="meet-type-chip" style="${ev.meeting_type===mt?'background:rgba(161,255,90,0.2);border-color:rgba(161,255,90,0.6);color:#a1ff5a;':''}">${mt}</span></label>`).join('')}
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
                        <label style="cursor:pointer;"><input type="radio" name="em_mode" value="Online" ${ev.meeting_mode!=='Offline'?'checked':''} style="display:none;"><span class="meet-mode-chip ${ev.meeting_mode!=='Offline'?'active':''}"><i class="fas fa-video"></i> Online</span></label>
                        <label style="cursor:pointer;"><input type="radio" name="em_mode" value="Offline" ${ev.meeting_mode==='Offline'?'checked':''} style="display:none;"><span class="meet-mode-chip ${ev.meeting_mode==='Offline'?'active':''}"><i class="fas fa-map-marker-alt"></i> Offline</span></label>
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Perusahaan</label>
                    <input type="text" id="em_target_name" value="${ev.target_name||''}" style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;padding:8px;font-family:inherit;">
                    <input type="hidden" id="em_target_type" value="${ev.target_type||'Client'}">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Lokasi / Link Meeting</label>
                    <div style="display:flex;gap:6px;">
                        <input type="text" id="em_location" value="${ev.location||''}" style="flex:1;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;padding:8px;font-family:inherit;">
                        <button type="button" onclick="if(document.getElementById('em_location').value){window.open('https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(document.getElementById('em_location').value),'_blank');}" style="width:36px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#4efdc4;cursor:pointer;"><i class="fas fa-map-marked-alt"></i></button>
                    </div>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:8px;"><i class="fas fa-users" style="margin-right:4px;"></i>Tim yang Hadir</label>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        ${teams.map(tm=>{ const checked=(ev.teams_involved||'').split(',').map(s=>s.trim()).includes(tm); return `<label style="cursor:pointer;display:flex;align-items:center;gap:5px;background:${checked?'rgba(161,255,90,0.1)':'rgba(255,255,255,0.04)'};border:1px solid ${checked?'rgba(161,255,90,0.4)':'rgba(255,255,255,0.08)'};border-radius:8px;padding:5px 10px;transition:0.2s;" class="team-check-label"><input type="checkbox" name="em_teams[]" value="${tm}" ${checked?'checked':''} style="display:none;" class="team-cb" onchange="updateTeamLabel(this)"><span style="font-size:0.8rem;color:#ccc;">${tm}</span></label>`; }).join('')}
                    </div>
                </div>
                <div style="margin-bottom:6px;">
                    <label style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Log Hasil Meeting</label>
                    <textarea id="em_log" rows="3" style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:8px;padding:10px;font-family:inherit;resize:none;">${ev.log_hasil||''}</textarea>
                </div>
            `;
            document.getElementById('detailFooter').innerHTML = `
                <button onclick="showEventDetail('${ev.title}','${ev.event_date}','${ev.time_start}','${ev.detail||''}','${ev.color}',${id})" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#888;border-radius:10px;padding:8px 16px;font-family:inherit;font-size:0.82rem;cursor:pointer;">← Kembali</button>
                <button onclick="saveEditEvent(${id})" style="background:linear-gradient(135deg,#a1ff5a,#4efdc4);border:none;color:#000;border-radius:10px;padding:8px 20px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;"><i class="fas fa-save"></i> Simpan</button>
            `;
            // Activate radio chips
            document.querySelectorAll('#detailContent input[name=em_meet_type]').forEach(r=>r.addEventListener('change', function(){ document.querySelectorAll('#editMeetTypes .meet-type-chip').forEach(s=>{s.style.background='';s.style.borderColor='';s.style.color='';}); this.nextElementSibling.style.background='rgba(161,255,90,0.2)'; this.nextElementSibling.style.borderColor='rgba(161,255,90,0.6)'; this.nextElementSibling.style.color='#a1ff5a'; }));
            document.querySelectorAll('#detailContent input[name=em_mode]').forEach(r=>r.addEventListener('change', function(){ document.querySelectorAll('#detailContent .meet-mode-chip').forEach(s=>s.classList.remove('active')); this.nextElementSibling.classList.add('active'); }));
        }

        function saveEditEvent(id) {
            const meet_type = document.querySelector('#detailContent input[name=em_meet_type]:checked')?.value || '';
            const meet_mode = document.querySelector('#detailContent input[name=em_mode]:checked')?.value || 'Online';
            const target_name = document.getElementById('em_target_name').value;
            const target_type = document.getElementById('em_target_type').value;
            const event_date  = document.getElementById('em_date').value;
            const time_start  = document.getElementById('em_time').value;
            const location    = document.getElementById('em_location').value;
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
            fd.append('log_hasil', log_hasil);
            teams.forEach(t => fd.append('teams_involved[]', t));

            fetch('index.php', {method:'POST', body:fd})
                .then(r=>r.json())
                .then(res=>{ if(res.ok){ closeModal('detailModal'); refreshPlanner(); } })
                .catch(()=>alert('Gagal simpan.'));
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

        // ── notesDataMap: simpan data note di sini, bukan di onclick ──
        let notesDataMap = {};

        function loadNotes() {
            fetch(`ajax.php?action=get_notes&view=${currentKeepView}`).then(r=>r.json()).then(notes => {
                notesDataMap = {}; // reset map
                let pinnedHtml = '', otherHtml = '';
                const colorMapBg  = { red:'rgba(92,43,41,0.7)', orange:'rgba(97,74,25,0.7)', yellow:'rgba(99,93,25,0.7)', green:'rgba(52,89,32,0.7)', teal:'rgba(22,80,75,0.7)', blue:'rgba(45,85,94,0.7)', purple:'rgba(66,39,94,0.7)', default:'rgba(255,255,255,0.03)' };

                if(notes.length === 0) {
                    const msg = currentKeepView === 'trash' ? 'Sampah kosong.' : 'Belum ada catatan.';
                    document.getElementById('notesGrid').innerHTML = `<div style="color:#555;text-align:center;grid-column:span 3;padding:40px 0;">${msg}</div>`;
                    document.getElementById('pinnedGrid').innerHTML = '';
                    document.getElementById('pinnedLabel').style.display = 'none';
                    return;
                }

                notes.forEach(n => {
                    // Simpan di map agar onclick tinggal pakai ID
                    notesDataMap[n.id] = n;
                    const bg = colorMapBg[n.color] || colorMapBg.default;
                    const isDefault = (n.color === 'default' || !n.color);
                    const pinIcon = n.is_pinned == 1 ? '<i class="fas fa-thumbtack nc-pin-icon active" title="Disematkan"></i>' : '';
                    const reminderHtml = n.reminder_date ? `<div class="nc-reminder"><i class="far fa-clock"></i> ${n.reminder_date.substr(0,16)}</div>` : '';
                    const imgThumb = n.image_path ? `<img src="${n.image_path}" style="width:100%;max-height:120px;object-fit:cover;border-radius:8px;margin-top:8px;">` : '';
                    const trashFooter = currentKeepView === 'trash' ? `
                        <div style="margin-top:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,0.08);display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.65rem;color:#ff6b6b;"><i class="fas fa-exclamation-circle"></i> Sisa ${n.days_left||0} hari</span>
                            <div style="display:flex;gap:6px;">
                                <button data-nid="${n.id}" class="btn-restore" style="background:rgba(161,255,90,0.15);border:none;color:#a1ff5a;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:0.72rem;"><i class="fas fa-trash-restore"></i></button>
                                <button data-nid="${n.id}" class="btn-del-forever" style="background:rgba(255,90,90,0.15);border:none;color:#ff5a5a;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:0.72rem;"><i class="fas fa-times"></i></button>
                            </div>
                        </div>` : '';

                    const clickAttr = (currentKeepView !== 'trash') ? `data-note-id="${n.id}"` : '';
                    const card = `<div class="note-card" style="background:${bg};border:1px solid ${isDefault?'rgba(255,255,255,0.08)':'transparent'};" ${clickAttr}>
                        ${pinIcon}
                        ${n.title ? `<div class="nc-title">${n.title}</div>` : ''}
                        <div class="nc-body">${n.content}</div>
                        ${imgThumb}${reminderHtml}${trashFooter}
                    </div>`;

                    if(n.is_pinned == 1 && currentKeepView !== 'trash') pinnedHtml += card;
                    else otherHtml += card;
                });

                document.getElementById('pinnedGrid').innerHTML = pinnedHtml;
                document.getElementById('notesGrid').innerHTML  = otherHtml;
                document.getElementById('pinnedLabel').style.display = (pinnedHtml && currentKeepView === 'notes') ? 'block' : 'none';

                // Pasang event listener lewat delegation (aman dari special chars)
                document.querySelectorAll('[data-note-id]').forEach(el => {
                    el.addEventListener('click', function() { openEditNote(+this.dataset.noteId); });
                });
                document.querySelectorAll('.btn-restore').forEach(btn => {
                    btn.addEventListener('click', function(e) { e.stopPropagation(); restoreNote(+this.dataset.nid); });
                });
                document.querySelectorAll('.btn-del-forever').forEach(btn => {
                    btn.addEventListener('click', function(e) { e.stopPropagation(); deleteNoteForever(+this.dataset.nid); });
                });
            });
        }
        
        function saveEditedNote() {
            const id  = document.getElementById('editNoteId').value;
            const t   = document.getElementById('editTitle').value;
            const c   = document.getElementById('editContent').value;
            const col = document.getElementById('editColor').value;
            const isP = document.getElementById('editPinBtn').classList.contains('active') ? 1 : 0;
            const rem = document.getElementById('editReminder').value;
            const imgFile  = document.getElementById('editImgFile').files[0];
            const removeImg = document.getElementById('imgRemoveFlag').value;

            const fd = new FormData();
            fd.append('action','save_note');
            fd.append('id', id);
            fd.append('title', t);
            fd.append('content', c);
            fd.append('color', col);
            fd.append('is_pinned', isP);
            if(rem) fd.append('reminder', rem.replace('T',' '));
            if(imgFile) fd.append('note_image', imgFile);
            if(removeImg === '1') fd.append('remove_image','1');

            fetch('ajax.php', { method:'POST', body:fd }).then(r=>r.json()).then(d=> {
                if(d.status==='success') {
                    const mc = document.getElementById('editNoteModalContent');
                    mc.style.transform='scale(0.95)'; mc.style.opacity='0';
                    setTimeout(()=>{ document.getElementById('editNoteModal').classList.remove('active'); loadNotes(); }, 200);
                }
            });
        }
        
        function trashNote(id) {
            Swal.fire({ title:'Pindah ke Sampah?', text:'Catatan akan dihapus permanen dalam 30 hari.', icon:'warning', showCancelButton:true, confirmButtonText:'Ya, Pindahkan', cancelButtonText:'Batal', background:'#111', color:'#fff' }).then((result)=>{
                if(result.isConfirmed) {
                    const fd = new FormData(); fd.append('action','trash_note'); fd.append('id',id);
                    fetch('ajax.php',{method:'POST',body:fd}).then(()=> { loadNotes(); closeModal('editNoteModal'); });
                }
            });
        }
        function restoreNote(id) {
            const fd = new FormData(); fd.append('action','restore_note'); fd.append('id',id);
            fetch('ajax.php',{method:'POST',body:fd}).then(()=>loadNotes());
        }
        function deleteNoteForever(id) {
            Swal.fire({ title:'Hapus Permanen?', text:'Tindakan ini tidak bisa dibatalkan!', icon:'error', showCancelButton:true, confirmButtonText:'Hapus Permanen', cancelButtonText:'Batal', background:'#111', color:'#fff' }).then((result)=>{
                if(result.isConfirmed) {
                    const fd = new FormData(); fd.append('action','delete_forever'); fd.append('id',id);
                    fetch('ajax.php',{method:'POST',body:fd}).then(()=>loadNotes());
                }
            });
        }

        // EDIT NOTE - by ID (safe, no JSON in onclick)
        function openEditNote(id) {
            const data = notesDataMap[id];
            if(!data) return;
            document.getElementById('editTitle').value   = data.title || '';
            document.getElementById('editContent').value = data.content || '';
            document.getElementById('editNoteId').value  = data.id;
            document.getElementById('editColor').value   = data.color || 'default';
            document.getElementById('editReminder').value = data.reminder_date ? data.reminder_date.replace(' ','T').substr(0,16) : '';

            const pinBtn = document.getElementById('editPinBtn');
            const isPinned = data.is_pinned == 1;
            pinBtn.classList.toggle('active', isPinned);
            pinBtn.querySelector('i').style.color = isPinned ? '#a1ff5a' : '#555';

            // Show / hide existing image
            const imgPreview = document.getElementById('editImgPreview');
            if(data.image_path) {
                imgPreview.src = data.image_path;
                imgPreview.parentElement.style.display = 'block';
            } else {
                imgPreview.parentElement.style.display = 'none';
            }
            document.getElementById('newImgPreviewWrap').style.display = 'none';
            document.getElementById('editImgFile').value = '';
            document.getElementById('imgRemoveFlag').value = '0';

            const colorMap = { red:'#5c2b29', orange:'#614a19', yellow:'#635d19', green:'#345920', teal:'#16504b', blue:'#2d555e', purple:'#42275e', default:'#0d0d0d' };
            const mc = document.getElementById('editNoteModalContent');
            mc.style.background = colorMap[data.color] || '#0d0d0d';

            const modal = document.getElementById('editNoteModal');
            modal.classList.add('active');
            requestAnimationFrame(() => { mc.style.transform='scale(1)'; mc.style.opacity='1'; });
            document.getElementById('editContent').focus();
        }

        // --- 4. REALTIME PRESENCE LOGIC ---
        function updateRealtimePresence() {
            fetch('presence_ping.php?page=workspace')
                .then(r => r.json())
                .then(users => {
                    const container = document.getElementById('realtimePresence');
                    if(!container) return;
                    let html = '';
                    users.forEach((u, i) => {
                        // Max 4 avatars to avoid overflow in thin sidebar
                        if(i > 3) return;
                        
                        let imgUrl = u.photo ? '/uploads/teams/' + u.photo : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name) + '&background=a1ff5a&color=000';
                        if(u.photo === 'default-profile.png') imgUrl = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(u.name) + '&background=a1ff5a&color=000';
                        
                        // Z-index to stack avatars nicely
                        let zIdx = 10 - i; 
                        
                        html += `
                            <div class="collab-avatar animate-pop" style="z-index:${zIdx}; position:relative; margin-bottom:-10px;" title="${u.name} (${u.role})">
                                <img src="${imgUrl}" alt="${u.name}" style="width:36px; height:36px; border-radius:50%; border:2px solid #050505; object-fit:cover;">
                                <div class="online-indicator" style="position:absolute; bottom:2px; right:2px; width:10px; height:10px; background:#a1ff5a; border-radius:50%; border:2px solid #050505;"></div>
                            </div>
                        `;
                    });
                    
                    if(users.length > 4) {
                        html += `
                            <div class="collab-avatar animate-pop" style="z-index:1; position:relative; display:flex; justify-content:center; align-items:center; width:36px; height:36px; border-radius:50%; border:2px solid #050505; background:rgba(255,255,255,0.1); color:#fff; font-size:0.7rem; font-weight:700;" title="+${users.length - 4} lainnya">
                                +${users.length - 4}
                            </div>
                        `;
                    }
                    
                    container.innerHTML = html;
                })
                .catch(e => console.error("Presence Error:", e));
        }
        
        // Run immediately, then every 20 seconds
        updateRealtimePresence();
        setInterval(updateRealtimePresence, 20000);

        function saveEditedNote() {
            const id = document.getElementById('editNoteId').value;
            const t = document.getElementById('editTitle').value;
            const c = document.getElementById('editContent').value;
            const col = document.getElementById('editColor').value;
            const isP = document.getElementById('editPinBtn').classList.contains('active') ? 1 : 0;
            const rem = document.getElementById('editReminder').value;
            
            const fd = new FormData();
            fd.append('action', 'save_note'); fd.append('id', id); fd.append('title', t);
            fd.append('content', c); fd.append('color', col); fd.append('is_pinned', isP);
            if(rem) fd.append('reminder', rem.replace('T', ' '));
            fetch('ajax.php', { method:'POST', body:fd }).then(() => {
                // Animate out then close
                const mc = document.getElementById('editNoteModalContent');
                mc.style.transform = 'scale(0.95)';
                mc.style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('editNoteModal').classList.remove('active');
                    mc.style.transform = 'scale(0.95)';
                    mc.style.opacity = '0';
                    loadNotes();
                }, 200);
            });
        }
        
        function previewNewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('newImgPreview').src = e.target.result;
                    document.getElementById('newImgPreviewWrap').style.display = 'block';
                    document.getElementById('imgRemoveFlag').value = '0';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeNoteImage() {
            document.getElementById('editImgPreviewWrap').style.display = 'none';
            document.getElementById('newImgPreviewWrap').style.display = 'none';
            document.getElementById('editImgFile').value = '';
            document.getElementById('imgRemoveFlag').value = '1';
        }

        function toggleEditPin() {
            const btn = document.getElementById('editPinBtn');
            btn.classList.toggle('active');
            btn.querySelector('i').style.color = btn.classList.contains('active') ? '#a1ff5a' : '#555';
        }

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
        // closeModal override for editNoteModal to animate out
        const _origCloseModal = closeModal;
        closeModal = function(id) {
            if(id === 'editNoteModal') {
                const mc = document.getElementById('editNoteModalContent');
                mc.style.transform = 'scale(0.95)';
                mc.style.opacity = '0';
                setTimeout(() => { document.getElementById(id).classList.remove('active'); }, 200);
            } else {
                document.getElementById(id).classList.remove('active');
            }
        };
    </script>
</body>
</html>