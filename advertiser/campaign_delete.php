<?php
session_start();

// Debug
error_log("Delete request received - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'advertiser') {
    error_log("Authentication failed: user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . 
        ", user_role=" . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'not set'));
    header("Location: /login.php"); 
    exit;
}

require_once '../includes/db_connection.php';

// Periksa apakah permintaan adalah POST dan memiliki campaign_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['campaign_id'])) {
    $campaign_id = (int)$_POST['campaign_id'];
    $advertiser_user_id = $_SESSION['user_id'];
    
    // Log informasi
    error_log("Processing delete for campaign ID: $campaign_id by user: $advertiser_user_id");
    
    // Validasi input
    if ($campaign_id <= 0) {
        error_log("Invalid campaign ID: $campaign_id");
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
        $result = $stmt_verify->fetch();
        
        error_log("Verification result: " . ($result ? "Campaign found" : "Campaign not found"));
        
        if ($result) {
            // Mulai transaksi database
            $pdo->beginTransaction();
            
            // Hapus targeting
            $stmt1 = $pdo->prepare("DELETE FROM campaign_targeting WHERE campaign_id = ?");
            $stmt1->execute([$campaign_id]);
            error_log("Targeting deleted");
            
            // Hapus details
            $stmt2 = $pdo->prepare("DELETE FROM campaign_details WHERE campaign_id = ?");
            $stmt2->execute([$campaign_id]);
            error_log("Details deleted");
            
            // Hapus kampanye
            $stmt3 = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
            $stmt3->execute([$campaign_id]);
            error_log("Campaign deleted");
            
            // Commit transaksi
            $pdo->commit();
            error_log("Transaction committed");
            
            // Redirect dengan pesan sukses
            header("Location: campaigns.php?success=delete");
            exit;
        } else {
            // Kampanye tidak ditemukan atau bukan milik advertiser ini
            error_log("Permission denied - campaign not found or doesn't belong to this advertiser");
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
    // Log debug info
    error_log("Invalid delete request - Method: " . $_SERVER['REQUEST_METHOD'] . 
        ", campaign_id present: " . (isset($_POST['campaign_id']) ? 'yes' : 'no'));
    
    // Permintaan tidak valid
    header("Location: campaigns.php?error=invalid");
    exit;
}