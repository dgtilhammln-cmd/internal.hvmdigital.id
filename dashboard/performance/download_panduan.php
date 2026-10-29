<?php
// ============================================================
// HVM DIGITAL — BUKU PANDUAN NERACA KEUANGAN (PDF PRINT ENGINE)
// ============================================================
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: /index.php"); exit;
}
$adminName = htmlspecialchars($_SESSION['admin'] ?? 'Super Admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panduan Neraca Keuangan HVM Digital</title>
<link rel="shortcut icon" href="/uploads/icon.png" type="image/x-icon">
<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">

<style>
:root {
    --primary: #0a0a0a;
    --accent: #117a3f;
    --border: #ddd;
    --text: #222;
}
* { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif; }
body { background:#f5f6f8; color:var(--text); padding:30px 20px; }

.page-container {
    max-width: 850px;
    margin: 0 auto;
    background: #fff;
    padding: 50px 60px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    border-radius: 8px;
    position: relative;
}

/* Print Toolbar */
.toolbar-bar {
    max-width: 850px;
    margin: 0 auto 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.btn-print {
    background: #000;
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: 0.2s;
    text-decoration: none;
}
.btn-print:hover { background: #333; }
.btn-back {
    color: #555;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-back:hover { color: #000; }

/* Document Header */
.doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px double #000;
    padding-bottom: 25px;
    margin-bottom: 35px;
}
.company-brand h2 { font-size: 1.6rem; font-weight: 900; letter-spacing: -0.5px; }
.company-brand p { font-size: 0.8rem; color: #666; margin-top: 4px; }
.doc-meta { text-align: right; font-size: 0.75rem; color: #555; line-height: 1.5; }
.doc-meta strong { color: #000; }

/* Document Title */
.doc-title-block { text-align: center; margin-bottom: 40px; }
.doc-tag { font-size: 0.72rem; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; }
.doc-title-block h1 { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 900; line-height: 1.25; margin-bottom: 12px; }
.doc-title-block p { font-size: 0.9rem; color: #666; max-width: 620px; margin: 0 auto; }

/* Chapter */
.chapter { margin-bottom: 35px; page-break-inside: avoid; }
.chapter-title {
    font-size: 1.05rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #000;
    padding-bottom: 8px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.chapter-title i { font-size: 1rem; color: var(--accent); }
.chapter p { font-size: 0.88rem; line-height: 1.7; color: #333; margin-bottom: 14px; text-align: justify; }

/* Table */
.guide-table {
    width: 100%;
    border-collapse: collapse;
    margin: 16px 0;
    font-size: 0.82rem;
}
.guide-table th, .guide-table td {
    border: 1px solid #ddd;
    padding: 10px 14px;
    text-align: left;
}
.guide-table th { background: #f8f8f8; font-weight: 800; text-transform: uppercase; font-size: 0.75rem; }
.guide-table tr:nth-child(even) { background: #fafafa; }

/* Callout Box */
.callout {
    background: #f4faf6;
    border-left: 4px solid var(--accent);
    padding: 14px 18px;
    border-radius: 0 6px 6px 0;
    margin: 18px 0;
    font-size: 0.84rem;
    line-height: 1.6;
}
.callout strong { color: #000; }

/* Footer */
.doc-footer {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 50px;
    display: flex;
    justify-content: space-between;
    font-size: 0.72rem;
    color: #888;
}

@media print {
    body { background: #fff; padding: 0; }
    .toolbar-bar { display: none !important; }
    .page-container { box-shadow: none; padding: 20px 0; border-radius: 0; }
}
</style>
</head>
<body>

<div class="toolbar-bar">
    <a href="/dashboard/performance/neraca.php" class="btn-back">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Neraca
    </a>
    <button type="button" class="btn-print" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Cetak / Simpan sebagai PDF
    </button>
</div>

<div class="page-container">

    <!-- Document Header -->
    <div class="doc-header">
        <div class="company-brand">
            <h2>HVM DIGITAL</h2>
            <p>PT HVM Digital Nusantara • internal.hvmdigital.id</p>
        </div>
        <div class="doc-meta">
            Dokumen: <strong>FIN-MANUAL-01</strong><br>
            Klasifikasi: <strong>Internal / Confidential</strong><br>
            Tanggal Terbit: <strong><?= date('d F Y') ?></strong><br>
            Otorisasi: <strong><?= $adminName ?></strong>
        </div>
    </div>

    <!-- Title -->
    <div class="doc-title-block">
        <div class="doc-tag">Standard Operating Procedure &amp; Executive Guidelines</div>
        <h1>Panduan Resmi Penyusunan &amp; Analisis Neraca Keuangan</h1>
        <p>Pedoman standar pemahaman struktur aset, kewajiban, ekuitas, dan rasio likuiditas untuk mendukung pertumbuhan berkesinambungan HVM Digital.</p>
    </div>

    <!-- Chapter 1 -->
    <div class="chapter">
        <div class="chapter-title">
            <i class="fa-solid fa-compass"></i> Bab 1: Urgensi Neraca bagi Agensi Digital
        </div>
        <p>
            Di industri agensi pemasaran digital, model bisnis sering melibatkan pembayaran di muka (uang muka kontrak atau retainer 3 hingga 6 bulan). Kondisi ini kerap memicu <em>retention/retainer trap</em>, di mana saldo rekening kas terlihat berlimpah sehingga tim manajemen merasa memiliki likuiditas bebas, padahal sebagian besar uang tersebut merupakan kewajiban penyelesaian jasa di bulan-bulan mendatang.
        </p>
        <p>
            <strong>Neraca Keuangan (Balance Sheet)</strong> hadir untuk memberikan potret rill posisi kekayaan dan kesehatan finansial HVM pada satu titik waktu tertentu. Melalui neraca, manajemen dapat memisahkan antara modal bersih riil yang telah menjadi hak perusahaan dengan beban kewajiban yang masih berjalan.
        </p>
        <div class="callout">
            <strong>Prinsip Kunci:</strong> Laporan Laba Rugi menjawab "Berapa keuntungan kita bulan ini?", sedangkan Neraca Keuangan menjawab "Berapa nilai kekayaan bersih dan ketahanan modal HVM hari ini?".
        </div>
    </div>

    <!-- Chapter 2 -->
    <div class="chapter">
        <div class="chapter-title">
            <i class="fa-solid fa-scale-balanced"></i> Bab 2: Anatomi &amp; Persamaan Dasar Neraca
        </div>
        <p>
            Neraca disusun berdasarkan persamaan baku akuntansi internasional:
        </p>
        <div style="background:#111; color:#a1ff5a; padding:12px 20px; border-radius:6px; font-family:monospace; text-align:center; font-size:1.05rem; font-weight:800; margin:14px 0;">
            TOTAL ASET (AKTIVA) = TOTAL KEWAJIBAN (UTANG) + EKUITAS (MODAL)
        </div>
        <table class="guide-table">
            <thead>
                <tr>
                    <th>Komponen</th>
                    <th>Sub-Kategori</th>
                    <th>Implementasi Riil di HVM Digital</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td rowspan="2"><strong>ASET (AKTIVA)</strong><br><span style="color:#666;font-size:0.75rem;">Semua kekayaan yang dikuasai HVM</span></td>
                    <td>Aset Lancar</td>
                    <td>Kas di rekening bank (BCA, Mandiri), Kas Kecil, Piutang invoice klien yang belum lunas, dan Biaya dibayar di muka (prepaid hosting & tools tahunan).</td>
                </tr>
                <tr>
                    <td>Aset Tetap &amp; Digital</td>
                    <td>Peralatan workstation (MacBook, PC Editing, Kamera studio), dikurangi akumulasi penyusutan, serta portofolio domain dan lisensi software permanen.</td>
                </tr>
                <tr>
                    <td rowspan="2"><strong>KEWAJIBAN</strong><br><span style="color:#666;font-size:0.75rem;">Utang & tagihan pihak ketiga</span></td>
                    <td>Kewajiban Lancar</td>
                    <td>Utang vendor/freelancer, Pendapatan Diterima di Muka (DP proyek yang belum selesai), Utang estimasi pajak usaha, dan beban gaji berjalan.</td>
                </tr>
                <tr>
                    <td>Kewajiban Jangka Panjang</td>
                    <td>Pinjaman modal ekspansi atau pembiayaan perbankan dengan tenor pelunasan di atas 12 bulan.</td>
                </tr>
                <tr>
                    <td><strong>EKUITAS</strong><br><span style="color:#666;font-size:0.75rem;">Kekayaan bersih milik founder</span></td>
                    <td>Modal &amp; Laba</td>
                    <td>Modal awal disetor pendiri, Laba ditahan tahun sebelumnya yang disimpan untuk cadangan kas darurat, dan Laba bersih periode berjalan.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Chapter 3 -->
    <div class="chapter">
        <div class="chapter-title">
            <i class="fa-solid fa-chart-line"></i> Bab 3: Parameter Rasio Kesehatan Keuangan
        </div>
        <p>
            Untuk mengevaluasi posisi keuangan tanpa harus membaca seluruh nominal tabel secara rumit, HVM menggunakan 4 rasio keuangan kunci:
        </p>
        <table class="guide-table">
            <thead>
                <tr>
                    <th>Nama Rasio</th>
                    <th>Formula</th>
                    <th>Target Sehat HVM</th>
                    <th>Interpretasi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Current Ratio</strong></td>
                    <td>Aset Lancar / Kewajiban Lancar</td>
                    <td><strong>&ge; 1.5x</strong></td>
                    <td>Setiap Rp 1 utang jangka pendek dijamin oleh minimal Rp 1,5 aset lancar. Menandakan risiko gagal bayar sangat rendah.</td>
                </tr>
                <tr>
                    <td><strong>Debt to Equity (DER)</strong></td>
                    <td>Total Utang / Total Ekuitas</td>
                    <td><strong>&le; 0.5x</strong></td>
                    <td>Mengukur seberapa besar porsi utang dibandingkan modal sendiri. Nilai di bawah 0.5 menunjukkan kemandirian finansial yang kokoh.</td>
                </tr>
                <tr>
                    <td><strong>Net Working Capital</strong></td>
                    <td>Aset Lancar &minus; Kewajiban Lancar</td>
                    <td><strong>Positif (&gt; Rp 50jt)</strong></td>
                    <td>Cadangan modal kerja riil yang siap digunakan mendanai operasional tanpa harus mencari pinjaman luar.</td>
                </tr>
                <tr>
                    <td><strong>Cash Ratio</strong></td>
                    <td>Kas &amp; Bank / Kewajiban Lancar</td>
                    <td><strong>&ge; 0.5x</strong></td>
                    <td>Kemampuan melunasi kewajiban mendesak secara langsung hanya mengandalkan uang kas di rekening bank.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Chapter 4 -->
    <div class="chapter">
        <div class="chapter-title">
            <i class="fa-solid fa-list-check"></i> Bab 4: SOP Rekonsiliasi Bulanan Super Admin
        </div>
        <p>
            Tata kelola data neraca dilakukan setiap akhir bulan dengan alur operasional sebagai berikut:
        </p>
        <ol style="margin-left:22px; font-size:0.86rem; line-height:1.75; color:#333;">
            <li><strong>Sinkronisasi Otomatis Piutang:</strong> Buka modul Neraca di dashboard, klik tombol <em>"Sinkronisasi Data"</em>. Sistem akan mengagregasi total invoice berstatus <code>Pending</code> dan <code>Overdue</code> secara instan ke pos Piutang Usaha.</li>
            <li><strong>Rekonsiliasi Kas Bank:</strong> Bandingkan saldo akhir rekening koran perbankan resmi (BCA/Mandiri) dengan pos Kas &amp; Bank pada neraca. Sesuaikan nominal bila terdapat selisih biaya administrasi atau bunga.</li>
            <li><strong>Verifikasi Keseimbangan:</strong> Perhatikan indikator <em>"Neraca Seimbang (Balanced)"</em>. Bila terdapat selisih, tinjau kembali apakah ada penarikan prive pribadi atau pengeluaran modal yang belum tercatat.</li>
            <li><strong>Arsip Dokumen:</strong> Cetak atau simpan dokumen neraca berkala dengan format PDF untuk audit internal dan arsip tahunan.</li>
        </ol>
    </div>

    <!-- Document Footer -->
    <div class="doc-footer">
        <div>HVM Digital Nusantara &copy; <?= date('Y') ?> • Hak Cipta Dilindungi Undang-Undang</div>
        <div>Halaman 1 dari 1 • Dokumen Resmi Finance</div>
    </div>

</div>

</body>
</html>
