<?php
// /pop.js.php - SECURE & ROBUST TAB-UNDER VERSION

// Set header agar browser menginterpretasikan file ini sebagai JavaScript
header("Content-Type: application/javascript");
// Izinkan permintaan dari domain mana pun (CORS)
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/popunder_logic.php';

/**
 * Fungsi untuk menyajikan respons JS kosong jika tidak ada iklan yang valid.
 * Ini mencegah error di sisi klien.
 */
function serve_empty_js() {
    echo "// Clicterra Pop: No eligible campaign found for this request.";
    exit;
}

// 1. Validasi Input
$zone_id = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;
if (!$zone_id) {
    serve_empty_js();
}

// 2. Dapatkan Informasi Situs & Validasi Zona
$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT s.id as site_id FROM zones z JOIN sites s ON z.site_id = s.id WHERE z.id = ? AND z.format = 'popunder' AND s.status = 'active'");
$stmt->execute([$zone_id]);
$site_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$site_info) {
    serve_empty_js();
}

// 3. Cari Kampanye yang Cocok
$user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$country_code = get_country_from_ip_local($user_ip);

$pop_request = [
    'site' => ['id' => (string)$site_info['site_id'], 'ext' => ['idzone' => $zone_id]],
    'device' => ['ip' => $user_ip, 'geo' => ['country' => $country_code]],
    'user' => ['id' => 'user_' . md5($user_ip . ($_SERVER['HTTP_USER_AGENT'] ?? ''))]
];

$winner = find_eligible_popunder_campaign($pop_request);

if (!$winner || empty($winner['ad_material'])) {
    serve_empty_js();
}

// 4. Buat Sesi Pop-under di Database
try {
    $session_id = uniqid('pop_');
    $destination_url = $winner['ad_material'];
    $cpm_price = $winner['final_cpm'];
    $campaign_id = $winner['campaign']['id'];

    $sql_insert = "INSERT INTO pop_sessions (id, campaign_id, zone_id, destination_url, cpm_price) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([$session_id, $campaign_id, $zone_id, $destination_url, $cpm_price]);

} catch (Exception $e) {
    error_log("Pop Session Insert Error: " . $e->getMessage());
    serve_empty_js();
}

// 5. Siapkan URL untuk JavaScript
$APP_SETTINGS = load_app_settings();
$AD_TAG_DOMAIN = $APP_SETTINGS['ad_tag_domain'] ?? ((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);

// URL yang akan dibuka sebagai pop-under (melalui skrip redirect go.php)
$pop_url_safe = rtrim($AD_TAG_DOMAIN, '/') . '/go.php?id=' . $session_id;

// URL untuk melacak impresi (menggunakan endpoint khusus pop_track.php)
$tracker_url_safe = rtrim($AD_TAG_DOMAIN, '/') . '/api/pop_track.php?sid=' . $session_id;

?>
// ================================================================
// BAGIAN JAVASCRIPT - Logika "Tab-Under" yang Canggih
// ================================================================
(function() {
    'use strict';
    
    // Decode URL dari PHP (sedikit disamarkan dengan base64)
    const popUrl = atob('<?php echo base64_encode($pop_url_safe); ?>');
    const trackerUrl = atob('<?php echo base64_encode($tracker_url_safe); ?>');
    
    // Flag untuk memastikan pop-under hanya terpicu sekali per halaman
    let hasFired = false;

    /**
     * Mengirim sinyal pelacakan impresi ke server.
     * Menggunakan navigator.sendBeacon jika tersedia untuk keandalan yang lebih tinggi.
     */
    function fireTracker() {
        if (navigator.sendBeacon) {
            navigator.sendBeacon(trackerUrl);
        } else {
            // Fallback untuk browser lama
            new Image().src = trackerUrl;
        }
    }
    
    /**
     * Fungsi utama yang menangani logika tab-under saat pengguna mengklik tautan.
     * @param {MouseEvent} event - Objek event dari klik mouse.
     */
    function handleNavigation(event) {
        // Jika sudah terpicu, jangan lakukan apa-apa lagi
        if (hasFired) return;

        // Cari elemen <a> (tautan) yang paling dekat dengan target klik
        let target = event.target.closest('a');

        // Validasi tautan untuk memastikan kita tidak membajak klik yang salah:
        // 1. Pastikan target adalah tautan (<a>) yang valid dan memiliki href.
        // 2. Pastikan tautan tersebut adalah tautan internal (hostname sama dengan situs saat ini).
        // 3. Pastikan tautan tidak dimaksudkan untuk membuka tab baru (target="_blank").
        // 4. Pastikan tautan bukan tautan JavaScript (misal: href="javascript:void(0)").
        if (!target || !target.href || target.hostname !== window.location.hostname || target.target === '_blank' || target.href.toLowerCase().startsWith('javascript:')) {
            return;
        }

        // Jika semua validasi lolos, cegah perilaku default tautan untuk sementara
        event.preventDefault();
        hasFired = true;

        // Buka URL iklan di tab baru di latar belakang
        window.open(popUrl, '_blank');
        
        // Kirim sinyal pelacakan impresi
        fireTracker();

        // Arahkan tab saat ini ke tujuan tautan asli yang diklik pengguna
        window.location.href = target.href;
    }

    // Tambahkan event listener ke seluruh dokumen untuk menangkap semua klik.
    // 'capture: true' memastikan listener ini berjalan sebelum listener lain di halaman.
    document.addEventListener('click', handleNavigation, { capture: true });
})();