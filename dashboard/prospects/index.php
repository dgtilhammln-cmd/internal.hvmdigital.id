<?php
// 1. INIT
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
if(!isset($_SESSION['admin'])){ echo "<script>window.location='/index.php';</script>"; exit; }
$allowed  = (isset($_SESSION['role']) && ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin'));

// 2. AUTO-FIX DATABASE
function fixCol($conn, $t, $c, $d){
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    if(mysqli_num_rows($q) == 0) mysqli_query($conn, "ALTER TABLE `$t` ADD COLUMN `$c` $d");
}

// Create table if not exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `prospects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL,
    `pic` VARCHAR(255) DEFAULT NULL,
    `jabatan` VARCHAR(255) DEFAULT NULL,
    `wa` VARCHAR(50) DEFAULT NULL,
    `alamat` TEXT DEFAULT NULL,
    `domain` VARCHAR(255) DEFAULT NULL,
    `link_deck` TEXT DEFAULT NULL,
    `catatan` TEXT DEFAULT NULL,
    `status` ENUM('Cold','Warm','Hot','Closed') DEFAULT 'Cold',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// 3. AJAX ACTIONS
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];

    if($act === 'save') {
        $id          = intval($_POST['id'] ?? 0);
        $company     = mysqli_real_escape_string($conn, trim($_POST['company_name'] ?? ''));
        $pic         = mysqli_real_escape_string($conn, trim($_POST['pic'] ?? ''));
        $jabatan     = mysqli_real_escape_string($conn, trim($_POST['jabatan'] ?? ''));
        $wa          = mysqli_real_escape_string($conn, trim($_POST['wa'] ?? ''));
        $alamat      = mysqli_real_escape_string($conn, trim($_POST['alamat'] ?? ''));
        $domain      = mysqli_real_escape_string($conn, trim($_POST['domain'] ?? ''));
        $link_deck   = mysqli_real_escape_string($conn, trim($_POST['link_deck'] ?? ''));
        $catatan     = mysqli_real_escape_string($conn, trim($_POST['catatan'] ?? ''));
        $status      = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Cold');

        if(empty($company)) { echo json_encode(['ok'=>false,'msg'=>'Nama perusahaan wajib diisi.']); exit; }

        if($id > 0) {
            mysqli_query($conn, "UPDATE prospects SET company_name='$company', pic='$pic', jabatan='$jabatan', wa='$wa', alamat='$alamat', domain='$domain', link_deck='$link_deck', catatan='$catatan', status='$status' WHERE id=$id");
        } else {
            mysqli_query($conn, "INSERT INTO prospects (company_name, pic, jabatan, wa, alamat, domain, link_deck, catatan, status) VALUES ('$company','$pic','$jabatan','$wa','$alamat','$domain','$link_deck','$catatan','$status')");
        }
        echo json_encode(['ok'=>true]);
        exit;
    }

    if($act === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        mysqli_query($conn, "DELETE FROM prospects WHERE id=$id");
        echo json_encode(['ok'=>true]);
        exit;
    }

    if($act === 'get') {
        $id = intval($_POST['id'] ?? 0);
        $q = mysqli_query($conn, "SELECT * FROM prospects WHERE id=$id");
        $row = mysqli_fetch_assoc($q);
        echo json_encode($row ?: null);
        exit;
    }
    exit;
}

// 4. LOAD DATA
$search = trim($_GET['s'] ?? '');
$status_filter = trim($_GET['st'] ?? '');
$where = "1=1";
if($search) $where .= " AND (company_name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR pic LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR domain LIKE '%".mysqli_real_escape_string($conn,$search)."%')";
if($status_filter) $where .= " AND status='".mysqli_real_escape_string($conn,$status_filter)."'";
$q = mysqli_query($conn, "SELECT * FROM prospects WHERE $where ORDER BY FIELD(status,'Hot','Warm','Cold','Closed'), created_at DESC");
$prospects = [];
while($row = mysqli_fetch_assoc($q)) $prospects[] = $row;

