<div align="center">
  <img src="https://internal.hvmdigital.id/uploads/logohvm.png" alt="HVM Digital" height="80"/>
  <br/><br/>
  <h1>HVM Digital — Internal Dashboard</h1>
  <p><strong>Platform manajemen internal all-in-one untuk operasional bisnis digital agency.</strong></p>
  <br/>
  <img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white"/>
  <img src="https://img.shields.io/badge/MariaDB-11.x-003545?style=flat-square&logo=mariadb&logoColor=white"/>
  <img src="https://img.shields.io/badge/Hosting-Hostinger-673DE6?style=flat-square&logo=hostinger&logoColor=white"/>
  <img src="https://img.shields.io/badge/AI-Groq%20%7C%20OpenAI%20%7C%20Gemini-black?style=flat-square&logo=openai&logoColor=white"/>
  <br/><br/>
</div>

---

## 📌 Tentang Project

**HVM Digital Internal Dashboard** adalah sistem manajemen internal berbasis web yang dibangun khusus untuk operasional **HVM Digital** — digital agency yang bergerak di bidang Web Development, SEO, Social Media, Branding, Content Creator, dan Ads.

Platform ini memungkinkan seluruh tim memantau, mengelola, dan berkolaborasi secara real-time dari satu tempat — mulai dari klien, keuangan, prospek bisnis, hingga AI assistant yang memahami konteks data perusahaan secara langsung.

