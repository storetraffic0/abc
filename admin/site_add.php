<?php
$page_title = 'Add New Site';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();
$error_message = '';

// Ambil daftar publisher untuk dropdown
try {
    $publishers = $pdo->query("SELECT id, company_name FROM publishers ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $publishers = [];
    $error_message = "Could not fetch publishers.";
}

// Daftar kategori IAB
$iab_categories = [
    'IAB1' => 'Arts & Entertainment', 'IAB2' => 'Automotive', 'IAB3' => 'Business', 'IAB7' => 'Health & Fitness', 'IAB9' => 'Hobbies & Interests', 'IAB10' => 'Home & Garden', 'IAB11' => 'Law, Gov’t & Politics', 'IAB12' => 'News', 'IAB15' => 'Science', 'IAB17' => 'Sports', 'IAB18' => 'Style & Fashion', 'IAB19' => 'Technology & Computing', 'IAB20' => 'Travel', 'IAB24' => 'Uncategorized'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $publisher_id = $_POST['publisher_id'];
    $domain = trim($_POST['domain']);
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $status = $_POST['status'];

    if (empty($publisher_id) || empty($domain) || empty($name)) {
        $error_message = "Please fill in Publisher, Domain, and Site Name.";
    } else {
        try {
            $sql = "INSERT INTO sites (publisher_id, domain, name, category, status) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$publisher_id, $domain, $name, $category, $status]);

            header("Location: sites.php?success=2"); // Redirect dengan pesan sukses
            exit;
        } catch (PDOException $e) {
            $error_message = "Failed to add site: " . $e->getMessage();
        }
    }
}
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>

<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<form method="POST">
    <div class="card shadow mb-4">
        <div class="card-header">New Site Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="publisher_id" class="form-label">Publisher</label>
                    <select class="form-select" id="publisher_id" name="publisher_id" required>
                        <option value="">-- Select a Publisher --</option>
                        <?php foreach($publishers as $publisher): ?>
                            <option value="<?php echo $publisher['id']; ?>"><?php echo htmlspecialchars($publisher['company_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Initial Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" selected>Active</option>
                        <option value="pending">Pending</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="domain" class="form-label">Domain</label>
                    <input type="text" class="form-control" id="domain" name="domain" placeholder="example.com" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Site Name</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="My Example Site" required>
                </div>
            </div>
             <div class="row">
                 <div class="col-md-6 mb-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">-- Select a Category --</option>
                        <?php foreach ($iab_categories as $code => $cat_name): ?>
                            <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($cat_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Add Site</button>
    <a href="sites.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'template/footer.php'; ?>