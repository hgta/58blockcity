<?php
// 管理员验证等代码...
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';

checkAdmin();

$appealId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("
    SELECT a.*, u.username, n.code AS nft_code, c.name AS city_name
    FROM nft_claim_appeals a
    JOIN users u ON a.user_id = u.id
    JOIN nft_avatars n ON a.nft_id = n.id
    JOIN cities c ON a.city_id = c.id
    WHERE a.id = ?
");
$stmt->execute([$appealId]);
$appeal = $stmt->fetch();

if (!$appeal) {
    require_once '../includes/header.php';
    ?>
    <div class="container" style="padding:60px 20px;text-align:center;">
        <div class="card" style="max-width:500px;margin:0 auto;padding:40px 20px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
            <div style="font-size:48px;color:#ccc;margin-bottom:20px;"><i class="fas fa-exclamation-circle"></i></div>
            <h3 style="margin-bottom:10px;color:#333;">申诉记录不存在</h3>
            <p style="color:#888;margin-bottom:20px;">该申诉记录可能已被删除或ID错误。</p>
            <a href="appeal_list.php" class="btn btn-primary" style="display:inline-block;padding:10px 24px;background:#ff6b00;color:#fff;border-radius:8px;text-decoration:none;">
                <i class="fas fa-arrow-left"></i> 返回申诉列表
            </a>
        </div>
    </div>
    <?php
    require_once '../includes/footer.php';
    exit;
}

// 处理审核操作
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
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
        ")->execute([$_SESSION['user_id'], $comment, $appealId]);
        
        $success = "申诉已通过，NFT所有权已转移";
    } elseif ($action === 'reject') {
        // 拒绝申诉
        $pdo->prepare("
            UPDATE nft_claim_appeals 
            SET status = 'rejected', 
                admin_id = ?,
                admin_comment = ?,
                processed_at = NOW()
            WHERE id = ?
        ")->execute([$_SESSION['user_id'], $comment, $appealId]);
        
        $success = "申诉已拒绝";
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<!-- 审核页面HTML -->
<div class="container">
    <h3>申诉审核 #<?= $appealId ?></h3>
    
    <?php if ($success): ?>
    <div class="alert alert-success" style="padding:12px 20px;background:#e8f5e9;color:#388e3c;border-radius:8px;margin-bottom:20px;">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>NFT信息</h5>
            <p>编号: <?= htmlspecialchars($appeal['nft_code'] ?? '') ?></p>
            <p>城市: <?= htmlspecialchars($appeal['city_name'] ?? '') ?></p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>申诉人</h5>
            <p><?= htmlspecialchars($appeal['username'] ?? '') ?></p>
            <p>申诉时间: <?= $appeal['created_at'] ?? '' ?></p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>申诉理由</h5>
            <p><?= nl2br(htmlspecialchars($appeal['reason'] ?? '')) ?></p>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <h5>证据图片</h5>
            <div class="row">
                <?php if (!empty($appeal['evidence_images'])): ?>
                <?php foreach (explode(',', $appeal['evidence_images']) as $image): ?>
                    <?php $image = trim($image); if (empty($image)) continue; ?>
                    <div class="col-md-4 mb-3">
                        <img src="/uploads/evidence/<?= htmlspecialchars($image) ?>" 
                             class="img-fluid img-thumbnail" style="max-width:100%;border-radius:8px;">
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#888;">未上传证据图片</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($appeal['status'] === 'pending'): ?>
    <form method="post">
        <div class="form-group">
            <label>处理意见</label>
            <textarea name="comment" class="form-control" rows="4" style="width:100%;padding:10px;border:1px solid #e0e0e0;border-radius:8px;"></textarea>
        </div>
        
        <button type="submit" name="action" value="approve" class="btn btn-success" style="padding:10px 20px;background:#388e3c;color:#fff;border:none;border-radius:8px;cursor:pointer;margin-right:10px;">
            <i class="fas fa-check"></i> 通过申诉
        </button>
        <button type="submit" name="action" value="reject" class="btn btn-danger" style="padding:10px 20px;background:#c62828;color:#fff;border:none;border-radius:8px;cursor:pointer;">
            <i class="fas fa-times"></i> 拒绝申诉
        </button>
    </form>
    <?php else: ?>
    <div class="alert alert-info" style="padding:12px 20px;background:#e3f2fd;color:#1976d2;border-radius:8px;">
        <i class="fas fa-info-circle"></i> 该申诉已处理（状态：<?= htmlspecialchars($appeal['status']) ?>）
        <?php if (!empty($appeal['admin_comment'])): ?>
        <div style="margin-top:8px;padding:8px 12px;background:#fff;border-radius:6px;color:#333;">
            <strong>处理意见：</strong><?= htmlspecialchars($appeal['admin_comment']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>