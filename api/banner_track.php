<?php
// /api/banner_track.php - VERSI FINAL DENGAN PERBAIKAN TIPE DATA ID

header("Content-Type: image/gif");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

function serve_pixel() { echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='); exit; }

$event = $_GET['event'] ?? '';
$redirect_url = $_GET['redirect'] ?? '';

if ($event === 'click' && !empty($redirect_url) && filter_var($redirect_url, FILTER_VALIDATE_URL)) {
    try { log_banner_event($_GET); } catch (Exception $e) { error_log("Banner Click Tracking Error: " . $e->getMessage()); }
    header("Location: " . $redirect_url);
    exit;
}

try { log_banner_event($_GET); } catch (Exception $e) { error_log("Banner Tracking Error: " . $e->getMessage() . " | Params: " . http_build_query($_GET)); }

serve_pixel();

function log_banner_event($params) {
    $pdo = get_db_connection();
    $event = $params['event'] ?? '';
    if (!in_array($event, ['win', 'impression', 'click'])) { return; }

    // ============================================================
    // PERBAIKAN DI SINI: Jangan paksa ID menjadi (int) terlalu dini
    // ============================================================
    $campaign_id = $params['cid'] ?? '0'; // Ambil sebagai string

    // Konversi ke integer hanya untuk validasi, bukan untuk query
    if ((int)$campaign_id <= 0) return;

    $zone_id = (int)($params['zid'] ?? 0);
    $publisher_id = (int)($params['pid'] ?? 0);
    $site_id = (int)($params['sid'] ?? 0);
    $banner_size = $params['size'] ?? null;
    $country = $params['cc'] ?? null;
    $device_type = $params['dev'] ?? null;
    $os = $params['os'] ?? null;
    $browser = $params['brw'] ?? null;
    
    if ($event === 'win' && (int)$publisher_id > 0) {
        $clearing_price = (float)($params['price'] ?? 0.0);
        
        $stmt_camp = $pdo->prepare("SELECT advertiser_id, cpm_rate FROM campaigns WHERE id = ?");
        $stmt_camp->execute([$campaign_id]); // Gunakan string ID di sini
        $campaign_data = $stmt_camp->fetch(PDO::FETCH_ASSOC);

        $stmt_pub = $pdo->prepare("SELECT revenue_share FROM publishers WHERE id = ?");
        $stmt_pub->execute([$publisher_id]);
        $revenue_share = $stmt_pub->fetchColumn();

        if ($campaign_data && $revenue_share !== false) {
            $advertiser_id = (int)$campaign_data['advertiser_id'];
            $cpm_to_record = (float)$campaign_data['cpm_rate'];
            $price_for_revenue = ($clearing_price > 0) ? $clearing_price : $cpm_to_record;
            
            $impression_revenue = $price_for_revenue / 1000.0;
            $publisher_earning = $impression_revenue * ((float)$revenue_share / 100.0);
            $platform_earning = $impression_revenue - $publisher_earning;

            $sql = "INSERT INTO banner_stats (event_type, campaign_id, advertiser_id, publisher_id, site_id, zone_id, cpm_price, platform_revenue, publisher_revenue, country, device_type, os, browser, banner_size, is_processed) 
                    VALUES ('win', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$campaign_id, $advertiser_id, $publisher_id, $site_id, $zone_id, $cpm_to_record, $platform_earning, $publisher_earning, $country, $device_type, $os, $browser, $banner_size]);
        }
    }
    elseif ($event === 'impression' && ((int)$publisher_id > 0 || $zone_id > 0)) {
        if ((int)$publisher_id <= 0 && $zone_id > 0) {
             $stmt_lookup = $pdo->prepare("SELECT s.publisher_id FROM zones z JOIN sites s ON z.site_id = s.id WHERE z.id = ?");
             $stmt_lookup->execute([$zone_id]);
             $publisher_id = (int)$stmt_lookup->fetchColumn();
        }
        
        if ((int)$publisher_id <= 0) return;

        $stmt_camp = $pdo->prepare("SELECT advertiser_id, cpm_rate FROM campaigns WHERE id = ?");
        $stmt_camp->execute([$campaign_id]); // Gunakan string ID di sini
        $campaign_data = $stmt_camp->fetch(PDO::FETCH_ASSOC);

        $stmt_pub = $pdo->prepare("SELECT revenue_share FROM publishers WHERE id = ?");
        $stmt_pub->execute([$publisher_id]);
        $revenue_share = $stmt_pub->fetchColumn();

        if ($campaign_data && $revenue_share !== false) {
            $advertiser_id = (int)$campaign_data['advertiser_id'];
            $cpm_price = (float)$campaign_data['cpm_rate'];
            $impression_revenue = $cpm_price / 1000.0;
            $publisher_earning = $impression_revenue * ((float)$revenue_share / 100.0);
            $platform_earning = $impression_revenue - $publisher_earning;
            
            $sql = "INSERT INTO banner_stats (event_type, campaign_id, advertiser_id, publisher_id, site_id, zone_id, cpm_price, platform_revenue, publisher_revenue, country, device_type, os, browser, banner_size, is_processed) 
                    VALUES ('impression', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$campaign_id, $advertiser_id, $publisher_id, $site_id, $zone_id, $cpm_price, $platform_earning, $publisher_earning, $country, $device_type, $os, $browser, $banner_size]);
        }
    }
}
?>