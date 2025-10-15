<?php
// Menggunakan PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Muat file PHPMailer
require '../includes/PHPMailer.php';
require '../includes/SMTP.php';
require '../includes/Exception.php';
require_once '../includes/db_connection.php';

// Ambil pengaturan dari database
try {
    $pdo = get_db_connection();
    $APP_SETTINGS = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) { die("Configuration error."); }

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    
    // Cek apakah email admin ada
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        // Buat token yang aman
        $token = bin2hex(random_bytes(50));
        $expires = new DateTime('NOW');
        $expires->add(new DateInterval('PT1H')); // Token berlaku 1 jam

        // Simpan token ke database
        $stmt_insert = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt_insert->execute([$email, $token, $expires->format('Y-m-d H:i:s')]);
        
        // Buat link reset
        $reset_link = rtrim($APP_SETTINGS['ad_tag_domain'], '/') . '/admin/reset-password.php?token=' . $token;

        // Kirim email menggunakan PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Pengaturan Server
            $mail->isSMTP();
            $mail->Host       = $APP_SETTINGS['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $APP_SETTINGS['smtp_username'];
            $mail->Password   = $APP_SETTINGS['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $APP_SETTINGS['smtp_port'];

            // Penerima
            $mail->setFrom($APP_SETTINGS['smtp_from_email'], $APP_SETTINGS['platform_title']);
            $mail->addAddress($email);

            // Konten
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request for ' . $APP_SETTINGS['platform_title'];
            $mail->Body    = "Hello,<br><br>You requested a password reset. Please click the link below to reset your password. This link is valid for 1 hour.<br><br><a href='{$reset_link}'>Reset Password</a><br><br>If you did not request this, please ignore this email.";
            $mail->AltBody = 'To reset your password, please visit the following URL: ' . $reset_link;

            $mail->send();
            $message = 'Password reset link has been sent to your email.';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            $message_type = 'danger';
        }
    } else {
        $message = 'If an account with that email exists, a reset link has been sent.';
        $message_type = 'success'; // Tampilkan pesan generik untuk keamanan
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-primary">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-8 col-md-9">
                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <div class="p-5">
                            <div class="text-center">
                                <h1 class="h4 text-gray-900 mb-4">Forgot Your Password?</h1>
                                <p class="mb-4">Enter your email address below and we will send you a link to reset your password.</p>
                            </div>
                            <?php if ($message): ?>
                                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                            <?php endif; ?>
                            <form class="user" method="POST">
                                <div class="form-group">
                                    <input type="email" class="form-control form-control-user" name="email" placeholder="Enter Email Address..." required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-user btn-block">
                                    Reset Password
                                </button>
                            </form>
                            <hr>
                            <div class="text-center">
                                <a class="small" href="index.php">Back to Login</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>