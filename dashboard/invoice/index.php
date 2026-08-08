<?php
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';
if(!isset($_SESSION['admin'])){ echo "<script>window.location='/index.php';</script>"; exit; }

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Auto-create invoices table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `invoices` (
    `id`              VARCHAR(20)  NOT NULL PRIMARY KEY,
    `inv_no`          VARCHAR(50)  DEFAULT NULL,
    `client_name`     VARCHAR(255) DEFAULT NULL,
    `client_ref_type` ENUM('Client','Prospect','') DEFAULT '',
    `client_ref_id`   VARCHAR(50)  DEFAULT NULL,
    `service_label`   VARCHAR(255) DEFAULT NULL,
    `inv_date`        DATE         DEFAULT NULL,
    `subtotal`        BIGINT       DEFAULT 0,
    `ppn`             TINYINT      DEFAULT 11,
    `total`           BIGINT       DEFAULT 0,
    `status`          ENUM('Pending','DP','Lunas','Overdue') DEFAULT 'Pending',
    `bank`            VARCHAR(100) DEFAULT NULL,
    `rekening`        VARCHAR(100) DEFAULT NULL,
    `atas_nama`       VARCHAR(255) DEFAULT NULL,
    `pay_type`        VARCHAR(50)  DEFAULT 'Lunas',
    `dp1_pct`         TINYINT      DEFAULT 100,
    `sig_name`        VARCHAR(100) DEFAULT NULL,
    `sig_role`        VARCHAR(100) DEFAULT NULL,
    `contact`         VARCHAR(100) DEFAULT NULL,
    `email`           VARCHAR(255) DEFAULT NULL,
    `note`            TEXT         DEFAULT NULL,
    `items_json`      LONGTEXT     DEFAULT NULL,
    `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Auto-create bank_accounts table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `bank_accounts` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `bank_name`       VARCHAR(100) NOT NULL,
    `account_number`  VARCHAR(100) NOT NULL,
    `account_name`    VARCHAR(255) NOT NULL,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// AJAX handlers
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inv_ajax'])) {
    header('Content-Type: application/json');
    $act = $_POST['inv_ajax'];

    if($act === 'get_all') {
        $rows = [];
        $q = mysqli_query($conn, "SELECT * FROM invoices ORDER BY inv_date DESC, created_at DESC");
        if($q) while($r = mysqli_fetch_assoc($q)) { $r['items'] = json_decode($r['items_json'] ?? '[]', true) ?: []; $rows[] = $r; }
        echo json_encode($rows); exit;
    }

    if($act === 'get_banks') {
        $rows = [];
        $q = mysqli_query($conn, "SELECT * FROM bank_accounts ORDER BY id ASC");
        if($q) while($r = mysqli_fetch_assoc($q)) $rows[] = $r;
        echo json_encode($rows); exit;
    }

    if($act === 'save_bank') {
        $id = intval($_POST['id'] ?? 0);
        $bank = mysqli_real_escape_string($conn, trim($_POST['bank_name'] ?? ''));
        $acc = mysqli_real_escape_string($conn, trim($_POST['account_number'] ?? ''));
        $name = mysqli_real_escape_string($conn, trim($_POST['account_name'] ?? ''));
        if($id > 0) {
            mysqli_query($conn, "UPDATE bank_accounts SET bank_name='$bank', account_number='$acc', account_name='$name' WHERE id=$id");
        } else {
            mysqli_query($conn, "INSERT INTO bank_accounts (bank_name, account_number, account_name) VALUES ('$bank','$acc','$name')");
        }
        echo json_encode(['ok'=>true]); exit;
    }

    if($act === 'delete_bank') {
        $id = intval($_POST['id'] ?? 0);
        mysqli_query($conn, "DELETE FROM bank_accounts WHERE id=$id");
        echo json_encode(['ok'=>true]); exit;
    }

    if($act === 'get_companies') {
        $out = [];
        $qc = mysqli_query($conn, "SELECT client_id as ref_id, company_name FROM clients ORDER BY company_name ASC");
        if($qc) while($r=mysqli_fetch_assoc($qc)) $out[] = ['ref_id'=>$r['ref_id'],'name'=>$r['company_name'],'type'=>'Client'];
        $qpr_chk = mysqli_query($conn, "SHOW TABLES LIKE 'prospects'");
        if(mysqli_num_rows($qpr_chk) > 0) {
            $qp = mysqli_query($conn, "SELECT id as ref_id, company_name FROM prospects ORDER BY company_name ASC");
            if($qp) while($r=mysqli_fetch_assoc($qp)) $out[] = ['ref_id'=>$r['ref_id'],'name'=>$r['company_name'],'type'=>'Prospect'];
        }
        echo json_encode($out); exit;
    }

    if($act === 'save') {
        $d = $_POST;
        $id       = mysqli_real_escape_string($conn, $d['id']);
        $inv_no   = mysqli_real_escape_string($conn, $d['no'] ?? '');
        $client   = mysqli_real_escape_string($conn, $d['client'] ?? '');
        $ref_type = mysqli_real_escape_string($conn, $d['client_ref_type'] ?? '');
        $ref_id   = mysqli_real_escape_string($conn, $d['client_ref_id'] ?? '');
        $service  = mysqli_real_escape_string($conn, $d['service'] ?? '');
        $inv_date = mysqli_real_escape_string($conn, $d['date'] ?? date('Y-m-d'));
        $subtotal = (int)($d['subtotal'] ?? 0);
        $ppn      = (int)($d['ppn'] ?? 11);
        $total    = (int)($d['total'] ?? 0);
        $status   = mysqli_real_escape_string($conn, $d['status'] ?? 'Pending');
        $bank     = mysqli_real_escape_string($conn, $d['bank'] ?? '');
        $rek      = mysqli_real_escape_string($conn, $d['rekening'] ?? '');
        $an       = mysqli_real_escape_string($conn, $d['atasNama'] ?? '');
        $pt       = mysqli_real_escape_string($conn, $d['payType'] ?? 'Lunas');
        $dp1      = (int)($d['dp1Pct'] ?? 100);
        $sn       = mysqli_real_escape_string($conn, $d['sigName'] ?? '');
        $sr       = mysqli_real_escape_string($conn, $d['sigRole'] ?? '');
        $ct       = mysqli_real_escape_string($conn, $d['contact'] ?? '');
        $em       = mysqli_real_escape_string($conn, $d['email'] ?? '');
        $note     = mysqli_real_escape_string($conn, $d['note'] ?? '');
        $items    = mysqli_real_escape_string($conn, $d['items_json'] ?? '[]');

        $chk = mysqli_query($conn, "SELECT id FROM invoices WHERE id='$id'");
        if(mysqli_num_rows($chk) > 0) {
            $ok = mysqli_query($conn, "UPDATE invoices SET inv_no='$inv_no', client_name='$client', client_ref_type='$ref_type', client_ref_id='$ref_id', service_label='$service', inv_date='$inv_date', subtotal=$subtotal, ppn=$ppn, total=$total, status='$status', bank='$bank', rekening='$rek', atas_nama='$an', pay_type='$pt', dp1_pct=$dp1, sig_name='$sn', sig_role='$sr', contact='$ct', email='$em', note='$note', items_json='$items' WHERE id='$id'");
        } else {
            $ok = mysqli_query($conn, "INSERT INTO invoices (id,inv_no,client_name,client_ref_type,client_ref_id,service_label,inv_date,subtotal,ppn,total,status,bank,rekening,atas_nama,pay_type,dp1_pct,sig_name,sig_role,contact,email,note,items_json) VALUES ('$id','$inv_no','$client','$ref_type','$ref_id','$service','$inv_date',$subtotal,$ppn,$total,'$status','$bank','$rek','$an','$pt',$dp1,'$sn','$sr','$ct','$em','$note','$items')");
        }
        echo json_encode(['ok'=>(bool)$ok]); exit;
    }

    if($act === 'delete') {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $ok = mysqli_query($conn, "DELETE FROM invoices WHERE id='$id'");
        echo json_encode(['ok'=>(bool)$ok]); exit;
    }

    if($act === 'update_status') {
        $id     = mysqli_real_escape_string($conn, $_POST['id']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $ok = mysqli_query($conn, "UPDATE invoices SET status='$status' WHERE id='$id'");
        echo json_encode(['ok'=>(bool)$ok]); exit;
    }
    exit;
}
?>
<?php include '../sidebar.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice Generator - HVM Digital</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
:root {
    --bg-dark:     #050505;
    --card-bg:     rgba(20,20,20,0.6);
    --card-border: rgba(255,255,255,0.08);
    --neon-main:   #ffffff;
    --neon-sec:    #cccccc;
    --neon-red:    #ff6b6b;
    --neon-orange: #fca311;
    --neon-purple: #adb5bd;
    --grad-main:   #ffffff;
    --text-white:  #ffffff;
    --text-muted:  #a0a0a0;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif; }
body { background:var(--bg-dark); color:var(--text-white); min-height:100vh; overflow-x:hidden; }

.ambient-glow { position:fixed; border-radius:50%; filter:blur(150px); opacity:0.06; z-index:-1; animation:floatGlow 15s infinite alternate; pointer-events:none; }
.glow-1 { top:-100px; left:-100px; width:600px; height:600px; background:#a1ff5a; }
.glow-2 { bottom:-100px; right:-100px; width:600px; height:600px; background:#4efdc4; }
@keyframes floatGlow { from{transform:scale(1);}to{transform:scale(1.1);} }

::-webkit-scrollbar { width:6px; height:6px; }
::-webkit-scrollbar-track { background:#0a0a0a; }
::-webkit-scrollbar-thumb { background:#333; border-radius:10px; border:none; }
::-webkit-scrollbar-thumb:hover { background:#555; }

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
    color: #ffffff;
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
    background:transparent; color:var(--text-white);
    border:1px solid #333; border-radius:10px;
    padding:10px 18px; font-family:inherit; font-size:0.85rem; font-weight:600;
    cursor:pointer; display:flex;align-items:center;gap:8px;
    transition: background 0.2s;
}
.btn-outline:hover { background:rgba(255,255,255,0.05); }

/* TABLE */
.invoice-table-wrap { background:var(--card-bg); border:1px solid var(--card-border); border-radius:16px; overflow:hidden; backdrop-filter:blur(10px); }
.invoice-table { width:100%; border-collapse:collapse; }
.invoice-table thead tr { background:rgba(255,255,255,0.03); border-bottom:1px solid rgba(255,255,255,0.06); }
.invoice-table th { padding:14px 18px; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--text-muted); text-align:left; }
.invoice-table tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.15s; }
.invoice-table tbody tr:last-child { border-bottom:none; }
.invoice-table tbody tr:hover { background:rgba(255,255,255,0.03); }
.invoice-table td { padding:14px 18px; font-size:0.83rem; vertical-align:middle; }
.inv-number { font-weight:700; color:#fff; font-size:0.85rem; }
.inv-client { font-weight:600; }
.inv-client small { display:block; color:var(--text-muted); font-size:0.72rem; font-weight:400; margin-top:2px; }
.inv-date { color:var(--text-muted); font-size:0.8rem; }
.inv-amount { font-weight:700; color:#fff; }
.status-badge { display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:0.72rem;font-weight:700;letter-spacing:0.3px; }
.status-paid    { background:rgba(255,255,255,0.05); color:#ccc; border:1px solid #333; }
.status-pending { background:rgba(255,255,255,0.05); color:#ccc; border:1px solid #333; }
.status-dp      { background:rgba(255,255,255,0.05); color:#ccc; border:1px solid #333; }
.status-overdue { background:rgba(255,90,90,0.05); color:#ff8888; border:1px solid rgba(255,90,90,0.2); }
.action-btns { display:flex;gap:6px; }
.tbl-btn { width:32px;height:32px;border-radius:8px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.8rem;transition:all 0.2s; }
.tbl-btn.view   { background:rgba(255,255,255,0.05); color:#aaa; }
.tbl-btn.edit   { background:rgba(255,255,255,0.05); color:#aaa; }
.tbl-btn.print  { background:rgba(255,255,255,0.05); color:#aaa; }
.tbl-btn.del    { background:rgba(255,90,90,0.05); color:#ff8888; }
.tbl-btn:hover  { transform:scale(1.1); filter:brightness(1.3); background:rgba(255,255,255,0.1); }
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
.form-select {
    cursor:pointer;
    appearance:none;
    -webkit-appearance:none;
    -moz-appearance:none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 16px;
    padding-right: 40px;
}
.form-select option {
    background: #1a1a1a;
    color: #fff;
    padding: 10px;
}
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
.btn-add-item { background:rgba(255,255,255,0.04);border:1px dashed #444;color:#aaa;border-radius:10px;padding:10px;width:100%;font-family:inherit;font-size:0.82rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;margin-top:6px;transition:background 0.2s; }
.btn-add-item:hover { background:rgba(255,255,255,0.08); }

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
.payment-toggle.active { background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.2);color:#fff; }
.dp-section { background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:10px;padding:14px;margin-top:12px; }
.dp-section-title { font-size:0.72rem;font-weight:700;color:#aaa;margin-bottom:10px;display:flex;align-items:center;gap:6px; }
.dp-grid { display:grid;grid-template-columns:1fr 1fr;gap:10px; }

/* FOOTER BTNS */
.btn-ghost { background:transparent;border:1px solid rgba(255,255,255,0.1);color:#888;border-radius:10px;padding:9px 16px;font-family:inherit;font-size:0.82rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.2s; }
.btn-ghost:hover { border-color:rgba(255,255,255,0.25);color:#fff; }
.btn-preview { background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#bbb;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:0.82rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.2s; }
.btn-preview:hover { border-color:rgba(255,255,255,0.2);color:#fff; }
.btn-save-inv { background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);color:#bbb;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:0.82rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.2s; }
.btn-save-inv:hover { border-color:rgba(255,255,255,0.2);color:#fff; }
.btn-print-inv { background:#fff;border:none;color:#000;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.2s; }
.btn-print-inv:hover { background:#eee;transform:translateY(-1px); }

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

/* PRINT - CLEAN NO WATERMARK */
@page { margin: 0; size: A4; }
@media print {
    html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 99999; background: #fff; }
    #printArea.dark-print { background: #0a0a0a; }
    .preview-actions, .modal-overlay, .ambient-glow { display: none !important; }
}

@media (max-width: 768px) {
    .main-content { padding:20px 16px; }
    .stat-cards-row { grid-template-columns:1fr 1fr; }
    .form-grid { grid-template-columns:1fr; }
    .payment-info-grid { grid-template-columns:1fr; }
    .items-head { display:none; }
    .item-row { grid-template-columns:1fr; }
}

/* ====== DARK INVOICE PAPER (1:1 Reference) ====== */
.invoice-paper-dark {
    background: #000000;
    color: #ffffff;
    width: 794px;       /* A4 at 96dpi */
    min-height: 1122px; /* A4 height fix for html2pdf rounding */
    max-width: 100%;
    font-family: 'Montserrat', sans-serif;
    border-radius: 0;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.9);
    position: relative;
}
.dark-header-img { width: 100%; height: 240px; object-fit: cover; display: block; }
.dark-header-placeholder { width: 100%; height: 240px; background: linear-gradient(135deg, #111, #222); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #333; font-weight: 900; }
.dark-inv-body { padding: 48px; }
.dark-inv-logo-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.dark-inv-logo { width: 44px; height: 44px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #000; font-size: 1.4rem; font-weight: 900; }
.dark-inv-number { font-size: 0.9rem; font-weight: 700; color: #fff; margin-top: 10px; }
.dark-inv-title { font-size: 4rem; font-weight: 800; color: #fff; letter-spacing: -2px; margin-bottom: 48px; line-height: 1; }
.dark-inv-parties { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-bottom: 48px; }
.dark-inv-party-label { font-size: 0.65rem; color: #666; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 12px; }
.dark-inv-party-name { font-size: 0.95rem; font-weight: 700; color: #fff; margin-bottom: 6px; }
.dark-inv-party-info { font-size: 0.8rem; color: #888; line-height: 1.5; }
.dark-inv-table { width: 100%; border-collapse: collapse; margin-bottom: 32px; }
.dark-inv-table thead tr { border-bottom: 1px solid #222; }
.dark-inv-table th { padding: 12px 0; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #666; text-align: left; }
.dark-inv-table th:not(:first-child) { text-align: right; }
.dark-inv-table tbody tr { border-bottom: 1px solid #1a1a1a; }
.dark-inv-table tbody tr:last-child { border-bottom: 1px solid #222; }
.dark-inv-table td { padding: 20px 0; font-size: 0.9rem; color: #ccc; vertical-align: top; }
.dark-inv-table td:not(:first-child) { text-align: right; }
.dark-inv-table td strong { color: #fff; font-weight: 700; }
.dark-inv-totals { display: flex; justify-content: flex-end; margin-bottom: 48px; }
.dark-inv-totals-box { width: 280px; }
.dark-inv-totals-line { display: flex; justify-content: space-between; font-size: 0.85rem; color: #888; margin-bottom: 12px; }
.dark-inv-totals-div { border: none; border-top: 1px solid #333; margin: 16px 0; }
.dark-inv-totals-grand { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 800; color: #fff; }
.dark-inv-bottom { display: flex; flex-direction: column; gap: 32px; }
.dark-inv-payment-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; color: #666; font-weight: 600; margin-bottom: 12px; }
.dark-inv-payment-line { font-size: 0.8rem; color: #888; margin-bottom: 6px; }
.dark-inv-note-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; color: #666; font-weight: 600; margin-bottom: 12px; }
.dark-inv-note-text { font-size: 0.8rem; color: #888; line-height: 1.5; max-width: 400px; }
.dark-inv-signature { margin-top: 48px; display: flex; justify-content: flex-end; }
.dark-inv-sig-box { text-align: center; }
.dark-inv-sig-line { width: 160px; border-top: 1px solid #444; margin-bottom: 8px; padding-top: 8px; }
.dark-inv-sig-name { font-size: 0.85rem; font-weight: 700; color: #fff; }
.dark-inv-sig-role { font-size: 0.72rem; color: #666; margin-top: 2px; }
.dark-inv-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 32px; }
.dark-inv-status-badge.lunas { background: rgba(74,222,128,0.15); color: #4ade80; border: 1px solid rgba(74,222,128,0.3); }
.dark-inv-status-badge.dp { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
.dark-inv-status-badge.pending { background: rgba(148,163,184,0.1); color: #94a3b8; border: 1px solid rgba(148,163,184,0.2); }
.dark-inv-status-badge.overdue { background: rgba(248,113,113,0.15); color: #f87171; border: 1px solid rgba(248,113,113,0.3); }

/* theme toggle button - kept for legacy CSS */
.btn-theme-toggle { display:none; }

/* Preview Modal Actions */
.preview-btn-dl {
    background: #fff; color: #000; border: none; border-radius: 10px;
    padding: 10px 20px; font-family: inherit; font-size: 0.85rem; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; gap: 8px;
    transition: opacity 0.2s;
}
.preview-btn-dl:hover { opacity: 0.85; }
.preview-btn-print {
    background: transparent; border: 1px solid rgba(255,255,255,0.2); color: #ccc; border-radius: 10px;
    padding: 10px 20px; font-family: inherit; font-size: 0.85rem; font-weight: 600;
    cursor: pointer; display: flex; align-items: center; gap: 8px;
    transition: all 0.2s;
}
.preview-btn-print:hover { border-color: #fff; color: #fff; }
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
                    <div class="stat-num" style="color:var(--neon-main);" id="statTotal">--</div>
                    <div class="stat-label">Total Invoice</div>
                </div>
                <div class="stat-deco">INV</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(78,253,196,0.08);color:var(--neon-sec);"><i class="fas fa-check-double"></i></div>
                <div>
                    <div class="stat-num" style="color:var(--neon-sec);" id="statPaid">--</div>
                    <div class="stat-label">Lunas</div>
                </div>
                <div class="stat-deco">OK</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(255,159,67,0.08);color:var(--neon-orange);"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-num" style="color:var(--neon-orange);" id="statPending">--</div>
                    <div class="stat-label">Pending / DP</div>
                </div>
                <div class="stat-deco">DP</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(192,132,252,0.08);color:var(--neon-purple);"><i class="fas fa-wallet"></i></div>
                <div>
                    <div class="stat-num" style="color:var(--neon-purple);font-size:1.2rem;" id="statRevenue">--</div>
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
                    <div class="form-section-title"><i class="fas fa-image"></i> Center Logo (Watermark)</div>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div id="centerLogoPreviewWrap" style="display:none;border-radius:10px;overflow:hidden;border:1px solid rgba(255,255,255,0.06);position:relative;background:#111;text-align:center;padding:20px;">
                            <img id="centerLogoPreview" src="" style="max-width:150px;max-height:150px;object-fit:contain;display:inline-block;">
                            <button type="button" onclick="removeCenterLogo()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);border:none;color:#fff;border-radius:50%;width:28px;height:28px;cursor:pointer;font-size:0.8rem;" title="Hapus Gambar"><i class="fas fa-times"></i></button>
                        </div>
                        <div id="centerLogoUploadZone" style="border:2px dashed rgba(255,255,255,0.1);border-radius:10px;padding:20px;text-align:center;cursor:pointer;transition:0.2s;" onclick="document.getElementById('f_centerLogo').click()" ondragover="event.preventDefault();this.style.borderColor='var(--neon-main)'" ondragleave="this.style.borderColor='rgba(255,255,255,0.1)'" ondrop="handleCenterLogoDrop(event)">
                            <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem;color:#555;margin-bottom:8px;display:block;"></i>
                            <div style="font-size:0.82rem;color:#666;">Klik atau drag untuk upload logo watermark (PNG)</div>
                        </div>
                        <input type="file" id="f_centerLogo" accept="image/*" style="display:none;" onchange="handleCenterLogo(this)">
                        
                        <div class="form-group" style="margin-top:10px;">
                            <label>Opacity Watermark (<span id="opacityVal">10</span>%)</label>
                            <input type="range" id="f_logoOpacity" min="5" max="100" value="10" style="width:100%;accent-color:var(--neon-main);" oninput="document.getElementById('opacityVal').innerText=this.value; savePaymentInfo();">
                        </div>
                    </div>
                </div>

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
                            <label>Klien / Perusahaan</label>
                            <div style="display:flex;gap:8px;margin-bottom:8px;">
                                <button type="button" id="inv-btn-client" onclick="invSwitchClientType('Client')" style="padding:5px 14px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:rgba(161,255,90,0.1);color:#a1ff5a;font-size:.75rem;font-weight:700;cursor:pointer;"><i class="fas fa-users"></i> Clients</button>
                                <button type="button" id="inv-btn-prospect" onclick="invSwitchClientType('Prospect')" style="padding:5px 14px;border-radius:8px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);color:#666;font-size:.75rem;font-weight:700;cursor:pointer;"><i class="fas fa-binoculars"></i> Prospects</button>
                                <button type="button" onclick="invSwitchClientType('manual')" id="inv-btn-manual" style="padding:5px 14px;border-radius:8px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);color:#666;font-size:.75rem;font-weight:700;cursor:pointer;"><i class="fas fa-keyboard"></i> Manual</button>
                            </div>
                            <input type="hidden" id="f_clientRefType" value="">
                            <input type="hidden" id="f_clientRefId" value="">
                            <select id="f_clientSelect" class="form-input" style="display:none;" onchange="invOnClientSelect(this)">
                                <option value="">-- Pilih Perusahaan --</option>
                            </select>
                            <input type="text" class="form-input" id="f_clientName" placeholder="Ketik nama klien manual..." style="display:none;">
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
                    <div class="form-section-title" style="display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fas fa-university"></i> Info Pembayaran</span>
                        <button type="button" onclick="openBankModal()" style="background:rgba(161,255,90,0.1); border:1px solid rgba(161,255,90,0.2); color:#a1ff5a; padding:6px 12px; border-radius:6px; font-size:0.75rem; cursor:pointer;"><i class="fas fa-cog"></i> Kelola Rekening PT</button>
                    </div>
                    <div class="payment-info-grid">
                        <div>
                            <div class="form-group">
                                <label>Pilih Rekening PT</label>
                                <select class="form-input form-select" id="f_bankSelect" onchange="onBankSelect(this)">
                                    <option value="">-- Pilih Rekening --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Bank Tujuan</label>
                                <input type="text" class="form-input" id="f_bank" readonly style="color:#888; background:rgba(255,255,255,0.02);">
                            </div>
                            <div class="form-group">
                                <label>No. Rekening</label>
                                <input type="text" class="form-input" id="f_rekening" readonly style="color:#888; background:rgba(255,255,255,0.02);">
                            </div>
                            <div class="form-group">
                                <label>Atas Nama</label>
                                <input type="text" class="form-input" id="f_atasNama" readonly style="color:#888; background:rgba(255,255,255,0.02);">
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
                    <div style="margin-top:14px; display:none;">
                        <button type="button" onclick="savePaymentInfo()" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.15);color:#ccc;border-radius:10px;padding:9px 16px;font-family:inherit;font-size:0.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;" onmouseover="this.style.borderColor='rgba(255,255,255,0.4)';this.style.color='#fff';" onmouseout="this.style.borderColor='rgba(255,255,255,0.15)';this.style.color='#ccc';"><i class="fas fa-save"></i> Simpan Permanen (berlaku di semua invoice)</button>
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

        <div class="modal-footer" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:8px;">
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
<div id="previewModal" style="position:fixed;inset:0;z-index:2000;background:#111;display:none;flex-direction:column;align-items:center;overflow-y:auto;padding:24px 20px;">
    <div class="preview-actions" style="display:flex;gap:10px;margin-bottom:24px;">
        <button class="preview-btn-dl" onclick="downloadPDF()"><i class="fas fa-file-download"></i> Download PDF</button>
        <button class="preview-btn-print" onclick="doPrintClean()"><i class="fas fa-print"></i> Print</button>
        <button class="preview-btn-print" onclick="closePreview()"><i class="fas fa-times"></i> Tutup</button>
    </div>
    <div id="invoicePaper"></div>
</div>
<!-- PRINT AREA (bersih, tanpa chrome) -->
<div id="printArea" style="display:none;"></div>

<!-- POPUP -->
<div id="popup" class="popup"><i class="fas fa-check-circle"></i> <span id="popupMsg">Berhasil</span></div>

<!-- BANK MODAL -->
<div id="bankModal" class="modal">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h2 class="modal-title" style="font-size:1.1rem;"><i class="fas fa-university"></i> Kelola Rekening PT</h2>
            <button class="close-modal" onclick="closeBankModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:8px; border:1px solid rgba(255,255,255,0.05); margin-bottom:20px;">
                <input type="hidden" id="f_bankId" value="0">
                <div class="form-group">
                    <label>Bank Tujuan</label>
                    <input type="text" class="form-input" id="f_bankName" placeholder="Contoh: BANK BCA">
                </div>
                <div class="form-group">
                    <label>No. Rekening</label>
                    <input type="text" class="form-input" id="f_bankAcc" placeholder="Contoh: 123-456-789">
                </div>
                <div class="form-group">
                    <label>Atas Nama (PT)</label>
                    <input type="text" class="form-input" id="f_bankPT" placeholder="Contoh: PT. BERSAMA">
                </div>
                <button type="button" onclick="saveBank()" style="background:var(--neon-main); color:#000; border:none; padding:8px 15px; border-radius:6px; font-weight:700; cursor:pointer; font-size:0.8rem; width:100%;"><i class="fas fa-save"></i> Simpan Rekening</button>
                <button type="button" id="btnCancelBank" onclick="resetBankForm()" style="background:transparent; color:#888; border:1px solid #444; padding:8px 15px; border-radius:6px; font-weight:600; cursor:pointer; font-size:0.8rem; width:100%; margin-top:8px; display:none;">Batal Edit</button>
            </div>
            
            <div style="font-size:0.85rem; font-weight:700; color:#fff; margin-bottom:10px;">Daftar Rekening Tersimpan</div>
            <div id="bankList" style="max-height:250px; overflow-y:auto; padding-right:5px; display:flex; flex-direction:column; gap:8px;"></div>
        </div>
    </div>
</div>

<script>
let invoices = [];
let allCompanies = { clients:[], prospects:[] };
let allBanks = [];
let editingId = null;
let payType = 'Lunas';
let centerLogoDataUrl = localStorage.getItem('invCenterLogo') || null;
let centerLogoOpacity = localStorage.getItem('invCenterLogoOp') || 10;

// Saved payment info
const savedPayment = JSON.parse(localStorage.getItem('invPaymentInfo') || '{}');

function loadSavedPaymentInfo() {
    if (savedPayment.bank)     document.getElementById('f_bank').value = savedPayment.bank;
    if (savedPayment.rekening) document.getElementById('f_rekening').value = savedPayment.rekening;
    if (savedPayment.atasNama) document.getElementById('f_atasNama').value = savedPayment.atasNama;
    if (savedPayment.contact)  document.getElementById('f_contact').value = savedPayment.contact;
    if (savedPayment.email)    document.getElementById('f_email').value = savedPayment.email;
    if (savedPayment.sigName)  document.getElementById('f_sigName').value = savedPayment.sigName;
    if (savedPayment.sigRole)  document.getElementById('f_sigRole').value = savedPayment.sigRole;
}

function savePaymentInfo() {
    const info = {
        bank:     document.getElementById('f_bank').value,
        rekening: document.getElementById('f_rekening').value,
        atasNama: document.getElementById('f_atasNama').value,
        contact:  document.getElementById('f_contact').value,
        email:    document.getElementById('f_email').value,
        sigName:  document.getElementById('f_sigName').value,
        sigRole:  document.getElementById('f_sigRole').value,
    };
    localStorage.setItem('invPaymentInfo', JSON.stringify(info));
    localStorage.setItem('invCenterLogoOp', document.getElementById('f_logoOpacity').value);
    Object.assign(savedPayment, info);
    showPopup('success', 'Info tersimpan permanen!');
}

// Init center logo preview on load
document.addEventListener('DOMContentLoaded', () => {
    if (centerLogoDataUrl) {
        document.getElementById('centerLogoPreview').src = centerLogoDataUrl;
        document.getElementById('centerLogoPreviewWrap').style.display = 'block';
        document.getElementById('centerLogoUploadZone').style.display = 'none';
    }
    document.getElementById('f_logoOpacity').value = centerLogoOpacity;
    document.getElementById('opacityVal').innerText = centerLogoOpacity;
    loadBanks();
    loadCompanies();
    loadInvoices();
});

function loadBanks() {
    const fd = new FormData(); fd.append('inv_ajax', 'get_banks');
    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(data => {
        allBanks = data;
        renderBankOptions();
        renderBankList();
    });
}

function renderBankOptions() {
    const sel = document.getElementById('f_bankSelect');
    sel.innerHTML = '<option value="">-- Pilih Rekening --</option>';
    allBanks.forEach(b => {
        sel.innerHTML += `<option value="${b.id}">${b.bank_name} - ${b.account_number} (${b.account_name})</option>`;
    });
}

function onBankSelect(sel) {
    const b = allBanks.find(x => x.id == sel.value);
    if(b) {
        document.getElementById('f_bank').value = b.bank_name;
        document.getElementById('f_rekening').value = b.account_number;
        document.getElementById('f_atasNama').value = b.account_name;
    } else {
        document.getElementById('f_bank').value = '';
        document.getElementById('f_rekening').value = '';
        document.getElementById('f_atasNama').value = '';
    }
}

function openBankModal() { document.getElementById('bankModal').classList.add('active'); resetBankForm(); }
function closeBankModal() { document.getElementById('bankModal').classList.remove('active'); }

function resetBankForm() {
    document.getElementById('f_bankId').value = '0';
    document.getElementById('f_bankName').value = '';
    document.getElementById('f_bankAcc').value = '';
    document.getElementById('f_bankPT').value = '';
    document.getElementById('btnCancelBank').style.display = 'none';
}

function renderBankList() {
    const list = document.getElementById('bankList');
    list.innerHTML = '';
    if(allBanks.length===0) {
        list.innerHTML = '<div style="font-size:0.75rem; color:#666; text-align:center; padding:10px;">Belum ada rekening</div>';
        return;
    }
    allBanks.forEach(b => {
        list.innerHTML += `
            <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); padding:10px 12px; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-weight:700; color:#fff; font-size:0.8rem; margin-bottom:2px;">${b.bank_name} - <span style="color:var(--neon-sec);">${b.account_number}</span></div>
                    <div style="font-size:0.75rem; color:#888;">${b.account_name}</div>
                </div>
                <div style="display:flex; gap:6px;">
                    <button type="button" onclick="editBank(${b.id})" style="background:rgba(255,255,255,0.05); color:#a1ff5a; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;"><i class="fas fa-edit"></i></button>
                    <button type="button" onclick="deleteBank(${b.id})" style="background:rgba(255,255,255,0.05); color:#ff6b6b; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `;
    });
}

