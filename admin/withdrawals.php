<?php
$page_title = 'Withdrawal Requests';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();
$requests = [];
try {
    // Query kompleks untuk mengambil semua data yang dibutuhkan dalam satu kali jalan
    $sql = "SELECT 
                wr.id, wr.amount, wr.status, wr.request_date, wr.admin_notes,
                p.company_name as publisher_name,
                m.name as method_name,
                pm.account_details
            FROM withdrawal_requests wr
            JOIN publishers p ON wr.publisher_id = p.id
            JOIN publisher_payment_methods pm ON wr.payment_method_id = pm.id
            JOIN payment_methods m ON pm.method_id = m.id
            ORDER BY 
                CASE wr.status
                    WHEN 'pending' THEN 1
                    WHEN 'processing' THEN 2
                    ELSE 3
                END, wr.request_date DESC";
    $requests = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Could not retrieve withdrawal requests.</div>";
    error_log($e->getMessage());
}
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo e($page_title); ?></h1>

<?php if (isset($_GET['success'])): ?><div class="alert alert-success">Action completed successfully.</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger">An error occurred.</div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">All Withdrawal Requests</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%">
                <thead>
                    <tr>
                        <th>Publisher</th>
                        <th>Amount</th>
                        <th>Requested On</th>
                        <th>Payment Details</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="6" class="text-center">No withdrawal requests found.</td></tr>
                    <?php else: foreach ($requests as $req): ?>
                        <tr>
                            <td><?php echo e($req['publisher_name']); ?></td>
                            <td><strong>$<?php echo number_format($req['amount'], 2); ?></strong></td>
                            <td><?php echo date('M d, Y', strtotime($req['request_date'])); ?></td>
                            <td>
                                <strong><?php echo e($req['method_name']); ?>:</strong><br>
                                <ul class="list-unstyled mb-0 small">
                                    <?php foreach(json_decode($req['account_details'], true) as $key => $val): ?>
                                        <li><strong><?php echo e(ucwords(str_replace('_', ' ', $key))); ?>:</strong> <?php echo e($val); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <?php $s = $req['status']; $b = 'bg-secondary';
                                if ($s == 'paid') $b = 'bg-success';
                                if ($s == 'pending') $b = 'bg-warning text-dark';
                                if ($s == 'processing') $b = 'bg-info text-dark';
                                if ($s == 'rejected') $b = 'bg-danger'; ?>
                                <span class="badge <?php echo $b; ?>"><?php echo e(ucfirst($s)); ?></span>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <form action="withdrawal_action.php" method="POST" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                    </form>
                                    <form action="withdrawal_action.php" method="POST" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                                    </form>
                                <?php elseif ($req['status'] === 'processing'): ?>
                                     <form action="withdrawal_action.php" method="POST" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="action" value="mark_paid" class="btn btn-primary btn-sm">Mark as Paid</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
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