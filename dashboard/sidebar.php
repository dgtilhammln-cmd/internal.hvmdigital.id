<?php
$current_page = $_SERVER['REQUEST_URI'];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/dashboard/sidebar.style.css">

<nav class="sidebar">
    <!-- Logo -->
    <div class="logo-container">
        <!-- Minimized: icon only -->
        <div class="logo-min" style="display:flex;align-items:center;justify-content:center;">
            <svg width="34" height="34" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="20" cy="20" r="20" fill="#1a1a1a"/>
                <circle cx="20" cy="20" r="18" fill="none" stroke="#a1ff5a" stroke-width="1.5"/>
                <path d="M10 13H15V20H25V13H30V27H25V22H15V27H10V13Z" fill="#a1ff5a"/>
            </svg>
        </div>
        <!-- Full: text + icon -->
        <div class="logo-full" style="display:flex;align-items:center;gap:10px;">
            <svg width="34" height="34" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="20" cy="20" r="20" fill="#1a1a1a"/>
                <circle cx="20" cy="20" r="18" fill="none" stroke="#a1ff5a" stroke-width="1.5"/>
                <path d="M10 13H15V20H25V13H30V27H25V22H15V27H10V13Z" fill="#a1ff5a"/>
            </svg>
            <div style="line-height:1.1;">
                <div style="font-family:'Montserrat',sans-serif;font-size:1.05rem;font-weight:800;color:#fff;letter-spacing:0.5px;">HVM<span style="color:#a1ff5a;">Digital</span></div>
                <div style="font-size:0.58rem;color:#666;font-weight:500;letter-spacing:1px;text-transform:uppercase;">Digital &amp; IT Solution</div>
            </div>
        </div>
    </div>

    <!-- Nav List tetap satu kontainer untuk memudahkan transisi -->
    <div class="nav-list">
        <a href="/dashboard/" class="nav-item <?= (strpos($current_page, 'overview.php')) ? 'active' : '' ?>" title="Overview">
            <i class="fas fa-th-large"></i>
            <span class="nav-text">Overview</span>
        </a>

        <a href="/dashboard/performance/" class="nav-item <?= (strpos($current_page, 'performance')) ? 'active' : '' ?>" title="Performance">
            <i class="fas fa-chart-line"></i>
            <span class="nav-text">Performance</span>
        </a>

        <a href="/dashboard/clients/" class="nav-item <?= (strpos($current_page, 'clients')) ? 'active' : '' ?>" title="Clients">
            <i class="fas fa-users"></i>
            <span class="nav-text">Clients</span>
        </a>

        <a href="/dashboard/payment/" class="nav-item <?= (strpos($current_page, 'payment')) ? 'active' : '' ?>" title="Payment">
            <i class="fas fa-wallet"></i>
            <span class="nav-text">Payment</span>
        </a>
        
                <a href="/dashboard/invoice/" class="nav-item <?= (strpos($current_page, 'teams')) ? 'active' : '' ?>" title="Invoice">
            <i class="fas fa-file-invoice"></i>
            <span class="nav-text">Invoice</span>

        <a href="/dashboard/teams/" class="nav-item <?= (strpos($current_page, 'teams')) ? 'active' : '' ?>" title="Teams">
            <i class="fas fa-user-friends"></i>
            <span class="nav-text">Teams</span>
        </a>

        <a href="/dashboard/logout.php" class="nav-item logout-item" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</nav>