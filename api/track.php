<?php
// /api/track.php - FINAL PRODUCTION VERSION

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Mengirimkan respons gambar GIF transparan 1x1 piksel dan menghentikan skrip.
 */
function send_pixel_response() {
    if (!headers_sent()) {
        header('Content-Type: image/gif');
        // GIF 1x1 piksel transparan, base64 encoded
        echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICRAEAOw==');
    }
    exit;
}

$event_type = $_GET['event'] ?? null;

// Jika tidak ada event, tidak ada yang perlu dilakukan.
if (empty($event_type)) {
    send_pixel_response();
}

try {
    $pdo = get_db_connection();

    // =================================================================
    // BAGIAN 1: LOGIKA KHUSUS UNTUK NOTIFIKASI KEMENANGAN LELANG DARI SSP
    // =================================================================
    if ($event_type === 'win') {
        $campaign_id = isset($_GET['cid']) ? (int)$_GET['cid'] : null;
        $publisher_id = isset($_GET['pid']) ? (int)$_GET['pid'] : null;
        $site_id = isset($_GET['sid']) ? (int)$_GET['sid'] : null;
        $zone_id = isset($_GET['zid']) ? (int)$_GET['zid'] : null;
        // Harga kemenangan lelang dari makro ${AUCTION_PRICE}, ini adalah CPM sebenarnya.
        $win_cpm_price = isset($_GET['price']) ? (float)$_GET['price'] : 0.0;

        // Hanya proses jika ada publisher dan harga kemenangan yang valid.
        if ($campaign_id && $publisher_id && $win_cpm_price > 0) {
            
            // Dapatkan revenue_share publisher
            $stmt_pub = $pdo->prepare("SELECT revenue_share FROM publishers WHERE id = ?");
            $stmt_pub->execute([$publisher_id]);
            $revenue_share_percentage = $stmt_pub->fetchColumn();

            if ($revenue_share_percentage !== false) {
                // Biaya untuk satu impresi dihitung dari harga kemenangan lelang (CPM).
                $cost_per_impression = $win_cpm_price / 1000.0;
                
                // Hitung bagi hasil
                $publisher_revenue = $cost_per_impression * ((float)$revenue_share_percentage / 100.0);
                $platform_revenue = $cost_per_impression - $publisher_revenue;

                // Dapatkan advertiser_id untuk kelengkapan data
                $stmt_adv = $pdo->prepare("SELECT advertiser_id FROM campaigns WHERE id = ?");
                $stmt_adv->execute([$campaign_id]);
                $advertiser_id = $stmt_adv->fetchColumn();

                // 'win' dianggap sebagai 'impression' yang dapat ditagih.
                $sql = "INSERT INTO ad_stats (event_type, campaign_id, zone_id, site_id, publisher_id, advertiser_id, cpm_price, publisher_revenue, platform_revenue, impression_id, is_processed) 
                        VALUES ('impression', :cid, :zid, :sid, :pid, :adv_id, :cpm, :pub_rev, :plat_rev, :impid, 0)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':cid' => $campaign_id,
                    ':zid' => $zone_id,
                    ':sid' => $site_id,
                    ':pid' => $publisher_id,
                    ':adv_id' => $advertiser_id,
                    ':cpm' => $win_cpm_price, // Catat harga kemenangan sebagai cpm_price
                    ':pub_rev' => $publisher_revenue,
                    ':plat_rev' => $platform_revenue,
                    ':impid' => $_GET['impid'] ?? uniqid('ssp_win_')
                ]);
            }
        }
        // Setelah memproses 'win', selalu keluar.
        send_pixel_response();
    }

    // =================================================================
    // BAGIAN 2: LOGIKA UNTUK VAST TRACKER BIASA (DARI vast.php)
    // =================================================================
    $valid_vast_events = ['impression', 'click', 'start', 'midpoint', 'complete'];
    $campaign_id = isset($_GET['cid']) ? (int)$_GET['cid'] : null;

    // Jika campaign_id tidak ada atau event tidak valid, hentikan.
    if (empty($campaign_id) || !in_array($event_type, $valid_vast_events)) {
        send_pixel_response();
    }

    // Kumpulkan semua parameter dari URL
    $zone_id = isset($_GET['zid']) ? (int)$_GET['zid'] : null;
    $site_id = isset($_GET['sid']) ? (int)$_GET['sid'] : null;
    $cpm_price = isset($_GET['price']) ? (float)$_GET['price'] : 0.0;
    
    $publisher_id = null;
    $publisher_revenue = null;
    $platform_revenue = null;
    
    // Perhitungan pendapatan hanya terjadi pada event 'impression'
    if ($event_type === 'impression' && $site_id !== null) {
        $stmt_pub = $pdo->prepare("SELECT s.publisher_id, p.revenue_share FROM sites s JOIN publishers p ON s.publisher_id = p.id WHERE s.id = ?");
        $stmt_pub->execute([$site_id]);
        $publisher_info = $stmt_pub->fetch(PDO::FETCH_ASSOC);

        if ($publisher_info) {
            $publisher_id = $publisher_info['publisher_id'];
            $revenue_share_percentage = (float)$publisher_info['revenue_share'];
            
            // Biaya untuk satu impresi dihitung dari CPM statis kampanye.
            $cost_per_impression = $cpm_price / 1000.0;
            $publisher_revenue = $cost_per_impression * ($revenue_share_percentage / 100.0);
            $platform_revenue = $cost_per_impression - $publisher_revenue;
        }
    } elseif ($site_id) {
        // Untuk event lain (click, dll), cukup dapatkan publisher_id tanpa menghitung revenue.
        $stmt_pub = $pdo->prepare("SELECT publisher_id FROM sites WHERE id = ?");
        $stmt_pub->execute([$site_id]);
        $publisher_id = $stmt_pub->fetchColumn();
    }
    
    // Dapatkan advertiser_id untuk kelengkapan data
    $stmt_adv = $pdo->prepare("SELECT advertiser_id FROM campaigns WHERE id = ?");
    $stmt_adv->execute([$campaign_id]);
    $advertiser_id = $stmt_adv->fetchColumn();
    
    // Query INSERT utama
    $sql = "INSERT INTO ad_stats (event_type, campaign_id, zone_id, site_id, publisher_id, advertiser_id, country_code, cpm_price, publisher_revenue, platform_revenue, impression_id, user_id_hash, os, device_type, browser, is_processed) 
            VALUES (:event_type, :campaign_id, :zone_id, :site_id, :publisher_id, :advertiser_id, :country_code, :cpm_price, :publisher_revenue, :platform_revenue, :impression_id, :user_id_hash, :os, :device_type, :browser, 0)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':event_type' => $event_type,
        ':campaign_id' => $campaign_id,
        ':zone_id' => $zone_id,
        ':site_id' => $site_id,
        ':publisher_id' => $publisher_id,
        ':advertiser_id' => $advertiser_id,
        ':country_code' => country_code_alpha2_to_alpha3($_GET['cc'] ?? null),
        ':cpm_price' => ($event_type === 'impression') ? $cpm_price : null,
        ':publisher_revenue' => $publisher_revenue,
        ':platform_revenue' => $platform_revenue,
        ':impression_id' => $_GET['impid'] ?? null,
        ':user_id_hash' => $_GET['uid'] ?? null,
        ':os' => $_GET['os'] ?? null,
        ':device_type' => $_GET['dev'] ?? null,
        ':browser' => $_GET['brw'] ?? null
    ]);

} catch (PDOException $e) {
    // Jika terjadi error database, catat ke file log untuk debugging.
    $log_directory = __DIR__ . '/../logs';
    if (!is_dir($log_directory)) { @mkdir($log_directory, 0755, true); }
    $db_error_message = sprintf("[%s] DB ERROR in track.php: %s\nQuery Parameters: %s\n", date('Y-m-d H:i:s'), $e->getMessage(), http_build_query($_GET));
    @file_put_contents($log_directory . '/tracking_errors.log', $db_error_message, FILE_APPEND);
}

// Selalu kirim respons piksel di akhir.
send_pixel_response();
?>