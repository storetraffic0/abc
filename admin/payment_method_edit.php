<?php
$page_title = 'Edit Payment Method';
$is_new = !isset($_GET['id']);
if ($is_new) $page_title = 'Add New Payment Method';
include 'template/header.php';
$pdo = get_db_connection();
$method = ['name' => '', 'required_fields' => '{"field_name": "Field Label"}', 'is_active' => 1];
$error = ''; $success = '';

if (!$is_new) {
    $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $method = $stmt->fetch();
    if (!$method) die('Method not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $fields_json = $_POST['required_fields'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    if (json_decode($fields_json) === null) {
        $error = 'Required Fields JSON is invalid.';
    } else {
        try {
            if ($is_new) {
                $stmt = $pdo->prepare("INSERT INTO payment_methods (name, required_fields, is_active) VALUES (?, ?, ?)");
                $stmt->execute([$name, $fields_json, $is_active]);
            } else {
                $stmt = $pdo->prepare("UPDATE payment_methods SET name = ?, required_fields = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $fields_json, $is_active, $_GET['id']]);
            }
            $success = "Payment method saved successfully. <a href='payment_methods.php'>Back to list.</a>";
        } catch(PDOException $e) { $error = "Database error: " . $e->getMessage(); }
    }
}
?>
<h1 class="h3 mb-4 text-gray-800"><?php echo e($page_title); ?></h1>
<?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3"><label class="form-label">Method Name</label><input type="text" name="name" class="form-control" value="<?php echo e($method['name']); ?>" required></div>
            <div class="mb-3"><label class="form-label">Required Fields (JSON)</label><textarea name="required_fields" class="form-control" rows="5" required><?php echo e($method['required_fields']); ?></textarea><small class="form-text text-muted">Format: {"field_key": "Field Label", "another_key": "Another Label"}</small></div>
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo $method['is_active'] ? 'checked' : ''; ?>><label class="form-check-label" for="is_active">Is Active</label></div>
            <button type="submit" class="btn btn-primary">Save Method</button>
            <a href="payment_methods.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
<?php include 'template/footer.php'; ?>