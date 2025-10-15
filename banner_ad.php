<?php
// Set header untuk respons JSON dan CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Jika ini adalah request OPTIONS (preflight), hentikan di sini
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Ambil konfigurasi dari file atau database
$APP_SETTINGS = [
    'ad_tag_domain' => 'https://ssp.svradv.com',
    'default_banner_path' => '/default_banners/',
    'track_path' => '/api/banner_track.php'
];

// Ambil raw data dari body request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log input untuk debugging
error_log("banner_ad.php - Input data: " . print_r($data, true));

// Validasi data request
if (!isset($data['zone_id']) || !isset($data['width']) || !isset($data['height'])) {
    error_log("banner_ad.php - Missing required parameters");
    echo json_encode(['error' => 'Parameter yang diperlukan tidak ada']);
    exit;
}

// Ekstrak parameter
$zone_id = (int)$data['zone_id'];
$width = (int)$data['width'];
$height = (int)$data['height'];
$domain = $data['domain'] ?? '';
$token = $data['token'] ?? '';
$referrer = $data['referrer'] ?? '';
$url = $data['url'] ?? '';
$debug = $data['debug'] ?? false;

require_once 'includes/db_connection.php';
$pdo = get_db_connection();

// Log untuk debugging
error_log("banner_ad.php - Processing zone_id: $zone_id, size: {$width}x{$height}");