function editBank(id) {
    const b = allBanks.find(x => x.id == id);
    if(!b) return;
    document.getElementById('f_bankId').value = b.id;
    document.getElementById('f_bankName').value = b.bank_name;
    document.getElementById('f_bankAcc').value = b.account_number;
    document.getElementById('f_bankPT').value = b.account_name;
    document.getElementById('btnCancelBank').style.display = 'block';
}

function saveBank() {
    const fd = new FormData();
    fd.append('inv_ajax', 'save_bank');
    fd.append('id', document.getElementById('f_bankId').value);
    fd.append('bank_name', document.getElementById('f_bankName').value);
    fd.append('account_number', document.getElementById('f_bankAcc').value);
    fd.append('account_name', document.getElementById('f_bankPT').value);
    
    fetch('', { method:'POST', body:fd }).then(r=>r.json()).then(res => {
        if(res.ok) {
            showPopup('success', 'Rekening tersimpan!');
            resetBankForm();
            loadBanks();
        }
    });
}

function deleteBank(id) {
    if(!confirm('Hapus rekening ini?')) return;
    const fd = new FormData();
    fd.append('inv_ajax', 'delete_bank');
    fd.append('id', id);
    fetch('', { method:'POST', body:fd }).then(r=>r.json()).then(res => {
        if(res.ok) {
            showPopup('success', 'Rekening dihapus!');
            loadBanks();
        }
    });
}

