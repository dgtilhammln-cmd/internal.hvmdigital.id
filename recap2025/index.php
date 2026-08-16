<?php
// Public page - no auth required
include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/db_connect.php';

// --- DATA PREPARATION ---
$year = '2025';

function get_val($conn, $query, $key='total') {
    $res = mysqli_query($conn, $query);
    if($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row[$key] ?? 0;
    }
    return 0;
}

// 1. Finance Data
$revenue = get_val($conn, "SELECT SUM(amount) as total FROM payments WHERE payment_date >= '2025-10-01'");
$total_trx = get_val($conn, "SELECT COUNT(*) as total FROM payments WHERE payment_date >= '2025-10-01'");

// 2. Clients Data
$new_clients = get_val($conn, "SELECT COUNT(*) as total FROM clients WHERE created_at >= '2025-10-01'");

// 3. Best Month
$q_best = mysqli_query($conn, "SELECT MONTH(payment_date) as m, YEAR(payment_date) as y, SUM(amount) as total FROM payments WHERE payment_date >= '2025-10-01' GROUP BY y, m ORDER BY total DESC LIMIT 1");
$best_data = mysqli_fetch_assoc($q_best);
$best_month = $best_data ? date("F", mktime(0, 0, 0, $best_data['m'], 10, $best_data['y'])) . ' ' . $best_data['y'] : "None";
$best_val = $best_data['total'] ?? 0;

// 4. Top Service
$q_svc = mysqli_query($conn, "SELECT service_type, COUNT(*) as cnt FROM payments WHERE payment_date >= '2025-10-01' GROUP BY service_type ORDER BY cnt DESC LIMIT 1");
$svc_data = mysqli_fetch_assoc($q_svc);
$top_service = $svc_data['service_type'] ?? "General";

// 5. Top Client
$q_cl = mysqli_query($conn, "SELECT company_name, SUM(amount) as total FROM payments WHERE payment_date >= '2025-10-01' GROUP BY company_name ORDER BY total DESC LIMIT 1");
$cl_data = mysqli_fetch_assoc($q_cl);
$top_client = $cl_data['company_name'] ?? "No Data";

