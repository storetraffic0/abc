<?php
$page_title = 'Edit Advertiser';
include 'template/header.php';
require_once '../includes/db_connection.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid Advertiser ID.</div>";
    include 'template/footer.php';
    exit;
}

$advertiser_id = (int)$_GET['id'];
$pdo = get_db_connection();
$error_message = '';
$success_message = '';

// --- PROSES UPDATE DATA (JIKA FORM DI-SUBMIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $user_id = $_POST['user_id'];
        $company_name = trim($_POST['company_name']);
        $allow_external = isset($_POST['allow_external_campaigns']) ? 1 : 0;

        // 1. Update tabel users
        $sql_user = "UPDATE users SET first_name=?, last_name=?, email=?, country=?, contact_whatsapp=?, contact_telegram=?, contact_skype=? WHERE id=?";
        $pdo->prepare($sql_user)->execute([
            trim($_POST['first_name']), trim($_POST['last_name']), trim($_POST['email']),
            $_POST['country'], trim($_POST['contact_whatsapp']), trim($_POST['contact_telegram']),
            trim($_POST['contact_skype']), $user_id
        ]);

        // 2. Update tabel advertisers
        $sql_adv = "UPDATE advertisers SET company_name = ?, allow_external_campaigns = ? WHERE id = ?";
        $stmt_adv = $pdo->prepare($sql_adv);
        $stmt_adv->execute([$company_name, $allow_external, $advertiser_id]);

        $pdo->commit();
        $success_message = "Advertiser updated successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Failed to update advertiser: " . $e->getMessage();
    }
}

// --- AMBIL DATA TERBARU UNTUK DITAMPILKAN DI FORM ---
try {
    $sql = "SELECT a.id, a.company_name, a.allow_external_campaigns, 
                   u.id as user_id, u.email, u.username, u.first_name, u.last_name, u.country, 
                   u.contact_whatsapp, u.contact_telegram, u.contact_skype
            FROM advertisers a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $advertiser_id]);
    $advertiser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$advertiser) {
        throw new Exception("Advertiser not found.");
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    include 'template/footer.php';
    exit;
}
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($advertiser['company_name']); ?></h1>

<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<form method="POST" action="advertiser_edit.php?id=<?php echo $advertiser_id; ?>">
    <input type="hidden" name="user_id" value="<?php echo $advertiser['user_id']; ?>">
    <div class="card shadow mb-4">
        <div class="card-header">Advertiser Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">First Name</label><input type="text" class="form-control" name="first_name" value="<?php echo e($advertiser['first_name']); ?>"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Last Name</label><input type="text" class="form-control" name="last_name" value="<?php echo e($advertiser['last_name']); ?>"></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Company Name</label><input type="text" class="form-control" name="company_name" value="<?php echo e($advertiser['company_name']); ?>" required></div>
                <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo e($advertiser['email']); ?>" required></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">Username</label><input type="text" class="form-control" value="<?php echo e($advertiser['username']); ?>" disabled></div>
                <div class="col-md-6 mb-3"><label class="form-label">Country</label><input type="text" class="form-control" name="country" value="<?php echo e($advertiser['country']); ?>"></div>
            </div>
            <hr>
            <h6 class="text-muted small">Contact Details</h6>
            <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">WhatsApp</label><input type="text" class="form-control" name="contact_whatsapp" value="<?php echo e($advertiser['contact_whatsapp']); ?>"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Telegram</label><input type="text" class="form-control" name="contact_telegram" value="<?php echo e($advertiser['contact_telegram']); ?>"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Skype</label><input type="text" class="form-control" name="contact_skype" value="<?php echo e($advertiser['contact_skype']); ?>"></div>
            </div>
            <hr>
             <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="allow_external" name="allow_external_campaigns" <?php echo $advertiser['allow_external_campaigns'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_external">Allow External (RTB) Campaigns</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Changes</button>
    <a href="advertisers.php" class="btn btn-secondary">Back to List</a>
</form>

<?php
include 'template/footer.php';
?>