$counts = ['all'=>0,'Cold'=>0,'Warm'=>0,'Hot'=>0,'Closed'=>0];
$q_c = mysqli_query($conn, "SELECT status, COUNT(*) as c FROM prospects GROUP BY status");
while($r = mysqli_fetch_assoc($q_c)) { $counts[$r['status']] = $r['c']; $counts['all'] += $r['c']; }
?>
<?php include '../sidebar.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Prospects - HVM Digital</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --bg:      #050505;
    --card:    rgba(18,18,18,0.8);
    --border:  rgba(255,255,255,0.08);
    --green:   #a1ff5a;
    --teal:    #4efdc4;
    --muted:   #888;
    --red:     #ff6b6b;
    --orange:  #fca311;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif; }
body { background:var(--bg); color:#fff; min-height:100vh; }

/* ambient */
.ambient-glow { position:fixed; border-radius:50%; filter:blur(150px); opacity:0.05; z-index:-1; pointer-events:none; }
.glow-1 { top:-100px; left:40px; width:500px; height:500px; background:var(--green); }
.glow-2 { bottom:-100px; right:40px; width:400px; height:400px; background:var(--teal); }

.main-content { padding:32px 40px; max-width:1400px; margin:0 auto; }

/* Page header */
.page-top { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
.page-top h1 { font-size:1.8rem; font-weight:800; color:#fff; }
.page-top p { color:var(--muted); font-size:0.88rem; margin-top:4px; }
.btn-add { background:linear-gradient(135deg,var(--green),var(--teal)); border:none; color:#000; border-radius:10px; padding:11px 22px; font-family:inherit; font-size:0.88rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:8px; transition:transform 0.2s,box-shadow 0.2s; }
.btn-add:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(161,255,90,0.25); }

/* Stat cards */
.stat-row { display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap; }
.stat-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:16px 22px; min-width:120px; cursor:pointer; transition:border-color 0.2s,background 0.2s; }
.stat-card:hover, .stat-card.active { border-color:rgba(161,255,90,0.3); background:rgba(161,255,90,0.05); }
.stat-card .val { font-size:1.6rem; font-weight:800; }
.stat-card .lbl { font-size:0.7rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }
.s-hot  .val { color:var(--red); }
.s-warm .val { color:var(--orange); }
.s-cold .val { color:#94a3b8; }
.s-closed .val { color:var(--muted); }

/* Search & filter */
.toolbar { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.search-wrap { position:relative; flex:1; min-width:200px; }
.search-wrap i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:0.85rem; }
.search-input { width:100%; background:var(--card); border:1px solid var(--border); color:#fff; border-radius:10px; padding:10px 14px 10px 38px; font-family:inherit; font-size:0.85rem; outline:none; transition:border-color 0.2s; }
.search-input:focus { border-color:rgba(161,255,90,0.3); }
.search-input::placeholder { color:var(--muted); }

/* Table */
.table-wrap { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
.ptable { width:100%; border-collapse:collapse; }
.ptable thead tr { background:rgba(255,255,255,0.03); border-bottom:1px solid var(--border); }
.ptable th { padding:13px 16px; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--muted); text-align:left; }
.ptable tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.15s; cursor:pointer; }
.ptable tbody tr:last-child { border-bottom:none; }
.ptable tbody tr:hover { background:rgba(255,255,255,0.03); }
.ptable td { padding:14px 16px; font-size:0.82rem; vertical-align:middle; }
.company-name { font-weight:700; color:#fff; }
.company-sub  { font-size:0.72rem; color:var(--muted); margin-top:2px; }

.badge { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:20px; font-size:0.68rem; font-weight:700; letter-spacing:0.3px; }
.b-hot    { background:rgba(255,107,107,0.1); color:var(--red); border:1px solid rgba(255,107,107,0.2); }
.b-warm   { background:rgba(252,163,17,0.1); color:var(--orange); border:1px solid rgba(252,163,17,0.2); }
.b-cold   { background:rgba(148,163,184,0.1); color:#94a3b8; border:1px solid rgba(148,163,184,0.2); }
.b-closed { background:rgba(255,255,255,0.05); color:var(--muted); border:1px solid #333; }

.act-btns { display:flex; gap:6px; }
.act-btn { width:30px; height:30px; border-radius:8px; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.78rem; transition:all 0.2s; background:rgba(255,255,255,0.05); color:#aaa; }
.act-btn:hover { background:rgba(255,255,255,0.12); color:#fff; transform:scale(1.1); }
.act-btn.del:hover { background:rgba(255,107,107,0.12); color:var(--red); }
.act-btn.wa-btn { color:#25d366; }
.act-btn.wa-btn:hover { background:rgba(37,211,102,0.12); }

.empty-state { text-align:center; padding:60px; color:var(--muted); }
.empty-state i { font-size:3rem; margin-bottom:12px; opacity:0.3; display:block; }

/* Modal */
.modal-overlay { position:fixed; inset:0; z-index:2000; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); display:none; align-items:center; justify-content:center; padding:20px; }
.modal-overlay.open { display:flex; }
.modal-box { background:#0d0d0d; border:1px solid rgba(255,255,255,0.1); border-radius:20px; width:100%; max-width:620px; max-height:92vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 40px 80px rgba(0,0,0,0.7); }
.modal-head { display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border); }
.modal-head h3 { font-size:1rem; font-weight:700; }
.btn-close { background:rgba(255,255,255,0.06); border:none; color:#888; border-radius:8px; width:32px; height:32px; cursor:pointer; font-size:1.1rem; transition:all 0.2s; display:flex; align-items:center; justify-content:center; }
.btn-close:hover { background:rgba(255,255,255,0.12); color:#fff; }
.modal-body { padding:24px; overflow-y:auto; }

.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-grp { margin-bottom:14px; }
.form-grp label { display:block; font-size:0.7rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
.form-grp input, .form-grp textarea, .form-grp select {
    width:100%; background:rgba(255,255,255,0.04); border:1px solid var(--border); color:#fff;
    border-radius:10px; padding:10px 14px; font-family:inherit; font-size:0.85rem; outline:none;
    transition:border-color 0.2s; resize:none;
}
.form-grp input:focus, .form-grp textarea:focus, .form-grp select:focus { border-color:rgba(161,255,90,0.35); }
.form-grp select option { background:#111; }

.modal-foot { padding:16px 24px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }
.btn-cancel { background:rgba(255,255,255,0.05); border:1px solid var(--border); color:#888; border-radius:10px; padding:9px 20px; font-family:inherit; font-size:0.85rem; cursor:pointer; transition:all 0.2s; }
.btn-cancel:hover { color:#fff; background:rgba(255,255,255,0.08); }
.btn-save { background:linear-gradient(135deg,var(--green),var(--teal)); border:none; color:#000; border-radius:10px; padding:9px 22px; font-family:inherit; font-size:0.85rem; font-weight:700; cursor:pointer; transition:all 0.2s; }
.btn-save:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(161,255,90,0.2); }

/* Toast */
.toast { position:fixed; bottom:24px; right:24px; background:#111; border:1px solid var(--green); color:var(--green); padding:12px 20px; border-radius:10px; font-size:0.85rem; font-weight:600; z-index:9999; display:none; animation:fadeIn 0.3s; }
@keyframes fadeIn { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;} }
</style>
</head>
<body>
<div class="ambient-glow glow-1"></div>
<div class="ambient-glow glow-2"></div>
<div class="main-content">

    <div class="page-top">
        <div>
            <h1><i class="fas fa-binoculars" style="color:var(--green);margin-right:10px;font-size:1.4rem;vertical-align:middle;"></i>Prospects</h1>
            <p>Kelola data calon klien dan pantau status pipeline Anda</p>
        </div>
        <button class="btn-add" onclick="openModal()">
            <i class="fas fa-plus"></i> Tambah Prospek
        </button>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-row">
        <div class="stat-card <?= !$status_filter?'active':'' ?>" onclick="filterStatus('')">
            <div class="val"><?= $counts['all'] ?></div>
            <div class="lbl">Total</div>
        </div>
        <div class="stat-card s-hot <?= $status_filter=='Hot'?'active':'' ?>" onclick="filterStatus('Hot')">
            <div class="val"><?= $counts['Hot'] ?></div>
            <div class="lbl"><i class="fas fa-fire"></i> Hot</div>
        </div>
        <div class="stat-card s-warm <?= $status_filter=='Warm'?'active':'' ?>" onclick="filterStatus('Warm')">
            <div class="val"><?= $counts['Warm'] ?></div>
            <div class="lbl"><i class="fas fa-sun"></i> Warm</div>
        </div>
        <div class="stat-card s-cold <?= $status_filter=='Cold'?'active':'' ?>" onclick="filterStatus('Cold')">
            <div class="val"><?= $counts['Cold'] ?></div>
            <div class="lbl"><i class="fas fa-snowflake"></i> Cold</div>
        </div>
        <div class="stat-card s-closed <?= $status_filter=='Closed'?'active':'' ?>" onclick="filterStatus('Closed')">
            <div class="val"><?= $counts['Closed'] ?></div>
            <div class="lbl"><i class="fas fa-check-circle"></i> Closed</div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" class="search-input" id="searchInput" placeholder="Cari nama perusahaan, PIC, domain..." value="<?= htmlspecialchars($search) ?>" oninput="debounceSearch(this.value)">
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
        <table class="ptable">
            <thead>
                <tr>
                    <th>Perusahaan / PIC</th>
                    <th>Kontak</th>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php if(empty($prospects)): ?>
                <tr><td colspan="5" class="empty-state">
                    <i class="fas fa-binoculars"></i>
                    <div>Belum ada data prospek.</div>
                    <div style="font-size:0.8rem;margin-top:6px;">Klik "Tambah Prospek" untuk mulai menambahkan.</div>
                </td></tr>
            <?php else: foreach($prospects as $p): 
                $badgeClass = 'b-'.strtolower($p['status']);
                $waLink = $p['wa'] ? 'https://wa.me/'.preg_replace('/[^0-9]/','',$p['wa']) : '#';
            ?>
                <tr onclick="editProspect(<?= $p['id'] ?>)">
                    <td>
                        <div class="company-name"><?= htmlspecialchars($p['company_name']) ?></div>
                        <?php if($p['pic']): ?><div class="company-sub"><i class="fas fa-user" style="margin-right:4px;"></i><?= htmlspecialchars($p['pic']) ?><?= $p['jabatan'] ? ' · '.$p['jabatan'] : '' ?></div><?php endif; ?>
                    </td>
                    <td style="color:var(--muted); font-size:0.8rem;"><?= htmlspecialchars($p['wa'] ?: '-') ?></td>
                    <td style="font-size:0.8rem;">
                        <?php if($p['domain']): ?>
                            <a href="https://<?= htmlspecialchars($p['domain']) ?>" target="_blank" onclick="event.stopPropagation()" style="color:var(--teal); text-decoration:none;"><?= htmlspecialchars($p['domain']) ?></a>
                        <?php else: ?><span style="color:var(--muted);">-</span><?php endif; ?>
                    </td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $p['status'] ?></span></td>
                    <td>
                        <div class="act-btns" onclick="event.stopPropagation()">
                            <?php if($p['wa']): ?>
                            <a href="<?= $waLink ?>" target="_blank" class="act-btn wa-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                            <?php if($p['link_deck']): ?>
                            <a href="<?= htmlspecialchars($p['link_deck']) ?>" target="_blank" class="act-btn" title="Deck"><i class="fas fa-file-powerpoint"></i></a>
                            <?php endif; ?>
                            <button class="act-btn" onclick="editProspect(<?= $p['id'] ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                            <button class="act-btn del" onclick="deleteProspect(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['company_name'])) ?>')" title="Hapus"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ADD/EDIT -->
<div class="modal-overlay" id="prospectModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="modalTitle">Tambah Prospek</h3>
            <button class="btn-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="f_id">
            <div class="form-row">
                <div class="form-grp" style="grid-column:span 2;">
                    <label>Nama Perusahaan *</label>
                    <input type="text" id="f_company" placeholder="PT. Contoh Maju Bersama">
                </div>
            </div>
            <div class="form-row">
                <div class="form-grp">
                    <label>PIC (Person In Charge)</label>
                    <input type="text" id="f_pic" placeholder="Nama kontak">
                </div>
                <div class="form-grp">
                    <label>Jabatan</label>
                    <input type="text" id="f_jabatan" placeholder="Marketing Manager">
                </div>
            </div>
            <div class="form-row">
                <div class="form-grp">
                    <label>No. WhatsApp</label>
                    <input type="text" id="f_wa" placeholder="08xxxxxxxxxx">
                </div>
                <div class="form-grp">
                    <label>Status Pipeline</label>
                    <select id="f_status">
                        <option value="Cold"><i class="fas fa-snowflake"></i> Cold</option>
                        <option value="Warm"><i class="fas fa-sun"></i> Warm</option>
                        <option value="Hot"><i class="fas fa-fire"></i> Hot</option>
                        <option value="Closed"><i class="fas fa-check-circle"></i> Closed</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-grp">
                    <label>Domain / Website</label>
                    <input type="text" id="f_domain" placeholder="contoh.com">
                </div>
                <div class="form-grp">
                    <label>Link Deck / Proposal</label>
                    <input type="text" id="f_deck" placeholder="https://...">
                </div>
            </div>
            <div class="form-grp">
                <label>Alamat</label>
                <input type="text" id="f_alamat" placeholder="Jl. Contoh No. 1, Kota">
            </div>
            <div class="form-grp">
                <label>Catatan</label>
                <textarea id="f_catatan" rows="3" placeholder="Catatan meeting, kebutuhan, dll..."></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-cancel" onclick="closeModal()">Batal</button>
            <button class="btn-save" onclick="saveProspect()"><i class="fas fa-save" style="margin-right:6px;"></i>Simpan</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
let searchTimer;
function debounceSearch(val) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const url = new URL(window.location);
        url.searchParams.set('s', val);
        window.location = url;
    }, 500);
}

function filterStatus(st) {
    const url = new URL(window.location);
    if(st) url.searchParams.set('st', st);
    else url.searchParams.delete('st');
    window.location = url;
}

function openModal(data = null) {
    document.getElementById('f_id').value = data ? data.id : '';
    document.getElementById('f_company').value = data ? (data.company_name||'') : '';
    document.getElementById('f_pic').value = data ? (data.pic||'') : '';
    document.getElementById('f_jabatan').value = data ? (data.jabatan||'') : '';
    document.getElementById('f_wa').value = data ? (data.wa||'') : '';
    document.getElementById('f_domain').value = data ? (data.domain||'') : '';
    document.getElementById('f_deck').value = data ? (data.link_deck||'') : '';
    document.getElementById('f_alamat').value = data ? (data.alamat||'') : '';
    document.getElementById('f_catatan').value = data ? (data.catatan||'') : '';
    document.getElementById('f_status').value = data ? (data.status||'Cold') : 'Cold';
    document.getElementById('modalTitle').textContent = data ? 'Edit Prospek' : 'Tambah Prospek';
    document.getElementById('prospectModal').classList.add('open');
    setTimeout(() => document.getElementById('f_company').focus(), 100);
}

function closeModal() { document.getElementById('prospectModal').classList.remove('open'); }

async function editProspect(id) {
    const fd = new FormData();
    fd.append('ajax_action', 'get');
    fd.append('id', id);
    const res = await fetch('', { method:'POST', body:fd });
    const data = await res.json();
    if(data) openModal(data);
}

async function saveProspect() {
    const company = document.getElementById('f_company').value.trim();
    if(!company) { showToast('Nama perusahaan wajib diisi!', true); return; }
    const fd = new FormData();
    fd.append('ajax_action', 'save');
    fd.append('id', document.getElementById('f_id').value || 0);
    fd.append('company_name', company);
    fd.append('pic', document.getElementById('f_pic').value);
    fd.append('jabatan', document.getElementById('f_jabatan').value);
    fd.append('wa', document.getElementById('f_wa').value);
    fd.append('domain', document.getElementById('f_domain').value);
    fd.append('link_deck', document.getElementById('f_deck').value);
    fd.append('alamat', document.getElementById('f_alamat').value);
    fd.append('catatan', document.getElementById('f_catatan').value);
    fd.append('status', document.getElementById('f_status').value);
    const res = await fetch('', { method:'POST', body:fd });
    const data = await res.json();
    if(data.ok) { closeModal(); showToast('Data berhasil disimpan!'); setTimeout(() => location.reload(), 800); }
    else showToast(data.msg || 'Gagal menyimpan!', true);
}

async function deleteProspect(id, name) {
    if(!confirm(`Hapus "${name}" dari daftar prospek?`)) return;
    const fd = new FormData();
    fd.append('ajax_action', 'delete');
    fd.append('id', id);
    await fetch('', { method:'POST', body:fd });
    showToast('Dihapus!');
    setTimeout(() => location.reload(), 700);
}

function showToast(msg, isErr = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.borderColor = isErr ? 'var(--red)' : 'var(--green)';
    t.style.color = isErr ? 'var(--red)' : 'var(--green)';
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 2800);
}

document.getElementById('prospectModal').addEventListener('click', function(e) {
    if(e.target === this) closeModal();
});
</script>
</body>
</html>