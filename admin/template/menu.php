<?php
// Dapatkan nama file saat ini untuk menandai menu aktif
$current_page = basename($_SERVER['PHP_SELF']);
?>
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboard.php">
        <div class="sidebar-brand-icon">
            <img src="<?php echo e($APP_SETTINGS['platform_logo'] ?? ''); ?>" alt="Logo" style="max-height: 40px;">
        </div>
    </a>

    <hr class="sidebar-divider my-0">

    <li class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <a class="nav-link" href="dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        Management
    </div>
    <li class="nav-item <?php echo in_array($current_page, ['campaigns.php', 'campaign_add.php', 'campaign_edit.php']) ? 'active' : ''; ?>">
        <a class="nav-link" href="campaigns.php"><i class="fas fa-fw fa-bullhorn"></i><span>Campaigns</span></a>
    </li>
    <li class="nav-item <?php echo in_array($current_page, ['advertisers.php', 'advertiser_add.php', 'advertiser_edit.php']) ? 'active' : ''; ?>">
        <a class="nav-link" href="advertisers.php"><i class="fas fa-fw fa-user-tie"></i><span>Advertisers</span></a>
    </li>
    <li class="nav-item <?php echo in_array($current_page, ['publishers.php', 'publisher_add.php', 'publisher_edit.php']) ? 'active' : ''; ?>">
        <a class="nav-link" href="publishers.php"><i class="fas fa-fw fa-globe"></i><span>Publishers</span></a>
    </li>
    <li class="nav-item <?php echo in_array($current_page, ['sites.php', 'site_add.php', 'site_edit.php']) ? 'active' : ''; ?>">
        <a class="nav-link" href="sites.php"><i class="fas fa-fw fa-desktop"></i><span>Sites</span></a>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        Reports
    </div>
    <li class="nav-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
        <a class="nav-link" href="reports.php"><i class="fas fa-fw fa-chart-area"></i><span>Statistics</span></a>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        Financial
    </div>
   <li class="nav-item <?php echo in_array($current_page, ['withdrawals.php']) ? 'active' : ''; ?>">
        <a class="nav-link" href="withdrawals.php">
            <i class="fas fa-fw fa-hand-holding-usd"></i>
            <span>Withdrawals</span>
        </a>
    </li>
    
    <li class="nav-item <?php echo ($current_page == 'transactions.php') ? 'active' : ''; ?>">
        <a class="nav-link" href="transactions.php">
            <i class="fas fa-fw fa-exchange-alt"></i>
            <span>Transactions</span>
        </a>
    </li>
    <hr class="sidebar-divider">

    <div class="sidebar-heading">
        System
    </div>
    <li class="nav-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
        <a class="nav-link" href="settings.php"><i class="fas fa-fw fa-cog"></i><span>Settings</span></a>
    </li>
    <li class="nav-item <?php echo in_array($current_page, ['payment_methods.php', 'payment_method_edit.php']) ? 'active' : ''; ?>">
        <a class="nav-link" href="payment_methods.php"><i class="fas fa-fw fa-credit-card"></i><span>Payment Methods</span></a>
    </li>
    <li class="nav-item <?php echo in_array($current_page, ['deposit_methods.php', 'deposit_method_edit.php']) ? 'active' : ''; ?>">
        <a class="nav-link" href="deposit_methods.php">
            <i class="fas fa-fw fa-money-bill-wave"></i>
            <span>Deposit Methods</span>
        </a>
    </li>

    <hr class="sidebar-divider d-none d-md-block">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>