<div align="center">
  <img src="https://internal.hvmdigital.id/uploads/logohvm.png" alt="HVM Digital Logo" width="200"/>
  <br/><br/>
  <h1>HVM Digital — Internal Dashboard</h1>
  <p><strong>Platform manajemen internal all-in-one untuk operasional bisnis digital agency.</strong></p>
  <br/>
  <img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white"/>
  <img src="https://img.shields.io/badge/MariaDB-11.x-003545?style=flat-square&logo=mariadb&logoColor=white"/>
  <img src="https://img.shields.io/badge/Hostinger-VPS-673DE6?style=flat-square&logo=hostinger&logoColor=white"/>
  <img src="https://img.shields.io/badge/AI-Groq%20%7C%20OpenAI%20%7C%20Gemini-black?style=flat-square&logo=openai&logoColor=white"/>
  <br/><br/>
</div>

---

## 📌 Tentang Project

**HVM Digital Internal Dashboard** adalah sistem manajemen internal berbasis web yang dibangun khusus untuk kebutuhan operasional **HVM Digital** — sebuah digital agency yang bergerak di bidang Web Development, SEO, Social Media, Branding, dan Ads.

Sistem ini dirancang agar seluruh tim dapat memantau, mengelola, dan berkolaborasi secara real-time dari satu platform terpusat — mulai dari manajemen klien, keuangan, prospek bisnis, hingga AI assistant cerdas yang memahami konteks data perusahaan.

