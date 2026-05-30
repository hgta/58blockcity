<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';

checkLogin();

$userId = $_SESSION['user_id'];
$circleId = $_GET['circle_id'] ?? null;
$visit = new Visit($pdo);
$circle = new Circle($pdo);

if ($circleId) {
    $circleInfo = $circle->getCircleById($circleId);
    $visits = $visit->getCircleVisits($circleId);
    $pageTitle = "互访圈: ".htmlspecialchars($circleInfo['name'])." 的访问记录";
} else {
    $visits = $visit->getUserVisits($userId);
    $pageTitle = "我的所有访问记录";
}

// 按状态分类
$visitsByStatus = [
    'pending' => [],
    'confirmed' => [],
    'visited' => [],
    'completed' => []
];

foreach ($visits as $v) {
    $visitsByStatus[$v['status']][] = $v;
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container user-container">
    <div class="user-header">
        <h2><i class="fas fa-exchange-alt"></i> <?= $pageTitle ?></h2>
        
        <?php if ($circleId): ?>
            <a href="circles.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> 返回我的互访圈
            </a>
        <?php endif; ?>
    </div>

    <div class="visit-tabs">
        <div class="tabs-header">
            <div class="tab-item active" data-tab="all">全部记录</div>
            <div class="tab-item" data-tab="pending">待处理 (<?= count($visitsByStatus['pending']) ?>)</div>
            <div class="tab-item" data-tab="confirmed">已确认 (<?= count($visitsByStatus['confirmed']) ?>)</div>
            <div class="tab-item" data-tab="completed">已完成 (<?= count($visitsByStatus['completed']) ?>)</div>
        </div>
        
        <div class="tabs-content">
            <!-- 全部记录 -->
            <div class="tab-pane active" id="tab-all">
                <?php if (empty($visits)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <h3>暂无访问记录</h3>
                        <p>您还没有任何访问记录，快去发现其他互访圈吧</p>
                    </div>
                <?php else: ?>
                    <div class="visit-list">
                        <?php foreach ($visits as $visit): ?>
                            <div class="visit-item status-<?= $visit['status'] ?>">
                                <div class="visit-user">
                                    <img src="../assets/images/<?= htmlspecialchars($visit['avatar'] ?? 'default.jpg') ?>" class="avatar">
                                    <span class="username"><?= htmlspecialchars($visit['username']) ?></span>
                                </div>
                                
                                <div class="visit-info">
                                    <div class="info-row">
                                        <span class="label">互访圈:</span>
                                        <span class="value"><?= htmlspecialchars($visit['circle_name']) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">状态:</span>
                                        <span class="status-badge <?= $visit['status'] ?>"><?= $visit['status'] ?></span>
                                    </div>
                                    <?php if ($visit['visit_date']): ?>
                                        <div class="info-row">
                                            <span class="label">访问日期:</span>
                                            <span class="value"><?= $visit['visit_date'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($visit['return_date']): ?>
                                        <div class="info-row">
                                            <span class="label">回访日期:</span>
                                            <span class="value"><?= $visit['return_date'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="visit-actions">
                                    <?php if ($visit['status'] == 'pending' && $circleId): ?>
                                        <a href="confirm_visit.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-check"></i> 确认
                                        </a>
                                    <?php elseif ($visit['status'] == 'confirmed' && !$circleId): ?>
                                        <a href="record_return.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-check-double"></i> 记录回访
                                        </a>
                                    <?php endif; ?>
                                    <a href="visit_detail.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-info-circle"></i> 详情
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 其他状态选项卡内容... -->
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>