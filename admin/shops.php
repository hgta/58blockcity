<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/Shop.php';
require_once '../classes/User.php';

checkAdmin();

$shop = new Shop($pdo);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (s.shop_name LIKE ? OR s.shop_description LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($statusFilter && in_array($statusFilter, ['active','pending','suspended','closed'])) {
    $where .= " AND s.status = ?";
    $params[] = $statusFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM shops s LEFT JOIN users u ON s.user_id = u.id $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT s.*, u.username as owner_name, (SELECT COUNT(*) FROM products p WHERE p.shop_id = s.id) as product_count FROM shops s LEFT JOIN users u ON s.user_id = u.id $where ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
foreach ($params as $i => $v) { $stmt->bindValue($i+1, $v); }
$stmt->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params)+2, $offset, PDO::PARAM_INT);
$stmt->execute();
$shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

$admin_site_config = ['site' => 'main', 'page_title' => '店铺管理'];
require_once '../shared/admin/admin-header.php';
?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-store"></i> 店铺列表 (共 <?= $total ?> 家)</span>
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" placeholder="搜索店铺..." value="<?= htmlspecialchars($search) ?>"
                   style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;width:180px;">
            <select name="status" style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <option value="">全部状态</option>
                <option value="active" <?= $statusFilter=='active'?'selected':'' ?>>营业中</option>
                <option value="pending" <?= $statusFilter=='pending'?'selected':'' ?>>审核中</option>
                <option value="suspended" <?= $statusFilter=='suspended'?'selected':'' ?>>已暂停</option>
                <option value="closed" <?= $statusFilter=='closed'?'selected':'' ?>>已关闭</option>
            </select>
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">搜索</button>
            <?php if ($search || $statusFilter): ?><a href="shops.php" class="admin-btn admin-btn-secondary admin-btn-sm">重置</a><?php endif; ?>
        </form>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-data-table">
            <thead>
                <tr><th>店铺</th><th>店主</th><th>商品数</th><th>评分</th><th>状态</th><th>创建时间</th><th>操作</th></tr>
            </thead>
            <tbody>
                <?php foreach ($shops as $s): ?>
                <?php $statusMap = ['active'=>'营业中','pending'=>'审核中','suspended'=>'已暂停','closed'=>'已关闭']; ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php if (!empty($s['shop_logo'])): ?>
                                <img src="../<?= htmlspecialchars($s['shop_logo']) ?>" style="width:36px;height:36px;border-radius:6px;object-fit:cover;">
                            <?php else: ?>
                                <div style="width:36px;height:36px;border-radius:6px;background:#1e293b;display:flex;align-items:center;justify-content:center;color:#64748b;">
                                    <i class="fas fa-store"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($s['shop_name']) ?></strong>
                                <div style="font-size:12px;color:#64748b;"><?= htmlspecialchars(mb_substr($s['shop_description'] ?? '', 0, 30)) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($s['owner_name'] ?? '未知') ?></td>
                    <td><?= $s['product_count'] ?></td>
                    <td><?= number_format($s['rating'] ?? 5, 1) ?></td>
                    <td><span class="admin-badge <?= $s['status']=='active'?'success':($s['status']=='pending'?'warning':($s['status']=='suspended'?'danger':'default')) ?>"><?= $statusMap[$s['status']] ?? $s['status'] ?></span></td>
                    <td><?= $s['created_at'] ? date('Y-m-d', strtotime($s['created_at'])) : '-' ?></td>
                    <td><a href="../mall/shop/view.php?id=<?= $s['id'] ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-secondary">查看</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($shops)): ?>
                <tr><td colspan="7" style="text-align:center;color:#64748b;padding:30px;">未找到店铺</td></tr>
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
