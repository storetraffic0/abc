<?php
$page_title = 'Advertiser Management';
include 'template/header.php';
require_once '../includes/db_connection.php';

$advertisers = [];
try {
    $pdo = get_db_connection();
    $sql = "SELECT a.id, a.company_name, a.allow_external_campaigns, u.email, u.username 
            FROM advertisers a 
            JOIN users u ON a.user_id = u.id 
            ORDER BY a.company_name ASC";
    $advertisers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Advertiser page error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Could not retrieve advertiser data.</div>";
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <a href="advertiser_add.php" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-plus fa-sm text-white-50"></i> Add New Advertiser</a>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert alert-success">New advertiser added successfully.</div>
<?php elseif (isset($_GET['success']) && $_GET['success'] == 2): ?>
    <div class="alert alert-success">Advertiser deleted successfully.</div>
<?php elseif (isset($_GET['error'])): ?>
     <div class="alert alert-danger">An error occurred. Please try again.</div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Advertiser List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($advertisers)): ?>
                        <tr><td colspan="4" class="text-center">No advertisers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($advertisers as $advertiser): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($advertiser['company_name']); ?></strong><br>
                                    <?php if ($advertiser['allow_external_campaigns']): ?>
                                        <span class="badge bg-success">External Campaigns Enabled</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">External Campaigns Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($advertiser['username']); ?></td>
                                <td><?php echo htmlspecialchars($advertiser['email']); ?></td>
                                <td class="text-center">
                                    <a href="advertiser_edit.php?id=<?php echo $advertiser['id']; ?>" class="btn btn-info btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <form method="POST" action="advertiser_delete.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this advertiser and all their campaigns? This action is permanent.');">
                                        <input type="hidden" name="advertiser_id" value="<?php echo $advertiser['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                            <i class="fas fa-trash"></i>
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