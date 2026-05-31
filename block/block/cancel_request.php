<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE purchase_requests SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
}
header("Location: ../user/purchase_requests.php");
exit();
