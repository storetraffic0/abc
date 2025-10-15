<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'advertiser') {
    header("Location: /login.php"); 
    exit;
}

require_once '../includes/db_connection.php';

// Check for CSRF token
if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
    header("Location: campaigns.php?error=invalid_token");
    exit;
}

if (isset($_GET['id'])) {
    $campaign_id = (int)$_GET['id'];
    $advertiser_user_id = $_SESSION['user_id'];
    
    // Validasi input
    if ($campaign_id <= 0) {
        header("Location: campaigns.php?error=invalid_id");
        exit;
    }
    
    try {
        $pdo = get_db_connection();
        
        // Verifikasi kepemilikan sebelum menghapus
        $stmt_verify = $pdo->prepare("SELECT c.id FROM campaigns c 
                                     JOIN advertisers a ON c.advertiser_id = a.id 
                                     WHERE c.id = ? AND a.user_id = ?");
        $stmt_verify->execute([$campaign_id, $advertiser_user_id]);
        
        if ($stmt_verify->fetch()) {
            // Mulai transaksi database
            $pdo->beginTransaction();
            
            // Hapus targeting
            $stmt1 = $pdo->prepare("DELETE FROM campaign_targeting WHERE campaign_id = ?");
            $stmt1->execute([$campaign_id]);
            
            // Hapus details
            $stmt2 = $pdo->prepare("DELETE FROM campaign_details WHERE campaign_id = ?");
            $stmt2->execute([$campaign_id]);
            
            // Hapus kampanye
            $stmt3 = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
            $stmt3->execute([$campaign_id]);
            
            // Commit transaksi
            $pdo->commit();
            
            // Redirect dengan pesan sukses
            header("Location: campaigns.php?success=delete");
            exit;
        } else {
            // Kampanye tidak ditemukan atau bukan milik advertiser ini
            header("Location: campaigns.php?error=permission");
            exit;
        }
    } catch (Exception $e) {
        // Rollback transaksi jika error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log error
        error_log("Campaign delete error: " . $e->getMessage());
        
        // Redirect dengan pesan error
        header("Location: campaigns.php?error=delete&message=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Permintaan tidak valid
    header("Location: campaigns.php?error=invalid");
    exit;
}