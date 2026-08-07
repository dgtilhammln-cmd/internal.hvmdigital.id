<?php
// 1. INIT
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

// Security
// Check if user is logged in
if(!isset($_SESSION['admin'])){ echo "<script>window.location='/index.php';</script>"; exit; }

// Check Role (Hanya Super Admin yang diizinkan melihat data performa)
$allowed = (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');

// Config DB
mysqli_query($conn, "SET SESSION group_concat_max_len = 100000");

// 2. FILTER DATE LOGIC
$start_m = $_GET['start_m'] ?? date('m', strtotime("-5 months"));
$start_y = $_GET['start_y'] ?? date('Y', strtotime("-5 months"));
$end_m   = $_GET['end_m']   ?? date('m', strtotime("+1 month"));
$end_y   = $_GET['end_y']   ?? date('Y', strtotime("+1 month"));

$start_date = "$start_y-$start_m-01";
$end_date   = date("Y-m-t", strtotime("$end_y-$end_m-01"));

// 3. DATA PROCESSING LOOP
$period = new DatePeriod(new DateTime($start_date), new DateInterval('P1M'), new DateTime($end_date . ' +1 day'));

$labels = [];
$chart_profit = [];
$chart_expense = [];
$chart_income = []; 
$table_data = [];

$total_omzet = 0;
$total_expense = 0;
$new_clients = 0;

foreach ($period as $dt) {
    $curr_m = $dt->format('m');
    $curr_y = $dt->format('Y');
    $lbl = $dt->format('M'); 
    
    // Income Desc
    $sql_desc = "SELECT payment_type, COUNT(*) as cnt FROM payments WHERE MONTH(payment_date)='$curr_m' AND YEAR(payment_date)='$curr_y' GROUP BY payment_type";
    $q_desc = mysqli_query($conn, $sql_desc);
    $desc_parts = [];
    while($d = mysqli_fetch_assoc($q_desc)){ $desc_parts[] = $d['cnt'] . " " . $d['payment_type']; }
    $income_desc = empty($desc_parts) ? '-' : implode(', ', $desc_parts);

    // Sum Income
    $q_in = mysqli_query($conn, "SELECT SUM(amount) as t FROM payments WHERE MONTH(payment_date)='$curr_m' AND YEAR(payment_date)='$curr_y'");
    $inc = mysqli_fetch_assoc($q_in)['t'] ?? 0;
    
    // Sum Expense
    $q_out = mysqli_query($conn, "SELECT SUM(amount) as t FROM spendings WHERE MONTH(spending_date)='$curr_m' AND YEAR(spending_date)='$curr_y'");
    $exp = mysqli_fetch_assoc($q_out)['t'] ?? 0;

    // New Clients
    $q_nc = mysqli_query($conn, "SELECT COUNT(*) as c FROM clients WHERE MONTH(created_at)='$curr_m' AND YEAR(created_at)='$curr_y'");
    $nc = mysqli_fetch_assoc($q_nc)['c'];

    $prof = $inc - $exp;
    $labels[] = "$lbl";
    $chart_profit[] = $prof;
    $chart_expense[] = $exp;
    $chart_income[] = $inc;

    $table_data[] = [
        'period' => "$lbl $curr_y",
        'income' => $inc,
        'expense' => $exp,
        'profit' => $prof,
        'nc' => $nc,
        'desc' => $income_desc
    ];

    $total_omzet += $inc;
    $total_expense += $exp;
    $new_clients += $nc;
}

$rt_active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM clients WHERE status='Active'"))['c'];
$rt_suspend = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM clients WHERE status='Suspended' OR contract_end < NOW()"))['c'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance - HVM</title>
    <link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="dashboard-wrapper">
        
        <!-- Sidebar -->
        <?php include '../sidebar.php'; ?>

        <main class="main-content">
    
    <!-- Headline tetap tampil agar rapi -->
    <div class="page-headline">
        <h1>Performance Report</h1>
        <p>Financial analysis & growth metrics.</p>
    </div>

    <?php if(!$allowed): ?>
        <!-- TAMPILAN ACCESS DENIED JIKA BUKAN SUPER ADMIN -->
        <div class="forbidden-box">
            <div class="forbidden-icon"><i class="fas fa-lock"></i></div>
            <div class="forbidden-text">DALAM PERBAIKAN</div>
            <div class="forbidden-sub">Halaman ini sedang dalam proses perbaikan.</div>
            <a href="/dashboard/" class="btn-neon" style="text-decoration:none;">KEMBALI KE DASHBOARD</a>
        </div>
    <?php else: ?>

            <!-- Filter -->
            <div class="top-bar">
                <form method="GET" class="filter-glass">
                    <select name="start_m"><?php for($i=1;$i<=12;$i++) echo "<option value='".sprintf("%02d",$i)."' ".($i==$start_m?'selected':'').">$i</option>"; ?></select>
                    <select name="start_y"><?php for($y=2024;$y<=2030;$y++) echo "<option value='$y' ".($y==$start_y?'selected':'').">$y</option>"; ?></select>
                    <span style="color:#555">/</span>
                    <select name="end_m"><?php for($i=1;$i<=12;$i++) echo "<option value='".sprintf("%02d",$i)."' ".($i==$end_m?'selected':'').">$i</option>"; ?></select>
                    <select name="end_y"><?php for($y=2024;$y<=2030;$y++) echo "<option value='$y' ".($y==$end_y?'selected':'').">$y</option>"; ?></select>
                    <button class="btn-filter">UPDATE VIEW</button>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="glass-card">
                    <div class="stat-label">Total Omzet</div>
                    <div class="stat-value" style="color:var(--neon-sec)">Rp <?php echo number_format($total_omzet/1000000, 1); ?>jt</div>
                    <div class="stat-trend trend-up"><span>↗</span> +<?php echo $new_clients; ?> Clients</div>
                </div>
                <div class="glass-card">
                    <div class="stat-label">Profit Bersih</div>
                    <div class="stat-value" style="color:var(--neon-main)">Rp <?php echo number_format(($total_omzet-$total_expense)/1000000, 1); ?>jt</div>
                    <div class="stat-trend trend-up" style="color:var(--text-muted)">High Margin</div>
                </div>
                <div class="glass-card">
                    <div class="stat-label">Total Pengeluaran</div>
                    <div class="stat-value" style="color:var(--neon-red)">Rp <?php echo number_format($total_expense/1000000, 1); ?>jt</div>
                    <div class="stat-trend trend-down"><span>↘</span> Operational</div>
                </div>
                <div class="glass-card">
                    <div class="stat-label">Active Clients</div>
                    <div class="stat-value"><?php echo $rt_active; ?></div>
                    <div class="stat-trend trend-down">⚠️ <?php echo $rt_suspend; ?> Suspended</div>
                </div>
            </div>

            <!-- Chart & Satisfaction -->
            <div class="chart-grid">
                <div class="glass-card chart-container">
                    <div class="section-title">Growth Wave (Omzet vs Profit)</div>
                    <div style="height:300px; width:100%;">
                        <canvas id="perfChart"></canvas>
                    </div>
                </div>

                <div class="glass-card satisfaction-card">
                    <div class="section-title">Client Retention</div>
                    <div class="circle-wrap">
                        <div class="circle-graph">
                            <span class="percentage">98%</span>
                        </div>
                        <div class="circle-text">
                            <h2>Excellent</h2>
                            <p>Based on contract renewal</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-card">
                <div class="section-title">Monthly Breakdown</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Periode</th>
                                <th>Transaction Detail</th>
                                <th>Income</th>
                                <th>Expense</th>
                                <th>Profit</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($table_data as $row): ?>
                            <tr>
                                <td><?php echo $row['period']; ?></td>
                                <td style="color:#aaa; font-style:italic; font-size:0.8rem;"><?php echo $row['desc']; ?></td>
                                <td style="color:var(--neon-sec); font-weight:600;">Rp <?php echo number_format($row['income']); ?></td>
                                <td style="color:var(--neon-red)">Rp <?php echo number_format($row['expense']); ?></td>
                                <td style="color:var(--neon-main); font-weight:800;">Rp <?php echo number_format($row['profit']); ?></td>
                                <td><span class="status-dot"></span> Profit</td>
                            </tr>
                           <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

    <?php endif; ?> <!-- TAMBAHKAN BARIS INI -->

        </main>
    </div>

    <!-- Chart Config -->
    <script>
        const ctx = document.getElementById('perfChart').getContext('2d');
        
        // Gradient Fill
        let grad = ctx.createLinearGradient(0, 0, 0, 400);
        grad.addColorStop(0, 'rgba(0, 227, 150, 0.25)');
        grad.addColorStop(1, 'rgba(0, 227, 150, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Net Profit',
                    data: <?php echo json_encode($chart_profit); ?>,
                    borderColor: '#00E396',
                    backgroundColor: grad,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#000',
                    pointBorderColor: '#00E396',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Expense',
                    data: <?php echo json_encode($chart_expense); ?>,
                    borderColor: '#ff5a5a',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointRadius: 0,
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#888', font: {family:'Montserrat'} } },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { 
                        grid: { color: 'rgba(255,255,255,0.03)' }, 
                        ticks: { color: '#666', font: {family: 'Montserrat'} } 
                    },
                    x: { 
                        grid: { display: false }, 
                        ticks: { color: '#666', font: {family: 'Montserrat'} } 
                    }
                }
            }
        });
    </script>
</body>
</html>