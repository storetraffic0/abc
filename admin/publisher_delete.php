<?php
session_start();
// Pastikan hanya admin yang bisa mengakses skrip ini
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once '../includes/db_connection.php';

// Hanya proses jika metodenya POST dan ada publisher_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publisher_id'])) {
    $publisher_id = (int)$_POST['publisher_id'];
    $pdo = get_db_connection();

    try {
        $pdo->beginTransaction();

        // 1. Dapatkan user_id dan semua site_id milik publisher ini
        $sql_info = "SELECT user_id FROM publishers WHERE id = :id";
        $stmt_info = $pdo->prepare($sql_info);
        $stmt_info->execute([':id' => $publisher_id]);
        $publisher = $stmt_info->fetch();
        $user_id = $publisher['user_id'];

        $sql_sites = "SELECT id FROM sites WHERE publisher_id = :id";
        $stmt_sites = $pdo->prepare($sql_sites);
        $stmt_sites->execute([':id' => $publisher_id]);
        $sites = $stmt_sites->fetchAll(PDO::FETCH_COLUMN);

        // 2. Hapus semua ZONES dari situs-situs milik publisher
        if (!empty($sites)) {
            $sql_del_zones = "DELETE FROM zones WHERE site_id IN (" . implode(',', $sites) . ")";
            $pdo->query($sql_del_zones);
        }

        // 3. Hapus semua SITES milik publisher
        $sql_del_sites = "DELETE FROM sites WHERE publisher_id = :id";
        $stmt_del_sites = $pdo->prepare($sql_del_sites);
        $stmt_del_sites->execute([':id' => $publisher_id]);

        // 4. Hapus data dari tabel PUBLISHERS
        $sql_del_pub = "DELETE FROM publishers WHERE id = :id";
        $stmt_del_pub = $pdo->prepare($sql_del_pub);
        $stmt_del_pub->execute([':id' => $publisher_id]);

        // 5. Hapus data dari tabel USERS
        $sql_del_user = "DELETE FROM users WHERE id = :id";
        $stmt_del_user = $pdo->prepare($sql_del_user);
        $stmt_del_user->execute([':id' => $user_id]);

        $pdo->commit();
        header("Location: publishers.php?success=2"); // Redirect dengan pesan sukses
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete Publisher Error: " . $e->getMessage());
        header("Location: publishers.php?error=1"); // Redirect dengan pesan error
        exit;
    }
} else {
    // Jika akses tidak sah, redirect ke halaman daftar
    header("Location: publishers.php");
    exit;
}