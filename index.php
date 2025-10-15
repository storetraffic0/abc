<?php
// Memuat fungsi global untuk mengambil pengaturan aplikasi (judul, logo, dll.)
require_once __DIR__ . '/includes/functions.php';
$APP_SETTINGS = load_app_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?php echo e($APP_SETTINGS['platform_title']); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo e($APP_SETTINGS['platform_favicon']); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .hero-section {
            background-color: #4e73df;
            background-image: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            background-size: cover;
            color: white;
            padding: 100px 0;
        }
        .hero-section h1 {
            font-weight: 700;
            font-size: 3.5rem;
        }
        .hero-section p {
            font-size: 1.25rem;
            opacity: 0.9;
        }
        .features-section {
            padding: 80px 0;
        }
        .feature-icon {
            font-size: 3rem;
            color: #4e73df;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="<?php echo e($APP_SETTINGS['platform_logo']); ?>" alt="Logo" height="30" class="me-2">
                <span class="fw-bold"><?php echo e($APP_SETTINGS['platform_title']); ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-lg-3" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-section text-center">
        <div class="container">
            <h1 class="display-4">Monetize Your Audience, Maximize Your Revenue</h1>
            <p class="lead my-4">A powerful and intuitive ad serving platform for publishers and advertisers.</p>
            <a href="register.php" class="btn btn-light btn-lg fw-bold px-4 me-2">Get Started</a>
            <a href="login.php" class="btn btn-outline-light btn-lg px-4">Sign In</a>
        </div>
    </header>

    <main>
        <section class="features-section">
            <div class="container text-center">
                <h2 class="mb-5">Why Choose Us?</h2>
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <i class="fas fa-bullseye feature-icon mb-3"></i>
                        <h3>Advanced Targeting</h3>
                        <p class="text-muted">Reach the right audience with our detailed targeting options, including Geo, Device, OS, Browser, and Site Category.</p>
                    </div>
                    <div class="col-md-4 mb-4">
                        <i class="fas fa-chart-line feature-icon mb-3"></i>
                        <h3>Real-time Analytics</h3>
                        <p class="text-muted">Track your performance with our dynamic and filterable reports. Understand your data to make smarter decisions.</p>
                    </div>
                    <div class="col-md-4 mb-4">
                        <i class="fas fa-users-cog feature-icon mb-3"></i>
                        <h3>Full Control</h3>
                        <p class="text-muted">Complete management panels for Admins, Publishers, and Advertisers give you full control over your advertising ecosystem.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-dark text-white text-center p-4">
        <div class="container">
            <?php
                $copyright_text = !empty($APP_SETTINGS['copyright_text']) ? e($APP_SETTINGS['copyright_text']) : 'Copyright &copy; ' . e(ucfirst($_SERVER['HTTP_HOST'])) . ' ' . date('Y');
                echo "<span>{$copyright_text}</span>";
            ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>