// Log impression
try {
    $stmt = $pdo->prepare("INSERT INTO banner_impressions (zone_id, referrer, page_url, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $zone_id,
        $referrer,
        $url,
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    error_log("banner_ad.php - Impression logged successfully");
} catch (Exception $e) {
    error_log("banner_ad.php - Failed to log impression: " . $e->getMessage());
}

// Try to get campaign from ad server
$campaign_id = 0;
$banner_url = '';
$banner_html = '';
$click_url = '';

try {
    // Debug untuk menampilkan banner size yang dicari
    error_log("banner_ad.php - Looking for banner with size: {$width}x{$height}");
    
    // Query untuk mencari kampanye banner yang sesuai
    $stmt = $pdo->prepare("
        SELECT c.id, cd.banner_url, cd.banner_html, cd.banner_click_url
        FROM campaigns c
        JOIN campaign_details cd ON c.id = cd.campaign_id
        WHERE c.ad_format = 'banner' 
        AND c.status = 'active' 
        AND (cd.banner_size = ? OR cd.banner_size IS NULL)
        LIMIT 1
    ");
    $stmt->execute([$width . 'x' . $height]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($campaign) {
        $campaign_id = $campaign['id'];
        $banner_url = $campaign['banner_url'];
        $banner_html = $campaign['banner_html'];
        $click_url = $campaign['banner_click_url'];
        
        // Log untuk debugging
        error_log("banner_ad.php - Found campaign ID: $campaign_id");
        error_log("banner_ad.php - Banner URL: " . (empty($banner_url) ? "KOSONG" : "ADA"));
        error_log("banner_ad.php - Banner HTML: " . (empty($banner_html) ? "KOSONG" : "ADA"));
        
        // Log konten HTML untuk debugging jika diaktifkan
        if ($debug) {
            error_log("banner_ad.php - HTML content: " . substr($banner_html, 0, 500) . (strlen($banner_html) > 500 ? '...' : ''));
        }
    } else {
        error_log("banner_ad.php - No active campaign found for size: {$width}x{$height}");
    }
    
} catch (Exception $e) {
    error_log("banner_ad.php - Database error: " . $e->getMessage());
}

// Jika tidak ada kampanye yang ditemukan, kembalikan respons kosong
if ($campaign_id === 0) {
    error_log("banner_ad.php - No campaign available, returning empty response");
    echo json_encode(['html' => '']);
    exit;
}

// URL untuk pelacakan
$click_tracking_url = "{$APP_SETTINGS['ad_tag_domain']}{$APP_SETTINGS['track_path']}?type=click&zone=$zone_id&campaign=$campaign_id&url=" . urlencode($click_url);
$impression_tracking_url = "{$APP_SETTINGS['ad_tag_domain']}{$APP_SETTINGS['track_path']}?type=impression&zone=$zone_id&campaign=$campaign_id";

// Pilih metode untuk menampilkan iklan berdasarkan konten yang tersedia
$html = '';

// OPSI 1: Jika ada konten HTML dari pihak ketiga (seperti dari magsrv)
if (!empty($banner_html)) {
    // Buat ID unik untuk wrapper
    $wrapper_id = 'ad-wrapper-' . $campaign_id . '-' . time();
    
    // Buat wrapper dengan script pengecekan, untuk menggunakan konten HTML yang sudah ada
    $html = '
    <div id="' . $wrapper_id . '" style="width:' . $width . 'px;height:' . $height . 'px;">
        ' . $banner_html . '
    </div>
    <script>
    (function() {
        var wrapperEl = document.getElementById("' . $wrapper_id . '");
        
        // Tambahkan event listener untuk click jika tidak ada URL pelacakan dalam HTML
        if (wrapperEl) {
            var links = wrapperEl.getElementsByTagName("a");
            if (links.length === 0) {
                wrapperEl.addEventListener("click", function() {
                    window.open("' . $click_tracking_url . '", "_blank");
                });
                wrapperEl.style.cursor = "pointer";
            }
        }

        // Periksa apakah iklan dimuat dalam 3 detik
        setTimeout(function() {
            if (!wrapperEl) return;
            var iframes = wrapperEl.querySelectorAll("iframe");
            var visibleImages = wrapperEl.querySelectorAll("img:not([style*=\"visibility:hidden\"])");
            
            // Jika tidak ada iframe atau gambar terlihat, mungkin iklan tidak dimuat
            if (iframes.length === 0 && visibleImages.length === 0) {
                console.log("Third-party ad might have failed to load");
            }
        }, 3000);
    })();
    </script>
    <img src="' . htmlspecialchars($impression_tracking_url) . '" width="1" height="1" style="position:absolute; visibility:hidden;" alt="" />';
}
// OPSI 2: Jika menggunakan URL banner (gambar)
else if (!empty($banner_url)) {
    // Gunakan banner berbasis gambar
    $html = '<a href="' . htmlspecialchars($click_tracking_url) . '" target="_blank" rel="nofollow">';
    $html .= '<img src="' . htmlspecialchars($banner_url) . '" width="' . $width . '" height="' . $height . '" alt="Advertisement" style="border:0">';
    $html .= '</a>';
    $html .= '<img src="' . htmlspecialchars($impression_tracking_url) . '" width="1" height="1" style="position:absolute; visibility:hidden;" alt="" />';
}
// OPSI 3: Fallback jika tidak ada konten yang valid
else {
    // Gunakan iklan default
    $html = '<a href="https://clicterra.com" target="_blank">';
    $html .= '<img src="' . $APP_SETTINGS['ad_tag_domain'] . $APP_SETTINGS['default_banner_path'] . $width . 'x' . $height . '.jpg" width="' . $width . '" height="' . $height . '" alt="Ad" style="border:0">';
    $html .= '</a>';
}

// Pastikan respons tidak kosong
if (empty(trim($html))) {
    error_log("banner_ad.php - Generated empty HTML, using fallback");
    $html = '<a href="https://clicterra.com" target="_blank"><img src="' . $APP_SETTINGS['ad_tag_domain'] . $APP_SETTINGS['default_banner_path'] . $width . 'x' . $height . '.jpg" width="' . $width . '" height="' . $height . '" alt="Ad" style="border:0"></a>';
}

error_log("banner_ad.php - Returning HTML content, length: " . strlen($html));

// Isi respons
$response = [
    'html' => $html,
    'campaign_id' => $campaign_id,
    'type' => !empty($banner_html) ? 'html' : (!empty($banner_url) ? 'image' : 'fallback')
];

// Tambahkan informasi debugging jika diminta
if ($debug) {
    $response['debug'] = [
        'has_banner_url' => !empty($banner_url),
        'has_banner_html' => !empty($banner_html),
        'banner_size' => $width . 'x' . $height,
        'html_length' => strlen($html)
    ];
}

// Kembalikan respons dalam format JSON
echo json_encode($response);