<?php
$page_title = 'Billing & Payments';
include 'template/header.php';
require_once '../includes/db_connection.php';

$pdo = get_db_connection();
$advertiser_user_id = $_SESSION['user_id'];
$success_message = ''; $error_message = '';
$current_balance = 0; $total_spend = 0; $total_deposited = 0;
$chart_labels = []; $chart_spend_data = []; $transactions = []; $deposit_methods = [];

try {
    // Ambil ID advertiser yang terkait dengan user yang login
    $stmt_adv_id = $pdo->prepare("SELECT id FROM advertisers WHERE user_id = ?");
    $stmt_adv_id->execute([$advertiser_user_id]);
    $advertiser_id = $stmt_adv_id->fetchColumn();

    // Proses form konfirmasi deposit saat disubmit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'confirm_deposit') {
        if (!$advertiser_id) {
            $error_message = "Advertiser account not found.";
        } else {
            $amount = (float)($_POST['amount'] ?? 0);
            $method_id = (int)($_POST['deposit_method_id'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            $proof_file = $_FILES['proof_image'] ?? null;

            if ($amount > 0 && $method_id && $proof_file && $proof_file['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/proofs/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $filename = uniqid('proof_', true) . '-' . basename($proof_file['name']);
                $target_path = $upload_dir . $filename;
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

                if (in_array($proof_file['type'], $allowed_types) && $proof_file['size'] < 5000000) {
                    if (move_uploaded_file($proof_file['tmp_name'], $target_path)) {
                        $stmt = $pdo->prepare("INSERT INTO deposit_requests (user_id, advertiser_id, deposit_method_id, amount, proof_image_path, notes) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$advertiser_user_id, $advertiser_id, $method_id, $amount, $filename, $notes]);
                        $success_message = "Your deposit request has been submitted and is now pending review.";
                    } else { $error_message = "Failed to upload proof file."; }
                } else { $error_message = "Invalid file type or size. Please upload a JPG, PNG, or GIF image under 5MB."; }
            } else { $error_message = "Please fill all required fields and upload a valid proof of payment."; }
        }
    }

    if ($advertiser_id) {
        $stmt_deposits = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND transaction_type = 'deposit'");
        $stmt_deposits->execute([$advertiser_user_id]);
        $total_deposited = $stmt_deposits->fetchColumn();

        $stmt_spend = $pdo->prepare("SELECT COALESCE(SUM(total_spend), 0) FROM advertiser_daily_stats WHERE advertiser_id = ?");
        $stmt_spend->execute([$advertiser_id]);
        $total_spend = $stmt_spend->fetchColumn();

        $current_balance = $total_deposited - $total_spend;
        $deposit_methods = $pdo->query("SELECT id, name, instructions FROM deposit_methods WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $sql_chart = "SELECT report_date as date, SUM(total_spend) as daily_spend FROM advertiser_daily_stats WHERE advertiser_id = ? AND report_date >= CURDATE() - INTERVAL 29 DAY GROUP BY date ORDER BY date ASC";
        $stmt_chart = $pdo->prepare($sql_chart);
        $stmt_chart->execute([$advertiser_id]);
        $chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

        $period = new DatePeriod(new DateTime('-29 days'), new DateInterval('P1D'), new DateTime('+1 day'));
        $spend_by_date = array_column($chart_data, 'daily_spend', 'date');
        foreach ($period as $date) {
            $date_string = $date->format('Y-m-d');
            $chart_labels[] = $date->format('M d');
            $chart_spend_data[] = $spend_by_date[$date_string] ?? 0;
        }

        // =================================================================
        // PERBAIKAN SQL: Menggunakan dua parameter berbeda untuk UNION
        // =================================================================
        $sql_history = "
            (SELECT transaction_date as date, description, amount, 'approved' as status, NULL as rejection_reason FROM transactions WHERE user_id = :user_id_1 AND transaction_type = 'deposit')
            UNION ALL
            (SELECT created_at as date, CONCAT('Deposit Request via ', dm.name) as description, dr.amount, dr.status, dr.rejection_reason FROM deposit_requests dr JOIN deposit_methods dm ON dr.deposit_method_id = dm.id WHERE dr.user_id = :user_id_2)
            ORDER BY date DESC";
        $stmt_history = $pdo->prepare($sql_history);
        $stmt_history->execute([':user_id_1' => $advertiser_user_id, ':user_id_2' => $advertiser_user_id]);
        $transactions = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { $error_message = $e->getMessage(); }
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo e($page_title); ?></h1>

<?php if ($success_message): ?><div class="alert alert-success"><?php echo e($success_message); ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo e($error_message); ?></div><?php endif; ?>

<!-- ================================================================= -->
<!-- PERBAIKAN HTML: Memastikan semua tag <div> ditutup dengan benar -->
<!-- ================================================================= -->
<div class="row">
    <div class="col-xl-4 col-md-6 mb-4"><div class="card border-left-success shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-success text-uppercase mb-1">Current Balance</div><div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($current_balance, 2); ?></div></div><div class="col-auto"><i class="fas fa-wallet fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-4 col-md-6 mb-4"><div class="card border-left-danger shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Spend</div><div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($total_spend, 2); ?></div></div><div class="col-auto"><i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i></div></div></div></div></div>
    <div class="col-xl-4 col-md-6 mb-4"><div class="card border-left-primary shadow h-100 py-2"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Deposited</div><div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($total_deposited, 2); ?></div></div><div class="col-auto"><i class="fas fa-piggy-bank fa-2x text-gray-300"></i></div></div></div></div></div>
</div>

<div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daily Spend (Last 30 Days)</h6></div><div class="card-body"><div class="chart-area"><canvas id="spendChart"></canvas></div></div></div>

<div class="row">
    <div class="col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Add Funds</h6></div>
            <div class="card-body">
                <p class="small">Select a method, make the payment as instructed, then confirm your deposit using the form below.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="confirm_deposit">
                    <div class="mb-3"><label for="deposit_method_id" class="form-label">Payment Method</label><select name="deposit_method_id" id="deposit_method_id" class="form-select" required><option value="">-- Select Method --</option><?php foreach($deposit_methods as $method): ?><option value="<?php echo $method['id']; ?>" data-instructions="<?php echo e($method['instructions']); ?>"><?php echo e($method['name']); ?></option><?php endforeach; ?></select></div>
                    <div id="instructions-box" class="alert alert-info" style="display:none; white-space: pre-wrap; font-size: 0.9em;"></div>
                    <div class="mb-3"><label for="amount" class="form-label">Amount Sent (USD)</label><input type="number" name="amount" id="amount" class="form-control" step="0.01" min="1" required></div>
                    <div class="mb-3"><label for="notes" class="form-label">Notes <small>(e.g. Transaction ID, Sender Name)</small></label><textarea name="notes" id="notes" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label for="proof_image" class="form-label">Proof of Payment <small>(Image: JPG, PNG, GIF)</small></label><input type="file" name="proof_image" id="proof_image" class="form-control" accept="image/jpeg,image/png,image/gif" required></div>
                    <button type="submit" class="btn btn-primary w-100">Submit Confirmation</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Transaction History</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%">
                        <thead><tr><th>Date</th><th>Description</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if(empty($transactions)): ?>
                                <tr><td colspan="4" class="text-center">No transactions found.</td></tr>
                            <?php else: foreach($transactions as $tx): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($tx['date'])); ?></td>
                                    <td><?php echo e($tx['description']); ?><?php if($tx['status'] == 'rejected' && !empty($tx['rejection_reason'])): ?><br><small class="text-danger">Reason: <?php echo e($tx['rejection_reason']); ?></small><?php endif; ?></td>
                                    <td class="fw-bold <?php echo $tx['status'] == 'approved' ? 'text-success' : 'text-muted'; ?>">$<?php echo number_format($tx['amount'], 2); ?></td>
                                    <td><?php $status_class = 'secondary'; if ($tx['status'] == 'approved') $status_class = 'success'; elseif ($tx['status'] == 'pending') $status_class = 'warning'; elseif ($tx['status'] == 'rejected') $status_class = 'danger'; ?><span class="badge bg-<?php echo $status_class; ?>"><?php echo ucfirst($tx['status']); ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'template/footer.php'; ?>

<script>
$(function() {
    var ctx = document.getElementById("spendChart");
    new Chart(ctx, { type: 'line', data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: "Spend", lineTension: 0.3, backgroundColor: "rgba(28, 200, 138, 0.05)", borderColor: "rgba(28, 200, 138, 1)", pointRadius: 3, data: <?php echo json_encode($chart_spend_data); ?>, }], }, options: { maintainAspectRatio: false, scales: { yAxes: [{ ticks: { beginAtZero: true, callback: function(value) { return '$' + new Intl.NumberFormat().format(value); } } }], }, legend: { display: false }, tooltips: { callbacks: { label: function(tooltipItem, chart) { return 'Spend: $' + new Intl.NumberFormat().format(tooltipItem.yLabel); } } } } });
    $('#deposit_method_id').on('change', function() { var instructions = $(this).find('option:selected').data('instructions'); var box = $('#instructions-box'); if (instructions) { box.text(instructions).show(); } else { box.hide(); } });
});
</script>