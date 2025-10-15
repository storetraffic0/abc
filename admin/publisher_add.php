<?php
$page_title = 'Add New Publisher';
include 'template/header.php';
require_once '../includes/db_connection.php';

$error_message = '';
$success_message = '';

// --- PROSES INSERT DATA (JIKA FORM DI-SUBMIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $company_name = trim($_POST['company_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password']; // Di aplikasi nyata, ini harus di-hash
    $revenue_share = $_POST['revenue_share'];
    $status = $_POST['status'];

    // Validasi sederhana
    if (empty($company_name) || empty($email) || empty($username) || empty($password)) {
        $error_message = "Please fill in all required fields.";
    } else {
        $pdo = get_db_connection();
        try {
            $pdo->beginTransaction();

            // 1. Cek apakah username atau email sudah ada
            $sql_check = "SELECT id FROM users WHERE username = :username OR email = :email";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':username' => $username, ':email' => $email]);
            if ($stmt_check->fetch()) {
                throw new Exception("Username or Email already exists.");
            }

            // 2. Insert ke tabel 'users'
            // PENTING: Gunakan password_hash() untuk keamanan di aplikasi produksi!
            $sql_user = "INSERT INTO users (username, password_hash, email, role) VALUES (:username, :password, :email, 'publisher')";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([
                ':username' => $username,
                ':password' => $password, // Seharusnya password_hash($password, PASSWORD_DEFAULT)
                ':email' => $email
            ]);
            $user_id = $pdo->lastInsertId();

            // 3. Insert ke tabel 'publishers'
            $sql_pub = "INSERT INTO publishers (user_id, company_name, revenue_share, status) VALUES (:user_id, :company_name, :revenue_share, :status)";
            $stmt_pub = $pdo->prepare($sql_pub);
            $stmt_pub->execute([
                ':user_id' => $user_id,
                ':company_name' => $company_name,
                ':revenue_share' => $revenue_share,
                ':status' => $status
            ]);

            $pdo->commit();
            // Redirect ke halaman daftar dengan pesan sukses
            header("Location: publishers.php?success=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Failed to add publisher: " . $e->getMessage();
        }
    }
}
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<form method="POST" action="publisher_add.php">
    <div class="card shadow mb-4">
        <div class="card-header">
            New Publisher Details
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="company_name" class="form-label">Company Name</label>
                    <input type="text" class="form-control" id="company_name" name="company_name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Contact Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                 <div class="col-md-3 mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="revenue_share" class="form-label">Revenue Share (%)</label>
                    <input type="number" class="form-control" id="revenue_share" name="revenue_share" value="70.00" min="0" max="100" step="0.01" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Add Publisher</button>
    <a href="publishers.php" class="btn btn-secondary">Cancel</a>
</form>

<?php
include 'template/footer.php';
?>