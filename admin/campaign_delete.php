<?php
session_start();
// Pastikan hanya admin yang bisa mengakses skrip ini
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['campaign_id'])) {
    $campaign_id = (int)$_POST['campaign_id'];
    $pdo = get_db_connection();

    try {
        $pdo->beginTransaction();

        // 1. Hapus dari tabel 'campaign_targeting'
        $stmt1 = $pdo->prepare("DELETE FROM campaign_targeting WHERE campaign_id = ?");
        $stmt1->execute([$campaign_id]);

        // 2. Hapus dari tabel 'campaign_details'
        $stmt2 = $pdo->prepare("DELETE FROM campaign_details WHERE campaign_id = ?");
        $stmt2->execute([$campaign_id]);

        // 3. Hapus dari tabel utama 'campaigns'
        $stmt3 = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
        $stmt3->execute([$campaign_id]);

        $pdo->commit();
        header("Location: campaigns.php?success=2"); // Redirect dengan pesan sukses
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete Campaign Error: ". $e->getMessage());
        header("Location: campaigns.php?error=1"); // Redirect dengan pesan error
        exit;
    }
} else {
    // Akses tidak sah
    header("Location: campaigns.php");
    exit;
}