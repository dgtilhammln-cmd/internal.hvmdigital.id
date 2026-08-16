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

// Auto-create settings table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `ai_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Handle save
$saved = false;
if(isset($_POST['save_settings'])){
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
    $saved = true;
}

// Load current settings
$settings = [];
$q = mysqli_query($conn, "SELECT setting_key, setting_value FROM ai_settings");
if($q) while($r = mysqli_fetch_assoc($q)) $settings[$r['setting_key']] = $r['setting_value'];

$provider = $settings['ai_provider'] ?? 'openai';
$api_key  = $settings['ai_api_key'] ?? '';
$model    = $settings['ai_model'] ?? 'gpt-4o';
$ai_name  = $settings['ai_name'] ?? 'Asisten HVM';
$persona  = $settings['ai_persona'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings AI - HVM Internal</title>
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --bg:#050505; --card:rgba(20,20,20,0.7); --border:rgba(255,255,255,0.07); --green:#a1ff5a; --cyan:#4efdc4; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif; }
        body { background:var(--bg); color:#fff; min-height:100vh; display:flex; }
        .main-content { flex:1; padding:40px; margin-left:260px; width:calc(100% - 260px); }
        @media(max-width:768px) { .main-content { margin-left:0; width:100%; padding:20px 20px 120px; } }
        .page-header { margin-bottom:35px; }
        .page-header h1 { font-size:2rem; font-weight:800; background:linear-gradient(135deg,#fff,#aaa); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .page-header p { color:#555; font-size:0.9rem; margin-top:6px; }
        .settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        @media(max-width:768px) { .settings-grid { grid-template-columns:1fr; } }
        .settings-card { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:28px; backdrop-filter:blur(20px); }
        .settings-card.full { grid-column:1/-1; }
        .card-title { font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#888; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-size:0.72rem; font-weight:600; color:#555; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
        .form-input {
            width:100%; padding:12px 16px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);
            border-radius:12px; color:#fff; font-family:inherit; font-size:0.9rem; outline:none; transition:0.2s;
        }
        .form-input:focus { border-color:rgba(255,255,255,0.3); background:rgba(255,255,255,0.04); }
        .form-input option { background:#111; color:#fff; }
        textarea.form-input { min-height:100px; resize:vertical; }
        .input-password-wrap { position:relative; }
        .input-password-wrap .form-input { padding-right:45px; }
        .toggle-pass { position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:#555; cursor:pointer; font-size:1rem; }
        .toggle-pass:hover { color:#fff; }
        .btn-save {
            background:linear-gradient(135deg,var(--green),var(--cyan)); color:#000;
            border:none; border-radius:12px; padding:13px 30px;
            font-family:inherit; font-size:0.9rem; font-weight:800;
            cursor:pointer; transition:0.2s; display:flex; align-items:center; gap:8px;
        }
        .btn-save:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(161,255,90,0.3); }
        .alert-success {
            background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.15);
            color:#ccc; padding:12px 16px; border-radius:12px;
            margin-bottom:20px; font-size:0.85rem; font-weight:600;
            display:flex; align-items:center; gap:8px;
        }
        .provider-tabs { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .provider-tab {
            padding:8px 18px; border-radius:10px; border:1px solid rgba(255,255,255,0.1);
            background:transparent; color:#666; cursor:pointer; font-size:0.8rem; font-weight:700;
            transition:0.2s; font-family:inherit;
        }
        .provider-tab.active { background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.3); color:#fff; }
        .model-hint { font-size:0.72rem; color:#444; margin-top:5px; }
        .model-hint code { color:var(--cyan); background:rgba(78,253,196,0.08); padding:1px 6px; border-radius:4px; }
        .test-result { margin-top:10px; font-size:0.8rem; padding:10px; border-radius:8px; display:none; }
        .test-result.ok { background:rgba(161,255,90,0.08); color:var(--green); border:1px solid rgba(161,255,90,0.2); display:block; }
        .test-result.err { background:rgba(255,80,80,0.08); color:#ff6b6b; border:1px solid rgba(255,80,80,0.2); display:block; }
        .status-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
        .dot-ok { background:var(--green); box-shadow:0 0 6px var(--green); }
        .dot-err { background:#ff6b6b; box-shadow:0 0 6px #ff6b6b; }
    </style>
</head>
<body>
<?php include '../sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-cog" style="font-size:1.5rem;color:var(--green);vertical-align:middle;margin-right:10px;"></i>Settings AI Asisten</h1>
        <p>Konfigurasikan API key dan model AI yang digunakan untuk asisten pintar HVM.</p>
    </div>

    <?php if(isset($saved)): ?>
    <div class="alert-success">
        <i class="fas fa-check-circle"></i> Pengaturan berhasil disimpan!
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="settings-grid">

            <!-- Provider Selection -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-robot"></i> Provider AI</div>

                <div class="form-group">
                    <label>Pilih Provider</label>
                    <div class="provider-tabs">
                        <button type="button" class="provider-tab <?= $provider==='openai'?'active':'' ?>" onclick="setProvider('openai',this)"><i class="fas fa-brain"></i> OpenAI</button>
                        <button type="button" class="provider-tab <?= $provider==='groq'?'active':'' ?>" onclick="setProvider('groq',this)"><i class="fas fa-bolt"></i> Groq</button>
                        <button type="button" class="provider-tab <?= $provider==='gemini'?'active':'' ?>" onclick="setProvider('gemini',this)"><i class="fas fa-gem"></i> Google Gemini</button>
                        <button type="button" class="provider-tab <?= $provider==='anthropic'?'active':'' ?>" onclick="setProvider('anthropic',this)"><i class="fas fa-feather"></i> Anthropic</button>
                    </div>
                    <input type="hidden" name="ai_provider" id="ai_provider" value="<?= htmlspecialchars($provider) ?>">
                </div>

                <div class="form-group">
                    <label>Model</label>
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
                <div class="card-title"><i class="fas fa-key"></i> Konfigurasi API Key</div>

                <div class="form-group">
                    <label>API Key</label>
                    <div class="input-password-wrap">
                        <input type="password" name="ai_api_key" id="ai_api_key" class="form-input"
                               value="<?= htmlspecialchars($api_key) ?>"
                               placeholder="sk-... / AIzaSy... / sk-ant-...">
                        <button type="button" class="toggle-pass" onclick="togglePass()">
                            <i class="fas fa-eye" id="passIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="button" class="btn-save" style="background:rgba(255,255,255,0.06); color:#fff; box-shadow:none;" onclick="testConnection()">
                    <i class="fas fa-plug"></i> Test Koneksi
                </button>
                <div id="testResult" class="test-result"></div>
            </div>

            <!-- Icon Upload -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-image"></i> Ikon Tombol AI</div>
                <?php
                $ai_icon = $settings['ai_icon'] ?? '';
                $icon_path = $ai_icon ? '/uploads/ai/' . $ai_icon : '';
                ?>
                <?php if($icon_path): ?>
                <div style="margin-bottom:18px; text-align:center;">
                    <div style="width:80px;height:80px;border-radius:50%;overflow:hidden;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);margin:0 auto 10px;display:flex;align-items:center;justify-content:center;">
                        <img src="<?= htmlspecialchars($icon_path) ?>" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                    <div style="font-size:0.72rem;color:#555;">Ikon saat ini</div>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Upload Ikon Baru (PNG/JPG/SVG, max 2MB)</label>
                    <div style="border:2px dashed rgba(255,255,255,0.1);border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:0.2s;" onclick="document.getElementById('ai_icon_input').click()" id="iconDropZone">
                        <i class="fas fa-cloud-upload-alt" style="font-size:1.8rem;color:#444;margin-bottom:8px;display:block;"></i>
                        <div id="iconFileName" style="font-size:0.8rem;color:#555;">Klik atau drag gambar ke sini</div>
                    </div>
                    <input type="file" name="ai_icon" id="ai_icon_input" accept="image/*" style="display:none;" onchange="previewIcon(this)">
                    <div style="margin-top:12px;text-align:center;display:none;" id="iconPreviewWrap">
                        <img id="iconPreviewImg" src="" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:2px solid rgba(161,255,90,0.3);">
                    </div>
                </div>
            </div>

            <!-- Persona -->
            <div class="settings-card">
                <div class="card-title"><i class="fas fa-user-astronaut"></i> Identitas & Persona AI</div>
                <div class="form-group">
                    <label>Nama Asisten</label>
                    <input type="text" name="ai_name" class="form-input" value="<?= htmlspecialchars($ai_name) ?>" placeholder="cth: Nova, Aria, HVM AI">
                </div>
                <div class="form-group">
                    <label>Persona / Instruksi Tambahan (opsional)</label>
                    <textarea name="ai_persona" class="form-input"><?= htmlspecialchars($persona) ?></textarea>
                </div>
                <button type="submit" name="save_settings" class="btn-save">
                    <i class="fas fa-save"></i> Simpan Semua Pengaturan
                </button>
            </div>

        </div>
    </form>
</main>

<script>
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

function togglePass() {
    const inp = document.getElementById('ai_api_key');
    const ic  = document.getElementById('passIcon');
    if(inp.type==='password') { inp.type='text'; ic.className='fas fa-eye-slash'; }
    else { inp.type='password'; ic.className='fas fa-eye'; }
}

async function testConnection() {
    const key  = document.getElementById('ai_api_key').value.trim();
    const prov = document.getElementById('ai_provider').value;
    const mod  = document.getElementById('ai_model').value.trim();
    const res  = document.getElementById('testResult');
    res.className = 'test-result';
    res.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    res.style.display = 'block';

    try {
        const fd = new FormData();
        fd.append('action', 'test'); fd.append('key', key); fd.append('provider', prov); fd.append('model', mod);
        const r = await fetch('/dashboard/ai/handler.php', { method:'POST', body:fd });
        const d = await r.json();
        if(d.ok) {
            res.className = 'test-result ok';
            res.innerHTML = '<span class="status-dot dot-ok"></span> Koneksi berhasil! Model: ' + (d.model||mod);
        } else {
            res.className = 'test-result err';
            res.innerHTML = '<span class="status-dot dot-err"></span> Gagal: ' + (d.error || 'Unknown error');
        }
    } catch(e) {
        res.className = 'test-result err';
        res.innerHTML = '<span class="status-dot dot-err"></span> Gagal terhubung ke handler.';
    }
}

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
