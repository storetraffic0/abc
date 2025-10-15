<?php
// advertiser/campaign_add.php
$page_title = 'Create New Campaign';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();
$advertiser_user_id = $_SESSION['user_id'];
$error_message = '';

try {
    $stmt_adv = $pdo->prepare("SELECT id, allow_external_campaigns FROM advertisers WHERE user_id = ?");
    $stmt_adv->execute([$advertiser_user_id]);
    $advertiser = $stmt_adv->fetch(PDO::FETCH_ASSOC);
    if (!$advertiser) { throw new Exception("Advertiser account not found. Please contact support."); }
    $advertiser_id = $advertiser['id'];
    $allow_external = $advertiser['allow_external_campaigns'];
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>" . e($e->getMessage()) . "</div>";
    include 'template/footer.php';
    exit;
}

$iab_categories = ['IAB1' => 'Arts & Entertainment', 'IAB2' => 'Automotive', 'IAB3' => 'Business', 'IAB4' => 'Careers', 'IAB5' => 'Education', 'IAB6' => 'Family & Parenting', 'IAB7' => 'Health & Fitness', 'IAB8' => 'Food & Drink', 'IAB9' => 'Hobbies & Interests', 'IAB10' => 'Home & Garden', 'IAB11' => 'Law, Gov\'t & Politics', 'IAB12' => 'News', 'IAB13' => 'Personal Finance', 'IAB14' => 'Society', 'IAB15' => 'Science', 'IAB16' => 'Pets', 'IAB17' => 'Sports', 'IAB18' => 'Style & Fashion', 'IAB19' => 'Technology & Computing', 'IAB20' => 'Travel', 'IAB21' => 'Real Estate', 'IAB22' => 'Shopping', 'IAB23' => 'Religion & Spirituality', 'IAB24' => 'Uncategorized'];
$countries = ['AFG'=>'Afghanistan','ALB'=>'Albania','DZA'=>'Algeria','AND'=>'Andorra','AGO'=>'Angola','ATG'=>'Antigua and Barbuda','ARG'=>'Argentina','ARM'=>'Armenia','AUS'=>'Australia','AUT'=>'Austria','AZE'=>'Azerbaijan','BHS'=>'Bahamas','BHR'=>'Bahrain','BGD'=>'Bangladesh','BRB'=>'Barbados','BLR'=>'Belarus','BEL'=>'Belgium','BLZ'=>'Belize','BEN'=>'Benin','BTN'=>'Bhutan','BOL'=>'Bolivia','BIH'=>'Bosnia and Herzegovina','BWA'=>'Botswana','BRA'=>'Brazil','BRN'=>'Brunei Darussalam','BGR'=>'Bulgaria','BFA'=>'Burkina Faso','BDI'=>'Burundi','CPV'=>'Cabo Verde','KHM'=>'Cambodia','CMR'=>'Cameroon','CAN'=>'Canada','CAF'=>'Central African Republic','TCD'=>'Chad','CHL'=>'Chile','CHN'=>'China','COL'=>'Colombia','COM'=>'Comoros','COG'=>'Congo','COD'=>'Congo, Democratic Republic of the','CRI'=>'Costa Rica','CIV'=>'Côte d\'Ivoire','HRV'=>'Croatia','CUB'=>'Cuba','CYP'=>'Cyprus','CZE'=>'Czech Republic','DNK'=>'Denmark','DJI'=>'Djibouti','DMA'=>'Dominica','DOM'=>'Dominican Republic','ECU'=>'Ecuador','EGY'=>'Egypt','SLV'=>'El Salvador','GNQ'=>'Equatorial Guinea','ERI'=>'Eritrea','EST'=>'Estonia','SWZ'=>'Eswatini','ETH'=>'Ethiopia','FJI'=>'Fiji','FIN'=>'Finland','FRA'=>'France','GAB'=>'Gabon','GMB'=>'Gambia','GEO'=>'Georgia','DEU'=>'Germany','GHA'=>'Ghana','GRC'=>'Greece','GRD'=>'Grenada','GTM'=>'Guatemala','GIN'=>'Guinea','GNB'=>'Guinea-Bissau','GUY'=>'Guyana','HTI'=>'Haiti','HND'=>'Honduras','HUN'=>'Hungary','ISL'=>'Iceland','IND'=>'India','IDN'=>'Indonesia','IRN'=>'Iran','IRQ'=>'Iraq','IRL'=>'Ireland','ISR'=>'Israel','ITA'=>'Italy','JAM'=>'Jamaica','JPN'=>'Japan','JOR'=>'Jordan','KAZ'=>'Kazakhstan','KEN'=>'Kenya','KIR'=>'Kiribati','PRK'=>'Korea, Democratic People\'s Republic of','KOR'=>'Korea, Republic of','KWT'=>'Kuwait','KGZ'=>'Kyrgyzstan','LAO'=>'Lao People\'s Democratic Republic','LVA'=>'Latvia','LBN'=>'Lebanon','LSO'=>'Lesotho','LBR'=>'Liberia','LBY'=>'Libya','LIE'=>'Liechtenstein','LTU'=>'Lithuania','LUX'=>'Luxembourg','MDG'=>'Madagascar','MWI'=>'Malawi','MYS'=>'Malaysia','MDV'=>'Maldives','MLI'=>'Mali','MLT'=>'Malta','MHL'=>'Marshall Islands','MRT'=>'Mauritania','MUS'=>'Mauritius','MEX'=>'Mexico','FSM'=>'Micronesia','MDA'=>'Moldova','MCO'=>'Monaco','MNG'=>'Mongolia','MNE'=>'Montenegro','MAR'=>'Morocco','MOZ'=>'Mozambique','MMR'=>'Myanmar','NAM'=>'Namibia','NRU'=>'Nauru','NPL'=>'Nepal','NLD'=>'Netherlands','NZL'=>'New Zealand','NIC'=>'Nicaragua','NER'=>'Niger','NGA'=>'Nigeria','MKD'=>'North Macedonia','NOR'=>'Norway','OMN'=>'Oman','PAK'=>'Pakistan','PLW'=>'Palau','PAN'=>'Panama','PNG'=>'Papua New Guinea','PRY'=>'Paraguay','PER'=>'Peru','PHL'=>'Philippines','POL'=>'Poland','PRT'=>'Portugal','QAT'=>'Qatar','ROU'=>'Romania','RUS'=>'Russian Federation','RWA'=>'Rwanda','KNA'=>'Saint Kitts and Nevis','LCA'=>'Saint Lucia','VCT'=>'Saint Vincent and the Grenadines','WSM'=>'Samoa','SMR'=>'San Marino','STP'=>'Sao Tome and Principe','SAU'=>'Saudi Arabia','SEN'=>'Senegal','SRB'=>'Serbia','SYC'=>'Seychelles','SLE'=>'Sierra Leone','SGP'=>'Singapore','SVK'=>'Slovakia','SVN'=>'Slovenia','SLB'=>'Solomon Islands','SOM'=>'Somalia','ZAF'=>'South Africa','SSD'=>'South Sudan','ESP'=>'Spain','LKA'=>'Sri Lanka','SDN'=>'Sudan','SUR'=>'Suriname','SWE'=>'Sweden','CHE'=>'Switzerland','SYR'=>'Syrian Arab Republic','TWN'=>'Taiwan','TJK'=>'Tajikistan','TZA'=>'Tanzania','THA'=>'Thailand','TLS'=>'Timor-Leste','TGO'=>'Togo','TON'=>'Tonga','TTO'=>'Trinidad and Tobago','TUN'=>'Tunisia','TUR'=>'Turkey','TKM'=>'Turkmenistan','TUV'=>'Tuvalu','UGA'=>'Uganda','UKR'=>'Ukraine','ARE'=>'United Arab Emirates','GBR'=>'United Kingdom','USA'=>'United States','URY'=>'Uruguay','UZB'=>'Uzbekistan','VUT'=>'Vanuatu','VAT'=>'Vatican City','VEN'=>'Venezuela','VNM'=>'Vietnam','YEM'=>'Yemen','ZMB'=>'Zambia','ZWE'=>'Zimbabwe'];
$operating_systems = ['Windows','macOS','Linux','ChromeOS','Android','iOS','iPadOS','HarmonyOS','KaiOS','Other'];
$device_types = ['Desktop','Laptop','Mobile','Tablet','SmartTV','Console','Wearable','Other'];
$browsers = ['Chrome','Firefox','Safari','Edge','Opera','Samsung Internet','UC Browser','Brave','Vivaldi','Internet Explorer','Yandex','DuckDuckGo Browser','Other'];
$banner_sizes = [ '300x250' => '300x250 - Medium Rectangle', '728x90' => '728x90 - Leaderboard', '300x500' => '300x500 - Half Page', '300x100' => '300x100 - Large Mobile Banner', '300x50' => '300x50 - Mobile Banner', '160x600' => '160x600 - Wide Skyscraper', '900x250' => '900x250 - Billboard', '900x90' => '900x90 - Large Leaderboard', '336x280' => '336x280 - Large Rectangle', '250x250' => '250x250 - Square', '200x200' => '200x200 - Small Square', '468x60' => '468x60 - Banner', '120x600' => '120x600 - Skyscraper', '234x60' => '234x60 - Half Banner' ];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campaign_type = $_POST['campaign_type'];
    $ad_format = $_POST['ad_format'];

    if ($campaign_type === 'external' && !$allow_external) {
        $error_message = "You do not have permission to create external (RTB) campaigns.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // ============================================================
            // PERUBAHAN PHP 1: Ambil nilai checkbox 'allowed_in_banner'
            // ============================================================
            $allowed_in_banner = ($ad_format === 'vast' && isset($_POST['allowed_in_banner'])) ? 1 : 0;

            // ============================================================
            // PERUBAHAN PHP 2: Tambahkan kolom 'allowed_in_banner' ke query
            // ============================================================
            $sql_camp = "INSERT INTO campaigns (advertiser_id, name, campaign_type, ad_format, status, priority, cpm_rate, allowed_in_banner) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)";
            $stmt_camp = $pdo->prepare($sql_camp);
            $stmt_camp->execute([
                $advertiser_id, 
                $_POST['name'], 
                $campaign_type, 
                $ad_format,
                $_POST['priority'], 
                ($campaign_type == 'internal' ? $_POST['cpm_rate'] : 0),
                $allowed_in_banner // Nilai baru ditambahkan di sini
            ]);
            $campaign_id = $pdo->lastInsertId();

            // Sisa logika PHP tidak perlu diubah...
            $vast_url = ($ad_format == 'vast') ? $_POST['third_party_vast_url'] : null;
            $pop_url = ($ad_format == 'popunder') ? $_POST['destination_url'] : null;
            $rtb_url = ($campaign_type == 'external') ? $_POST['rtb_endpoint_url'] : null;
            
            $banner_url = null; $banner_html = null; $banner_size = null; $banner_click_url = null;
            
            if ($ad_format == 'banner') {
                $banner_size = $_POST['banner_size'] ?? null;
                if ($campaign_type == 'internal') {
                    $banner_type = $_POST['banner_type'] ?? 'image';
                    if ($banner_type == 'image') { $banner_url = $_POST['banner_url'] ?? null; } else { $banner_html = $_POST['banner_html'] ?? null; }
                    $banner_click_url = $_POST['banner_click_url'] ?? null;
                }
            }

            $sql_details = "INSERT INTO campaign_details (campaign_id, third_party_vast_url, destination_url, rtb_endpoint_url, banner_url, banner_html, banner_size, banner_click_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_details = $pdo->prepare($sql_details);
            $stmt_details->execute([$campaign_id, $vast_url, $pop_url, $rtb_url, $banner_url, $banner_html, $banner_size, $banner_click_url]);
            
            $sql_targeting = "INSERT INTO campaign_targeting (campaign_id, countries, site_categories, operating_systems, device_types, browsers) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_targeting = $pdo->prepare($sql_targeting);
            $stmt_targeting->execute([$campaign_id, json_encode($_POST['countries'] ?? []), json_encode($_POST['categories'] ?? []), json_encode($_POST['operating_systems'] ?? []), json_encode($_POST['device_types'] ?? []), json_encode($_POST['browsers'] ?? [])]);

            $pdo->commit();
            header("Location: campaigns.php?success=1&new=pending");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Failed to add campaign: " . $e->getMessage();
        }
    }
}
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo e($page_title); ?></h1>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo e($error_message); ?></div><?php endif; ?>

