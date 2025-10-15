<?php
$page_title = 'Campaign Management';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();
$campaigns = [];
$advertisers = [];

// Ambil nilai filter dari URL, jika ada
$advertiser_filter = $_GET['advertiser_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$format_filter = $_GET['format'] ?? '';
$name_filter = $_GET['name'] ?? '';

try {
    // 1. Ambil daftar semua advertiser untuk dropdown filter
    $advertisers = $pdo->query("SELECT id, company_name FROM advertisers ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Bangun query dinamis berdasarkan filter yang dipilih
    $sql = "SELECT 
                c.id, c.name, c.campaign_type, c.ad_format, c.status, c.priority, c.cpm_rate, c.price_model,
                a.company_name as advertiser_name
            FROM campaigns c
            JOIN advertisers a ON c.advertiser_id = a.id
            WHERE 1=1"; // Kondisi awal untuk memudahkan penambahan filter

    $params = [];

    if (!empty($advertiser_filter)) {
        $sql .= " AND c.advertiser_id = ?";
        $params[] = $advertiser_filter;
    }
    if (!empty($status_filter)) {
        $sql .= " AND c.status = ?";
        $params[] = $status_filter;
    }
    if (!empty($format_filter)) {
        $sql .= " AND c.ad_format = ?";
        $params[] = $format_filter;
    }
    if (!empty($name_filter)) {
        $sql .= " AND c.name LIKE ?";
        $params[] = '%' . $name_filter . '%';
    }

    // Urutkan berdasarkan status 'pending' terlebih dahulu
    $sql .= " ORDER BY FIELD(c.status, 'pending', 'active', 'paused', 'rejected', 'completed', 'archived'), c.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Admin Campaign page error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Could not retrieve data. Please contact support.</div>";
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <a href="campaign_add.php" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-plus fa-sm text-white-50"></i> Add New Campaign</a>
</div>

<!-- ============================================================ -->
<!-- KARTU FILTER BARU -->
<!-- ============================================================ -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Campaigns</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="campaigns.php">
            <div class="row align-items-end">
                <div class="col-md-3 mb-3">
                    <label for="name" class="form-label">Campaign Name</label>
                    <input type="text" name="name" id="name" class="form-control" value="<?php echo e($name_filter); ?>" placeholder="Search by name...">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="advertiser_id" class="form-label">Advertiser</label>
                    <select name="advertiser_id" id="advertiser_id" class="form-select">
                        <option value="">All Advertisers</option>
                        <?php foreach ($advertisers as $advertiser): ?>
                            <option value="<?php echo $advertiser['id']; ?>" <?php echo ($advertiser_filter == $advertiser['id']) ? 'selected' : ''; ?>>
                                <?php echo e($advertiser['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="paused" <?php echo ($status_filter == 'paused') ? 'selected' : ''; ?>>Paused</option>
                        <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="format" class="form-label">Ad Format</label>
                    <select name="format" id="format" class="form-select">
                        <option value="">All Formats</option>
                        <option value="vast" <?php echo ($format_filter == 'vast') ? 'selected' : ''; ?>>VAST</option>
                        <option value="banner" <?php echo ($format_filter == 'banner') ? 'selected' : ''; ?>>Banner</option>
                        <option value="popunder" <?php echo ($format_filter == 'popunder') ? 'selected' : ''; ?>>Popunder</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                </div>
            </div>
        </form>
    </div>
</div>


<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Campaign List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Campaign Name</th>
                        <th>Advertiser</th>
                        <th>Type / Format</th>
                        <th>Status</th>
                        <th>Rate / Model</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr><td colspan="6" class="text-center">No campaigns found matching your criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($campaigns as $campaign): ?>
                            <tr class="<?php if($campaign['status'] == 'pending') echo 'table-warning'; ?>">
                                <td><?php echo e($campaign['name']); ?></td>
                                <td><?php echo e($campaign['advertiser_name']); ?></td>
                                <td>
                                    <?php if ($campaign['campaign_type'] == 'internal'): ?><span class="badge bg-info text-dark">Internal</span><?php else: ?><span class="badge bg-primary">External (RTB)</span><?php endif; ?>
                                    <span class="badge bg-secondary"><?php echo ucfirst($campaign['ad_format'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <?php
                                        $status = $campaign['status'];
                                        $badge_class = 'bg-secondary';
                                        if ($status == 'active') $badge_class = 'bg-success';
                                        if ($status == 'paused') $badge_class = 'bg-warning text-dark';
                                        if ($status == 'pending') $badge_class = 'bg-primary';
                                        if ($status == 'rejected') $badge_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                </td>
                                <td>
                                    <?php if ($campaign['campaign_type'] == 'internal'): ?>
                                        <strong>$<?php echo number_format($campaign['cpm_rate'], 4); ?></strong>
                                        <span class="badge bg-light text-dark"><?php echo e($campaign['price_model']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Dynamic Bid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($campaign['status'] == 'pending'): ?>
                                        <a href="campaign_status.php?id=<?php echo $campaign['id']; ?>&action=approve" class="btn btn-success btn-sm" title="Approve"><i class="fas fa-check"></i></a>
                                        <a href="campaign_status.php?id=<?php echo $campaign['id']; ?>&action=reject" class="btn btn-danger btn-sm" title="Reject"><i class="fas fa-times"></i></a>
                                    <?php elseif ($campaign['status'] == 'active'): ?>
                                        <a href="campaign_status.php?id=<?php echo $campaign['id']; ?>&action=pause" class="btn btn-warning btn-sm" title="Pause"><i class="fas fa-pause"></i></a>
                                    <?php elseif ($campaign['status'] == 'paused' || $campaign['status'] == 'rejected'): ?>
                                        <a href="campaign_status.php?id=<?php echo $campaign['id']; ?>&action=activate" class="btn btn-success btn-sm" title="Activate"><i class="fas fa-play"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if ($campaign['status'] != 'pending'): ?>
                                        <a href="campaign_edit.php?id=<?php echo $campaign['id']; ?>" class="btn btn-info btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="campaign_delete.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to permanently delete this campaign?');">
                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include 'template/footer.php';
?>