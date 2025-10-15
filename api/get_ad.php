<?php
// /api/get_ad.php - Endpoint untuk JavaScript Ad Tag (Outstream/Slider)

// Header untuk mengizinkan permintaan dari domain lain (CORS) dan menentukan tipe konten
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Muat semua file dependensi yang diperlukan
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/ad_logic.php';
require_once __DIR__ . '/../includes/functions.php';

// Ambil zone_id dari parameter GET dan lakukan validasi dasar
$zone_id = (int)($_GET['zone_id'] ?? 0);
if ($zone_id === 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid Zone ID']);
    exit;
}

try {
    $pdo = get_db_connection();

    // 1. Dapatkan informasi Zone, Situs, dan Publisher dari database
    $stmt = $pdo->prepare("
        SELECT 
            z.format, s.id as site_id, s.domain, s.category, p.id as publisher_id
        FROM zones z
        JOIN sites s ON z.site_id = s.id
        JOIN publishers p ON s.publisher_id = p.id
        WHERE z.id = ? AND z.status = 'active' AND s.status = 'active'
    ");
    $stmt->execute([$zone_id]);
    $zone_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pastikan Zone ada dan formatnya adalah outstream atau slider
    if (!$zone_info || !in_array($zone_info['format'], ['outstream', 'slider'])) {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Zone not found or not a valid video format for this endpoint.']);
        exit;
    }

    // 2. Buat "Dummy" RTB Request untuk disimulasikan ke fungsi find_eligible_campaign
    // Ini memungkinkan kita menggunakan kembali logika pemilihan kampanye yang sudah ada.
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $dummy_rtb_request = [
        'id' => 'js_tag_' . uniqid(),
        'imp' => [['id' => '1']],
        'site' => [
            'id' => (string)$zone_info['site_id'],
            'domain' => $zone_info['domain'],
            'cat' => [$zone_info['category']],
            'publisher' => ['id' => $zone_info['publisher_id']],
            'ext' => ['idzone' => $zone_id]
        ],
        'device' => [
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $user_ip,
            'geo' => ['country' => get_country_from_ip_local($user_ip)]
        ],
        'user' => ['id' => '']
    ];

    // 3. Panggil fungsi inti untuk mencari kampanye VAST yang cocok
    $result = find_eligible_campaign($dummy_rtb_request);

    // 4. Proses hasilnya. Jika tidak ada iklan, kirim respons "No Content".
    if (empty($result) || $result['type'] !== 'json' || empty($result['content']['adm'])) {
        http_response_code(204); // No Content
        exit;
    }

    // 5. Simpan VAST XML ke file cache untuk efisiensi.
    // Ini mencegah VAST XML yang besar dikirim melalui JSON.
    $vast_content = $result['content']['adm'];
    $campaign_id = $result['content']['cid'];
    $vast_filename = "vast_{$campaign_id}_" . md5($vast_content . uniqid()) . ".xml"; // Tambah uniqid untuk mencegah race condition
    $cache_dir = __DIR__ . '/../cache/vast/';
    
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    file_put_contents($cache_dir . $vast_filename, $vast_content);

    // Ambil domain dari pengaturan aplikasi
    $APP_SETTINGS = load_app_settings();
    $AD_TAG_DOMAIN = $APP_SETTINGS['ad_tag_domain'] ?? ((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);
    
    // PERBAIKAN FINAL: Buat URL yang menunjuk ke skrip proxy serve_vast.php untuk mengatasi masalah CORS
    $vast_url = rtrim($AD_TAG_DOMAIN, '/') . '/api/serve_vast.php?file=' . urlencode($vast_filename);

    // 6. Kembalikan respons JSON yang berisi format dan URL VAST ke player.js
    echo json_encode([
        'format' => $zone_info['format'], // 'outstream' atau 'slider'
        'vastUrl' => $vast_url           // URL ini sekarang menunjuk ke proxy PHP kita
    ]);

} catch (Exception $e) {
    // Tangani error tak terduga dan catat ke log server
    http_response_code(500); // Internal Server Error
    error_log("get_ad.php Error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal Server Error']);
}
?>