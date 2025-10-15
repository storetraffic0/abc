<?php
$page_title = 'Admin Reports';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();

// --- PENGATURAN FILTER ---
$start_date = $_GET['start'] ?? date('Y-m-d', strtotime('-6 days'));
$end_date = $_GET['end'] ?? date('Y-m-d');
$publisher_id_filter = !empty($_GET['publisher_id']) ? (int)$_GET['publisher_id'] : null;
$advertiser_id_filter = !empty($_GET['advertiser_id']) ? (int)$_GET['advertiser_id'] : null;
$ad_format_filter = !empty($_GET['ad_format']) ? $_GET['ad_format'] : null;
$group_by_options = [
    'report_date'=>'Date', 
    'campaign_id'=>'Campaign', 
    'publisher_id'=>'Publisher', 
    'advertiser_id'=>'Advertiser', 
    'site_id'=>'Site', 
    'country_code'=>'Country',
    'ad_format'=>'Ad Format'
];
$group_by = isset($_GET['group_by']) && isset($group_by_options[$_GET['group_by']]) ? $_GET['group_by'] : 'report_date';

// --- AMBIL DATA UNTUK DROPDOWN FILTER ---
$publishers_list = $pdo->query("SELECT id, company_name FROM publishers ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
$advertisers_list = $pdo->query("SELECT id, company_name FROM advertisers ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
$ad_formats = $pdo->query("SELECT DISTINCT ad_format FROM advertiser_daily_stats UNION SELECT DISTINCT ad_format FROM publisher_daily_stats")->fetchAll(PDO::FETCH_COLUMN);

// --- MEMBANGUN QUERY DARI TABEL RINGKASAN ---
$report_data = [];
$totals = ['impressions' => 0, 'clicks' => 0, 'revenue' => 0, 'publisher_payout' => 0, 'profit' => 0];

try {
    // 1. Buat Common Table Expression (CTE) untuk menggabungkan data
    $base_query = "
        WITH combined_stats AS (
            SELECT report_date, advertiser_id, campaign_id, site_id, country_code, ad_format, 
                   impressions, clicks, total_spend as revenue, 0 as publisher_payout
            FROM advertiser_daily_stats
            UNION ALL
            SELECT report_date, NULL as advertiser_id, campaign_id, site_id, country_code, ad_format, 
                   0 as impressions, 0 as clicks, 0 as revenue, total_revenue as publisher_payout
            FROM publisher_daily_stats
        )
        SELECT ";

    // 2. Tentukan field SELECT dan GROUP BY secara dinamis
    $select_fields = [
        'SUM(s.impressions) as impressions',
        'SUM(s.clicks) as clicks',
        'SUM(s.revenue) as revenue',
        'SUM(s.publisher_payout) as publisher_payout',
        'SUM(s.revenue) - SUM(s.publisher_payout) as profit'
    ];
    $group_by_fields = [];
    $joins = " FROM combined_stats s ";

    switch ($group_by) {
        case 'campaign_id': 
            $select_fields[] = 'c.name as group_name'; 
            $group_by_fields[] = 's.campaign_id, c.name'; 
            $joins .= " LEFT JOIN campaigns c ON s.campaign_id = c.id"; 
            break;
        case 'advertiser_id': 
            $select_fields[] = 'a.company_name as group_name'; 
            $group_by_fields[] = 's.advertiser_id, a.company_name'; 
            $joins .= " LEFT JOIN advertisers a ON s.advertiser_id = a.id"; 
            break;
        case 'publisher_id': 
            // Perlu join dari site_id -> publisher
            $select_fields[] = 'p.company_name as group_name'; 
            $group_by_fields[] = 'p.id, p.company_name'; 
            $joins .= " LEFT JOIN sites si ON s.site_id = si.id LEFT JOIN publishers p ON si.publisher_id = p.id"; 
            break;
        case 'site_id': 
            $select_fields[] = 'si.domain as group_name'; 
            $group_by_fields[] = 's.site_id, si.domain'; 
            $joins .= " LEFT JOIN sites si ON s.site_id = si.id"; 
            break;
        case 'country_code': 
            $select_fields[] = 's.country_code as group_name'; 
            $group_by_fields[] = 's.country_code'; 
            break;
        case 'ad_format': 
            $select_fields[] = 's.ad_format as group_name'; 
            $group_by_fields[] = 's.ad_format'; 
            break;
        default: 
            $select_fields[] = 's.report_date as group_name'; 
            $group_by_fields[] = 's.report_date'; 
            break;
    }
    
    // 3. Bangun klausa WHERE secara dinamis
    $where_conditions = ["s.report_date BETWEEN :start_date AND :end_date"];
    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if ($advertiser_id_filter) {
        $where_conditions[] = "s.advertiser_id = :advertiser_id";
        $params[':advertiser_id'] = $advertiser_id_filter;
    }
    
    if ($publisher_id_filter) {
        // Jika filter publisher ada, kita harus memastikan join ke publisher dilakukan
        if (strpos($joins, 'publishers p') === false) {
             $joins .= " LEFT JOIN sites si ON s.site_id = si.id LEFT JOIN publishers p ON si.publisher_id = p.id";
        }
        $where_conditions[] = "p.id = :publisher_id";
        $params[':publisher_id'] = $publisher_id_filter;
    }
    
    if ($ad_format_filter) {
        $where_conditions[] = "s.ad_format = :ad_format";
        $params[':ad_format'] = $ad_format_filter;
    }
    
    // 4. Gabungkan semua bagian query
    $final_sql = $base_query . implode(', ', $select_fields) . $joins . 
                " WHERE " . implode(' AND ', $where_conditions) . 
                " GROUP BY " . implode(', ', $group_by_fields) . 
                " ORDER BY " . ($group_by === 'report_date' ? 'group_name DESC' : 'revenue DESC');

    $stmt = $pdo->prepare($final_sql);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Hitung total
    $totals_sql = "
        SELECT 
            SUM(impressions) as impressions,
            SUM(clicks) as clicks,
            SUM(revenue) as revenue,
            SUM(publisher_payout) as publisher_payout,
            SUM(revenue - publisher_payout) as profit
        FROM (
            SELECT 
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(total_spend) as revenue,
                0 as publisher_payout
            FROM advertiser_daily_stats
            WHERE report_date BETWEEN :start_date AND :end_date
            " . ($advertiser_id_filter ? "AND advertiser_id = :adv_id" : "") . "
            " . ($ad_format_filter ? "AND ad_format = :ad_fmt1" : "") . "
            UNION ALL
            SELECT 
                0 as impressions,
                0 as clicks,
                0 as revenue,
                SUM(total_revenue) as publisher_payout
            FROM publisher_daily_stats
            WHERE report_date BETWEEN :start_date2 AND :end_date2
            " . ($publisher_id_filter ? "AND publisher_id = :pub_id" : "") . "
            " . ($ad_format_filter ? "AND ad_format = :ad_fmt2" : "") . "
        ) as combined_totals";
    
    $totals_params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':start_date2' => $start_date,
        ':end_date2' => $end_date
    ];
    
    if ($advertiser_id_filter) {
        $totals_params[':adv_id'] = $advertiser_id_filter;
    }
    if ($publisher_id_filter) {
        $totals_params[':pub_id'] = $publisher_id_filter;
    }
    if ($ad_format_filter) {
        $totals_params[':ad_fmt1'] = $ad_format_filter;
        $totals_params[':ad_fmt2'] = $ad_format_filter;
    }
    
    $totals_stmt = $pdo->prepare($totals_sql);
    $totals_stmt->execute($totals_params);
    $totals = $totals_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Could not retrieve report data: " . $e->getMessage() . "</div>";
}
?>

<!-- Header Section -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo e($page_title); ?></h1>
    <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" id="downloadReport">
        <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
    </a>
</div>

<!-- Filter Card -->
<div class="card shadow mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">Report Filters</h6>
    </div>
    <div class="card-body">
        <form method="GET" id="filterForm">
            <div class="row align-items-end">
                <div class="col-lg-3 col-md-6 mb-3">
                    <label for="daterange" class="form-label">Date Range</label>
                    <input type="text" id="daterange" class="form-control">
                </div>
                <div class="col-lg-2 col-md-6 mb-3">
                    <label for="publisher_id" class="form-label">Publisher</label>
                    <select name="publisher_id" id="publisher_id" class="form-select">
                        <option value="">All Publishers</option>
                        <?php foreach ($publishers_list as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($publisher_id_filter==$p['id']) ? 'selected' : ''; ?>>
                                <?php echo e($p['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 mb-3">
                    <label for="advertiser_id" class="form-label">Advertiser</label>
                    <select name="advertiser_id" id="advertiser_id" class="form-select">
                        <option value="">All Advertisers</option>
                        <?php foreach ($advertisers_list as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo ($advertiser_id_filter==$a['id']) ? 'selected' : ''; ?>>
                                <?php echo e($a['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 mb-3">
                    <label for="ad_format" class="form-label">Ad Format</label>
                    <select name="ad_format" id="ad_format" class="form-select">
                        <option value="">All Formats</option>
                        <?php foreach ($ad_formats as $format): ?>
                            <option value="<?php echo $format; ?>" <?php echo ($ad_format_filter==$format) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($format); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 mb-3">
                    <label for="group_by" class="form-label">Group By</label>
                    <select name="group_by" id="group_by" class="form-select">
                        <?php foreach ($group_by_options as $key => $val): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($group_by==$key) ? 'selected' : ''; ?>>
                                <?php echo $val; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-1 col-md-6 mb-3">
                    <a href="reports.php" class="btn btn-secondary w-100">Clear</a>
                </div>
            </div>
            <input type="hidden" name="start" id="start_date" value="<?php echo e($start_date); ?>">
            <input type="hidden" name="end" id="end_date" value="<?php echo e($end_date); ?>">
        </form>
    </div>
</div>

<!-- Summary Cards Row -->
<div class="row">
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Impressions</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totals['impressions']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-eye fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Clicks</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totals['clicks']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-mouse-pointer fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Revenue</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($totals['revenue'], 4); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Publisher Payout</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($totals['publisher_payout'], 4); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Net Profit</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($totals['profit'], 4); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Performance Report</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="reportTable" width="100%">
                <thead>
                    <tr>
                        <th><?php echo e($group_by_options[$group_by]); ?></th>
                        <th>Impressions</th>
                        <th>Clicks</th>
                        <th>CTR (%)</th>
                        <th>Revenue ($)</th>
                        <th>Payout ($)</th>
                        <th>Profit ($)</th>
                        <th>Margin (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr><td colspan="8" class="text-center">No data found for the selected filters.</td></tr>
                    <?php else: foreach ($report_data as $row): ?>
                        <?php 
                            $ctr = ($row['impressions'] > 0) ? ($row['clicks'] / $row['impressions']) * 100 : 0;
                            $margin = ($row['revenue'] > 0) ? ($row['profit'] / $row['revenue']) * 100 : 0;
                            $display_name = $row['group_name'] ?? 'N/A';
                            
                            // Format display name berdasarkan tipe pengelompokan
                            if ($group_by === 'report_date') {
                                $display_name = date('M d, Y', strtotime($display_name));
                            } elseif ($group_by === 'ad_format') {
                                $display_name = ucfirst($display_name);
                            }
                        ?>
                        <tr>
                            <td><?php echo e($display_name); ?></td>
                            <td><?php echo number_format($row['impressions']); ?></td>
                            <td><?php echo number_format($row['clicks']); ?></td>
                            <td><?php echo number_format($ctr, 2); ?>%</td>
                            <td>$<?php echo number_format($row['revenue'], 4); ?></td>
                            <td>$<?php echo number_format($row['publisher_payout'], 4); ?></td>
                            <td>$<?php echo number_format($row['profit'], 4); ?></td>
                            <td><?php echo number_format($margin, 2); ?>%</td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold bg-light">
                        <td>Total</td>
                        <td><?php echo number_format($totals['impressions']); ?></td>
                        <td><?php echo number_format($totals['clicks']); ?></td>
                        <td><?php echo number_format(($totals['impressions'] > 0) ? ($totals['clicks'] / $totals['impressions']) * 100 : 0, 2); ?>%</td>
                        <td>$<?php echo number_format($totals['revenue'], 4); ?></td>
                        <td>$<?php echo number_format($totals['publisher_payout'], 4); ?></td>
                        <td>$<?php echo number_format($totals['profit'], 4); ?></td>
                        <td><?php echo number_format(($totals['revenue'] > 0) ? ($totals['profit'] / $totals['revenue']) * 100 : 0, 2); ?>%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include 'template/footer.php'; ?>

<script>
$(function() {
    // Date range picker initialization
    var start = moment($('#start_date').val());
    var end = moment($('#end_date').val());
    
    function cb(start, end) {
        $('#daterange').val(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
        $('#start_date').val(start.format('YYYY-MM-DD'));
        $('#end_date').val(end.format('YYYY-MM-DD'));
    }
    
    $('#daterange').daterangepicker({
        startDate: start,
        endDate: end,
        ranges: {
           'Today': [moment(), moment()],
           'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
           'Last 7 Days': [moment().subtract(6, 'days'), moment()],
           'Last 30 Days': [moment().subtract(29, 'days'), moment()],
           'This Month': [moment().startOf('month'), moment().endOf('month')],
           'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, cb);
    
    cb(start, end);
    
    // Form submission on change
    $('#daterange').on('apply.daterangepicker', function() {
        $('#filterForm').submit();
    });
    
    $('#publisher_id, #advertiser_id, #ad_format, #group_by').on('change', function() {
        $('#filterForm').submit();
    });
    
    // DataTables initialization
    $('#reportTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf'
        ],
        pageLength: 25,
        ordering: true,
        searching: true,
        info: true,
        responsive: true
    });
    
    // Download report button
    $('#downloadReport').on('click', function(e) {
        e.preventDefault();
        $('.buttons-excel').click();
    });
});
</script>