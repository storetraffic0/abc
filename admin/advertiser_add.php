<?php
$page_title = 'Add New Campaign';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();
$error_message = '';

// Daftar kategori IAB yang umum
$iab_categories = [
    'IAB1' => 'Arts & Entertainment', 'IAB2' => 'Automotive', 'IAB3' => 'Business',
    'IAB7' => 'Health & Fitness', 'IAB9' => 'Hobbies & Interests', 'IAB10' => 'Home & Garden',
    'IAB11' => 'Law, Gov’t & Politics', 'IAB12' => 'News', 'IAB15' => 'Science',
    'IAB17' => 'Sports', 'IAB18' => 'Style & Fashion', 'IAB19' => 'Technology & Computing',
    'IAB20' => 'Travel', 'IAB24' => 'Uncategorized'
];

// Ambil daftar advertiser
try {
    $advertisers = $pdo->query("SELECT id, company_name, allow_external_campaigns FROM advertisers ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $advertisers = []; $error_message = "Could not fetch advertisers."; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (Ambil data form lain seperti sebelumnya)
    $advertiser_id = $_POST['advertiser_id'];
    $name = trim($_POST['name']);
    $campaign_type = $_POST['campaign_type'];
    $status = $_POST['status'];
    $priority = $_POST['priority'];
    $third_party_vast_url = trim($_POST['third_party_vast_url']);
    $rtb_endpoint_url = trim($_POST['rtb_endpoint_url']);
    
    // Ambil data targeting baru
    $countries = trim($_POST['countries']);
    $categories = $_POST['categories'] ?? []; // Ini akan menjadi array

    // ... (Validasi dasar tidak berubah)

    if (true /* $is_valid */) {
        try {
            $pdo->beginTransaction();

            // 1. Insert ke tabel 'campaigns' (tidak berubah)
            $sql_camp = "INSERT INTO campaigns (advertiser_id, name, campaign_type, status, priority) VALUES (:adv_id, :name, :type, :status, :priority)";
            $stmt_camp = $pdo->prepare($sql_camp);
            $stmt_camp->execute([':adv_id' => $advertiser_id, ':name' => $name, ':type' => $campaign_type, ':status' => $status, ':priority' => $priority]);
            $campaign_id = $pdo->lastInsertId();

            // 2. Insert ke tabel 'campaign_details' (tidak berubah)
            $sql_details = "INSERT INTO campaign_details (campaign_id, third_party_vast_url, rtb_endpoint_url) VALUES (:camp_id, :vast_url, :rtb_url)";
            $stmt_details = $pdo->prepare($sql_details);
            $stmt_details->execute([':camp_id' => $campaign_id, ':vast_url' => ($campaign_type == 'internal') ? $third_party_vast_url : null, ':rtb_url' => ($campaign_type == 'external') ? $rtb_endpoint_url : null]);
            
            // 3. Insert ke tabel 'campaign_targeting' (DIPERBARUI)
            $country_array = array_filter(array_map('trim', explode(',', $countries)));
            $sql_targeting = "INSERT INTO campaign_targeting (campaign_id, countries, site_categories) VALUES (:camp_id, :countries, :categories)";
            $stmt_targeting = $pdo->prepare($sql_targeting);
            $stmt_targeting->execute([
                ':camp_id' => $campaign_id,
                ':countries' => json_encode($country_array),
                ':categories' => json_encode($categories) // Simpan array kategori sebagai JSON
            ]);

            $pdo->commit();
            header("Location: campaigns.php?success=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Failed to add campaign: " . $e->getMessage();
        }
    }
}
?>

<div class="card shadow mb-4">
        <div class="card-header">Targeting</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="countries" class="form-label">Country Targeting</label>
                    <input type="text" class="form-control" id="countries" name="countries" placeholder="ID, US, SG">
                    <small class="form-text text-muted">Enter 3-letter country codes, separated by commas. Leave blank to target all.</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="categories" class="form-label">Category Targeting</label>
                    <select class="form-select" id="categories" name="categories[]" multiple size="5">
                        <?php foreach ($iab_categories as $code => $name): ?>
                            <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple. Leave blank to target all.</small>
                </div>
            </div>
        </div>
    </div>