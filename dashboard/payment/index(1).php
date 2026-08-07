<?php
// 1. INIT
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

if(!isset($_SESSION['admin']) || $_SESSION['role'] !== 'super_admin'){ 
    echo "<script>alert('Akses Ditolak'); window.location='/dashboard/';</script>"; exit; 
}

// 2. ROMAN MONTH HELPER
function getRomanMonth($m){
    $map = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
    return $map[intval($m)];
}

// 3. AUTO-FIX DB
function fixCol($conn, $t, $c, $d) {
    $q = mysqli_query($conn, "SHOW COLUMNS FROM $t LIKE '$c'");
    if(mysqli_num_rows($q) == 0) mysqli_query($conn, "ALTER TABLE $t ADD COLUMN $c $d");
}
fixCol($conn, 'payments', 'email', 'VARCHAR(100)');
fixCol($conn, 'payments', 'company_name', 'VARCHAR(150)');
fixCol($conn, 'payments', 'proof', 'VARCHAR(255)');
fixCol($conn, 'payments', 'service_type', 'VARCHAR(255)');
fixCol($conn, 'payments', 'payment_type', 'VARCHAR(50)');
fixCol($conn, 'spendings', 'proof', 'VARCHAR(255)');
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifications (id INT AUTO_INCREMENT PRIMARY KEY, type VARCHAR(50), message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, is_read TINYINT(1) DEFAULT 0)");

