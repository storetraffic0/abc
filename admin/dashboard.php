<?php
$page_title = 'Dashboard';
include 'template/header.php';
require_once '../includes/db_connection.php';

// Inisialisasi semua variabel untuk mencegah error
$kpi = [
    'today_revenue' => 0, 'today_publisher_payout' => 0, 'today_profit' => 0,
    'today_impressions' => 0, 'today_ecpm' => 0
];
$pending_deposits_count = 0;
$pending_campaigns_count = 0;
$top_advertisers = []; $recent_users = [];
$chart_labels = []; $chart_revenue_data = []; $chart_impressions_data = [];

try {
    $pdo = get_db_connection();
    $today_str = date('Y-m-d');

    // =================================================================
    // 1. OPTIMASI: DATA KPI MENGGUNAKAN TABEL RINGKASAN
    // =================================================================
    
    // Data dari advertiser_daily_stats (untuk revenue total)
    $sql_today_adv = "SELECT 
                        SUM(impressions) as total_impressions,
                        SUM(total_spend) as total_revenue
                      FROM advertiser_daily_stats 
                      WHERE report_date = ?";
    $stmt_today_adv = $pdo->prepare($sql_today_adv);
    $stmt_today_adv->execute([$today_str]);
    $stats_today_adv = $stmt_today_adv->fetch(PDO::FETCH_ASSOC);
    
    // Data dari publisher_daily_stats (untuk publisher payouts)
    $sql_today_pub = "SELECT SUM(total_revenue) as total_payout
                      FROM publisher_daily_stats
                      WHERE report_date = ?";
    $stmt_today_pub = $pdo->prepare($sql_today_pub);
    $stmt_today_pub->execute([$today_str]);
    $stats_today_pub = $stmt_today_pub->fetch(PDO::FETCH_ASSOC);
    
    // Gabungkan data untuk KPI
    $kpi['today_impressions'] = $stats_today_adv['total_impressions'] ?? 0;
    $kpi['today_revenue'] = $stats_today_adv['total_revenue'] ?? 0;
    $kpi['today_publisher_payout'] = $stats_today_pub['total_payout'] ?? 0;
    $kpi['today_profit'] = $kpi['today_revenue'] - $kpi['today_publisher_payout'];
    $kpi['today_ecpm'] = ($kpi['today_impressions'] > 0) ? ($kpi['today_revenue'] / $kpi['today_impressions']) * 1000 : 0;

    // =================================================================
    // 2. DATA UNTUK ACTION CENTER (Real-time)
    // =================================================================
    $pending_deposits_count = $pdo->query("SELECT COUNT(*) FROM deposit_requests WHERE status = 'pending'")->fetchColumn();
    $pending_campaigns_count = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status = 'pending'")->fetchColumn();

    // =================================================================
    // 3. OPTIMASI: DATA CHART MENGGUNAKAN TABEL RINGKASAN
    // =================================================================
    $sql_chart = "SELECT report_date, SUM(total_spend) as revenue, SUM(impressions) as impressions 
                  FROM advertiser_daily_stats 
                  WHERE report_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
                  GROUP BY report_date ORDER BY report_date ASC";
    $chart_results = $pdo->query($sql_chart)->fetchAll(PDO::FETCH_ASSOC);
    
    // Buat map dari tanggal ke data untuk mempermudah akses
    $chart_data_map = array_column($chart_results, null, 'report_date');
    
    // Isi data chart untuk 7 hari terakhir
    for ($i = 6; $i >= 0; $i--) {
        $date_str_loop = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('M d', strtotime($date_str_loop));
        
        $chart_revenue_data[] = $chart_data_map[$date_str_loop]['revenue'] ?? 0;
        $chart_impressions_data[] = $chart_data_map[$date_str_loop]['impressions'] ?? 0;
    }

    // =================================================================
    // 4. OPTIMASI: TOP ADVERTISERS MENGGUNAKAN TABEL RINGKASAN
    // =================================================================
    $sql_top_adv = "SELECT a.company_name, SUM(s.total_spend) as total 
                    FROM advertiser_daily_stats s 
                    JOIN advertisers a ON s.advertiser_id = a.id 
                    WHERE s.report_date = ?
                    GROUP BY s.advertiser_id, a.company_name 
                    HAVING total > 0 
                    ORDER BY total DESC 
                    LIMIT 5";
    $stmt_top_adv = $pdo->prepare($sql_top_adv);
    $stmt_top_adv->execute([$today_str]);
    $top_advertisers = $stmt_top_adv->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil recent users - ini sudah efisien, tidak perlu diubah
    $recent_users = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { 
    error_log("Admin dashboard error: " . $e->getMessage()); 
}
?>

<!-- HTML Bagian Atas (Kartu KPI, dll) -->
<div class="d-sm-flex align-items-center justify-content-between mb-4"><h1 class="h3 mb-0 text-gray-800">Admin Dashboard</h1></div>
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-primary shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Impressions (Today)</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($kpi['today_impressions']); ?></div></div><div class="col-auto"><i class="fas fa-eye fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-success shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-success text-uppercase mb-1">Revenue (Today)</div><div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($kpi['today_revenue'], 2); ?></div></div><div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-info shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-info text-uppercase mb-1">Profit (Today)</div><div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($kpi['today_profit'], 2); ?></div></div><div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div></div></div></div></div>
    
    <!-- Kartu KPI ke-4 untuk Pending Campaigns -->
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="campaigns.php" style="text-decoration: none;">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Campaigns</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pending_campaigns_count); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-signature fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Action Center -->
<h5 class="h5 mb-2 text-gray-800">Action Center</h5>
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card bg-warning text-white shadow">
            <div class="card-body">
                Pending Deposits
                <div class="text-white-50 small"><?php echo number_format($pending_deposits_count); ?> items require attention</div>
            </div>
        </div>
    </div>
