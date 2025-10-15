<?php
// /update_campaign_cache.php - VERSI FINAL DENGAN PERBAIKAN KONEKSI DB

require_once __DIR__ . '/includes/db_connection.php'; // Kita tetap butuh ini di sini

function update_campaigns_in_redis() {
    // === PERBAIKAN KRUSIAL: BUAT KONEKSI BARU DI SETIAP SIKLUS ===
    $pdo = get_db_connection();
    // ==========================================================

    $redis = new Redis();
    try {
        $redis->connect('127.0.0.1', 6379);
    } catch (RedisException $e) {
        error_log("Worker failed to connect to Redis: " . $e->getMessage());
        return;
    }

    $sql = "SELECT c.id, c.advertiser_id, c.campaign_type, c.priority, c.cpm_rate, 
                   cd.third_party_vast_url, cd.rtb_endpoint_url,
                   ct.countries
            FROM campaigns c 
            JOIN campaign_details cd ON c.id = cd.campaign_id 
            JOIN campaign_targeting ct ON c.id = ct.campaign_id
            WHERE c.status = 'active'
              AND c.ad_format = 'vast'
              AND (c.start_date IS NULL OR c.start_date <= CURDATE()) 
              AND (c.end_date IS NULL OR c.end_date >= CURDATE())";
    
    $stmt = $pdo->query($sql);
    $all_campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $campaigns_by_country = [];

    foreach ($all_campaigns as $campaign) {
        $countries = json_decode($campaign['countries'], true);
        if (empty($countries)) {
            $campaigns_by_country['ALL'][] = $campaign;
        } else {
            foreach ($countries as $country_code) {
                $campaigns_by_country[strtoupper($country_code)][] = $campaign;
            }
        }
    }
    
    $keys_to_delete = $redis->keys('campaigns:*');
    if (!empty($keys_to_delete)) {
        $redis->del($keys_to_delete);
    }
    
    $pipe = $redis->multi(Redis::PIPELINE);
    foreach ($campaigns_by_country as $country => $campaigns) {
        $pipe->set('campaigns:' . $country, json_encode($campaigns));
    }
    $pipe->exec();
    
    $log_message = "Campaign cache updated for " . count($campaigns_by_country) . " country groups.";
    echo $log_message . PHP_EOL;

    // Tutup koneksi secara eksplisit di akhir (praktik yang baik)
    $pdo = null;
}

// Loop tak terbatas
while (true) {
    try {
        update_campaigns_in_redis();
    } catch (Exception $e) {
        // Catat error ke log php-fpm
        error_log("CRITICAL ERROR in campaign cache worker: " . $e->getMessage());
    }
    sleep(60); // Tunggu 60 detik
}
?>