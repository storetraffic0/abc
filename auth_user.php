<?php
session_start();
require_once __DIR__ . '/includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $pdo = get_db_connection();
        $sql = "SELECT id, password_hash, role FROM users WHERE username = ? AND role IN ('publisher', 'advertiser')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'publisher') {
                header("Location: /publisher/dashboard.php");
            } else { // Untuk advertiser di masa depan
                header("Location: /advertiser/dashboard.php");
            }
            exit;
        } else {
            header("Location: login.php?error=Invalid credentials or access denied.");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: login.php?error=Database error.");
        exit;
    }
}