// 4. EXPORT LOGIC (EXCEL / PDF)
if(isset($_GET['action']) && $_GET['action'] == 'export'){
    $type = $_GET['type'];
    $d_start = $_GET['d_start'];
    $d_end = $_GET['d_end'];
    $format = $_GET['format'];

    $where = "WHERE (payment_date BETWEEN '$d_start' AND '$d_end')";
    if($type == 'income'){
        $sql = "SELECT payment_date as Date, company_name as Company, service_type as Service, amount as Nominal FROM payments $where ORDER BY payment_date ASC";
    } elseif($type == 'expense'){
        $where = str_replace('payment_date', 'spending_date', $where);
        $sql = "SELECT spending_date as Date, type as Category, detail as Description, amount as Nominal FROM spendings $where ORDER BY spending_date ASC";
    } else { 
        $sql = "SELECT payment_date as Date, company_name as Desc_1, 'INCOME' as Type, amount as Nominal FROM payments $where 
                UNION ALL 
                SELECT spending_date as Date, detail as Desc_1, 'EXPENSE' as Type, amount as Nominal FROM spendings ".str_replace('payment_date', 'spending_date', $where);
    }
    
    $res = mysqli_query($conn, $sql);

    if($format == 'excel'){
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=Laporan_HVM_$type.xls");
    } else {
        echo "<html><head><title>Report PDF</title><style>
            @page { size: portrait; margin: 15mm; }
            body{font-family:sans-serif; color:#000; padding:10px;}
            table{width:100%; border-collapse:collapse; margin-top:20px;}
            th, td{border:1px solid #000; padding:8px; text-align:left; font-size:11px;}
            th{background:#f0f0f0;}
            h2{text-align:center; margin-bottom:5px; text-transform: uppercase;}
            p{text-align:center; font-size:12px; margin-bottom:20px;}
            .val-in { color: green; }
            .val-out { color: red; }
        </style></head><body onload='window.print()'>";
    }

    // Perbaikan teks periode dan keterangan tipe
    $nama_laporan = strtoupper($type);
    echo "<h2>LAPORAN KEUANGAN HVM ($nama_laporan)</h2>";
    echo "<p>Periode Tanggal: " . date('d M Y', strtotime($d_start)) . " s/d " . date('d M Y', strtotime($d_end)) . "</p>";
echo "<table><thead><tr>";
    
    if($type == 'income'){ 
        echo "<th>Tanggal</th><th>Nama Perusahaan / Client</th><th>Jenis Layanan</th><th>Nominal Masuk</th>"; 
    } elseif($type == 'expense'){ 
        echo "<th>Tanggal</th><th>Kategori Pengeluaran</th><th>Keterangan Detail</th><th>Nominal Keluar</th>"; 
    } else { 
        // Header untuk MUTASI
        echo "<th>Tanggal</th><th>Keterangan Transaksi</th><th>Tipe</th><th>Nominal</th>"; 
    }
    
    echo "</tr></thead><tbody>";

    while($row = mysqli_fetch_assoc($res)){
        $nom = "Rp " . number_format($row['Nominal'], 0, ',', '.');
        echo "<tr>";
        foreach($row as $k => $v){
            if($k == 'Nominal') echo "<td>$nom</td>";
            else echo "<td>$v</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table>";
    if($format == 'pdf') echo "</body></html>";
    exit;
}

// 5. UPLOAD HANDLER
function handleUpload($fileInputName){
    if(isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == 0){
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/proofs/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION);
        $fileName = time() . '_' . rand(100,999) . '.' . $ext;
        move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $uploadDir . $fileName);
        return $fileName;
    }
    return null;
}

// 6. CRUD OPERATIONS
if(isset($_POST['add_payment'])){
    try {
        $date = $_POST['payment_date'] ?: date('Y-m-d');
        $m = date('n', strtotime($date)); $y = date('Y', strtotime($date));
        $rom = getRomanMonth($m);
        
        // FIX: ANTI DUPLICATE LOGIC
        $prefix = "HVM/$rom/$y/";
        $q_check = mysqli_query($conn, "SELECT payment_id FROM payments WHERE payment_id LIKE '$prefix%' ORDER BY payment_id DESC LIMIT 1");
        if(mysqli_num_rows($q_check) > 0){
            $last_id = mysqli_fetch_assoc($q_check)['payment_id'];
            $last_num = (int)substr($last_id, -3);
            $seq = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $seq = "001";
        }
        $id = $prefix . $seq;

        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $company = mysqli_real_escape_string($conn, $_POST['company_name']);
        $service = isset($_POST['service_type']) ? implode(', ', $_POST['service_type']) : 'General';
        $amount = (int)str_replace(['Rp', '.', ' '], '', $_POST['amount']);
        $type = $_POST['payment_type'];
        $proof = handleUpload('proof_file');
        
        $sql = "INSERT INTO payments (payment_id, email, company_name, amount, payment_date, service_type, payment_type, proof) 
                VALUES ('$id', '$email', '$company', '$amount', '$date', '$service', '$type', '$proof')";
        mysqli_query($conn, $sql);
        
        mysqli_query($conn, "INSERT INTO notifications (type, message) VALUES ('income', 'Masuk Rp ".number_format($amount)." dari $company')");
        $_SESSION['popup'] = "Pemasukan berhasil dicatat!";
        header("Location: index.php?view=income&m=$m&y=$y"); exit;
    } catch (Exception $e) {
        $_SESSION['popup_err'] = $e->getMessage();
        header("Location: index.php?view=income"); exit;
    }
}

if(isset($_POST['add_spending'])){
    try {
        $type = $_POST['type'];
        $detail = mysqli_real_escape_string($conn, $_POST['detail']);
        $amount = (int)str_replace(['Rp', '.', ' '], '', $_POST['amount']);
        $date = $_POST['spending_date'];
        $proof = handleUpload('proof_file');
        mysqli_query($conn, "INSERT INTO spendings (type, detail, amount, spending_date, proof) VALUES ('$type', '$detail', '$amount', '$date', '$proof')");
        mysqli_query($conn, "INSERT INTO notifications (type, message) VALUES ('expense', 'Keluar Rp ".number_format($amount)." untuk $type')");
        $_SESSION['popup'] = "Pengeluaran berhasil dicatat!";
        header("Location: index.php?view=expense&m=".date('m', strtotime($date))."&y=".date('Y', strtotime($date))); exit;
    } catch (Exception $e) { }
}

if(isset($_GET['del_pay'])){
    $id = mysqli_real_escape_string($conn, $_GET['del_pay']);
    mysqli_query($conn, "DELETE FROM payments WHERE payment_id='$id'");
    header("Location: index.php?view=income"); exit;
}
if(isset($_GET['del_spend'])){
    $id = (int)$_GET['del_spend'];
    mysqli_query($conn, "DELETE FROM spendings WHERE id='$id'");
    header("Location: index.php?view=expense"); exit;
}

// 7. VIEW DATA
$view = $_GET['view'] ?? 'income';
$search = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
$date_start = $_GET['start'] ?? date('Y-m-01'); // Default tanggal 1 bulan ini
$date_end = $_GET['end'] ?? date('Y-m-t');     // Default tanggal terakhir bulan ini

// Stats Calculation
// Stats Calculation dengan range tanggal
$total_in = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as t FROM payments WHERE payment_date BETWEEN '$date_start' AND '$date_end'"))['t'] ?? 0;
$total_out = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as t FROM spendings WHERE spending_date BETWEEN '$date_start' AND '$date_end'"))['t'] ?? 0;
$alloc_aset = $total_in * 0.20;
$alloc_gaji = $total_in * 0.10;
$real_profit = ($total_in - $total_out) - ($alloc_aset + $alloc_gaji);

if($view == 'income'){
    $where = "WHERE (payment_id LIKE '%$search%' OR company_name LIKE '%$search%') AND (payment_date BETWEEN '$date_start' AND '$date_end')";
    $query = "SELECT * FROM payments $where ORDER BY payment_date DESC";
} elseif ($view == 'expense') {
    $where = "WHERE (detail LIKE '%$search%' OR type LIKE '%$search%') AND (spending_date BETWEEN '$date_start' AND '$date_end')";
    $query = "SELECT * FROM spendings $where ORDER BY spending_date DESC";
} else {
    $query = "SELECT * FROM payments WHERE payment_date BETWEEN '$date_start' AND '$date_end' ORDER BY payment_date DESC";
}
$result = mysqli_query($conn, $query);

$clients_data = [];
$q_cli = mysqli_query($conn, "SELECT email, company_name FROM clients WHERE status='Active'");
while($c = mysqli_fetch_assoc($q_cli)) $clients_data[] = $c;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance - HVM</title>
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="dashboard-wrapper">
        <?php include '../sidebar.php'; ?>

        <main class="main-content">
            <div class="page-headline">
                <h1>Finance Overview</h1>
                <p>Track cashflow, allocations, and mutations.</p>
            </div>

            <div class="stats-grid">
                <div class="glass-card">
                    <i class="fas fa-arrow-up card-bg-icon icon-in"></i>
                    <div class="stat-label">Total Income</div>
                    <div class="stat-value val-in">Rp <?php echo number_format($total_in/1000000, 1); ?> jt</div>
                </div>
                <div class="glass-card">
                    <i class="fas fa-arrow-down card-bg-icon icon-out"></i>
                    <div class="stat-label">Total Expense</div>
                    <div class="stat-value val-out">Rp <?php echo number_format($total_out/1000000, 1); ?> jt</div>
                </div>
                <div class="glass-card">
                    <i class="fas fa-building card-bg-icon icon-alloc"></i>
                    <div class="stat-label">Alokasi Aset (20%)</div>
                    <div class="stat-value val-alloc">Rp <?php echo number_format($alloc_aset/1000000, 1); ?> jt</div>
                </div>
                <div class="glass-card">
                    <i class="fas fa-users card-bg-icon icon-alloc"></i>
                    <div class="stat-label">Alokasi Gaji (10%)</div>
                    <div class="stat-value val-alloc">Rp <?php echo number_format($alloc_gaji/1000000, 1); ?> jt</div>
                </div>
                <div class="glass-card">
                    <i class="fas fa-wallet card-bg-icon icon-net"></i>
                    <div class="stat-label">Net Profit</div>
                    <div class="stat-value val-net">Rp <?php echo number_format($real_profit/1000000, 1); ?> jt</div>
                </div>
            </div>

            <div class="action-container">
                <div class="tab-group">
    <button class="tab-btn <?php echo $view=='income'?'active':''; ?>" onclick="window.location='?view=income&start=<?=$date_start?>&end=<?=$date_end?>'">Incomes</button>
    <button class="tab-btn expense <?php echo $view=='expense'?'active':''; ?>" onclick="window.location='?view=expense&start=<?=$date_start?>&end=<?=$date_end?>'">Expenses</button>
    <button class="tab-btn <?php echo $view=='mutasi'?'active':''; ?>" onclick="window.location='?view=mutasi&start=<?=$date_start?>&end=<?=$date_end?>'">Mutasi</button>
</div>

                <form method="GET" class="filter-group">
    <input type="hidden" name="view" value="<?php echo $view; ?>">
    <?php if($view != 'mutasi'): ?>
    <input type="text" name="q" class="search-input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
    <?php endif; ?>
    
    <input type="date" name="start" class="date-select" value="<?=$date_start?>" onchange="this.form.submit()">
    <span style="color:white">to</span>
    <input type="date" name="end" class="date-select" value="<?=$date_end?>" onchange="this.form.submit()">
</form>


                <div style="display:flex; gap:10px;">
                    <button class="btn-grad blue" onclick="openModal('downloadModal')"><i class="fas fa-download"></i> Download</button>
                    <?php if($view == 'income'): ?>
                        <button class="btn-grad" onclick="openModal('incomeModal')"><i class="fas fa-plus"></i> Add</button>
                    <?php elseif($view == 'expense'): ?>
                        <button class="btn-grad red" onclick="openModal('expenseModal')"><i class="fas fa-minus"></i> Add</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <?php if($view == 'mutasi'): ?>
                                <tr><th>Date</th><th>Client / PT</th><th>Total Masuk</th><th>Pot. Aset (20%)</th><th>Pot. Gaji (10%)</th><th>Sisa Bersih</th></tr>
                            <?php elseif($view == 'income'): ?>
                                <tr><th>Date</th><th>Trans ID</th><th>Client / Company</th><th>Service</th><th>Amount</th><th>Proof</th><th>Action</th></tr>
                            <?php else: ?>
                                <tr><th>Date</th><th>Category</th><th>Detail</th><th>Amount</th><th>Proof</th><th>Action</th></tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <?php if($view == 'mutasi'): 
                                    $p_aset = $row['amount'] * 0.20; $p_gaji = $row['amount'] * 0.10; $p_sisa = $row['amount'] - ($p_aset + $p_gaji);
                                ?>
                                    <td><?php echo date('d/m/Y', strtotime($row['payment_date'])); ?></td>
                                    <td><?php echo $row['company_name']; ?></td>
                                    <td class="val-in">+ <?php echo number_format($row['amount']); ?></td>
                                    <td style="color:#ff9f43">- <?php echo number_format($p_aset); ?></td>
                                    <td style="color:#ff9f43">- <?php echo number_format($p_gaji); ?></td>
                                    <td class="val-net" style="font-weight:700"><?php echo number_format($p_sisa); ?></td>

                                <?php elseif($view == 'income'): ?>
                                    <td><?php echo date('d M', strtotime($row['payment_date'])); ?></td>
                                    <td style="font-family:'Courier New'; color:var(--neon-sec);">#<?php echo $row['payment_id']; ?></td>
                                    <td><div style="font-weight:600"><?php echo $row['company_name']; ?></div><div style="font-size:0.75rem; color:#888;"><?php echo $row['email']; ?></div></td>
                                    <td><?php $svcs = explode(', ', $row['service_type']); foreach($svcs as $s) echo "<span class='badge service' style='margin-right:3px;'>$s</span>"; ?></td>
                                    <td class="val-in">+ Rp <?php echo number_format($row['amount']); ?></td>
                                    <td><?php if($row['proof']): ?><button class="btn-icon" onclick="viewImage('/uploads/proofs/<?php echo $row['proof']; ?>')"><i class="fas fa-image"></i></button><?php endif; ?></td>
                                    <td><a href="?del_pay=<?php echo $row['payment_id']; ?>" class="btn-icon btn-del" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td>

                                <?php else: ?>
                                    <td><?php echo date('d M', strtotime($row['spending_date'])); ?></td>
                                    <td><span class="badge exp"><?php echo $row['type']; ?></span></td>
                                    <td><?php echo $row['detail']; ?></td>
                                    <td class="val-out">- Rp <?php echo number_format($row['amount']); ?></td>
                                    <td><?php if($row['proof']): ?><button class="btn-icon" onclick="viewImage('/uploads/proofs/<?php echo $row['proof']; ?>')"><i class="fas fa-image"></i></button><?php endif; ?></td>
                                    <td><a href="?del_spend=<?php echo $row['id']; ?>" class="btn-icon btn-del" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a></td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL INCOME -->
    <div class="modal-overlay" id="incomeModal">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title">Input Payment</h2><button class="close-btn" onclick="closeModal('incomeModal')">&times;</button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group"><label>Trans ID (Preview)</label><input type="text" id="trans_id_field" class="form-input" readonly value="HVM/XII/2024/00X" style="color:var(--neon-main); font-weight:bold;"></div>
                    
                    <div class="form-group full">
                        <label>Client (Auto Fill)</label>
                        <input list="clients_list" id="search_client" class="form-input" onchange="fillClientData()" placeholder="Ketik untuk mencari...">
                        <datalist id="clients_list">
                            <?php foreach($clients_data as $c): ?>
                                <option value="<?php echo $c['company_name']; ?>" data-email="<?php echo $c['email']; ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <input type="hidden" name="email" id="field_email">
                    <input type="hidden" name="company_name" id="field_company">
                    
                    <div class="form-group full">
                        <label>Service (Pilih Lebih dari Satu)</label>
                        <div class="service-options">
                            <input type="checkbox" name="service_type[]" value="SEO" id="svc_seo" class="service-check"><label for="svc_seo" class="service-label">SEO</label>
                            <input type="checkbox" name="service_type[]" value="Branding" id="svc_brand" class="service-check"><label for="svc_brand" class="service-label">Branding</label>
                            <input type="checkbox" name="service_type[]" value="Social Media" id="svc_sosmed" class="service-check"><label for="svc_sosmed" class="service-label">Social Media</label>
                            <input type="checkbox" name="service_type[]" value="Web Dev" id="svc_web" class="service-check"><label for="svc_web" class="service-label">Web Dev</label>
                            <input type="checkbox" name="service_type[]" value="Content Creator" id="svc_content" class="service-check"><label for="svc_content" class="service-label">Content Creator</label>
                        </div>
                    </div>

                    <div class="form-group"><label>Type</label><select name="payment_type" class="form-input"><option>New Client</option><option>Recurring</option><option>Add-on</option></select></div>
                    <div class="form-group"><label>Date</label><input type="date" name="payment_date" class="form-input" id="input_date" value="<?php echo date('Y-m-d'); ?>" onchange="updateTransID()"></div>
                    <div class="form-group"><label>Amount</label><input type="text" name="amount" class="form-input rupiah" required placeholder="Rp 0"></div>
                    <div class="form-group"><label>Proof</label><div class="file-upload-box"><input type="file" name="proof_file"><span>Choose File</span></div></div>
                </div>
                <button type="submit" name="add_payment" class="btn-grad" style="width:100%; justify-content:center;">Save Payment</button>
            </form>
        </div>
    </div>

    <!-- MODAL EXPENSE -->
    <div class="modal-overlay" id="expenseModal">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title" style="color:var(--neon-red)">New Spending</h2><button class="close-btn" onclick="closeModal('expenseModal')">&times;</button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group"><label>Category</label><select name="type" class="form-input"><option value="aset">Aset</option><option value="operasional">Operasional</option><option value="fee">Fee</option></select></div>
                    <div class="form-group"><label>Date</label><input type="date" name="spending_date" class="form-input" value="<?=date('Y-m-d')?>" required></div>
                    <div class="form-group full"><label>Detail</label><input type="text" name="detail" class="form-input" required></div>
                    <div class="form-group"><label>Amount</label><input type="text" name="amount" class="form-input rupiah" required></div>
                    <div class="form-group"><label>Proof</label><div class="file-upload-box"><input type="file" name="proof_file"><span>Choose File</span></div></div>
                </div>
                <button type="submit" name="add_spending" class="btn-grad red" style="width:100%; justify-content:center;">Save Spending</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="downloadModal">
        <div class="modal-content" style="width:400px;">
            <div class="modal-header"><h2 class="modal-title">Download Report</h2><button class="close-btn" onclick="closeModal('downloadModal')">&times;</button></div>
            <form method="GET">
                <input type="hidden" name="action" value="export">
                <div class="form-group"><label>Report Type</label><select name="type" class="form-input"><option value="income">Income</option><option value="expense">Expense</option><option value="mutasi">Mutasi</option></select></div>
                <div class="form-group">
    <label>Start Date</label>
    <input type="date" name="d_start" class="form-input" value="<?=date('Y-m-01')?>">
</div>
                <div class="form-group">
    <label>End Date</label>
    <input type="date" name="d_end" class="form-input" value="<?=date('Y-m-d')?>">
</div>
                <div class="form-group"><label>Format</label><select name="format" class="form-input"><option value="excel">Excel</option><option value="pdf">PDF</option></select></div>
                <button type="submit" class="btn-grad blue" style="width:100%;">Download</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="imageModal" onclick="closeModal('imageModal')"><img id="imgPreview" class="img-zoom"></div>
    <div id="popup" class="popup"><i class="fas fa-check-circle"></i> <span id="popupMsg"></span></div>

    <script>
        function openModal(id) { document.getElementById(id).classList.add('active'); if(id=='incomeModal') updateTransID(); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        function viewImage(src) { document.getElementById('imgPreview').src = src; openModal('imageModal'); }

        function fillClientData() {
            const val = document.getElementById('search_client').value;
            const options = document.querySelectorAll('#clients_list option');
            options.forEach(opt => {
                if(opt.value === val) {
                    document.getElementById('field_company').value = val;
                    document.getElementById('field_email').value = opt.getAttribute('data-email');
                }
            });
        }

        function updateTransID() {
            const dateVal = document.getElementById('input_date').value;
            if(!dateVal) return;
            const d = new Date(dateVal);
            const roman = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
            document.getElementById('trans_id_field').value = `HVM/${roman[d.getMonth()]}/${d.getFullYear()}/00X`;
        }

        const formatRupiah = (angka) => {
            let number_string = angka.replace(/[^,\d]/g, '').toString(),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);
            if(ribuan) { separator = sisa ? '.' : ''; rupiah += separator + ribuan.join('.'); }
            return rupiah ? 'Rp ' + rupiah : '';
        }

        document.querySelectorAll('.rupiah').forEach(inp => {
            inp.addEventListener('keyup', function() { this.value = formatRupiah(this.value); });
        });

        <?php if(isset($_SESSION['popup'])): ?>
            const p = document.getElementById('popup');
            document.getElementById('popupMsg').innerText = "<?= $_SESSION['popup'] ?>";
            p.classList.add('show');
            setTimeout(() => { p.classList.remove('show'); }, 3000);
            <?php unset($_SESSION['popup']); ?>
        <?php endif; ?>
    </script>
</body>
</html>