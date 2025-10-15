<?php
$page_title = 'Edit Publisher';
include 'template/header.php';
require_once '../includes/db_connection.php';

// Validasi ID Publisher
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid Publisher ID.</div>";
    include 'template/footer.php';
    exit;
}
$publisher_id = (int)$_GET['id'];
$pdo = get_db_connection();
$error_message = '';
$success_message = '';

// --- PROSES UPDATE DATA (JIKA FORM DI-SUBMIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Update data di tabel users
        $sql_user = "UPDATE users u JOIN publishers p ON u.id = p.user_id 
                     SET u.first_name=?, u.last_name=?, u.email=?, u.country=?, 
                         u.contact_whatsapp=?, u.contact_telegram=?, u.contact_skype=? 
                     WHERE p.id = ?";
        $pdo->prepare($sql_user)->execute([
            trim($_POST['first_name']), trim($_POST['last_name']), trim($_POST['email']),
            $_POST['country'], trim($_POST['contact_whatsapp']), trim($_POST['contact_telegram']),
            trim($_POST['contact_skype']), $publisher_id
        ]);
        
        // 2. Logika untuk SSP (Supply-Side Platform)
        $ssp_enabled = isset($_POST['ssp_enabled']) ? 1 : 0;
        $stmt_token = $pdo->prepare("SELECT ssp_token FROM publishers WHERE id = ?");
        $stmt_token->execute([$publisher_id]);
        $current_token = $stmt_token->fetchColumn();
        if ($ssp_enabled && empty($current_token)) {
            $current_token = bin2hex(random_bytes(16));
        } elseif (!$ssp_enabled) {
            $current_token = null;
        }

        // 3. Update data di tabel publishers
        $sql_pub = "UPDATE publishers SET company_name = ?, revenue_share = ?, status = ?, ssp_enabled = ?, ssp_token = ? WHERE id = ?";
        $stmt_pub = $pdo->prepare($sql_pub);
        $stmt_pub->execute([
            trim($_POST['company_name']), $_POST['revenue_share'], $_POST['status'],
            $ssp_enabled, $current_token, $publisher_id
        ]);

        // 4. Update data di tabel zones (rtb_enabled)
        if (isset($_POST['zones'])) {
            $sql_zone = "UPDATE zones SET rtb_enabled = :rtb_enabled WHERE id = :id";
            $stmt_zone = $pdo->prepare($sql_zone);
            foreach ($_POST['zones'] as $zone_id => $settings) {
                $rtb_enabled = isset($settings['rtb_enabled']) ? 1 : 0;
                $stmt_zone->execute([':rtb_enabled' => $rtb_enabled, ':id' => $zone_id]);
            }
        }

        $pdo->commit();
        $success_message = "Publisher updated successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Failed to update publisher: " . $e->getMessage();
    }
}

// --- AMBIL DATA TERBARU UNTUK DITAMPILKAN DI FORM ---
try {
    $sql = "SELECT p.company_name, p.revenue_share, p.status, p.ssp_enabled, p.ssp_token, 
                   u.email, u.first_name, u.last_name, u.country, u.contact_whatsapp, u.contact_telegram, u.contact_skype 
            FROM publishers p JOIN users u ON p.user_id = u.id WHERE p.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $publisher_id]);
    $publisher = $stmt->fetch();

    if (!$publisher) { throw new Exception("Publisher not found."); }

    $sql_sites = "SELECT s.id as site_id, s.domain, z.id as zone_id, z.name, z.format, z.rtb_enabled FROM sites s LEFT JOIN zones z ON s.id = z.site_id WHERE s.publisher_id = :id ORDER BY s.domain, z.name";
    $stmt_sites = $pdo->prepare($sql_sites);
    $stmt_sites->execute([':id' => $publisher_id]);
    $sites_and_zones = $stmt_sites->fetchAll(PDO::FETCH_ASSOC);

    $sites = [];
    foreach ($sites_and_zones as $item) { $sites[$item['domain']][] = $item; }

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    include 'template/footer.php';
    exit;
}
?>

<h1 class="h3 mb-4 text-gray-800"><?php echo e($page_title); ?>: <?php echo e($publisher['company_name']); ?></h1>

<?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

