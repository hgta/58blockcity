<?php
// 管理员验证等代码...
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';

//checkAdminLogin(); // 需要管理员权限
checkAdmin();

$appealId = $_GET['id'] ?? 0;
$appeal = $pdo->query("
    SELECT a.*, u.username, n.code AS nft_code, c.name AS city_name
    FROM nft_claim_appeals a
    JOIN users u ON a.user_id = u.id
    JOIN nft_avatars n ON a.nft_id = n.id
    JOIN cities c ON a.city_id = c.id
    WHERE a.id = $appealId
")->fetch();

if (!$appeal) {
    die("申诉记录不存在");
}

// 处理审核操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $comment = $_POST['comment'] ?? '';
    
    if ($action === 'approve') {
        // 1. 转移NFT所有权
        $pdo->prepare("
            UPDATE nft_city_user 
            SET user_id = ?, is_current = 1
            WHERE nft_id = ? AND city_id = ?
        ")->execute([$appeal['user_id'], $appeal['nft_id'], $appeal['city_id']]);
        
        // 2. 更新申诉状态
        $pdo->prepare("
            UPDATE nft_claim_appeals 
            SET status = 'approved', 
                admin_id = ?,
                admin_comment = ?,
                processed_at = NOW()
            WHERE id = ?
        ")->execute([$_SESSION['admin_id'], $comment, $appealId]);
        
        $success = "申诉已通过，NFT所有权已转移";
    } else {
        // 拒绝申诉
        $pdo->prepare("
            UPDATE nft_claim_appeals 
            SET status = 'rejected', 
                admin_id = ?,
                admin_comment = ?,
                processed_at = NOW()
            WHERE id = ?
        ")->execute([$_SESSION['admin_id'], $comment, $appealId]);
        
        $success = "申诉已拒绝";
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<!-- 审核页面HTML -->
<div class="container">
    <h3>申诉审核 #<?= $appealId ?></h3>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>NFT信息</h5>
            <p>编号: <?= htmlspecialchars($appeal['nft_code']) ?></p>
            <p>城市: <?= htmlspecialchars($appeal['city_name']) ?></p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>申诉人</h5>
            <p><?= htmlspecialchars($appeal['username']) ?></p>
            <p>申诉时间: <?= $appeal['created_at'] ?></p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>申诉理由</h5>
            <p><?= nl2br(htmlspecialchars($appeal['reason'])) ?></p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>证据图片</h5>
            <div class="row">
                <?php foreach (explode(',', $appeal['evidence_images']) as $image): ?>
                    <div class="col-md-4 mb-3">
                        <img src="/uploads/evidence/<?= htmlspecialchars($image) ?>" 
                             class="img-fluid img-thumbnail">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <form method="post">
        <div class="form-group">
            <label>处理意见</label>
            <textarea name="comment" class="form-control"></textarea>
        </div>
        
        <button type="submit" name="action" value="approve" class="btn btn-success">
            <i class="fas fa-check"></i> 通过申诉
        </button>
        <button type="submit" name="action" value="reject" class="btn btn-danger">
            <i class="fas fa-times"></i> 拒绝申诉
        </button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>