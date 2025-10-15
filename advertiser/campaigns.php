<?php
$page_title = 'My Campaigns';
include 'template/header.php';
require_once '../includes/db_connection.php';

// Generate CSRF token untuk keamanan operasi delete
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$pdo = get_db_connection();
$advertiser_user_id = $_SESSION['user_id'];
$campaigns_data = [];

// Retrieve filter values
$start_date = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$format_filter = $_GET['format'] ?? '';

// Process bulk action if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['campaign_ids'])) {
    $action = $_POST['bulk_action'];
    $campaign_ids = $_POST['campaign_ids'];
    $new_status = '';
    
    if ($action === 'activate') {
        $new_status = 'active';
    } elseif ($action === 'pause') {
        $new_status = 'paused';
    }
    
    if ($new_status && is_array($campaign_ids) && !empty($campaign_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($campaign_ids), '?'));
            
            $stmt_verify = $pdo->prepare("SELECT c.id FROM campaigns c JOIN advertisers a ON c.advertiser_id = a.id WHERE c.id IN ($placeholders) AND a.user_id = ?");
            
            $verify_params = array_merge($campaign_ids, [$advertiser_user_id]);
            $stmt_verify->execute($verify_params);
            $verified_campaigns = $stmt_verify->fetchAll(PDO::FETCH_COLUMN, 0);
            
            if (!empty($verified_campaigns)) {
                $verified_placeholders = implode(',', array_fill(0, count($verified_campaigns), '?'));
                
                $update_sql = "UPDATE campaigns SET status = ? WHERE id IN ($verified_placeholders)";
                $stmt_update = $pdo->prepare($update_sql);
                $update_params = array_merge([$new_status], $verified_campaigns);
                $stmt_update->execute($update_params);
                
                $message = "Selected campaigns have been " . ($action === 'activate' ? 'activated' : 'paused') . " successfully.";
                echo '<div class="alert alert-success">' . $message . '</div>';
            } else {
                echo '<div class="alert alert-danger">You do not have permission to modify these campaigns.</div>';
            }
        } catch (Exception $e) {
            error_log("Bulk action error: " . $e->getMessage());
            echo '<div class="alert alert-danger">An error occurred while processing your request.</div>';
        }
    }
}

