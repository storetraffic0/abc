<?php
// /api/serve_vast.php - Skrip Proxy untuk menyajikan file VAST XML dengan header CORS yang benar.

// 1. Tambahkan header CORS yang sangat penting.
// Ini adalah izin yang kita berikan ke browser.
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/xml; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");

// 2. Dapatkan nama file dari parameter URL.
$filename = $_GET['file'] ?? '';

// 3. Lakukan validasi keamanan yang ketat.
// - Pastikan nama file tidak kosong.
// - Pastikan tidak ada upaya "directory traversal" (menggunakan '..' atau '/').
// - Pastikan file berakhiran .xml.
if (empty($filename) || strpos($filename, '..') !== false || strpos($filename, '/') !== false || substr($filename, -4) !== '.xml') {
    http_response_code(400); // Bad Request
    echo '<?xml version="1.0" encoding="UTF-8"?><VAST version="2.0"><Error>Invalid file requested.</Error></VAST>';
    exit;
}

// 4. Bangun path file yang aman dan periksa apakah file ada.
$cache_dir = __DIR__ . '/../cache/vast/';
$full_path = $cache_dir . $filename;

if (!file_exists($full_path)) {
    http_response_code(404); // Not Found
    echo '<?xml version="1.0" encoding="UTF-8"?><VAST version="2.0"><Error>VAST file not found.</Error></VAST>';
    exit;
}

// 5. Jika semua aman dan file ada, baca dan tampilkan isinya.
readfile($full_path);
exit;