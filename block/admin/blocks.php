<?php
/**
 * Block 子站 — 区块管理
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Block.php';
require_once '../../classes/City.php';

// 使用统一后台框架
$admin_site_config = [
    'site'       => 'block',
    'page_title' => '区块管理',
];
require_once '../../shared/admin/admin-header.php';

$block = new Block($pdo);
$city = new City($pdo);

// 筛选参数
$search   = trim($_GET['search'] ?? '');
$cityId   = intval($_GET['city_id'] ?? 0);
$zone     = $_GET['zone'] ?? '';
$status   = $_GET['status'] ?? '';
$page     = max(1, intval($_GET['page'] ?? 1));
$perPage  = 20;

// 构建查询
$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(b.block_number LIKE ? OR u.username LIKE ? OR b.name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($cityId > 0) {
    $where[] = 'b.city_id = ?';
    $params[] = $cityId;
}
if ($zone !== '' && in_array($zone, ['A','B','C','D','E','F','G','H','Z'])) {
    $where[] = 'b.zone = ?';
    $params[] = $zone;
}
if ($status !== '' && in_array($status, ['available','sold','reserved'])) {
    $where[] = 'b.status = ?';
    $params[] = $status;
}

$whereSql = implode(' AND ', $where);

// 总数
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM blocks b LEFT JOIN users u ON b.owner_id = u.id WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// 数据
$sql = "SELECT b.*, c.name as city_name, u.username as owner_name
        FROM blocks b
        JOIN cities c ON b.city_id = c.id
        LEFT JOIN users u ON b.owner_id = u.id
        WHERE $whereSql
        ORDER BY b.city_id, b.zone, b.block_number
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 城市列表（用于筛选下拉）
$cities = $pdo->query("SELECT id, name FROM cities ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// 状态标签映射
function blockStatusBadge(string $status): string {
    switch ($status) {
        case 'available': return '<span class="admin-badge success">可认领</span>';
        case 'sold':      return '<span class="admin-badge warning">已认领</span>';
        case 'reserved':  return '<span class="admin-badge info">已预订</span>';
        default:          return '<span class="admin-badge default">' . htmlspecialchars($status) . '</span>';
    }
}
?>

<!-- 筛选栏 -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fas fa-filter"></i> 筛选条件</h3>
    </div>
    <form method="get" class="admin-form-row">
        <div class="admin-form-group">
            <label>城市</label>
            <select name="city_id" class="admin-form-control">
                <option value="0">全部城市</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $cityId == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-form-group">
            <label>区域</label>
            <select name="zone" class="admin-form-control">
                <option value="">全部区域</option>
                <?php foreach (['A','B','C','D','E','F','G','H','Z'] as $z): ?>
                    <option value="<?= $z ?>" <?= $zone === $z ? 'selected' : '' ?>><?= $z ?>区</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-form-group">
            <label>状态</label>
            <select name="status" class="admin-form-control">
                <option value="">全部状态</option>
                <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>可认领</option>
                <option value="sold" <?= $status === 'sold' ? 'selected' : '' ?>>已认领</option>
                <option value="reserved" <?= $status === 'reserved' ? 'selected' : '' ?>>已预订</option>
            </select>
        </div>
        <div class="admin-form-group" style="flex:2;">
            <label>搜索</label>
            <div style="display:flex;gap:8px;">
                <input type="text" name="search" class="admin-form-control" placeholder="区块编号 / 所有者 / 区块名" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i></button>
                <a href="blocks.php" class="admin-btn admin-btn-default"><i class="fas fa-undo"></i></a>
            </div>
        </div>
    </form>
</div>

<!-- 统计 -->
<div class="admin-stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="admin-stat-card">
        <div class="stat-icon primary"><i class="fas fa-cubes"></i></div>
        <div class="stat-value"><?= number_format($total) ?></div>
        <div class="stat-label">符合条件的区块</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= number_format(array_reduce($blocks, fn($c,$b)=>$c+($b['status']==='available'?1:0),0)) ?></div>
        <div class="stat-label">本页可认领</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-user-check"></i></div>
        <div class="stat-value"><?= number_format(array_reduce($blocks, fn($c,$b)=>$c+($b['status']==='sold'?1:0),0)) ?></div>
        <div class="stat-label">本页已认领</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-layer-group"></i></div>
        <div class="stat-value"><?= number_format(array_reduce($blocks, fn($c,$b)=>$c+($b['is_large_block']?1:0),0)) ?></div>
        <div class="stat-label">本页合并区块</div>
    </div>
</div>

<!-- 数据表格 -->
<div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;">
        <h3 class="admin-card-title"><i class="fas fa-th"></i> 区块列表</h3>
        <span class="admin-text-muted">共 <?= number_format($total) ?> 条记录</span>
    </div>
    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>区块编号</th>
                    <th>城市</th>
                    <th>区域</th>
                    <th>价格</th>
                    <th>所有者</th>
                    <th>状态</th>
                    <th>合并</th>
                    <th>更新时间</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($blocks)): ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>没有找到符合条件的区块</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($blocks as $b): ?>
                        <tr>
                            <td><?= $b['id'] ?></td>
                            <td><strong><?= htmlspecialchars($b['block_number']) ?></strong></td>
                            <td><?= htmlspecialchars($b['city_name']) ?></td>
                            <td><?= $b['zone'] ?>区</td>
                            <td><?= number_format($b['price'], 2) ?></td>
                            <td><?= $b['owner_name'] ? htmlspecialchars($b['owner_name']) : '<span class="admin-text-muted">—</span>' ?></td>
                            <td><?= blockStatusBadge($b['status']) ?></td>
                            <td><?= $b['is_large_block'] ? '<span class="admin-badge info">是</span>' : '<span class="admin-text-muted">—</span>' ?></td>
                            <td><?= date('m-d H:i', strtotime($b['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
        <div class="admin-pagination">
            <div class="admin-page-info">第 <?= $page ?> 页 / 共 <?= $totalPages ?> 页</div>
            <div class="admin-page-buttons">
                <?php
                $qs = array_filter(['city_id'=>$cityId,'zone'=>$zone,'status'=>$status,'search'=>$search]);
                $qStr = http_build_query($qs);
                $prefix = $qStr ? '?' . $qStr . '&' : '?';
                ?>
                <a href="blocks.php<?= $prefix ?>page=1" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">首页</a>
                <a href="blocks.php<?= $prefix ?>page=<?= $page - 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">上一页</a>
                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="blocks.php<?= $prefix ?>page=<?= $i ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-default' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="blocks.php<?= $prefix ?>page=<?= $page + 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">下一页</a>
                <a href="blocks.php<?= $prefix ?>page=<?= $totalPages ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">末页</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
