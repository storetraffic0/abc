<?php
// /debug_vast.php

// Tampilkan semua error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';

echo "<pre>"; // Gunakan tag <pre> agar output mudah dibaca

// 1. Ambil informasi dasar dari request
$zone_id = 445566; // Hardcode Zone ID untuk pengetesan
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'User Agent Tidak Dikenali';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$referer = $_SERVER['HTTP_REFERER'] ?? 'https://sitedomain.com/test-page'; // Simulasikan referer

echo "========================================\n";
echo "1. DATA REQUEST MENTAH\n";
echo "========================================\n";
echo "Zone ID: " . htmlspecialchars($zone_id) . "\n";
echo "User Agent: " . htmlspecialchars($user_agent) . "\n";
echo "IP Address: " . htmlspecialchars($ip_address) . "\n\n";


// 2. Ambil informasi dari database berdasarkan Zone ID
$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT s.id as site_id, s.domain, s.name, s.category FROM zones z JOIN sites s ON z.site_id = s.id WHERE z.id = ?");
$stmt->execute([$zone_id]);
$site_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site_info) {
    die("KRITIS: Zone ID " . $zone_id . " tidak ditemukan di database.");
}

echo "========================================\n";
echo "2. INFORMASI DARI DATABASE (BERDASARKAN ZONE ID)\n";
echo "========================================\n";
print_r($site_info);
echo "\n";


// 3. Bangun data OpenRTB (seperti yang dilakukan vast.php)
$openrtb_request = [
    'id' => uniqid(),
    'imp' => [['id' => '1', 'video' => ['mimes' => ['video/mp4'], 'protocols' => [2, 3, 5, 6]]]],
    'site' => [
        'id' => (string)$site_info['site_id'], 'domain' => $site_info['domain'],
        'cat' => [$site_info['category'] ?? 'IAB24'], 'page' => $referer,
        'ext' => ['idzone' => $zone_id]
    ],
    'device' => ['ua' => $user_agent, 'ip' => $ip_address, 'geo' => ['country' => 'ID']], // Asumsikan negara ID untuk tes
    'user' => ['id' => 'user_' . md5($ip_address . $user_agent)]
];

echo "========================================\n";
echo "3. SIMULASI REQUEST OPENRTB YANG DIKIRIM KE AD.PHP\n";
echo "========================================\n";
echo json_encode($openrtb_request, JSON_PRETTY_PRINT);
echo "\n\n";


// 4. Proses data seperti yang dilakukan ad.php untuk mendapatkan parameter targeting
$rtb_request = $openrtb_request; // Gunakan request yang baru kita buat

$country = $rtb_request['device']['geo']['country'] ?? 'unknown';
$site_category = $rtb_request['site']['cat'][0] ?? 'IAB24';

$device_os = 'Unknown';
if (stripos($user_agent, 'Windows') !== false) $device_os = 'Windows';
elseif (stripos($user_agent, 'Macintosh') !== false) $device_os = 'macOS';
elseif (stripos($user_agent, 'Android') !== false) $device_os = 'Android';
elseif (stripos($user_agent, 'iPhone') !== false || stripos($user_agent, 'iPad') !== false) $device_os = 'iOS';
elseif (stripos($user_agent, 'Linux') !== false) $device_os = 'Linux';

$device_type = 'Desktop';
if (stripos($user_agent, 'Mobi') !== false) $device_type = 'Mobile';
elseif (stripos($user_agent, 'Tablet') !== false) $device_type = 'Tablet';

$browser = 'Unknown';
if (stripos($user_agent, 'Edg') !== false) $browser = 'Edge';
elseif (stripos($user_agent, 'Chrome') !== false && !stripos($user_agent, 'Chromium')) $browser = 'Chrome';
elseif (stripos($user_agent, 'Safari') !== false && !stripos($user_agent, 'Chrome')) $browser = 'Safari';
elseif (stripos($user_agent, 'Firefox') !== false) $browser = 'Firefox';
elseif (stripos($user_agent, 'OPR') !== false || stripos($user_agent, 'Opera') !== false) $browser = 'Opera';

$params_to_check = [
    ':zone_id' => $zone_id,
    ':country' => $country, 
    ':category' => $site_category, 
    ':os' => $device_os, 
    ':device_type' => $device_type, 
    ':browser' => $browser
];

echo "========================================\n";
echo "4. PARAMETER FINAL UNTUK QUERY SQL\n";
echo "========================================\n";
print_r($params_to_check);
echo "\n";


// 5. Jalankan query SQL dengan parameter di atas dan lihat hasilnya
$sql = "
    SELECT 
        c.id, c.name, c.campaign_type, c.priority,
        (JSON_LENGTH(ct.countries) = 0 OR JSON_CONTAINS(ct.countries, JSON_QUOTE(:country))) as country_match,
        (JSON_LENGTH(ct.site_categories) = 0 OR JSON_CONTAINS(ct.site_categories, JSON_QUOTE(:category))) as category_match,
        (JSON_LENGTH(ct.operating_systems) = 0 OR JSON_CONTAINS(ct.operating_systems, JSON_QUOTE(:os))) as os_match,
        (JSON_LENGTH(ct.device_types) = 0 OR JSON_CONTAINS(ct.device_types, JSON_QUOTE(:device_type))) as device_type_match,
        (JSON_LENGTH(ct.browsers) = 0 OR JSON_CONTAINS(ct.browsers, JSON_QUOTE(:browser))) as browser_match
    FROM campaigns c
    JOIN campaign_targeting ct ON c.id = ct.campaign_id
    WHERE c.status = 'active'
      AND (c.start_date IS NULL OR c.start_date <= CURDATE())
      AND (c.end_date IS NULL OR c.end_date >= CURDATE())";
      
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':country' => $params_to_check[':country'],
    ':category' => $params_to_check[':category'],
    ':os' => $params_to_check[':os'],
    ':device_type' => $params_to_check[':device_type'],
    ':browser' => $params_to_check[':browser']
]);
$campaign_check_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "========================================\n";
echo "5. HASIL PENGECEKAN KAMPANYE AKTIF\n";
echo "========================================\n";
if (empty($campaign_check_results)) {
    echo "TIDAK ADA KAMPANYE AKTIF YANG DITEMUKAN SAMA SEKALI (mengabaikan targeting).\n";
    echo "Pastikan ada kampanye dengan status 'active' dan tanggalnya valid.\n";
} else {
    echo "Ditemukan " . count($campaign_check_results) . " kampanye aktif. Menganalisis kecocokan targeting...\n\n";
    foreach ($campaign_check_results as $row) {
        echo "----------------------------------------\n";
        echo "Kampanye ID: " . $row['id'] . " (" . $row['name'] . ")\n";
        echo "----------------------------------------\n";
        echo "Cocok Negara ('" . $params_to_check[':country'] . "'): \t" . ($row['country_match'] ? 'YA' : 'TIDAK') . "\n";
        echo "Cocok Kategori ('" . $params_to_check[':category'] . "'): \t" . ($row['category_match'] ? 'YA' : 'TIDAK') . "\n";
        echo "Cocok OS ('" . $params_to_check[':os'] . "'): \t\t" . ($row['os_match'] ? 'YA' : 'TIDAK') . "\n";
        echo "Cocok Tipe Device ('" . $params_to_check[':device_type'] . "'): \t" . ($row['device_type_match'] ? 'YA' : 'TIDAK') . "\n";
        echo "Cocok Browser ('" . $params_to_check[':browser'] . "'): \t" . ($row['browser_match'] ? 'YA' : 'TIDAK') . "\n\n";
    }
}

echo "</pre>";

?>