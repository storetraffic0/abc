<?php
$page_title = 'My Profile';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();
$user_id = $_SESSION['user_id'];
$success_message = ''; $error_message = '';

// Proses form jika ada data yang dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        try {
            $pdo->beginTransaction();
            // Update data di tabel 'users'
            $stmt_user = $pdo->prepare(
                "UPDATE users SET first_name=?, last_name=?, email=?, country=?, contact_whatsapp=?, contact_telegram=?, contact_skype=? WHERE id=?"
            );
            $stmt_user->execute([$_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['country'], $_POST['contact_whatsapp'], $_POST['contact_telegram'], $_POST['contact_skype'], $user_id]);
            // Update data di tabel 'advertisers'
            $stmt_adv = $pdo->prepare("UPDATE advertisers SET company_name = ? WHERE user_id = ?");
            $stmt_adv->execute([$_POST['company_name'], $user_id]);
            $pdo->commit();
            $success_message = "Profile updated successfully.";
        } catch (PDOException $e) { $pdo->rollBack(); $error_message = "Failed to update profile."; }

    } elseif (isset($_POST['action']) && $_POST['action'] == 'change_password') {
        // (Logika ganti password sama persis seperti di panel publisher)
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        try {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if ($user && $current_password === $user['password_hash']) { // Ganti dengan password_verify() di produksi
                if ($new_password === $confirm_password) {
                    if (strlen($new_password) >= 6) {
                        $stmt_update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?"); // Ganti dengan password_hash() di produksi
                        $stmt_update->execute([$new_password, $user_id]);
                        $success_message = "Password changed successfully.";
                    } else { $error_message = "New password must be at least 6 characters long."; }
                } else { $error_message = "New password and confirmation do not match."; }
            } else { $error_message = "Incorrect current password."; }
        } catch (PDOException $e) { $error_message = "An error occurred."; }
    }
}

// Ambil data terbaru untuk ditampilkan di form
try {
    $stmt_get = $pdo->prepare("SELECT u.*, a.company_name FROM users u JOIN advertisers a ON u.id = a.user_id WHERE u.id = ?");
    $stmt_get->execute([$user_id]);
    $user_data = $stmt_get->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error_message = "Could not load user data."; }
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo e($page_title); ?></h1>
<?php if ($success_message): ?><div class="alert alert-success"><?php echo e($success_message); ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo e($error_message); ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header">Profile Information</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3"><label class="form-label">Username</label><input type="text" class="form-control" value="<?php echo e($user_data['username']); ?>" disabled></div>
                    <div class="row"><div class="col-md-6 mb-3"><label for="first_name" class="form-label">First Name</label><input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e($user_data['first_name']); ?>" required></div><div class="col-md-6 mb-3"><label for="last_name" class="form-label">Last Name</label><input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e($user_data['last_name']); ?>" required></div></div>
                    <div class="mb-3"><label for="company_name" class="form-label">Company Name</label><input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo e($user_data['company_name']); ?>" required></div>
                    <div class="row"><div class="col-md-8 mb-3"><label for="email" class="form-label">Contact Email</label><input type="email" class="form-control" id="email" name="email" value="<?php echo e($user_data['email']); ?>" required></div><div class="col-md-4 mb-3"><label for="country" class="form-label">Country</label><input type="text" class="form-control" id="country" name="country" value="<?php echo e($user_data['country']); ?>" required></div></div>
                    <hr><h6 class="text-muted small mb-3">Contact Details (Optional)</h6>
                    <div class="row"><div class="col-md-4 mb-3"><label for="contact_whatsapp" class="form-label">WhatsApp</label><input type="text" class="form-control" id="contact_whatsapp" name="contact_whatsapp" value="<?php echo e($user_data['contact_whatsapp']); ?>"></div><div class="col-md-4 mb-3"><label for="contact_telegram" class="form-label">Telegram</label><input type="text" class="form-control" id="contact_telegram" name="contact_telegram" value="<?php echo e($user_data['contact_telegram']); ?>"></div><div class="col-md-4 mb-3"><label for="contact_skype" class="form-label">Skype</label><input type="text" class="form-control" id="contact_skype" name="contact_skype" value="<?php echo e($user_data['contact_skype']); ?>"></div></div>
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3"><label for="current_password" class="form-label">Current Password</label><input type="password" class="form-control" id="current_password" name="current_password" required></div>
                    <div class="mb-3"><label for="new_password" class="form-label">New Password</label><input type="password" class="form-control" id="new_password" name="new_password" required></div>
                    <div class="mb-3"><label for="confirm_password" class="form-label">Confirm New Password</label><input type="password" class="form-control" id="confirm_password" name="confirm_password" required></div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'template/footer.php'; ?>