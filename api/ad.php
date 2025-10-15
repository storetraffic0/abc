<?php
// /api/ad.php - REFACTORED

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ad_logic.php'; // Muat file logika baru

$rtb_request = json_decode(file_get_contents('php://input'), true);
if (!$rtb_request) {
    header("Content-Type: application/xml; charset=utf-8");
    echo '<?xml version="1.0" encoding="UTF-8"?><VAST version="3.0"></VAST>';
    exit;
}

// Panggil fungsi inti
$result = find_eligible_campaign($rtb_request);

// Kirim respons berdasarkan tipe hasilnya
if ($result['type'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result['content']);
} else { // 'xml'
    header('Content-Type: application/xml; charset=utf-8');
    echo $result['content'];
}
exit;