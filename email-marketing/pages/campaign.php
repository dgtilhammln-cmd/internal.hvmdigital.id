<?php
/* ==========================================================================
   NEBULA TITAN OUTBOUND - GOD-MODE ENTERPRISE (V5.0)
   The Ultimate Email Deployment Engine for HVM Digital
   ========================================================================== */

if(!defined('DB_NAME')) { 
    include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php'; 
}

// 1. NEURAL SYNC: AMBIL CONFIG DARI ENGINE SETTINGS
$res_cfg = mysqli_query($conn, "SELECT * FROM email_settings");
$cfg = [];
while($r = mysqli_fetch_assoc($res_cfg)) { $cfg[$r['key_name']] = $r['key_value']; }

$def_subject  = $cfg['default_subject'] ?? 'Strategic Partnership Opportunity';
$drip_min     = (int)($cfg['drip_interval'] ?? 5);
$office_start = $cfg['work_hour_start'] ?? '08:30';

$report = ["status" => "", "count" => 0];

// 2. CAMPAIGN EXECUTION CORE
if (isset($_POST['launch_titan_mission'])) {
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $compro  = mysqli_real_escape_string($conn, $_POST['compro_link']);
    $body    = mysqli_real_escape_string($conn, $_POST['body_content']);
    $manual_list = $_POST['target_list'];

    // Handle Spreadsheet Upload
    $file_list = "";
    if (!empty($_FILES['spreadsheet']['tmp_name'])) {
        $file_list = file_get_contents($_FILES['spreadsheet']['tmp_name']);
    }

    $final_raw = trim($manual_list . "\n" . $file_list);

    // Create Campaign Entry
    $sql_c = "INSERT INTO email_campaigns (subject, body_content, attachment_link, status) VALUES ('$subject', '$body', '$compro', 'running')";
    
    if (mysqli_query($conn, $sql_c)) {
        $camp_id = mysqli_insert_id($conn);
        $targets = explode("\n", str_replace("\r", "", $final_raw));
        
        // Drip-Feed Calculation Logic
        $now = new DateTime();
        $work_trigger = new DateTime(date('Y-m-d') . ' ' . $office_start);
        $current_ptr = ($now > $work_trigger) ? $now : $work_trigger;

        foreach ($targets as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $csv = explode(",", $line);
            if(count($csv) < 2) continue;

            $name  = mysqli_real_escape_string($conn, trim($csv[0]));
            $email = mysqli_real_escape_string($conn, trim($csv[1]));

            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // FORCE UPDATE: Jika email sudah ada, GANTI namanya dengan yang diketik sekarang
                $sql_contact = "INSERT INTO email_contacts (name, email) VALUES ('$name', '$email') 
                                ON DUPLICATE KEY UPDATE name = '$name'";
                mysqli_query($conn, $sql_contact);
                
                // Ambil ID kontaknya
                $contact_res = mysqli_query($conn, "SELECT id FROM email_contacts WHERE email='$email'");
                $contact_id = mysqli_fetch_assoc($contact_res)['id'];

                // Masukkan ke Antrean
                $sched = $current_ptr->format('Y-m-d H:i:s');
                mysqli_query($conn, "INSERT INTO email_queue (campaign_id, contact_id, scheduled_at, status) 
                                     VALUES ($camp_id, $contact_id, '$sched', 'pending')");
                
                $current_ptr->modify("+$drip_min minutes");
                $report['count']++;
            }
        }
        $report['status'] = "deployed";
    }
}
?>

