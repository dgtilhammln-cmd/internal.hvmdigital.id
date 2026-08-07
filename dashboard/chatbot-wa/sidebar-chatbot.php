<?php
// Mendapatkan URI saat ini untuk logika class 'active'
$current_page = $_SERVER['REQUEST_URI'];
?>

<!-- FONT & ICONS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* --- SIDEBAR BASE STYLES --- */
:root {
    --bg-glass: rgba(0, 0, 0, 0.85); 
    --border-glass: rgba(255, 255, 255, 0.12);
    --primary-green: #00ff88;
    --text-gray: #a0a0a0;
    --active-bg: rgba(255, 255, 255, 0.08);
}

.sidebar {
    position: fixed;
    z-index: 99999; /* Pastikan di atas elemen nebula */
    background: var(--bg-glass);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border: 1px solid var(--border-glass);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
}

/* --- TAMPILAN DESKTOP (SIDEBAR KIRI) --- */
@media (min-width: 769px) {
    .sidebar {
        left: 20px;
        top: 20px;
        bottom: 20px;
        width: 85px;
        border-radius: 30px;
        display: flex;
        flex-direction: column;
        padding: 25px 0;
        overflow: hidden;
    }

    .sidebar:hover { width: 260px; }

    .logo-container {
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 30px;
    }

    .logo-min { height: 35px; width: auto; transition: 0.3s; }
    .logo-full { display: none; height: 38px; width: auto; }
    
    .sidebar:hover .logo-min { display: none; }
    .sidebar:hover .logo-full { display: block; }

    .nav-list { 
        list-style: none; 
        width: 100%; 
        flex-grow: 1; 
        display: flex;
        flex-direction: column;
    }
    
    .nav-item {
        display: flex;
        align-items: center;
        text-decoration: none;
        height: 55px;
        margin: 5px 15px;
        border-radius: 18px;
        color: var(--text-gray);
        transition: 0.3s;
        white-space: nowrap;
    }

    .nav-item i {
        min-width: 55px; 
        display: flex;
        justify-content: center;
        font-size: 1.25rem;
    }

    .nav-text { opacity: 0; font-weight: 500; font-size: 14px; transition: 0.3s; }
    .sidebar:hover .nav-text { opacity: 1; }
    
    .nav-item:hover, .nav-item.active { background: var(--active-bg); color: #fff; }
    .nav-item.active i { color: var(--primary-green); text-shadow: 0 0 10px var(--primary-green); }
    
    .logout-item { margin-top: auto; color: #ff4d4d !important; }
}

/* --- TAMPILAN MOBILE (BOTTOM PILL NAVIGATION) --- */
@media (max-width: 768px) {
    .sidebar {
        bottom: 15px;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 40px);
        max-width: 450px;
        height: 65px;
        border-radius: 25px;
        display: flex;
        flex-direction: row;
        justify-content: space-evenly;
        align-items: center;
        padding: 0 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }

    .logo-container, .nav-text { display: none; }

    .nav-list {
        display: flex;
        width: 100%;
        justify-content: space-around;
        align-items: center;
        height: 100%;
    }

    .nav-item {
        text-decoration: none;
        color: var(--text-gray);
        font-size: 1.3rem;
        width: 50px;
        height: 50px;
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 15px;
        transition: 0.3s ease;
    }

    .nav-item.active {
        color: var(--primary-green);
        background: rgba(0, 255, 136, 0.1);
        transform: translateY(-5px);
    }

    .logout-item { color: #ff4d4d !important; }

    /* Mencegah konten tertutup nav bar di mobile */
    .main-content {
        padding-bottom: 100px !important;
    }
}
</style>

<nav class="sidebar">
    <!-- LOGO AREA -->
    <div class="logo-container">
        <img src="/uploads/icon.png" class="logo-min" alt="Icon">
        <img src="/uploads/logohvm.png" class="logo-full" alt="Logo">
    </div>

    <!-- MENU LIST -->
    <div class="nav-list">
        
        <!-- 1. Command Center (Home Monitoring) -->
        <a href="/dashboard/chatbot-wa/index.php?page=command" 
           class="nav-item <?= ($page == 'command') ? 'active' : '' ?>" title="Nebula Command Center">
            <i class="fas fa-robot"></i>
            <span class="nav-text">Nebula Bot</span>
        </a>

        <!-- 2. QR Scanner (Remote Connection) -->
        <a href="/dashboard/chatbot-wa/index.php?page=qr" 
           class="nav-item <?= ($page == 'qr') ? 'active' : '' ?>" title="Remote QR Scanner">
            <i class="fas fa-qrcode"></i>
            <span class="nav-text">QR Scanner</span>
        </a>

        <!-- 3. AI Training (Knowledge Base) -->
        <a href="/dashboard/chatbot-wa/index.php?page=training" 
           class="nav-item <?= ($page == 'training') ? 'active' : '' ?>" title="Neural Training">
            <i class="fas fa-brain"></i>
            <span class="nav-text">AI Training</span>
        </a>

        <!-- 4. Automation (Outbound Tasks) -->
        <a href="/dashboard/chatbot-wa/index.php?page=automation" 
           class="nav-item <?= ($page == 'automation') ? 'active' : '' ?>" title="Automation Tasks">
            <i class="fas fa-bolt"></i>
            <span class="nav-text">Automation</span>
        </a>

        <!-- 5. Back to Main Dashboard -->
        <a href="/dashboard/" class="nav-item back-item" title="Exit to Overview">
            <i class="fas fa-arrow-left"></i>
            <span class="nav-text">Back to Home</span>
        </a>
    </div>
</nav>