- 🌐 **URL:** [internal.hvmdigital.id](https://internal.hvmdigital.id)
- 🗄️ **Database:** `u664715641_INTERNAL` (MariaDB 11.x)
- 🖥️ **Hosting:** Hostinger VPS (`u664715641@46.202.186.86`, port `65002`)
- 🔐 **Auth:** Session-based login dengan role `super_admin` / `admin`

---

## 🗂️ Modul & Fitur

### 1. 🏠 Overview (Dashboard Utama)
**Path:** `/dashboard/`

Pusat komando operasional HVM Digital yang menampilkan gambaran menyeluruh bisnis secara real-time.

| Fitur | Detail |
|-------|--------|
| **Revenue Tracker** | Total pemasukan bulan ini vs target bulanan (Rp 40jt) dengan progress bar & level status |
| **Stats Cards** | Klien aktif, prospek pipeline, invoice outstanding, total transaksi |
| **Service Breakdown** | Distribusi pendapatan per jenis layanan (Web Dev, SEO, Social Media, dll) |
| **Upcoming Deadlines** | Kontrak klien yang akan segera berakhir (dari `services_data` JSON) |
| **Calendar / Planner** | Kalender meeting & event (mode Month/Week/Day) terintegrasi dengan tabel `events` |
| **Daily Quote** | Kutipan motivasi acak setiap hari untuk semangat tim |
| **Notifikasi** | Bell notification untuk payment masuk, alert, dan informasi sistem |

---

### 2. 👥 Clients
**Path:** `/dashboard/clients/`

Manajemen data klien aktif HVM Digital dengan sistem *Service Lifecycle* multi-layanan.

| Fitur | Detail |
|-------|--------|
| **Profil Klien** | Nama perusahaan, PIC, kontak WA, domain, kota, status, logo |
| **Service Lifecycle** | Setiap klien bisa punya banyak layanan (Web Dev, SEO, dll) masing-masing dengan tanggal mulai, tanggal berakhir, harga/bulan, status (Active/Inactive), keywords, catatan |
| **Vault Akses** | Penyimpanan credential aman (cPanel, Instagram, TikTok, YouTube, dsb) |
| **Dokumen** | Upload & manajemen dokumen terkait klien |
| **Riwayat Meeting** | Histori semua meeting yang pernah dijadwalkan dengan klien tersebut |
| **Invoice Klien** | Daftar invoice yang pernah diterbitkan untuk klien |
| **H-countdown** | Badge peringatan otomatis H-7, H-14, H-30 sebelum kontrak berakhir |
| **Quick Actions** | Langsung hubungi via WhatsApp, edit data, hapus dari tabel |

---

### 3. 🔭 Prospects
**Path:** `/dashboard/prospects/`

Pipeline manajemen calon klien (leads) dengan sistem pelacakan status dan riwayat interaksi.

| Fitur | Detail |
|-------|--------|
| **Pipeline Status** | Cold → Warm → Hot → Closed dengan stat card & filter klik |
| **Deal Status** | Chip selector: Deal / Gak Deal / Ghosting |
| **Filter Periode** | Filter berdasarkan aktivitas: Semua / 3 Hari / 7 Hari / 30 Hari |
| **Terakhir Visit** | Kolom tanggal visit terakhir dari tabel `events` (hijau = ≤7 hari) |
| **Riwayat Meeting** | Tab histori semua meeting per prospek beserta log hasil |
| **Edit Log Hasil** | Update catatan/hasil meeting langsung dari dalam modal detail |
| **Quick Actions** | WhatsApp langsung, buka deck/proposal, buka Google Maps alamat |
| **Pencarian** | Real-time search nama perusahaan, PIC, domain |

---

### 4. 💰 Payment
**Path:** `/dashboard/payment/`

Pencatatan dan manajemen semua pemasukan (revenue) dari klien.

| Fitur | Detail |
|-------|--------|
| **Catat Pembayaran** | Input payment manual: nama perusahaan, jumlah, tanggal, jenis layanan, tipe (DP/Lunas/Cicilan), upload bukti |
| **Statistik Bulan Ini** | Total pemasukan, jumlah transaksi, rata-rata per transaksi |
| **Riwayat Pembayaran** | Tabel lengkap semua histori transaksi dengan filter dan pencarian |
| **Link ke Invoice** | Nomor invoice terhubung ke tabel `invoices` |
| **Finance Audit Log** | Setiap perubahan data keuangan tercatat di `finance_audit_log` |

---

### 5. 🧾 Invoice
**Path:** `/dashboard/invoice/`

Pembuatan dan manajemen invoice profesional HVM Digital.

| Fitur | Detail |
|-------|--------|
| **Buat Invoice** | Generate invoice dengan nomor otomatis (INV-YYYY-XXXX), client ref, label layanan, total |
| **Status Tracking** | Status invoice: Belum Lunas / Lunas |
| **Cetak / Unduh** | Print atau download invoice sebagai PDF |
| **Link ke Klien/Prospek** | Invoice bisa dihubungkan ke klien aktif atau prospek |

---

### 6. 📊 Performance
**Path:** `/dashboard/performance/`

Analitik kinerja bisnis HVM Digital dalam rentang waktu tertentu.

| Fitur | Detail |
|-------|--------|
| **Revenue Chart** | Grafik pendapatan bulanan/tahunan |
| **Service Comparison** | Perbandingan pendapatan antar jenis layanan |
| **Top Clients** | Klien dengan kontribusi revenue tertinggi |
| **Trend Analysis** | Tren pertumbuhan bisnis dari waktu ke waktu |

---

### 7. 👔 Teams
**Path:** `/dashboard/teams/`

Manajemen data anggota tim HVM Digital.

| Fitur | Detail |
|-------|--------|
| **Data Anggota** | Nama, posisi/jabatan, nomor WA, email, domisili |
| **Referensi AI** | Data tim digunakan AI untuk mengetahui siapa yang terlibat di setiap proyek/meeting |

---

### 8. 🤖 AI Asisten (Nebula)
**Path:** `sidebar (floating bubble)`

AI assistant cerdas yang memahami data internal HVM Digital secara real-time.

| Fitur | Detail |
|-------|--------|
| **Multi-Provider** | Mendukung Groq, OpenAI, Google Gemini, Anthropic |
| **Context-Aware** | AI membaca data dari: Clients, Prospects, Events/Meeting, Payments, Spendings, Invoices, Teams |
| **Efisien Token** | Hanya kolom penting yang dikirim ke AI (filter kolom + truncate cerdas) |
| **Identitas Custom** | Nama asisten (Nebula), persona, ikon bisa dikustomisasi |
| **Chat Persist** | Riwayat percakapan tersimpan di session |
| **Floating UI** | Bubble chat muncul di pojok kanan bawah semua halaman |

**Contoh pertanyaan yang bisa dijawab AI:**
- *"Kapan kontrak alatrumah.com berakhir?"*
- *"Siapa klien yang kontraknya mau habis bulan ini?"*
- *"Berapa total pemasukan bulan Juli?"*
- *"Prospek mana yang masih Hot tapi belum ada follow-up?"*
- *"Siapa PIC dari PT. Mardika Sarana Engineering?"*

---

### 9. ⚙️ Settings AI
**Path:** `/dashboard/settings/`

Konfigurasi AI Assistant (hanya `super_admin`).

| Fitur | Detail |
|-------|--------|
| **Pilih Provider** | Toggle antara Groq / OpenAI / Google Gemini / Anthropic |
| **API Key** | Input & simpan API key secara aman di database |
| **Model Selection** | Pilih model AI yang digunakan (beserta hint model yang tersedia per provider) |
| **Test Koneksi** | Test koneksi API key langsung dari UI |
| **Identitas AI** | Atur nama asisten, persona/instruksi karakter |
| **Upload Ikon** | Ganti ikon AI yang tampil di floating bubble |

---

### 10. 🗓️ Workspace / Planner
**Path:** `/dashboard/workspace/`

Workspace kolaborasi tim untuk perencanaan proyek dan jadwal kerja.

| Fitur | Detail |
|-------|--------|
| **Planner Board** | Kanban-style task management per proyek |
| **Deadline Tracker** | Monitor deadline aktif dari services_data klien |
| **Notes & Brainstorm** | Workspace notes dan sesi brainstorm canvas |

---

## 🗄️ Struktur Database

Database: `u664715641_INTERNAL` (~40+ tabel)

```
📦 KEUANGAN
├── payments          — Pemasukan dari klien
├── spendings         — Pengeluaran operasional
├── invoices          — Invoice yang diterbitkan
└── finance_audit_log — Audit trail keuangan

📦 CLIENT MANAGEMENT
├── clients           — Data klien aktif (services_data JSON)
├── prospects         — Pipeline calon klien
├── events            — Meeting & jadwal visit
└── reminders         — Pengingat untuk tim

📦 AI / NEBULA
├── ai_settings       — Konfigurasi provider, model, API key
├── ai_memory         — Memori percakapan per user
└── ai_corrections    — Koreksi jawaban AI

📦 INTERNAL
├── admin_users       — Login dashboard
├── teams             — Data anggota tim
├── keep_notes        — Catatan personal
├── workspace_notes   — Notes workspace
└── audit_logs        — Audit log aksi user

📦 EMAIL MARKETING
├── email_campaigns   — Kampanye email blast
├── email_contacts    — Daftar kontak (206 kontak)
├── email_queue       — Antrian pengiriman
└── email_tracking    — Tracking open/click
```

---

## 🚀 Deployment

### Deploy via SSH
```bash
# Pull update terbaru ke server
ssh -p 65002 u664715641@46.202.186.86 \
  "cd domains/internal.hvmdigital.id/public_html && \
   git fetch origin && \
   git reset --hard origin/master"
```

### Deploy via PowerShell Script
```powershell
# Dari local, jalankan:
.\deploy.ps1
```

### Git Remote
```
https://github.com/dgtilhammln-cmd/internal.hvmdigital.id
```

---

## 🔐 Security

- Login berbasis PHP session dengan enkripsi password
- Role-based access: `super_admin` (full access) / `admin` (limited)
- File sensitif (`.env`, `*.sql`, `config.php`) sudah di-exclude via `.gitignore`
- API Key AI tersimpan di database, tidak di file
- HTTPS enforced via `.htaccess`

---

## 🛠️ Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | PHP 8.x (Procedural) |
| Database | MariaDB 11.x via MySQLi |
| Frontend | HTML5, Vanilla CSS, Vanilla JS |
| Fonts | Google Fonts — Montserrat |
| Icons | Font Awesome 6 |
| AI Provider | Groq (default), OpenAI, Gemini, Anthropic |
| Hosting | Hostinger VPS |
| Version Control | Git + GitHub |

---

## 📁 Struktur Folder

```
public_html/
├── index.php              # Halaman login
├── dashboard/
│   ├── index.php          # Overview / Dashboard utama
│   ├── clients/           # Manajemen klien
│   ├── prospects/         # Pipeline prospek
│   ├── payment/           # Pembayaran & pemasukan
│   ├── invoice/           # Invoice management
│   ├── performance/       # Analitik kinerja
│   ├── teams/             # Data tim
│   ├── workspace/         # Planner & workspace
│   ├── ai/
│   │   └── handler.php    # Backend AI chat handler
│   ├── settings/          # Konfigurasi AI
│   ├── sidebar.php        # Komponen sidebar navigasi
│   └── sidebar.style.css  # Styling sidebar
├── includes/
│   └── db_connect.php     # Koneksi database
├── uploads/               # Aset upload (logo, ikon, bukti bayar)
└── email-marketing/       # Modul email blast
```

---

<div align="center">
  <br/>
  <sub>Built with ❤️ by <strong>HVM Digital Dev Team</strong> — Internal use only.</sub>
  <br/>
  <sub>© 2025–2026 HVM Digital. All rights reserved.</sub>
</div>
