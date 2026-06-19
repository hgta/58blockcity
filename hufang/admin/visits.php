<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Visit.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';

// 检查管理员权限
checkAdmin();
$visit = new Visit($pdo);
$circle = new Circle($pdo);
$user = new User($pdo);

// 处理搜索和筛选
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;

// 获取互访记录数据
$totalVisits = $visit->getTotalVisitsCount($search, $status);
$visits = $visit->getVisitsWithPagination($page, $perPage, $search, $status);
$totalPages = ceil($totalVisits / $perPage);

// 处理记录状态变更
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $visitId = intval($_POST['visit_id']);
    
    switch ($_POST['action']) {
        case 'confirm':
            $visit->confirmVisit($visitId, date('Y-m-d'));
            $_SESSION['message'] = '互访已确认';
            break;
        case 'complete':
            $visit->recordReturn($visitId, date('Y-m-d'));
            $_SESSION['message'] = '互访已完成';
            break;
        case 'cancel':
            $visit->updateVisitStatus($visitId, 'cancelled');
            $_SESSION['message'] = '互访已取消';
            break;
        case 'delete':
            $visit->deleteVisit($visitId);
            $_SESSION['message'] = '互访记录已删除';
            break;
    }
    header('Location: visits.php');
    exit();
}

$admin_site_config = ['site' => 'hufang', 'page_title' => '互访记录'];
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
        <span class="admin-card-title"><i class="fas fa-filter"></i> 搜索互访记录</span>
    </div>
    <form method="get" class="admin-form-row">
        <div class="admin-form-group" style="flex:2;">
            <input type="text" name="search" class="admin-form-input" placeholder="搜索访问者、互访圈名称或城市..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="admin-form-group">
            <select name="status" class="admin-form-select">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>所有状态</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>待确认</option>
                <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>已确认</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>已完成</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>已取消</option>
            </select>
        </div>
        <div class="admin-form-group">
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> 搜索</button>
            <a href="visits.php" class="admin-btn admin-btn-default"><i class="fas fa-undo"></i> 重置</a>
        </div>
    </form>
</div>

<!-- 互访记录列表 -->
<div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;">
        <span class="admin-card-title"><i class="fas fa-exchange-alt"></i> 互访记录列表</span>
        <span class="admin-text-muted">共 <?= number_format($totalVisits) ?> 条记录</span>
    </div>
    <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>访问者</th>
                    <th>互访圈</th>
                    <th>城市</th>
                    <th>访问日期</th>
                    <th>回访日期</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visits)): ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <p>没有找到互访记录</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($visits as $v): ?>
                        <?php 
                            $visitor = $user->getUserById($v['visitor_id']);
                            $circleInfo = $circle->getCircleById($v['circle_id']);
                            $owner = $user->getUserById($circleInfo['user_id']);
                        ?>
                        <tr>
                            <td><?= $v['id'] ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <img src="../assets/images/<?= htmlspecialchars($visitor['avatar'] ?? 'default.jpg') ?>" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                    <span><?= htmlspecialchars($visitor['username']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span><?= htmlspecialchars($circleInfo['name']) ?></span>
                                <small class="admin-text-muted" style="display:block;">创建者: <?= htmlspecialchars($owner['username']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($circleInfo['city']) ?></td>
                            <td><?= $v['visit_date'] ? date('Y-m-d', strtotime($v['visit_date'])) : '-' ?></td>
                            <td><?= $v['return_date'] ? date('Y-m-d', strtotime($v['return_date'])) : '-' ?></td>
                            <td>
                                <?php
                                $statusMap = [
                                    'completed' => ['已完成', 'success'],
                                    'confirmed' => ['已确认', 'info'],
                                    'pending'   => ['待确认', 'warning'],
                                    'cancelled' => ['已取消', 'default'],
                                ];
                                $s = $statusMap[$v['status']] ?? [$v['status'], 'default'];
                                ?>
                                <span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span>
                            </td>
                            <td>
                                <div class="admin-btn-group">
                                    <a href="visit_detail.php?id=<?= $v['id'] ?>" class="admin-btn admin-btn-sm admin-btn-default" title="详情"><i class="fas fa-info-circle"></i></a>
                                    <?php if ($v['status'] === 'pending'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-default" title="确认" onclick="return confirm('确定要确认此互访吗？')"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php elseif ($v['status'] === 'confirmed'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-default" title="完成" onclick="return confirm('确定要标记此互访为已完成吗？')"><i class="fas fa-flag-checkered"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($v['status'] !== 'completed' && $v['status'] !== 'cancelled'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-default" title="取消" onclick="return confirm('确定要取消此互访吗？')"><i class="fas fa-times"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="visit_id" value="<?= $v['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" title="删除" onclick="return confirm('确定要删除此互访记录吗？此操作不可恢复！')"><i class="fas fa-trash"></i></button>
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
                <a href="visits.php<?= $prefix ?>page=1" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">首页</a>
                <a href="visits.php<?= $prefix ?>page=<?= $page - 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page <= 1 ? 'disabled' : '' ?>">上一页</a>
                <?php $start = max(1, $page - 2); $end = min($totalPages, $page + 2); ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="visits.php<?= $prefix ?>page=<?= $i ?>" class="admin-btn admin-btn-sm <?= $i === $page ? 'admin-btn-primary' : 'admin-btn-default' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="visits.php<?= $prefix ?>page=<?= $page + 1 ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">下一页</a>
                <a href="visits.php<?= $prefix ?>page=<?= $totalPages ?>" class="admin-btn admin-btn-sm admin-btn-default <?= $page >= $totalPages ? 'disabled' : '' ?>">末页</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
