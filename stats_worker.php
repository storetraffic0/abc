<?php
// /stats_worker.php - Memproses data statistik dari antrian Redis ke MariaDB

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';

// Atur agar skrip tidak timeout
set_time_limit(0);

echo "Starting Statistics Worker...\n";

$redis = new Redis();
try {
    $redis->connect('127.0.0.1', 6379);
} catch (RedisException $e) {
    echo "Worker failed to connect to Redis: " . $e->getMessage() . "\n";
    exit(1);
}

while (true) {
    try {
        // Ambil data dari Redis secara berkelompok (hingga 500 event per siklus)
        $batch = [];
        for ($i = 0; $i < 500; $i++) {
            // BRPOP adalah 'blocking pop', akan menunggu hingga 5 detik jika antrian kosong
            $event_json = $redis->brpop('tracking_events_queue', 5);
            if ($event_json && isset($event_json[1])) {
                $batch[] = json_decode($event_json[1], true);
            } else {
                // Jika antrian kosong setelah menunggu, keluar dari loop for
                break;
            }
        }

        if (empty($batch)) {
            // Tidak ada pekerjaan, tunggu sebentar sebelum memeriksa lagi
            // sleep(1);
            continue;
        }

        echo "Processing a batch of " . count($batch) . " events.\n";
        
        // Buat koneksi database yang baru dan segar di setiap siklus
        $pdo = get_db_connection();
        $pdo->beginTransaction();

        $sql = "INSERT INTO ad_stats (event_type, campaign_id, zone_id, site_id, publisher_id, advertiser_id, country_code, cpm_price, publisher_revenue, platform_revenue, impression_id, user_id_hash, os, device_type, browser, event_time, is_processed) 
                VALUES (:event_type, :campaign_id, :zone_id, :site_id, :publisher_id, :advertiser_id, :country_code, :cpm_price, :publisher_revenue, :platform_revenue, :impression_id, :user_id_hash, :os, :device_type, :browser, :event_time, 0)";
        $stmt = $pdo->prepare($sql);

        foreach ($batch as $data) {
            // Logika pemrosesan data, disadur dari track.php lama
            $event_type = $data['event'] ?? null;
            if (!$event_type) continue;
            
            $campaign_id = isset($data['cid']) ? (int)$data['cid'] : null;
            if (!$campaign_id) continue;
            
            // Variabel default
            $publisher_id = null; $advertiser_id = null; $publisher_revenue = null; $platform_revenue = null; $cpm_price = 0.0;
            $zone_id = isset($data['zid']) ? (int)$data['zid'] : null;
            $site_id = isset($data['sid']) ? (int)$data['sid'] : null;

            // Dapatkan advertiser_id
            $stmt_adv = $pdo->prepare("SELECT advertiser_id FROM campaigns WHERE id = ?");
            $stmt_adv->execute([$campaign_id]);
            $advertiser_id = $stmt_adv->fetchColumn();

            // Logika perhitungan revenue
            if ($event_type === 'impression' || $event_type === 'win') {
                $price_from_get = isset($data['price']) ? (float)$data['price'] : 0.0;
                $publisher_id_from_get = isset($data['pid']) ? (int)$data['pid'] : null;

                $target_publisher_id = $publisher_id_from_get;
                if (!$target_publisher_id && $site_id) {
                     $stmt_pub_id = $pdo->prepare("SELECT publisher_id FROM sites WHERE id = ?");
                     $stmt_pub_id->execute([$site_id]);
                     $target_publisher_id = $stmt_pub_id->fetchColumn();
                }

                if ($target_publisher_id && $price_from_get > 0) {
                     $stmt_pub = $pdo->prepare("SELECT revenue_share FROM publishers WHERE id = ?");
                     $stmt_pub->execute([$target_publisher_id]);
                     $revenue_share = (float)$stmt_pub->fetchColumn();

                     $cpm_price = $price_from_get;
                     $cost_per_impression = $cpm_price / 1000.0;
                     $publisher_revenue = $cost_per_impression * ($revenue_share / 100.0);
                     $platform_revenue = $cost_per_impression - $publisher_revenue;
                     $publisher_id = $target_publisher_id;
                }
            }

            $stmt->execute([
                ':event_type' => ($event_type === 'win') ? 'impression' : $event_type,
                ':campaign_id' => $campaign_id,
                ':zone_id' => $zone_id,
                ':site_id' => $site_id,
                ':publisher_id' => $publisher_id,
                ':advertiser_id' => $advertiser_id,
                ':country_code' => country_code_alpha2_to_alpha3($data['cc'] ?? null),
                ':cpm_price' => ($event_type === 'impression' || $event_type === 'win') ? $cpm_price : null,
                ':publisher_revenue' => $publisher_revenue,
                ':platform_revenue' => $platform_revenue,
                ':impression_id' => $data['impid'] ?? null,
                ':user_id_hash' => $data['uid'] ?? null,
                ':os' => $data['os'] ?? null,
                ':device_type' => $data['dev'] ?? null,
                ':browser' => $data['brw'] ?? null,
                ':event_time' => $data['timestamp']
            ]);
        }
        
        $pdo->commit();
        $pdo = null; // Tutup koneksi
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("CRITICAL ERROR in stats_worker.php: " . $e->getMessage());
        sleep(5); // Tunggu 5 detik jika ada error sebelum mencoba lagi
    }
}
?>