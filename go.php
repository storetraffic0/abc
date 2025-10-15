<?php
// /go.php - Secure Click Redirector

require_once __DIR__ . '/includes/db_connection.php';

$session_id = $_GET['id'] ?? '';

if (empty($session_id) || !preg_match('/^pop_[a-f0-9]{13}$/', $session_id)) {
    http_response_code(400);
    echo "Invalid ID.";
    exit;
}

try {
    $pdo = get_db_connection();
    // Ambil URL tujuan dari database berdasarkan ID sesi
    $stmt = $pdo->prepare("SELECT destination_url FROM pop_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $destination_url = $stmt->fetchColumn();

    if ($destination_url && filter_var($destination_url, FILTER_VALIDATE_URL)) {
        // Redirect pengguna ke URL tujuan yang sebenarnya
        header("Location: " . $destination_url, true, 302);
        exit;
    } else {
        // Jika ID tidak ditemukan atau URL tidak valid
        http_response_code(404);
        echo "Destination not found.";
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Redirector error: " . $e->getMessage());
    echo "An internal error occurred.";
}
?>