</div>

<!-- Chart & Top Lists -->
<div class="row">
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Performance Overview (Last 7 Days)</h6></div>
            <div class="card-body"><div class="chart-area"><canvas id="performanceChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Top Advertisers by Spend (Today)</h6></div>
            <div class="card-body">
                <?php if(empty($top_advertisers)): echo '<p class="text-center text-muted mt-3">No spending data for today.</p>'; else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach($top_advertisers as $item): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center"><?php echo e($item['company_name']); ?><span class="badge bg-success rounded-pill">$<?php echo number_format($item['total'], 2); ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Users -->
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Recent Activity - Newest Users</h6></div>
            <div class="card-body">
            <?php if(empty($recent_users)): echo '<p class="text-center text-muted mt-3">No new users.</p>'; else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <?php foreach($recent_users as $user): ?>
                        <tr>
                            <td><?php echo e($user['username']); ?><br><small class="text-muted"><?php echo e($user['email']); ?></small></td>
                            <td><span class="badge bg-secondary"><?php echo e(ucfirst($user['role'])); ?></span></td>
                            <td class="text-end"><small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'template/footer.php'; ?>

<script>
var ctx = document.getElementById("performanceChart");
new Chart(ctx, {
  type: 'line',
  data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: "Revenue ($)", yAxisID: 'A', data: <?php echo json_encode($chart_revenue_data); ?>, borderColor: '#1cc88a', backgroundColor: 'rgba(28, 200, 138, 0.05)' }, { label: "Impressions", yAxisID: 'B', data: <?php echo json_encode($chart_impressions_data); ?>, borderColor: '#4e73df', backgroundColor: 'rgba(78, 115, 223, 0.05)' }] },
  options: { maintainAspectRatio: false, scales: { yAxes: [{ id: 'A', type: 'linear', position: 'left', ticks: { beginAtZero: true, callback: function(v) { return '$' + new Intl.NumberFormat().format(v); } }, scaleLabel: { display: true, labelString: 'Revenue ($)' } }, { id: 'B', type: 'linear', position: 'right', gridLines: { drawOnChartArea: false }, ticks: { beginAtZero: true, callback: function(v) { return new Intl.NumberFormat().format(v); } }, scaleLabel: { display: true, labelString: 'Impressions' } }] }, tooltips: { mode: 'index', intersect: false } }
});
</script>