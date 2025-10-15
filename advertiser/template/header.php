<?php
// Keamanan: Pastikan hanya advertiser yang bisa akses
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'advertiser') {
    header("Location: /login.php");
    exit;
}

require_once __DIR__ . '/../../includes/functions.php';
$APP_SETTINGS = load_app_settings();

// Ambil nama advertiser yang sedang login
$pdo = get_db_connection();
$stmt_adv_name = $pdo->prepare("SELECT company_name FROM advertisers WHERE user_id = ?");
$stmt_adv_name->execute([$_SESSION['user_id']]);
$advertiser_name = $stmt_adv_name->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo e($page_title ?? 'Advertiser Panel'); ?> - <?php echo e($APP_SETTINGS['platform_title']); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo e($APP_SETTINGS['platform_favicon']); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/1.1.2/css/bootstrap-multiselect.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<style>
.bg-primary {
    background-color: #00ff11 !important;
}
.bg-secondary {
    background-color: #ffebb8 !important;
}
</style>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include 'menu.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow px-4">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3"><i class="fa fa-bars"></i></button>
                    <ul class="navbar-nav ms-auto">
                       <li class="nav-item dropdown no-arrow">
    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
        <span class="me-2 d-none d-lg-inline text-gray-600 small"><?php echo e($advertiser_name ?? 'Advertiser'); ?></span>
        <i class="fas fa-user-circle fa-2x text-gray-400"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-end shadow">
        <a class="dropdown-item" href="profile.php">
            <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
            Profile
        </a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="logout.php">
            <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
            Logout
        </a>
    </div>
</li>
                    </ul>
                </nav>
                <div class="container-fluid">