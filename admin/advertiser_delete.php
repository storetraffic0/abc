<?php
session_start();
// Pastikan hanya admin yang bisa mengakses skrip ini
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['advertiser_id'])) {
    $advertiser_id = (int)$_POST['advertiser_id'];
    $pdo = get_db_connection();

    try {
        $pdo->beginTransaction();

        // 1. Dapatkan user_id dari advertiser
        $stmt_info = $pdo->prepare("SELECT user_id FROM advertisers WHERE id = :id");
        $stmt_info->execute([':id' => $advertiser_id]);
        $advertiser = $stmt_info->fetch();
        
        if (!$advertiser) {
            throw new Exception("Advertiser not found.");
        }
        $user_id = $advertiser['user_id'];

        // 2. Hapus semua data terkait kampanye (details, targeting, mapping)
        // (Ini adalah contoh, jika ada lebih banyak tabel terkait, tambahkan di sini)
        $stmt_campaigns = $pdo->prepare("SELECT id FROM campaigns WHERE advertiser_id = :id");
        $stmt_campaigns->execute([':id' => $advertiser_id]);
        $campaign_ids = $stmt_campaigns->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($campaign_ids)) {
            $in_clause = implode(',', array_fill(0, count($campaign_ids), '?'));
            $pdo->prepare("DELETE FROM campaign_details WHERE campaign_id IN ($in_clause)")->execute($campaign_ids);
            $pdo->prepare("DELETE FROM campaign_targeting WHERE campaign_id IN ($in_clause)")->execute($campaign_ids);
            $pdo->prepare("DELETE FROM campaign_zone_mapping WHERE campaign_id IN ($in_clause)")->execute($campaign_ids);
        }

        // 3. Hapus kampanye itu sendiri
        $stmt_del_campaigns = $pdo->prepare("DELETE FROM campaigns WHERE advertiser_id = :id");
        $stmt_del_campaigns->execute([':id' => $advertiser_id]);

        // 4. Hapus data dari tabel ADVERTISERS
        $stmt_del_adv = $pdo->prepare("DELETE FROM advertisers WHERE id = :id");
        $stmt_del_adv->execute([':id' => $advertiser_id]);

        // 5. Hapus data dari tabel USERS
        $stmt_del_user = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt_del_user->execute([':id' => $user_id]);

        $pdo->commit();
        header("Location: advertisers.php?success=2"); // Redirect dengan pesan sukses
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete Advertiser Error: " . $e->getMessage());
        header("Location: advertisers.php?error=1"); // Redirect dengan pesan error
        exit;
    }
} else {
    header("Location: advertisers.php");
    exit;
}