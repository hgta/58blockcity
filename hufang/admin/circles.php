<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';

// 检查管理员权限
checkAdmin();
$circle = new Circle($pdo);
$user = new User($pdo);

// 处理搜索和分页
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

// 获取互访圈数据
$totalCircles = $circle->getTotalCirclesCount($search, $status);
$circles = $circle->getCirclesWithPagination($page, $perPage, $search, $status);
$totalPages = ceil($totalCircles / $perPage);

// 处理互访圈状态变更
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $circleId = intval($_POST['circle_id']);
    
    switch ($_POST['action']) {
        case 'activate':
            $circle->updateCircleStatus($circleId, 'active');
            $_SESSION['message'] = '互访圈已激活';
            break;
        case 'deactivate':
            $circle->updateCircleStatus($circleId, 'inactive');
            $_SESSION['message'] = '互访圈已停用';
            break;
        case 'delete':
            $circle->deleteCircle($circleId);
            $_SESSION['message'] = '互访圈已删除';
            break;
    }
    header('Location: circles.php');
    exit();
}

$admin_site_config = ['site' => 'hufang', 'page_title' => '互访圈管理'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if (isset($_SESSION['message'])): ?>
    <div class="admin-alert success">
        <i class="fas fa-check-circle"></i> <?= $_SESSION['message'] ?>
    </div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<!-- 筛选栏 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-filter"></i> 搜索互访圈</span>
    </div>
    <form method="get" class="admin-form-row">
        <div class="admin-form-group" style="flex:2;">
            <input type="text" name="search" class="admin-form-input" placeholder="搜索互访圈名称、描述或城市..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="admin-form-group">
            <select name="status" class="admin-form-select">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>所有状态</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>活跃</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>停用</option>
            </select>
        </div>
        <div class="admin-form-group">
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> 搜索</button>
            <a href="circles.php" class="admin-btn admin-btn-default"><i class="fas fa-undo"></i> 重置</a>
        </div>
    </form>
</div>

<!-- 互访圈列表 -->
<div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;">
        <span class="admin-card-title"><i class="fas fa-circle-notch"></i> 互访圈列表</span>
        <span class="admin-text-muted">共 <?= number_format($totalCircles) ?> 个互访圈</span>
    </div>
    <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>创建者</th>
                    <th>城市</th>
                    <th>区块数</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($circles)): ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state">
                            <i class="fas fa-users-slash"></i>
                            <p>没有找到互访圈</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($circles as $c): ?>
                        <?php $creator = $user->getUserById($c['user_id']); ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i class="fas fa-circle" style="font-size:10px;color:<?= $c['status'] === 'active' ? 'var(--admin-success)' : 'var(--admin-text-muted)' ?>;"></i>
                                    <strong><?= htmlspecialchars($c['name']) ?></strong>
                                </div>
                                <small class="admin-text-muted" style="display:block;margin-top:4px;margin-left:18px;"><?= htmlspecialchars(mb_substr($c['description'], 0, 30)) ?><?= mb_strlen($c['description']) > 30 ? '...' : '' ?></small>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <img src="../assets/images/<?= htmlspecialchars($creator['avatar'] ?? 'default.jpg') ?>" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                    <span><?= htmlspecialchars($creator['username']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($c['city']) ?></td>
                            <td><?= $c['block_count'] ?></td>
                            <td>
                                <?php if ($c['status'] === 'active'): ?>
                                    <span class="admin-badge success">活跃</span>
                                <?php else: ?>
                                    <span class="admin-badge default">停用</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
                            <td>
                                <div class="admin-btn-group">
                                    <a href="../circles/view.php?id=<?= $c['id'] ?>" class="admin-btn admin-btn-sm admin-btn-default" title="查看"><i class="fas fa-eye"></i></a>
                                    <?php if ($c['status'] === 'active'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="circle_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-default" title="停用" onclick="return confirm('确定要停用此互访圈吗？')"><i class="fas fa-ban"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="circle_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-default" title="激活" onclick="return confirm('确定要激活此互访圈吗？')"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="circle_id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" title="删除" onclick="return confirm('确定要删除此互访圈吗？此操作不可恢复！')"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php $prefix = '?search=' . urlencode($search) . '&status=' . $status . '&'; ?>
        <div class="admin-pagination">
            <div class="admin-page-info">第 <?= $page ?> 页 / 共 <?= $totalPages ?> 页</div>
            <div class="admin-page-buttons">
                <a href="circles.php<?= $prefix ?>page=1" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">首页</a>
                <a href="circles.php<?= $prefix ?>page=<?= $page - 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">上一页</a>
                <?php $start = max(1, $page - 2); $end = min($totalPages, $page + 2); ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="circles.php<?= $prefix ?>page=<?= $i ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-default' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="circles.php<?= $prefix ?>page=<?= $page + 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">下一页</a>
                <a href="circles.php<?= $prefix ?>page=<?= $totalPages ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">末页</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
