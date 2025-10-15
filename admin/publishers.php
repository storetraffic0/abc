<?php
$page_title = 'Publisher Management';
include 'template/header.php';
require_once '../includes/db_connection.php';

$publishers_data = [];
try {
    $pdo = get_db_connection();
    // Query ini mengambil semua data yang dibutuhkan dari beberapa tabel
    $sql = "SELECT 
                p.id as publisher_id, p.company_name, p.revenue_share, p.status as publisher_status,
                u.email,
                s.id as site_id, s.domain, s.status as site_status,
                z.id as zone_id, z.name as zone_name, z.format as zone_format, z.rtb_enabled
            FROM publishers p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN sites s ON p.id = s.publisher_id
            LEFT JOIN zones z ON s.id = z.site_id
            ORDER BY p.company_name, s.domain, z.name";
            
    $stmt = $pdo->query($sql);
    
    // Data diolah dan dikelompokkan berdasarkan publisher agar mudah ditampilkan
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $publishers_data[$row['publisher_id']]['details'] = [
            'company_name' => $row['company_name'], 
            'email' => $row['email'], 
            'revenue_share' => $row['revenue_share'],
            'status' => $row['publisher_status']
        ];
        if ($row['site_id']) {
            $publishers_data[$row['publisher_id']]['sites'][$row['site_id']]['details'] = [
                'domain' => $row['domain'], 
                'status' => $row['site_status']
            ];
            if ($row['zone_id']) {
                $publishers_data[$row['publisher_id']]['sites'][$row['site_id']]['zones'][] = [
                    'name' => $row['zone_name'], 
                    'format' => $row['zone_format'], 
                    'rtb_enabled' => $row['rtb_enabled']
                ];
            }
        }
    }

} catch (PDOException $e) {
    error_log("Publisher page error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Could not retrieve publisher data.</div>";
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <a href="publisher_add.php" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-plus fa-sm text-white-50"></i> Add New Publisher</a>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert alert-success">New publisher added successfully.</div>
<?php elseif (isset($_GET['success']) && $_GET['success'] == 2): ?>
    <div class="alert alert-success">Publisher deleted successfully.</div>
<?php elseif (isset($_GET['error'])): ?>
     <div class="alert alert-danger">An error occurred. Please try again.</div>
<?php endif; ?>


<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Publisher List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Publisher Details</th>
                        <th>Sites & Zones</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($publishers_data)): ?>
                        <tr><td colspan="3" class="text-center">No publishers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($publishers_data as $pub_id => $pub): ?>
                            <tr>
                                <td style="width: 30%;">
                                    <strong><?php echo htmlspecialchars($pub['details']['company_name']); ?></strong>
                                    
                                    <?php // Logika untuk badge status publisher
                                        $status = $pub['details']['status'];
                                        $badge_class = 'bg-secondary'; // Default
                                        if ($status == 'active') $badge_class = 'bg-success';
                                        if ($status == 'pending') $badge_class = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                    <br>
                                    
                                    <small class="text-muted"><?php echo htmlspecialchars($pub['details']['email']); ?></small><br>
                                    <span class="badge bg-info text-dark">Share: <?php echo htmlspecialchars($pub['details']['revenue_share']); ?>%</span>
                                </td>
                                <td>
                                    <?php if (!empty($pub['sites'])): ?>
                                        <?php foreach ($pub['sites'] as $site): ?>
                                            <div class="mb-2">
                                                <strong>Site:</strong> <?php echo htmlspecialchars($site['details']['domain']); ?>
                                                <?php if (!empty($site['zones'])): ?>
                                                    <ul class="list-unstyled ms-3">
                                                        <?php foreach ($site['zones'] as $zone): ?>
                                                            <li>
                                                                <?php echo htmlspecialchars($zone['name']); ?>
                                                                <?php if ($zone['rtb_enabled']): ?>
                                                                    <span class="badge bg-success">RTB Enabled</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger">RTB Disabled</span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <small class="d-block ms-3 text-muted">No zones found.</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No sites found for this publisher.</span>
                                    <?php endif; ?>
                                </td>
                                <td style="width: 15%;" class="text-center">
                                    <a href="publisher_edit.php?id=<?php echo $pub_id; ?>" class="btn btn-info btn-sm mb-1" title="Edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" action="publisher_delete.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this publisher and all their sites/zones? This action cannot be undone.');">
                                        <input type="hidden" name="publisher_id" value="<?php echo $pub_id; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm mb-1" title="Delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
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