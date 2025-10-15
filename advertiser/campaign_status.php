<?php
session_start();
// Keamanan: Pastikan hanya advertiser yang login yang bisa mengakses
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'advertiser') {
    header("Location: /login.php");
    exit;
}

require_once '../includes/db_connection.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $campaign_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $advertiser_user_id = $_SESSION['user_id'];
    $new_status = '';

    if ($action === 'pause') {
        $new_status = 'paused';
    } elseif ($action === 'activate') {
        $new_status = 'active';
    }

    if ($new_status && $campaign_id > 0) {
        $pdo = get_db_connection();
        try {
            // KEAMANAN: Verifikasi bahwa kampanye ini milik advertiser yang sedang login
            // dan ambil status saat ini untuk divalidasi.
            $stmt_verify = $pdo->prepare("SELECT c.id, c.status FROM campaigns c JOIN advertisers a ON c.advertiser_id = a.id WHERE c.id = ? AND a.user_id = ?");
            $stmt_verify->execute([$campaign_id, $advertiser_user_id]);
            $campaign = $stmt_verify->fetch();
            
            if ($campaign) {
                // PERUBAHAN: Logika validasi aksi
                $can_update = false;
                if ($action === 'pause' && $campaign['status'] === 'active') {
                    $can_update = true;
                } elseif ($action === 'activate' && $campaign['status'] === 'paused') {
                    // Hanya izinkan aktivasi jika kampanye sedang dijeda (bukan pending/rejected)
                    $can_update = true;
                }

                if ($can_update) {
                    // Jika verifikasi dan validasi berhasil, update status
                    $sql = "UPDATE campaigns SET status = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$new_status, $campaign_id]);
                    header("Location: campaigns.php?success=3");
                } else {
                    // Aksi tidak diizinkan untuk status saat ini
                    throw new Exception("Action not allowed for the current campaign status.");
                }
            } else {
                // Kampanye tidak ditemukan atau bukan milik advertiser ini
                throw new Exception("Permission denied.");
            }
        } catch (Exception $e) {
            error_log("Advertiser campaign status error: " . $e->getMessage());
            header("Location: campaigns.php?error=2"); // Redirect dengan pesan error
        }
        exit;
    }
}

// Jika parameter tidak valid, redirect kembali
header("Location: campaigns.php");
exit;