<form method="POST" action="publisher_edit.php?id=<?php echo $publisher_id; ?>">
    <div class="card shadow mb-4">
        <div class="card-header">Publisher Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">First Name</label><input type="text" class="form-control" name="first_name" value="<?php echo e($publisher['first_name']); ?>"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Last Name</label><input type="text" class="form-control" name="last_name" value="<?php echo e($publisher['last_name']); ?>"></div>
            </div>
            <div class="row">
                <div class="col-md-5 mb-3"><label class="form-label">Company Name</label><input type="text" class="form-control" name="company_name" value="<?php echo e($publisher['company_name']); ?>" required></div>
                <div class="col-md-4 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo e($publisher['email']); ?>" required></div>
                <div class="col-md-3 mb-3"><label class="form-label">Country</label><input type="text" class="form-control" name="country" value="<?php echo e($publisher['country']); ?>"></div>
            </div>
            <div class="row">
                 <div class="col-md-3 mb-3"><label class="form-label">Revenue Share (%)</label><input type="number" class="form-control" name="revenue_share" value="<?php echo e($publisher['revenue_share']); ?>" required></div>
                <div class="col-md-3 mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?php echo ($publisher['status'] == 'active') ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo ($publisher['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option><option value="pending" <?php echo ($publisher['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option></select></div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="ssp_enabled" id="ssp_enabled" <?php echo $publisher['ssp_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ssp_enabled">Enable Publisher as SSP (Provide RTB Endpoints)</label>
                    </div>
                    
                    <?php if ($publisher['ssp_enabled'] && !empty($publisher['ssp_token'])): ?>
                        <div class="mt-3">
        <label class="form-label small fw-bold">VAST (Video) SSP Endpoint:</label>
        <input type="text" class="form-control form-control-sm mb-2" value="<?php echo $APP_SETTINGS['ad_tag_domain']; ?>/ssp.php?token=<?php echo e($publisher['ssp_token']); ?>" readonly>

        <label class="form-label small fw-bold">Banner SSP Endpoint:</label>
        <input type="text" class="form-control form-control-sm mb-2" value="<?php echo $APP_SETTINGS['ad_tag_domain']; ?>/ssp_banner.php?token=<?php echo e($publisher['ssp_token']); ?>" readonly>
        
        <label class="form-label small fw-bold">Popunder SSP Endpoint:</label>
        <input type="text" class="form-control form-control-sm" value="<?php echo $APP_SETTINGS['ad_tag_domain']; ?>/ssp_pop.php?token=<?php echo e($publisher['ssp_token']); ?>" readonly>
    </div>
<?php endif; ?>
                </div>
            </div>
            <hr>
            <h6 class="text-muted small">Contact Details</h6>
            <div class="row">
                <div class="col-md-4 mb-3"><label class="form-label">WhatsApp</label><input type="text" class="form-control" name="contact_whatsapp" value="<?php echo e($publisher['contact_whatsapp']); ?>"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Telegram</label><input type="text" class="form-control" name="contact_telegram" value="<?php echo e($publisher['contact_telegram']); ?>"></div>
                <div class="col-md-4 mb-3"><label class="form-label">Skype</label><input type="text" class="form-control" name="contact_skype" value="<?php echo e($publisher['contact_skype']); ?>"></div>
            </div>
        </div>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header">Sites & Zones Settings</div>
        <div class="card-body">
            <?php if (empty($sites)): ?>
                <p class="text-muted">This publisher has no sites yet.</p>
            <?php else: foreach ($sites as $domain => $zones): ?>
                <h5 class="mt-3"><?php echo e($domain); ?></h5>
                <table class="table table-sm table-bordered">
                    <thead><tr><th style="width: 60%;">Zone Name</th><th>Format</th><th>Enable RTB Traffic</th></tr></thead>
                    <tbody>
                        <?php foreach ($zones as $zone): if ($zone['zone_id']): ?>
                            <tr>
                                <td><?php echo e($zone['name']); ?></td>
                                <td><span class="badge bg-primary"><?php echo e(ucfirst($zone['format'])); ?></span></td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" name="zones[<?php echo $zone['zone_id']; ?>][rtb_enabled]" <?php echo $zone['rtb_enabled'] ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Changes</button>
    <a href="publishers.php" class="btn btn-secondary">Back to List</a>
</form>

<?php include 'template/footer.php'; ?>