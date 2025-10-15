<?php
// Deklarasi namespace PHPMailer di scope global (paling atas)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start(); // Sesi dimulai setelah deklarasi 'use'

// Muat semua file yang dibutuhkan di awal
require_once '../includes/db_connection.php';
require_once '../includes/PHPMailer.php';
require_once '../includes/SMTP.php';
require_once '../includes/Exception.php';

// --- LOGIKA UNTUK MENGIRIM EMAIL TES ---
if (isset($_GET['action']) && $_GET['action'] === 'test_smtp') {
    $recipient_email = $_GET['test_email'] ?? null;

    if (filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        $pdo_test = get_db_connection();
        $settings_test = $pdo_test->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $mail = new PHPMailer(true);
        try {
            // Konfigurasi server dari settings
            $mail->isSMTP();
            $mail->Host       = $settings_test['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings_test['smtp_username'];
            $mail->Password   = $settings_test['smtp_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$settings_test['smtp_port'];

            // Pengirim dan Penerima
            $mail->setFrom($settings_test['smtp_from_email'], $settings_test['platform_title'] . ' Tester');
            $mail->addAddress($recipient_email); // Kirim ke email yang diinput manual

            // Konten Email
            $mail->isHTML(true);
            $mail->Subject = 'SMTP Test from ' . $settings_test['platform_title'];
            $mail->Body    = 'This is a test email to verify your SMTP settings. If you received this, your configuration is correct!';
            
            $mail->send();
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Test email sent successfully to ' . htmlspecialchars($recipient_email)];
        } catch (Exception $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "Failed to send email. Mailer Error: {$mail->ErrorInfo}"];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Invalid email address provided for testing.'];
    }

    // Redirect kembali untuk menghindari re-submit
    header("Location: settings.php");
    exit;
}


$page_title = 'Platform Settings';
include 'template/header.php';

$pdo = get_db_connection();
$success_message = '';
$error_message = '';

// --- PROSES UPDATE PENGATURAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $pdo->prepare($sql);

        // Simpan semua data dari POST
        foreach ($_POST as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => trim($value)]);
        }

        // --- Proses Upload File (Logo & Favicon) ---
        $upload_dir = __DIR__ . '/../assets/img/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if (isset($_FILES['platform_logo']) && $_FILES['platform_logo']['error'] == 0) {
            $logo_filename = 'logo' . '.' . pathinfo($_FILES['platform_logo']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['platform_logo']['tmp_name'], $upload_dir . $logo_filename);
            $stmt->execute([':key' => 'platform_logo', ':value' => '/assets/img/' . $logo_filename]);
        }
        if (isset($_FILES['platform_favicon']) && $_FILES['platform_favicon']['error'] == 0) {
            $favicon_filename = 'favicon' . '.' . pathinfo($_FILES['platform_favicon']['name'], PATHINFO_EXTENSION);
             move_uploaded_file($_FILES['platform_favicon']['tmp_name'], $upload_dir . $favicon_filename);
            $stmt->execute([':key' => 'platform_favicon', ':value' => '/assets/img/' . $favicon_filename]);
        }
        
        $pdo->commit();
        $success_message = "Settings saved successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Failed to save settings: " . $e->getMessage();
    }
}

// --- AMBIL SEMUA PENGATURAN DARI DATABASE ---
$all_settings = [];
try {
    // Gunakan fungsi global yang sudah kita buat
    $all_settings = load_app_settings();
} catch (PDOException $e) {
    $error_message = "Could not load settings.";
}
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

<?php
if (isset($_SESSION['flash_message'])) {
    echo '<div class="alert alert-' . $_SESSION['flash_message']['type'] . '">' . htmlspecialchars($_SESSION['flash_message']['text']) . '</div>';
    unset($_SESSION['flash_message']);
}
?>
<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>


