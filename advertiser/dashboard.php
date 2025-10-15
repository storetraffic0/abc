<?php
$page_title = 'Advertiser Dashboard';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();
$advertiser_user_id = $_SESSION['user_id'];

// --- PENGATURAN PERIODE ---
$start_date_str = $_GET['start'] ?? date('Y-m-d');
$end_date_str = $_GET['end'] ?? date('Y-m-d');
$start_date = new DateTime($start_date_str);
$end_date = new DateTime($end_date_str);

// Inisialisasi variabel
$stats = ['impressions' => 0, 'clicks' => 0, 'total_spend' => 0];
$chart_data = [];
$top_campaigns = [];

try {
    $stmt_adv_id = $pdo->prepare("SELECT id FROM advertisers WHERE user_id = ?");
    $stmt_adv_id->execute([$advertiser_user_id]);
    $advertiser_id = $stmt_adv_id->fetchColumn();

    if ($advertiser_id) {
        $params = [':advertiser_id' => $advertiser_id, ':start_date' => $start_date_str, ':end_date' => $end_date_str];

        // =================================================================
        // PERBAIKAN 1: Query statistik utama dari tabel ringkasan
        // =================================================================
        $sql_stats = "SELECT 
                        SUM(impressions) as total_impressions,
                        SUM(clicks) as total_clicks,
                        SUM(total_spend) as total_spend
                      FROM advertiser_daily_stats 
                      WHERE advertiser_id = :advertiser_id AND report_date BETWEEN :start_date AND :end_date";
        $stmt_stats = $pdo->prepare($sql_stats);
        $stmt_stats->execute($params);
        $stats_result = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        $stats['impressions'] = $stats_result['total_impressions'] ?? 0;
        $stats['clicks'] = $stats_result['total_clicks'] ?? 0;
        $stats['total_spend'] = $stats_result['total_spend'] ?? 0;

        // =================================================================
        // PERBAIKAN 2: Query untuk data chart dari tabel ringkasan
        // =================================================================
        $sql_chart = "SELECT report_date as date, SUM(impressions) as impressions 
                      FROM advertiser_daily_stats 
                      WHERE advertiser_id = :advertiser_id AND report_date BETWEEN :start_date AND :end_date
                      GROUP BY date ORDER BY date ASC";
        $stmt_chart = $pdo->prepare($sql_chart);
        $stmt_chart->execute($params);
        $chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

        // =================================================================
        // PERBAIKAN 3: Query untuk top 5 kampanye dari tabel ringkasan
        // =================================================================
        $sql_top = "SELECT c.name, SUM(s.impressions) as impressions
                    FROM advertiser_daily_stats s JOIN campaigns c ON s.campaign_id = c.id
                    WHERE s.advertiser_id = :advertiser_id AND s.report_date BETWEEN :start_date AND :end_date
                    GROUP BY c.id, c.name ORDER BY impressions DESC LIMIT 5";
        $stmt_top = $pdo->prepare($sql_top);
        $stmt_top->execute($params);
        $top_campaigns = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Advertiser dashboard error: " . $e->getMessage());
    // Tampilkan pesan error yang lebih ramah jika perlu
    // echo "<div class='alert alert-danger'>Could not load dashboard data. Please try again later.</div>";
}

$ctr = ($stats['impressions'] > 0) ? ($stats['clicks'] / $stats['impressions']) * 100 : 0;
$chart_labels = []; $chart_impressions = [];
$period = new DatePeriod($start_date, new DateInterval('P1D'), (clone $end_date)->modify('+1 day'));
$impressions_by_date = array_column($chart_data, 'impressions', 'date');
foreach ($period as $date) {
    $chart_labels[] = $date->format('M d');
    $chart_impressions[] = $impressions_by_date[$date->format('Y-m-d')] ?? 0;
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Advertiser Dashboard</h1>
    <div id="reportrange" style="background: #fff; cursor: pointer; padding: 5px 10px; border: 1px solid #ccc; width: 100%; max-width: 280px; text-align:center;"><i class="fa fa-calendar"></i>&nbsp;<span></span> <i class="fa fa-caret-down"></i></div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-primary shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Impressions</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['impressions']); ?></div></div><div class="col-auto"><i class="fas fa-eye fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-info shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-info text-uppercase mb-1">Clicks</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['clicks']); ?></div></div><div class="col-auto"><i class="fas fa-mouse-pointer fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-warning shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-warning text-uppercase mb-1">CTR</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($ctr, 2); ?>%</div></div><div class="col-auto"><i class="fas fa-percentage fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-success shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Spend</div><div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_spend'], 4); ?></div></div><div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div></div></div></div></div>
</div>

<div class="row">
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Impressions Trend</h6></div><div class="card-body"><div class="chart-area"><canvas id="advertiserImpressionsChart"></canvas></div></div></div>
    </div>
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Top Campaigns by Impressions</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <?php if(empty($top_campaigns)): ?>
                            <tr><td class="text-center">No campaign data in this period.</td></tr>
                        <?php else: foreach($top_campaigns as $campaign): ?>
                            <tr><td><?php echo e($campaign['name']); ?></td><td class="text-end"><strong><?php echo number_format($campaign['impressions']); ?></strong> imp.</td></tr>
                        <?php endforeach; endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'template/footer.php'; ?>

<script>
$(function() {
    // --- Inisialisasi Date Range Picker ---
    var start = moment("<?php echo $start_date_str; ?>"); var end = moment("<?php echo $end_date_str; ?>");
    function cb(start, end) { $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY')); }
    $('#reportrange').daterangepicker({ startDate: start, endDate: end, ranges: {'Today': [moment(), moment()], 'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')], 'Last 7 Days': [moment().subtract(6, 'days'), moment()], 'This Month': [moment().startOf('month'), moment().endOf('month')]} }, cb);
    cb(start, end);
    $('#reportrange').on('apply.daterangepicker', function(ev, picker) { window.location.href = 'dashboard.php?start=' + picker.startDate.format('YYYY-MM-DD') + '&end=' + picker.endDate.format('YYYY-MM-DD'); });

    // --- Inisialisasi Chart ---
    var ctx = document.getElementById("advertiserImpressionsChart");
    new Chart(ctx, { type: 'line', data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: "Impressions", lineTension: 0.3, backgroundColor: "rgba(78, 115, 223, 0.05)", borderColor: "rgba(78, 115, 223, 1)", data: <?php echo json_encode($chart_impressions); ?> }] }, options: { maintainAspectRatio: false, scales: { yAxes: [{ ticks: { beginAtZero: true, callback: function(value) { if (Number.isInteger(value)) { return new Intl.NumberFormat().format(value); } } } }] }, legend: { display: false } } });
});
</script>