<!-- START TITAN UI ENGINE -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

    :root {
        --n-green: #a1ff5a;
        --n-cyan: #4efdc4;
        --n-glass: rgba(15, 15, 15, 0.75);
        --n-border: rgba(255, 255, 255, 0.08);
        --n-grad: linear-gradient(135deg, #a1ff5a 0%, #4efdc4 100%);
    }

    .titan-outbound-view {
        padding-left: 100px; /* SYNC WITH HVM SIDEBAR */
        padding-right: 30px;
        padding-top: 15px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: #fff;
        animation: titanFadeUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
    }

    @keyframes titanFadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    /* HEADER STYLES */
    .titan-head-area { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; }
    .titan-head-area h1 { font-weight: 800; font-size: 2.8rem; margin: 0; letter-spacing: -2px; }
    .titan-head-area p { color: #555; font-size: 1rem; margin-top: 5px; }

    /* LAYOUT GRID */
    .titan-grid { display: grid; grid-template-columns: 1fr 400px; gap: 30px; }

    /* CARDS & CONTAINERS */
    .titan-card {
        background: var(--n-glass);
        backdrop-filter: blur(40px);
        border: 1px solid var(--n-border);
        border-radius: 40px;
        padding: 45px;
        box-shadow: 0 40px 80px rgba(0,0,0,0.5);
        position: relative;
        overflow: hidden;
    }

    .titan-card::after {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(161, 255, 90, 0.02) 0%, transparent 70%);
        z-index: -1;
    }

    /* FORM ELEMENTS */
    .titan-field-group { margin-bottom: 30px; }
    .titan-label {
        display: block; margin-bottom: 12px; font-weight: 800; color: #777;
        font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px;
    }

    .titan-input {
        width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--n-border);
        padding: 18px 25px; border-radius: 20px; color: #fff; outline: none; transition: 0.4s;
        font-size: 0.95rem; font-family: inherit;
    }
    .titan-input:focus { border-color: var(--n-green); box-shadow: 0 0 25px rgba(161, 255, 90, 0.15); background: rgba(255,255,255,0.05); }

    /* PREMIUM BUTTONS */
    .btn-titan-main {
        width: 100%; padding: 20px; border-radius: 50px; border: none;
        background: var(--n-grad); color: #000; font-weight: 900;
        text-transform: uppercase; font-size: 0.9rem; letter-spacing: 2px;
        cursor: pointer; transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex; align-items: center; justify-content: center; gap: 15px;
        box-shadow: 0 15px 35px rgba(161, 255, 90, 0.3);
    }
    .btn-titan-main:hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 20px 45px rgba(78, 253, 196, 0.5); }

    .btn-titan-outline {
        width: 100%; padding: 15px; border-radius: 50px; background: transparent;
        border: 1px solid var(--n-border); color: #fff; font-weight: 700;
        font-size: 0.75rem; text-transform: uppercase; cursor: pointer; transition: 0.3s;
        display: flex; align-items: center; justify-content: center; gap: 10px;
    }
    .btn-titan-outline:hover { border-color: #fff; background: rgba(255,255,255,0.05); }

    /* UPLOAD AREA */
    .titan-upload-zone {
        border: 2px dashed var(--n-border); border-radius: 25px; padding: 40px 20px;
        text-align: center; cursor: pointer; transition: 0.4s; background: rgba(255,255,255,0.01);
        margin-bottom: 25px;
    }
    .titan-upload-zone:hover { border-color: var(--n-green); background: rgba(161, 255, 90, 0.03); }
    .titan-upload-zone i { font-size: 2.5rem; color: var(--n-green); margin-bottom: 15px; display: block; }
    .titan-upload-zone span { display: block; font-weight: 700; font-size: 0.9rem; }
    .titan-upload-zone small { color: #555; }

    /* PREMIUM MODALS */
    .titan-modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.92); z-index: 100000; backdrop-filter: blur(25px);
        align-items: center; justify-content: center;
    }
    .titan-modal-card {
        width: 95%; max-width: 800px; background: #050505; border: 1px solid var(--n-green);
        border-radius: 40px; padding: 50px; box-shadow: 0 0 100px rgba(161, 255, 90, 0.2);
        animation: titanModalZoom 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes titanModalZoom { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }

    /* GUIDANCE TABLE */
    .titan-guide-table { width: 100%; border-collapse: collapse; margin-top: 25px; border-radius: 15px; overflow: hidden; }
    .titan-guide-table th { background: rgba(161, 255, 90, 0.1); color: var(--n-green); padding: 15px; text-align: left; font-size: 0.75rem; text-transform: uppercase; }
    .titan-guide-table td { background: rgba(255,255,255,0.02); border: 1px solid var(--n-border); padding: 15px; color: #ccc; font-size: 0.85rem; font-family: monospace; }

    /* NOTIF BADGES */
    .titan-notif {
        background: rgba(161, 255, 90, 0.1); border: 1px solid var(--n-green);
        padding: 20px; border-radius: 20px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px;
        animation: titanSlideLeft 0.5s ease;
    }
    @keyframes titanSlideLeft { from { transform: translateX(50px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    /* MOBILE ADAPTATION */
    @media (max-width: 1200px) {
        .titan-grid { grid-template-columns: 1fr; }
        .titan-outbound-view { padding-left: 20px; padding-bottom: 120px; }
    }
</style>

<div class="titan-outbound-view">
    
    <!-- TITAN HEADER -->
    <div class="titan-head-area">
        <div>
            <h1>Titan Outbound</h1>
            <p>Cloud-based corporate email orchestration engine.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <div style="background:rgba(255,255,255,0.03); border:1px solid var(--n-border); padding:10px 20px; border-radius:50px; font-size:0.7rem; font-weight:800; color:var(--n-cyan);">
                <i class="fas fa-history"></i> INTERVAL: <?= $drip_min ?> MIN
            </div>
            <div style="background:rgba(161,255,90,0.05); border:1px solid var(--n-green); padding:10px 20px; border-radius:50px; font-size:0.7rem; font-weight:800; color:var(--n-green);">
                <i class="fas fa-clock"></i> STARTS: <?= $office_start ?>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION -->
    <?php if($report['status'] == "deployed"): ?>
    <div class="titan-notif">
        <i class="fas fa-space-shuttle" style="font-size: 2.5rem; color: var(--n-green);"></i>
        <div>
            <b style="font-size: 1.1rem; color: #fff;">MISSION DEPLOYED!</b>
            <p style="margin: 0; color: #888;">Nebula Titan telah menjadwalkan <b><?= $report['count'] ?> email</b>. Pengiriman akan dilakukan secara bertahap (Drip-feeding).</p>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="titan-grid">
            
            <!-- LEFT: CORE CONTENT -->
            <div class="titan-card">
                <div class="titan-field-group">
                    <label class="titan-label">Email Subject</label>
                    <input type="text" name="subject" class="titan-input" value="<?= htmlspecialchars($def_subject) ?>" placeholder="Contoh: Undangan VIP Media Partner" required>
                </div>

                <div class="titan-field-group">
                    <label class="titan-label">Proposal / Compro Link (Tracking Enabled)</label>
                    <input type="url" name="compro_link" class="titan-input" placeholder="https://hvmdigital.id/proposal-partnership.pdf" required>
                </div>

                <div class="titan-field-group" style="margin-bottom: 0;">
                    <label class="titan-label">Strategic Message Body</label>
                    <textarea name="body_content" rows="12" class="titan-input" style="line-height: 1.6;" placeholder="Tulis pesan profesional Anda di sini..." required></textarea>
                </div>
            </div>

            <!-- RIGHT: TARGETING ENGINE -->
            <div>
                <div class="titan-card" style="padding: 30px; margin-bottom: 20px;">
                    <label class="titan-label">Audience Ingestion</label>
                    
                    <!-- UPLOAD ZONE -->
                    <div class="titan-upload-zone" onclick="document.getElementById('fileInp').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Upload Spreadsheet</span>
                        <small>Drag & drop .csv or .txt</small>
                        <input type="file" id="fileInp" name="spreadsheet" accept=".csv,.txt" style="display:none;" onchange="showFileName(this)">
                        <div id="fileShow" style="margin-top: 15px; color: var(--n-green); font-weight: 800; font-size: 11px;"></div>
                    </div>

                    <button type="button" class="btn-titan-outline" style="margin-bottom: 25px;" onclick="openGuide()">
                        <i class="fas fa-info-circle"></i> View Data Structure
                    </button>

                    <label class="titan-label">Manual Entry (Name,Email)</label>
                    <textarea name="target_list" rows="6" class="titan-input" style="font-family: monospace; font-size: 0.8rem; background: #000;" placeholder="Bapak Ilham,ilham@hvm.id&#10;PT Sukses,admin@sukses.id"></textarea>
                </div>

                <!-- MAIN ACTIONS -->
                <button type="button" class="btn-titan-outline" onclick="openPreviewEngine()" style="height: 55px; border-radius: 15px; margin-bottom: 15px; font-size: 0.85rem;">
                    <i class="fas fa-eye"></i> Analysis & Preview
                </button>
                
                <button type="submit" name="launch_titan_mission" class="btn-titan-main">
                    <i class="fas fa-bolt"></i> Launch Mission
                </button>
            </div>

        </div>
    </form>
</div>

<!-- GUIDE MODAL: PREMIUM TABLE -->
<div id="guideModal" class="titan-modal-overlay">
    <div class="titan-modal-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
            <div>
                <h2 style="margin: 0; font-weight: 800; font-size: 1.8rem; letter-spacing: -1px;">Data Structure Guide</h2>
                <p style="color: #666; margin-top: 5px;">Format file CSV/Text agar Nebula dapat memproses target secara akurat.</p>
            </div>
            <button onclick="closeGuide()" style="background:none; border:none; color:#555; font-size: 2rem; cursor:pointer;">&times;</button>
        </div>

        <table class="titan-guide-table">
            <thead>
                <tr>
                    <th>Kolom 1 (Nama Sapaan)</th>
                    <th>Kolom 2 (Alamat Email)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Bapak Ilham</td>
                    <td>ilham@hvmdigital.id</td>
                </tr>
                <tr>
                    <td>Direksi PT Sukses</td>
                    <td>admin@ptsukses.com</td>
                </tr>
                <tr>
                    <td>Ibu Indah</td>
                    <td>indah.pameran@gmail.com</td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 30px; background: rgba(255,255,255,0.03); padding: 25px; border-radius: 20px; border: 1px solid var(--n-border);">
            <h4 style="color: var(--n-cyan); margin-top: 0;"><i class="fas fa-lightbulb"></i> Penting:</h4>
            <ul style="color: #888; font-size: 0.85rem; line-height: 1.8; padding-left: 20px;">
                <li>Jangan sertakan baris Judul (Header) di file Bapak.</li>
                <li>Pisahkan Nama dan Email hanya menggunakan tanda <b>Koma ( , )</b>.</li>
                <li>Pastikan tidak ada spasi tambahan setelah tanda koma.</li>
                <li>Satu baris mewakili satu pengiriman email.</li>
            </ul>
        </div>

        <button class="btn-titan-main" style="margin-top: 40px;" onclick="closeGuide()">I UNDERSTAND</button>
    </div>
</div>

<!-- SCRIPTS ENGINE -->
<script>
    function openGuide() { document.getElementById('guideModal').style.display = 'flex'; }
    function closeGuide() { document.getElementById('guideModal').style.display = 'none'; }

    function showFileName(input) {
        const display = document.getElementById('fileShow');
        if (input.files.length > 0) {
            display.innerHTML = '<i class="fas fa-file-csv"></i> FILENAME: ' + input.files[0].name.toUpperCase();
        }
    }

    function openPreviewEngine() {
        const sub = document.querySelector('input[name="subject"]').value;
        const msg = document.querySelector('textarea[name="body_content"]').value;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'pages/preview_render.php';
        form.target = 'previewWindow';

        const sInp = document.createElement('input'); sInp.name = 'subject'; sInp.value = sub; form.appendChild(sInp);
        const cInp = document.createElement('input'); cInp.name = 'content'; cInp.value = msg; form.appendChild(cInp);

        document.body.appendChild(form);
        window.open('', 'previewWindow', 'width=900,height=950');
        form.submit();
        document.body.removeChild(form);
    }

    // Modal Close Trigger
    window.onclick = function(e) {
        if (e.target == document.getElementById('guideModal')) closeGuide();
    }
</script>