<form method="POST" id="campaignForm">
    <div class="card shadow mb-4">
        <div class="card-header">Campaign Details</div>
        <div class="card-body">
            <!-- Form tidak berubah -->
            <div class="form-group mb-3"><label for="name" class="form-label">Campaign Name</label><input type="text" class="form-control" id="name" name="name" required></div>
            <div class="row align-items-end"><div class="col-md-4 mb-3"><label for="ad_format" class="form-label">Ad Format</label><select class="form-select" id="ad_format" name="ad_format"><option value="vast" selected>VAST Video</option><option value="popunder">Popunder</option><option value="banner">Banner</option></select></div><div class="col-md-8 mb-3"><label class="form-label">Campaign Type</label><div class="form-check"><input class="form-check-input" type="radio" name="campaign_type" id="type_internal" value="internal" checked><label class="form-check-label" for="type_internal">Internal (Direct URL)</label></div><div class="form-check"><input class="form-check-input" type="radio" name="campaign_type" id="type_external" value="external" <?php if(!$allow_external) echo 'disabled'; ?>><label class="form-check-label" for="type_external">External (RTB Endpoint)</label></div><?php if(!$allow_external): ?><small class="text-muted">Your account is not enabled for External campaigns.</small><?php endif; ?></div></div>
            <div class="row align-items-end"><div class="col-md-4 mb-3"><label for="priority" class="form-label">Priority</label><input type="number" class="form-control" id="priority" name="priority" value="5" min="1" max="10"></div><div class="col-md-8 mb-3" id="cpm_rate_field"><label for="cpm_rate" class="form-label">CPM Rate ($)</label><input type="number" class="form-control" id="cpm_rate" name="cpm_rate" value="0.001" step="0.001" min="0"><small class="form-text text-muted">Cost per 1000 impressions.</small></div></div>
            <small class="form-text text-muted">Your campaign will be submitted for review and will be set to "Pending" status.</small>
        </div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header">Creative Details</div>
        <div class="card-body">
            <!-- VAST fields -->
            <div id="vast_field_wrapper" class="ad-format-fields">
                <div class="mb-3">
                    <label for="third_party_vast_url" class="form-label">3rd Party VAST URL</label>
                    <input type="url" class="form-control" id="third_party_vast_url" name="third_party_vast_url" placeholder="https://example.com/vast.xml">
                </div>
                
                <!-- ============================================================ -->
                <!-- PERUBAHAN HTML: Checkbox baru untuk Video-in-Banner -->
                <!-- ============================================================ -->
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="allowed_in_banner" value="1" id="allowed_in_banner">
                        <label class="form-check-label" for="allowed_in_banner">
                            Allow this video to be served in banner slots
                        </label>
                        <small class="form-text text-muted d-block">Check this if your video is suitable for smaller banner placements (e.g., 300x250). The video will be muted by default.</small>
                    </div>
                </div>
            </div>
            
            <!-- Popunder fields -->
            <div id="popunder_field_wrapper" class="ad-format-fields" style="display: none;">
                <label for="destination_url" class="form-label">Destination URL</label>
                <input type="url" class="form-control" id="destination_url" name="destination_url" placeholder="https://destination.com/landing-page">
            </div>
            
            <!-- Banner fields -->
            <div id="banner_field_wrapper" class="ad-format-fields" style="display: none;">
                <div class="mb-3"><label for="banner_size" class="form-label">Banner Size</label><select class="form-select" id="banner_size" name="banner_size"><option value="">Select Size</option><?php foreach ($banner_sizes as $size => $label): ?><option value="<?php echo $size; ?>"><?php echo e($label); ?></option><?php endforeach; ?></select></div>
                <div id="internal_banner_options"><div class="mb-3"><label class="form-label">Banner Type</label><div class="form-check"><input class="form-check-input" type="radio" name="banner_type" id="banner_type_image" value="image" checked><label class="form-check-label" for="banner_type_image">Image Banner</label></div><div class="form-check"><input class="form-check-input" type="radio" name="banner_type" id="banner_type_html" value="html"><label class="form-check-label" for="banner_type_html">HTML Banner</label></div></div><div id="image_banner_fields" class="mb-3"><label for="banner_url" class="form-label">Banner Image URL</label><input type="url" class="form-control" id="banner_url" name="banner_url" placeholder="https://example.com/banner.jpg"><small class="form-text text-muted">Direct URL to your banner image. We recommend using HTTPS.</small></div><div id="html_banner_fields" class="mb-3" style="display:none;"><label for="banner_html" class="form-label">Banner HTML Code</label><textarea class="form-control" id="banner_html" name="banner_html" rows="6" placeholder="<div>Your HTML banner code here...</div>"></textarea><small class="form-text text-muted">Paste your HTML/CSS/JavaScript code for the banner.</small></div><div class="mb-3"><label for="banner_click_url" class="form-label">Click URL</label><input type="url" class="form-control" id="banner_click_url" name="banner_click_url" placeholder="https://example.com/landing-page"><small class="form-text text-muted">Where users will go when they click on your banner.</small></div></div>
            </div>
            
            <!-- External RTB fields -->
            <div id="external_fields" style="display: none;">
                <label for="rtb_endpoint_url" class="form-label">RTB Endpoint URL</label>
                <input type="url" class="form-control" id="rtb_endpoint_url" name="rtb_endpoint_url" placeholder="https://rtb.partner.com/endpoint">
            </div>
        </div>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header">Targeting</div>
        <div class="card-body">
            <!-- Form tidak berubah -->
            <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Country Targeting</label><select class="form-select" name="countries[]" multiple><?php foreach ($countries as $code => $name): ?><option value="<?php echo $code; ?>"><?php echo e($name); ?></option><?php endforeach; ?></select></div><div class="col-md-6 mb-3"><label class="form-label">Category Targeting</label><select class="form-select" name="categories[]" multiple><?php foreach ($iab_categories as $code => $name): ?><option value="<?php echo $code; ?>"><?php echo e($name); ?></option><?php endforeach; ?></select></div></div>
            <div class="row"><div class="col-md-6 mb-3"><label class="form-label">OS Targeting</label><select class="form-select" name="operating_systems[]" multiple><?php foreach ($operating_systems as $val): ?><option value="<?php echo $val; ?>"><?php echo e($val); ?></option><?php endforeach; ?></select></div><div class="col-md-6 mb-3"><label class="form-label">Device Targeting</label><select class="form-select" name="device_types[]" multiple><?php foreach ($device_types as $val): ?><option value="<?php echo $val; ?>"><?php echo e($val); ?></option><?php endforeach; ?></select></div></div>
            <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Browser Targeting</label><select class="form-select" name="browsers[]" multiple><?php foreach ($browsers as $val): ?><option value="<?php echo $val; ?>"><?php echo e($val); ?></option><?php endforeach; ?></select></div></div>
            <small class="form-text text-muted">Leave blank to target all options in a category.</small>
        </div>
    </div>
    
    <button type="submit" class="btn btn-primary">Submit Campaign for Review</button>
    <a href="campaigns.php" class="btn btn-secondary">Cancel</a>