try {
    $stmt_adv_id = $pdo->prepare("SELECT id FROM advertisers WHERE user_id = ?");
    $stmt_adv_id->execute([$advertiser_user_id]);
    $advertiser_id = $stmt_adv_id->fetchColumn();

    if ($advertiser_id) {
        $sql = "SELECT c.id, c.name, c.campaign_type, c.ad_format, c.status, c.priority, c.cpm_rate,
                    COALESCE(s.total_impressions, 0) as total_impressions,
                    COALESCE(s.total_clicks, 0) as total_clicks,
                    COALESCE(s.total_spend, 0) as total_spend
                FROM campaigns c
                LEFT JOIN (
                    SELECT campaign_id, SUM(impressions) as total_impressions, SUM(clicks) as total_clicks, SUM(total_spend) as total_spend
                    FROM advertiser_daily_stats
                    WHERE advertiser_id = ? AND report_date BETWEEN ? AND ?
                    GROUP BY campaign_id
                ) s ON c.id = s.campaign_id
                WHERE c.advertiser_id = ?";
        
        $params = [$advertiser_id, $start_date, $end_date, $advertiser_id];
        
        if (!empty($status_filter)) { $sql .= " AND c.status = ?"; $params[] = $status_filter; }
        if (!empty($format_filter)) { $sql .= " AND c.ad_format = ?"; $params[] = $format_filter; }
        
        $sql .= " ORDER BY c.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Advertiser campaigns page error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Could not retrieve campaign data.</div>";
}

// Success & Error Messages
if (isset($_GET['success'])) { /* ... kode pesan sukses ... */ }
if (isset($_GET['error'])) { /* ... kode pesan error ... */ }
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo e($page_title); ?></h1>
    <a href="campaign_add.php" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-plus fa-sm text-white-50"></i> Create New Campaign</a>
</div>

<!-- Filter Card -->
<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Filter Campaigns</h6></div>
    <div class="card-body">
        <form method="GET" id="filterForm">
            <div class="row">
                <div class="col-md-4 mb-3"><label for="daterange">Date Range for Statistics</label><input type="text" id="daterange" class="form-control"><input type="hidden" id="start_date" name="start" value="<?php echo $start_date; ?>"><input type="hidden" id="end_date" name="end" value="<?php echo $end_date; ?>"></div>
                <div class="col-md-3 mb-3"><label for="status">Campaign Status</label><select name="status" id="status" class="form-select"><option value="">All Statuses</option><option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option><option value="paused" <?php echo $status_filter === 'paused' ? 'selected' : ''; ?>>Paused</option><option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option></select></div>
                <div class="col-md-3 mb-3"><label for="format">Ad Format</label><select name="format" id="format" class="form-select"><option value="">All Formats</option><option value="vast" <?php echo $format_filter === 'vast' ? 'selected' : ''; ?>>VAST Video</option><option value="popunder" <?php echo $format_filter === 'popunder' ? 'selected' : ''; ?>>Popunder</option><option value="banner" <?php echo $format_filter === 'banner' ? 'selected' : ''; ?>>Banner</option></select></div>
                <div class="col-md-2 mb-3 d-flex align-items-end"><div class="w-100"><button type="submit" class="btn btn-primary w-100">Apply Filters</button></div></div>
            </div>
        </form>
    </div>
</div>

<!-- Campaign List with Bulk Actions -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center"><h6 class="m-0 font-weight-bold text-primary">Campaign List & Performance</h6></div>
    <div class="card-body">
        <form method="POST" id="bulkActionsForm">
            <div class="mb-3 d-flex align-items-center">
                <div class="form-check me-3"><input type="checkbox" class="form-check-input" id="selectAll"><label class="form-check-label" for="selectAll">Select All</label></div>
                <div class="input-group" style="width: auto;"><select name="bulk_action" class="form-select" id="bulkActionSelect"><option value="">-- Select Action --</option><option value="activate">Activate Selected</option><option value="pause">Pause Selected</option></select><button type="submit" class="btn btn-primary" id="applyBulkAction" disabled>Apply</button></div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th width="30px"><input type="checkbox" class="checkbox-all"></th>
                            <th>Campaign</th><th>Format</th><th>Type</th><th>Status</th><th>Rate / Priority</th>
                            <th>Impressions</th><th>Clicks</th><th>Spend (Est.)</th><th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($campaigns_data)): ?>
                            <tr><td colspan="10" class="text-center">No campaigns found matching your filters.</td></tr>
                        <?php else: foreach ($campaigns_data as $campaign): ?>
                            <tr>
                                <td><input type="checkbox" name="campaign_ids[]" value="<?php echo $campaign['id']; ?>" class="campaign-checkbox"></td>
                                <td><?php echo e($campaign['name']); ?></td>
                                <td><?php switch ($campaign['ad_format']) { case 'vast': echo '<span class="badge bg-primary"><i class="fas fa-video me-1"></i> VAST</span>'; break; case 'popunder': echo '<span class="badge bg-info text-dark"><i class="fas fa-window-restore me-1"></i> Popunder</span>'; break; case 'banner': echo '<span class="badge bg-success"><i class="fas fa-image me-1"></i> Banner</span>'; break; default: echo '<span class="badge bg-secondary">Unknown</span>'; } ?></td>
                                <td><?php echo ($campaign['campaign_type'] == 'internal') ? '<span class="badge bg-secondary">Internal</span>' : '<span class="badge bg-dark">External RTB</span>'; ?></td>
                                <td><?php $status = $campaign['status']; $badge_class = 'bg-secondary'; if ($status == 'active') $badge_class = 'bg-success'; if ($status == 'paused') $badge_class = 'bg-warning text-dark'; if ($status == 'pending') $badge_class = 'bg-primary'; if ($status == 'rejected') $badge_class = 'bg-danger'; ?><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span></td>
                                <td><?php if ($campaign['campaign_type'] == 'internal'): ?><strong>$<?php echo number_format($campaign['cpm_rate'], 4); ?></strong> <span class="badge bg-light text-dark">CPM</span><?php else: ?><span class="badge bg-secondary">Dynamic Bid</span><?php endif; ?><br><small class="text-muted">Priority: <?php echo e($campaign['priority']); ?></small></td>
                                <td><?php echo number_format($campaign['total_impressions']); ?></td>
                                <td><?php echo number_format($campaign['total_clicks']); ?></td>
                                <td>$<?php echo number_format($campaign['total_spend'], 4); ?></td>
                                <td class="text-center">
                                    <?php if ($campaign['status'] == 'active'): ?>
                                        <a href="campaign_status.php?id=<?php echo $campaign['id']; ?>&action=pause" class="btn btn-warning btn-sm" title="Pause"><i class="fas fa-pause"></i></a>
                                    <?php elseif ($campaign['status'] == 'paused' || $campaign['status'] == 'rejected'): ?>
                                        <a href="campaign_status.php?id=<?php echo $campaign['id']; ?>&action=activate" class="btn btn-success btn-sm" title="Activate"><i class="fas fa-play"></i></a>
                                    <?php endif; ?>
                                    
                                    <!-- ============================================================ -->
                                    <!-- PERUBAHAN DI SINI: Tombol Edit hanya muncul jika status BUKAN pending -->
                                    <!-- ============================================================ -->
                                    <?php if ($campaign['status'] != 'pending'): ?>
                                        <a href="campaign_edit.php?id=<?php echo $campaign['id']; ?>" class="btn btn-info btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                    
                                    <a href="campaign_clone.php?id=<?php echo $campaign['id']; ?>" class="btn btn-primary btn-sm" title="Clone Campaign" onclick="return confirm('Do you want to clone this campaign?');"><i class="fas fa-copy"></i></a>
                                    <a href="campaign_delete_alt.php?id=<?php echo $campaign['id']; ?>&token=<?php echo $csrf_token; ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Are you sure you want to delete campaign \'<?php echo htmlspecialchars(addslashes($campaign['name'])); ?>\'?\nThis action cannot be undone.');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<?php include 'template/footer.php'; ?>

