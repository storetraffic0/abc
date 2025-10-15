<?php
// banner_frame.php - VERSI FINAL DENGAN PELACAKAN PERANGKAT LENGKAP

header('X-Frame-Options: ALLOWALL');
header('Content-Type: text/html; charset=utf-8');

require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/banner_logic.php';

$zone_id = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;
if ($zone_id <= 0) {
    $APP_SETTINGS = load_app_settings();
    $ad_tag_domain = rtrim($APP_SETTINGS['ad_tag_domain'], '/');
    echo '<a href="' . $ad_tag_domain . '" target="_blank"><img src="' . $ad_tag_domain . '/default_banners/300x250.jpg" style="width:100%;height:100%;border:0"></a>';
    exit;
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT z.banner_size, s.id as site_id, s.domain, s.category, p.id as publisher_id FROM zones z JOIN sites s ON z.site_id = s.id JOIN publishers p ON s.publisher_id = p.id WHERE z.id = ? AND z.format = 'banner' AND s.status = 'active' AND p.status = 'active'");
$stmt->execute([$zone_id]);
$zone_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$zone_info) { exit; }

$country_code = get_country_from_ip_local($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
list($width, $height) = explode('x', $zone_info['banner_size']);
$rtb_request = ['id' => 'direct-iframe-' . uniqid(), 'imp' => [['id' => 'imp-1', 'banner' => ['w' => (int)$width, 'h' => (int)$height]]], 'site' => ['id' => $zone_info['site_id'], 'domain' => $zone_info['domain'], 'cat' => [$zone_info['category']], 'publisher' => ['id' => $zone_info['publisher_id']]], 'device' => ['ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'geo' => ['country' => $country_code]]];

$winner = find_eligible_banner_campaign($rtb_request);

if (!$winner) {
    $APP_SETTINGS = load_app_settings();
    $ad_tag_domain = rtrim($APP_SETTINGS['ad_tag_domain'], '/');
    $size = $zone_info['banner_size'] ?? '300x250';
    echo '<a href="' . $ad_tag_domain . '" target="_blank"><img src="' . $ad_tag_domain . '/default_banners/' . $size . '.jpg" style="width:100%;height:100%;border:0"></a>';
    echo "\n<img src=\"{$ad_tag_domain}/api/banner_track.php?event=impression&zid={$zone_id}\" width=\"1\" height=\"1\" style=\"display:none\" alt=\"\"/>";
    exit;
}

$winning_ad_format = $winner['campaign']['ad_format'];
$ad_material_type = $winner['ad_material']['type'];
$adm = '';
$APP_SETTINGS = load_app_settings();
$AD_TAG_DOMAIN = $APP_SETTINGS['ad_tag_domain'] ?? ((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);

if ($winning_ad_format === 'vast' || $ad_material_type === 'vast') {
    $vast_url = $winner['ad_material']['url'] ?? '';
    if (empty($vast_url)) exit;
    $vast_url_safe = addslashes($vast_url);
    $player_library_url = '/js/adplayer.js'; 
    $adm = <<<HTML
<!DOCTYPE html><html><head><style>html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;background-color:#000;}#player1{position:relative;width:100%;height:100%;}#player1 video{object-fit:cover;}.adserve-tv-player .overlay{display:none!important;}</style></head><body><div id="player1"></div><script src="{$player_library_url}"></script><script>(function(){var checkPlayer=setInterval(function(){if(window.adserve&&window.adserve.tv&&typeof window.adserve.tv.Player==="function"){clearInterval(checkPlayer);initializePlayer()}},100);function initializePlayer(){var container=document.getElementById("player1");var vastUrl="{$vast_url_safe}";try{var player1=new adserve.tv.Player(container,{width:"auto",height:"auto",src:"https://video.rmhfrtnd.com/production/prerolls/oil-show11.mp4",autoplay:!0,loop:!1,muted:!0,volume:1,controls:!1,ads:{enabled:!0,desktop:{becomeInView:!0,inView:{preroll:!0,midroll:!0,vastUrl:vastUrl,interval:1e3,retryInterval:1e3},notInView:{preroll:!0,midroll:!0,vastUrl:vastUrl,interval:15e3,retryInterval:1e4}},mobile:{inView:{preroll:!0,vastUrl:vastUrl,interval:1e3,retryInterval:1e3},notInView:{vastUrl:vastUrl,interval:15e3,retryInterval:1e4}},schain:{ver:"1.0",complete:1,nodes:[{asi:"adserve.tv",hp:1,sid:""}]}}});player1.addEventListener("PlayerError",function(e){console.log("Ad player error:",e)})}catch(e){console.error("Gagal menginisialisasi Ad Player:",e)}}})();</script></body></html>
HTML;
} else {
    if ($ad_material_type === 'html') { $adm = $winner['ad_material']['html']; } 
    else { $click_url = $winner['campaign']['click_url'] ?? '#'; $adm = "<a href='{$click_url}' target='_blank'><img src='{$winner['ad_material']['url']}' width='100%' height='100%' style='border:0;'></a>"; }
}
echo $adm;

// ============================================================
// PERBAIKAN LOGIKA: Menambahkan info perangkat ke tracker impresi
// ============================================================
$tracker_url = '';
$params = [
    'event' => 'impression',
    'cid'   => $winner['campaign']['id'],
    'pid'   => $zone_info['publisher_id'],
    'sid'   => $zone_info['site_id'],
    'zid'   => $zone_id,
    'size'  => $zone_info['banner_size'],
    'cc'    => $country_code,
    'dev'   => $winner['device_info']['type'],   // DITAMBAHKAN
    'os'    => $winner['device_info']['os'],      // DITAMBAHKAN
    'brw'   => $winner['device_info']['browser'] // DITAMBAHKAN
];
$tracker_url = rtrim($AD_TAG_DOMAIN, '/') . '/api/banner_track.php?' . http_build_query($params);

echo "\n<img src=\"{$tracker_url}\" width=\"1\" height=\"1\" style=\"display:none\" alt=\"\"/>";
?>