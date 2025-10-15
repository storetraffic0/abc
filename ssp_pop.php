<?php
// /ssp_pop.php - DEDICATED Popunder SSP Endpoint with NURL FIX

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/popunder_logic.php'; 

function serve_no_bid_pop() {
    header("HTTP/1.1 204 No Content");
    exit;
}

$token = $_GET['token'] ?? '';
if (empty($token)) { 
    http_response_code(401); 
    exit('Missing token'); 
}

$request_body = file_get_contents('php://input');
$rtb_request = json_decode($request_body, true);
if (!$rtb_request) { 
    http_response_code(400); 
    exit('Invalid request body'); 
}

$pdo = get_db_connection();

// Query publisher info dari token
$stmt = $pdo->prepare("
    SELECT id as publisher_id, revenue_share 
    FROM publishers 
    WHERE ssp_token = ? AND ssp_enabled = 1 AND status = 'active'
");
$stmt->execute([$token]);
$publisher_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$publisher_info) {
    http_response_code(403);
    exit('Invalid or inactive token');
}

// Tambahkan informasi publisher ke request
$rtb_request['site']['publisher']['id'] = $publisher_info['publisher_id'];

// Tambahkan info geo jika tidak ada
if (empty($rtb_request['device']['geo']['country'])) {
    $user_ip = $rtb_request['device']['ip'] ?? '127.0.0.1';
    $rtb_request['device']['geo']['country'] = get_country_from_ip_local($user_ip);
}

// Cari kampanye yang eligible
$winner = find_eligible_popunder_campaign($rtb_request);
$adm = $winner['ad_material'] ?? null;

if (empty($winner) || empty($adm) || !filter_var($adm, FILTER_VALIDATE_URL)) {
    serve_no_bid_pop();
}

// Hitung revenue share
$gross_cpm_price = (float)($winner['final_cpm'] ?? 0);
$publisher_revenue_share = (float)$publisher_info['revenue_share'];
$net_bid_price = $gross_cpm_price * ($publisher_revenue_share / 100.0);

if ($net_bid_price <= 0) {
    serve_no_bid_pop();
}

// ID untuk kampanye dan creative
$campaign_id = $winner['campaign']['id'] ?? '';
$crid = 'crid-' . $campaign_id;
$impression_id = $rtb_request['imp'][0]['id'] ?? ('imp_' . uniqid());

// ============================================================
// PERBAIKAN UTAMA: Generate nurl ke api/pop_track.php (BUKAN api/track.php)
// ============================================================
$APP_SETTINGS = load_app_settings();
$AD_TAG_DOMAIN = $APP_SETTINGS['ad_tag_domain'] ?? ((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);

// Buat parameter untuk nurl - SEKARANG MENGARAH KE pop_track.php
$nurl_params = http_build_query([
    'event' => 'win',
    'cid' => $campaign_id,
    'impid' => $impression_id,
    'pid' => $publisher_info['publisher_id'],
    'price' => '${AUCTION_PRICE}' // Makro standar OpenRTB
]);

// Buat URL lengkap untuk nurl - MENGARAH KE POP_TRACK.PHP
$nurl = rtrim($AD_TAG_DOMAIN, '/') . '/api/pop_track.php?' . $nurl_params;
// ============================================================

// Bangun respons OpenRTB
$final_response = [
    'id' => $rtb_request['id'],
    'seatbid' => [[
        'bid' => [[
            'id' => 'bid-pop-' . uniqid(),
            'impid' => $impression_id,
            'price' => round($net_bid_price, 4),
            'adm' => $adm,
            'nurl' => $nurl, // <-- NURL ke POP_TRACK.PHP
            'cid' => (string)$campaign_id,
            'crid' => (string)$crid,
        ]]
    ]],
    'cur' => 'USD'
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($final_response);
exit;
?>