<?php
$page_title = 'Campaign Reports';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();
$advertiser_user_id = $_SESSION['user_id'];

// --- PENGATURAN FILTER DAN PAGINASI ---
$start_date_str = $_GET['start'] ?? date('Y-m-d', strtotime('-6 days'));
$end_date_str = $_GET['end'] ?? date('Y-m-d');
$ad_format_filter = $_GET['ad_format'] ?? ''; // Filter berdasarkan format iklan
$group_by_options = [
    'report_date' => 'Date', 
    'campaign_id' => 'Campaign', 
    'country_code' => 'Country',
    'site_id' => 'Site', 
    'device_type' => 'Device Type', 
    'os' => 'Operating System', 
    'browser' => 'Browser',
    'ad_format' => 'Ad Format'
];
$group_by = isset($_GET['group_by']) && isset($group_by_options[$_GET['group_by']]) ? $_GET['group_by'] : 'report_date';

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $limit;

$report_data = [];
$totals = ['impressions' => 0, 'clicks' => 0, 'spend' => 0];
$total_groups = 0;
$total_pages = 0;
$advertiser_id = null;

$available_ad_formats = ['vast', 'popunder', 'banner'];

// Data untuk Format Breakdown chart
$format_data = [];
$format_labels = [];
$format_values = [];
$format_colors = [];
$format_color_map = [
    'vast' => 'rgba(78, 115, 223, 0.8)',
    'popunder' => 'rgba(54, 185, 204, 0.8)',
    'banner' => 'rgba(28, 200, 138, 0.8)',
    'unknown' => 'rgba(246, 194, 62, 0.8)'
];


