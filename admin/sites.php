<?php
$page_title = 'Site Management';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();

// --- PROSES AKSI (APPROVE, REJECT, TOGGLE STATUS) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $site_id = (int)$_GET['id'];
    $new_status = '';

    switch ($action) {
        case 'approve':
            $new_status = 'active';
            break;
        case 'reject':
            $new_status = 'inactive';
            break;
        case 'deactivate':
            $new_status = 'inactive';
            break;
        case 'activate':
            $new_status = 'active';
            break;
    }

    if ($new_status) {
        try {
            $sql = "UPDATE sites SET status = :status WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':status' => $new_status, ':id' => $site_id]);
            // Redirect kembali ke halaman ini tanpa parameter action untuk refresh
            header("Location: sites.php?success=1");
            exit;
        } catch (PDOException $e) {
            error_log("Site status update error: " . $e->getMessage());
            header("Location: sites.php?error=1");
            exit;
        }
    }
}


// --- AMBIL SEMUA DATA SITUS UNTUK DITAMPILKAN ---
$sites = [];
try {
    $sql_select = "SELECT s.id, s.domain, s.status, s.category, p.company_name 
                   FROM sites s 
                   JOIN publishers p ON s.publisher_id = p.id 
                   ORDER BY s.status, s.id DESC";
    $sites = $pdo->query($sql_select)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Could not retrieve site data.</div>";
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
    <a href="site_add.php" class="btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Add New Site
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Site status updated successfully.</div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="alert alert-danger">Failed to update site status.</div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Sites</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Publisher</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sites)): ?>
                        <tr><td colspan="5" class="text-center">No sites found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sites as $site): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($site['domain']); ?></td>
                                <td><?php echo htmlspecialchars($site['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($site['category'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                        $status = $site['status'];
                                        $badge_class = 'bg-secondary';
                                        if ($status == 'active') $badge_class = 'bg-success';
                                        if ($status == 'pending') $badge_class = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                </td>
                                   <td class="text-center">
    <a href="site_edit.php?id=<?php echo $site['id']; ?>" class="btn btn-info btn-sm me-1" title="Edit">
        <i class="fas fa-edit"></i>
    </a>
    
    <?php // Tombol Aksi Status Dinamis ?>
    <?php if ($site['status'] == 'pending'): ?>
        <a href="sites.php?action=approve&id=<?php echo $site['id']; ?>" class="btn btn-success btn-sm">Approve</a>
        <a href="sites.php?action=reject&id=<?php echo $site['id']; ?>" class="btn btn-danger btn-sm">Reject</a>
    <?php elseif ($site['status'] == 'active'): ?>
        <a href="sites.php?action=deactivate&id=<?php echo $site['id']; ?>" class="btn btn-warning btn-sm">Deactivate</a>
    <?php elseif ($site['status'] == 'inactive'): ?>
        <a href="sites.php?action=activate&id=<?php echo $site['id']; ?>" class="btn btn-success btn-sm">Activate</a>
    <?php endif; ?>

    <form method="POST" action="site_delete.php" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this site and all its zones? This is irreversible.');">
        <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
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