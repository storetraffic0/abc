<?php
// cache_ssp_tokens.php - Skrip untuk mengisi cache Redis dengan data SSP

require_once __DIR__ . '/includes/db_connection.php';

echo "Memulai proses caching data SSP ke Redis...\n";

$pdo = get_db_connection();
$redis = get_redis_connection();

if (!$pdo || !$redis) {
    die("Tidak dapat terhubung ke DB atau Redis. Keluar.\n");
}

try {
    // Query untuk mengambil semua data SSP yang aktif
    $sql = "SELECT 
                p.ssp_token,
                p.id as publisher_id, 
                p.revenue_share, 
                z.id as zone_id, 
                s.id as site_id, 
                s.domain, 
                s.category 
            FROM publishers p 
            JOIN sites s ON p.id = s.publisher_id 
            JOIN zones z ON s.id = z.site_id 
            WHERE p.ssp_token IS NOT NULL 
              AND p.ssp_token != '' 
              AND p.ssp_enabled = 1 
              AND s.status = 'active' 
              AND z.status = 'active'";
    
    $stmt = $pdo->query($sql);
    
    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $token = $row['ssp_token'];
        
        // Buat kunci Redis yang unik untuk setiap token
        $redis_key = "ssp_token:" . $token;
        
        // Simpan seluruh baris data sebagai string JSON.
        // Tidak ada masa berlaku (expire), data akan ada selamanya sampai di-refresh.
        $redis->set($redis_key, json_encode($row));
        
        $count++;
    }
    
    echo "Selesai! Berhasil menyimpan data untuk " . $count . " token SSP ke dalam cache Redis.\n";

} catch (Exception $e) {
    die("Terjadi error: " . $e->getMessage() . "\n");
}
?>