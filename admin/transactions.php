<?php
$page_title = 'Deposit Requests Management';
include 'template/header.php';
$pdo = get_db_connection();
$success_message = ''; $error_message = '';
$admin_user_id = $_SESSION['user_id']; // Asumsikan admin yang login punya user_id

// Proses Approval atau Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];

    // Ambil detail permintaan
    $stmt_req = $pdo->prepare("SELECT * FROM deposit_requests WHERE id = ? AND status = 'pending'");
    $stmt_req->execute([$request_id]);
    $request = $stmt_req->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        $pdo->beginTransaction();
        try {
            if ($action === 'approve') {
                // 1. Tambahkan ke tabel transactions
                $desc = "Approved deposit from request #" . $request['id'];
                $stmt_insert = $pdo->prepare("INSERT INTO transactions (user_id, amount, transaction_type, description) VALUES (?, ?, 'deposit', ?)");
                $stmt_insert->execute([$request['user_id'], $request['amount'], $desc]);

                // 2. Update status permintaan
                $stmt_update = $pdo->prepare("UPDATE deposit_requests SET status = 'approved', processed_at = NOW(), processed_by_admin_id = ? WHERE id = ?");
                $stmt_update->execute([$admin_user_id, $request_id]);
                $success_message = "Deposit request #{$request_id} has been approved and funds added.";
            } elseif ($action === 'reject') {
                $reason = $_POST['rejection_reason'] ?? 'No reason provided.';
                $stmt_update = $pdo->prepare("UPDATE deposit_requests SET status = 'rejected', processed_at = NOW(), processed_by_admin_id = ?, rejection_reason = ? WHERE id = ?");
                $stmt_update->execute([$admin_user_id, $reason, $request_id]);
                $success_message = "Deposit request #{$request_id} has been rejected.";
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Operation failed: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid or already processed request.";
    }
}

// Ambil daftar permintaan deposit berdasarkan status
$filter_status = $_GET['status'] ?? 'pending';
$sql = "SELECT dr.*, u.username, a.company_name, dm.name as method_name
        FROM deposit_requests dr
        JOIN users u ON dr.user_id = u.id
        JOIN advertisers a ON dr.advertiser_id = a.id
        JOIN deposit_methods dm ON dr.deposit_method_id = dm.id
        WHERE dr.status = ? ORDER BY dr.created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$filter_status]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="h3 mb-2 text-gray-800"><?php echo e($page_title); ?></h1>
<p class="mb-4">Review and process deposit requests submitted by advertisers.</p>

<?php if ($success_message): ?><div class="alert alert-success"><?php echo e($success_message); ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo e($error_message); ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Requests List</h6>
        <div class="btn-group mt-2">
            <a href="?status=pending" class="btn btn-sm btn-warning <?php if($filter_status == 'pending') echo 'active'; ?>">Pending</a>
            <a href="?status=approved" class="btn btn-sm btn-success <?php if($filter_status == 'approved') echo 'active'; ?>">Approved</a>
            <a href="?status=rejected" class="btn btn-sm btn-danger <?php if($filter_status == 'rejected') echo 'active'; ?>">Rejected</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%">
                <thead>
                    <tr><th>Date</th><th>Advertiser</th><th>Amount</th><th>Method</th><th>Notes</th><th>Proof</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="text-center">No requests with status '<?php echo e($filter_status); ?>' found.</td></tr>
                    <?php else: foreach ($requests as $req): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($req['created_at'])); ?></td>
                            <td><?php echo e($req['company_name']); ?><br><small class="text-muted"><?php echo e($req['username']); ?></small></td>
                            <td class="fw-bold">$<?php echo number_format($req['amount'], 2); ?></td>
                            <td><?php echo e($req['method_name']); ?></td>
                            <td><?php echo e($req['notes']); ?></td>
                            <td>
                                <!-- Asumsikan gambar disimpan di /uploads/proofs/ -->
                                <a href="../uploads/proofs/<?php echo e($req['proof_image_path']); ?>" target="_blank" class="btn btn-sm btn-info">View</a>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline-block">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal-<?php echo $req['id']; ?>">Reject</button>

                                    <!-- Modal Penolakan -->
                                    <div class="modal fade" id="rejectModal-<?php echo $req['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header"><h5 class="modal-title">Reject Request #<?php echo $req['id']; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <label for="rejection_reason" class="form-label">Reason for Rejection (Optional)</label>
                                                        <textarea name="rejection_reason" class="form-control"></textarea>
                                                    </div>
                                                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Confirm Rejection</button></div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-<?php echo $req['status'] == 'approved' ? 'success' : 'danger'; ?>"><?php echo ucfirst($req['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'template/footer.php'; ?>