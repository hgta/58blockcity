<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

$txId = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($txId > 0 && in_array($action, ['accept', 'reject'])) {
    $status = $action === 'accept' ? 'accepted' : 'rejected';
    try {
        $stmt = $pdo->prepare("UPDATE transactions SET status = ? WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
        $stmt->execute([$status, $txId, $_SESSION['user_id'], $_SESSION['user_id']]);
        
        if ($action === 'accept' && $stmt->rowCount() > 0) {
            // 获取交易详情
            $txStmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
            $txStmt->execute([$txId]);
            $tx = $txStmt->fetch();
            
            if ($tx) {
                // 更新NFT所有权
                $pdo->prepare("UPDATE nfts SET owner_id = ? WHERE id = ?")
                    ->execute([$tx['buyer_id'], $tx['nft_id']]);
            }
        }
    } catch (Exception $e) {
        error_log("Process transaction failed: " . $e->getMessage());
    }
}

header("Location: dashboard.php");
exit();
