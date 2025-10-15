<?php
// process_tracker_queue.php - WORKER UNTUK MEMPROSES ANTRIAN TRACKING DARI REDIS

// Set agar skrip berjalan tanpa batas waktu dari command line.
set_time_limit(0);

// Sertakan file-file yang diperlukan.
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';

echo "Memulai worker untuk antrian tracking...\n";

$pdo = get_db_connection();
$redis = get_redis_connection();

if (!$pdo || !$redis) {
    die("Tidak dapat terhubung ke DB atau Redis. Keluar.\n");
}

// Jumlah data yang akan diproses dalam satu kali operasi database (batch).
$batch_size = 500;

// Loop tak terbatas untuk terus memproses antrian.
while (true) {
    try {
        // Ambil data dari Redis. BLPOP akan menunggu hingga 60 detik jika antrian kosong.
        // Ini jauh lebih efisien daripada melakukan loop terus-menerus dan membebani CPU.
        $item = $redis->blpop('tracking_queue', 60);

        if (!$item || !isset($item[1])) {
            // Jika timeout (60 detik) dan tidak ada data, ulangi loop.
            continue;
        }

        // Kumpulkan data dari antrian hingga mencapai ukuran batch atau antrian kosong.
        $items_to_process = [$item[1]];
        while (count($items_to_process) < $batch_size) {
            $next_item = $redis->lpop('tracking_queue');
            if ($next_item) {
                $items_to_process[] = $next_item;
            } else {
                break; // Antrian kosong, proses apa yang sudah ada.
            }
        }

        $pdo->beginTransaction();

        // Siapkan query INSERT utama.
        $sql = "INSERT INTO ad_stats (event_type, campaign_id, zone_id, site_id, publisher_id, advertiser_id, cpm_price, publisher_revenue, platform_revenue, impression_id, is_processed, country_code, os, device_type, browser, user_id_hash) 
                VALUES (:event_type, :cid, :zid, :sid, :pid, :adv_id, :cpm, :pub_rev, :plat_rev, :impid, 0, :cc, :os, :dev, :brw, :uid)";
        $stmt_insert = $pdo->prepare($sql);

        // Siapkan query-query lookup yang akan digunakan berulang kali.
        $stmt_pub_share = $pdo->prepare("SELECT revenue_share FROM publishers WHERE id = ?");
        $stmt_adv_id = $pdo->prepare("SELECT advertiser_id FROM campaigns WHERE id = ?");
        $stmt_site_pub_info = $pdo->prepare("SELECT s.publisher_id, p.revenue_share FROM sites s JOIN publishers p ON s.publisher_id = p.id WHERE s.id = ?");
        $stmt_site_pub_id = $pdo->prepare("SELECT publisher_id FROM sites WHERE id = ?");

        $processed_count = 0;
        foreach ($items_to_process as $json_data) {
            $data = json_decode($json_data, true);
            if (!$data || empty($data['event'])) continue;

            $event_type = $data['event'];
            $campaign_id = isset($data['cid']) ? (int)$data['cid'] : null;
            
            // Inisialisasi variabel
            $publisher_id = null; $advertiser_id = null;
            $publisher_revenue = null; $platform_revenue = null;
            $cpm_price = null;

            // Logika untuk event 'win' dari SSP
            if ($event_type === 'win' && $campaign_id) {
                $event_type = 'impression'; // 'win' dicatat sebagai 'impression'
                $publisher_id = isset($data['pid']) ? (int)$data['pid'] : null;
                $win_cpm_price = isset($data['price']) ? (float)$data['price'] : 0.0;
                
                if ($publisher_id && $win_cpm_price > 0) {
                    $stmt_pub_share->execute([$publisher_id]);
                    $revenue_share_percentage = $stmt_pub_share->fetchColumn();
                    if ($revenue_share_percentage !== false) {
                        $cost_per_impression = $win_cpm_price / 1000.0;
                        $publisher_revenue = $cost_per_impression * ((float)$revenue_share_percentage / 100.0);
                        $platform_revenue = $cost_per_impression - $publisher_revenue;
                        $cpm_price = $win_cpm_price;
                    }
                }
            } 
            // Logika untuk event VAST biasa
            elseif ($campaign_id) {
                $site_id = isset($data['sid']) ? (int)$data['sid'] : null;
                $static_cpm_price = isset($data['price']) ? (float)$data['price'] : 0.0;

                if ($event_type === 'impression' && $site_id && $static_cpm_price > 0) {
                    $stmt_site_pub_info->execute([$site_id]);
                    $publisher_info = $stmt_site_pub_info->fetch(PDO::FETCH_ASSOC);
                    if ($publisher_info) {
                        $publisher_id = $publisher_info['publisher_id'];
                        $cost_per_impression = $static_cpm_price / 1000.0;
                        $publisher_revenue = $cost_per_impression * ((float)$publisher_info['revenue_share'] / 100.0);
                        $platform_revenue = $cost_per_impression - $publisher_revenue;
                        $cpm_price = $static_cpm_price;
                    }
                } elseif ($site_id) {
                    $stmt_site_pub_id->execute([$site_id]);
                    $publisher_id = $stmt_site_pub_id->fetchColumn();
                }
            }

            // Dapatkan advertiser_id jika ada campaign_id
            if ($campaign_id) {
                $stmt_adv_id->execute([$campaign_id]);
                $advertiser_id = $stmt_adv_id->fetchColumn();
            }

            // Jalankan INSERT
            $stmt_insert->execute([
                ':event_type' => $event_type,
                ':cid'        => $campaign_id,
                ':zid'        => isset($data['zid']) ? (int)$data['zid'] : null,
                ':sid'        => isset($data['sid']) ? (int)$data['sid'] : null,
                ':pid'        => $publisher_id,
                ':adv_id'     => $advertiser_id,
                ':cpm'        => $cpm_price,
                ':pub_rev'    => $publisher_revenue,
                ':plat_rev'   => $platform_revenue,
                ':impid'      => $data['impid'] ?? null,
                ':cc'         => country_code_alpha2_to_alpha3($data['cc'] ?? null),
                ':os'         => $data['os'] ?? null,
                ':dev'        => $data['dev'] ?? null,
                ':brw'        => $data['brw'] ?? null,
                ':uid'        => $data['uid'] ?? null
            ]);
            $processed_count++;
        }
        
        $pdo->commit();
        echo sprintf("[%s] Memproses batch %d item. (%d data valid dimasukkan)\n", date('Y-m-d H:i:s'), count($items_to_process), $processed_count);

    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = sprintf("[%s] Worker ERROR: %s\n", date('Y-m-d H:i:s'), $e->getMessage());
        echo $error_message;
        @file_put_contents(__DIR__ . '/logs/worker_errors.log', $error_message, FILE_APPEND);
        sleep(5);
    }
}