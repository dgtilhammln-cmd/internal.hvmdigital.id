<?php
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
if(!isset($_SESSION['admin'])){ header("Location: /"); exit; }
if(($_SESSION['role'] ?? '') !== 'super_admin'){
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Akses Ditolak</title></head><body style='background:#050505;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;flex-direction:column;gap:15px;'>
    <div style='font-size:3rem;'>🔒</div>
    <h2>Akses Ditolak</h2>
    <p style='color:#555;'>Halaman ini hanya bisa diakses oleh Super Admin.</p>
    <a href='/dashboard/' style='color:#a1ff5a;'>← Kembali ke Dashboard</a>
    </body></html>";
    exit;
}

// Auto-create settings tables
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `ai_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `internal_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` MEDIUMTEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$saved_msg = '';
$active_tab = $_POST['active_tab'] ?? $_GET['tab'] ?? 'ai';
if(!in_array($active_tab, ['ai', 'internal'])) $active_tab = 'ai';

// Handle save AI settings
if(isset($_POST['save_ai_settings'])){
    $fields = [
        'ai_provider' => $_POST['ai_provider'] ?? 'openai',
        'ai_api_key'  => $_POST['ai_api_key'] ?? '',
        'ai_model'    => $_POST['ai_model'] ?? 'gpt-4o',
        'ai_name'     => $_POST['ai_name'] ?? 'Asisten HVM',
        'ai_persona'  => $_POST['ai_persona'] ?? '',
    ];

    // Handle icon upload
    if(isset($_FILES['ai_icon']) && $_FILES['ai_icon']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['ai_icon']['name'], PATHINFO_EXTENSION));
        if(in_array($ext, ['png','jpg','jpeg','gif','webp','svg'])) {
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/ai/';
            if(!file_exists($dir)) mkdir($dir, 0755, true);
            $fname = 'ai_icon.' . $ext;
            move_uploaded_file($_FILES['ai_icon']['tmp_name'], $dir . $fname);
            $fields['ai_icon'] = $fname;
        }
    }

    foreach($fields as $k => $v) {
        $k = mysqli_real_escape_string($conn, $k);
        $v = mysqli_real_escape_string($conn, trim($v));
        mysqli_query($conn, "INSERT INTO ai_settings (setting_key, setting_value) VALUES ('$k','$v') ON DUPLICATE KEY UPDATE setting_value='$v'");
    }
    $saved_msg = 'Pengaturan AI Asisten berhasil disimpan!';
    $active_tab = 'ai';
}

// Handle save Internal Data
if(isset($_POST['save_internal_data'])){
    $internal_inputs = $_POST['internal'] ?? [];
    if(is_array($internal_inputs)){
        foreach($internal_inputs as $k => $v) {
            $k = mysqli_real_escape_string($conn, trim($k));
            $v = mysqli_real_escape_string($conn, trim($v));
            mysqli_query($conn, "INSERT INTO internal_settings (setting_key, setting_value) VALUES ('$k','$v') ON DUPLICATE KEY UPDATE setting_value='$v'");
        }
    }
    $saved_msg = 'Data internal & arsip HVM berhasil disimpan!';
    $active_tab = 'internal';
}

// Load AI settings
$ai_settings = [];
$q_ai = mysqli_query($conn, "SELECT setting_key, setting_value FROM ai_settings");
if($q_ai) while($r = mysqli_fetch_assoc($q_ai)) $ai_settings[$r['setting_key']] = $r['setting_value'];

$provider = $ai_settings['ai_provider'] ?? 'openai';
$api_key  = $ai_settings['ai_api_key'] ?? '';
$model    = $ai_settings['ai_model'] ?? 'gpt-4o';
$ai_name  = $ai_settings['ai_name'] ?? 'Asisten HVM';
$persona  = $ai_settings['ai_persona'] ?? '';

// Load Internal settings
$internal_settings = [];
$q_int = mysqli_query($conn, "SELECT setting_key, setting_value FROM internal_settings");
if($q_int) while($r = mysqli_fetch_assoc($q_int)) $internal_settings[$r['setting_key']] = $r['setting_value'];

function getInternalVal($arr, $key, $default = '') {
    return htmlspecialchars($arr[$key] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HVM Internal</title>
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { 
            --bg: #050505; 
            --card: rgba(20,20,20,0.7); 
            --card-subtle: rgba(25,25,25,0.45);
            --border: rgba(255,255,255,0.07); 
            --border-hover: rgba(255,255,255,0.18); 
            --green: #a1ff5a; 
            --cyan: #4efdc4; 
            --text-main: #f0f0f0;
            --text-muted: #777;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif; }
        body { background:var(--bg); color:#fff; min-height:100vh; display:flex; }
        .main-content { flex:1; padding:40px; margin-left:260px; width:calc(100% - 260px); }
        @media(max-width:768px) { .main-content { margin-left:0; width:100%; padding:20px 20px 120px; } }

        /* Header */
        .page-header { margin-bottom:28px; }
        .page-header h1 { font-size:2.1rem; font-weight:800; background:linear-gradient(135deg,#ffffff 20%,#aaaaaa 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; display:flex; align-items:center; gap:12px; }
        .page-header p { color:var(--text-muted); font-size:0.9rem; margin-top:6px; line-height:1.5; }

        /* Modern Main Switcher Tabs */
        .tabs-nav {
            display:inline-flex;
            background:rgba(255,255,255,0.03);
            border:1px solid var(--border);
            border-radius:16px;
            padding:5px;
            gap:6px;
            margin-bottom:32px;
            backdrop-filter:blur(12px);
        }
        .main-tab-btn {
            display:inline-flex;
            align-items:center;
            gap:10px;
            padding:12px 26px;
            border-radius:12px;
            border:none;
            background:transparent;
            color:#888;
            font-size:0.92rem;
            font-weight:700;
            font-family:inherit;
            cursor:pointer;
            transition:all 0.25s ease;
        }
        .main-tab-btn i { font-size:1.05rem; }
        .main-tab-btn:hover { color:#fff; background:rgba(255,255,255,0.04); }
        .main-tab-btn.active {
            background:linear-gradient(135deg, rgba(161,255,90,0.15), rgba(78,253,196,0.08));
            border:1px solid rgba(161,255,90,0.3);
            color:var(--green);
            box-shadow:0 4px 20px rgba(161,255,90,0.1);
        }
        .main-tab-btn .badge-pill {
            font-size:0.68rem;
            padding:2px 8px;
            border-radius:20px;
            background:rgba(255,255,255,0.06);
            color:#999;
            font-weight:600;
        }
        .main-tab-btn.active .badge-pill {
            background:rgba(161,255,90,0.2);
            color:var(--green);
        }

        /* Tab Content Panes */
        .tab-pane { display:none; animation:fadeIn 0.3s ease; }
        .tab-pane.active { display:block; }
        @keyframes fadeIn {
            from { opacity:0; transform:translateY(8px); }
            to { opacity:1; transform:translateY(0); }
        }

        /* Grid & Cards */
        .settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        .settings-grid-3 { display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:24px; }
        @media(max-width:900px) { 
            .settings-grid, .settings-grid-3 { grid-template-columns:1fr; } 
        }
        
        .settings-card { 
            background:var(--card); 
            border:1px solid var(--border); 
            border-radius:20px; 
            padding:26px; 
            backdrop-filter:blur(20px); 
            position:relative;
            transition:border-color 0.25s ease;
        }
        .settings-card:hover { border-color:var(--border-hover); }
        .settings-card.full { grid-column:1/-1; }

        .card-header-bar {
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:20px;
            padding-bottom:14px;
            border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .card-title { 
            font-size:0.85rem; 
            font-weight:700; 
            text-transform:uppercase; 
            letter-spacing:1px; 
            color:#bbb; 
            display:flex; 
            align-items:center; 
            gap:10px; 
        }
        .card-title i { color:var(--cyan); font-size:1.05rem; }
        .card-badge {
            font-size:0.68rem;
            padding:3px 10px;
            border-radius:12px;
            background:rgba(255,255,255,0.05);
            color:#777;
            font-weight:600;
        }

        /* Form elements */
        .form-group { margin-bottom:16px; }
        .form-group:last-child { margin-bottom:0; }
        .form-group label { 
            display:flex; 
            align-items:center;
            justify-content:space-between;
            font-size:0.73rem; 
            font-weight:600; 
            color:#888; 
            text-transform:uppercase; 
            letter-spacing:0.5px; 
            margin-bottom:7px; 
        }
        .form-group label span.label-opt {
            font-size:0.65rem;
            color:#555;
            text-transform:none;
            font-weight:400;
        }

        .form-input {
            width:100%; 
            padding:12px 16px; 
            background:rgba(255,255,255,0.035); 
            border:1px solid rgba(255,255,255,0.08);
            border-radius:12px; 
            color:#fff; 
            font-family:inherit; 
            font-size:0.88rem; 
            outline:none; 
            transition:all 0.2s ease;
        }
        .form-input:focus { 
            border-color:rgba(161,255,90,0.5); 
            background:rgba(255,255,255,0.05); 
            box-shadow:0 0 15px rgba(161,255,90,0.1);
        }
        .form-input::placeholder { color:#444; }
        textarea.form-input { min-height:95px; resize:vertical; line-height:1.5; }

        /* Action Input Groups (Copy, Toggle, External Link) */
        .input-action-group {
            position:relative;
            display:flex;
            align-items:stretch;
        }
        .input-action-group .form-input {
            padding-right:82px;
        }
        .input-action-group.single-btn .form-input {
            padding-right:45px;
        }
        .input-actions {
            position:absolute;
            right:6px;
            top:50%;
            transform:translateY(-50%);
            display:flex;
            align-items:center;
            gap:4px;
        }
        .btn-input-icon {
            background:transparent;
            border:none;
            color:#666;
            width:32px;
            height:32px;
            border-radius:8px;
            cursor:pointer;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:0.85rem;
            transition:all 0.2s;
        }
        .btn-input-icon:hover {
            color:#fff;
            background:rgba(255,255,255,0.08);
        }
        .btn-input-icon.copied {
            color:var(--green) !important;
        }

        /* Buttons & Actions */
        .btn-save {
            background:linear-gradient(135deg,var(--green),var(--cyan)); 
            color:#000;
            border:none; 
            border-radius:12px; 
            padding:13px 32px;
            font-family:inherit; 
            font-size:0.92rem; 
            font-weight:800;
            cursor:pointer; 
            transition:all 0.25s ease; 
            display:inline-flex; 
            align-items:center; 
            gap:10px;
        }
        .btn-save:hover { 
            transform:translateY(-2px); 
            box-shadow:0 8px 25px rgba(161,255,90,0.3); 
        }

        .btn-secondary {
            background:rgba(255,255,255,0.06); 
            color:#fff;
            border:1px solid rgba(255,255,255,0.1); 
            border-radius:12px; 
            padding:11px 22px;
            font-family:inherit; 
            font-size:0.85rem; 
            font-weight:700;
            cursor:pointer; 
            transition:all 0.2s; 
            display:inline-flex; 
            align-items:center; 
            gap:8px;
        }
        .btn-secondary:hover { 
            background:rgba(255,255,255,0.12); 
            border-color:rgba(255,255,255,0.25);
        }

        .bottom-sticky-bar {
            margin-top:30px;
            padding:20px 24px;
            background:rgba(18,18,18,0.85);
            border:1px solid var(--border);
            border-radius:16px;
            backdrop-filter:blur(20px);
            display:flex;
            align-items:center;
            justify-content:space-between;
            flex-wrap:wrap;
            gap:15px;
        }

        /* Alerts */
        .alert-success {
            background:rgba(161,255,90,0.08); 
            border:1px solid rgba(161,255,90,0.3);
            color:var(--green); 
            padding:14px 20px; 
            border-radius:14px;
            margin-bottom:25px; 
            font-size:0.88rem; 
            font-weight:600;
            display:flex; 
            align-items:center; 
            gap:10px;
            animation:fadeIn 0.3s ease;
        }

        .info-banner {
            background:rgba(78,253,196,0.05);
            border:1px solid rgba(78,253,196,0.18);
            border-radius:16px;
            padding:16px 20px;
            margin-bottom:28px;
            display:flex;
            align-items:flex-start;
            gap:14px;
        }
        .info-banner i { color:var(--cyan); font-size:1.2rem; margin-top:3px; }
        .info-banner .info-text h4 { font-size:0.88rem; font-weight:700; color:#fff; margin-bottom:4px; }
        .info-banner .info-text p { font-size:0.78rem; color:#888; line-height:1.5; }

        /* Provider Tabs */
        .provider-tabs { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .provider-tab {
            padding:9px 18px; border-radius:10px; border:1px solid rgba(255,255,255,0.1);
            background:transparent; color:#777; cursor:pointer; font-size:0.82rem; font-weight:700;
            transition:0.2s; font-family:inherit;
        }
        .provider-tab.active { background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.35); color:#fff; }
        .model-hint { font-size:0.74rem; color:#555; margin-top:7px; line-height:1.5; }
        .model-hint code { color:var(--cyan); background:rgba(78,253,196,0.08); padding:2px 6px; border-radius:4px; }
        
        .test-result { margin-top:12px; font-size:0.82rem; padding:12px 14px; border-radius:10px; display:none; }
        .test-result.ok { background:rgba(161,255,90,0.08); color:var(--green); border:1px solid rgba(161,255,90,0.25); display:block; }
        .test-result.err { background:rgba(255,80,80,0.08); color:#ff6b6b; border:1px solid rgba(255,80,80,0.25); display:block; }
        .status-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
        .dot-ok { background:var(--green); box-shadow:0 0 6px var(--green); }
        .dot-err { background:#ff6b6b; box-shadow:0 0 6px #ff6b6b; }

        /* Social Item Group */
        .sub-block {
            background:rgba(255,255,255,0.02);
            border:1px solid rgba(255,255,255,0.05);
            border-radius:14px;
            padding:18px;
            margin-bottom:18px;
        }
        .sub-block:last-child { margin-bottom:0; }
        .sub-block-title {
            font-size:0.78rem;
            font-weight:700;
            color:#bbb;
            margin-bottom:14px;
            display:flex;
            align-items:center;
            justify-content:space-between;
        }
        .sub-block-title span { display:flex; align-items:center; gap:8px; }
    </style>
</head>
<body>
<?php include '../sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-cog" style="color:var(--green);"></i> Settings</h1>
        <p>Kelola konfigurasi sistem kecerdasan buatan (AI) serta repositori form data & akses internal HVM Digital.</p>
    </div>

    <!-- Main Navigation Switcher (AI vs Data Internal) -->
    <div class="tabs-nav">
        <button type="button" class="main-tab-btn <?= $active_tab==='ai'?'active':'' ?>" onclick="switchMainTab('ai')">
            <i class="fas fa-robot"></i> Asisten AI
            <span class="badge-pill">Konfigurasi</span>
        </button>
        <button type="button" class="main-tab-btn <?= $active_tab==='internal'?'active':'' ?>" onclick="switchMainTab('internal')">
            <i class="fas fa-database"></i> Data Internal
            <span class="badge-pill">Vault & Form</span>
        </button>
    </div>

    <?php if(!empty($saved_msg)): ?>
    <div class="alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($saved_msg) ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         TAB 1: ASISTEN AI
         ============================================================ -->
    <div id="tab-ai" class="tab-pane <?= $active_tab==='ai'?'active':'' ?>">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="active_tab" value="ai">
            
            <div class="settings-grid">
                <!-- Provider Selection -->
                <div class="settings-card">
                    <div class="card-header-bar">
                        <div class="card-title"><i class="fas fa-brain"></i> Provider AI</div>
                        <span class="card-badge">Engine</span>
                    </div>

                    <div class="form-group">
                        <label>Pilih Provider AI</label>
                        <div class="provider-tabs">
                            <button type="button" class="provider-tab <?= $provider==='openai'?'active':'' ?>" onclick="setProvider('openai',this)"><i class="fas fa-brain"></i> OpenAI</button>
                            <button type="button" class="provider-tab <?= $provider==='groq'?'active':'' ?>" onclick="setProvider('groq',this)"><i class="fas fa-bolt"></i> Groq</button>
                            <button type="button" class="provider-tab <?= $provider==='gemini'?'active':'' ?>" onclick="setProvider('gemini',this)"><i class="fas fa-gem"></i> Google Gemini</button>
                            <button type="button" class="provider-tab <?= $provider==='anthropic'?'active':'' ?>" onclick="setProvider('anthropic',this)"><i class="fas fa-feather"></i> Anthropic</button>
                        </div>
                        <input type="hidden" name="ai_provider" id="ai_provider" value="<?= htmlspecialchars($provider) ?>">
                    </div>

                    <div class="form-group">
                        <label>Model LLM</label>
                        <input type="text" name="ai_model" id="ai_model" class="form-input" value="<?= htmlspecialchars($model) ?>" placeholder="cth: llama-3.3-70b-versatile">
                        <div class="model-hint" id="model_hint">
                            <?php
                            $hints = [
                                'openai'    => 'OpenAI: <code>gpt-4o</code>, <code>gpt-4o-mini</code>, <code>gpt-3.5-turbo</code>',
                                'groq'      => 'Groq: <code>llama-3.3-70b-versatile</code>, <code>llama-3.1-8b-instant</code>, <code>mixtral-8x7b-32768</code>, <code>gemma2-9b-it</code>',
                                'gemini'    => 'Gemini: <code>gemini-1.5-pro</code>, <code>gemini-1.5-flash</code>, <code>gemini-2.0-flash</code>',
                                'anthropic' => 'Anthropic: <code>claude-3-5-sonnet-20241022</code>, <code>claude-3-haiku-20240307</code>',
                            ];
                            echo $hints[$provider] ?? $hints['openai'];
                            ?>
                        </div>
                    </div>
                </div>

                <!-- API Key -->
                <div class="settings-card">
                    <div class="card-header-bar">
                        <div class="card-title"><i class="fas fa-key"></i> Konfigurasi API Key</div>
                        <span class="card-badge">Kredensial</span>
                    </div>

                    <div class="form-group">
                        <label>API Key Rahasia</label>
                        <div class="input-action-group single-btn">
                            <input type="password" name="ai_api_key" id="ai_api_key" class="form-input"
                                   value="<?= htmlspecialchars($api_key) ?>"
                                   placeholder="sk-... / AIzaSy... / sk-ant-...">
                            <div class="input-actions">
                                <button type="button" class="btn-input-icon" onclick="togglePassField('ai_api_key', this)" title="Lihat/Sembunyikan">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn-secondary" style="margin-top:6px;" onclick="testConnection()">
                        <i class="fas fa-plug"></i> Test Koneksi API
                    </button>
                    <div id="testResult" class="test-result"></div>
                </div>

                <!-- Icon Upload -->
                <div class="settings-card">
                    <div class="card-header-bar">
                        <div class="card-title"><i class="fas fa-image"></i> Ikon Tombol AI</div>
                        <span class="card-badge">Visual</span>
                    </div>
                    <?php
                    $ai_icon = $ai_settings['ai_icon'] ?? '';
                    $icon_path = $ai_icon ? '/uploads/ai/' . $ai_icon : '';
                    ?>
                    <?php if($icon_path): ?>
                    <div style="margin-bottom:18px; text-align:center;">
                        <div style="width:76px;height:76px;border-radius:50%;overflow:hidden;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;">
                            <img src="<?= htmlspecialchars($icon_path) ?>" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                        <div style="font-size:0.72rem;color:#666;">Ikon Asisten Aktif</div>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Upload Ikon Baru <span class="label-opt">(PNG/JPG/SVG, max 2MB)</span></label>
                        <div style="border:2px dashed rgba(255,255,255,0.1);border-radius:14px;padding:22px;text-align:center;cursor:pointer;transition:0.2s;" onclick="document.getElementById('ai_icon_input').click()" id="iconDropZone">
                            <i class="fas fa-cloud-upload-alt" style="font-size:1.8rem;color:#444;margin-bottom:8px;display:block;"></i>
                            <div id="iconFileName" style="font-size:0.8rem;color:#777;">Klik atau seret file gambar ke sini</div>
                        </div>
                        <input type="file" name="ai_icon" id="ai_icon_input" accept="image/*" style="display:none;" onchange="previewIcon(this)">
                        <div style="margin-top:12px;text-align:center;display:none;" id="iconPreviewWrap">
                            <img id="iconPreviewImg" src="" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:2px solid rgba(161,255,90,0.3);">
                        </div>
                    </div>
                </div>

                <!-- Persona -->
                <div class="settings-card">
                    <div class="card-header-bar">
                        <div class="card-title"><i class="fas fa-user-astronaut"></i> Identitas & Persona AI</div>
                        <span class="card-badge">Prompting</span>
                    </div>
                    <div class="form-group">
                        <label>Nama Asisten</label>
                        <input type="text" name="ai_name" class="form-input" value="<?= htmlspecialchars($ai_name) ?>" placeholder="cth: Nova, Aria, Asisten HVM">
                    </div>
                    <div class="form-group">
                        <label>Instruksi Sistem & Persona <span class="label-opt">(Prompt Tambahan)</span></label>
                        <textarea name="ai_persona" class="form-input" placeholder="Tuliskan panduan persona atau gaya bicara AI..."><?= htmlspecialchars($persona) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="bottom-sticky-bar">
                <span style="font-size:0.82rem;color:#777;"><i class="fas fa-info-circle"></i> Simpan konfigurasi agar AI floating assistant dapat terhubung langsung.</span>
                <button type="submit" name="save_ai_settings" class="btn-save">
                    <i class="fas fa-save"></i> Simpan Pengaturan AI
                </button>
            </div>
        </form>
    </div>

    <!-- ============================================================
         TAB 2: DATA INTERNAL (VAULT & FORM FORM OPERASIONAL)
         ============================================================ -->
    <div id="tab-internal" class="tab-pane <?= $active_tab==='internal'?'active':'' ?>">
        
        <div class="info-banner">
            <i class="fas fa-shield-alt"></i>
            <div class="info-text">
                <h4>Master Repository & Vault Data Internal HVM</h4>
                <p>Formulir ini berfungsi menyimpan link berkas penting, deck presentasi, akun sosmed resmi, serta kredensial operasional HVM Digital. Seluruh data hanya dapat diakses dan diedit oleh Super Admin.</p>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="active_tab" value="internal">

            <div class="settings-grid">

                <!-- 1. DECK & PRESENTASI -->
                <div class="settings-card">
                    <div class="card-header-bar">
                        <div class="card-title"><i class="fas fa-file-powerpoint"></i> Deck Proposal & Progress</div>
                        <span class="card-badge">Presentasi</span>
                    </div>

                    <!-- Deck Proposal -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fas fa-briefcase" style="color:var(--green)"></i> Deck Proposal Klien</span>
                        </div>
                        <div class="form-group">
                            <label>Tautan Link Deck (Google Slides / Canva / PDF)</label>
                            <div class="input-action-group">
                                <input type="url" name="internal[deck_proposal_url]" id="deck_proposal_url" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'deck_proposal_url') ?>" 
                                       placeholder="https://docs.google.com/presentation/d/...">
                                <div class="input-actions">
                                    <button type="button" class="btn-input-icon" onclick="copyInputVal('deck_proposal_url', this)" title="Salin Tautan">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" class="btn-input-icon" onclick="openInputUrl('deck_proposal_url')" title="Buka Tautan">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Catatan / Versi Proposal</label>
                            <input type="text" name="internal[deck_proposal_note]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'deck_proposal_note') ?>" 
                                   placeholder="cth: Versi 2026 - Template All Service SEO & Ads">
                        </div>
                    </div>

                    <!-- Deck SEO Progress -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fas fa-chart-line" style="color:var(--cyan)"></i> Deck SEO Progress & Reporting</span>
                        </div>
                        <div class="form-group">
                            <label>Tautan Link Deck SEO Progress / Looker Studio</label>
                            <div class="input-action-group">
                                <input type="url" name="internal[deck_seo_url]" id="deck_seo_url" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'deck_seo_url') ?>" 
                                       placeholder="https://lookerstudio.google.com/... atau Drive Link">
                                <div class="input-actions">
                                    <button type="button" class="btn-input-icon" onclick="copyInputVal('deck_seo_url', this)" title="Salin Tautan">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" class="btn-input-icon" onclick="openInputUrl('deck_seo_url')" title="Buka Tautan">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Catatan / Periode Template</label>
                            <input type="text" name="internal[deck_seo_note]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'deck_seo_note') ?>" 
                                   placeholder="cth: Template bulanan progres keyword & backlink">
                        </div>
                    </div>

                    <!-- Company Profile & Rate Card (Saran Tambahan) -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fas fa-id-card-alt" style="color:#ffc107"></i> Company Profile & Rate Card</span>
                        </div>
                        <div class="form-group">
                            <label>Link Deck Company Profile HVM</label>
                            <div class="input-action-group">
                                <input type="url" name="internal[deck_compro_url]" id="deck_compro_url" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'deck_compro_url') ?>" 
                                       placeholder="https://drive.google.com/file/d/...">
                                <div class="input-actions">
                                    <button type="button" class="btn-input-icon" onclick="copyInputVal('deck_compro_url', this)" title="Salin Tautan">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" class="btn-input-icon" onclick="openInputUrl('deck_compro_url')" title="Buka Tautan">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Link Rate Card / Pricelist Layanan</label>
                            <div class="input-action-group">
                                <input type="url" name="internal[rate_card_url]" id="rate_card_url" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'rate_card_url') ?>" 
                                       placeholder="https://drive.google.com/file/d/...">
                                <div class="input-actions">
                                    <button type="button" class="btn-input-icon" onclick="copyInputVal('rate_card_url', this)" title="Salin Tautan">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" class="btn-input-icon" onclick="openInputUrl('rate_card_url')" title="Buka Tautan">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- 2. AKSES LOGIN MEDIA SOSIAL HVM DIGITAL -->
                <div class="settings-card">
                    <div class="card-header-bar">
                        <div class="card-title"><i class="fas fa-hashtag"></i> Akses Akun Media Sosial Resmi</div>
                        <span class="card-badge">Sosmed</span>
                    </div>

                    <!-- Instagram -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fab fa-instagram" style="color:#e1306c"></i> Instagram Official</span>
                        </div>
                        <div class="settings-grid" style="gap:12px; margin-bottom:12px;">
                            <div class="form-group">
                                <label>Username / Handle</label>
                                <div class="input-action-group single-btn">
                                    <input type="text" name="internal[sosmed_ig_user]" id="sosmed_ig_user" class="form-input" 
                                           value="<?= getInternalVal($internal_settings, 'sosmed_ig_user', '@hvmdigital') ?>" 
                                           placeholder="@hvmdigital">
                                    <div class="input-actions">
                                        <button type="button" class="btn-input-icon" onclick="copyInputVal('sosmed_ig_user', this)" title="Salin Username">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Password / Sandi</label>
                                <div class="input-action-group">
                                    <input type="password" name="internal[sosmed_ig_pass]" id="sosmed_ig_pass" class="form-input" 
                                           value="<?= getInternalVal($internal_settings, 'sosmed_ig_pass') ?>" 
                                           placeholder="••••••••">
                                    <div class="input-actions">
                                        <button type="button" class="btn-input-icon" onclick="togglePassField('sosmed_ig_pass', this)" title="Lihat/Sembunyikan">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn-input-icon" onclick="copyInputVal('sosmed_ig_pass', this)" title="Salin Password">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Link Profil / 2FA Note</label>
                            <input type="text" name="internal[sosmed_ig_note]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'sosmed_ig_note') ?>" 
                                   placeholder="https://instagram.com/hvmdigital | 2FA ke WhatsApp Admin">
                        </div>
                    </div>

                    <!-- TikTok -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fab fa-tiktok" style="color:#00f2fe"></i> TikTok Official</span>
                        </div>
                        <div class="settings-grid" style="gap:12px; margin-bottom:12px;">
                            <div class="form-group">
                                <label>Username / Email</label>
                                <div class="input-action-group single-btn">
                                    <input type="text" name="internal[sosmed_tiktok_user]" id="sosmed_tiktok_user" class="form-input" 
                                           value="<?= getInternalVal($internal_settings, 'sosmed_tiktok_user') ?>" 
                                           placeholder="@hvmdigital">
                                    <div class="input-actions">
                                        <button type="button" class="btn-input-icon" onclick="copyInputVal('sosmed_tiktok_user', this)" title="Salin Username">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Password / Sandi</label>
                                <div class="input-action-group">
                                    <input type="password" name="internal[sosmed_tiktok_pass]" id="sosmed_tiktok_pass" class="form-input" 
                                           value="<?= getInternalVal($internal_settings, 'sosmed_tiktok_pass') ?>" 
                                           placeholder="••••••••">
                                    <div class="input-actions">
                                        <button type="button" class="btn-input-icon" onclick="togglePassField('sosmed_tiktok_pass', this)" title="Lihat/Sembunyikan">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn-input-icon" onclick="copyInputVal('sosmed_tiktok_pass', this)" title="Salin Password">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Link Profil / Catatan Akses</label>
                            <input type="text" name="internal[sosmed_tiktok_note]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'sosmed_tiktok_note') ?>" 
                                   placeholder="https://tiktok.com/@hvmdigital">
                        </div>
                    </div>

                    <!-- LinkedIn -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fab fa-linkedin" style="color:#0a66c2"></i> LinkedIn Company Page</span>
                        </div>
                        <div class="settings-grid" style="gap:12px; margin-bottom:12px;">
                            <div class="form-group">
                                <label>Email / Super Admin</label>
                                <div class="input-action-group single-btn">
                                    <input type="text" name="internal[sosmed_linkedin_user]" id="sosmed_linkedin_user" class="form-input" 
                                           value="<?= getInternalVal($internal_settings, 'sosmed_linkedin_user') ?>" 
                                           placeholder="admin@hvmdigital.id">
                                    <div class="input-actions">
                                        <button type="button" class="btn-input-icon" onclick="copyInputVal('sosmed_linkedin_user', this)" title="Salin Email">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Password / Akses</label>
                                <div class="input-action-group">
                                    <input type="password" name="internal[sosmed_linkedin_pass]" id="sosmed_linkedin_pass" class="form-input" 
                                           value="<?= getInternalVal($internal_settings, 'sosmed_linkedin_pass') ?>" 
                                           placeholder="••••••••">
                                    <div class="input-actions">
                                        <button type="button" class="btn-input-icon" onclick="togglePassField('sosmed_linkedin_pass', this)" title="Lihat/Sembunyikan">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn-input-icon" onclick="copyInputVal('sosmed_linkedin_pass', this)" title="Salin Password">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>URL Halaman LinkedIn</label>
                            <input type="text" name="internal[sosmed_linkedin_url]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'sosmed_linkedin_url') ?>" 
                                   placeholder="https://linkedin.com/company/hvm-digital">
                        </div>
                    </div>

                    <!-- Meta Business Suite & YouTube -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fab fa-facebook-square" style="color:#1877f2"></i> Meta Business Suite & YouTube</span>
                        </div>
                        <div class="form-group">
                            <label>Meta BM / Ads Manager Access</label>
                            <input type="text" name="internal[sosmed_meta_note]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'sosmed_meta_note') ?>" 
                                   placeholder="Akun BM: HVM Digital | Akses: admin@hvmdigital.id">
                        </div>
                        <div class="form-group">
                            <label>YouTube Channel & Google Brand Account</label>
                            <input type="text" name="internal[sosmed_youtube_note]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'sosmed_youtube_note') ?>" 
                                   placeholder="https://youtube.com/@hvmdigital | Akun Google Tim">
                        </div>
                    </div>

                </div>

                <!-- 3. SARAN TAMBAHAN: SERVER, HOSTING & DOMAIN -->
                <div class="settings-card">
                    <div class="card-header-bar">
                        <div class="card-title"><i class="fas fa-server"></i> Akses Hosting & Domain HVM</div>
                        <span class="card-badge">Infrastruktur</span>
                    </div>

                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fas fa-hdd" style="color:var(--green)"></i> Hosting / CPanel / VPS</span>
                        </div>
                        <div class="form-group">
                            <label>URL Panel Login</label>
                            <div class="input-action-group">
                                <input type="url" name="internal[server_cpanel_url]" id="server_cpanel_url" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'server_cpanel_url') ?>" 
                                       placeholder="https://cpanel.hvmdigital.id atau port 2083">
                                <div class="input-actions">
                                    <button type="button" class="btn-input-icon" onclick="openInputUrl('server_cpanel_url')" title="Buka URL">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="settings-grid" style="gap:12px;">
                            <div class="form-group">
                                <label>Username Server</label>
                                <input type="text" name="internal[server_cpanel_user]" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'server_cpanel_user') ?>" 
                                       placeholder="cth: u664715641">
                            </div>
                            <div class="form-group">
                                <label>Password / Kunci</label>
                                <div class="input-action-group">
                                    <input type="password" name="internal[server_cpanel_pass]" id="server_cpanel_pass" class="form-input" 
                                           value="<?= getInternalVal($internal_settings, 'server_cpanel_pass') ?>" 
                                           placeholder="••••••••">
                                    <div class="input-actions">
                                        <button type="button" class="btn-input-icon" onclick="togglePassField('server_cpanel_pass', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn-input-icon" onclick="copyInputVal('server_cpanel_pass', this)">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fas fa-globe" style="color:#f38020"></i> DNS & Domain Management (Cloudflare / Registrar)</span>
                        </div>
                        <div class="form-group">
                            <label>Provider & Login URL</label>
                            <input type="text" name="internal[domain_provider_note]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'domain_provider_note') ?>" 
                                   placeholder="Cloudflare / Niagahoster / Domainesia - email@hvmdigital.id">
                        </div>
                        <div class="form-group">
                            <label>Catatan DNS / API Token / Nameservers</label>
                            <input type="text" name="internal[domain_dns_note]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'domain_dns_note') ?>" 
                                   placeholder="Nameservers: ns1.cloudflare.com, ns2.cloudflare.com">
                        </div>
                    </div>
                </div>

                <!-- 4. SARAN TAMBAHAN: TOOLS SEO & ASSET TIM -->
                <div class="settings-card">
                    <div class="card-header-bar">
                        <div class="card-title"><i class="fas fa-tools"></i> Akses Tools & Shared Drive Tim</div>
                        <span class="card-badge">Operasional</span>
                    </div>

                    <!-- SEO Subscriptions -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fas fa-search" style="color:var(--cyan)"></i> Tools SEO (Ahrefs / SEMrush / Moz)</span>
                        </div>
                        <div class="form-group">
                            <label>Akun / Email Login SEO Tools</label>
                            <input type="text" name="internal[tools_seo_user]" class="form-input" 
                                   value="<?= getInternalVal($internal_settings, 'tools_seo_user') ?>" 
                                   placeholder="tools@hvmdigital.id (Ahrefs Agency Seat)">
                        </div>
                        <div class="form-group">
                            <label>Password / Token Akses</label>
                            <div class="input-action-group">
                                <input type="password" name="internal[tools_seo_pass]" id="tools_seo_pass" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'tools_seo_pass') ?>" 
                                       placeholder="••••••••">
                                <div class="input-actions">
                                    <button type="button" class="btn-input-icon" onclick="togglePassField('tools_seo_pass', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn-input-icon" onclick="copyInputVal('tools_seo_pass', this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cloud Drive & Brand Assets -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fab fa-google-drive" style="color:#0f9d58"></i> Google Shared Drive & Brand Assets</span>
                        </div>
                        <div class="form-group">
                            <label>Master Shared Drive Tim HVM</label>
                            <div class="input-action-group">
                                <input type="url" name="internal[drive_master_url]" id="drive_master_url" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'drive_master_url') ?>" 
                                       placeholder="https://drive.google.com/drive/folders/...">
                                <div class="input-actions">
                                    <button type="button" class="btn-input-icon" onclick="openInputUrl('drive_master_url')">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Link Folder Logo & Brand Kit Assets</label>
                            <div class="input-action-group">
                                <input type="url" name="internal[drive_brand_url]" id="drive_brand_url" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'drive_brand_url') ?>" 
                                       placeholder="https://drive.google.com/drive/folders/... (Logo SVG, Typography, Guidelines)">
                                <div class="input-actions">
                                    <button type="button" class="btn-input-icon" onclick="openInputUrl('drive_brand_url')">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kontak CS & Webmail -->
                    <div class="sub-block">
                        <div class="sub-block-title">
                            <span><i class="fas fa-headset" style="color:#a1ff5a"></i> CS WhatsApp & Email Bisnis</span>
                        </div>
                        <div class="settings-grid" style="gap:12px;">
                            <div class="form-group">
                                <label>Nomor WhatsApp CS Official</label>
                                <input type="text" name="internal[contact_wa_cs]" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'contact_wa_cs') ?>" 
                                       placeholder="+62 8xx-xxxx-xxxx (PIC: Admin CS)">
                            </div>
                            <div class="form-group">
                                <label>Webmail / Email Resmi</label>
                                <input type="text" name="internal[contact_email_official]" class="form-input" 
                                       value="<?= getInternalVal($internal_settings, 'contact_email_official') ?>" 
                                       placeholder="contact@hvmdigital.id">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- 5. CATATAN OPERASIONAL & KREDENSIAL LAINNYA (Full Width) -->
                <div class="settings-card full">
                    <div class="card-header-bar">
                        <div class="card-title"><i class="fas fa-sticky-note"></i> Catatan Khusus & Akses Operasional Bebas</div>
                        <span class="card-badge">Scratchpad Vault</span>
                    </div>
                    <div class="form-group">
                        <label>Catatan Rahasia Tambahan <span class="label-opt">(Akses Wi-Fi kantor, panduan darurat, atau catatan penting lainnya)</span></label>
                        <textarea name="internal[custom_internal_notes]" class="form-input" style="min-height:120px;" placeholder="Simpan catatan bebas untuk keperluan operasional internal tim..."><?= getInternalVal($internal_settings, 'custom_internal_notes') ?></textarea>
                    </div>
                </div>

            </div>

            <!-- Sticky Save Bar for Data Internal -->
            <div class="bottom-sticky-bar">
                <span style="font-size:0.82rem;color:#777;"><i class="fas fa-lock"></i> Seluruh data internal disimpan secara terenkripsi di server internal HVM Digital.</span>
                <button type="submit" name="save_internal_data" class="btn-save">
                    <i class="fas fa-save"></i> Simpan Semua Data Internal
                </button>
            </div>
        </form>

    </div>
</main>

<script>
// Main Tab Switching
function switchMainTab(tab) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.main-tab-btn').forEach(el => el.classList.remove('active'));

    const targetPane = document.getElementById('tab-' + tab);
    if(targetPane) targetPane.classList.add('active');

    const btns = document.querySelectorAll('.main-tab-btn');
    if(tab === 'ai' && btns[0]) btns[0].classList.add('active');
    if(tab === 'internal' && btns[1]) btns[1].classList.add('active');

    // Update URL hash without scroll jump
    history.replaceState(null, null, '#' + tab);
}

// Auto open tab from URL hash if provided
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    if(hash === 'ai' || hash === 'internal') {
        switchMainTab(hash);
    }
});

// AI Provider hints
const modelHints = {
    openai:    'OpenAI: <code>gpt-4o</code>, <code>gpt-4o-mini</code>, <code>gpt-3.5-turbo</code>',
    groq:      'Groq: <code>llama-3.3-70b-versatile</code>, <code>llama-3.1-8b-instant</code>, <code>mixtral-8x7b-32768</code>, <code>gemma2-9b-it</code>',
    gemini:    'Google Gemini: <code>gemini-1.5-pro</code>, <code>gemini-1.5-flash</code>, <code>gemini-2.0-flash</code>',
    anthropic: 'Anthropic: <code>claude-3-5-sonnet-20241022</code>, <code>claude-3-haiku-20240307</code>'
};
const modelDefaults = { openai:'gpt-4o', groq:'llama-3.3-70b-versatile', gemini:'gemini-2.0-flash', anthropic:'claude-3-5-sonnet-20241022' };

function setProvider(p, el) {
    document.getElementById('ai_provider').value = p;
    document.getElementById('model_hint').innerHTML = modelHints[p] || '';
    document.getElementById('ai_model').value = modelDefaults[p] || '';
    document.querySelectorAll('.provider-tab').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
}

// Toggle password visibility for any field
function togglePassField(fieldId, btn) {
    const inp = document.getElementById(fieldId);
    if(!inp) return;
    const icon = btn.querySelector('i');
    if(inp.type === 'password') {
        inp.type = 'text';
        if(icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
    } else {
        inp.type = 'password';
        if(icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
    }
}

// Quick copy field value to clipboard
function copyInputVal(fieldId, btn) {
    const inp = document.getElementById(fieldId);
    if(!inp || !inp.value) {
        alert('Kolom masih kosong untuk disalin.');
        return;
    }
    navigator.clipboard.writeText(inp.value).then(() => {
        btn.classList.add('copied');
        const icon = btn.querySelector('i');
        const origClass = icon ? icon.className : '';
        if(icon) icon.className = 'fas fa-check';
        setTimeout(() => {
            btn.classList.remove('copied');
            if(icon) icon.className = origClass;
        }, 1800);
    }).catch(err => {
        // Fallback for non-https/local
        inp.select();
        document.execCommand('copy');
        btn.classList.add('copied');
        setTimeout(() => btn.classList.remove('copied'), 1800);
    });
}

// Open URL directly in new tab
function openInputUrl(fieldId) {
    const inp = document.getElementById(fieldId);
    if(!inp || !inp.value.trim()) {
        alert('Tautan URL belum diisi.');
        return;
    }
    let url = inp.value.trim();
    if(!url.startsWith('http://') && !url.startsWith('https://')) {
        url = 'https://' + url;
    }
    window.open(url, '_blank', 'noopener,noreferrer');
}

// AI Test connection
async function testConnection() {
    const key  = document.getElementById('ai_api_key').value.trim();
    const prov = document.getElementById('ai_provider').value;
    const mod  = document.getElementById('ai_model').value.trim();
    const res  = document.getElementById('testResult');
    res.className = 'test-result';
    res.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghubungi provider...';
    res.style.display = 'block';

    try {
        const fd = new FormData();
        fd.append('action', 'test'); fd.append('key', key); fd.append('provider', prov); fd.append('model', mod);
        const r = await fetch('/dashboard/ai/handler.php', { method:'POST', body:fd });
        const d = await r.json();
        if(d.ok) {
            res.className = 'test-result ok';
            res.innerHTML = '<span class="status-dot dot-ok"></span> Koneksi berhasil! Model aktif: ' + (d.model||mod);
        } else {
            res.className = 'test-result err';
            res.innerHTML = '<span class="status-dot dot-err"></span> Gagal terhubung: ' + (d.error || 'Unknown error');
        }
    } catch(e) {
        res.className = 'test-result err';
        res.innerHTML = '<span class="status-dot dot-err"></span> Gagal terhubung ke handler backend.';
    }
}

// Preview uploaded icon
function previewIcon(input) {
    if(!input.files[0]) return;
    const file = input.files[0];
    document.getElementById('iconFileName').textContent = file.name;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('iconPreviewImg').src = e.target.result;
        document.getElementById('iconPreviewWrap').style.display = 'block';
        document.getElementById('iconDropZone').style.borderColor = 'rgba(161,255,90,0.4)';
    };
    reader.readAsDataURL(file);
}
</script>
</body>
</html>
