<?php
session_start();
// Keamanan: Pastikan hanya advertiser yang login yang bisa mengakses
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'advertiser') {
    header("Location: /login.php");
    exit;
}

require_once '../includes/db_connection.php';

$campaign_id = $_GET['id'] ?? null;
$advertiser_user_id = $_SESSION['user_id'];

if (!$campaign_id) {
    header("Location: campaigns.php");
    exit;
}

try {
    $pdo = get_db_connection();
    
    // KEAMANAN: Verifikasi bahwa kampanye ini milik advertiser yang sedang login
    $stmt_verify = $pdo->prepare("SELECT c.*, a.id as advertiser_id 
                               FROM campaigns c 
                               JOIN advertisers a ON c.advertiser_id = a.id 
                               WHERE c.id = ? AND a.user_id = ?");
    $stmt_verify->execute([$campaign_id, $advertiser_user_id]);
    $campaign = $stmt_verify->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        throw new Exception("Permission denied or campaign not found.");
    }
    
    $advertiser_id = $campaign['advertiser_id'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get campaign details
    $stmt_details = $pdo->prepare("SELECT * FROM campaign_details WHERE campaign_id = ?");
    $stmt_details->execute([$campaign_id]);
    $details = $stmt_details->fetch(PDO::FETCH_ASSOC);
    
    // Get campaign targeting
    $stmt_targeting = $pdo->prepare("SELECT * FROM campaign_targeting WHERE campaign_id = ?");
    $stmt_targeting->execute([$campaign_id]);
    $targeting = $stmt_targeting->fetch(PDO::FETCH_ASSOC);
    
    // Insert new campaign with cloned data
    $new_name = $campaign['name'] . " (Clone)";
    
    $sql_camp = "INSERT INTO campaigns (advertiser_id, name, campaign_type, ad_format, status, priority, cpm_rate) 
                 VALUES (?, ?, ?, ?, 'pending', ?, ?)";
    $stmt_camp = $pdo->prepare($sql_camp);
    $stmt_camp->execute([
        $advertiser_id,
        $new_name,
        $campaign['campaign_type'],
        $campaign['ad_format'],
        $campaign['priority'],
        $campaign['cpm_rate']
    ]);
    $new_campaign_id = $pdo->lastInsertId();
    
    // Insert cloned details
    $sql_details = "INSERT INTO campaign_details (
        campaign_id, third_party_vast_url, destination_url, 
        rtb_endpoint_url, banner_url, banner_html, banner_size, banner_click_url
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_details = $pdo->prepare($sql_details);
    $stmt_details->execute([
        $new_campaign_id,
        $details['third_party_vast_url'] ?? null,
        $details['destination_url'] ?? null,
        $details['rtb_endpoint_url'] ?? null,
        $details['banner_url'] ?? null,
        $details['banner_html'] ?? null,
        $details['banner_size'] ?? null,
        $details['banner_click_url'] ?? null
    ]);
    
    // Insert cloned targeting
    $sql_targeting = "INSERT INTO campaign_targeting (
        campaign_id, countries, site_categories, operating_systems, device_types, browsers
    ) VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt_targeting = $pdo->prepare($sql_targeting);
    $stmt_targeting->execute([
        $new_campaign_id,
        $targeting['countries'] ?? '[]',
        $targeting['site_categories'] ?? '[]',
        $targeting['operating_systems'] ?? '[]',
        $targeting['device_types'] ?? '[]',
        $targeting['browsers'] ?? '[]'
    ]);
    
    $pdo->commit();
    header("Location: campaigns.php?success=1&clone=success");
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Advertiser campaign clone error: " . $e->getMessage());
    header("Location: campaigns.php?error=2"); // Redirect dengan pesan error
}
exit;