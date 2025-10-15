<?php
$page_title = 'Edit Campaign';
include 'template/header.php';
require_once '../includes/db_connection.php';

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid Campaign ID.</div>";
    include 'template/footer.php';
    exit;
}
$campaign_id = (int)$_GET['id'];
$pdo = get_db_connection();
$error_message = '';
$success_message = '';

// Definisi data untuk dropdowns
$advertisers = $pdo->query("SELECT id, company_name, allow_external_campaigns FROM advertisers ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
$iab_categories = ['IAB1' => 'Arts & Entertainment', 'IAB2' => 'Automotive', 'IAB3' => 'Business', 'IAB7' => 'Health & Fitness', 'IAB9' => 'Hobbies & Interests', 'IAB10' => 'Home & Garden', 'IAB11' => 'Law, Gov’t & Politics', 'IAB12' => 'News', 'IAB15' => 'Science', 'IAB17' => 'Sports', 'IAB18' => 'Style & Fashion', 'IAB19' => 'Technology & Computing', 'IAB20' => 'Travel', 'IAB24' => 'Uncategorized'];
$countries = ['IDN'=>'Indonesia', 'USA'=>'United States', 'SGP'=>'Singapore', 'MYS'=>'Malaysia', 'JPN'=>'Japan', 'GBR'=>'United Kingdom', 'DEU'=>'Germany', 'AUS'=>'Australia', 'CAN'=>'Canada'];
$operating_systems = ['Windows'=>'Windows', 'macOS'=>'macOS', 'Android'=>'Android', 'iOS'=>'iOS', 'Linux'=>'Linux'];
$device_types = ['Desktop'=>'Desktop', 'Mobile'=>'Mobile', 'Tablet'=>'Tablet'];
$browsers = ['Chrome'=>'Chrome', 'Firefox'=>'Firefox', 'Safari'=>'Safari', 'Edge'=>'Edge', 'Opera'=>'Opera'];
$banner_sizes = [ '300x250' => '300x250 - Medium Rectangle', '728x90' => '728x90 - Leaderboard', '300x600' => '300x600 - Half Page', '320x100' => '320x100 - Large Mobile Banner', '320x50' => '320x50 - Mobile Banner', '160x600' => '160x600 - Wide Skyscraper' ];

// --- PROSES UPDATE DATA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $campaign_type = $_POST['campaign_type'];
        $ad_format = $_POST['ad_format'];
        
        // Ambil nilai 'allowed_in_banner'
        $allowed_in_banner = ($ad_format === 'vast' && isset($_POST['allowed_in_banner'])) ? 1 : 0;

        // 1. Update tabel 'campaigns'
        $sql_camp = "UPDATE campaigns SET advertiser_id=?, name=?, campaign_type=?, ad_format=?, status=?, priority=?, cpm_rate=?, allowed_in_banner=? WHERE id=?";
        $pdo->prepare($sql_camp)->execute([
            $_POST['advertiser_id'], $_POST['name'], $campaign_type, $ad_format, $_POST['status'], 
            $_POST['priority'], ($campaign_type == 'internal' ? $_POST['cpm_rate'] : 0),
            $allowed_in_banner, $campaign_id
        ]);

        // 2. Update tabel 'campaign_details' (logika yang lebih bersih)
        $vast_url = ($ad_format == 'vast') ? $_POST['third_party_vast_url'] : null;
        $pop_url = ($ad_format == 'popunder') ? $_POST['destination_url'] : null;
        $rtb_url = ($campaign_type == 'external') ? $_POST['rtb_endpoint_url'] : null;
        $banner_url = null; $banner_html = null; $banner_size = null; $banner_click_url = null;
        if ($ad_format == 'banner') {
            $banner_size = $_POST['banner_size'] ?? null;
            if ($campaign_type == 'internal') {
                $banner_click_url = $_POST['banner_click_url'] ?? null;
                if ($_POST['banner_type'] == 'image') { $banner_url = $_POST['banner_url'] ?? null; } 
                else { $banner_html = $_POST['banner_html'] ?? null; }
            }
        }
        $sql_details = "UPDATE campaign_details SET third_party_vast_url=?, destination_url=?, rtb_endpoint_url=?, banner_url=?, banner_html=?, banner_size=?, banner_click_url=? WHERE campaign_id=?";
        $pdo->prepare($sql_details)->execute([$vast_url, $pop_url, $rtb_url, $banner_url, $banner_html, $banner_size, $banner_click_url, $campaign_id]);
        
        // 3. Update tabel 'campaign_targeting'
        $sql_targeting = "UPDATE campaign_targeting SET countries=?, site_categories=?, operating_systems=?, device_types=?, browsers=? WHERE campaign_id=?";
        $pdo->prepare($sql_targeting)->execute([json_encode($_POST['countries'] ?? []), json_encode($_POST['categories'] ?? []), json_encode($_POST['operating_systems'] ?? []), json_encode($_POST['device_types'] ?? []), json_encode($_POST['browsers'] ?? []), $campaign_id]);

        $pdo->commit();
        $success_message = "Campaign updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Failed to update campaign: " . $e->getMessage();
    }
}

