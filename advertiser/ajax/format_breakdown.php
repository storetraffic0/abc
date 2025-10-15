<?php
// Pastikan tidak ada output sebelum header dan JSON
error_reporting(0); // Nonaktifkan pelaporan error untuk mencegah warning/notice yang tidak sengaja
ob_start(); // Mulai output buffering

require_once '../../includes/db_connection.php';

// Dapatkan parameter
$start_date = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end'] ?? date('Y-m-d');
$advertiser_user_id = $_SESSION['user_id'] ?? 0;

try {
    $pdo = get_db_connection();
    
    // Dapatkan advertiser_id dari user_id
    $stmt_adv_id = $pdo->prepare("SELECT id FROM advertisers WHERE user_id = ?");
    $stmt_adv_id->execute([$advertiser_user_id]);
    $advertiser_id = $stmt_adv_id->fetchColumn();
    
    if (!$advertiser_id) {
        // Default ke data kosong jika advertiser ID tidak valid
        echo json_encode(['labels' => ['Vast', 'Popunder', 'Banner'], 'data' => [0, 0, 0]]);
        exit;
    }
    
    // Query untuk mendapatkan breakdown berdasarkan format iklan
    $sql = "SELECT 
                IFNULL(ad_format, 'unknown') as ad_format,
                SUM(impressions) as impressions
            FROM 
                advertiser_daily_stats
            WHERE 
                advertiser_id = :advertiser_id AND 
                report_date BETWEEN :start_date AND :end_date
            GROUP BY 
                ad_format
            ORDER BY 
                impressions DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':advertiser_id', $advertiser_id);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->execute();
    
    $format_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data untuk chart.js
    $labels = [];
    $data = [];
    $found_formats = [];
    
    foreach ($format_data as $row) {
        if (empty($row['ad_format'])) continue;
        
        $format = strtolower($row['ad_format']);
        $found_formats[] = $format;
        $labels[] = ucfirst($format);
        $data[] = (int)$row['impressions'];
    }
    
    // Pastikan semua format standar ada
    $all_formats = ['vast', 'popunder', 'banner'];
    foreach ($all_formats as $format) {
        if (!in_array($format, $found_formats)) {
            $labels[] = ucfirst($format);
            $data[] = 0;
        }
    }
    
    // Bersihkan buffer output untuk memastikan tidak ada output sebelumnya
    ob_end_clean();
    
    // Set header dan output JSON
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data]);
    
} catch (Exception $e) {
    // Bersihkan buffer output
    ob_end_clean();
    
    // Kirim JSON kosong yang valid
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'labels' => ['Vast', 'Popunder', 'Banner'], 'data' => [0, 0, 0]]);
}
?>