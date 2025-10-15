<?php
// /includes/auth.php

// Mulai sesi jika belum ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fungsi ini memeriksa apakah pengguna sudah login sebagai admin.
 * Jika tidak, ia akan mengarahkan pengguna ke halaman login.
 */
function require_admin_login() {
    // Pengecualian agar halaman login, auth, dan forgot-password tidak saling redirect
    $allowed_files = ['index.php', 'auth.php', 'forgot-password.php', 'reset-password.php'];
    
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        if (!in_array(basename($_SERVER['PHP_SELF']), $allowed_files)) {
            header("Location: /admin/index.php");
            exit;
        }
    }
}