try {
    $stmt_adv_id = $pdo->prepare("SELECT id FROM advertisers WHERE user_id = ?");
    $stmt_adv_id->execute([$advertiser_user_id]);
    $advertiser_id = $stmt_adv_id->fetchColumn();

    if ($advertiser_id) {
        $base_select = ""; $base_join = "FROM advertiser_daily_stats s"; $base_group_by = "";
        switch ($group_by) {
            case 'campaign_id': $base_select = "s.campaign_id, c.name as group_name"; $base_join .= " LEFT JOIN campaigns c ON s.campaign_id = c.id"; $base_group_by = "s.campaign_id, c.name"; break;
            case 'site_id': $base_select = "s.site_id, si.domain as group_name"; $base_join .= " LEFT JOIN sites si ON s.site_id = si.id"; $base_group_by = "s.site_id, si.domain"; break;
            case 'country_code': $base_select = "s.country_code as group_name"; $base_group_by = "s.country_code"; break;
            case 'device_type': $base_select = "s.device_type as group_name"; $base_group_by = "s.device_type"; break;
            case 'os': $base_select = "s.os as group_name"; $base_group_by = "s.os"; break;
            case 'browser': $base_select = "s.browser as group_name"; $base_group_by = "s.browser"; break;
            case 'ad_format': $base_select = "s.ad_format as group_name"; $base_group_by = "s.ad_format"; break;
            default: $base_select = "s.report_date as group_name"; $base_group_by = "s.report_date"; break;
        }

        $where_clause = "WHERE s.advertiser_id = :advertiser_id AND s.report_date BETWEEN :start_date AND :end_date";
        $params = [':advertiser_id' => $advertiser_id, ':start_date' => $start_date_str, ':end_date' => $end_date_str];
        
        if (!empty($ad_format_filter)) {
            $where_clause .= " AND s.ad_format = :ad_format";
            $params[':ad_format'] = $ad_format_filter;
        }

        $count_sql = "SELECT COUNT(*) FROM (SELECT {$base_select} {$base_join} {$where_clause} GROUP BY {$base_group_by}) AS subquery";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_groups = $count_stmt->fetchColumn();
        $total_pages = $total_groups > 0 ? ceil($total_groups / $limit) : 0;

        if ($total_groups > 0) {
            $data_sql = "SELECT {$base_select}, 
                                SUM(s.impressions) as impressions, 
                                SUM(s.clicks) as clicks, 
                                SUM(s.total_spend) as spend
                         {$base_join} {$where_clause} GROUP BY {$base_group_by} ORDER BY ";
                         
            if ($group_by === 'report_date') { $data_sql .= "group_name DESC"; } 
            else { $data_sql .= "spend DESC"; }
            
            $data_sql .= " LIMIT :limit OFFSET :offset";
            $data_stmt = $pdo->prepare($data_sql);
            $data_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $data_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            foreach ($params as $key => &$val) { $data_stmt->bindParam($key, $val); }
            $data_stmt->execute();
            $report_data = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $total_sql = "SELECT SUM(impressions) as total_impressions, 
                             SUM(clicks) as total_clicks, 
                             SUM(total_spend) as total_spend
                      FROM advertiser_daily_stats s {$where_clause}";
        $total_stmt = $pdo->prepare($total_sql);
        $total_stmt->execute($params);
        $totals_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
        if ($totals_result) {
            $totals['impressions'] = $totals_result['total_impressions'] ?? 0;
            $totals['clicks'] = $totals_result['total_clicks'] ?? 0;
            $totals['spend'] = $totals_result['total_spend'] ?? 0;
        }
        
        $format_query = "SELECT ad_format, SUM(impressions) as impressions FROM advertiser_daily_stats WHERE advertiser_id = :advertiser_id AND report_date BETWEEN :start_date AND :end_date GROUP BY ad_format";
        $format_stmt = $pdo->prepare($format_query);
        $format_stmt->execute([':advertiser_id' => $advertiser_id, ':start_date' => $start_date_str, ':end_date' => $end_date_str]);
        $format_rows = $format_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $temp_format_data = [];
        foreach ($format_rows as $row) {
            $format = strtolower($row['ad_format'] ?? 'unknown');
            $temp_format_data[$format] = (int)$row['impressions'];
        }

        foreach ($available_ad_formats as $format) {
            $impressions = $temp_format_data[$format] ?? 0;
            $format_data[$format] = $impressions;
            $format_labels[] = ucfirst($format);
            $format_values[] = $impressions;
            $format_colors[] = $format_color_map[$format] ?? $format_color_map['unknown'];
        }

    }
} catch (PDOException $e) { 
    echo "<div class='alert alert-danger'>Could not retrieve report data: " . $e->getMessage() . "</div>"; 
}

$overall_ctr = ($totals['impressions'] > 0) ? ($totals['clicks'] / $totals['impressions']) * 100 : 0;
$overall_avg_cpm = ($totals['impressions'] > 0) ? ($totals['spend'] / $totals['impressions']) * 1000 : 0;

$chart_labels = []; $chart_impressions = []; $chart_spend = [];
$chart_data_source = ($group_by === 'report_date') ? $report_data : array_reverse($report_data);
foreach ($chart_data_source as $row) {
    $label = $row['group_name'] ?? 'N/A';
    if ($group_by == 'report_date') { $label = date('M d', strtotime($label)); }
    $chart_labels[] = strlen($label) > 20 ? substr($label, 0, 18) . '...' : $label;
    $chart_impressions[] = $row['impressions'];
    $chart_spend[] = (float)$row['spend'];
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo e($page_title); ?></h1>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Filters</h6></div>
    <div class="card-body">
        <form method="GET" id="filterForm">
            <div class="row align-items-end">
                <div class="col-md-4 mb-3">
                    <label for="daterange" class="form-label">Date Range</label>
                    <input type="text" id="daterange" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="ad_format" class="form-label">Ad Format</label>
                    <select name="ad_format" id="ad_format" class="form-select">
                        <option value="">All Formats</option>
                        <?php foreach ($available_ad_formats as $format): ?>
                            <option value="<?php echo $format; ?>" <?php echo ($ad_format_filter == $format) ? 'selected' : ''; ?>><?php echo ucfirst($format); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="group_by" class="form-label">Group By</label>
                    <select name="group_by" id="group_by" class="form-select">
                        <?php foreach ($group_by_options as $key => $val): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($group_by == $key) ? 'selected' : ''; ?>><?php echo $val; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <a href="reports.php" class="btn btn-secondary w-100">Clear Filters</a>
                </div>
            </div>
            <input type="hidden" name="start" id="start_date" value="<?php echo e($start_date_str); ?>">
            <input type="hidden" name="end" id="end_date" value="<?php echo e($end_date_str); ?>">
        </form>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-primary shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Impressions</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totals['impressions']); ?></div></div><div class="col-auto"><i class="fas fa-eye fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-success shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Spend</div><div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($totals['spend'], 4); ?></div></div><div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-info shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-info text-uppercase mb-1">Average CPM</div><div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($overall_avg_cpm, 4); ?></div></div><div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card border-left-warning shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Clicks</div><div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totals['clicks']); ?></div></div><div class="col-auto"><i class="fas fa-mouse-pointer fa-2x text-gray-300"></i></div></div></div></div></div>
