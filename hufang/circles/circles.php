<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';

checkLogin();

$userId = $_GET['user_id'] ?? $_SESSION['user_id'];
$currentUserId = $_SESSION['user_id'];

$circle = new Circle($pdo);
$user = new User($pdo);

// 获取用户信息
$profileUser = $user->getUserById($userId);
if (!$profileUser) {
    header("Location: ../index.php");
    exit;
}

// 获取用户的圈子及正确的访问统计数据
$userCircles = $circle->getUserCirclesWithAccurateStats($userId);

// 计算用户的总统计数据
$totalStats = [
    'total_circles' => count($userCircles),
    'total_blocks' => array_sum(array_column($userCircles, 'block_count')),
    'total_visits' => array_sum(array_column($userCircles, 'total_visits')),
    'completed_visits' => array_sum(array_column($userCircles, 'completed_visits')),
    'unique_visitors' => array_sum(array_column($userCircles, 'unique_visitors'))
];

// 检查是否是查看自己的圈子
$isOwnProfile = ($userId == $currentUserId);
?>

<?php require_once '../includes/header.php'; ?>

<style>
/* 统计卡片样式 */
.stat-card {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* 圈子头像样式 */
.circle-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
}

/* 粘性侧边栏 */
.profile-card {
    position: sticky;
    top: 20px;
}
</style>

<div class="container">
    <div class="page-header">
        <h1>
            <i class="fas fa-users"></i> 
            <?= htmlspecialchars($isOwnProfile ? '我的互访圈' : $profileUser['username'].'的互访圈') ?>
        </h1>
        <?php if ($isOwnProfile): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> 创建新圈子
            </a>
        <?php endif; ?>
    </div>

    <!-- 统计概览 -->
    <div class="row mb-4">
        <div class="col-md-2 col-6">
            <div class="stat-card text-center">
                <div class="stat-number text-primary"><?= $totalStats['total_circles'] ?></div>
                <div class="stat-label">圈子数量</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card text-center">
                <div class="stat-number text-success"><?= $totalStats['total_blocks'] ?></div>
                <div class="stat-label">区块总数</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card text-center">
                <div class="stat-number text-info"><?= $totalStats['total_visits'] ?></div>
                <div class="stat-label">总访问次数</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card text-center">
                <div class="stat-number text-warning"><?= $totalStats['completed_visits'] ?></div>
                <div class="stat-label">完成互访</div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="stat-card text-center">
                <div class="stat-number text-danger"><?= $totalStats['unique_visitors'] ?></div>
                <div class="stat-label">独立访客</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card profile-card">
                <div class="card-body text-center">
                    <img src="../assets/images/<?= htmlspecialchars($profileUser['avatar'] ?? 'default.jpg') ?>" 
                         class="avatar-lg rounded-circle mb-3" alt="用户头像">
                    <h4><?= htmlspecialchars($profileUser['username']) ?></h4>
                    <p class="text-muted">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?= htmlspecialchars($profileUser['city'] ?? '未知城市') ?>
                    </p>
                    <div class="user-stats mt-3">
                        <small class="text-muted">
                            注册时间: <?= date('Y-m-d', strtotime($profileUser['created_at'])) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <?php if (empty($userCircles)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <h4><?= $isOwnProfile ? '您还没有创建任何互访圈' : '该用户尚未创建任何互访圈' ?></h4>
                    <p class="mb-3">互访圈是您与其他用户交流访问的平台</p>
                    <?php if ($isOwnProfile): ?>
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> 创建您的第一个互访圈
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">
                            <i class="fas fa-list"></i> 圈子列表
                            <span class="badge badge-primary"><?= count($userCircles) ?></span>
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>圈子名称</th>
                                        <th>城市</th>
                                        <th>区块数</th>
                                        <th>被访问次数</th>
                                        <th>完成互访</th>
                                        <!--<th>独立访客</th>-->
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userCircles as $circle): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="circle-avatar mr-2">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                                <div>
                                                    <a href="view.php?id=<?= $circle['id'] ?>" class="font-weight-bold">
                                                        <?= htmlspecialchars($circle['name']) ?>
                                                    </a>
                                                    <?php if ($circle['description'] && 0): ?>
                                                        <small class="d-block text-muted">
                                                            <?= mb_substr(htmlspecialchars($circle['description']), 0, 30) ?>...
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-light">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($circle['city']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-success"><?= $circle['block_count'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?= $circle['total_visits'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-warning"><?= $circle['completed_visits'] ?></span>
                                        </td>
                                        <!--<td>
                                            <span class="badge badge-danger"><?= $circle['unique_visitors'] ?></span>
                                        </td>-->
                                        <td>
                                            <small class="text-muted">
                                                <?= date('m-d', strtotime($circle['created_at'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?= $circle['id'] ?>" class="btn btn-outline-primary" title="查看">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($isOwnProfile): ?>
                                                    <a href="edit.php?id=<?= $circle['id'] ?>" class="btn btn-outline-secondary" title="编辑">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="manage_visits.php?id=<?= $circle['id'] ?>" class="btn btn-outline-info" title="管理访问">
                                                        <i class="fas fa-handshake"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>
.stat-card {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
}
.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}
.stat-label {
    font-size: 0.875rem;
    color: #666;
}
.circle-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.profile-card {
    position: sticky;
    top: 20px;
}
.user-stats {
    border-top: 1px solid #eee;
    padding-top: 1rem;
}
</style>