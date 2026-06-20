<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';
require_once '../../classes/Notification.php';

checkLogin();

$userId = $_SESSION['user_id'];
$circle = new Circle($pdo);
$visit = new Visit($pdo);

// 获取用户数据
$userCircles = $circle->getUserCircles($userId);
$pendingVisits = $visit->getCircleVisits($userId, 'pending');
$confirmedVisits = $visit->getCircleVisits($userId, 'confirmed');
$completedVisits = $visit->getCircleVisits($userId, 'completed');
$myVisits = $visit->getUserVisits($userId);
$notification = new Notification($pdo);
$unreadNotifications = $notification->getUnreadCount($userId);
$recentNotifications = $notification->getUserNotifications($userId, 5);
?>

<?php require_once '../includes/header.php'; ?>


<div class="container dashboard-container">
    <div class="dashboard-header">
		<div class="row" style="align-items:center;margin:0;">
			<div class="col-md-8" style="padding:0;">
				<h1><i class="fas fa-tachometer-alt"></i> 我的互访圈仪表盘</h1>
				<p>欢迎回来，<?= htmlspecialchars($_SESSION['username']) ?>！这里是您管理互访圈和访问记录的中心</p>
			</div>
			
			<div class="col-md-4 text-right" style="padding:0;">
					<a href="../circles/create.php" class="btn btn-primary">
						<i class="fas fa-plus"></i> 创建互访圈
					</a>
					<a href="profile.php" class="btn btn-default">
						<i class="fas fa-cog"></i> 账户设置
					</a>
			</div>
		</div>
    </div>

    <div class="row quick-stats">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?= count($userCircles) ?></h3>
                    <p>我的互访圈</p>
                </div>
                <a href="circles.php" class="stat-link">查看全部 <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?= count($pendingVisits) ?></h3>
                    <p>待处理请求</p>
                </div>
                <a href="visits.php?status=pending" class="stat-link">处理请求 <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-info">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?= count($confirmedVisits) ?></h3>
                    <p>已确认访问</p>
                </div>
                <a href="visits.php?status=confirmed" class="stat-link">查看详情 <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= count($completedVisits) ?></h3>
                    <p>已完成互访</p>
                </div>
                <a href="visits.php?status=completed" class="stat-link">查看记录 <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <!-- 我的互访圈 -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> 我的互访圈</h3>
                    <a href="../circles/create.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> 创建新互访圈
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($userCircles)): ?>
                        <?= renderEmptyState('users-slash', '您还没有创建任何互访圈', '创建一个互访圈，开始与其他用户互动吧',
                            '<a href="../circles/create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> 创建第一个互访圈</a>') ?>
                    <?php else: ?>
                        <div class="circle-grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                            <?php foreach (array_slice($userCircles, 0, 5) as $circle): ?>
                                <?= renderCircleCard($circle, null, '', '', true) ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($userCircles) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="circles.php" class="btn btn-sm btn-outline-secondary">
                                    查看全部 <?= count($userCircles) ?> 个互访圈 <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- 我的访问记录 -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-exchange-alt"></i> 最近的访问记录</h3>
                    <a href="visits.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-list"></i> 查看全部
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($myVisits)): ?>
                        <?= renderEmptyState('exchange-alt', '您还没有任何访问记录', '申请互访后会在这里显示') ?>
                    <?php else: ?>
                        <div class="visit-list">
                            <?php foreach (array_slice($myVisits, 0, 5) as $visit): ?>
                                <?= renderVisitItem($visit, false) ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($myVisits) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="visits.php" class="btn btn-sm btn-outline-secondary">
                                    查看全部 <?= count($myVisits) ?> 条记录 <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 通知与待处理聚合 -->
    <?php if ($unreadNotifications > 0 || !empty($pendingVisits)): ?>
    <div class="dashboard-card mt-4">
        <div class="card-header">
            <h3><i class="fas fa-bell"></i> 需要处理的事项</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <?php if ($unreadNotifications > 0): ?>
                <div class="col-md-6 mb-3">
                    <div class="d-flex align-items-center p-3 border rounded">
                        <i class="fas fa-bell fa-2x text-warning mr-3"></i>
                        <div>
                            <h5 class="mb-1"><?= $unreadNotifications ?> 条未读通知</h5>
                            <a href="notifications.php" class="btn btn-sm btn-outline-primary">查看通知</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($pendingVisits)): ?>
                <div class="col-md-6 mb-3">
                    <div class="d-flex align-items-center p-3 border rounded">
                        <i class="fas fa-clock fa-2x text-info mr-3"></i>
                        <div>
                            <h5 class="mb-1"><?= count($pendingVisits) ?> 个待处理请求</h5>
                            <a href="visits.php?status=pending" class="btn btn-sm btn-outline-info">处理请求</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 待处理的访问请求 -->
    <div class="dashboard-card mt-4">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> 待处理的访问请求</h3>
            <span class="badge badge-warning"><?= count($pendingVisits) ?> 个待处理</span>
        </div>
        <div class="card-body">
            <?php if (empty($pendingVisits)): ?>
                <div class="empty-state-sm">
                    <i class="fas fa-check-circle"></i>
                    <p>暂无待处理的访问请求</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table visits-table">
                        <thead>
                            <tr>
                                <th>访问者</th>
                                <th>互访圈</th>
                                <th>申请时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($pendingVisits, 0, 5) as $visit): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <img src="../assets/images/<?= htmlspecialchars($visit['avatar'] ?? 'default.jpg') ?>" 
                                                 class="avatar-sm" alt="<?= htmlspecialchars($visit['username']) ?>">
                                            <span><?= htmlspecialchars($visit['username']) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($visit['circle_name']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($visit['created_at'])) ?></td>
                                    <td>
                                        <a href="confirm_visit.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-check"></i> 确认
                                        </a>
                                        <a href="visit_detail.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-info-circle"></i> 详情
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($pendingVisits) > 5): ?>
                    <div class="text-center mt-3">
                        <a href="visits.php?status=pending" class="btn btn-sm btn-outline-secondary">
                            查看全部 <?= count($pendingVisits) ?> 条请求 <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>