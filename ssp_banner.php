<?php
// /ssp_banner.php - VERSI BARU YANG MENGHORMATI UKURAN DARI REQUEST PARTNER

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/banner_logic.php'; 

function serve_no_bid() { header("HTTP/1.1 204 No Content"); exit; }

$token = $_GET['token'] ?? '';
if (empty($token)) { http_response_code(401); exit('Missing token'); }

$request_body = file_get_contents('php://input');
$rtb_request = json_decode($request_body, true);
if (!$rtb_request || !isset($rtb_request['imp'][0]['banner']['w']) || !isset($rtb_request['imp'][0]['banner']['h'])) {
    http_response_code(400); exit('Invalid Request: Missing banner dimensions'); 
}

$pdo = get_db_connection();

// ============================================================
// PERBAIKAN UTAMA: Query hanya untuk verifikasi, tidak mengambil ukuran
// ============================================================
// Query ini sekarang hanya untuk memvalidasi token dan mendapatkan info publisher.
// Kita tidak lagi mengambil `zone_id` atau `banner_size` dari sini.
$sql = "SELECT p.id as publisher_id, p.revenue_share, s.id as site_id, s.domain, s.category 
        FROM publishers p
        LEFT JOIN sites s ON p.id = s.publisher_id
        WHERE p.ssp_token = :token 
          AND p.ssp_enabled = 1 
          AND p.status = 'active'
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([':token' => $token]);
$ssp_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ssp_info) {
    http_response_code(403); 
    exit('Invalid Token or SSP not enabled.'); 
}

// Mengisi data request, TAPI TIDAK MENGGANTI UKURAN BANNER
$rtb_request['site']['publisher']['id'] = $ssp_info['publisher_id'];
// Menggunakan site info dari database jika tersedia, jika tidak, gunakan dari request asli
$rtb_request['site']['id'] = $ssp_info['site_id'] ?? $rtb_request['site']['id'] ?? null;
$rtb_request['site']['domain'] = $ssp_info['domain'] ?? $rtb_request['site']['domain'] ?? null;
$rtb_request['site']['category'] = !empty($ssp_info['category']) ? [$ssp_info['category']] : ($rtb_request['site']['cat'] ?? []);

if (empty($rtb_request['device']['geo']['country']) && !empty($rtb_request['device']['ip'])) {
    $rtb_request['device']['geo']['country'] = get_country_from_ip_local($rtb_request['device']['ip']);
}

// Sekarang, $rtb_request berisi ukuran yang benar dari partner
$winner = find_eligible_banner_campaign($rtb_request);
if (empty($winner)) { serve_no_bid(); }

$gross_cpm_price = (float)($winner['final_cpm'] ?? 0);
$publisher_revenue_share = (float)$ssp_info['revenue_share'];
$net_bid_price = $gross_cpm_price * ($publisher_revenue_share / 100.0);
if ($net_bid_price <= 0) { serve_no_bid(); }

$campaign_id = $winner['campaign']['id'] ?? '';
$impression_id = $rtb_request['imp'][0]['id'] ?? ('imp_' . uniqid());
$APP_SETTINGS = load_app_settings();
$AD_TAG_DOMAIN = $APP_SETTINGS['ad_tag_domain'] ?? ((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);
$adm = '';

$nurl_params = http_build_query([
    'event' => 'win', 'cid'   => $campaign_id, 'impid' => $impression_id,
    'pid'   => $ssp_info['publisher_id'], 'sid'   => $rtb_request['site']['id'], 'zid'   => null, // zone_id tidak relevan untuk SSP
    'price' => '${AUCTION_PRICE}', 'size'  => $winner['banner_size'], 'os'    => $winner['device_info']['os'],
    'dev'   => $winner['device_info']['type'], 'brw'   => $winner['device_info']['browser'],
    'cc'    => $rtb_request['device']['geo']['country'] ?? null
]);
$nurl = rtrim($AD_TAG_DOMAIN, '/') . '/api/banner_track.php?' . $nurl_params;

if ($winner['ad_material']['type'] === 'vast') {
    $vast_url = $winner['ad_material']['url'] ?? '';
    if (empty($vast_url)) { serve_no_bid(); }
    $vast_url_safe = addslashes($vast_url);
    $player_library_url = '/js/adplayer.js'; 
    $adm = <<<HTML
<!DOCTYPE html><html><head><style>html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background-color:#000;}#player1{position:relative;width:100%;height:100%;}#player1 video{object-fit:cover;}.adserve-tv-player .overlay{display:none!important;}</style></head><body><div id="player1"></div><script src="{$player_library_url}"></script><script>(function(){var checkPlayer=setInterval(function(){if(window.adserve&&window.adserve.tv&&typeof window.adserve.tv.Player==="function"){clearInterval(checkPlayer);initializePlayer()}},100);function initializePlayer(){var container=document.getElementById("player1");var vastUrl="{$vast_url_safe}";try{var player1=new adserve.tv.Player(container,{width:"auto",height:"auto",src:"https://video.rmhfrtnd.com/production/prerolls/oil-show11.mp4",autoplay:!0,loop:!1,muted:!0,volume:1,controls:!1,ads:{enabled:!0,desktop:{becomeInView:!0,inView:{preroll:!0,midroll:!0,vastUrl:vastUrl,interval:1e3,retryInterval:1e3},notInView:{preroll:!0,midroll:!0,vastUrl:vastUrl,interval:15e3,retryInterval:1e4}},mobile:{inView:{preroll:!0,vastUrl:vastUrl,interval:1e3,retryInterval:1e3},notInView:{vastUrl:vastUrl,interval:15e3,retryInterval:1e4}},schain:{ver:"1.0",complete:1,nodes:[{asi:"adserve.tv",hp:1,sid:""}]}}});player1.addEventListener("PlayerError",function(e){console.log("Ad player error:",e)})}catch(e){console.error("Gagal menginisialisasi Ad Player:",e)}}})();</script></body></html>
HTML;
} else { // Banner (image atau html)
    if ($winner['ad_material']['type'] === 'html') { $adm = $winner['ad_material']['html']; } 
    else { $click_url = $winner['campaign']['click_url'] ?? '#'; $adm = "<a href='{$click_url}' target='_blank'><img src='{$winner['ad_material']['url']}' width='100%' height='100%' style='border:0;'></a>"; }
}

$final_response = ['id' => $rtb_request['id'], 'seatbid' => [['bid' => [['id' => 'bid-banner-' . uniqid(), 'impid' => $impression_id, 'price' => round($net_bid_price, 4), 'adm' => $adm, 'nurl' => $nurl, 'cid' => (string)$campaign_id, 'crid' => 'crid-' . $campaign_id, 'w' => $rtb_request['imp'][0]['banner']['w'], 'h' => $rtb_request['imp'][0]['banner']['h'] ]]]], 'cur' => 'USD'];
header('Content-Type: application/json; charset=utf-8');
echo json_encode($final_response);
exit;
?>