// 6. Monthly Trend
$q_trend = mysqli_query($conn, "SELECT DATE_FORMAT(payment_date, '%b %y') as m_label, SUM(amount) as total FROM payments WHERE payment_date >= '2025-10-01' GROUP BY YEAR(payment_date), MONTH(payment_date) ORDER BY YEAR(payment_date), MONTH(payment_date)");
$trend_labels = [];
$trend_data = [];
while($r = mysqli_fetch_assoc($q_trend)){
    $trend_labels[] = $r['m_label'];
    $trend_data[] = $r['total'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>HVM Rewind 2025 — Pencapaian Luar Biasa</title>
    <meta name="description" content="Melihat kembali pencapaian luar biasa HVM Digital dari Oktober 2025 hingga saat ini.">
    <link rel="stylesheet" href="/dashboard/recap2025/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const chartLabels = <?php echo json_encode($trend_labels); ?>;
        const chartData = <?php echo json_encode($trend_data); ?>;
    </script>
</head>
<body>

    <!-- AUDIO -->
    <audio id="bgMusic" loop>
        <source src="https://cdn.pixabay.com/download/audio/2022/05/27/audio_1808fbf07a.mp3" type="audio/mpeg">
    </audio>

    <!-- BACKGROUND -->
    <canvas id="cosmos"></canvas>
    <div class="overlay-vignette"></div>

    <!-- HEADER CTRL -->
    <div class="top-controls">
        <div class="glass-icon-btn" onclick="toggleMute()"><i class="fas fa-volume-up" id="volIcon"></i></div>
        <div class="glass-icon-btn" onclick="window.location.href='https://internal.hvmdigital.id'"><i class="fas fa-times"></i></div>
    </div>

    <!-- SLIDESHOW -->
    <div class="slides-wrapper" id="slider">

        <!-- SLIDE 1: INTRO -->
        <div class="slide active" id="slide1">
            <div class="logo-badge">HVM DIGITAL</div>
            <h1 class="mega-title">REWIND <span class="text-neon">2025</span></h1>
            <p class="subtitle">PERJALANAN LUAR BIASA (Okt - Sekarang)</p>
            <button class="btn-primary" onclick="startExperience()">
                MULAI RECAP <i class="fas fa-play"></i>
            </button>
        </div>

        <!-- SLIDE 2: REVENUE -->
        <div class="slide" id="slide2">
            <div class="glass-card">
                <i class="fas fa-wallet" style="font-size:3rem; color:var(--neon-green); margin-bottom:20px;"></i>
                <div class="data-label">TOTAL PENDAPATAN</div>
                <div class="data-big">
                    <span class="currency">Rp</span>
                    <span class="counter" data-val="<?php echo $revenue; ?>">0</span>
                </div>
                <p style="color:#aaa;">Bukti nyata dari kerja keras, eksekusi, dan dedikasi tanpa henti.</p>
            </div>
            <div class="nav-bottom">
                <button class="nav-btn" onclick="nextSlide()"><i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- SLIDE 3: STATS -->
        <div class="slide" id="slide3">
            <div class="grid-stats">
                <div class="stat-box">
                    <i class="fas fa-users stat-icon"></i>
                    <h2 class="counter" data-val="<?php echo $new_clients; ?>" style="font-size:3rem; margin:0;">0</h2>
                    <p class="data-label">MITRA BARU EKSLUSIF</p>
                </div>
                <div class="stat-box">
                    <i class="fas fa-handshake stat-icon" style="color:var(--neon-cyan);"></i>
                    <h2 class="counter" data-val="<?php echo $total_trx; ?>" style="font-size:3rem; margin:0;">0</h2>
                    <p class="data-label">PROYEK GOL</p>
                </div>
            </div>
            <div class="nav-bottom">
                <button class="nav-btn" onclick="prevSlide()"><i class="fas fa-arrow-left"></i></button>
                <button class="nav-btn" onclick="nextSlide()"><i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- SLIDE 4: GROWTH CHART -->
        <div class="slide" id="slide4">
            <h2 class="subtitle">GRAFIK PERTUMBUHAN</h2>
            <div class="glass-card" style="width: 100%; max-width: 800px; padding: 20px; box-sizing: border-box;">
                <canvas id="growthChart" height="250"></canvas>
            </div>
            <div class="nav-bottom">
                <button class="nav-btn" onclick="prevSlide()"><i class="fas fa-arrow-left"></i></button>
                <button class="nav-btn" onclick="nextSlide()"><i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- SLIDE 5: HIGHLIGHTS -->
        <div class="slide" id="slide5">
            <h2 class="subtitle">PUNCAK PRESTASI</h2>
            <div class="grid-stats">
                <div class="stat-box" style="border-color:#ffd700;">
                    <i class="fas fa-crown stat-icon" style="color:#ffd700;"></i>
                    <h3 style="font-size:2rem; margin:10px 0;"><?php echo strtoupper($best_month); ?></h3>
                    <p style="color:#fff;">Rp <?php echo number_format($best_val/1000000, 1); ?> JT</p>
                    <p class="data-label" style="margin-top:10px;">BULAN TERBAIK</p>
                </div>
                <div class="stat-box">
                    <i class="fas fa-star stat-icon"></i>
                    <h3 style="font-size:1.8rem; margin:10px 0;"><?php echo $top_service; ?></h3>
                    <p class="data-label">LAYANAN TERFAVORIT</p>
                </div>
                <div class="stat-box">
                    <i class="fas fa-trophy stat-icon" style="color:var(--neon-cyan);"></i>
                    <h3 style="font-size:1.5rem; margin:10px 0;"><?php echo $top_client; ?></h3>
                    <p class="data-label">KLIEN ELIT</p>
                </div>
            </div>
            <div class="nav-bottom">
                <button class="nav-btn" onclick="prevSlide()"><i class="fas fa-arrow-left"></i></button>
                <button class="nav-btn" onclick="nextSlide()"><i class="fas fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- SLIDE 6: SHARE STORY -->
        <div class="slide" id="slide6">
            <div class="share-container">
                <div id="storyCard" class="story-wrapper">
                    <div class="story-content">
                        <div class="story-bg-glow"></div>
                        <div class="story-header">
                            <div class="story-brand">HVM DIGITAL</div>
                        </div>
                        
                        <div style="margin-top:40px;">
                            <h1 class="story-year"><?php echo $year; ?></h1>
                            <div style="font-size:1.2rem; letter-spacing:5px; margin-bottom:30px;">PENCAPAIAN</div>
                            
                            <div class="story-data-row">
                                <span class="story-lbl">TOTAL PENDAPATAN</span>
                                <div class="story-val" style="color:var(--neon-green)">Rp <?php echo number_format($revenue/1000000, 1); ?> Juta</div>
                            </div>
                            
                            <div class="story-data-row">
                                <span class="story-lbl">TOTAL PROYEK GOL</span>
                                <div class="story-val"><?php echo $total_trx; ?> Transaksi</div>
                            </div>

                            <div class="story-data-row">
                                <span class="story-lbl">LAYANAN TERFAVORIT</span>
                                <div class="story-val" style="color:var(--neon-cyan)"><?php echo $top_service; ?></div>
                            </div>
                        </div>

                        <div class="story-footer">#HVM2025 #Growth</div>
                    </div>
                </div>

                <div class="btn-group" style="margin-top:20px; display:flex; gap:10px;">
                    <button class="btn-primary" onclick="downloadStory()"><i class="fas fa-download"></i> Simpan</button>
                    <button class="btn-primary" onclick="location.reload()" style="background:transparent; color:#fff; border:1px solid #fff;"><i class="fas fa-redo"></i> Ulang</button>
                </div>
            </div>
        </div>

    </div>

    <script src="/dashboard/recap2025/script.js"></script>
</body>
</html>
