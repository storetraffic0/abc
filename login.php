<?php
require_once __DIR__ . '/includes/functions.php';
$APP_SETTINGS = load_app_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - <?php echo e($APP_SETTINGS['platform_title']); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo e($APP_SETTINGS['platform_favicon']); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #2e59d9;
            --accent-color: #36b9cc;
            --success-color: #1cc88a;
            --gradient-primary: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            --gradient-secondary: linear-gradient(135deg, var(--accent-color) 0%, #2c8fa5 100%);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #141E30;
            background: linear-gradient(to right, #243B55, #141E30);
            height: 100vh;
            overflow-x: hidden;
            position: relative;
        }
        
        /* Animated background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.5;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.1);
            animation: float 15s infinite linear;
        }
        
        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; }
        }
        
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            position: relative;
            z-index: 10;
            transform: translateY(0);
            transition: transform 0.5s ease, box-shadow 0.5s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.3);
        }
        
        .login-header {
            background: var(--gradient-primary);
            padding: 30px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.1) 50%, rgba(255, 255, 255, 0) 100%);
            transform: rotate(45deg);
            animation: shimmer 3s infinite;
            z-index: 1;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        
        .login-header img {
            max-height: 70px;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.2));
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .login-header h1 {
            color: #fff;
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
        }
        
        .login-body {
            padding: 35px 30px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating input {
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            padding: 15px 20px;
            height: 60px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
        }
        
        .form-floating input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.1);
            transform: translateY(-2px);
        }
        
        .form-floating label {
            padding: 15px 20px;
            color: #999;
        }
        
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            transform: scale(0.85) translateY(-0.75rem) translateX(0.15rem);
            color: var(--primary-color);
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .form-check-label {
            font-size: 14px;
            cursor: pointer;
            user-select: none;
            color: #666;
        }
        
        .btn-login {
            border-radius: 12px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: var(--gradient-primary);
            border: none;
            color: #fff;
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::after {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.1) 50%, rgba(255, 255, 255, 0) 100%);
            transform: rotate(45deg);
            animation: shimmer-btn 2s infinite;
            z-index: 1;
        }
        
        @keyframes shimmer-btn {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(78, 115, 223, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 4px 15px rgba(78, 115, 223, 0.2);
        }
        
        .login-footer {
            padding: 15px 30px 25px;
            text-align: center;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: #999;
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .divider::before {
            margin-right: 15px;
        }
        
        .divider::after {
            margin-left: 15px;
        }
        
        .link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .link:hover {
            color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 20px;
            animation: fadeIn 0.5s;
            font-size: 14px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 576px) {
            .login-card {
                border-radius: 15px;
            }
            
            .login-header {
                padding: 25px 15px;
            }
            
            .login-body {
                padding: 25px 20px;
            }
            
            .login-header img {
                max-height: 60px;
            }
        }
        
        /* Icon positioning for input fields */
        .input-icon-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            z-index: 10;
            transition: all 0.3s ease;
        }
        
        .form-control:focus ~ .input-icon {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Animated background particles -->
    <div class="bg-animation" id="bg-animation"></div>

    <div class="login-wrapper">
        <div class="login-card animate__animated animate__fadeIn">
            <div class="login-header">
                <img src="<?php echo e($APP_SETTINGS['platform_logo']); ?>" alt="Logo" class="animate__animated animate__fadeInDown">
                <h1 class="animate__animated animate__fadeIn animate__delay-1s">Welcome Back!</h1>
            </div>
            
            <div class="login-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success text-center animate__animated animate__fadeIn">
                        <i class="fas fa-check-circle me-2"></i> Registration successful! Please log in.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger text-center animate__animated animate__fadeIn">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo e($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <form class="animate__animated animate__fadeIn animate__delay-1s" action="auth_user.php" method="POST">
                    <div class="form-floating input-icon-wrapper">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                        <label for="username">Username</label>
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                    </div>
                    
                    <div class="form-floating input-icon-wrapper">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password">Password</label>
                        <span class="input-icon" id="togglePassword">
                            <i class="fas fa-eye-slash"></i>
                        </span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                            <label class="form-check-label" for="rememberMe">Remember Me</label>
                        </div>
                        <a href="admin/forgot-password.php" class="link">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-login w-100">
                        <span class="position-relative" style="z-index: 2">Sign In <i class="fas fa-sign-in-alt ms-2"></i></span>
                    </button>
                </form>
            </div>
            
            <div class="login-footer">
                <div class="divider">or</div>
                <p class="mb-0">Don't have an account? <a href="register.php" class="link">Create One <i class="fas fa-arrow-right ms-1"></i></a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add animated particles to background
        document.addEventListener('DOMContentLoaded', function() {
            const bgAnimation = document.getElementById('bg-animation');
            
            // Create particles
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random size
                const size = Math.floor(Math.random() * 100) + 50; // 50-150px
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Random position
                const posX = Math.floor(Math.random() * 100);
                const posY = Math.floor(Math.random() * 100);
                particle.style.left = `${posX}%`;
                particle.style.top = `${posY}%`;
                
                // Random animation duration
                const duration = Math.floor(Math.random() * 15) + 15; // 15-30s
                particle.style.animationDuration = `${duration}s`;
                
                // Random animation delay
                const delay = Math.floor(Math.random() * 10);
                particle.style.animationDelay = `${delay}s`;
                
                bgAnimation.appendChild(particle);
            }
            
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Toggle eye icon
                    const eyeIcon = this.querySelector('i');
                    eyeIcon.classList.toggle('fa-eye-slash');
                    eyeIcon.classList.toggle('fa-eye');
                });
            }
        });
    </script>
</body>
</html>