function loadCompanies() {
    const fd = new FormData(); fd.append('inv_ajax', 'get_companies');
    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(data => {
        allCompanies.clients = data.filter(d=>d.type==='Client');
        allCompanies.prospects = data.filter(d=>d.type==='Prospect');
        invSwitchClientType('Client');
    });
}

function invSwitchClientType(type) {
    document.getElementById('inv-btn-client').style.background = type==='Client' ? 'rgba(161,255,90,0.1)' : 'rgba(255,255,255,0.03)';
    document.getElementById('inv-btn-client').style.color = type==='Client' ? '#a1ff5a' : '#666';
    document.getElementById('inv-btn-prospect').style.background = type==='Prospect' ? 'rgba(161,255,90,0.1)' : 'rgba(255,255,255,0.03)';
    document.getElementById('inv-btn-prospect').style.color = type==='Prospect' ? '#a1ff5a' : '#666';
    document.getElementById('inv-btn-manual').style.background = type==='manual' ? 'rgba(161,255,90,0.1)' : 'rgba(255,255,255,0.03)';
    document.getElementById('inv-btn-manual').style.color = type==='manual' ? '#a1ff5a' : '#666';

    const sel = document.getElementById('f_clientSelect');
    const inp = document.getElementById('f_clientName');
    
    if(type==='manual') {
        sel.style.display = 'none';
        inp.style.display = 'block';
        document.getElementById('f_clientRefType').value = '';
        document.getElementById('f_clientRefId').value = '';
    } else {
        sel.style.display = 'block';
        inp.style.display = 'none';
        document.getElementById('f_clientRefType').value = type;
        
        const opts = (type==='Client') ? allCompanies.clients : allCompanies.prospects;
        sel.innerHTML = '<option value="">-- Pilih Perusahaan --</option>';
        opts.forEach(o => {
            sel.innerHTML += `<option value="${o.name}" data-id="${o.ref_id}">${o.name}</option>`;
        });
        invOnClientSelect(sel);
    }
}

function invOnClientSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    if(opt && opt.value) {
        document.getElementById('f_clientName').value = opt.value;
        document.getElementById('f_clientRefId').value = opt.dataset.id || '';
    } else {
        document.getElementById('f_clientName').value = '';
        document.getElementById('f_clientRefId').value = '';
    }
}

function loadInvoices() {
    const fd = new FormData(); fd.append('inv_ajax', 'get_all');
    fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(data => {
        invoices = data.map(i => ({
            id: i.id, no: i.inv_no, client: i.client_name, refType: i.client_ref_type, refId: i.client_ref_id,
            service: i.service_label, date: i.inv_date, subtotal: parseFloat(i.subtotal), ppn: parseFloat(i.ppn),
            total: parseFloat(i.total), status: i.status, bank: i.bank, rekening: i.rekening, atasNama: i.atas_nama,
            payType: i.pay_type, dp1Pct: parseFloat(i.dp1_pct), sigName: i.sig_name, sigRole: i.sig_role,
            contact: i.contact, email: i.email, note: i.note, items: i.items
        }));
        filterInvoices();
    });
}

function handleCenterLogo(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        centerLogoDataUrl = e.target.result;
        localStorage.setItem('invCenterLogo', centerLogoDataUrl);
        document.getElementById('centerLogoPreview').src = centerLogoDataUrl;
        document.getElementById('centerLogoPreviewWrap').style.display = 'block';
        document.getElementById('centerLogoUploadZone').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}
