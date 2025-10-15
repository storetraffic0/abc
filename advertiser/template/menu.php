<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboard.php">
        <div class="sidebar-brand-icon"><img src="<?php echo e($APP_SETTINGS['platform_logo'] ?? ''); ?>" alt="Logo" style="max-height: 40px;"></div>
    </a>
    <hr class="sidebar-divider my-0">
    <li class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <a class="nav-link" href="dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a>
    </li>
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Management</div>
    
     <li class="nav-item <?php echo in_array($current_page, ['campaigns.php', 'campaign_add.php', 'campaign_edit.php']) ? 'active' : ''; ?>">
        <a class="nav-link" href="campaigns.php"><i class="fas fa-fw fa-bullhorn"></i><span>My Campaigns</span></a>
    </li>
    
    <li class="nav-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
        <a class="nav-link" href="reports.php">
            <i class="fas fa-fw fa-chart-bar"></i>
            <span>Reports</span>
        </a>
    </li>

    <li class="nav-item <?php echo ($current_page == 'billing.php') ? 'active' : ''; ?>">
        <a class="nav-link" href="billing.php"><i class="fas fa-fw fa-wallet"></i><span>Billing</span></a>
    </li>
    <hr class="sidebar-divider d-none d-md-block">
    <div class="text-center d-none d-md-inline"><button class="rounded-circle border-0" id="sidebarToggle"></button></div>
</ul>