<?php
session_start();
require_once '../includes/db_connection.php';

// Keamanan: Pastikan hanya admin yang bisa mengakses skrip ini.
// Sesuaikan 'user_role' dan 'admin' dengan nama session dan role Anda.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php"); // Alihkan ke halaman login jika bukan admin
    exit;
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    $campaign_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    $new_status = '';

    // Gunakan switch untuk logika yang lebih bersih dan mudah dikembangkan
    switch ($action) {
        case 'approve':
            // Menyetujui kampanye akan membuatnya 'paused' by default.
            // Ini memberi advertiser kontrol untuk mengaktifkannya saat mereka siap.
            $new_status = 'paused'; 
            break;
        case 'reject':
            $new_status = 'rejected';
            break;
        case 'pause':
            $new_status = 'paused';
            break;
        case 'activate':
            // Aksi ini bisa digunakan untuk kampanye yang 'paused' atau 'rejected'
            $new_status = 'active';
            break;
    }

    // Jika status baru valid dan ID kampanye ada, lakukan pembaruan
    if ($new_status && $campaign_id > 0) {
        try {
            $pdo = get_db_connection();
            $sql = "UPDATE campaigns SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_status, $campaign_id]);
        } catch (PDOException $e) {
            // Sebaiknya ada sistem notifikasi error yang lebih baik di masa depan
            error_log('Failed to update campaign status: ' . $e->getMessage());
            // Anda bisa menambahkan parameter error di URL jika ingin menampilkan pesan
            // header("Location: campaigns.php?error=status_update_failed");
            // exit;
        }
    }
}

// Setelah selesai (atau jika parameter tidak valid), redirect kembali ke halaman utama kampanye.
header("Location: campaigns.php");
exit;
?>