<form method="POST" enctype="multipart/form-data">
    <div class="card shadow mb-4">
        <div class="card-header">General Settings</div>
        <div class="card-body">
            <div class="mb-3"><label for="platform_title" class="form-label">Platform Title</label><input type="text" class="form-control" id="platform_title" name="platform_title" value="<?php echo htmlspecialchars($all_settings['platform_title'] ?? ''); ?>"></div>
            <div class="mb-3"><label for="platform_logo" class="form-label">Platform Logo</label><input class="form-control" type="file" id="platform_logo" name="platform_logo"><small>Current: <?php echo htmlspecialchars($all_settings['platform_logo'] ?? 'None'); ?></small></div>
            <div class="mb-3"><label for="platform_favicon" class="form-label">Platform Favicon (.ico)</label><input class="form-control" type="file" id="platform_favicon" name="platform_favicon"><small>Current: <?php echo htmlspecialchars($all_settings['platform_favicon'] ?? 'None'); ?></small></div>
            <div class="mb-3"><label for="copyright_text" class="form-label">Footer Copyright Text</label><input type="text" class="form-control" id="copyright_text" name="copyright_text" value="<?php echo htmlspecialchars($all_settings['copyright_text'] ?? ''); ?>"><small class="form-text text-muted">Leave blank to automatically use your domain name.</small></div>
        </div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header">Domain Settings</div>
        <div class="card-body">
            <div class="mb-3"><label for="ad_tag_domain" class="form-label">Ad Tag & Tracking Domain</label><input type="url" class="form-control" id="ad_tag_domain" name="ad_tag_domain" value="<?php echo htmlspecialchars($all_settings['ad_tag_domain'] ?? ''); ?>"><small>Base URL for generating tracking links. Example: https://track.yourdomain.com</small></div>
            <div class="mb-3"><label for="internal_api_endpoint" class="form-label">Internal Ad Server API Endpoint</label><input type="url" class="form-control" id="internal_api_endpoint" name="internal_api_endpoint" value="<?php echo htmlspecialchars($all_settings['internal_api_endpoint'] ?? ''); ?>"><small>URL where `ssp.php` will forward requests. Usually points to your own `api/ad.php`.</small></div>
        </div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">SMTP Email Settings</h6>
            <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                <i class="fas fa-paper-plane fa-sm"></i> Send Test Email
            </button>
        </div>
        <div class="card-body">
            <p class="text-muted">Used for sending verification and password reset emails.</p>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="smtp_host" class="form-label">SMTP Host</label><input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($all_settings['smtp_host'] ?? ''); ?>"></div>
                <div class="col-md-2 mb-3"><label for="smtp_port" class="form-label">SMTP Port</label><input type="text" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($all_settings['smtp_port'] ?? ''); ?>"></div>
                <div class="col-md-4 mb-3"><label for="smtp_from_email" class="form-label">From Email Address</label><input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($all_settings['smtp_from_email'] ?? ''); ?>"></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3"><label for="smtp_username" class="form-label">SMTP Username</label><input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($all_settings['smtp_username'] ?? ''); ?>"></div>
                <div class="col-md-6 mb-3"><label for="smtp_password" class="form-label">SMTP Password</label><input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($all_settings['smtp_password'] ?? ''); ?>"></div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>

<div class="modal fade" id="testEmailModal" tabindex="-1" aria-labelledby="testEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testEmailModalLabel">Send Test Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="test_email_recipient" class="form-label">Recipient Email Address:</label>
                    <input type="email" class="form-control" id="test_email_recipient" placeholder="test@example.com">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="sendTestEmailBtn">Send Test</button>
            </div>
        </div>
    </div>
</div>

<?php include 'template/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sendBtn = document.getElementById('sendTestEmailBtn');
    const emailInput = document.getElementById('test_email_recipient');

    if(sendBtn) {
        sendBtn.addEventListener('click', function() {
            const email = emailInput.value;
            if (email) {
                // Redirect ke URL aksi dengan email sebagai parameter
                window.location.href = 'settings.php?action=test_smtp&test_email=' + encodeURIComponent(email);
            } else {
                alert('Please enter an email address.');
            }
        });
    }
});
</script>