// --- AMBIL DATA KAMPANYE UNTUK FORM ---
try {
    $sql_get = "SELECT c.*, cd.*, ct.* FROM campaigns c LEFT JOIN campaign_details cd ON c.id = cd.campaign_id LEFT JOIN campaign_targeting ct ON c.id = ct.campaign_id WHERE c.id = ?";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute([$campaign_id]);
    $campaign = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) { throw new Exception("Campaign not found."); }
    
    $targeted_countries = json_decode($campaign['countries'] ?? '[]', true);
    $targeted_categories = json_decode($campaign['site_categories'] ?? '[]', true);
    $targeted_os = json_decode($campaign['operating_systems'] ?? '[]', true);
    $targeted_devices = json_decode($campaign['device_types'] ?? '[]', true);
    $targeted_browsers = json_decode($campaign['browsers'] ?? '[]', true);
    $banner_type = !empty($campaign['banner_html']) ? 'html' : 'image';

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    include 'template/footer.php';
    exit;
}
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo e($page_title); ?>: <?php echo e($campaign['name']); ?></h1>
<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<form method="POST" id="campaignForm">
    <div class="card shadow mb-4">
        <div class="card-header">Campaign Details</div>
        <div class="card-body">
            <div class="row"><div class="col-md-6 mb-3"><label for="name" class="form-label">Campaign Name</label><input type="text" class="form-control" id="name" name="name" value="<?php echo e($campaign['name']); ?>" required></div><div class="col-md-6 mb-3"><label for="advertiser_id" class="form-label">Advertiser</label><select class="form-select" id="advertiser_id" name="advertiser_id" required><?php foreach ($advertisers as $advertiser): ?><option value="<?php echo $advertiser['id']; ?>" data-allow-external="<?php echo $advertiser['allow_external_campaigns']; ?>" <?php echo ($advertiser['id'] == $campaign['advertiser_id']) ? 'selected' : ''; ?>><?php echo e($advertiser['company_name']); ?></option><?php endforeach; ?></select></div></div>
            <div class="row align-items-end"><div class="col-md-3 mb-3"><label for="ad_format" class="form-label">Ad Format</label><select class="form-select" id="ad_format" name="ad_format"><option value="vast" <?php echo ($campaign['ad_format'] == 'vast') ? 'selected' : ''; ?>>VAST Video</option><option value="banner" <?php echo ($campaign['ad_format'] == 'banner') ? 'selected' : ''; ?>>Banner</option><option value="popunder" <?php echo ($campaign['ad_format'] == 'popunder') ? 'selected' : ''; ?>>Popunder</option></select></div><div class="col-md-3 mb-3"><label class="form-label">Campaign Type</label><div class="form-check"><input class="form-check-input" type="radio" name="campaign_type" id="type_internal" value="internal" <?php echo ($campaign['campaign_type'] == 'internal') ? 'checked' : ''; ?>><label class="form-check-label" for="type_internal">Internal</label></div><div class="form-check"><input class="form-check-input" type="radio" name="campaign_type" id="type_external" value="external" <?php echo ($campaign['campaign_type'] == 'external') ? 'checked' : ''; ?>><label class="form-check-label" for="type_external">External (RTB)</label></div></div><div class="col-md-2 mb-3"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status"><option value="paused" <?php echo ($campaign['status'] == 'paused') ? 'selected' : ''; ?>>Paused</option><option value="active" <?php echo ($campaign['status'] == 'active') ? 'selected' : ''; ?>>Active</option><option value="pending" <?php echo ($campaign['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option><option value="rejected" <?php echo ($campaign['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option></select></div><div class="col-md-2 mb-3"><label for="priority" class="form-label">Priority</label><input type="number" class="form-control" id="priority" name="priority" value="<?php echo e($campaign['priority']); ?>" min="1" max="10"></div><div class="col-md-2 mb-3" id="cpm_rate_field"><label for="cpm_rate" class="form-label">CPM Rate ($)</label><input type="number" class="form-control" id="cpm_rate" name="cpm_rate" value="<?php echo e($campaign['cpm_rate']); ?>" step="0.01" min="0"></div></div>
        </div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header">Creative Details</div>
        <div class="card-body">
            <div id="vast_fields" class="creative-fields">
                <div class="mb-3"><label for="third_party_vast_url" class="form-label">3rd Party VAST URL</label><input type="url" class="form-control" name="third_party_vast_url" placeholder="https://example.com/vast.xml" value="<?php echo e($campaign['third_party_vast_url']); ?>"></div>
                <!-- ============================================================ -->
                <!-- PERUBAHAN HTML: Checkbox baru untuk Video-in-Banner -->
                <!-- ============================================================ -->
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="allowed_in_banner" value="1" id="allowed_in_banner" <?php echo !empty($campaign['allowed_in_banner']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allowed_in_banner">Allow this video to be served in banner slots</label>
                        <small class="form-text text-muted d-block">Check this if the video is suitable for smaller banner placements (e.g., 300x250).</small>
                    </div>
                </div>
            </div>
            <div id="popunder_fields" class="creative-fields" style="display: none;"><label for="destination_url" class="form-label">Destination URL</label><input type="url" class="form-control" name="destination_url" placeholder="https://destination.com/landing-page" value="<?php echo e($campaign['destination_url']); ?>"></div>
            <div id="banner_fields" class="creative-fields" style="display: none;">
                <div class="mb-3"><label for="banner_size" class="form-label">Banner Size</label><select class="form-select" name="banner_size"><?php foreach ($banner_sizes as $size => $label):?><option value="<?php echo $size; ?>" <?php echo ($campaign['banner_size'] == $size) ? 'selected' : ''; ?>><?php echo e($label); ?></option><?php endforeach; ?></select></div>
                <div id="internal_banner_options">
                    <div class="mb-3"><label class="form-label">Banner Type</label><div class="form-check"><input class="form-check-input" type="radio" name="banner_type" value="image" id="banner_type_image" <?php echo ($banner_type == 'image') ? 'checked' : ''; ?>><label class="form-check-label" for="banner_type_image">Image Banner</label></div><div class="form-check"><input class="form-check-input" type="radio" name="banner_type" value="html" id="banner_type_html" <?php echo ($banner_type == 'html') ? 'checked' : ''; ?>><label class="form-check-label" for="banner_type_html">HTML Banner</label></div></div>
                    <div id="image_banner_fields" class="mb-3"><label for="banner_url" class="form-label">Banner Image URL</label><input type="url" class="form-control" name="banner_url" placeholder="https://example.com/banner.jpg" value="<?php echo e($campaign['banner_url']); ?>"></div>
                    <div id="html_banner_fields" class="mb-3" style="display:none;"><label for="banner_html" class="form-label">Banner HTML Code</label><textarea class="form-control" name="banner_html" rows="6" placeholder="<div>Your HTML banner code here...</div>"><?php echo e($campaign['banner_html']); ?></textarea></div>
                    <div class="mb-3"><label for="banner_click_url" class="form-label">Click URL</label><input type="url" class="form-control" name="banner_click_url" placeholder="https://example.com/landing-page" value="<?php echo e($campaign['banner_click_url']); ?>"></div>
                </div>
            </div>
            <div id="external_fields" style="display: none;"><label for="rtb_endpoint_url" class="form-label">RTB Endpoint URL</label><input type="url" class="form-control" name="rtb_endpoint_url" placeholder="https://rtb.partner.com/endpoint" value="<?php echo e($campaign['rtb_endpoint_url']); ?>"></div>
        </div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header">Targeting</div>
        <div class="card-body">
            <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Country Targeting</label><select class="form-select" name="countries[]" multiple><?php foreach ($countries as $code => $name): ?><option value="<?php echo $code; ?>" <?php echo in_array($code, $targeted_countries) ? 'selected' : ''; ?>><?php echo e($name); ?></option><?php endforeach; ?></select></div><div class="col-md-6 mb-3"><label class="form-label">Category Targeting</label><select class="form-select" name="categories[]" multiple><?php foreach ($iab_categories as $code => $name): ?><option value="<?php echo $code; ?>" <?php echo in_array($code, $targeted_categories) ? 'selected' : ''; ?>><?php echo e($name); ?></option><?php endforeach; ?></select></div></div>
            <div class="row"><div class="col-md-6 mb-3"><label class="form-label">OS Targeting</label><select class="form-select" name="operating_systems[]" multiple><?php foreach ($operating_systems as $val): ?><option value="<?php echo $val; ?>" <?php echo in_array($val, $targeted_os) ? 'selected' : ''; ?>><?php echo e($val); ?></option><?php endforeach; ?></select></div><div class="col-md-6 mb-3"><label class="form-label">Device Targeting</label><select class="form-select" name="device_types[]" multiple><?php foreach ($device_types as $val): ?><option value="<?php echo $val; ?>" <?php echo in_array($val, $targeted_devices) ? 'selected' : ''; ?>><?php echo e($val); ?></option><?php endforeach; ?></select></div></div>
            <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Browser Targeting</label><select class="form-select" name="browsers[]" multiple><?php foreach ($browsers as $val): ?><option value="<?php echo $val; ?>" <?php echo in_array($val, $targeted_browsers) ? 'selected' : ''; ?>><?php echo e($val); ?></option><?php endforeach; ?></select></div></div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
    <a href="campaigns.php" class="btn btn-secondary">Back to List</a>
</form>

<?php include 'template/footer.php'; ?>

<script>
// JavaScript dari halaman 'add' bisa digunakan kembali di sini dengan sedikit modifikasi
document.addEventListener('DOMContentLoaded', function() {
    const adFormatSelect = document.getElementById('ad_format');
    const campaignTypeRadios = document.getElementsByName('campaign_type');
    const bannerTypeRadios = document.getElementsByName('banner_type');
    const advertiserSelect = document.getElementById('advertiser_id');
    const vastFields = document.getElementById('vast_fields');
    const popunderFields = document.getElementById('popunder_fields');
    const bannerFields = document.getElementById('banner_fields');
    const externalFields = document.getElementById('external_fields');
    const cpmRateField = document.getElementById('cpm_rate_field');
    const internalBannerOptions = document.getElementById('internal_banner_options');
    const imageBannerFields = document.getElementById('image_banner_fields');
    const htmlBannerFields = document.getElementById('html_banner_fields');
    function updateFormVisibility() {
        const adFormat = adFormatSelect.value;
        const campaignType = document.querySelector('input[name="campaign_type"]:checked').value;
        vastFields.style.display = 'none'; popunderFields.style.display = 'none'; bannerFields.style.display = 'none'; externalFields.style.display = 'none';
        if (campaignType === 'internal') {
            cpmRateField.style.display = 'block';
            if (adFormat === 'vast') vastFields.style.display = 'block';
            if (adFormat === 'popunder') popunderFields.style.display = 'block';
            if (adFormat === 'banner') {
                bannerFields.style.display = 'block';
                internalBannerOptions.style.display = 'block';
                updateBannerTypeVisibility();
            }
        } else {
            cpmRateField.style.display = 'none';
            externalFields.style.display = 'block';
            if (adFormat === 'banner') {
                bannerFields.style.display = 'block';
                internalBannerOptions.style.display = 'none';
            }
        }
    }
    function updateBannerTypeVisibility() {
        const bannerType = document.querySelector('input[name="banner_type"]:checked').value;
        imageBannerFields.style.display = (bannerType === 'image') ? 'block' : 'none';
        htmlBannerFields.style.display = (bannerType === 'html') ? 'block' : 'none';
    }
    function checkAdvertiserPermissions() {
        const selectedOption = advertiserSelect.options[advertiserSelect.selectedIndex];
        const allowExternal = selectedOption ? selectedOption.dataset.allowExternal === '1' : false;
        document.getElementById('type_external').disabled = !allowExternal;
        if (!allowExternal && document.getElementById('type_external').checked) {
            document.getElementById('type_internal').checked = true;
            updateFormVisibility();
        }
    }
    adFormatSelect.addEventListener('change', updateFormVisibility);
    campaignTypeRadios.forEach(radio => radio.addEventListener('change', updateFormVisibility));
    bannerTypeRadios.forEach(radio => radio.addEventListener('change', updateBannerTypeVisibility));
    advertiserSelect.addEventListener('change', checkAdvertiserPermissions);
    checkAdvertiserPermissions(); // Panggil saat memuat untuk set state awal
});
$(document).ready(function() {
    $('#campaignForm select[multiple]').multiselect({
        buttonWidth: '100%', enableFiltering: true, enableCaseInsensitiveFiltering: true,
        maxHeight: 250, buttonClass: 'form-select text-start',
        templates: { button: '<button type="button" class="multiselect dropdown-toggle" data-bs-toggle="dropdown"><span class="multiselect-selected-text"></span></button>'},
        includeSelectAllOption: true, selectAllText: 'Select All', numberDisplayed: 1
    });
});
</script>
```