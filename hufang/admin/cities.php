<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/City.php';

// 检查管理员权限
checkAdmin();

$city = new City($pdo);

// 处理搜索和分页
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 获取数据
$cities = $city->searchAllCities($page, $perPage, $search);
$totalCities = $city->getTotalCitiesCount($search);
$totalPages = ceil($totalCities / $perPage);

// 处理删除操作
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $cityId = (int)$_GET['id'];
    if ($city->deleteCity($cityId)) {
        $_SESSION['success'] = '城市删除成功！';
    } else {
        $_SESSION['error'] = '城市删除失败！';
    }
    header('Location: cities.php');
    exit;
}

$admin_site_config = ['site' => 'hufang', 'page_title' => '城市管理'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="admin-alert success"><i class="fas fa-check-circle"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="admin-alert danger"><i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- 顶部操作 -->
<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <a href="city_add.php" class="admin-btn admin-btn-primary"><i class="fas fa-plus"></i> 添加城市</a>
</div>

<!-- 筛选栏 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-filter"></i> 搜索城市</span>
    </div>
    <form method="get" class="admin-form-row">
        <div class="admin-form-group" style="flex:2;">
            <input type="text" name="search" class="admin-form-input" placeholder="搜索城市名称、拼音或区域代码..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="admin-form-group">
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> 搜索</button>
            <a href="cities.php" class="admin-btn admin-btn-default"><i class="fas fa-undo"></i> 重置</a>
        </div>
    </form>
</div>

<!-- 城市列表 -->
<div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;">
        <span class="admin-card-title"><i class="fas fa-city"></i> 城市列表</span>
        <span class="admin-text-muted">共 <?= number_format($totalCities) ?> 个城市</span>
    </div>
    <?php if (empty($cities)): ?>
        <div class="admin-empty-state" style="padding:40px;">
            <i class="fas fa-city"></i>
            <p>暂无城市数据</p>
        </div>
    <?php else: ?>
    <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>排名</th>
                    <th>城市名称</th>
                    <th>拼音</th>
                    <th>区域代码</th>
                    <th>居民数</th>
                    <th>区块数</th>
                    <th>人气值</th>
                    <th>热门</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cities as $cityItem): ?>
                    <tr>
                        <td><?= $cityItem['id'] ?></td>
                        <td><span class="admin-badge <?= $cityItem['rank'] <= 3 ? 'warning' : 'default' ?>"><?= $cityItem['rank'] ?></span></td>
                        <td><?= htmlspecialchars($cityItem['name']) ?></td>
                        <td><?= htmlspecialchars($cityItem['pinyin']) ?></td>
                        <td><?= htmlspecialchars($cityItem['area_code']) ?></td>
                        <td><?= number_format($cityItem['resident_count']) ?></td>
                        <td><?= number_format($cityItem['activated_blocks']) ?></td>
                        <td><?= number_format($cityItem['popularity']) ?></td>
                        <td>
                            <?php if ($cityItem['is_hot']): ?>
                                <span class="admin-badge success">是</span>
                            <?php else: ?>
                                <span class="admin-text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="admin-btn-group">
                                <a href="city_edit.php?id=<?= $cityItem['id'] ?>" class="admin-btn admin-btn-sm admin-btn-default" title="编辑"><i class="fas fa-edit"></i></a>
                                <a href="cities.php?delete=1&id=<?= $cityItem['id'] ?>" class="admin-btn admin-btn-sm admin-btn-danger" title="删除" onclick="return confirm('确定要删除这个城市吗？此操作不可恢复！')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php $prefix = $search !== '' ? '?search=' . urlencode($search) . '&' : '?'; ?>
        <div class="admin-pagination">
            <div class="admin-page-info">第 <?= $page ?> 页 / 共 <?= $totalPages ?> 页</div>
            <div class="admin-page-buttons">
                <a href="cities.php<?= $prefix ?>page=1" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">首页</a>
                <a href="cities.php<?= $prefix ?>page=<?= $page - 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">上一页</a>
                <?php $start = max(1, $page - 2); $end = min($totalPages, $page + 2); ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="cities.php<?= $prefix ?>page=<?= $i ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-default' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="cities.php<?= $prefix ?>page=<?= $page + 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">下一页</a>
                <a href="cities.php<?= $prefix ?>page=<?= $totalPages ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">末页</a>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
