<?php
$page_title = 'Payment Methods';
include 'template/header.php';
$pdo = get_db_connection();
$methods = $pdo->query("SELECT * FROM payment_methods ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo e($page_title); ?></h1>
    <a href="payment_method_edit.php" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-plus fa-sm"></i> Add New Method</a>
</div>
<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Available Payment Methods</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Name</th><th>Required Fields</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($methods as $method): ?>
                    <tr>
                        <td><?php echo e($method['name']); ?></td>
                        <td>
                            <ul><?php foreach(json_decode($method['required_fields'], true) as $key => $label): ?><li><?php echo e($label); ?> (<?php echo e($key); ?>)</li><?php endforeach; ?></ul>
                        </td>
                        <td><span class="badge bg-<?php echo $method['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $method['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td><a href="payment_method_edit.php?id=<?php echo $method['id']; ?>" class="btn btn-info btn-sm"><i class="fas fa-edit"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'template/footer.php'; ?>