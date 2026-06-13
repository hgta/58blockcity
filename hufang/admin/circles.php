<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';

// 检查是否为超级管理员
checkLogin();

$userId = $_SESSION['user_id'];
if ($userId != 1) {
    header('Location: ../user/dashboard.php');
    exit();
}

// 初始化类
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
?>

<?php require_once '../includes/header.php'; ?>

<div class="container admin-container">
    <!-- 页面标题和面包屑导航 -->
    <div class="admin-header">
        <h1><i class="fas fa-users-circle"></i> 互访圈管理</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> 仪表盘</a></li>
                <li class="breadcrumb-item active" aria-current="page">互访圈管理</li>
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
                            <input type="text" name="search" class="form-control" placeholder="搜索互访圈名称、描述或城市..." value="<?= htmlspecialchars($search) ?>">
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
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>活跃</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>停用</option>
                            </select>
                            <div class="input-group-append">
                                <a href="circle_add.php" class="btn btn-success">
                                    <i class="fas fa-plus"></i> 添加互访圈
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 互访圈列表 -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">互访圈列表</h5>
            <div class="text-muted small">共 <?= $totalCircles ?> 个互访圈</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="20%">名称</th>
                            <th width="15%">创建者</th>
                            <th width="15%">城市</th>
                            <th width="15%">区块数</th>
                            <th width="10%">状态</th>
                            <th width="10%">创建时间</th>
                            <th width="10%">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($circles)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                                        <h4 class="text-muted">没有找到互访圈</h4>
                                        <p class="text-muted">尝试修改搜索条件或添加新互访圈</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($circles as $circle): ?>
                                <tr>
                                    <td><?= $circle['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="circle-logo mr-2">
                                                <i class="fas fa-circle text-<?= $circle['status'] === 'active' ? 'success' : 'secondary' ?>"></i>
                                            </div>
                                            <strong><?= htmlspecialchars($circle['name']) ?></strong>
                                        </div>
                                        <small class="text-muted d-block mt-1"><?= htmlspecialchars(mb_substr($circle['description'], 0, 20)) ?>...</small>
                                    </td>
                                    <td>
                                        <?php $creator = $user->getUserById($circle['user_id']); ?>
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/images/<?= htmlspecialchars($creator['avatar']) ?>" alt="<?= htmlspecialchars($creator['username']) ?>" class="rounded-circle avatar-sm mr-2" style="width:32px;height:32px;object-fit:cover;flex-shrink:0;">
                                            <span><?= htmlspecialchars($creator['username']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($circle['city']) ?></td>
                                    <td><?= $circle['block_count'] ?></td>
                                    <td>
                                        <span class="badge badge-<?= $circle['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= $circle['status'] === 'active' ? '活跃' : '停用' ?>
                                        </span>
                                    </td>
                                    <td><?= date('Y-m-d', strtotime($circle['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../circles/view.php?id=<?= $circle['id'] ?>" class="btn btn-outline-primary" title="查看">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="circle_edit.php?id=<?= $circle['id'] ?>" class="btn btn-outline-info" title="编辑">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($circle['status'] === 'active'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="circle_id" value="<?= $circle['id'] ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <button type="submit" class="btn btn-outline-warning" title="停用" onclick="return confirm('确定要停用此互访圈吗？')">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="circle_id" value="<?= $circle['id'] ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn btn-outline-success" title="激活" onclick="return confirm('确定要激活此互访圈吗？')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="circle_id" value="<?= $circle['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-outline-danger" title="删除" onclick="return confirm('确定要删除此互访圈吗？此操作不可恢复！')">
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

<?php require_once '../includes/footer.php'; ?>