<script>
$(function() {
    // Initialize date range picker
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
    
    // Apply daterange changes to form
    $('#daterange').on('apply.daterangepicker', function(ev, picker) {
        $('#start_date').val(picker.startDate.format('YYYY-MM-DD'));
        $('#end_date').val(picker.endDate.format('YYYY-MM-DD'));
    });
    
    // Bulk Selection and Actions
    $('#selectAll, .checkbox-all').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.campaign-checkbox').prop('checked', isChecked);
        
        // Sync other select all checkbox
        $('#selectAll, .checkbox-all').prop('checked', isChecked);
        
        updateBulkActionStatus();
    });
    
    $('.campaign-checkbox').on('change', function() {
        updateBulkActionStatus();
        
        // Check if all checkboxes are checked
        const allChecked = $('.campaign-checkbox:checked').length === $('.campaign-checkbox').length && $('.campaign-checkbox').length > 0;
        $('#selectAll, .checkbox-all').prop('checked', allChecked);
    });
    
    function updateBulkActionStatus() {
        const selectedCount = $('.campaign-checkbox:checked').length;
        const actionButton = $('#applyBulkAction');
        
        if (selectedCount > 0) {
            actionButton.prop('disabled', false);
            actionButton.text(`Apply (${selectedCount})`);
        } else {
            actionButton.prop('disabled', true);
            actionButton.text('Apply');
        }
    }
    
    // Form validation
    $('#bulkActionsForm').on('submit', function(e) {
        const action = $('#bulkActionSelect').val();
        const checkedBoxes = $('.campaign-checkbox:checked');
        
        if (!action) {
            e.preventDefault();
            alert('Please select an action to perform.');
            return false;
        }
        
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Please select at least one campaign.');
            return false;
        }
        
        // Confirm action
        if (!confirm(`Are you sure you want to ${action} the selected campaigns?`)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Initialize on page load
    updateBulkActionStatus();
});
</script>