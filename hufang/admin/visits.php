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

<div class="container admin-container">
    <!-- 页面标题和面包屑导航 -->
    <div class="admin-header">
        <h1><i class="fas fa-exchange-alt"></i> 互访记录管理</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> 仪表盘</a></li>
                <li class="breadcrumb-item active" aria-current="page">互访记录</li>
            </ol>
        </nav>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['message'] ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- 搜索和筛选 -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="search-form">
                <div class="row">
                    <div class="col-md-8 mb-3 mb-md-0">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="搜索访问者、互访圈名称或城市..." value="<?= htmlspecialchars($search) ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> 搜索
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <select name="status" class="form-control">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>所有状态</option>
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>待确认</option>
                                <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>已确认</option>
                                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>已完成</option>
                                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>已取消</option>
                            </select>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" onclick="window.location.href='visits.php'">
                                    <i class="fas fa-sync-alt"></i> 重置
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 互访记录列表 -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">互访记录列表</h5>
            <div class="text-muted small">共 <?= $totalVisits ?> 条记录</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="15%">访问者</th>
                            <th width="15%">互访圈</th>
                            <th width="10%">城市</th>
                            <th width="10%">访问日期</th>
                            <th width="10%">回访日期</th>
                            <th width="10%">状态</th>
                            <th width="15%">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($visits)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                        <h4 class="text-muted">没有找到互访记录</h4>
                                        <p class="text-muted">尝试修改搜索条件</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($visits as $visit): ?>
                                <?php 
                                    $visitor = $user->getUserById($visit['visitor_id']);
                                    $circleInfo = $circle->getCircleById($visit['circle_id']);
                                    $owner = $user->getUserById($circleInfo['user_id']);
                                ?>
                                <tr>
                                    <td><?= $visit['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/images/<?= htmlspecialchars($visitor['avatar']) ?>" alt="<?= htmlspecialchars($visitor['username']) ?>" class="rounded-circle avatar-sm mr-2" style="width:32px;height:32px;object-fit:cover;flex-shrink:0;">
                                            <span><?= htmlspecialchars($visitor['username']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="circle-logo mr-2">
                                                <i class="fas fa-circle text-<?= $circleInfo['status'] === 'active' ? 'success' : 'secondary' ?>"></i>
                                            </div>
                                            <span><?= htmlspecialchars($circleInfo['name']) ?></span>
                                        </div>
                                        <small class="text-muted d-block mt-1">创建者: <?= htmlspecialchars($owner['username']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($circleInfo['city']) ?></td>
                                    <td><?= $visit['visit_date'] ? date('Y-m-d', strtotime($visit['visit_date'])) : '-' ?></td>
                                    <td><?= $visit['return_date'] ? date('Y-m-d', strtotime($visit['return_date'])) : '-' ?></td>
                                    <td>
                                        <span class="badge badge-<?= 
                                            $visit['status'] === 'completed' ? 'success' : 
                                            ($visit['status'] === 'confirmed' ? 'primary' : 
                                            ($visit['status'] === 'pending' ? 'warning' : 'secondary')) ?>">
                                            <?= $visit['status'] === 'completed' ? '已完成' : 
                                              ($visit['status'] === 'confirmed' ? '已确认' : 
                                              ($visit['status'] === 'pending' ? '待确认' : '已取消')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="visit_detail.php?id=<?= $visit['id'] ?>" class="btn btn-outline-primary" title="详情">
                                                <i class="fas fa-info-circle"></i>
                                            </a>
                                            
                                            <?php if ($visit['status'] === 'pending'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="visit_id" value="<?= $visit['id'] ?>">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit" class="btn btn-outline-success" title="确认" onclick="return confirm('确定要确认此互访吗？')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($visit['status'] === 'confirmed'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="visit_id" value="<?= $visit['id'] ?>">
                                                    <input type="hidden" name="action" value="complete">
                                                    <button type="submit" class="btn btn-outline-info" title="完成" onclick="return confirm('确定要标记此互访为已完成吗？')">
                                                        <i class="fas fa-flag-checkered"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="visit_id" value="<?= $visit['id'] ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn btn-outline-warning" title="取消" onclick="return confirm('确定要取消此互访吗？')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="visit_id" value="<?= $visit['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-outline-danger" title="删除" onclick="return confirm('确定要删除此互访记录吗？此操作不可恢复！')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>