<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/Review.php';
require_once '../classes/Product.php';

checkAdmin();

$review = new Review($pdo);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 处理操作
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $reviewId = intval($_POST['review_id'] ?? 0);
    if ($reviewId > 0) {
        if ($_POST['action'] === 'delete') {
            if ($review->deleteReview($reviewId)) {
                $actionMsg = '<div class="admin-alert admin-alert-success">评论已删除</div>';
            }
        } elseif ($_POST['action'] === 'reject') {
            if ($review->updateReviewStatus($reviewId, 'rejected')) {
                $actionMsg = '<div class="admin-alert admin-alert-success">评论已驳回</div>';
            }
        } elseif ($_POST['action'] === 'approve') {
            if ($review->updateReviewStatus($reviewId, 'approved')) {
                $actionMsg = '<div class="admin-alert admin-alert-success">评论已通过</div>';
            }
        }
    }
}

$data = $review->getAllReviews($page, $perPage, $statusFilter, $search);
$reviews = $data['rows'];
$total = $data['total'];
$totalPages = ceil($total / $perPage);

$admin_site_config = ['site' => 'main', 'page_title' => '评论管理'];
require_once '../shared/admin/admin-header.php';
?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-comments"></i> 评论管理 (共 <?= $total ?> 条)</span>
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" placeholder="搜索评论/用户/商品..." value="<?= htmlspecialchars($search) ?>"
                   style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;width:220px;">
            <select name="status" style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <option value="">全部状态</option>
                <option value="approved" <?= $statusFilter=='approved'?'selected':'' ?>>已通过</option>
                <option value="pending" <?= $statusFilter=='pending'?'selected':'' ?>>待审核</option>
                <option value="rejected" <?= $statusFilter=='rejected'?'selected':'' ?>>已驳回</option>
            </select>
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">搜索</button>
            <?php if ($search || $statusFilter): ?><a href="reviews.php" class="admin-btn admin-btn-secondary admin-btn-sm">重置</a><?php endif; ?>
        </form>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <?= $actionMsg ?>
        <table class="admin-data-table">
            <thead>
                <tr><th>ID</th><th>商品</th><th>用户</th><th>内容</th><th>评分</th><th>状态</th><th>时间</th><th>操作</th></tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $r): ?>
                <?php $statusMap = ['approved'=>'已通过','pending'=>'待审核','rejected'=>'已驳回']; ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <?php if (!empty($r['product_image'])): ?>
                                <img src="/<?= ltrim(preg_replace('#^(\.\./)+#', '', $r['product_image']), '/') ?>" style="width:40px;height:40px;border-radius:6px;object-fit:cover;" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <span style="font-size:13px;"><?= htmlspecialchars(mb_substr($r['product_name'] ?? '-', 0, 20)) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($r['username'] ?? ($r['is_anonymous'] ? '匿名用户' : '未知')) ?></td>
                    <td style="max-width:280px;">
                        <div style="font-size:13px;line-height:1.5;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                            <?= htmlspecialchars($r['content'] ?? '') ?>
                        </div>
                        <?php if (!empty($r['reply_content'])): ?>
                            <div style="font-size:12px;color:#64748b;margin-top:4px;padding-left:8px;border-left:2px solid #475569;">
                                <span style="color:#94a3b8;">回复：</span><?= htmlspecialchars(mb_substr($r['reply_content'], 0, 60)) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color:<?= $i <= $r['rating'] ? '#f59e0b' : '#334155' ?>;font-size:12px;"></i>
                        <?php endfor; ?>
                    </td>
                    <td><span class="admin-badge <?= $r['status']=='approved'?'success':($r['status']=='pending'?'warning':'danger') ?>"><?= $statusMap[$r['status']] ?? $r['status'] ?></span></td>
                    <td style="font-size:12px;color:#94a3b8;"><?= $r['created_at'] ? date('m-d H:i', strtotime($r['created_at'])) : '-' ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('确定删除此评论？')">
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                            <button type="submit" name="action" value="delete" class="admin-btn admin-btn-sm admin-btn-danger">删除</button>
                        </form>
                        <?php if ($r['status'] == 'approved'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                            <button type="submit" name="action" value="reject" class="admin-btn admin-btn-sm admin-btn-secondary">驳回</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($r['status'] == 'rejected'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                            <button type="submit" name="action" value="approve" class="admin-btn admin-btn-sm admin-btn-primary">通过</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reviews)): ?>
                <tr><td colspan="8" style="text-align:center;color:#64748b;padding:30px;">暂无评论数据</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;gap:6px;justify-content:center;padding:16px 20px;border-top:1px solid #1e293b;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>" class="admin-btn admin-btn-sm <?= $i==$page?'admin-btn-primary':'admin-btn-secondary' ?>" style="min-width:36px;justify-content:center;"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