- 🌐 **URL:** [internal.hvmdigital.id](https://internal.hvmdigital.id)
- 🔐 **Auth:** Session-based dengan role `super_admin` / `admin`
- ☁️ **Hosting:** Hostinger

> **⚠️ Private Repository** — Sistem ini bersifat internal dan tidak dimaksudkan untuk direplikasi atau didistribusikan tanpa izin HVM Digital.

---

## 🗂️ Modul & Fitur

### 1. 🏠 Overview (Dashboard Utama)
Pusat komando operasional — gambaran menyeluruh bisnis secara real-time.

- **Revenue Tracker** — Total pemasukan bulan ini vs target bulanan dengan progress bar & level status
- **Stats Cards** — Klien aktif, prospek pipeline, invoice outstanding, total transaksi
- **Service Breakdown** — Distribusi pendapatan per jenis layanan (Web Dev, SEO, Social Media, dll)
- **Upcoming Deadlines** — Kontrak klien yang akan segera berakhir dengan countdown hari
- **Calendar / Planner** — Kalender meeting & event mode Month/Week/Day
- **Notifikasi** — Bell notification real-time untuk payment masuk, alert, dan info sistem

---

### 2. 👥 Clients
Manajemen data klien aktif dengan sistem **Service Lifecycle** multi-layanan.

- **Profil Klien** — Nama perusahaan, PIC, kontak WA, domain, kota, status, logo
- **Service Lifecycle** — Multi-layanan per klien (Web Dev, SEO, dll) masing-masing dengan tanggal mulai & berakhir, harga/bulan, status Active/Inactive, keywords, catatan
- **H-countdown Badge** — Peringatan otomatis H-7, H-14, H-30 sebelum kontrak berakhir
- **Vault Akses** — Penyimpanan credential (cPanel, Instagram, TikTok, YouTube, dsb)
- **Dokumen** — Upload & manajemen file/dokumen terkait klien
- **Riwayat Meeting** — Histori semua meeting yang pernah dijadwalkan
- **Invoice Klien** — Daftar invoice yang pernah diterbitkan

---

### 3. 🔭 Prospects
Pipeline manajemen calon klien (leads) dengan pelacakan status dan riwayat interaksi.

- **Pipeline Status** — Cold → Warm → Hot → Closed dengan stat card & filter klik
- **Deal Status** — Chip selector: Deal / Gak Deal / Ghosting
- **Filter Periode** — Filter aktivitas: Semua / 3 Hari / 7 Hari / 30 Hari
- **Kolom Terakhir Visit** — Tanggal visit terakhir dari jadwal meeting (hijau = ≤7 hari)
- **Riwayat Meeting** — Tab histori semua meeting + log hasil per prospek
- **Quick Actions** — WhatsApp langsung, buka deck/proposal, Google Maps alamat

---

### 4. 💰 Payment
Pencatatan dan manajemen seluruh pemasukan (revenue) dari klien.

- Catat pembayaran manual: nama, jumlah, tanggal, jenis layanan, tipe (DP/Lunas/Cicilan), upload bukti
- Statistik bulan ini: total pemasukan, jumlah transaksi, rata-rata per transaksi
- Riwayat pembayaran lengkap dengan filter & pencarian
- Finance audit log — setiap perubahan keuangan tercatat otomatis

---

### 5. 🧾 Invoice
Pembuatan dan manajemen invoice profesional.

- Generate invoice dengan nomor otomatis (format `INV-YYYY-XXXX`)
- Hubungkan ke klien aktif atau prospek
- Status tracking: Belum Lunas / Lunas
- Cetak atau download invoice

---

### 6. 📊 Performance
Analitik kinerja bisnis dalam rentang waktu tertentu.

- Grafik pendapatan bulanan/tahunan
- Perbandingan pendapatan antar jenis layanan
- Top klien berdasarkan kontribusi revenue
- Tren pertumbuhan bisnis

---

### 7. 👔 Teams
Manajemen data anggota tim HVM Digital.

- Data anggota: nama, posisi, nomor WA, email, domisili
- Data tim digunakan AI untuk mengetahui siapa yang terlibat di setiap proyek/meeting

---

### 8. 🤖 AI Asisten (Nebula)
AI assistant cerdas yang memahami data internal HVM Digital secara real-time.

- **Multi-Provider** — Groq, OpenAI, Google Gemini, Anthropic
- **Context-Aware** — Membaca data Clients, Prospects, Meetings, Payments, Spendings, Invoices, Teams langsung dari database
- **Efisien Token** — Hanya kolom penting yang dikirim ke AI (filter kolom + truncate cerdas)
- **Identitas Custom** — Nama asisten, persona, dan ikon bisa dikustomisasi
- **Floating UI** — Bubble chat muncul di pojok kanan bawah semua halaman

**Contoh yang bisa ditanyakan:**
> *"Kapan kontrak alatrumah.com berakhir?"*
> *"Klien mana yang kontraknya hampir habis bulan ini?"*
> *"Berapa total pemasukan bulan Juli?"*
> *"Prospek mana yang masih Hot tapi belum ada follow-up?"*

---

### 9. ⚙️ Settings AI
Konfigurasi AI Assistant — hanya bisa diakses oleh `super_admin`.

- Pilih provider: Groq / OpenAI / Google Gemini / Anthropic
- Input & simpan API key secara aman
- Pilih model AI yang digunakan
- Test koneksi API langsung dari UI
- Atur nama & persona asisten AI
- Upload ikon AI custom

---

### 10. 🗓️ Workspace / Planner
Workspace kolaborasi tim untuk perencanaan proyek dan jadwal kerja.

- Planner board & task management
- Deadline tracker dari kontrak klien aktif
- Notes, brainstorm canvas, checklist

---

## 🚀 Deployment

```bash
# Pull update terbaru ke server
ssh -p [PORT] [USER]@[HOST] \
  "cd domains/internal.hvmdigital.id/public_html && \
   git fetch origin && \
   git reset --hard origin/master"
```

---

## 🛠️ Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | PHP 8.x (Procedural + MySQLi) |
| Database | MariaDB 11.x |
| Frontend | HTML5, Vanilla CSS, Vanilla JS |
| Fonts | Google Fonts — Montserrat |
| Icons | Font Awesome 6 |
| AI Provider | Groq (default), OpenAI, Gemini, Anthropic |
| Hosting | Hostinger |
| Version Control | Git + GitHub |

---

## 🔐 Security & Privacy

- Login berbasis PHP session dengan enkripsi password
- Role-based access: `super_admin` (full access) / `admin` (limited)
- File sensitif dan konfigurasi server sudah di-exclude via `.gitignore`
- API Key AI tersimpan di database, tidak di file kode
- HTTPS enforced

---

<div align="center">
  <br/>
  <img src="https://internal.hvmdigital.id/uploads/logohvm.png" alt="HVM Digital" height="40"/>
  <br/><br/>
  <sub>Built with ❤️ by <strong>HVM Digital Dev Team</strong> — Internal use only.</sub>
  <br/>
  <sub>© 2025–2026 HVM Digital. All rights reserved.</sub>
</div>
