<?php
$page_title = 'Deposit Methods';
include 'template/header.php';
$pdo = get_db_connection();
$methods = $pdo->query("SELECT * FROM deposit_methods ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo e($page_title); ?></h1>
    <a href="deposit_method_edit.php" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-plus fa-sm"></i> Add New Method</a>
</div>
<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Available Deposit Methods</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Name</th><th>Instructions</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($methods as $method): ?>
                    <tr>
                        <td><?php echo e($method['name']); ?></td>
                        <td style="white-space: pre-wrap;"><?php echo e($method['instructions']); ?></td>
                        <td><span class="badge bg-<?php echo $method['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $method['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td>
                            <a href="deposit_method_edit.php?id=<?php echo $method['id']; ?>" class="btn btn-info btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                            <form method="POST" action="deposit_method_delete.php" class="d-inline" onsubmit="return confirm('Are you sure?');"><input type="hidden" name="id" value="<?php echo $method['id']; ?>"><button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button></form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'template/footer.php'; ?>