<?php
// Memuat fungsi global dan koneksi DB
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';

// Ambil pengaturan aplikasi untuk judul dan logo
$APP_SETTINGS = load_app_settings();
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil semua data dari form
    $role = $_POST['role'] ?? '';
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $company_name = trim($_POST['company_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $country = $_POST['country'];
    $whatsapp = trim($_POST['contact_whatsapp']);
    $telegram = trim($_POST['contact_telegram']);
    $skype = trim($_POST['contact_skype']);
    $terms = isset($_POST['terms']);

    // Validasi
    if (!$terms) {
        $error_message = 'You must agree to the Terms of Service.';
    } elseif (empty($first_name) || empty($last_name) || empty($company_name) || empty($email) || empty($username) || empty($password) || empty($country)) {
        $error_message = 'Please fill all required fields.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Password and confirmation do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif (!in_array($role, ['publisher', 'advertiser'])) {
        $error_message = 'Please select a valid role.';
    } else {
        $pdo = get_db_connection();
        try {
            // Cek duplikasi username atau email
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt_check->execute([$username, $email]);
            if ($stmt_check->fetch()) {
                throw new Exception("Username or Email is already taken.");
            }

            $pdo->beginTransaction();

            // Insert ke tabel users dengan semua data baru
            $sql_user = "INSERT INTO users (username, first_name, last_name, password_hash, email, country, contact_whatsapp, contact_telegram, contact_skype, role) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_user = $pdo->prepare($sql_user);
            // PENTING: Di lingkungan produksi, ganti '$password' dengan password_hash($password, PASSWORD_DEFAULT)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_user->execute([$username, $first_name, $last_name, $hashed_password, $email, $country, $whatsapp, $telegram, $skype, $role]);
            $user_id = $pdo->lastInsertId();

            // Insert ke tabel publisher atau advertiser
            if ($role === 'publisher') {
                $stmt_details = $pdo->prepare("INSERT INTO publishers (user_id, company_name, status) VALUES (?, ?, 'pending')");
                $stmt_details->execute([$user_id, $company_name]);
            } elseif ($role === 'advertiser') {
                $stmt_details = $pdo->prepare("INSERT INTO advertisers (user_id, company_name) VALUES (?, ?)");
                $stmt_details->execute([$user_id, $company_name]);
            }

            $pdo->commit();
            header("Location: login.php?success=1");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Register - <?php echo e($APP_SETTINGS['platform_title']); ?></title>
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
        
        .register-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 600px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            position: relative;
            z-index: 10;
            transform: translateY(0);
            transition: transform 0.5s ease, box-shadow 0.5s ease;
        }
        
        .register-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.3);
        }
        
        .register-header {
            background: var(--gradient-primary);
            padding: 30px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .register-header::before {
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
        
        .register-header img {
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
        
        .register-header h1 {
            color: #fff;
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
        }
        
        .register-body {
            padding: 35px 30px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating input, .form-floating select {
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            padding: 15px 20px;
            height: 60px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
        }
        
        .form-floating input:focus, .form-floating select:focus {
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
        
        .role-selection {
            margin-bottom: 25px;
        }
        
        .role-selection .form-check {
            display: inline-block;
            margin: 0 15px;
        }
        
        .role-selection label {
            font-weight: 500;
            color: #666;
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
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-register {
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
        
        .btn-register::after {
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
        
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(78, 115, 223, 0.4);
        }
        
        .btn-register:active {
            transform: translateY(0);
            box-shadow: 0 4px 15px rgba(78, 115, 223, 0.2);
        }
        
        .register-footer {
            padding: 15px 30px 25px;
            text-align: center;
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
        
        .contact-section {
            background: rgba(248, 249, 250, 0.5);
            padding: 20px;
            border-radius: 12px;
            margin-top: 10px;
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
        
        .form-floating:focus-within .input-icon {
            color: var(--primary-color);
        }
        
        @media (max-width: 576px) {
            .register-card {
                border-radius: 15px;
            }
            
            .register-header {
                padding: 25px 15px;
            }
            
            .register-body {
                padding: 25px 20px;
            }
            
            .register-header img {
                max-height: 60px;
            }
            
            .role-selection .form-check {
                margin: 0 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated background particles -->
    <div class="bg-animation" id="bg-animation"></div>

    <div class="register-wrapper">
        <div class="register-card animate__animated animate__fadeIn">
            <div class="register-header">
                <img src="<?php echo e($APP_SETTINGS['platform_logo']); ?>" alt="Logo" class="animate__animated animate__fadeInDown">
                <h1 class="animate__animated animate__fadeIn animate__delay-1s">Create an Account!</h1>
            </div>
            
            <div class="register-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger text-center animate__animated animate__fadeIn">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo e($error_message); ?>
                    </div>
                <?php endif; ?>

                <form class="animate__animated animate__fadeIn animate__delay-1s" method="POST" id="registerForm">
                    <div class="role-selection text-center">
                        <div class="mb-2 small text-muted">I am a...</div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="role" id="role_publisher" value="publisher" checked>
                            <label class="form-check-label" for="role_publisher">Publisher</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="role" id="role_advertiser" value="advertiser">
                            <label class="form-check-label" for="role_advertiser">Advertiser</label>
                        </div>
                    </div>
                    
                    <hr class="divider">
                    
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First Name" required>
                                <label for="first_name">First Name</label>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last Name" required>
                                <label for="last_name">Last Name</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="company_name" name="company_name" placeholder="Company Name" required>
                        <label for="company_name">Company Name</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required>
                        <label for="email">Email Address</label>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                                <label for="username">Username</label>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="form-floating">
                                <select class="form-control" id="country" name="country" required>
                                    <option value="" disabled selected>Select Country</option>
                                    <option value="ID">Indonesia</option>
                                    <option value="US">United States</option>
                                    <option value="SG">Singapore</option>
                                    <option value="MY">Malaysia</option>
                                    <option value="VN">Vietnam</option>
                                    <option value="TH">Thailand</option>
                                    <option value="PH">Philippines</option>
                                </select>
                                <label for="country">Country</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password">Password (min. 6 characters)</label>
                                <span class="input-icon" id="togglePassword">
                                    <i class="fas fa-eye-slash"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="form-floating">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Repeat Password" required>
                                <label for="confirm_password">Repeat Password</label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="password_error" class="alert alert-danger p-2 small animate__animated animate__fadeIn" style="display: none;">
                        Passwords do not match.
                    </div>
                    
                    <div class="contact-section">
                        <h6 class="text-center small text-muted mb-3">Contact Details (Optional)</h6>
                        <div class="row">
                            <div class="col-sm-4 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="contact_whatsapp" name="contact_whatsapp" placeholder="WhatsApp">
                                    <label for="contact_whatsapp">WhatsApp</label>
                                </div>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="contact_telegram" name="contact_telegram" placeholder="Telegram">
                                    <label for="contact_telegram">Telegram</label>
                                </div>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="contact_skype" name="contact_skype" placeholder="Skype">
                                    <label for="contact_skype">Skype</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check mt-4">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" class="tos-link text-primary">Terms of Service</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-register w-100 mt-4">
                        <span class="position-relative" style="z-index: 2">Register Account <i class="fas fa-user-plus ms-2"></i></span>
                    </button>
                </form>
            </div>
            
            <div class="register-footer">
                <p class="mb-0">Already have an account? <a href="login.php" class="link">Sign In <i class="fas fa-arrow-right ms-1"></i></a></p>
            </div>
        </div>
    </div>

    <!-- Publisher Terms Modal -->
    <div class="modal fade" id="publisherTermsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Publisher Terms of Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Last Updated: October 3, 2025</strong></p>
                    <p>Welcome to <?php echo e($APP_SETTINGS['platform_title']); ?>! These Publisher Terms of Service ("Terms") govern your access to and use of our advertising services ("Services") as a Publisher. By creating an account and using our Services, you agree to be bound by these Terms.</p>
                    <hr>
                    <h5>1. Account Registration and Responsibilities</h5>
                    <ul>
                        <li><strong>Eligibility:</strong> You must be at least 18 years of age and have the legal authority to enter into this agreement.</li>
                        <li><strong>Accurate Information:</strong> You agree to provide accurate, current, and complete information during the registration process and to update such information to keep it accurate.</li>
                        <li><strong>Account Security:</strong> You are responsible for safeguarding your password and for all activities that occur under your account. You must notify us immediately of any unauthorized use of your account.</li>
                    </ul>
                    <h5>2. Site & Content Requirements</h5>
                    <ul>
                        <li><strong>Site Approval:</strong> All websites ("Sites") you submit must be approved by us. We reserve the right to reject or remove any Site at our sole discretion.</li>
                        <li><strong>Prohibited Content:</strong> Your Sites may not contain, promote, or link to any of the following: illegal content, adult or pornographic material, hate speech, violence, harassment, malware, or any content that infringes upon intellectual property rights.</li>
                        <li><strong>Ad Placement:</strong> You agree to place our ad codes on your approved Sites in a manner that is not misleading or designed to generate fraudulent impressions or clicks. You may not alter the ad code provided.</li>
                    </ul>
                    <h5>3. Payments & Earnings</h5>
                    <ul>
                        <li><strong>Earnings Calculation:</strong> Your earnings will be calculated based on the valid impressions and/or clicks reported by our system, multiplied by the applicable rates (CPM/CPC) and your specified revenue share. We are the sole arbiter of what constitutes a valid impression or click.</li>
                        <li><strong>Payment Threshold:</strong> Payments will be processed once your accrued earnings reach the minimum withdrawal threshold, as specified in your Publisher Panel.</li>
                        <li><strong>Payment Schedule:</strong> Payments for withdrawal requests are typically processed within a 15-30 business day period after the request is approved.</li>
                        <li><strong>Invalid Traffic:</strong> We reserve the right to withhold payment or charge back your account for any revenue generated from what we deem, in our sole discretion, to be invalid or fraudulent traffic.</li>
                        <li><strong>Taxes:</strong> You are solely responsible for paying any and all taxes associated with the revenue you earn through our Services.</li>
                    </ul>
                    <h5>4. Termination</h5>
                    <ul>
                        <li><strong>Termination by You:</strong> You may terminate this agreement at any time by removing all of our ad codes from your Sites and ceasing to use the Services.</li>
                        <li><strong>Termination by Us:</strong> We may terminate or suspend your account at any time, with or without cause, and with or without notice. Upon termination, your right to use the Services will immediately cease, and any unpaid earnings may be forfeited if the termination is due to a breach of these Terms.</li>
                    </ul>
                    <h5>5. Limitation of Liability</h5>
                    <p>Our Services are provided "as is." We make no warranties, expressed or implied, and hereby disclaim all other warranties. In no event shall <?php echo e($APP_SETTINGS['platform_title']); ?> be liable for any damages (including, without limitation, damages for loss of data or profit, or due to business interruption) arising out of the use or inability to use our Services.</p>
                    <h5>6. Changes to Terms</h5>
                    <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. We will provide notice of any changes by posting the new Terms on this site.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Advertiser Terms Modal -->
    <div class="modal fade" id="advertiserTermsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Advertiser Terms of Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Last Updated: October 3, 2025</strong></p>
                    <p>Welcome to <?php echo e($APP_SETTINGS['platform_title']); ?>! These Advertiser Terms of Service ("Terms") outline your rights and responsibilities when using our advertising services ("Services") to run campaigns. By creating an account and placing an ad campaign, you agree to these Terms.</p>
                    <hr>
                    <h5>1. Account & Campaigns</h5>
                    <ul>
                        <li><strong>Account Information:</strong> You agree to provide true, accurate, and complete information for your account and billing details.</li>
                        <li><strong>Campaign Responsibility:</strong> You are solely responsible for all ad content, targeting selections, and bids submitted through your account.</li>
                        <li><strong>Ad Content (Creatives):</strong> All ad creatives, including text, images, videos, and landing pages, must comply with our content policies.</li>
                    </ul>
                    <h5>2. Content & Landing Page Policies</h5>
                    <p>Your advertisements and the landing pages they link to may NOT contain, promote, or consist of:</p>
                    <ul>
                        <li>Content that is illegal, pornographic, fraudulent, or promotes hate speech, violence, or discrimination.</li>
                        <li>Malware, spyware, unwanted software, or any deceptive or harmful code.</li>
                        <li>Infringement on any third-party intellectual property rights, including copyrights and trademarks.</li>
                        <li>Misleading claims, fake testimonials, or "get-rich-quick" schemes.</li>
                        <li>Auto-downloads, unexpected pop-ups, or site-locking scripts that degrade the user experience.</li>
                    </ul>
                    <p>We reserve the right to reject or remove any ad or campaign at any time for any reason at our sole discretion.</p>
                    <h5>3. Payment & Billing</h5>
                    <ul>
                        <li><strong>Payment Obligation:</strong> You agree to pay for all impressions, clicks, or actions delivered through your campaigns according to the pricing model (CPM, CPC, etc.) and bid prices you have set.</li>
                        <li><strong>Billing Cycle:</strong> You will be billed according to the terms established in your advertiser panel (e.g., prepaid balance, monthly invoicing).</li>
                        <li><strong>Invalid Traffic:</strong> While we employ filtering systems, you understand that some level of invalid traffic may occur. We will not charge for traffic that our system determines to be definitively invalid or fraudulent. All determinations made by <?php echo e($APP_SETTINGS['platform_title']); ?> regarding traffic validity are final.</li>
                        <li><strong>Refunds:</strong> All payments are final and non-refundable, except as required by law or at our sole discretion.</li>
                    </ul>
                    <h5>4. Data & Privacy</h5>
                    <ul>
                        <li><strong>Campaign Data:</strong> You will have access to performance reports for your campaigns. You may use this data for internal analysis but may not resell it or disclose it to third parties as data originating from our platform.</li>
                        <li><strong>User Data:</strong> You may not use any data obtained through the Services to personally identify any user.</li>
                    </ul>
                    <h5>5. Termination</h5>
                    <p>We may terminate or suspend your account and access to the Services at our discretion, without prior notice or liability, for any reason, including a breach of these Terms. Upon termination, your obligation to pay for services already rendered shall survive.</p>
                    <h5>6. Limitation of Liability</h5>
                    <p>Our Services are provided "as is." We are not responsible for any lost profits or other consequential, special, indirect, or incidental damages arising out of or in connection with your campaigns, even if we have been advised of the possibility of such damages.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animated particles to background
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
            
            // Password validation
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordError = document.getElementById('password_error');
            
            function checkPasswords() {
                if (confirmPassword.value && password.value !== confirmPassword.value) {
                    passwordError.style.display = 'block';
                } else {
                    passwordError.style.display = 'none';
                }
            }
            
            password.addEventListener('input', checkPasswords);
            confirmPassword.addEventListener('input', checkPasswords);
            
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    
                    // Toggle eye icon
                    const eyeIcon = this.querySelector('i');
                    eyeIcon.classList.toggle('fa-eye-slash');
                    eyeIcon.classList.toggle('fa-eye');
                });
            }
            
            // Terms of Service modal handling
            const tosLink = document.querySelector('.tos-link');
            const publisherModal = new bootstrap.Modal(document.getElementById('publisherTermsModal'));
            const advertiserModal = new bootstrap.Modal(document.getElementById('advertiserTermsModal'));
            
            tosLink.addEventListener('click', function(event) {
                event.preventDefault();
                if (document.getElementById('role_publisher').checked) {
                    publisherModal.show();
                } else {
                    advertiserModal.show();
                }
            });
        });
    </script>
</body>
</html>