function handleCenterLogoDrop(e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (!file || !file.type.startsWith('image/')) return;
    const dt = new DataTransfer(); dt.items.add(file);
    document.getElementById('f_centerLogo').files = dt.files;
    handleCenterLogo(document.getElementById('f_centerLogo'));
}
function removeCenterLogo() {
    centerLogoDataUrl = null;
    localStorage.removeItem('invCenterLogo');
    document.getElementById('centerLogoPreview').src = '';
    document.getElementById('centerLogoPreviewWrap').style.display = 'none';
    document.getElementById('centerLogoUploadZone').style.display = 'block';
    document.getElementById('f_centerLogo').value = '';
}

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
    document.getElementById('f_bankSelect').value='';
    document.getElementById('f_bank').value='';
    document.getElementById('f_rekening').value='';
    document.getElementById('f_atasNama').value='';
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
    
    // Auto-select bank if exists
    document.getElementById('f_bankSelect').value = '';
    const bMatch = allBanks.find(b => b.bank_name===inv.bank && b.account_number===inv.rekening);
    if(bMatch) document.getElementById('f_bankSelect').value = bMatch.id;
    
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
    document.getElementById('f_clientRefType').value = inv.refType || '';
    document.getElementById('f_clientRefId').value = inv.refId || '';
    if(inv.refType) {
        invSwitchClientType(inv.refType);
        document.getElementById('f_clientSelect').value = inv.client;
    } else {
        invSwitchClientType('manual');
        document.getElementById('f_clientName').value = inv.client;
    }
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
    const id = editingId || ('INV-'+String(Date.now()).slice(-6));
    
    const fd = new FormData();
    fd.append('inv_ajax', 'save');
    fd.append('id', id);
    fd.append('no', no);
    fd.append('client', client);
    fd.append('client_ref_type', document.getElementById('f_clientRefType').value);
    fd.append('client_ref_id', document.getElementById('f_clientRefId').value);
    fd.append('service', items[0]?.name || 'Layanan');
    fd.append('date', document.getElementById('f_invDate').value || new Date().toISOString().split('T')[0]);
    fd.append('subtotal', sub);
    fd.append('ppn', ppn);
    fd.append('total', total);
    fd.append('status', document.getElementById('f_status').value);
    fd.append('bank', document.getElementById('f_bank').value);
    fd.append('rekening', document.getElementById('f_rekening').value);
    fd.append('atasNama', document.getElementById('f_atasNama').value);
    fd.append('payType', payType);
    fd.append('dp1Pct', parseFloat(document.getElementById('f_dp1Pct').value)||50);
    fd.append('sigName', document.getElementById('f_sigName').value);
    fd.append('sigRole', document.getElementById('f_sigRole').value);
    fd.append('contact', document.getElementById('f_contact').value);
    fd.append('email', document.getElementById('f_email').value);
    fd.append('note', document.getElementById('f_note').value);
    fd.append('items_json', JSON.stringify(items));

    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        if(res.ok) {
            showPopup('success', editingId ? 'Invoice berhasil diperbarui!' : 'Invoice berhasil disimpan!');
            closeModal();
            loadInvoices();
        } else {
            showPopup('error', 'Gagal menyimpan invoice.');
        }
    });
}

