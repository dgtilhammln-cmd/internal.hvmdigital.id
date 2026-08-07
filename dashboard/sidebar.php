<?php
$current_page = $_SERVER['REQUEST_URI'];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/dashboard/sidebar.style.css">

<nav class="sidebar">
    <!-- Logo hanya muncul di Desktop/Minimize Sidebar -->
    <div class="logo-container">
        <img src="/uploads/icon.png" class="logo-min" alt="Icon">
        <img src="/uploads/logohvm.png" class="logo-full" alt="Logo">
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