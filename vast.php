<?php
// /vast.php - FINAL PRODUCTION VERSION

// Header CORS untuk VAST tester
header("Access-Control-Allow-Origin: *");
header("Access-control-allow-methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// Menangani preflight request dari browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ad_logic.php'; // Memuat file logika inti

// Fungsi untuk menyajikan VAST kosong jika diperlukan
function serve_empty_vast() {
    if (!headers_sent()) {
        header("Content-Type: application/xml; charset=utf-8");
    }
    echo '<?xml version="1.0" encoding="UTF-8"?><VAST version="3.0"></VAST>';
    exit;
}

$zone_id = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;
if (!$zone_id) {
    serve_empty_vast();
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT s.id as site_id, s.domain, s.category FROM zones z JOIN sites s ON z.site_id = s.id WHERE z.id = ?");
$stmt->execute([$zone_id]);
$site_info = $stmt->fetch(PDO::FETCH_ASSOC);

$user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$country_code = get_country_from_ip_local($user_ip); // Menggunakan GeoIP lokal

// Bangun request OpenRTB secara manual untuk dikirim ke logika inti
$openrtb_request = [
    'id' => uniqid('vast-prod-'),
    'imp' => [['id' => '1']],
    'site' => [
        'id' => (string)($site_info['site_id'] ?? 0),
        'domain' => $site_info['domain'] ?? '',
        'cat' => [$site_info['category'] ?? 'IAB24'],
        'ext' => ['idzone' => $zone_id]
    ],
    'device' => [
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $user_ip,
        'geo' => ['country' => $country_code]
    ],
    'user' => ['id' => 'user_' . md5($user_ip . ($_SERVER['HTTP_USER_AGENT'] ?? ''))]
];

// Panggil fungsi logika inti secara langsung
$result = find_eligible_campaign($openrtb_request);

// Proses hasil dari logika inti
$vast_xml_to_serve = '';
if (!empty($result) && $result['type'] === 'json') {
    $vast_xml_to_serve = $result['content']['adm'] ?? '';
} elseif (!empty($result) && $result['type'] === 'xml') {
    $vast_xml_to_serve = $result['content'];
}

// Kirim VAST XML yang sebenarnya (atau VAST kosong jika tidak ada)
header("Content-Type: application/xml; charset=utf-8");
echo $vast_xml_to_serve ?: '<?xml version="1.0" encoding="UTF-8"?><VAST version="3.0"></VAST>';
exit;
?>