</div>

<div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Performance Chart</h6></div><div class="card-body"><div class="chart-area"><canvas id="reportChart"></canvas></div></div></div>

<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Performance Report</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead><tr><th><?php echo e($group_by_options[$group_by]); ?></th><th>Impressions</th><th>Clicks</th><th>CTR (%)</th><th>Spend (Est.)</th><th>Avg. CPM ($)</th></tr></thead>
                <tfoot><tr><th>Total</th><th><?php echo number_format($totals['impressions']); ?></th><th><?php echo number_format($totals['clicks']); ?></th><th><?php echo number_format($overall_ctr, 2); ?>%</th><th>$<?php echo number_format($totals['spend'], 4); ?></th><th>$<?php echo number_format($overall_avg_cpm, 4); ?></th></tr></tfoot>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr><td colspan="6" class="text-center">No data found for the selected filters.</td></tr>
                    <?php else: foreach ($report_data as $row): ?>
                        <tr>
                            <td>
                                <?php 
                                $display_value = $row['group_name'] ?? 'N/A';
                                if ($group_by == 'report_date') { $display_value = date('M d, Y', strtotime($display_value)); } 
                                else if ($group_by == 'ad_format') {
                                    $display_value = ucfirst($display_value);
                                    $icon_map = ['vast' => 'fa-video text-primary', 'popunder' => 'fa-window-restore text-info', 'banner' => 'fa-image text-success'];
                                    $icon = $icon_map[strtolower($display_value)] ?? 'fa-ad';
                                    $display_value = '<i class="fas ' . $icon . ' mr-1"></i> ' . $display_value;
                                }
                                echo $display_value;
                                ?>
                            </td>
                            <td><?php echo number_format($row['impressions']); ?></td>
                            <td><?php echo number_format($row['clicks']); ?></td>
                            <td><?php $ctr = ($row['impressions'] > 0) ? ($row['clicks'] / $row['impressions']) * 100 : 0; echo number_format($ctr, 2); ?>%</td>
                            <td>$<?php echo number_format($row['spend'], 4); ?></td>
                            <td><?php $avg_cpm = ($row['impressions'] > 0) ? ($row['spend'] / $row['impressions']) * 1000 : 0; echo '$' . number_format($avg_cpm, 4); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <?php if($total_groups > 0): ?>
                    <small class="text-muted">Showing <?php echo $offset + 1; ?> to <?php echo $offset + count($report_data); ?> of <?php echo $total_groups; ?> results</small>
                <?php endif; ?>
            </div>
            <?php if($total_pages > 1): ?>
                <nav>
                    <ul class="pagination mb-0">
                        <?php $query_params = $_GET; unset($query_params['page']); $base_url = '?' . http_build_query($query_params); ?>
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $base_url . '&page=' . ($page - 1); ?>">Previous</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#">Page <?php echo $page; ?> of <?php echo $total_pages; ?></a></li>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $base_url . '&page=' . ($page + 1); ?>">Next</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($group_by != 'ad_format'): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Format Breakdown</h6></div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-8"><div style="height:300px;"><canvas id="formatBarChart" width="100%" height="300"></canvas></div></div>
            <div class="col-lg-4">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>Format</th><th>Impressions</th><th>%</th></tr></thead>
                        <tbody>
                            <?php 
                            $total_impressions_format = array_sum($format_values);
                            foreach ($format_data as $format => $impressions): 
                                $percentage = $total_impressions_format > 0 ? ($impressions / $total_impressions_format) * 100 : 0;
                                $icon_map = ['vast' => 'fa-video text-primary', 'popunder' => 'fa-window-restore text-info', 'banner' => 'fa-image text-success'];
                                $icon = $icon_map[$format] ?? 'fa-ad text-secondary';
                            ?>
                            <tr>
                                <td><span class="text-nowrap"><i class="fas <?php echo $icon; ?> mr-1"></i> <?php echo ucfirst($format); ?></span></td>
                                <td><?php echo number_format($impressions); ?></td>
                                <td><?php echo number_format($percentage, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr class="font-weight-bold"><td>Total</td><td><?php echo number_format($total_impressions_format); ?></td><td>100%</td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'template/footer.php'; ?>

<script>
$(function() {
    var start = moment($('#start_date').val()); var end = moment($('#end_date').val());
    function cb(start, end) { $('#daterange').val(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY')); }
    $('#daterange').daterangepicker({ startDate: start, endDate: end, ranges: {'Today': [moment(), moment()], 'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')], 'Last 7 Days': [moment().subtract(6, 'days'), moment()], 'This Month': [moment().startOf('month'), moment().endOf('month')]} }, cb);
    cb(start, end);
    $('#daterange').on('apply.daterangepicker', function(ev, picker) { $('#start_date').val(picker.startDate.format('YYYY-MM-DD')); $('#end_date').val(picker.endDate.format('YYYY-MM-DD')); $('#filterForm').submit(); });
    $('#group_by, #ad_format').on('change', function() { $('#filterForm').submit(); });
    
    var ctx = document.getElementById("reportChart");
    new Chart(ctx, { type: 'line', data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [ { label: "Impressions", yAxisID: 'A', borderColor: "rgba(78, 115, 223, 1)", data: <?php echo json_encode($chart_impressions); ?> }, { label: "Spend ($)", yAxisID: 'B', borderColor: "rgba(246, 194, 62, 1)", backgroundColor: "rgba(246, 194, 62, 0.05)", data: <?php echo json_encode($chart_spend); ?> } ] }, options: { maintainAspectRatio: false, scales: { yAxes: [ { id: 'A', type: 'linear', position: 'left', ticks: { beginAtZero: true, callback: function(v) { if(v % 1 === 0) { return new Intl.NumberFormat().format(v); } } }, scaleLabel: { display: true, labelString: 'Impressions' } }, { id: 'B', type: 'linear', position: 'right', gridLines: { drawOnChartArea: false }, ticks: { beginAtZero: true, callback: function(v) { return '$' + v.toFixed(4); } }, scaleLabel: { display: true, labelString: 'Spend ($)' } } ] }, legend: { display: true } } });

    var formatCtx = document.getElementById('formatBarChart');
    if (formatCtx) { new Chart(formatCtx, { type: 'bar', data: { labels: <?php echo json_encode($format_labels); ?>, datasets: [{ label: 'Impressions', data: <?php echo json_encode($format_values); ?>, backgroundColor: <?php echo json_encode($format_colors); ?>, borderColor: <?php echo json_encode($format_colors); ?>, borderWidth: 1 }] }, options: { maintainAspectRatio: false, responsive: true, legend: { display: false }, scales: { yAxes: [{ ticks: { beginAtZero: true, callback: function(value) { if (value % 1 === 0) { return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); } } } }], xAxes: [{ gridLines: { display: false } }] }, tooltips: { callbacks: { label: function(tooltipItem, data) { var value = data.datasets[0].data[tooltipItem.index]; var total = data.datasets[0].data.reduce(function(a, b) { return a + b; }, 0); var percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%'; return data.labels[tooltipItem.index] + ': ' + value.toLocaleString() + ' (' + percentage + ')'; } } } } }); }
});
</script>