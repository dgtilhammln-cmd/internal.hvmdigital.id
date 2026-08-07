<?php
// ============================================================
//  HVM FINANCE v2.1 — 100% ERROR-SAFE
//  PHP 7.4+ compatible, no strict mysqli, all @-suppressed
// ============================================================

error_reporting(0);
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if (!isset($_SESSION['admin'])) {
    echo "<script>window.location='/index.php';</script>";
    exit;
}

$allowed = (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');

// --- Helpers ---
function getRomanMonth($m) {
    $map = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',
            7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
    return isset($map[intval($m)]) ? $map[intval($m)] : 'I';
}

function safeQuery($conn, $sql) {
    return @mysqli_query($conn, $sql);
}

function fixCol($conn, $t, $c, $d) {
    $q = @mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    if ($q && mysqli_num_rows($q) == 0) {
        @mysqli_query($conn, "ALTER TABLE `$t` ADD COLUMN `$c` $d");
    }
}

function isProofPDF($f) {
    if (!$f) return false;
    return strtolower(substr($f, -4)) === '.pdf';
}

function auditLog($conn, $action, $table, $record_id, $desc) {
    $user = @mysqli_real_escape_string($conn, isset($_SESSION['admin']) ? (string)$_SESSION['admin'] : 'system');
    $ip   = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $desc = @mysqli_real_escape_string($conn, $desc);
    $rid  = @mysqli_real_escape_string($conn, (string)$record_id);
    @mysqli_query($conn, "INSERT INTO finance_audit_log (action, table_name, record_id, description, user_name, ip)
                          VALUES ('$action','$table','$rid','$desc','$user','$ip')");
}

function handleUpload($name) {
    if (!isset($_FILES[$name]) || $_FILES[$name]['error'] != 0) return null;
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/proofs/';
    if (!file_exists($uploadDir)) @mkdir($uploadDir, 0755, true);
    $tmp  = $_FILES[$name]['tmp_name'];
    $orig = $_FILES[$name]['name'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : 'image/jpeg';
    if ($mime === 'application/pdf' || $ext === 'pdf') {
        $fn = time() . '_' . rand(100,999) . '.pdf';
        return @move_uploaded_file($tmp, $uploadDir . $fn) ? $fn : null;
    }
    $gdOk = function_exists('imagecreatefromjpeg') && function_exists('imagewebp');
    if ($gdOk) {
        $img = null;
        if (in_array($mime, ['image/jpeg','image/jpg']) || in_array($ext, ['jpg','jpeg'])) $img = @imagecreatefromjpeg($tmp);
        elseif ($mime === 'image/png'  || $ext === 'png')  $img = @imagecreatefrompng($tmp);
        elseif ($mime === 'image/gif'  || $ext === 'gif')  $img = @imagecreatefromgif($tmp);
        elseif ($mime === 'image/webp' || $ext === 'webp') $img = @imagecreatefromwebp($tmp);
        if ($img) {
            $w = imagesx($img); $h = imagesy($img);
            if ($w > 1600) {
                $nh = (int)($h * 1600 / $w);
                $r  = imagecreatetruecolor(1600, $nh);
                imagecopyresampled($r, $img, 0,0,0,0, 1600, $nh, $w, $h);
                imagedestroy($img); $img = $r;
            }
            $fn = time() . '_' . rand(100,999) . '.webp';
            if (@imagewebp($img, $uploadDir . $fn, 82)) { imagedestroy($img); return $fn; }
            imagedestroy($img);
        }
    }
    $safeExt = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? $ext : 'jpg';
    $fn = time() . '_' . rand(100,999) . '.' . $safeExt;
    return @move_uploaded_file($tmp, $uploadDir . $fn) ? $fn : null;
}

// --- DB INIT ---
fixCol($conn,'payments','email',       'VARCHAR(100) DEFAULT NULL');
fixCol($conn,'payments','company_name','VARCHAR(150) DEFAULT NULL');
fixCol($conn,'payments','proof',       'VARCHAR(255) DEFAULT NULL');
fixCol($conn,'payments','service_type','VARCHAR(255) DEFAULT NULL');
fixCol($conn,'payments','payment_type','VARCHAR(50)  DEFAULT NULL');
fixCol($conn,'payments','notes',       'TEXT         DEFAULT NULL');
fixCol($conn,'payments','invoice_no',  'VARCHAR(100) DEFAULT NULL');
fixCol($conn,'spendings','proof',      'VARCHAR(255) DEFAULT NULL');
fixCol($conn,'spendings','notes',      'TEXT         DEFAULT NULL');
fixCol($conn,'spendings','vendor',     'VARCHAR(150) DEFAULT NULL');
@mysqli_query($conn,"CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY,type VARCHAR(50),message TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,is_read TINYINT(1) DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
@mysqli_query($conn,"CREATE TABLE IF NOT EXISTS finance_audit_log (id INT AUTO_INCREMENT PRIMARY KEY,action VARCHAR(50),table_name VARCHAR(50),record_id VARCHAR(100),description TEXT,user_name VARCHAR(100),ip VARCHAR(50),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ============================================================
// EXPORT
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $type    = isset($_GET['type'])    ? $_GET['type']    : 'income';
    $d_start = isset($_GET['d_start']) ? $_GET['d_start'] : date('Y-m-01');
    $d_end   = isset($_GET['d_end'])   ? $_GET['d_end']   : date('Y-m-d');
    $format  = isset($_GET['format'])  ? $_GET['format']  : 'pdf';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d_start)) $d_start = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d_end))   $d_end   = date('Y-m-d');
    if (strtotime($d_start) > strtotime($d_end)) { $t=$d_start; $d_start=$d_end; $d_end=$t; }
    $ds = @mysqli_real_escape_string($conn,$d_start);
    $de = @mysqli_real_escape_string($conn,$d_end);

    if ($type === 'income') {
        $sql = "SELECT payment_date AS Date, payment_id AS TransID, company_name AS Client, service_type AS Service, payment_type AS Type, amount AS Nominal FROM payments WHERE payment_date BETWEEN '$ds' AND '$de' ORDER BY payment_date ASC";
    } elseif ($type === 'expense') {
        $sql = "SELECT spending_date AS Date, type AS Category, COALESCE(vendor,'') AS Vendor, detail AS Description, amount AS Nominal FROM spendings WHERE spending_date BETWEEN '$ds' AND '$de' ORDER BY spending_date ASC";
    } else {
        $sql = "SELECT payment_date AS Date, payment_id AS Ref, company_name AS Description, 'INCOME' AS Type, amount AS Nominal FROM payments WHERE payment_date BETWEEN '$ds' AND '$de' UNION ALL SELECT spending_date,COALESCE(id,''),detail,'EXPENSE',amount FROM spendings WHERE spending_date BETWEEN '$ds' AND '$de' ORDER BY Date ASC";
    }
    $res  = safeQuery($conn,$sql);
    $rows = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    $total_sum = 0;
    foreach ($rows as $r) $total_sum += (float)($r['Nominal'] ?? 0);
    $cols = !empty($rows) ? array_keys($rows[0]) : [];

    if ($format === 'excel') {
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=HVM_" . strtoupper($type) . "_" . date('Ymd') . ".xls");
        echo "<html><head><meta charset='utf-8'></head><body>";
        echo "<table border='1' style='border-collapse:collapse; font-family:Arial, sans-serif; font-size:12px;'>";
        if (!empty($rows)) {
            echo "<tr>";
            foreach ($cols as $c) echo "<th style='background-color:#1a1a1a; color:#ffffff; font-weight:bold; padding:10px; text-transform:uppercase; text-align:left; border:1px solid #dddddd;'>" . htmlspecialchars($c) . "</th>";
            echo "</tr>";
            foreach ($rows as $r) {
                echo "<tr>";
                foreach ($r as $k => $v) {
                    if ($k==='Nominal') echo "<td style='padding:8px; text-align:right; border:1px solid #dddddd;'>" . number_format((float)$v,0,',','.') . "</td>";
                    elseif ($k==='Date') echo "<td style='padding:8px; text-align:center; border:1px solid #dddddd;'>" . date('d/m/Y',strtotime($v)) . "</td>";
                    else echo "<td style='padding:8px; border:1px solid #dddddd;'>" . htmlspecialchars((string)$v) . "</td>";
                }
                echo "</tr>";
            }
            $sp = count($cols)-1;
            echo "<tr><td colspan='$sp' style='padding:10px; text-align:right; font-weight:bold; background-color:#f5f5f5; border:1px solid #dddddd;'>TOTAL</td><td style='padding:10px; text-align:right; font-weight:bold; background-color:#f5f5f5; border:1px solid #dddddd;'>" . number_format($total_sum,0,',','.') . "</td></tr>";
        }
        echo "</table></body></html>"; exit;
    }

    // PDF
    header("Content-Type: text/html; charset=UTF-8");
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>HVM Report</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
@page{size:A4 portrait;margin:0}*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',Arial,sans-serif;background:#fff;color:#111;font-size:12px}
.hdr{background:#0a0a0a;color:#fff;padding:30px 40px;display:flex;justify-content:space-between;align-items:center}
.bn{font-size:20px;font-weight:900;letter-spacing:3px}.bs{font-size:9px;color:#666;letter-spacing:2px;margin-top:3px}
.rt{font-size:22px;font-weight:900;letter-spacing:2px;color:#a1ff5a;text-align:right}
.rs{font-size:9px;color:#888;margin-top:4px;text-align:right}
.ab{height:4px;background:linear-gradient(90deg,#a1ff5a,#4efdc4,#0984e3)}
.mr{display:flex;border-bottom:1px solid #eee;margin:0 40px}
.mi{padding:16px 24px 16px 0;flex:1;border-right:1px solid #eee}
.mi:last-child{border-right:none;padding-left:24px;padding-right:0;text-align:right}
.ml{font-size:8px;color:#999;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;margin-bottom:5px}
.mv{font-size:14px;font-weight:800}
.ct{padding:24px 40px 80px}
table{width:100%;border-collapse:collapse}
thead tr{background:#0a0a0a}
th{padding:11px 9px;text-align:left;font-size:8px;text-transform:uppercase;letter-spacing:1px;font-weight:800;color:#fff}
td{padding:10px 9px;font-size:10px;border-bottom:1px solid #f0f0f0;color:#333;vertical-align:middle}
tr:nth-child(even) td{background:#fafafa}
.nr{text-align:right;font-family:'Courier New',monospace;font-weight:700}
.tr2 td{background:#0a0a0a!important;color:#fff;font-weight:900;border:none}
.tr2 .nr{color:#a1ff5a;font-size:12px}
.bi{display:inline-block;padding:2px 7px;border-radius:4px;font-size:8px;font-weight:700;background:rgba(78,253,196,.15);color:#00b894}
.be{display:inline-block;padding:2px 7px;border-radius:4px;font-size:8px;font-weight:700;background:rgba(255,90,90,.15);color:#e74c3c}
.sa{display:flex;justify-content:space-between;margin-top:50px}
.sb{text-align:center;width:170px}
.sl{font-size:8px;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:48px}
.sn{border-top:1.5px solid #000;padding-top:7px}
.snm{font-weight:900;font-size:10px;text-transform:uppercase}
.sr{font-size:8px;color:#888;margin-top:3px}
.ft{position:fixed;bottom:0;left:0;right:0;background:#0a0a0a;color:#888;padding:10px 40px;display:flex;justify-content:space-between;font-size:8px;letter-spacing:1px}
.nd{text-align:center;padding:50px;color:#bbb}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
<div id="pdfContent">
<div class="hdr">
    <div><div class="bn">HVM DIGITAL</div><div class="bs">INTERNAL ACCOUNTING SYSTEM</div></div>
    <div><div class="rt"><?php echo htmlspecialchars(strtoupper($type)); ?> STATEMENT</div><div class="rs">Generated: <?php echo date('d M Y, H:i'); ?> WIB</div></div>
</div>
<div class="ab"></div>
<div class="mr">
    <div class="mi"><div class="ml">Period</div><div class="mv"><?php echo date('d M Y',strtotime($d_start)); ?> &mdash; <?php echo date('d M Y',strtotime($d_end)); ?></div></div>
    <div class="mi"><div class="ml">Total Records</div><div class="mv"><?php echo count($rows); ?> Transaksi</div></div>
    <div class="mi"><div class="ml">Total Amount</div><div class="mv">Rp <?php echo number_format($total_sum,0,',','.'); ?></div></div>
</div>
<div class="ct">
<table>
<thead><tr>
<?php foreach ($cols as $col): ?>
<th <?php echo ($col==='Nominal')?'style="text-align:right"':''; ?>><?php echo htmlspecialchars($col); ?></th>
<?php endforeach; ?>
</tr></thead>
<tbody>
<?php if (empty($rows)): ?>
<tr><td colspan="<?php echo count($cols); ?>" class="nd">Tidak ada data untuk periode ini.</td></tr>
<?php else: foreach ($rows as $row): ?>
<tr>
<?php foreach ($row as $k => $v): ?>
<?php if ($k==='Nominal'): ?><td class="nr">Rp <?php echo number_format((float)$v,0,',','.'); ?></td>
<?php elseif ($k==='Date'): ?><td><?php echo date('d/m/Y',strtotime($v)); ?></td>
<?php elseif ($k==='Type'): ?><td><span class="<?php echo ($v==='INCOME')?'bi':'be'; ?>"><?php echo htmlspecialchars((string)$v); ?></span></td>
<?php else: ?><td><?php echo htmlspecialchars((string)($v !== null ? $v : '-')); ?></td>
<?php endif; endforeach; ?>
</tr>
<?php endforeach; ?>
<tr class="tr2">
<td colspan="<?php echo max(1,count($cols)-1); ?>" style="text-align:right;padding-right:9px;letter-spacing:2px">TOTAL STATEMENT</td>
<td class="nr">Rp <?php echo number_format($total_sum,0,',','.'); ?></td>
</tr>
<?php endif; ?>
</tbody>
</table>
<div class="sa">
    <div class="sb"><div class="sl">Prepared By,</div><div class="sn"><div class="snm">Evi Kurnia Putri</div><div class="sr">Creative Operations</div></div></div>
    <div class="sb"><div class="sl">Verified By,</div><div class="sn"><div class="snm">Ilham Maulana</div><div class="sr">Managing Director</div></div></div>
</div>
</div>
</div>
<div class="ft"><span>CONFIDENTIAL &mdash; INTERNAL USE ONLY</span><span>HVM DIGITAL &copy; <?php echo date('Y'); ?></span><span>Printed: <?php echo date('d/m/Y H:i'); ?></span></div>
</div>
<script>
    window.onload = function() {
        var el = document.getElementById('pdfContent');
        var opt = {
            margin: 0,
            filename: 'HVM_<?php echo strtoupper($type); ?>_<?php echo date('Ymd'); ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().from(el).set(opt).save().then(function() {
            setTimeout(() => { window.close(); }, 1000); // Close window after download
        });
    };
</script>
</body></html>
<?php exit; }

// ============================================================
// CRUD
// ============================================================
if (isset($_POST['add_payment'])) {
    try {
        $date    = (!empty($_POST['payment_date'])) ? $_POST['payment_date'] : date('Y-m-d');
        $m       = (int)date('n', strtotime($date));
        $y       = (int)date('Y', strtotime($date));
        $rom     = getRomanMonth($m);
        $prefix  = "HVM/$rom/$y/";
        $ep      = @mysqli_real_escape_string($conn,$prefix);
        $qc      = safeQuery($conn,"SELECT payment_id FROM payments WHERE payment_id LIKE '$ep%' ORDER BY payment_id DESC LIMIT 1");
        if ($qc && mysqli_num_rows($qc) > 0) {
            $li  = mysqli_fetch_assoc($qc)['payment_id'];
            $seq = str_pad((int)substr($li,-3)+1, 3, '0', STR_PAD_LEFT);
        } else { $seq = "001"; }
        $id = $prefix . $seq;
        $email      = @mysqli_real_escape_string($conn, trim(isset($_POST['email'])          ? $_POST['email']          : ''));
        $company    = @mysqli_real_escape_string($conn, trim(isset($_POST['company_name'])   ? $_POST['company_name']   : ''));
        $service    = (isset($_POST['service_type']) && is_array($_POST['service_type'])) ? implode(', ',$_POST['service_type']) : 'General';
        $amount     = (int)preg_replace('/[^0-9]/','', isset($_POST['amount'])       ? $_POST['amount']       : '0');
        $ptype      = @mysqli_real_escape_string($conn, isset($_POST['payment_type']) ? $_POST['payment_type'] : 'New Client');
        $notes      = @mysqli_real_escape_string($conn, trim(isset($_POST['notes'])      ? $_POST['notes']      : ''));
        $invoice_no = @mysqli_real_escape_string($conn, trim(isset($_POST['invoice_no']) ? $_POST['invoice_no'] : ''));
        $proof      = handleUpload('proof_file');
        $psql       = ($proof!==null) ? "'".@mysqli_real_escape_string($conn,$proof)."'" : "NULL";
        $ok = safeQuery($conn,"INSERT INTO payments (payment_id,email,company_name,amount,payment_date,service_type,payment_type,proof,notes,invoice_no) VALUES ('$id','$email','$company','$amount','$date','$service','$ptype',$psql,'$notes','$invoice_no')");
        if ($ok) {
            @mysqli_query($conn,"INSERT INTO notifications (type,message) VALUES ('income','Masuk Rp ".number_format($amount)." dari $company')");
            auditLog($conn,'INSERT','payments',$id,"Tambah pemasukan Rp ".number_format($amount)." dari $company");
            $_SESSION['popup'] = "Pemasukan Rp ".number_format($amount)." berhasil dicatat!";
        } else { $_SESSION['popup_err'] = "Gagal simpan: ".@mysqli_error($conn); }
        header("Location: index.php?view=income&start=".date('Y-m-01',strtotime($date))."&end=".date('Y-m-t',strtotime($date))); exit;
    } catch (Exception $e) { $_SESSION['popup_err']=$e->getMessage(); header("Location: index.php?view=income"); exit; }
}

if (isset($_POST['add_spending'])) {
    try {
        $stype  = @mysqli_real_escape_string($conn, isset($_POST['type'])         ? $_POST['type']         : 'operasional');
        $detail = @mysqli_real_escape_string($conn, trim(isset($_POST['detail'])  ? $_POST['detail']  : ''));
        $vendor = @mysqli_real_escape_string($conn, trim(isset($_POST['vendor'])  ? $_POST['vendor']  : ''));
        $amount = (int)preg_replace('/[^0-9]/','', isset($_POST['amount'])        ? $_POST['amount']        : '0');
        $date   = !empty($_POST['spending_date']) ? $_POST['spending_date'] : date('Y-m-d');
        $notes  = @mysqli_real_escape_string($conn, trim(isset($_POST['notes'])   ? $_POST['notes']   : ''));
        $proof  = handleUpload('proof_file');
        $psql   = ($proof!==null) ? "'".@mysqli_real_escape_string($conn,$proof)."'" : "NULL";
        $ok = safeQuery($conn,"INSERT INTO spendings (type,detail,vendor,amount,spending_date,proof,notes) VALUES ('$stype','$detail','$vendor','$amount','$date',$psql,'$notes')");
        if ($ok) {
            $nid = (int)@mysqli_insert_id($conn);
            @mysqli_query($conn,"INSERT INTO notifications (type,message) VALUES ('expense','Keluar Rp ".number_format($amount)." untuk $stype')");
            auditLog($conn,'INSERT','spendings',$nid,"Tambah pengeluaran Rp ".number_format($amount)." $stype: $detail");
            $_SESSION['popup'] = "Pengeluaran Rp ".number_format($amount)." berhasil dicatat!";
        } else { $_SESSION['popup_err'] = "Gagal simpan: ".@mysqli_error($conn); }
        header("Location: index.php?view=expense&start=".date('Y-m-01',strtotime($date))."&end=".date('Y-m-t',strtotime($date))); exit;
    } catch (Exception $e) { $_SESSION['popup_err']=$e->getMessage(); header("Location: index.php?view=expense"); exit; }
}

if (isset($_POST['edit_payment'])) {
    try {
        $id         = @mysqli_real_escape_string($conn, $_POST['payment_id']);
        $date       = (!empty($_POST['payment_date'])) ? $_POST['payment_date'] : date('Y-m-d');
        $email      = @mysqli_real_escape_string($conn, trim(isset($_POST['email']) ? $_POST['email'] : ''));
        $company    = @mysqli_real_escape_string($conn, trim(isset($_POST['company_name']) ? $_POST['company_name'] : ''));
        $service    = (isset($_POST['service_type']) && is_array($_POST['service_type'])) ? implode(', ',$_POST['service_type']) : 'General';
        $amount     = (int)preg_replace('/[^0-9]/','', isset($_POST['amount']) ? $_POST['amount'] : '0');
        $ptype      = @mysqli_real_escape_string($conn, isset($_POST['payment_type']) ? $_POST['payment_type'] : 'New Client');
        $notes      = @mysqli_real_escape_string($conn, trim(isset($_POST['notes']) ? $_POST['notes'] : ''));
        $invoice_no = @mysqli_real_escape_string($conn, trim(isset($_POST['invoice_no']) ? $_POST['invoice_no'] : ''));
        $proof      = handleUpload('proof_file');
        
        $upd_proof = ($proof !== null) ? ", proof='".@mysqli_real_escape_string($conn,$proof)."'" : "";
        $ok = safeQuery($conn,"UPDATE payments SET email='$email', company_name='$company', amount='$amount', payment_date='$date', service_type='$service', payment_type='$ptype', notes='$notes', invoice_no='$invoice_no' $upd_proof WHERE payment_id='$id'");
        if ($ok) {
            auditLog($conn,'UPDATE','payments',$id,"Edit pemasukan Rp ".number_format($amount)." dari $company");
            $_SESSION['popup'] = "Pemasukan berhasil diubah!";
        } else { $_SESSION['popup_err'] = "Gagal edit: ".@mysqli_error($conn); }
        header("Location: index.php?view=income&start=".date('Y-m-01',strtotime($date))."&end=".date('Y-m-t',strtotime($date))); exit;
    } catch (Exception $e) { $_SESSION['popup_err']=$e->getMessage(); header("Location: index.php?view=income"); exit; }
}

if (isset($_POST['edit_spending'])) {
    try {
        $id     = (int)$_POST['spending_id'];
        $stype  = @mysqli_real_escape_string($conn, isset($_POST['type']) ? $_POST['type'] : 'operasional');
        $detail = @mysqli_real_escape_string($conn, trim(isset($_POST['detail']) ? $_POST['detail'] : ''));
        $vendor = @mysqli_real_escape_string($conn, trim(isset($_POST['vendor']) ? $_POST['vendor'] : ''));
        $amount = (int)preg_replace('/[^0-9]/','', isset($_POST['amount']) ? $_POST['amount'] : '0');
        $date   = !empty($_POST['spending_date']) ? $_POST['spending_date'] : date('Y-m-d');
        $notes  = @mysqli_real_escape_string($conn, trim(isset($_POST['notes']) ? $_POST['notes'] : ''));
        $proof  = handleUpload('proof_file');
        
        $upd_proof = ($proof !== null) ? ", proof='".@mysqli_real_escape_string($conn,$proof)."'" : "";
        $ok = safeQuery($conn,"UPDATE spendings SET type='$stype', detail='$detail', vendor='$vendor', amount='$amount', spending_date='$date', notes='$notes' $upd_proof WHERE id='$id'");
        if ($ok) {
            auditLog($conn,'UPDATE','spendings',$id,"Edit pengeluaran Rp ".number_format($amount)." $stype: $detail");
            $_SESSION['popup'] = "Pengeluaran berhasil diubah!";
        } else { $_SESSION['popup_err'] = "Gagal edit: ".@mysqli_error($conn); }
        header("Location: index.php?view=expense&start=".date('Y-m-01',strtotime($date))."&end=".date('Y-m-t',strtotime($date))); exit;
    } catch (Exception $e) { $_SESSION['popup_err']=$e->getMessage(); header("Location: index.php?view=expense"); exit; }
}

if (isset($_GET['del_pay'])) {
    $id  = @mysqli_real_escape_string($conn,$_GET['del_pay']);
    $qr  = safeQuery($conn,"SELECT company_name,amount,proof FROM payments WHERE payment_id='$id' LIMIT 1");
    $row = ($qr) ? mysqli_fetch_assoc($qr) : null;
    if ($row) {
        if (!empty($row['proof'])) @unlink($_SERVER['DOCUMENT_ROOT'].'/uploads/proofs/'.$row['proof']);
        safeQuery($conn,"DELETE FROM payments WHERE payment_id='$id'");
        auditLog($conn,'DELETE','payments',$id,"Hapus ".$row['company_name']." Rp ".number_format($row['amount']));
    }
    header("Location: index.php?view=income"); exit;
}

if (isset($_GET['del_spend'])) {
    $id  = (int)$_GET['del_spend'];
    $qr  = safeQuery($conn,"SELECT type,amount,proof FROM spendings WHERE id='$id' LIMIT 1");
    $row = ($qr) ? mysqli_fetch_assoc($qr) : null;
    if ($row) {
        if (!empty($row['proof'])) @unlink($_SERVER['DOCUMENT_ROOT'].'/uploads/proofs/'.$row['proof']);
        safeQuery($conn,"DELETE FROM spendings WHERE id='$id'");
        auditLog($conn,'DELETE','spendings',$id,"Hapus ".$row['type']." Rp ".number_format($row['amount']));
    }
    header("Location: index.php?view=expense"); exit;
}

// ============================================================
// VIEW DATA
// ============================================================
$view       = isset($_GET['view'])  ? $_GET['view']  : 'income';
$search     = @mysqli_real_escape_string($conn, isset($_GET['q'])     ? $_GET['q']     : '');
$date_start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$date_end   = isset($_GET['end'])   ? $_GET['end']   : date('Y-m-t');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_start)) $date_start = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_end))   $date_end   = date('Y-m-t');
$ds = @mysqli_real_escape_string($conn,$date_start);
$de = @mysqli_real_escape_string($conn,$date_end);

// Stats
$ri = safeQuery($conn,"SELECT SUM(amount) as t FROM payments  WHERE payment_date  BETWEEN '$ds' AND '$de'");
$ro = safeQuery($conn,"SELECT SUM(amount) as t FROM spendings WHERE spending_date BETWEEN '$ds' AND '$de'");
$total_in  = ($ri && ($tmp=mysqli_fetch_assoc($ri)))  ? (float)($tmp['t']??0) : 0.0;
$total_out = ($ro && ($tmp=mysqli_fetch_assoc($ro)))  ? (float)($tmp['t']??0) : 0.0;
$alloc_aset  = $total_in * 0.20;
$alloc_gaji  = $total_in * 0.10;
$real_profit = ($total_in - $total_out) - $alloc_aset - $alloc_gaji;
$profit_margin = ($total_in > 0) ? round(($real_profit/$total_in)*100, 1) : 0;

$ric = safeQuery($conn,"SELECT COUNT(*) as c FROM payments  WHERE payment_date  BETWEEN '$ds' AND '$de'");
$roc = safeQuery($conn,"SELECT COUNT(*) as c FROM spendings WHERE spending_date BETWEEN '$ds' AND '$de'");
$count_in  = ($ric && ($tmp=mysqli_fetch_assoc($ric))) ? (int)($tmp['c']??0) : 0;
$count_out = ($roc && ($tmp=mysqli_fetch_assoc($roc))) ? (int)($tmp['c']??0) : 0;

// Chart 7 hari
$chart_labels=[]; $chart_income=[]; $chart_expense=[];
for ($i=6; $i>=0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days", strtotime($date_end)));
    $chart_labels[] = date('d/m', strtotime($day));
    if ($day < $date_start) { $chart_income[]=0; $chart_expense[]=0; continue; }
    $de2 = @mysqli_real_escape_string($conn,$day);
    $ci2 = safeQuery($conn,"SELECT SUM(amount) as t FROM payments  WHERE payment_date ='$de2'");
    $ce2 = safeQuery($conn,"SELECT SUM(amount) as t FROM spendings WHERE spending_date='$de2'");
    $chart_income[]  = ($ci2 && ($tmp=mysqli_fetch_assoc($ci2))) ? (float)($tmp['t']??0) : 0.0;
    $chart_expense[] = ($ce2 && ($tmp=mysqli_fetch_assoc($ce2))) ? (float)($tmp['t']??0) : 0.0;
}

// Top clients
$top_clients = [];
$qt = safeQuery($conn,"SELECT company_name, SUM(amount) as total, COUNT(*) as trx FROM payments WHERE payment_date BETWEEN '$ds' AND '$de' GROUP BY company_name ORDER BY total DESC LIMIT 5");
if ($qt) while ($r=mysqli_fetch_assoc($qt)) $top_clients[] = $r;

// Service breakdown
$service_breakdown = [];
$qs = safeQuery($conn,"SELECT service_type, SUM(amount) as total FROM payments WHERE payment_date BETWEEN '$ds' AND '$de' GROUP BY service_type ORDER BY total DESC");
if ($qs) {
    while ($r=mysqli_fetch_assoc($qs)) {
        $svcs = explode(', ',$r['service_type']);
        foreach ($svcs as $sv) {
            $sv = trim($sv);
            if ($sv==='') continue;
            $service_breakdown[$sv] = (isset($service_breakdown[$sv]) ? $service_breakdown[$sv] : 0) + (float)$r['total'];
        }
    }
    arsort($service_breakdown);
}

// Expense breakdown
$expense_breakdown = [];
$qe = safeQuery($conn,"SELECT type, SUM(amount) as total FROM spendings WHERE spending_date BETWEEN '$ds' AND '$de' GROUP BY type ORDER BY total DESC");
if ($qe) while ($r=mysqli_fetch_assoc($qe)) $expense_breakdown[$r['type']] = $r['total'];

// Audit
$audit_rows = [];
$qa = safeQuery($conn,"SELECT * FROM finance_audit_log ORDER BY created_at DESC LIMIT 20");
if ($qa) while ($r=mysqli_fetch_assoc($qa)) $audit_rows[] = $r;

// Main query
if ($view === 'income') {
    $query = "SELECT * FROM payments WHERE (payment_id LIKE '%$search%' OR company_name LIKE '%$search%' OR email LIKE '%$search%') AND payment_date BETWEEN '$ds' AND '$de' ORDER BY payment_date DESC";
} elseif ($view === 'expense') {
    $query = "SELECT * FROM spendings WHERE (detail LIKE '%$search%' OR type LIKE '%$search%' OR vendor LIKE '%$search%') AND spending_date BETWEEN '$ds' AND '$de' ORDER BY spending_date DESC";
} else {
    $query = "SELECT * FROM payments WHERE payment_date BETWEEN '$ds' AND '$de' ORDER BY payment_date DESC";
}
$result = safeQuery($conn,$query);

// Clients autocomplete
$clients_data = [];
$qcl = safeQuery($conn,"SELECT email, company_name FROM clients WHERE status='Active' ORDER BY company_name ASC");
if ($qcl) while ($c=mysqli_fetch_assoc($qcl)) $clients_data[] = $c;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance &mdash; HVM Digital</title>
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="ambient-glow glow-1"></div>
<div class="ambient-glow glow-2"></div>
<div class="dashboard-wrapper">
    <?php include '../sidebar.php'; ?>
    <main class="main-content">

        <div class="page-headline">
            <div>
                <h1>Finance Overview</h1>
                <p>Track cashflow, allocations, mutations &amp; analytics.</p>
            </div>
            <div class="headline-actions">
                <button class="btn-icon-outline" onclick="openModal('auditModal')">
                    <i class="fas fa-history"></i> Audit Trail
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['popup_err'])): ?>
        <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['popup_err']); unset($_SESSION['popup_err']); ?></div>
        <?php endif; ?>

        <?php if (!$allowed): ?>
        <div class="forbidden-box">
            <div class="forbidden-icon"><i class="fas fa-lock"></i></div>
            <div class="forbidden-text">DALAM PERBAIKAN</div>
            <div class="forbidden-sub">Halaman ini sedang dalam proses perbaikan.</div>
            <a href="/dashboard/" class="btn-neon" style="text-decoration:none;">KEMBALI KE DASHBOARD</a>
        </div>
        <?php else: ?>

        <div class="stats-grid">
            <div class="glass-card">
                <i class="fas fa-arrow-up card-bg-icon icon-in"></i>
                <div class="stat-label">Total Income</div>
                <div class="stat-value val-in">Rp <?php echo number_format($total_in/1000000,2); ?> jt</div>
                <div class="stat-sub"><?php echo $count_in; ?> transaksi</div>
            </div>
            <div class="glass-card">
                <i class="fas fa-arrow-down card-bg-icon icon-out"></i>
                <div class="stat-label">Total Expense</div>
                <div class="stat-value val-out">Rp <?php echo number_format($total_out/1000000,2); ?> jt</div>
                <div class="stat-sub"><?php echo $count_out; ?> transaksi</div>
            </div>

            <div class="glass-card <?php echo ($real_profit<0)?'card-negative':''; ?>">
                <i class="fas fa-wallet card-bg-icon icon-net"></i>
                <div class="stat-label">Net Profit</div>
                <div class="stat-value <?php echo ($real_profit<0)?'val-out':'val-net'; ?>">Rp <?php echo number_format($real_profit/1000000,2); ?> jt</div>
                <div class="stat-sub">
                    <span class="profit-badge <?php echo ($profit_margin>=0)?'positive':'negative'; ?>">
                        <i class="fas fa-<?php echo ($profit_margin>=0)?'arrow-up':'arrow-down'; ?>"></i>
                        <?php echo abs($profit_margin); ?>% margin
                    </span>
                </div>
            </div>
        </div>

        <div class="analytics-grid">
            <div class="glass-card chart-card">
                <div class="chart-header">
                    <div><div class="chart-title">Cashflow 7 Hari Terakhir</div><div class="chart-sub">Income vs Expense harian</div></div>
                    <div class="chart-legend">
                        <span class="leg-dot" style="background:var(--neon-sec)"></span><span>Income</span>&nbsp;
                        <span class="leg-dot" style="background:var(--neon-red)"></span><span>Expense</span>
                    </div>
                </div>
                <div class="chart-wrap"><canvas id="cashflowChart"></canvas></div>
            </div>

            <div class="glass-card">
                <div class="chart-title" style="margin-bottom:15px">Top 5 Clients</div>
                <?php if (empty($top_clients)): ?>
                <div class="empty-state"><i class="fas fa-chart-bar"></i><p>Belum ada data</p></div>
                <?php else:
                    $mx = (float)max(array_column($top_clients,'total'));
                    if ($mx<=0) $mx=1;
                    foreach ($top_clients as $ci=>$cl):
                ?>
                <div class="top-item">
                    <div class="top-rank"><?php echo $ci+1; ?></div>
                    <div class="top-info">
                        <div class="top-name"><?php echo htmlspecialchars($cl['company_name']); ?></div>
                        <div class="progress-track"><div class="progress-fill" style="width:<?php echo round(((float)$cl['total']/$mx)*100); ?>%"></div></div>
                    </div>
                    <div class="top-amount val-in">Rp <?php echo number_format((float)$cl['total']/1000000,1); ?>jt</div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="glass-card">
                <div class="chart-title" style="margin-bottom:15px">Revenue per Layanan</div>
                <?php
                $sc = ['var(--neon-sec)','var(--neon-main)','var(--neon-orange)','#a29bfe','#fd79a8'];
                if (empty($service_breakdown)): ?>
                <div class="empty-state"><i class="fas fa-tags"></i><p>Belum ada data</p></div>
                <?php else:
                    $mxs = (float)max(array_values($service_breakdown)); if ($mxs<=0) $mxs=1;
                    $sci=0; foreach ($service_breakdown as $sn=>$sv):
                        $col=$sc[$sci%count($sc)];
                ?>
                <div class="top-item">
                    <div class="svc-dot" style="background:<?php echo $col; ?>"></div>
                    <div class="top-info">
                        <div class="top-name"><?php echo htmlspecialchars($sn); ?></div>
                        <div class="progress-track"><div class="progress-fill" style="width:<?php echo round(((float)$sv/$mxs)*100); ?>%;background:<?php echo $col; ?>"></div></div>
                    </div>
                    <div class="top-amount" style="color:<?php echo $col; ?>">Rp <?php echo number_format((float)$sv/1000000,1); ?>jt</div>
                </div>
                <?php $sci++; endforeach; endif; ?>
            </div>

            <div class="glass-card">
                <div class="chart-title" style="margin-bottom:15px">Pengeluaran per Kategori</div>
                <?php
                $ec = ['var(--neon-red)','var(--neon-orange)','#a29bfe','#fd79a8','#74b9ff'];
                if (empty($expense_breakdown)): ?>
                <div class="empty-state"><i class="fas fa-receipt"></i><p>Belum ada data</p></div>
                <?php else:
                    $mxe = (float)max(array_values($expense_breakdown)); if ($mxe<=0) $mxe=1;
                    $exi=0; foreach ($expense_breakdown as $en=>$ev):
                        $col=$ec[$exi%count($ec)];
                ?>
                <div class="top-item">
                    <div class="svc-dot" style="background:<?php echo $col; ?>"></div>
                    <div class="top-info">
                        <div class="top-name"><?php echo htmlspecialchars(ucfirst($en)); ?></div>
                        <div class="progress-track"><div class="progress-fill" style="width:<?php echo round(((float)$ev/$mxe)*100); ?>%;background:<?php echo $col; ?>"></div></div>
                    </div>
                    <div class="top-amount val-out">Rp <?php echo number_format((float)$ev/1000000,1); ?>jt</div>
                </div>
                <?php $exi++; endforeach; endif; ?>
            </div>
        </div>

        <div class="action-container">
            <div class="tab-group">
                <button class="tab-btn <?php echo ($view==='income')?'active':''; ?>" onclick="window.location='?view=income&start=<?php echo $date_start; ?>&end=<?php echo $date_end; ?>'"><i class="fas fa-arrow-up"></i> Income</button>
                <button class="tab-btn expense <?php echo ($view==='expense')?'active':''; ?>" onclick="window.location='?view=expense&start=<?php echo $date_start; ?>&end=<?php echo $date_end; ?>'"><i class="fas fa-arrow-down"></i> Expense</button>

            </div>
            <form method="GET" class="filter-group">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                <?php if ($view !== 'mutasi'): ?>
                <div class="search-wrap">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="q" class="search-input" placeholder="Cari..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                </div>
                <?php endif; ?>
                <input type="date" name="start" class="date-select" value="<?php echo $date_start; ?>" onchange="this.form.submit()">
                <span class="date-sep">&#8594;</span>
                <input type="date" name="end" class="date-select" value="<?php echo $date_end; ?>" onchange="this.form.submit()">
            </form>
            <div style="display:flex;gap:10px;flex-shrink:0;">
                <button class="btn-grad blue" onclick="openModal('downloadModal')"><i class="fas fa-download"></i> Export</button>
                <?php if ($view==='income'): ?><button class="btn-grad" onclick="openModal('incomeModal')"><i class="fas fa-plus"></i> Add Income</button>
                <?php elseif ($view==='expense'): ?><button class="btn-grad red" onclick="openModal('expenseModal')"><i class="fas fa-minus"></i> Add Expense</button><?php endif; ?>
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                    <?php if ($view==='income'): ?>
                        <tr><th>Date</th><th>Trans ID</th><th>Client</th><th>Service</th><th>Type</th><th class="align-right">Amount</th><th class="align-center">Proof</th><th>Aksi</th></tr>
                    <?php else: ?>
                        <tr><th>Date</th><th>Kategori</th><th>Vendor</th><th>Detail</th><th class="align-right">Amount</th><th class="align-center">Proof</th><th>Aksi</th></tr>
                    <?php endif; ?>
                    </thead>
                    <tbody>
                    <?php
                    $rn = 0;
                    $has = ($result && mysqli_num_rows($result) > 0);
                    if (!$has): ?>
                        <tr><td colspan="8"><div class="empty-state-table"><i class="fas fa-inbox"></i><p>Tidak ada data untuk periode ini</p></div></td></tr>
                    <?php else: while ($row = mysqli_fetch_assoc($result)): $rn++; ?>
                    <tr class="fade-row" style="animation-delay:<?php echo $rn*0.03; ?>s">
                        <?php if ($view==='income'): ?>
                            <td><?php echo date('d M Y',strtotime($row['payment_date'])); ?></td>
                            <td><span class="tx-id"><?php echo htmlspecialchars($row['payment_id']); ?></span></td>
                            <td>
                                <div class="client-name"><?php echo htmlspecialchars($row['company_name']); ?></div>
                                <?php if (!empty($row['email'])): ?><div class="client-email"><?php echo htmlspecialchars($row['email']); ?></div><?php endif; ?>
                                <?php if (!empty($row['notes'])): ?><span class="row-note" title="<?php echo htmlspecialchars($row['notes']); ?>"><i class="fas fa-sticky-note"></i></span><?php endif; ?>
                            </td>
                            <td><?php $svcs=explode(', ',$row['service_type']); foreach($svcs as $sv){ $sv=trim($sv); if($sv) echo '<span class="badge service">'.htmlspecialchars($sv).'</span> '; } ?></td>
                            <td>
                                <?php $pt=isset($row['payment_type'])?$row['payment_type']:''; $ptc='badge-addon'; if($pt==='Recurring')$ptc='badge-recurring'; elseif($pt==='New Client')$ptc='badge-new'; ?>
                                <span class="<?php echo $ptc; ?>"><?php echo htmlspecialchars($pt); ?></span>
                            </td>
                            <td class="align-right val-in fw-bold">+ Rp <?php echo number_format($row['amount']); ?></td>
                            <td class="align-center">
                                <?php if (!empty($row['proof'])): ?>
                                <button class="btn-icon" onclick="viewProof('/uploads/proofs/<?php echo htmlspecialchars($row['proof']); ?>','<?php echo htmlspecialchars($row['payment_id']); ?>')" title="Lihat"><i class="fas fa-<?php echo isProofPDF($row['proof'])?'file-pdf':'image'; ?>"></i></button>
                                <?php else: ?><span class="no-proof" title="Belum ada"><i class="fas fa-times-circle"></i></span><?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <button class="btn-icon" onclick='viewDetailIncome(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)' title="View"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon" onclick='editIncome(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon btn-del" onclick="confirmDelete('?del_pay=<?php echo urlencode($row['payment_id']); ?>','<?php echo htmlspecialchars(addslashes($row['company_name'])); ?>','Rp <?php echo number_format($row['amount']); ?>')" title="Hapus"><i class="fas fa-trash"></i></button>
                            </td>
                        <?php else: ?>
                            <td><?php echo date('d M Y',strtotime($row['spending_date'])); ?></td>
                            <td><span class="badge exp"><?php echo htmlspecialchars(ucfirst($row['type'])); ?></span></td>
                            <td><span class="vendor-name"><?php echo htmlspecialchars(isset($row['vendor'])?($row['vendor']?:'-'):'-'); ?></span></td>
                            <td><?php echo htmlspecialchars($row['detail']); ?><?php if(!empty($row['notes'])): ?>&nbsp;<span class="row-note" title="<?php echo htmlspecialchars($row['notes']); ?>"><i class="fas fa-sticky-note"></i></span><?php endif; ?></td>
                            <td class="align-right val-out fw-bold">- Rp <?php echo number_format($row['amount']); ?></td>
                            <td class="align-center">
                                <?php if (!empty($row['proof'])): ?>
                                <button class="btn-icon" onclick="viewProof('/uploads/proofs/<?php echo htmlspecialchars($row['proof']); ?>','#<?php echo $row['id']; ?>')" title="Lihat"><i class="fas fa-<?php echo isProofPDF($row['proof'])?'file-pdf':'image'; ?>"></i></button>
                                <?php else: ?><span class="no-proof" title="Belum ada"><i class="fas fa-times-circle"></i></span><?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <button class="btn-icon" onclick='viewDetailExpense(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)' title="View"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon" onclick='editExpense(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon btn-del" onclick="confirmDelete('?del_spend=<?php echo $row['id']; ?>','<?php echo htmlspecialchars(addslashes($row['detail'])); ?>','Rp <?php echo number_format($row['amount']); ?>')" title="Hapus"><i class="fas fa-trash"></i></button>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- MODAL: ADD INCOME -->
<div class="modal-overlay" id="incomeModal">
<div class="modal-content">
<div class="modal-header"><h2 class="modal-title"><i class="fas fa-arrow-up" style="color:var(--neon-sec);margin-right:10px"></i>Input Payment</h2><button class="close-btn" onclick="closeModal('incomeModal')">&times;</button></div>
<form method="POST" enctype="multipart/form-data">
<div class="form-grid">
    <div class="form-group"><label>Trans ID Preview</label><input type="text" id="trans_id_field" class="form-input" readonly style="color:var(--neon-main);font-weight:bold;"></div>
    <div class="form-group"><label>Invoice No. (Opsional)</label><input type="text" name="invoice_no" class="form-input" placeholder="INV-2024-001"></div>
    <div class="form-group full">
        <label>Client (Auto Fill)</label>
        <input list="clients_list" id="search_client" class="form-input" onchange="fillClientData()" oninput="onClientInput()" placeholder="Ketik nama perusahaan...">
        <datalist id="clients_list">
            <?php foreach ($clients_data as $cd): ?><option value="<?php echo htmlspecialchars($cd['company_name']); ?>" data-email="<?php echo htmlspecialchars($cd['email']); ?>"><?php endforeach; ?>
        </datalist>
    </div>
    <input type="hidden" name="email" id="field_email">
    <input type="hidden" name="company_name" id="field_company">
    <div class="form-group" id="wrap_mc" style="display:none;"><label>Nama Perusahaan (Manual)</label><input type="text" id="manual_company" class="form-input" placeholder="PT. Nama Baru" oninput="document.getElementById('field_company').value=this.value"></div>
    <div class="form-group" id="wrap_me" style="display:none;"><label>Email (Manual)</label><input type="email" id="manual_email" class="form-input" placeholder="email@client.com" oninput="document.getElementById('field_email').value=this.value"></div>
    <div class="form-group full">
        <label>Service (bisa pilih lebih dari satu)</label>
        <div class="service-options">
            <input type="checkbox" name="service_type[]" value="SEO" id="s1" class="service-check"><label for="s1" class="service-label">SEO</label>
            <input type="checkbox" name="service_type[]" value="Branding" id="s2" class="service-check"><label for="s2" class="service-label">Branding</label>
            <input type="checkbox" name="service_type[]" value="Social Media" id="s3" class="service-check"><label for="s3" class="service-label">Social Media</label>
            <input type="checkbox" name="service_type[]" value="Web Dev" id="s4" class="service-check"><label for="s4" class="service-label">Web Dev</label>
            <input type="checkbox" name="service_type[]" value="Content Creator" id="s5" class="service-check"><label for="s5" class="service-label">Content Creator</label>
            <input type="checkbox" name="service_type[]" value="Google Ads" id="s6" class="service-check"><label for="s6" class="service-label">Google Ads</label>
            <input type="checkbox" name="service_type[]" value="Meta Ads" id="s7" class="service-check"><label for="s7" class="service-label">Meta Ads</label>
            <input type="checkbox" name="service_type[]" value="Email Marketing" id="s8" class="service-check"><label for="s8" class="service-label">Email Marketing</label>
        </div>
    </div>
    <div class="form-group"><label>Payment Type</label><select name="payment_type" class="form-input"><option value="New Client">New Client</option><option value="Recurring">Recurring</option><option value="Add-on">Add-on</option><option value="Project">Project Based</option><option value="Retainer">Retainer</option></select></div>
    <div class="form-group"><label>Tanggal</label><input type="date" name="payment_date" class="form-input" id="input_date" value="<?php echo date('Y-m-d'); ?>" onchange="updateTransID()"></div>
    <div class="form-group"><label>Nominal</label><input type="text" name="amount" class="form-input rupiah" required placeholder="Rp 0" autocomplete="off"></div>
    <div class="form-group"><label>Bukti Transfer <span class="badge-webp">AUTO &rarr; WebP</span></label>
        <div class="file-upload-box"><input type="file" name="proof_file" accept="image/*,application/pdf" onchange="previewFile(this,'prevI','innerI')">
        <div class="upload-inner" id="innerI"><i class="fas fa-cloud-upload-alt"></i><span>Klik atau drag</span><small>JPG PNG WebP PDF</small></div>
        <img id="prevI" class="proof-preview" src="" style="display:none;"></div>
    </div>
    <div class="form-group full"><label>Catatan (Opsional)</label><textarea name="notes" class="form-input" rows="2" placeholder="Catatan internal..."></textarea></div>
</div>
<button type="submit" name="add_payment" class="btn-grad btn-submit"><i class="fas fa-save"></i> Simpan Pemasukan</button>
</form>
</div>
</div>

<!-- MODAL: ADD EXPENSE -->
<div class="modal-overlay" id="expenseModal">
<div class="modal-content">
<div class="modal-header"><h2 class="modal-title" style="color:var(--neon-red)"><i class="fas fa-arrow-down" style="margin-right:10px"></i>Tambah Pengeluaran</h2><button class="close-btn" onclick="closeModal('expenseModal')">&times;</button></div>
<form method="POST" enctype="multipart/form-data">
<div class="form-grid">
    <div class="form-group"><label>Kategori</label><select name="type" class="form-input"><option value="aset">Aset / Inventaris</option><option value="operasional">Operasional</option><option value="fee">Fee / Komisi</option><option value="gaji">Gaji / Honor</option><option value="software">Software / Subscription</option><option value="marketing">Marketing / Iklan</option><option value="pajak">Pajak / Retribusi</option><option value="lainnya">Lainnya</option></select></div>
    <div class="form-group"><label>Tanggal</label><input type="date" name="spending_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required></div>
    <div class="form-group"><label>Vendor (Opsional)</label><input type="text" name="vendor" class="form-input" placeholder="Nama vendor"></div>
    <div class="form-group"><label>Nominal</label><input type="text" name="amount" class="form-input rupiah" required placeholder="Rp 0" autocomplete="off"></div>
    <div class="form-group full"><label>Deskripsi</label><input type="text" name="detail" class="form-input" required placeholder="Contoh: Langganan Adobe CC..."></div>
    <div class="form-group"><label>Bukti <span class="badge-webp">AUTO &rarr; WebP</span></label>
        <div class="file-upload-box"><input type="file" name="proof_file" accept="image/*,application/pdf" onchange="previewFile(this,'prevE','innerE')">
        <div class="upload-inner" id="innerE"><i class="fas fa-cloud-upload-alt"></i><span>Klik atau drag</span><small>JPG PNG WebP PDF</small></div>
        <img id="prevE" class="proof-preview" src="" style="display:none;"></div>
    </div>
    <div class="form-group"><label>Catatan</label><textarea name="notes" class="form-input" rows="3" placeholder="Catatan internal..."></textarea></div>
</div>
<button type="submit" name="add_spending" class="btn-grad red btn-submit"><i class="fas fa-save"></i> Simpan Pengeluaran</button>
</form>
</div>
</div>

<!-- MODAL: VIEW DETAIL -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-content" style="background:#0c0c0e; border:1px solid rgba(255,255,255,0.07); width:500px; max-width:95%; padding:30px; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,0.8); position:relative;">
        <div class="modal-top-actions" style="position:absolute; top:20px; right:20px;">
            <button class="btn-close-x" onclick="closeModal('detailModal')" style="background:none; border:none; color:#555; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <h2 class="modal-title" style="color:#fff; margin-bottom:20px; font-size:1.3rem; font-weight:700;"><i class="fas fa-file-invoice-dollar" style="margin-right:8px; color:#888;"></i>Transaction Detail</h2>
        <div id="detailContent"></div>
    </div>
</div>

<!-- MODAL: EDIT INCOME -->
<div class="modal-overlay" id="editIncomeModal">
<div class="modal-content">
<div class="modal-header"><h2 class="modal-title" style="color:var(--neon-main)"><i class="fas fa-edit" style="margin-right:10px"></i>Edit Pemasukan</h2><button class="close-btn" onclick="closeModal('editIncomeModal')">&times;</button></div>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="edit_payment" value="1">
<input type="hidden" name="payment_id" id="edit_in_id">
<div class="form-grid">
    <div class="form-group"><label>Trans ID</label><input type="text" id="edit_in_txid" class="form-input" readonly style="background:rgba(255,255,255,0.02);color:#888;"></div>
    <div class="form-group"><label>Tanggal Transaksi</label><input type="date" name="payment_date" id="edit_in_date" class="form-input" required></div>
    <div class="form-group"><label>Client / Perusahaan</label><input type="text" name="company_name" id="edit_in_company" class="form-input" required></div>
    <div class="form-group"><label>Email Client (Opsional)</label><input type="email" name="email" id="edit_in_email" class="form-input"></div>
    <div class="form-group full"><label>Layanan (Service)</label>
        <div class="checkbox-group">
            <label class="check-label"><input type="checkbox" name="service_type[]" value="Web Development" id="edit_svc_1"> Web Development</label>
            <label class="check-label"><input type="checkbox" name="service_type[]" value="Social Media Management" id="edit_svc_2"> Socmed Mgt</label>
            <label class="check-label"><input type="checkbox" name="service_type[]" value="Digital Ads (Meta/Google)" id="edit_svc_3"> Digital Ads</label>
            <label class="check-label"><input type="checkbox" name="service_type[]" value="Branding & Design" id="edit_svc_4"> Branding</label>
            <label class="check-label"><input type="checkbox" name="service_type[]" value="SEO" id="edit_svc_5"> SEO</label>
            <label class="check-label"><input type="checkbox" name="service_type[]" value="Lainnya" id="edit_svc_6"> Lainnya</label>
        </div>
    </div>
    <div class="form-group"><label>Tipe Pembayaran</label><select name="payment_type" id="edit_in_ptype" class="form-input"><option value="New Client">New Client</option><option value="Recurring">Recurring (Perpanjangan)</option><option value="Down Payment">Down Payment (DP)</option><option value="Pelunasan">Pelunasan</option></select></div>
    <div class="form-group"><label>Nominal</label><input type="text" name="amount" id="edit_in_amount" class="form-input rupiah" required></div>
    <div class="form-group"><label>Nomor Invoice (Opsional)</label><input type="text" name="invoice_no" id="edit_in_inv" class="form-input" placeholder="INV-202X-..."></div>
    <div class="form-group"><label>Bukti Baru (Opsional)</label><input type="file" name="proof_file" class="form-input"></div>
    <div class="form-group full"><label>Catatan</label><textarea name="notes" id="edit_in_notes" class="form-input" rows="3"></textarea></div>
</div>
<button type="submit" class="btn-grad blue btn-submit"><i class="fas fa-save"></i> Simpan Perubahan</button>
</form>
</div>
</div>

<!-- MODAL: EDIT EXPENSE -->
<div class="modal-overlay" id="editExpenseModal">
<div class="modal-content">
<div class="modal-header"><h2 class="modal-title" style="color:var(--neon-red)"><i class="fas fa-edit" style="margin-right:10px"></i>Edit Pengeluaran</h2><button class="close-btn" onclick="closeModal('editExpenseModal')">&times;</button></div>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="edit_spending" value="1">
<input type="hidden" name="spending_id" id="edit_ex_id">
<div class="form-grid">
    <div class="form-group"><label>Kategori</label><select name="type" id="edit_ex_type" class="form-input"><option value="aset">Aset / Inventaris</option><option value="operasional">Operasional</option><option value="fee">Fee / Komisi</option><option value="gaji">Gaji / Honor</option><option value="software">Software / Subscription</option><option value="marketing">Marketing / Iklan</option><option value="pajak">Pajak / Retribusi</option><option value="lainnya">Lainnya</option></select></div>
    <div class="form-group"><label>Tanggal</label><input type="date" name="spending_date" id="edit_ex_date" class="form-input" required></div>
    <div class="form-group"><label>Vendor (Opsional)</label><input type="text" name="vendor" id="edit_ex_vendor" class="form-input"></div>
    <div class="form-group"><label>Nominal</label><input type="text" name="amount" id="edit_ex_amount" class="form-input rupiah" required></div>
    <div class="form-group full"><label>Deskripsi</label><input type="text" name="detail" id="edit_ex_detail" class="form-input" required></div>
    <div class="form-group"><label>Bukti Baru (Opsional)</label><input type="file" name="proof_file" class="form-input"></div>
    <div class="form-group full"><label>Catatan</label><textarea name="notes" id="edit_ex_notes" class="form-input" rows="3"></textarea></div>
</div>
<button type="submit" class="btn-grad red btn-submit"><i class="fas fa-save"></i> Simpan Perubahan</button>
</form>
</div>
</div>

<!-- MODAL: EXPORT -->
<div class="modal-overlay" id="downloadModal">
<div class="modal-content" style="max-width:420px;">
<div class="modal-header"><h2 class="modal-title"><i class="fas fa-file-download" style="color:var(--neon-sec);margin-right:8px"></i>Export Laporan</h2><button class="close-btn" onclick="closeModal('downloadModal')">&times;</button></div>
<form method="GET">
    <input type="hidden" name="action" value="export">
    <div class="form-group"><label>Tipe Laporan</label><select name="type" class="form-input"><option value="income">Income (Pemasukan)</option><option value="expense">Expense (Pengeluaran)</option><option value="mutasi">Mutasi (Semua)</option></select></div>
    <div class="form-group"><label>Tanggal Mulai</label><input type="date" name="d_start" class="form-input" value="<?php echo $date_start; ?>"></div>
    <div class="form-group"><label>Tanggal Akhir</label><input type="date" name="d_end" class="form-input" value="<?php echo $date_end; ?>"></div>
    <div class="form-group"><label>Format Output</label>
        <div class="format-picker">
            <label class="format-opt"><input type="radio" name="format" value="pdf" checked><div class="format-card"><i class="fas fa-file-pdf" style="color:var(--neon-red)"></i><span>PDF Premium</span><small>Siap cetak + tanda tangan</small></div></label>
            <label class="format-opt"><input type="radio" name="format" value="excel"><div class="format-card"><i class="fas fa-file-excel" style="color:var(--neon-main)"></i><span>Excel (.xls)</span><small>Analisis lebih lanjut</small></div></label>
        </div>
    </div>
    <button type="submit" class="btn-grad blue btn-submit"><i class="fas fa-download"></i> Download</button>
</form>
</div>
</div>

<!-- MODAL: PROOF VIEWER -->
<div class="modal-overlay" id="proofModal" onclick="closeModal('proofModal')">
<div class="proof-viewer" onclick="event.stopPropagation()">
    <div class="proof-header"><span id="proofTitle">Bukti Transaksi</span><button class="close-btn" onclick="closeModal('proofModal')">&times;</button></div>
    <div class="proof-body">
        <img id="imgPreview" src="" alt="Bukti" style="max-width:100%;border-radius:10px;display:none;">
        <iframe id="pdfPreview" src="" style="width:100%;height:65vh;border:none;border-radius:10px;display:none;"></iframe>
    </div>
    <div class="proof-footer"><a id="proofDl" href="#" download class="btn-grad blue" style="text-decoration:none;font-size:.8rem;"><i class="fas fa-download"></i> Download</a></div>
</div>
</div>

<!-- MODAL: DELETE -->
<div class="modal-overlay" id="deleteModal">
<div class="modal-content" style="max-width:370px;">
<div class="delete-content">
    <div class="del-icon-box"><i class="fas fa-trash-alt"></i></div>
    <div class="del-title">Hapus Transaksi?</div>
    <div class="del-desc" id="delDesc">Data ini akan dihapus permanen.</div>
    <div class="del-actions"><button class="btn-del-cancel" onclick="closeModal('deleteModal')">Batal</button><a href="#" id="delBtn" class="btn-del-confirm">Ya, Hapus!</a></div>
</div>
</div>
</div>

<!-- MODAL: AUDIT -->
<div class="modal-overlay" id="auditModal">
<div class="modal-content" style="max-width:640px;">
<div class="modal-header"><h2 class="modal-title"><i class="fas fa-history" style="color:var(--neon-sec);margin-right:10px"></i>Audit Trail</h2><button class="close-btn" onclick="closeModal('auditModal')">&times;</button></div>
<?php if (empty($audit_rows)): ?>
<div class="empty-state" style="padding:40px;text-align:center;"><i class="fas fa-history" style="font-size:2.5rem;color:#222;display:block;margin-bottom:12px;"></i><p style="color:#555;">Belum ada aktivitas</p></div>
<?php else: ?>
<div class="audit-list">
<?php foreach ($audit_rows as $al): ?>
<div class="audit-item">
    <div class="audit-icon <?php echo htmlspecialchars($al['action']); ?>"><i class="fas fa-<?php echo ($al['action']==='DELETE')?'trash':'plus'; ?>" style="font-size:7px;color:<?php echo ($al['action']==='DELETE')?'var(--neon-red)':'var(--neon-main)'; ?>"></i></div>
    <div class="audit-card">
        <div class="audit-header">
            <div><span class="audit-action-badge <?php echo strtolower($al['action']); ?>"><?php echo htmlspecialchars($al['action']); ?></span><span class="audit-table"><?php echo htmlspecialchars($al['table_name']); ?></span></div>
            <div class="audit-time"><?php echo date('d M Y, H:i',strtotime($al['created_at'])); ?></div>
        </div>
        <div class="audit-desc"><?php echo htmlspecialchars($al['description']); ?></div>
        <div class="audit-meta">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($al['user_name']); ?></span>
            <span><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($al['ip']); ?></span>
            <span><i class="fas fa-key"></i> <?php echo htmlspecialchars($al['record_id']); ?></span>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>

<!-- TOAST -->
<div id="popup" class="popup"><i class="fas fa-check-circle"></i><span id="popupMsg"></span></div>

<script>
function openModal(id){ document.getElementById(id).classList.add('active'); if(id==='incomeModal') updateTransID(); }
function closeModal(id){ document.getElementById(id).classList.remove('active'); }

function confirmDelete(url, name, amount){
    document.getElementById('delDesc').innerHTML = 'Hapus <strong style="color:#fff">'+name+'</strong> senilai <strong style="color:var(--neon-red)">'+amount+'</strong>?<br><small style="color:#666">Tidak bisa dibatalkan.</small>';
    document.getElementById('delBtn').href = url;
    openModal('deleteModal');
}

function viewProof(src, title){
    document.getElementById('proofTitle').textContent = title||'Bukti';
    var img=document.getElementById('imgPreview'), pdf=document.getElementById('pdfPreview'), dl=document.getElementById('proofDl');
    dl.href=src;
    if(src.indexOf('.pdf')!==-1){ img.style.display='none'; pdf.style.display='block'; pdf.src=src; }
    else { pdf.style.display='none'; img.style.display='block'; img.src=src; }
    openModal('proofModal');
}

function updateTransID(){
    var dv=document.getElementById('input_date').value;
    if(!dv){ document.getElementById('trans_id_field').value='HVM/-/-/-'; return; }
    var d=new Date(dv+'T00:00:00');
    var r=['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    document.getElementById('trans_id_field').value='HVM/'+r[d.getMonth()]+'/'+d.getFullYear()+'/00X';
}

function viewDetailIncome(data) {
    const container = document.getElementById('detailContent');
    const dateObj = new Date(data.payment_date);
    const dateNice = dateObj.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    let proofHtml = data.proof ? `<button class="btn-icon" onclick="viewProof('/uploads/proofs/${data.proof}','${data.payment_id}')" style="margin-left:10px; background:#111; padding:5px 10px; border-radius:5px;"><i class="fas fa-file"></i> Lihat Bukti</button>` : '';
    
    container.innerHTML = `
        <div class="detail-row" style="margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">TRANS ID</span><div class="detail-val" style="font-size:1rem; color:#eaeaea; font-weight:600;">${data.payment_id} ${proofHtml}</div></div>
        <div class="detail-row" style="margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">WAKTU</span><div class="detail-val" style="font-size:1rem; color:#eaeaea; font-weight:600;">${dateNice}</div></div>
        <div class="detail-row" style="margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">CLIENT / PT</span><div class="detail-val" style="font-size:1rem; color:#eaeaea; font-weight:600;">${data.company_name}</div></div>
        <div class="detail-row" style="margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">SERVICE & TIPE</span><div class="detail-val" style="font-size:1rem; color:#eaeaea; font-weight:600;">${data.service_type} &bull; ${data.payment_type}</div></div>
        <div class="detail-row" style="margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">NOMINAL</span><div class="detail-val" style="font-size:1rem; color:#4efdc4; font-weight:600;">+ Rp ${parseInt(data.amount).toLocaleString('id-ID')}</div></div>
        <div class="detail-row" style="border:none;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">CATATAN</span><div class="detail-desc" style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05); padding:15px; border-radius:12px; color:#aaa; font-size:0.85rem; line-height:1.5;">${data.notes || '-'}</div></div>
    `;
    openModal('detailModal');
}

function viewDetailExpense(data) {
    const container = document.getElementById('detailContent');
    const dateObj = new Date(data.spending_date);
    const dateNice = dateObj.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    let proofHtml = data.proof ? `<button class="btn-icon" onclick="viewProof('/uploads/proofs/${data.proof}','#${data.id}')" style="margin-left:10px; background:#111; padding:5px 10px; border-radius:5px;"><i class="fas fa-file"></i> Lihat Bukti</button>` : '';
    
    container.innerHTML = `
        <div class="detail-row" style="margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">KATEGORI</span><div class="detail-val" style="font-size:1rem; color:#eaeaea; font-weight:600; text-transform:capitalize;">${data.type} ${proofHtml}</div></div>
        <div class="detail-row" style="margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">WAKTU</span><div class="detail-val" style="font-size:1rem; color:#eaeaea; font-weight:600;">${dateNice}</div></div>
        <div class="detail-row" style="margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">VENDOR & DETAIL</span><div class="detail-val" style="font-size:1rem; color:#eaeaea; font-weight:600;">${data.vendor || '-'} &bull; ${data.detail}</div></div>
        <div class="detail-row" style="margin-bottom:18px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:15px;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">NOMINAL</span><div class="detail-val" style="font-size:1rem; color:#ff5a5a; font-weight:600;">- Rp ${parseInt(data.amount).toLocaleString('id-ID')}</div></div>
        <div class="detail-row" style="border:none;"><span class="detail-label" style="font-size:0.65rem; color:#777; text-transform:uppercase; letter-spacing:2px; display:block; margin-bottom:6px; font-weight:700;">CATATAN</span><div class="detail-desc" style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05); padding:15px; border-radius:12px; color:#aaa; font-size:0.85rem; line-height:1.5;">${data.notes || '-'}</div></div>
    `;
    openModal('detailModal');
}

function editIncome(data) {
    document.getElementById('edit_in_id').value = data.payment_id;
    document.getElementById('edit_in_txid').value = data.payment_id;
    document.getElementById('edit_in_date').value = data.payment_date;
    document.getElementById('edit_in_company').value = data.company_name;
    document.getElementById('edit_in_email').value = data.email || '';
    document.getElementById('edit_in_ptype').value = data.payment_type;
    document.getElementById('edit_in_amount').value = formatRupiah(data.amount.toString());
    document.getElementById('edit_in_inv').value = data.invoice_no || '';
    document.getElementById('edit_in_notes').value = data.notes || '';
    
    for(let i=1; i<=6; i++) document.getElementById('edit_svc_'+i).checked = false;
    if(data.service_type) {
        let svcs = data.service_type.split(', ');
        for(let i=1; i<=6; i++) {
            if(svcs.includes(document.getElementById('edit_svc_'+i).value)) {
                document.getElementById('edit_svc_'+i).checked = true;
            }
        }
    }
    openModal('editIncomeModal');
}

function editExpense(data) {
    document.getElementById('edit_ex_id').value = data.id;
    document.getElementById('edit_ex_type').value = data.type;
    document.getElementById('edit_ex_date').value = data.spending_date;
    document.getElementById('edit_ex_vendor').value = data.vendor || '';
    document.getElementById('edit_ex_amount').value = formatRupiah(data.amount.toString());
    document.getElementById('edit_ex_detail').value = data.detail || '';
    document.getElementById('edit_ex_notes').value = data.notes || '';
    openModal('editExpenseModal');
}

function fillClientData(){
    var val=document.getElementById('search_client').value.trim();
    var opts=document.querySelectorAll('#clients_list option');
    var found=false;
    for(var i=0;i<opts.length;i++){
        if(opts[i].value.trim()===val){
            document.getElementById('field_company').value=val;
            document.getElementById('field_email').value=opts[i].getAttribute('data-email')||'';
            found=true; break;
        }
    }
    if(!found && val.length>1){
        document.getElementById('field_company').value=val;
        document.getElementById('wrap_mc').style.display='block';
        document.getElementById('wrap_me').style.display='block';
        document.getElementById('manual_company').value=val;
    } else if(found){
        document.getElementById('wrap_mc').style.display='none';
        document.getElementById('wrap_me').style.display='none';
    }
}
function onClientInput(){
    var val=document.getElementById('search_client').value.trim();
    document.getElementById('field_company').value=val;
    if(val.length===0){ document.getElementById('wrap_mc').style.display='none'; document.getElementById('wrap_me').style.display='none'; }
}

function formatRupiah(v){ var n=v.replace(/[^0-9]/g,''); if(!n) return ''; return 'Rp '+parseInt(n,10).toLocaleString('id-ID'); }
var ri=document.querySelectorAll('.rupiah');
for(var x=0;x<ri.length;x++) ri[x].addEventListener('input',function(){ this.value=formatRupiah(this.value); });

function previewFile(input, pid, iid){
    var inner=document.getElementById(iid), prev=document.getElementById(pid);
    if(input.files&&input.files[0]){
        var f=input.files[0];
        if(f.type.indexOf('image')!==-1){
            var rd=new FileReader();
            rd.onload=function(e){ prev.src=e.target.result; prev.style.display='block'; if(inner) inner.style.display='none'; };
            rd.readAsDataURL(f);
        } else {
            if(inner) inner.innerHTML='<i class="fas fa-file-pdf" style="color:var(--neon-red)"></i><span>'+f.name+'</span><small>'+( f.size/1024/1024).toFixed(2)+' MB</small>';
        }
    }
}

var ce=document.getElementById('cashflowChart');
if(ce){
    new Chart(ce,{
        type:'bar',
        data:{
            labels:<?php echo json_encode($chart_labels); ?>,
            datasets:[
                {label:'Income',data:<?php echo json_encode($chart_income); ?>,backgroundColor:'rgba(78,253,196,.18)',borderColor:'#4efdc4',borderWidth:2,borderRadius:5},
                {label:'Expense',data:<?php echo json_encode($chart_expense); ?>,backgroundColor:'rgba(255,90,90,.18)',borderColor:'#ff5a5a',borderWidth:2,borderRadius:5}
            ]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{backgroundColor:'#111',titleColor:'#fff',bodyColor:'#aaa',borderColor:'rgba(255,255,255,.08)',borderWidth:1,callbacks:{label:function(c){ return ' Rp '+c.parsed.y.toLocaleString('id-ID'); }}}},
            scales:{
                x:{grid:{color:'rgba(255,255,255,.03)'},ticks:{color:'#555',font:{size:10}}},
                y:{grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#555',font:{size:10},callback:function(v){ return 'Rp '+(v/1000000).toFixed(1)+'jt'; }}}
            }
        }
    });
}

<?php if(isset($_SESSION['popup'])): ?>
(function(){ var p=document.getElementById('popup'); document.getElementById('popupMsg').textContent=<?php echo json_encode($_SESSION['popup']); ?>; p.classList.add('show'); setTimeout(function(){ p.classList.remove('show'); },4000); })();
<?php unset($_SESSION['popup']); ?>
<?php endif; ?>

updateTransID();
</script>
</body>
</html>