</form>

<?php include 'template/footer.php'; ?>

<!-- ============================================================ -->
<!-- PERUBAHAN JAVASCRIPT: Tidak ada perubahan yang diperlukan! -->
<!-- ============================================================ -->
<script>
// Kode JavaScript Anda yang sudah canggih tidak perlu diubah.
// Karena kita menempatkan checkbox di dalam #vast_field_wrapper,
// fungsi updateFormVisibility() akan menanganinya secara otomatis.
document.addEventListener('DOMContentLoaded', function() {
    const adFormatSelect = document.getElementById('ad_format');
    const campaignTypeRadios = document.getElementsByName('campaign_type');
    const bannerTypeRadios = document.getElementsByName('banner_type');
    
    const externalFields = document.getElementById('external_fields');
    const cpmRateField = document.getElementById('cpm_rate_field');
    
    const vastFieldWrapper = document.getElementById('vast_field_wrapper');
    const popunderFieldWrapper = document.getElementById('popunder_field_wrapper');
    const bannerFieldWrapper = document.getElementById('banner_field_wrapper');
    const bannerSizeSelect = document.getElementById('banner_size');
    
    const internalBannerOptions = document.getElementById('internal_banner_options');
    const imageBannerFields = document.getElementById('image_banner_fields');
    const htmlBannerFields = document.getElementById('html_banner_fields');

    function updateFormVisibility() {
        const adFormat = adFormatSelect.value;
        const campaignType = document.querySelector('input[name="campaign_type"]:checked').value;

        document.querySelectorAll('.ad-format-fields').forEach(el => { el.style.display = 'none'; });
        bannerSizeSelect.required = false;
        
        if (adFormat === 'vast') {
            vastFieldWrapper.style.display = 'block';
        } else if (adFormat === 'popunder') {
            popunderFieldWrapper.style.display = 'block';
        } else if (adFormat === 'banner') {
            bannerFieldWrapper.style.display = 'block';
            bannerSizeSelect.required = true;
            if (campaignType === 'internal') {
                internalBannerOptions.style.display = 'block';
                updateBannerTypeVisibility();
            } else {
                internalBannerOptions.style.display = 'none';
            }
        }
        
        if (campaignType === 'internal') {
            externalFields.style.display = 'none';
            cpmRateField.style.display = 'block';
        } else {
            externalFields.style.display = 'block';
            cpmRateField.style.display = 'none';
        }
    }

    function updateBannerTypeVisibility() {
        const bannerType = document.querySelector('input[name="banner_type"]:checked').value;
        if (bannerType === 'image') {
            imageBannerFields.style.display = 'block';
            htmlBannerFields.style.display = 'none';
        } else {
            imageBannerFields.style.display = 'none';
            htmlBannerFields.style.display = 'block';
        }
    }

    adFormatSelect.addEventListener('change', updateFormVisibility);
    campaignTypeRadios.forEach(radio => radio.addEventListener('change', updateFormVisibility));
    bannerTypeRadios.forEach(radio => radio.addEventListener('change', updateBannerTypeVisibility));
    
    updateFormVisibility();
});

$(document).ready(function() {
    $('#campaignForm select[multiple]').multiselect({
        buttonWidth: '100%', enableFiltering: true, enableCaseInsensitiveFiltering: true,
        maxHeight: 250, buttonClass: 'form-select text-start',
        templates: { button: '<button type="button" class="multiselect dropdown-toggle" data-bs-toggle="dropdown"><span class="multiselect-selected-text"></span></button>', },
        includeSelectAllOption: true, selectAllText: 'Select All', numberDisplayed: 1
    });
});
</script>
