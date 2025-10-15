<?php
// /cron/cleanup_sessions.php - Skrip untuk membersihkan sesi popunder yang kedaluwarsa

// Set zona waktu agar konsisten dengan server
date_default_timezone_set('UTC');

// Sertakan file koneksi database
require_once __DIR__ . '/../includes/db_connection.php';

echo "==================================================\n";
echo "Starting Popunder Session Cleanup Job - " . date('Y-m-d H:i:s') . "\n";
echo "==================================================\n";

try {
    $pdo = get_db_connection();

    // Tentukan batas waktu. Hapus sesi yang lebih tua dari 2 jam.
    $ttl_hours = 2;
    
    echo "Preparing to delete sessions older than $ttl_hours hours...\n";

    // Query untuk menghapus baris yang kedaluwarsa
    $sql = "DELETE FROM pop_sessions WHERE created_at < NOW() - INTERVAL $ttl_hours HOUR";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // Dapatkan jumlah baris yang terpengaruh (dihapus)
    $deleted_rows = $stmt->rowCount();
    
    echo "Success! Deleted $deleted_rows orphaned session(s).\n";

} catch (Exception $e) {
    // Catat error jika terjadi
    $error_message = "Cleanup Job Failed: " . $e->getMessage() . "\n";
    echo $error_message;
    error_log($error_message);
}

echo "Cleanup Job Finished.\n\n";

?>