<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';

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

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appeal) {
    $action = $_POST['action'] ?? '';
    $comment = $_POST['comment'] ?? '';

    if ($action === 'approve') {
        $pdo->prepare("
            UPDATE nft_city_user
            SET user_id = ?, is_current = 1
            WHERE nft_id = ? AND city_id = ?
        ")->execute([$appeal['user_id'], $appeal['nft_id'], $appeal['city_id']]);

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

    // 重新获取最新状态
    $stmt->execute([$appealId]);
    $appeal = $stmt->fetch();
}

$admin_site_config = [
    'site'       => 'nft',
    'page_title' => '申诉审核 #' . $appealId,
];
require_once '../../shared/admin/admin-header.php';
?>

<?php if (!$appeal): ?>
<!-- 申诉不存在 — 使用统一卡片样式 -->
<div class="admin-card" style="max-width:500px;margin:60px auto;text-align:center;">
    <div class="admin-card-body" style="padding:40px 20px;">
        <div style="font-size:48px;color:#ccc;margin-bottom:20px;">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <h3 style="margin-bottom:10px;color:#333;">申诉记录不存在</h3>
        <p style="color:#888;margin-bottom:20px;">该申诉记录可能已被删除或ID错误。</p>
        <a href="dashboard.php" class="admin-btn admin-btn-primary">
            <i class="fas fa-arrow-left"></i> 返回 NFT 看板
        </a>
    </div>
</div>
<?php else: ?>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success" style="margin-bottom:20px;">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<!-- NFT 信息 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-image" style="margin-right:8px;color:var(--admin-accent);"></i>NFT 信息</span>
    </div>
    <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:14px;">
            <div><strong style="color:#888;">编号：</strong><?= htmlspecialchars($appeal['nft_code'] ?? '') ?></div>
            <div><strong style="color:#888;">城市：</strong><?= htmlspecialchars($appeal['city_name'] ?? '') ?></div>
            <div><strong style="color:#888;">申诉人：</strong><?= htmlspecialchars($appeal['username'] ?? '') ?></div>
            <div><strong style="color:#888;">申诉时间：</strong><?= $appeal['created_at'] ?? '-' ?></div>
            <div><strong style="color:#888;">状态：</strong>
                <?php
                $statusMap = [
                    'pending'  => ['待审核', 'warning'],
                    'approved' => ['已通过', 'success'],
                    'rejected' => ['已拒绝', 'danger'],
                ];
                $s = $statusMap[$appeal['status']] ?? [$appeal['status'], 'default'];
                ?>
                <span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span>
            </div>
            <?php if (!empty($appeal['processed_at'])): ?>
            <div><strong style="color:#888;">处理时间：</strong><?= $appeal['processed_at'] ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 申诉理由 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-comment-alt" style="margin-right:8px;color:var(--admin-accent);"></i>申诉理由</span>
    </div>
    <div class="admin-card-body">
        <p style="font-size:14px;line-height:1.8;"><?= nl2br(htmlspecialchars($appeal['reason'] ?? '未填写')) ?></p>
    </div>
</div>

<!-- 证据图片 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-images" style="margin-right:8px;color:var(--admin-accent);"></i>证据图片</span>
    </div>
    <div class="admin-card-body">
        <?php if (!empty($appeal['evidence_images'])): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
            <?php foreach (explode(',', $appeal['evidence_images']) as $image): ?>
                <?php $image = trim($image); if (empty($image)) continue; ?>
                <div>
                    <a href="/uploads/evidence/<?= htmlspecialchars($image) ?>" target="_blank" style="display:block;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;transition:all .2s;" onmouseover="this.style.borderColor='var(--admin-border-light)'" onmouseout="this.style.borderColor='#e0e0e0'">
                        <img src="/uploads/evidence/<?= htmlspecialchars($image) ?>"
                             style="width:100%;height:180px;object-fit:cover;"
                             onerror="this.style.display='none';this.parentElement.innerHTML='<div style=padding:40px;text-align:center;color:#888>图片加载失败</div>'">
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="admin-empty-state" style="padding:30px;">
            <i class="fas fa-image" style="font-size:32px;color:#ccc;margin-bottom:8px;"></i>
            <p style="color:#888;font-size:14px;">未上传证据图片</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($appeal['status'] === 'pending'): ?>
<!-- 审核操作 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-gavel" style="margin-right:8px;color:var(--admin-accent);"></i>审核操作</span>
    </div>
    <div class="admin-card-body">
        <form method="post" action="">
            <div class="form-group" style="margin-bottom:16px;">
                <label style="display:block;font-size:14px;color:#555;margin-bottom:8px;font-weight:500;">处理意见</label>
                <textarea name="comment" class="form-control" rows="4" style="width:100%;padding:12px;border:1px solid #e0e0e0;border-radius:8px;font-size:14px;resize:vertical;" placeholder="请输入处理意见..."></textarea>
            </div>
            <div style="display:flex;gap:12px;">
                <button type="submit" name="action" value="approve" class="admin-btn admin-btn-success" style="padding:10px 24px;font-size:14px;">
                    <i class="fas fa-check"></i> 通过申诉
                </button>
                <button type="submit" name="action" value="reject" class="admin-btn admin-btn-danger" style="padding:10px 24px;font-size:14px;">
                    <i class="fas fa-times"></i> 拒绝申诉
                </button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<!-- 已处理结果 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-info-circle" style="margin-right:8px;color:var(--admin-accent);"></i>处理结果</span>
    </div>
    <div class="admin-card-body">
        <div style="padding:20px;background:#f8f9fa;border-radius:8px;">
            <div style="margin-bottom:12px;">
                <span style="font-size:14px;color:#888;">处理状态：</span>
                <?php $s = $statusMap[$appeal['status']] ?? [$appeal['status'], 'default']; ?>
                <span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span>
            </div>
            <?php if (!empty($appeal['admin_comment'])): ?>
            <div style="font-size:14px;color:#555;">
                <strong style="color:#888;">处理意见：</strong>
                <p style="margin-top:8px;padding:10px 14px;background:#fff;border-radius:6px;border:1px solid #e0e0e0;"><?= nl2br(htmlspecialchars($appeal['admin_comment'])) ?></p>
            </div>
            <?php endif; ?>
            <div style="margin-top:12px;font-size:13px;color:#aaa;">
                处理人：<?= htmlspecialchars($appeal['admin_id'] ?? '') ?> · 处理时间：<?= $appeal['processed_at'] ?? '-' ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
