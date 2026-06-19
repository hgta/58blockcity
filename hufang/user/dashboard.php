<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';

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
                        <div class="empty-state-sm">
                            <i class="fas fa-users-slash"></i>
                            <p>您还没有创建任何互访圈</p>
                            <a href="../circles/create.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> 创建第一个互访圈
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="circle-list-mini">
                            <?php foreach (array_slice($userCircles, 0, 5) as $circle): ?>
                                <div class="circle-item">
                                    <div class="circle-info">
                                        <h4><?= htmlspecialchars($circle['name']) ?></h4>
                                        <div class="circle-meta">
                                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($circle['city']) ?></span>
                                            <span><i class="fas fa-cube"></i> <?= $circle['block_count'] ?> 区块</span>
                                        </div>
                                    </div>
                                    <div class="circle-actions">
                                        <a href="../circles/view.php?id=<?= $circle['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> 查看
                                        </a>
                                    </div>
                                </div>
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
                        <div class="empty-state-sm">
                            <i class="fas fa-exchange-alt"></i>
                            <p>您还没有任何访问记录</p>
                        </div>
                    <?php else: ?>
                        <div class="visit-list-mini">
                            <?php foreach (array_slice($myVisits, 0, 5) as $visit): ?>
                                <div class="visit-item status-<?= $visit['status'] ?>">
                                    <div class="visit-info">
                                        <h4><?= htmlspecialchars($visit['circle_name']) ?></h4>
                                        <div class="visit-meta">
                                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($visit['circle_owner']) ?></span>
                                            <span class="status-badge <?= $visit['status'] ?>"><?= $visit['status'] ?></span>
                                        </div>
                                    </div>
                                    <div class="visit-date">
                                        <?php if ($visit['visit_date']): ?>
                                            <i class="fas fa-calendar-day"></i> <?= $visit['visit_date'] ?>
                                        <?php else: ?>
                                            <i class="fas fa-clock"></i> 待确认
                                        <?php endif; ?>
                                    </div>
                                </div>
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