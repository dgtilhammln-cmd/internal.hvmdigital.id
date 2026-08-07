<?php
/* ==========================================================================
   TITAN ENGINE CONTROL - SOVEREIGN EDITION (V5.0)
   - Intelligent Operational Window Sync
   - Advanced Preset CRUD (Create, Read, Update, Delete)
   - Ultra-Premium Glassmorphism Design
   ========================================================================== */

if(!defined('DB_NAME')) { 
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php'; 
}

$status_report = "";

// 1. ENGINE CORE: UPDATE SETTINGS UTAMA
if (isset($_POST['update_engine_settings'])) {
    foreach ($_POST['cfg'] as $key => $val) {
        $key = mysqli_real_escape_string($conn, $key);
        $val = mysqli_real_escape_string($conn, $val);
        mysqli_query($conn, "INSERT INTO email_settings (key_name, key_value) VALUES ('$key', '$val') 
                             ON DUPLICATE KEY UPDATE key_value = '$val'");
    }
    $status_report = "settings_updated";
}

// 2. PRESET ENGINE: SAVE / UPDATE PRESET
if (isset($_POST['commit_preset'])) {
    $preset_name = mysqli_real_escape_string($conn, $_POST['preset_name']);
    $config_json = mysqli_real_escape_string($conn, json_encode($_POST['cfg']));
    $preset_id   = (int)$_POST['preset_id'];

    if ($preset_id > 0) {
        // UPDATE PRESET LAMA
        mysqli_query($conn, "UPDATE email_presets SET preset_name = '$preset_name', config_data = '$config_json' WHERE id = $preset_id");
        $status_report = "preset_updated";
    } else {
        // SIMPAN PRESET BARU
        mysqli_query($conn, "INSERT INTO email_presets (preset_name, config_data) VALUES ('$preset_name', '$config_json')");
        $status_report = "preset_saved";
    }
}

// 3. PRESET ENGINE: APPLY PRESET (LOAD KE LIVE SETTINGS)
if (isset($_GET['action']) && $_GET['action'] == 'apply_preset') {
    $pid = (int)$_GET['id'];
    $res_p = mysqli_query($conn, "SELECT config_data FROM email_presets WHERE id = $pid");
    if ($row_p = mysqli_fetch_assoc($res_p)) {
        $data = json_decode($row_p['config_data'], true);
        foreach ($data as $k => $v) {
            $k = mysqli_real_escape_string($conn, $k);
            $v = mysqli_real_escape_string($conn, $v);
            mysqli_query($conn, "UPDATE email_settings SET key_value = '$v' WHERE key_name = '$k'");
        }
        $status_report = "preset_applied";
    }
}

// 4. PRESET ENGINE: DELETE PRESET
if (isset($_GET['action']) && $_GET['action'] == 'delete_preset') {
    $pid = (int)$_GET['id'];
    mysqli_query($conn, "DELETE FROM email_presets WHERE id = $pid");
    $status_report = "preset_deleted";
}

// 5. DATA FETCHING
$res = mysqli_query($conn, "SELECT * FROM email_settings");
$cfg = [];
while($row = mysqli_fetch_assoc($res)) { $cfg[$row['key_name']] = $row['key_value']; }

$presets = mysqli_query($conn, "SELECT * FROM email_presets ORDER BY id DESC");

// Status Helpers
$isOnline = ($cfg['system_status'] ?? 'OFF') == 'ON';
?>

<!-- START TITAN ENGINE STYLES -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

    :root {
        --titan-green: #a1ff5a;
        --titan-cyan: #4efdc4;
        --titan-red: #ff4560;
        --titan-glass: rgba(15, 15, 15, 0.75);
        --titan-border: rgba(255, 255, 255, 0.08);
        --titan-grad: linear-gradient(135deg, #a1ff5a 0%, #4efdc4 100%);
    }

    .titan-settings-viewport {
        padding-left: 110px; /* SYNC DENGAN SIDEBAR */
        padding-right: 30px;
        padding-top: 10px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: #fff;
        animation: titanFadeIn 0.8s ease-out;
    }

    @keyframes titanFadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

    /* STATUS RIBBON */
    .status-ribbon {
        background: var(--titan-glass);
        backdrop-filter: blur(20px);
        border: 1px solid var(--titan-border);
        padding: 20px 35px;
        border-radius: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }

    .engine-indicator { display: flex; align-items: center; gap: 15px; }
    .pulse-dot {
        width: 12px; height: 12px; border-radius: 50%;
        background: <?= $isOnline ? 'var(--titan-green)' : 'var(--titan-red)' ?>;
        box-shadow: 0 0 15px <?= $isOnline ? 'var(--titan-green)' : 'var(--titan-red)' ?>;
        animation: pulseHeartbeat 1.5s infinite;
    }
    @keyframes pulseHeartbeat { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.3); opacity: 0.5; } 100% { transform: scale(1); opacity: 1; } }

    /* GRID SYSTEM */
    .settings-grid { display: grid; grid-template-columns: 1fr 450px; gap: 30px; }

    /* PREMIUM CARD */
    .titan-card {
        background: var(--titan-glass);
        backdrop-filter: blur(40px);
        border: 1px solid var(--titan-border);
        border-radius: 40px;
        padding: 45px;
        box-shadow: 0 30px 60px rgba(0,0,0,0.5);
        position: relative;
        overflow: hidden;
    }

    .titan-card::after {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(161, 255, 90, 0.03) 0%, transparent 70%);
        z-index: -1;
    }

    /* FORM ELEMENTS */
    .field-label { display: block; margin-bottom: 12px; font-weight: 800; color: #666; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; }
    
    .titan-input {
        width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--titan-border);
        padding: 18px 25px; border-radius: 20px; color: #fff; outline: none; margin-bottom: 25px;
        font-size: 0.95rem; font-family: inherit; transition: 0.4s;
    }
    .titan-input:focus { border-color: var(--titan-green); background: rgba(255,255,255,0.05); box-shadow: 0 0 20px rgba(161, 255, 90, 0.1); }

    /* PRESET CARDS */
    .preset-scroller { display: flex; flex-direction: column; gap: 15px; }
    .preset-item {
        background: rgba(255,255,255,0.02); border: 1px solid var(--titan-border);
        border-radius: 25px; padding: 25px; transition: 0.4s; position: relative;
    }
    .preset-item:hover { transform: scale(1.02); border-color: var(--titan-green); background: rgba(161, 255, 90, 0.02); }
    
    .preset-meta { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
    .preset-name { font-weight: 800; font-size: 1.1rem; color: #fff; display: block; }
    .preset-time { font-size: 0.65rem; color: #555; text-transform: uppercase; font-weight: 700; }

    /* BUTTON ENGINE */
    .btn-titan {
        padding: 16px 32px; border-radius: 50px; font-weight: 900; font-size: 0.8rem;
        text-transform: uppercase; letter-spacing: 1.5px; cursor: pointer; transition: 0.5s;
        display: flex; align-items: center; justify-content: center; gap: 12px;
    }
    .btn-titan-main { background: var(--titan-grad); color: #000; border: none; box-shadow: 0 15px 30px rgba(161, 255, 90, 0.2); }
    .btn-titan-main:hover { transform: translateY(-5px); box-shadow: 0 20px 45px rgba(78, 253, 196, 0.4); }

    .btn-titan-glass { background: rgba(255,255,255,0.05); color: #fff; border: 1px solid var(--titan-border); }
    .btn-titan-glass:hover { background: rgba(255,255,255,0.1); border-color: #fff; }

    /* MODAL DESIGN */
    .titan-modal {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.9); z-index: 100000; backdrop-filter: blur(20px);
        align-items: center; justify-content: center;
    }
    .modal-card {
        width: 90%; max-width: 500px; background: #050505; border: 1px solid var(--titan-green);
        border-radius: 40px; padding: 45px; box-shadow: 0 0 100px rgba(161, 255, 90, 0.2);
    }

    @media (max-width: 1200px) {
        .settings-grid { grid-template-columns: 1fr; }
        .titan-settings-viewport { padding-left: 20px; padding-bottom: 120px; }
    }
</style>

<div class="titan-settings-viewport">
    
    <div class="page-headline">
        <h1 style="font-weight: 800; font-size: 2.8rem; letter-spacing: -2px;">Engine Control</h1>
        <p style="color: #666;">Synchronize HVM Nebula's neural operational windows & behavior.</p>
    </div>

    <!-- NOTIFIKASI HANDLING -->
    <?php if($status_report): ?>
        <div class="status-ribbon" style="border-color: var(--titan-green); background: rgba(161, 255, 90, 0.05);">
            <div style="display:flex; align-items:center; gap:20px;">
                <i class="fas fa-check-double" style="color:var(--titan-green); font-size: 1.5rem;"></i>
                <b style="text-transform:uppercase; font-size:0.8rem; letter-spacing:1px;">System Notification: Action Synchronized Successfully.</b>
            </div>
            <button onclick="this.parentElement.remove()" style="background:none; border:none; color:#fff; cursor:pointer;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- STATUS ENGINE -->
    <div class="status-ribbon">
        <div class="engine-indicator">
            <div class="pulse-dot"></div>
            <div>
                <span style="display:block; color:#555; font-size:0.65rem; font-weight:800;">CURRENT ENGINE STATE</span>
                <b style="font-size:1.1rem; letter-spacing:1px; color:<?= $isOnline ? 'var(--titan-green)' : 'var(--titan-red)' ?>">
                    <?= $isOnline ? 'CORE_ACTIVE' : 'CORE_PAUSED' ?>
                </b>
            </div>
        </div>
        <div style="text-align:right;">
            <span style="display:block; color:#555; font-size:0.65rem; font-weight:800;">SERVER TIME (WIB)</span>
            <b style="font-size:1.1rem; letter-spacing:1px; color:#fff;"><?= date('H:i') ?></b>
        </div>
    </div>

    <div class="settings-grid">
        
        <!-- LEFT: MASTER CONFIGURATION -->
        <div class="titan-card">
            <h2 style="font-weight: 800; margin-bottom: 35px; letter-spacing: -1px;"><i class="fas fa-sliders-h" style="color:var(--titan-green)"></i> Master Configuration</h2>
            
            <form method="POST" id="mainSettingsForm">
                <label class="field-label">Global Operational Switch</label>
                <select name="cfg[system_status]" class="titan-input">
                    <option value="ON" <?= ($cfg['system_status'] ?? 'OFF') == 'ON' ? 'selected' : '' ?>>🟢 ONLINE: Execute Neural Missions</option>
                    <option value="OFF" <?= ($cfg['system_status'] ?? 'OFF') == 'OFF' ? 'selected' : '' ?>>🔴 OFFLINE: Terminate All Transmissions</option>
                </select>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                    <div>
                        <label class="field-label">Daily Mission Start</label>
                        <input type="time" name="cfg[work_hour_start]" class="titan-input" value="<?= $cfg['work_hour_start'] ?? '08:00' ?>">
                    </div>
                    <div>
                        <label class="field-label">Daily Mission End</label>
                        <input type="time" name="cfg[work_hour_end]" class="titan-input" value="<?= $cfg['work_hour_end'] ?? '17:00' ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                    <div>
                        <label class="field-label">Weekend Protocols</label>
                        <select name="cfg[send_on_weekend]" class="titan-input">
                            <option value="yes" <?= ($cfg['send_on_weekend'] ?? 'no') == 'yes' ? 'selected' : '' ?>>7-Day Unrestricted</option>
                            <option value="no" <?= ($cfg['send_on_weekend'] ?? 'no') == 'no' ? 'selected' : '' ?>>Weekdays (Mon-Fri) Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Drip Interval (Minutes)</label>
                        <input type="number" name="cfg[drip_interval]" class="titan-input" value="<?= $cfg['drip_interval'] ?? 5 ?>" min="1" max="1440">
                    </div>
                </div>

                <label class="field-label">Standard Email Subject</label>
                <input type="text" name="cfg[default_subject]" class="titan-input" value="<?= htmlspecialchars($cfg['default_subject'] ?? '') ?>" placeholder="e.g. Invitation: HVM Digital Partnership">

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-top: 15px;">
                    <button type="submit" name="update_engine_settings" class="btn-titan btn-titan-main">
                        <i class="fas fa-sync-alt"></i> Apply Configuration
                    </button>
                    <button type="button" onclick="openSaveModal()" class="btn-titan btn-titan-glass">
                        <i class="fas fa-star"></i> Save Preset
                    </button>
                </div>
            </form>
        </div>

        <!-- RIGHT: PRESET REPOSITORY -->
        <div class="titan-card" style="padding: 35px; background: rgba(255,255,255,0.01);">
            <h3 style="font-weight: 800; margin-bottom: 25px; font-size: 1.2rem; letter-spacing: -0.5px;"><i class="fas fa-archive" style="color:var(--titan-cyan)"></i> Favorites Repository</h3>
            
            <div class="preset-scroller">
                <?php if(mysqli_num_rows($presets) > 0): ?>
                    <?php while($p = mysqli_fetch_assoc($presets)): 
                        $p_data = json_decode($p['config_data'], true);    
                    ?>
                        <div class="preset-item">
                            <div class="preset-meta">
                                <div>
                                    <span class="preset-name"><?= $p['preset_name'] ?></span>
                                    <span class="preset-time">Configured: <?= date('d M Y', strtotime($p['created_at'])) ?></span>
                                </div>
                                <div style="background: rgba(161, 255, 90, 0.1); color: var(--titan-green); padding: 4px 10px; border-radius: 50px; font-size: 0.6rem; font-weight: 800;">
                                    <?= $p_data['drip_interval'] ?>M Interval
                                </div>
                            </div>
                            
                            <div style="margin-top: 15px; border-top: 1px solid var(--titan-border); padding-top: 15px; display: flex; gap: 10px;">
                                <a href="?page=settings&action=apply_preset&id=<?= $p['id'] ?>" class="btn-titan btn-titan-main" style="flex:1; height: 40px; font-size: 0.65rem; border-radius: 12px; box-shadow: none;">DEPLOY PRESET</a>
                                <button onclick='editPreset(<?= json_encode($p) ?>)' class="btn-titan btn-titan-glass" style="width: 40px; height: 40px; padding: 0; border-radius: 12px;"><i class="fas fa-edit"></i></button>
                                <a href="?page=settings&action=delete_preset&id=<?= $p['id'] ?>" onclick="return confirm('Erase this neural preset?')" class="btn-titan btn-titan-glass" style="width: 40px; height: 40px; padding: 0; border-radius: 12px; color: var(--titan-red); border-color: rgba(255, 69, 96, 0.2);"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; padding: 60px 0; opacity: 0.2;">
                        <i class="fas fa-folder-open fa-4x"></i>
                        <p style="margin-top: 15px; font-weight: 600;">No neural presets found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- MODAL: SAVE / EDIT PRESET -->
<div id="savePresetModal" class="titan-modal">
    <div class="modal-card">
        <h2 style="font-weight: 800; margin-bottom: 10px;" id="modalTitle">Save Preset</h2>
        <p style="color: #666; font-size: 0.85rem; margin-bottom: 25px;">Define a name for this operational configuration.</p>
        
        <form method="POST">
            <input type="hidden" name="preset_id" id="modalPresetId" value="0">
            <!-- Container for cloned settings -->
            <div id="settingsCloneContainer"></div>

            <label class="field-label">Preset Name</label>
            <input type="text" name="preset_name" id="modalPresetName" class="titan-input" placeholder="e.g. Weekend Rush / Night Mode" required>
            
            <button type="submit" name="commit_preset" class="btn-titan btn-titan-main" style="width:100%;">
                <i class="fas fa-check"></i> Commit to Repository
            </button>
            <button type="button" onclick="closeSaveModal()" class="btn-titan btn-titan-glass" style="width:100%; border:none; margin-top:10px;">Abort Action</button>
        </form>
    </div>
</div>

<!-- TITAN LOGIC ENGINE -->
<script>
    // 1. MODAL CONTROL
    function openSaveModal() {
        document.getElementById('modalTitle').innerText = "Save Current Config";
        document.getElementById('modalPresetId').value = "0";
        document.getElementById('modalPresetName').value = "";
        cloneCurrentSettings();
        document.getElementById('savePresetModal').style.display = 'flex';
    }

    function editPreset(data) {
        document.getElementById('modalTitle').innerText = "Rename & Edit Preset";
        document.getElementById('modalPresetId').value = data.id;
        document.getElementById('modalPresetName').value = data.preset_name;
        
        // Inject data ke form utama dulu agar bisa di-clone kembali
        const config = JSON.parse(data.config_data);
        for (let key in config) {
            const input = document.querySelector(`[name="cfg[${key}]"]`);
            if(input) input.value = config[key];
        }
        
        cloneCurrentSettings();
        document.getElementById('savePresetModal').style.display = 'flex';
    }

    function closeSaveModal() {
        document.getElementById('savePresetModal').style.display = 'none';
    }

    // 2. FORM CLONING LOGIC (Pastikan data tersimpan utuh ke preset)
    function cloneCurrentSettings() {
        const sourceForm = document.getElementById('mainSettingsForm');
        const container = document.getElementById('settingsCloneContainer');
        container.innerHTML = ""; // Clear
        
        const formData = new FormData(sourceForm);
        for (let [key, value] of formData.entries()) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = key;
            hiddenInput.value = value;
            container.appendChild(hiddenInput);
        }
    }

    // Modal click-outside logic
    window.onclick = function(event) {
        const modal = document.getElementById('savePresetModal');
        if (event.target == modal) closeSaveModal();
    }
</script>