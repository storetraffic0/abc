<?php
// /summarize_stats.php - COMPREHENSIVE & ROBUST VERSION

set_time_limit(300); // 5 minutes

require_once __DIR__ . '/includes/db_connection.php';

echo "==================================================\n";
echo "Starting Statistics Summarization Process at " . date('Y-m-d H:i:s') . "\n";
echo "==================================================\n";

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // --- Bagian 1: Agregasi untuk Laporan Publisher dari ad_stats (VAST) ---
    echo "Processing publisher stats from ad_stats...\n";
    $sql_publisher = "
        INSERT INTO publisher_daily_stats (report_date, publisher_id, site_id, zone_id, campaign_id, country_code, ad_format, impressions, clicks, total_revenue)
        SELECT
            DATE(event_time) as report_date, publisher_id, site_id, zone_id, campaign_id, country_code, 'vast' as ad_format,
            SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
            SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
            SUM(IFNULL(publisher_revenue, 0)) as total_revenue
        FROM ad_stats
        WHERE
            is_processed = 0 AND publisher_id IS NOT NULL AND event_type IN ('impression', 'click')
            AND country_code IS NOT NULL -- PERBAIKAN: Abaikan baris dengan country_code NULL
        GROUP BY report_date, publisher_id, site_id, zone_id, campaign_id, country_code
        ON DUPLICATE KEY UPDATE
            impressions = impressions + VALUES(impressions), clicks = clicks + VALUES(clicks), total_revenue = total_revenue + VALUES(total_revenue)";
    $stmt_pub = $pdo->prepare($sql_publisher);
    $stmt_pub->execute();
    echo "-> Publisher stats processed from ad_stats: " . $stmt_pub->rowCount() . " summary rows affected.\n";

    // --- Bagian 2: Agregasi untuk Laporan Advertiser dari ad_stats (VAST) ---
    echo "Processing advertiser stats from ad_stats...\n";
    $sql_advertiser = "
        INSERT INTO advertiser_daily_stats (report_date, advertiser_id, campaign_id, country_code, site_id, device_type, os, browser, ad_format, impressions, clicks, total_spend)
        SELECT
            DATE(event_time) as report_date, advertiser_id, campaign_id, country_code, site_id, device_type, os, browser, 'vast' as ad_format,
            SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
            SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
            SUM(IFNULL(publisher_revenue, 0) + IFNULL(platform_revenue, 0)) as total_spend
        FROM ad_stats
        WHERE
            is_processed = 0 AND advertiser_id IS NOT NULL AND event_type IN ('impression', 'click')
            AND country_code IS NOT NULL -- PERBAIKAN: Abaikan baris dengan country_code NULL
        GROUP BY report_date, advertiser_id, campaign_id, country_code, site_id, device_type, os, browser
        ON DUPLICATE KEY UPDATE
            impressions = impressions + VALUES(impressions), clicks = clicks + VALUES(clicks), total_spend = total_spend + VALUES(total_spend)";
    $stmt_adv = $pdo->prepare($sql_advertiser);
    $stmt_adv->execute();
    echo "-> Advertiser stats processed from ad_stats: " . $stmt_adv->rowCount() . " summary rows affected.\n";
    
    // --- Bagian 3: Agregasi stats Popunder ---
    echo "Processing popunder stats...\n";
    $sql_popunder_adv = "
        INSERT INTO advertiser_daily_stats (report_date, advertiser_id, campaign_id, country_code, site_id, device_type, os, browser, ad_format, impressions, clicks, total_spend)
        SELECT
            DATE(created_at) as report_date, advertiser_id, campaign_id, country, IFNULL(site_id, 0), IFNULL(device_type, 'Unknown'), IFNULL(os, 'Unknown'), IFNULL(browser, 'Unknown'), 'popunder' as ad_format,
            SUM(CASE WHEN event_type = 'impression' OR event_type = 'win' THEN 1 ELSE 0 END) as impressions,
            SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
            SUM(IFNULL(publisher_revenue, 0) + IFNULL(platform_revenue, 0)) as total_spend
        FROM popunder_stats
        WHERE
            is_processed = 0 AND advertiser_id IS NOT NULL
            AND country IS NOT NULL -- PERBAIKAN: Abaikan baris dengan country NULL
        GROUP BY report_date, advertiser_id, campaign_id, country, site_id, device_type, os, browser
        ON DUPLICATE KEY UPDATE
            impressions = impressions + VALUES(impressions), clicks = clicks + VALUES(clicks), total_spend = total_spend + VALUES(total_spend)";
    $stmt_pop_adv = $pdo->prepare($sql_popunder_adv);
    $stmt_pop_adv->execute();
    echo "-> Popunder advertiser stats processed: " . $stmt_pop_adv->rowCount() . " summary rows affected.\n";
    
    $sql_popunder_pub = "
        INSERT INTO publisher_daily_stats (report_date, publisher_id, site_id, zone_id, campaign_id, country_code, ad_format, impressions, clicks, total_revenue)
        SELECT
            DATE(created_at) as report_date, publisher_id, IFNULL(site_id, 0), IFNULL(zone_id, 0), campaign_id, country, 'popunder' as ad_format,
            SUM(CASE WHEN event_type = 'impression' OR event_type = 'win' THEN 1 ELSE 0 END) as impressions,
            SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
            SUM(IFNULL(publisher_revenue, 0)) as total_revenue
        FROM popunder_stats
        WHERE
            is_processed = 0 AND publisher_id IS NOT NULL
            AND country IS NOT NULL -- PERBAIKAN: Abaikan baris dengan country NULL
        GROUP BY report_date, publisher_id, site_id, zone_id, campaign_id, country
        ON DUPLICATE KEY UPDATE
            impressions = impressions + VALUES(impressions), clicks = clicks + VALUES(clicks), total_revenue = total_revenue + VALUES(total_revenue)";
    $stmt_pop_pub = $pdo->prepare($sql_popunder_pub);
    $stmt_pop_pub->execute();
    echo "-> Popunder publisher stats processed: " . $stmt_pop_pub->rowCount() . " summary rows affected.\n";
    
    // --- BAGIAN 4: Agregasi stats Banner ---
    echo "Processing banner stats...\n";
    $sql_banner_adv = "
        INSERT INTO advertiser_daily_stats (report_date, advertiser_id, campaign_id, country_code, site_id, device_type, os, browser, ad_format, impressions, clicks, total_spend)
        SELECT
            DATE(created_at) as report_date, advertiser_id, campaign_id, country, IFNULL(site_id, 0), IFNULL(device_type, 'Unknown'), IFNULL(os, 'Unknown'), IFNULL(browser, 'Unknown'), 'banner' as ad_format,
            SUM(CASE WHEN event_type = 'impression' OR event_type = 'win' THEN 1 ELSE 0 END) as impressions,
            SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
            SUM(IFNULL(publisher_revenue, 0) + IFNULL(platform_revenue, 0)) as total_spend
        FROM banner_stats
        WHERE
            is_processed = 0 AND advertiser_id IS NOT NULL
            AND country IS NOT NULL -- PERBAIKAN: Abaikan baris dengan country NULL
        GROUP BY report_date, advertiser_id, campaign_id, country, site_id, device_type, os, browser
        ON DUPLICATE KEY UPDATE
            impressions = impressions + VALUES(impressions), clicks = clicks + VALUES(clicks), total_spend = total_spend + VALUES(total_spend)";
    $stmt_banner_adv = $pdo->prepare($sql_banner_adv);
    $stmt_banner_adv->execute();
    echo "-> Banner advertiser stats processed: " . $stmt_banner_adv->rowCount() . " summary rows affected.\n";
    
    $sql_banner_pub = "
        INSERT INTO publisher_daily_stats (report_date, publisher_id, site_id, zone_id, campaign_id, country_code, ad_format, impressions, clicks, total_revenue)
        SELECT
            DATE(created_at) as report_date, publisher_id, IFNULL(site_id, 0), IFNULL(zone_id, 0), campaign_id, country, 'banner' as ad_format,
            SUM(CASE WHEN event_type = 'impression' OR event_type = 'win' THEN 1 ELSE 0 END) as impressions,
            SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
            SUM(IFNULL(publisher_revenue, 0)) as total_revenue
        FROM banner_stats
        WHERE
            is_processed = 0 AND publisher_id IS NOT NULL
            AND country IS NOT NULL -- PERBAIKAN: Abaikan baris dengan country NULL
        GROUP BY report_date, publisher_id, site_id, zone_id, campaign_id, country
        ON DUPLICATE KEY UPDATE
            impressions = impressions + VALUES(impressions), clicks = clicks + VALUES(clicks), total_revenue = total_revenue + VALUES(total_revenue)";
    $stmt_banner_pub = $pdo->prepare($sql_banner_pub);
    $stmt_banner_pub->execute();
    echo "-> Banner publisher stats processed: " . $stmt_banner_pub->rowCount() . " summary rows affected.\n";

    // --- Bagian 5: Tandai semua baris yang sudah diproses ---
    echo "Marking raw stats as processed...\n";
    $pdo->exec("UPDATE ad_stats SET is_processed = 1 WHERE is_processed = 0");
    $pdo->exec("UPDATE popunder_stats SET is_processed = 1 WHERE is_processed = 0");
    $pdo->exec("UPDATE banner_stats SET is_processed = 1 WHERE is_processed = 0");
    echo "-> All raw stats marked as processed.\n";

    $pdo->commit();

    echo "==================================================\n";
    echo "Summary process finished successfully at " . date('Y-m-d H:i:s') . "\n";
    echo "==================================================\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error_message = "FATAL ERROR: Failed to summarize stats. Transaction rolled back. Reason: " . $e->getMessage() . "\n";
    echo $error_message;
    error_log($error_message);
    exit(1);
}
?>