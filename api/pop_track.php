<?php
// /api/pop_track.php - DEDICATED Popunder Tracker (FIXED FOR popunder_stats TABLE)

header("Content-Type: image/gif");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

function serve_pixel() {
    echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICRAEAOw==');
    exit;
}

try {
    $event = $_GET['event'] ?? 'impression'; // Default ke 'impression' jika tidak ada
    $pdo = get_db_connection();

    // Deteksi info pengguna (negara, perangkat, dll)
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $country_code = country_code_alpha2_to_alpha3(get_country_from_ip_local($ip_address));
    $os = 'Unknown'; if (stripos($user_agent, 'Windows') !== false) $os = 'Windows'; elseif (stripos($user_agent, 'Macintosh') !== false) $os = 'macOS'; elseif (stripos($user_agent, 'Android') !== false) $os = 'Android'; elseif (stripos($user_agent, 'iPhone') !== false || stripos($user_agent, 'iPad') !== false) $os = 'iOS'; elseif (stripos($user_agent, 'Linux') !== false) $os = 'Linux';
    $device_type = 'Desktop'; if (stripos($user_agent, 'Mobi') !== false) $device_type = 'Mobile'; elseif (stripos($user_agent, 'Tablet') !== false) $device_type = 'Tablet';
    $browser = 'Unknown'; if (stripos($user_agent, 'Edg') !== false) $browser = 'Edge'; elseif (stripos($user_agent, 'Chrome') !== false && !stripos($user_agent, 'Chromium')) $browser = 'Chrome'; elseif (stripos($user_agent, 'Safari') !== false && !stripos($user_agent, 'Chrome')) $browser = 'Safari'; elseif (stripos($user_agent, 'Firefox') !== false) $browser = 'Firefox'; elseif (stripos($user_agent, 'OPR') !== false || stripos($user_agent, 'Opera') !== false) $browser = 'Opera';

    // --- LOGIKA UNTUK EVENT 'win' DARI PARTNER EKSTERNAL ---
    if ($event === 'win') {
        $campaign_id = (int)($_GET['cid'] ?? 0);
        $publisher_id = (int)($_GET['pid'] ?? 0); // Publisher ID harus dikirim di nurl
        $clearing_price = (float)($_GET['price'] ?? 0);

        if ($campaign_id > 0 && $publisher_id > 0 && $clearing_price > 0) {
            $stmt = $pdo->prepare("SELECT advertiser_id FROM campaigns WHERE id = ?");
            $stmt->execute([$campaign_id]);
            $advertiser_id = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT revenue_share FROM publishers WHERE id = ?");
            $stmt->execute([$publisher_id]);
            $revenue_share = (float)($stmt->fetchColumn() ?? 0);

            $impression_revenue = $clearing_price / 1000.0;
            $publisher_earning = $impression_revenue * ($revenue_share / 100.0);
            $platform_earning = $impression_revenue - $publisher_earning;

            // PERBAIKAN: INSERT ke tabel `popunder_stats`
            $sql = "INSERT INTO popunder_stats (event_type, campaign_id, advertiser_id, publisher_id, cpm_price, platform_revenue, publisher_revenue, country, device_type, os, browser, is_processed) 
                    VALUES ('win', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt_insert = $pdo->prepare($sql);
            $stmt_insert->execute([$campaign_id, $advertiser_id, $publisher_id, $clearing_price, $platform_earning, $publisher_earning, $country_code, $device_type, $os, $browser]);
        }
    } 
    // --- LOGIKA UNTUK EVENT 'impression' DARI KAMPANYE INTERNAL ---
    else {
        $session_id = $_GET['sid'] ?? '';
        if (empty($session_id) || !preg_match('/^pop_[a-f0-9]{13}$/', $session_id)) {
            serve_pixel();
        }

        $sql_get_data = "SELECT ps.campaign_id, ps.zone_id, ps.cpm_price, z.site_id, c.advertiser_id, p.id AS publisher_id, p.revenue_share
                         FROM pop_sessions ps
                         JOIN campaigns c ON ps.campaign_id = c.id
                         JOIN zones z ON ps.zone_id = z.id
                         JOIN sites s ON z.site_id = s.id
                         JOIN publishers p ON s.publisher_id = p.id
                         WHERE ps.id = ?";
        
        $stmt = $pdo->prepare($sql_get_data);
        $stmt->execute([$session_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $cpm_price = (float)$data['cpm_price'];
            $revenue_share = (float)($data['revenue_share'] ?? 0);

            $impression_revenue = $cpm_price / 1000.0;
            $publisher_earning = $impression_revenue * ($revenue_share / 100.0);
            $platform_earning = $impression_revenue - $publisher_earning;
            
            // PERBAIKAN: INSERT ke tabel `popunder_stats`
            $sql_insert = "INSERT INTO popunder_stats (event_type, campaign_id, advertiser_id, publisher_id, site_id, zone_id, cpm_price, platform_revenue, publisher_revenue, country, device_type, os, browser, is_processed) 
                           VALUES ('impression', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
            
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([$data['campaign_id'], $data['advertiser_id'], $data['publisher_id'], $data['site_id'], $data['zone_id'], $cpm_price, $platform_earning, $publisher_earning, $country_code, $device_type, $os, $browser]);

            $pdo->prepare("DELETE FROM pop_sessions WHERE id = ?")->execute([$session_id]);
        }
    }
} catch (Exception $e) {
    error_log("Popunder Tracking Error: " . $e->getMessage() . " | GET Params: " . http_build_query($_GET));
}

serve_pixel();
?>