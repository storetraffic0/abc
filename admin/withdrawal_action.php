<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once '../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id']) && isset($_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $pdo = get_db_connection();
    $sql = '';

    try {
        switch ($action) {
            case 'approve':
                $sql = "UPDATE withdrawal_requests SET status = 'processing' WHERE id = ?";
                $pdo->prepare($sql)->execute([$request_id]);
                break;
            case 'reject':
                // Di aplikasi nyata, Anda mungkin ingin menambahkan alasan penolakan
                $sql = "UPDATE withdrawal_requests SET status = 'rejected', admin_notes = 'Rejected by admin' WHERE id = ?";
                $pdo->prepare($sql)->execute([$request_id]);
                break;
            case 'mark_paid':
                $sql = "UPDATE withdrawal_requests SET status = 'paid', paid_date = NOW() WHERE id = ?";
                $pdo->prepare($sql)->execute([$request_id]);
                break;
            default:
                throw new Exception("Invalid action.");
        }
        header("Location: withdrawals.php?success=1");
        exit;
    } catch (Exception $e) {
        error_log("Withdrawal action error: " . $e->getMessage());
        header("Location: withdrawals.php?error=1");
        exit;
    }
}

header("Location: withdrawals.php");
exit;