<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { header("Location: index.php"); exit; }
require_once '../includes/db_connection.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM deposit_methods WHERE id = ?");
    $stmt->execute([$_POST['id']]);
}
header("Location: deposit_methods.php");
exit;