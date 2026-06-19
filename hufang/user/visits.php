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
    'completed' => []
];

foreach ($visits as $v) {
    $status = $v['status'];
    if (isset($visitsByStatus[$status])) {
        $visitsByStatus[$status][] = $v;
    }
}

// 渲染访问列表的辅助函数
function renderVisitList($list, $circleId) {
    if (empty($list)) {
        echo '<div class="empty-state">
                <div class="empty-icon"><i class="fas fa-exchange-alt"></i></div>
                <h3>暂无访问记录</h3>
                <p>这里还没有任何访问记录</p>
              </div>';
        return;
    }
    echo '<div class="visit-list">';
    foreach ($list as $v) {
        $statusLabels = ['pending'=>'待处理','confirmed'=>'已确认','completed'=>'已完成','cancelled'=>'已取消'];
        $label = $statusLabels[$v['status']] ?? $v['status'];
        echo '<div class="visit-item status-'.$v['status'].'">
            <div class="visit-user">
                <img src="../assets/images/'.htmlspecialchars($v['avatar'] ?? 'default.jpg').'" class="avatar">
                <span class="username">'.htmlspecialchars($v['username'] ?? '').'</span>
            </div>
            <div class="visit-info">
                <div class="info-row">
                    <span class="label">互访圈:</span>
                    <span class="value">'.htmlspecialchars($v['circle_name']).'</span>
                </div>
                <div class="info-row">
                    <span class="label">状态:</span>
                    <span class="status-badge '.$v['status'].'">'.$label.'</span>
                </div>';
        if (!empty($v['visit_date'])) {
            echo '<div class="info-row"><span class="label">访问日期:</span><span class="value">'.htmlspecialchars($v['visit_date']).'</span></div>';
        }
        if (!empty($v['return_date'])) {
            echo '<div class="info-row"><span class="label">回访日期:</span><span class="value">'.htmlspecialchars($v['return_date']).'</span></div>';
        }
        echo '</div>
            <div class="visit-actions">';
        if ($v['status'] == 'pending' && $circleId) {
            echo '<a href="confirm_visit.php?id='.$v['id'].'" class="btn btn-sm btn-primary"><i class="fas fa-check"></i> 确认</a>';
        } elseif ($v['status'] == 'confirmed' && !$circleId) {
            echo '<a href="record_return.php?id='.$v['id'].'" class="btn btn-sm btn-success"><i class="fas fa-check-double"></i> 记录回访</a>';
        }
        echo '<a href="visit_detail.php?id='.$v['id'].'" class="btn btn-sm btn-secondary"><i class="fas fa-info-circle"></i> 详情</a>
            </div>
        </div>';
    }
    echo '</div>';
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
            <div class="tab-item active" data-tab="all" onclick="switchVisitTab('all')">全部记录 (<?= count($visits) ?>)</div>
            <div class="tab-item" data-tab="pending" onclick="switchVisitTab('pending')">待处理 (<?= count($visitsByStatus['pending']) ?>)</div>
            <div class="tab-item" data-tab="confirmed" onclick="switchVisitTab('confirmed')">已确认 (<?= count($visitsByStatus['confirmed']) ?>)</div>
            <div class="tab-item" data-tab="completed" onclick="switchVisitTab('completed')">已完成 (<?= count($visitsByStatus['completed']) ?>)</div>
        </div>
        
        <div class="tabs-content">
            <div class="tab-pane active" id="tab-all">
                <?php renderVisitList($visits, $circleId); ?>
            </div>
            <div class="tab-pane" id="tab-pending">
                <?php renderVisitList($visitsByStatus['pending'], $circleId); ?>
            </div>
            <div class="tab-pane" id="tab-confirmed">
                <?php renderVisitList($visitsByStatus['confirmed'], $circleId); ?>
            </div>
            <div class="tab-pane" id="tab-completed">
                <?php renderVisitList($visitsByStatus['completed'], $circleId); ?>
            </div>
        </div>
    </div>
</div>

<script>
function switchVisitTab(name) {
    document.querySelectorAll('.visit-tabs .tab-item').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.visit-tabs .tab-pane').forEach(p => p.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}
</script>

<?php require_once '../includes/footer.php'; ?>
