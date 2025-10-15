<?php
session_start();

// Debug info - hapus di lingkungan produksi
// error_log("Session data: " . print_r($_SESSION, true));

// Keamanan: Pastikan hanya advertiser yang login yang bisa mengakses
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'advertiser') {
    // Log error untuk debugging
    error_log("Access denied to campaign_bulk_actions.php. User ID: " . 
        (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . 
        ", Role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'not set'));
    
    header("Location: /login.php?error=unauthorized");
    exit;
}

require_once '../includes/db_connection.php';

// Pastikan request adalah POST dan parameter yang diperlukan ada
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['campaign_ids']) || 
    !isset($_POST['bulk_action']) || 
    !is_array($_POST['campaign_ids'])) {
    
    header("Location: campaigns.php?error=1");
    exit;
}

$campaign_ids = array_map('intval', $_POST['campaign_ids']); // Sanitasi input
$action = $_POST['bulk_action'];
$advertiser_user_id = $_SESSION['user_id'];

if (empty($campaign_ids)) {
    header("Location: campaigns.php?error=1");
    exit;
}

if ($action !== 'activate' && $action !== 'pause') {
    header("Location: campaigns.php?error=1");
    exit;
}

$new_status = ($action === 'activate') ? 'active' : 'paused';

try {
    // Get database connection
    $pdo = get_db_connection();
    
    // Create placeholders for SQL
    $placeholders = implode(',', array_fill(0, count($campaign_ids), '?'));
    
    // KEAMANAN: Verifikasi bahwa semua kampanye ini milik advertiser yang sedang login
    $stmt_verify = $pdo->prepare("SELECT c.id, c.status FROM campaigns c 
                                JOIN advertisers a ON c.advertiser_id = a.id 
                                WHERE c.id IN ($placeholders) AND a.user_id = ?");
    
    $params = array_merge($campaign_ids, [$advertiser_user_id]);
    $stmt_verify->execute($params);
    $campaigns = $stmt_verify->fetchAll(PDO::FETCH_ASSOC);
    
    // Verifikasi bahwa jumlah kampanye yang ditemukan sama dengan jumlah yang diminta
    if (count($campaigns) !== count($campaign_ids)) {
        throw new Exception("Permission denied. Not all campaigns belong to you.");
    }
    
    // Filter kampanye berdasarkan aksi yang diminta
    $valid_campaign_ids = [];
    foreach ($campaigns as $campaign) {
        $can_update = false;
        
        // PERUBAHAN: Logika validasi aksi, sama seperti campaign_status.php
        if ($action === 'pause' && $campaign['status'] === 'active') {
            $can_update = true;
        } elseif ($action === 'activate' && $campaign['status'] === 'paused') {
            // Hanya izinkan aktivasi jika kampanye sedang dijeda (bukan pending/rejected)
            $can_update = true;
        }
        
        if ($can_update) {
            $valid_campaign_ids[] = $campaign['id'];
        }
    }
    
    if (!empty($valid_campaign_ids)) {
        // Buat placeholders baru untuk kampanye yang valid
        $valid_placeholders = implode(',', array_fill(0, count($valid_campaign_ids), '?'));
        
        // Update status untuk kampanye yang valid
        $sql = "UPDATE campaigns SET status = ? WHERE id IN ($valid_placeholders)";
        $stmt = $pdo->prepare($sql);
        $update_params = array_merge([$new_status], $valid_campaign_ids);
        $stmt->execute($update_params);
        
        header("Location: campaigns.php?success=1&bulk_action=$action&count=" . count($valid_campaign_ids));
    } else {
        // Tidak ada kampanye yang dapat diupdate
        header("Location: campaigns.php?error=3"); // Tidak ada perubahan dilakukan
    }
    
} catch (Exception $e) {
    error_log("Advertiser campaign bulk action error: " . $e->getMessage());
    header("Location: campaigns.php?error=2"); // Redirect dengan pesan error
}
exit;