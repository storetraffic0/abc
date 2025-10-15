<?php
require_once '../includes/db_connection.php';
$pdo = get_db_connection();
$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';
$show_form = false;

if (empty($token)) {
    $message = 'Invalid or missing token.';
    $message_type = 'danger';
} else {
    // Cari token di database
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();

    if (!$reset_request) {
        $message = 'This password reset token is invalid or has expired.';
        $message_type = 'danger';
    } else {
        $show_form = true;
        // Proses form jika password baru di-submit
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];

            if ($password !== $password_confirm) {
                $message = 'Passwords do not match.';
                $message_type = 'danger';
            } elseif (strlen($password) < 6) {
                $message = 'Password must be at least 6 characters long.';
                $message_type = 'danger';
            } else {
                try {
                    $pdo->beginTransaction();
                    // Update password di tabel users
                    // PENTING: Ganti dengan password_hash() di aplikasi produksi
                    $stmt_update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                    $stmt_update->execute([$password, $reset_request['email']]);

                    // Hapus token yang sudah digunakan
                    $stmt_delete = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                    $stmt_delete->execute([$reset_request['email']]);
                    
                    $pdo->commit();
                    $message = 'Your password has been reset successfully! You can now log in.';
                    $message_type = 'success';
                    $show_form = false;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = 'An error occurred. Please try again.';
                    $message_type = 'danger';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><title>Reset Password</title><link href="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/css/sb-admin-2.min.css" rel="stylesheet"></head><body class="bg-gradient-primary"><div class="container"><div class="row justify-content-center"><div class="col-xl-6 col-lg-8 col-md-9"><div class="card o-hidden border-0 shadow-lg my-5"><div class="card-body p-0"><div class="p-5">
    <div class="text-center"><h1 class="h4 text-gray-900 mb-4">Reset Your Password</h1></div>
    <?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div><?php endif; ?>
    
    <?php if ($show_form): ?>
    <form class="user" method="POST">
        <div class="form-group"><input type="password" class="form-control form-control-user" name="password" placeholder="New Password" required></div>
        <div class="form-group"><input type="password" class="form-control form-control-user" name="password_confirm" placeholder="Confirm New Password" required></div>
        <button type="submit" class="btn btn-primary btn-user btn-block">Reset Password</button>
    </form>
    <?php endif; ?>
    <hr>
    <div class="text-center"><a class="small" href="index.php">Back to Login</a></div>
</div></div></div></div></div></div></body></html>