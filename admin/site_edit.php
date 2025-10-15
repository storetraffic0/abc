<?php
$page_title = 'Edit Site';
include 'template/header.php';
require_once '../includes/db_connection.php';

// Daftar kategori IAB yang umum
$iab_categories = [
    'IAB1' => 'Arts & Entertainment',
    'IAB2' => 'Automotive',
    'IAB3' => 'Business',
    'IAB7' => 'Health & Fitness',
    'IAB9' => 'Hobbies & Interests',
    'IAB10' => 'Home & Garden',
    'IAB11' => 'Law, Gov’t & Politics',
    'IAB12' => 'News',
    'IAB15' => 'Science',
    'IAB17' => 'Sports',
    'IAB18' => 'Style & Fashion',
    'IAB19' => 'Technology & Computing',
    'IAB20' => 'Travel',
    'IAB24' => 'Uncategorized'
];

// Cek ID situs
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid Site ID.</div>";
    include 'template/footer.php';
    exit;
}
$site_id = (int)$_GET['id'];
$pdo = get_db_connection();
$error_message = '';
$success_message = '';

// --- PROSES UPDATE DATA (JIKA FORM DI-SUBMIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "UPDATE sites SET domain = :domain, name = :name, category = :category WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':domain' => $_POST['domain'],
            ':name' => $_POST['name'],
            ':category' => $_POST['category'],
            ':id' => $site_id
        ]);
        $success_message = "Site updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Failed to update site: " . $e->getMessage();
    }
}

// --- AMBIL DATA SITUS UNTUK FORMULIR ---
try {
    $sql_get = "SELECT * FROM sites WHERE id = :id";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute([':id' => $site_id]);
    $site = $stmt_get->fetch();

    if (!$site) {
        throw new Exception("Site not found.");
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    include 'template/footer.php';
    exit;
}
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?>: <?php echo htmlspecialchars($site['domain']); ?></h1>

<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<form method="POST">
    <div class="card shadow mb-4">
        <div class="card-header">Site Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="domain" class="form-label">Domain</label>
                    <input type="text" class="form-control" id="domain" name="domain" value="<?php echo htmlspecialchars($site['domain']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Site Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($site['name']); ?>" required>
                </div>
            </div>
            <div class="row">
                 <div class="col-md-6 mb-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">-- Select a Category --</option>
                        <?php foreach ($iab_categories as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo ($site['category'] == $code) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?> (<?php echo $code; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                     <small class="form-text text-muted">Based on IAB Content Taxonomy. <a href="https://www.iab.com/guidelines/iab-quality-assurance-guidelines-qag-taxonomy/" target="_blank">See full list</a>.</small>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Changes</button>
    <a href="sites.php" class="btn btn-secondary">Back to List</a>
</form>

<?php
include 'template/footer.php';
?>