function deleteInvoice(id){
    if(!confirm('Hapus invoice ini?')) return;
    const fd = new FormData(); fd.append('inv_ajax', 'delete'); fd.append('id', id);
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        if(res.ok) {
            showPopup('success','Invoice dihapus.');
            loadInvoices();
        } else {
            showPopup('error', 'Gagal menghapus.');
        }
    });
}

function buildInvoiceHTML(inv) {
    const ppnVal = inv.subtotal*(inv.ppn/100);
    
    let watermarkHtml = '';
    if (centerLogoDataUrl) {
        watermarkHtml = `<img src="${centerLogoDataUrl}" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:400px;opacity:${centerLogoOpacity/100};pointer-events:none;z-index:0;" alt="Watermark">`;
    }
    let itemsHtml = inv.items.map(item => {
        const subsHtml = item.subs ? `<div style="font-size:0.75rem;color:#666;margin-top:4px;line-height:1.5;">${esc(item.subs).replace(/\n/g,'<br>')}</div>` : '';
        return `<tr>
            <td style="color:#fff;">${esc(item.name)}${subsHtml}</td>
            <td>${item.qty}</td>
            <td>${fmtRp(item.price)}</td>
            <td><strong>${fmtRp(item.qty*item.price)}</strong></td>
        </tr>`;
    }).join('');
    const today = new Date();
    const due = inv.date ? new Date(new Date(inv.date).getTime() + 30*86400000) : today;
    const statusColors = {
        Lunas: 'lunas', DP: 'dp', Pending: 'pending', Overdue: 'overdue'
    };
    const statusCls = statusColors[inv.status] || 'pending';
    return `<div class="invoice-paper-dark" style="position:relative;">
        ${watermarkHtml}
        <div class="dark-inv-body" style="position:relative;z-index:1;">
            <div class="dark-inv-logo-row">
                <img src="/uploads/icon.png" style="width:44px;height:44px;object-fit:contain;border-radius:50%;" alt="HVM Digital">
                <div class="dark-inv-number">HVM-${esc(inv.no)}</div>
            </div>
            <div class="dark-inv-title">INVOICE</div>
            <div class="dark-inv-status-badge ${statusCls}">
                <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                ${esc(inv.status||'Pending')}
            </div>
            <div class="dark-inv-parties">
                <div>
                    <div class="dark-inv-party-label">From</div>
                    <div class="dark-inv-party-name">HVM Digital</div>
                    <div class="dark-inv-party-info">${esc(inv.email)}<br>${esc(inv.contact)}</div>
                </div>
                <div>
                    <div class="dark-inv-party-label">Bill To</div>
                    <div class="dark-inv-party-name">${esc(inv.client)}</div>
                </div>
                <div style="text-align:right;">
                    <div class="dark-inv-party-label">Issued</div>
                    <div class="dark-inv-party-name">${fmtDate(inv.date)}</div>
                    <div class="dark-inv-party-label" style="margin-top:16px;">Due</div>
                    <div class="dark-inv-party-name">${fmtDate(due.toISOString().split('T')[0])}</div>
                </div>
            </div>
            <table class="dark-inv-table">
                <thead><tr>
                    <th style="width:55%;">DESCRIPTION</th>
                    <th>QTY</th>
                    <th>RATE</th>
                    <th>AMOUNT</th>
                </tr></thead>
                <tbody>${itemsHtml}</tbody>
            </table>
            <div class="dark-inv-totals" style="margin-bottom: 24px;">
                <div class="dark-inv-totals-box">
                    <div class="dark-inv-totals-line"><span>Subtotal</span><span>${fmtRp(inv.subtotal)}</span></div>
                    ${inv.ppn?`<div class="dark-inv-totals-line"><span>Tax (${inv.ppn}%)</span><span>${fmtRp(ppnVal)}</span></div>`:''}
                    <hr class="dark-inv-totals-div">
                    <div class="dark-inv-totals-grand"><span>Total</span><span>${fmtRp(inv.total)}</span></div>
                </div>
            </div>
            <div class="dark-inv-bottom" style="display:flex;flex-direction:row;justify-content:space-between;align-items:flex-start;">
                <!-- LEFT COLUMN: Payment, Notes -->
                <div style="flex:1;padding-right:40px;">
                    <div class="dark-inv-payment-label">PAYMENT DETAILS</div>
                    <div class="dark-inv-payment-line">Payment Method: Bank Transfer</div>
                    <div class="dark-inv-payment-line">Bank: ${esc(inv.bank)}</div>
                    <div class="dark-inv-payment-line">Account: ${esc(inv.rekening)}</div>
                    <div class="dark-inv-payment-line">A/N: ${esc(inv.atasNama)}</div>
                    
                    ${inv.note?`<div style="margin-top:30px;"><div class="dark-inv-note-label">NOTES</div><div class="dark-inv-note-text">${esc(inv.note)}</div></div>`:''}
                </div>
                
                <!-- RIGHT COLUMN: QR Code & Signature Data -->
                <div style="width:250px;text-align:right;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent('https://wa.me/6285179982373?text=Halo%20HVM%20Digital,%20saya%20ingin%20konfirmasi%20pembayaran%20untuk%20Invoice%20HVM-' + esc(inv.no))}" style="width:110px;height:110px;border-radius:4px;border:4px solid #fff;background:#fff;margin-bottom:12px;display:inline-block;" alt="QR Code Konfirmasi">
                    
                    <div style="font-size:0.75rem;color:#ccc;line-height:1.5;">
                        <div style="color:#fff;">${esc(inv.sigName||'')} | <span style="color:#a1ff5a;">${esc(inv.sigRole||'')}</span></div>
                        <div style="font-size:0.7rem;margin-top:2px;">${esc(inv.contact||'')} | ${esc(inv.email||'')}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

let _currentInvClient = '';
let _currentInvNo = '';

function previewInvoice(doPrint){
    const no = document.getElementById('f_invNo').value||'—';
    const client = document.getElementById('f_clientName').value||'—';
    const status = document.getElementById('f_status').value||'Pending';
    _currentInvClient = client;
    _currentInvNo = no;
    const items = []; let sub = 0;
    document.querySelectorAll('#itemsBody .item-row').forEach(row => {
        const name = row.querySelector('.item-desc-input').value;
        const subs = row.querySelector('textarea').value;
        const qty = parseFloat(row.querySelectorAll('.item-num-input')[0].value)||0;
        const price = parseFloat(row.querySelectorAll('.item-num-input')[1].value)||0;
        items.push({name,subs,qty,price}); sub += qty*price;
    });
    const ppn = parseFloat(document.getElementById('ppnInput').value)||0;
    const inv = { no, client, status, date: document.getElementById('f_invDate').value||new Date().toISOString().split('T')[0],
        subtotal: sub, ppn, total: sub+sub*(ppn/100),
        bank: document.getElementById('f_bank').value, rekening: document.getElementById('f_rekening').value,
        atasNama: document.getElementById('f_atasNama').value, payType, dp1Pct: parseFloat(document.getElementById('f_dp1Pct').value)||50,
        sigName: document.getElementById('f_sigName').value, sigRole: document.getElementById('f_sigRole').value,
        contact: document.getElementById('f_contact').value, email: document.getElementById('f_email').value,
        note: document.getElementById('f_note').value, items };
    const html = buildInvoiceHTML(inv);
    document.getElementById('invoicePaper').innerHTML = html;
    const pm = document.getElementById('previewModal');
    pm.style.display = 'flex';
    if(doPrint) setTimeout(()=>doPrintClean(), 500);
}

function doPrintClean() {
    const content = document.getElementById('invoicePaper').innerHTML;
    const pa = document.getElementById('printArea');
    pa.innerHTML = content;
    pa.className = 'dark-print';
    pa.style.display = 'block';
    setTimeout(() => {
        window.print();
        setTimeout(() => { pa.style.display = 'none'; pa.innerHTML = ''; }, 1000);
    }, 300);
}

function downloadPDF() {
    const el = document.getElementById('invoicePaper');
    if (!el || !el.innerHTML.trim()) { showPopup('error','Buka preview dulu sebelum download PDF.'); return; }
    const clientName = _currentInvClient || 'Client';
    const filename = `Invoice to ${clientName} - HVM Digital.pdf`;
    showPopup('success', 'Menyiapkan PDF...');
    const opt = {
        margin:       0,
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#000000' },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().from(el).set(opt).save();
}

function viewInvoice(id){
    const inv = invoices.find(i=>i.id===id);
    if(!inv) return;
    _currentInvClient = inv.client;
    _currentInvNo = inv.no;
    document.getElementById('invoicePaper').innerHTML = buildInvoiceHTML(inv);
    document.getElementById('previewModal').style.display = 'flex';
}
function printInvoice(id){ viewInvoice(id); setTimeout(()=>doPrintClean(),600); }
function closePreview(){ document.getElementById('previewModal').style.display='none'; }

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