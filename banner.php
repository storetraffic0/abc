<?php
header('Content-Type: application/javascript');
$zone_id = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : 0;
if ($zone_id <= 0) {
    echo "console.error('Missing or invalid zone ID');";
    exit;
}
require_once 'includes/db_connection.php';
$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT banner_size FROM zones WHERE id = ? AND format = 'banner'");
$stmt->execute([$zone_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "console.error('Zone not found or not a banner zone');";
    exit;
}
list($width, $height) = explode('x', $row['banner_size']);
?>
(function() {
    var zoneId = <?php echo $zone_id; ?>;
    var width = <?php echo (int)$width; ?>;
    var height = <?php echo (int)$height; ?>;
    var containerId = 'banner-container-' + zoneId;
    var container = document.getElementById(containerId);
    if (!container) { console.error('Container not found'); return; }
    container.innerHTML = '';
    var iframe = document.createElement('iframe');
    iframe.src = '<?php echo $APP_SETTINGS['ad_tag_domain']; ?>/banner_frame.php?zone_id=' + zoneId;
    iframe.width = width;
    iframe.height = height;
    iframe.frameBorder = 0;
    iframe.scrolling = 'no';
    iframe.style.border = '0';
    iframe.style.display = 'block';
    iframe.allow = 'autoplay; encrypted-media; fullscreen';
    container.appendChild(iframe);
})();