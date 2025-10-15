<?php
session_start();
require_once '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $pdo = get_db_connection();
        $sql = "SELECT id, password_hash, role FROM users WHERE username = :username LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // PENTING: Ganti pengecekan ini dengan password_verify() di aplikasi produksi
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login sukses, simpan info user ke session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            // PERUBAHAN UTAMA: Arahkan berdasarkan peran
            if ($user['role'] === 'admin') {
                header("Location: /admin/dashboard.php");
            } elseif ($user['role'] === 'publisher') {
                header("Location: /publisher/dashboard.php");
            } else {
                // Untuk peran lain di masa depan, misal advertiser
                header("Location: index.php?error=Access Denied");
            }
            exit;
        } else {
            // Login gagal
            header("Location: index.php?error=Invalid credentials");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: index.php?error=Database error");
        exit;
    }
}