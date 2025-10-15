<?php
// /ssp.php - FINAL CORRECTED VERSION (SSP Bidding Price Fix)

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ad_logic.php';

function serve_no_bid() {
    header("HTTP/1.1 204 No Content");
    exit;
}

$token = $_GET['token'] ?? '';
if (empty($token)) { http_response_code(401); exit('Missing token'); }

$request_body = file_get_contents('php://input');
$rtb_request = json_decode($request_body, true);
if (!$rtb_request) { http_response_code(400); exit('Invalid request body'); }

$pdo = get_db_connection();

// Ambil informasi publisher, termasuk revenue_share
$stmt = $pdo->prepare("
    SELECT p.id as publisher_id, p.revenue_share, z.id as zone_id, s.id as site_id, s.domain, s.category 
    FROM publishers p 
    JOIN sites s ON p.id = s.publisher_id 
    JOIN zones z ON s.id = z.site_id 
    WHERE p.ssp_token = ? AND p.ssp_enabled = 1 AND s.status = 'active' AND z.status = 'active' 
    LIMIT 1
");
$stmt->execute([$token]);
$ssp_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ssp_info) { http_response_code(403); exit('Invalid or inactive token'); }

// Modifikasi Bid Request dengan data internal kita
$rtb_request['site']['id'] = (string)$ssp_info['site_id'];
$rtb_request['site']['domain'] = $ssp_info['domain'];
$rtb_request['site']['cat'] = [$ssp_info['category']];
$rtb_request['site']['ext']['idzone'] = $ssp_info['zone_id'];
$rtb_request['site']['publisher']['id'] = $ssp_info['publisher_id'];

if (empty($rtb_request['device']['geo']['country'])) {
    $user_ip = $rtb_request['device']['ip'] ?? '127.0.0.1';
    $rtb_request['device']['geo']['country'] = get_country_from_ip_local($user_ip);
}

$result = find_eligible_campaign($rtb_request);

if (empty($result) || $result['type'] !== 'json' || empty($result['content']['adm'])) {
    serve_no_bid();
}

$bid_details = $result['content'];

// =================================================================
// PERBAIKAN LOGIKA BISNIS KRITIS DI SINI
// =================================================================
// Harga penawaran (bid price) harus disesuaikan dengan bagi hasil publisher.
$gross_cpm_price = (float)$bid_details['price'];
$publisher_revenue_share = (float)$ssp_info['revenue_share'];

// Harga yang kita tawarkan ke SSP adalah bagian yang akan diterima oleh publisher.
$net_bid_price = $gross_cpm_price * ($publisher_revenue_share / 100.0);
// =================================================================

$adm = $bid_details['adm'];
$cid = $bid_details['cid'] ?? '';
$crid = $bid_details['crid'] ?? '';
$iurl = $bid_details['iurl'] ?? '';

// Jika setelah perhitungan harga menjadi 0 atau tidak ada iklan, jangan menawar.
if (empty($adm) || $net_bid_price <= 0) {
    serve_no_bid();
}

$APP_SETTINGS = load_app_settings();
$AD_TAG_DOMAIN = $APP_SETTINGS['ad_tag_domain'] ?? ((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);

// nurl sekarang menggunakan harga bid yang sudah disesuaikan
$win_url_params = http_build_query([
    'event' => 'win',
    'cid' => $cid,
    'impid' => $rtb_request['imp'][0]['id'] ?? '1',
    'pid' => $ssp_info['publisher_id'],
    'sid' => $ssp_info['site_id'],
    'zid' => $ssp_info['zone_id'],
    'price' => '${AUCTION_PRICE}' // Makro ini akan diisi oleh platform SSP partner dengan harga kemenangan lelang
]);
$nurl = rtrim($AD_TAG_DOMAIN, '/') . '/api/track.php?' . $win_url_params;

$final_response = [
    'id' => $rtb_request['id'],
    'seatbid' => [[
        'bid' => [[
            'id' => 'bid-' . uniqid(),
            'impid' => $rtb_request['imp'][0]['id'] ?? '1',
            'price' => round($net_bid_price, 4), // Kirim harga yang sudah disesuaikan
            'adm' => $adm,
            'nurl' => $nurl,
            'cid' => $cid,
            'crid' => $crid,
            'iurl' => $iurl
        ]]
    ]],
    'cur' => 'USD'
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($final_response);
exit;
?>