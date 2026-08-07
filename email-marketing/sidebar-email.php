<?php
// Mendapatkan halaman aktif untuk indikator menu
$page = $_GET['page'] ?? 'campaign';
?>

<style>
:root {
    --bg-glass: rgba(0, 0, 0, 0.85); 
    --border-glass: rgba(255, 255, 255, 0.12);
    --primary-green: #a1ff5a;
    --text-gray: #a0a0a0;
    --active-bg: rgba(161, 255, 90, 0.1);
}

.sidebar {
    position: fixed;
    z-index: 9999;
    background: var(--bg-glass);
    backdrop-filter: blur(25px);
    border: 1px solid var(--border-glass);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
}

/* --- DESKTOP VIEW --- */
@media (min-width: 769px) {
    .sidebar {
        left: 20px; top: 20px; bottom: 20px;
        width: 85px; border-radius: 30px;
        display: flex; flex-direction: column; padding: 25px 0;
        overflow: hidden;
    }
    .sidebar:hover { width: 260px; }
    .logo-container { height: 60px; display: flex; align-items: center; justify-content: center; margin-bottom: 30px; }
    .logo-min { height: 35px; width: auto; transition: 0.3s; }
    .logo-full { display: none; height: 38px; width: auto; }
    .sidebar:hover .logo-min { display: none; }
    .sidebar:hover .logo-full { display: block; }
    .nav-list { list-style: none; width: 100%; flex-grow: 1; display: flex; flex-direction: column; }
    .nav-item {
        display: flex; align-items: center; text-decoration: none;
        height: 55px; margin: 5px 15px; border-radius: 18px;
        color: var(--text-gray); transition: 0.3s; white-space: nowrap;
    }
    .nav-item i { min-width: 55px; display: flex; justify-content: center; font-size: 1.25rem; }
    .nav-text { opacity: 0; font-weight: 600; font-size: 14px; transition: 0.3s; }
    .sidebar:hover .nav-text { opacity: 1; }
    .nav-item:hover, .nav-item.active { background: var(--active-bg); color: #fff; }
    .nav-item.active i { color: var(--primary-green); text-shadow: 0 0 10px var(--primary-green); }
    .logout-item { margin-top: auto; color: #ff4d4d !important; }
}

/* --- MOBILE VIEW (BOTTOM) --- */
@media (max-width: 768px) {
    .sidebar {
        bottom: 15px; left: 50%; transform: translateX(-50%);
        width: calc(100% - 40px); max-width: 500px; height: 65px;
        border-radius: 25px; display: flex; flex-direction: row;
        justify-content: space-evenly; align-items: center; padding: 0 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .logo-container, .nav-text { display: none; }
    .nav-list { display: flex; width: 100%; justify-content: space-between; align-items: center; }
    .nav-item { text-decoration: none; color: var(--text-gray); font-size: 1.3rem; width: 45px; height: 45px; display: flex; justify-content: center; align-items: center; border-radius: 15px; }
    .nav-item.active { color: var(--primary-green); background: rgba(161, 255, 90, 0.1); transform: translateY(-5px); }
}
</style>

<nav class="sidebar">
    <div class="logo-container">
        <img src="/uploads/icon.png" class="logo-min" alt="Icon">
        <img src="/uploads/logohvm.png" class="logo-full" alt="Logo">
    </div>
    <div class="nav-list">
        <!-- Buat Campaign -->
        <a href="?page=campaign" class="nav-item <?= ($page == 'campaign') ? 'active' : '' ?>" title="New Campaign">
            <i class="fas fa-paper-plane"></i>
            <span class="nav-text">New Campaign</span>
        </a>

        <!-- List Antrean -->
        <a href="?page=queue_logs" class="nav-item <?= ($page == 'queue_logs') ? 'active' : '' ?>" title="Queue System">
            <i class="fas fa-tasks"></i>
            <span class="nav-text">Queue System</span>
        </a>

        <!-- Tracking -->
        <a href="?page=tracking" class="nav-item <?= ($page == 'tracking') ? 'active' : '' ?>" title="Link Tracking">
            <i class="fas fa-chart-line"></i>
            <span class="nav-text">Link Tracking</span>
        </a>

        <!-- Engine Control (MENU BARU) -->
        <a href="?page=settings" class="nav-item <?= ($page == 'settings') ? 'active' : '' ?>" title="Engine Control">
            <i class="fas fa-sliders-h"></i>
            <span class="nav-text">Engine Control</span>
        </a>
        
        <!-- Engine Control (MENU BARU) -->
        <a href="?page=warm_leads" class="nav-item <?= ($page == 'warm_leads') ? 'active' : '' ?>" title="Leads">
            <i class="fas fa-bullseye"></i>
            <span class="nav-text">Leads</span>
        </a>

        <!-- Back to Dashboard Utama -->
        <a href="/dashboard/" class="nav-item logout-item" title="Back to Main">
            <i class="fas fa-arrow-left"></i>
            <span class="nav-text">Main Dashboard</span>
        </a>
    </div>
</nav>