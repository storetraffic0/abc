<?php
session_start();
// Pastikan hanya admin yang bisa mengakses skrip ini
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_id'])) {
    $site_id = (int)$_POST['site_id'];
    $pdo = get_db_connection();

    try {
        $pdo->beginTransaction();

        // 1. Hapus semua ZONES yang terkait dengan situs ini terlebih dahulu
        $stmt_zones = $pdo->prepare("DELETE FROM zones WHERE site_id = ?");
        $stmt_zones->execute([$site_id]);

        // 2. Setelah semua zona terhapus, hapus SITES itu sendiri
        $stmt_sites = $pdo->prepare("DELETE FROM sites WHERE id = ?");
        $stmt_sites->execute([$site_id]);

        $pdo->commit();
        header("Location: sites.php?success=3"); // Redirect dengan pesan sukses
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete Site Error: " . $e->getMessage());
        header("Location: sites.php?error=2"); // Redirect dengan pesan error
        exit;
    }
} else {
    // Akses tidak sah
    header("Location: sites.php");
    exit;
}