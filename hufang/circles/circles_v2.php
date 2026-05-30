<?php
require_once '../../config/database.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';
require_once '../../classes/Visit.php';

$userId = $_GET['user_id'] ?? 0;
$circle = new Circle($pdo);
$user = new User($pdo);
$visit = new Visit($pdo);

// 获取用户信息
$userInfo = $user->getUserById($userId);
if (!$userInfo) {
    header('Location: index.php');
    exit;
}

// 获取用户的互访圈列表
$circles = $circle->getUserCircles($userId);

// 获取每个互访圈的完成互访次数
foreach ($circles as &$circleItem) {
    $circleItem['completed_visits'] = $visit->getCompletedVisitsCount($circleItem['id']);
}
unset($circleItem);
?>

<?php require_once '../includes/header.php'; ?>

<div class="container user-circles-container">
    <div class="user-profile-header mb-3">
        <div class="d-flex align-items-center">
            <img src="../assets/images/<?= htmlspecialchars($userInfo['avatar'] ?? 'default.jpg') ?>" 
                 class="avatar-sm rounded-circle mr-2">
            <h5 class="mb-0"><?= htmlspecialchars($userInfo['username']) ?>的互访圈</h5>
        </div>
    </div>

    <div class="compact-circles-table">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th width="120">所属城市</th>
                    <th>互访圈名称</th>
                    <th width="80" class="text-end">区块数</th>
                    <th width="80" class="text-end">完成互访</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($circles as $circle): ?>
                <tr onclick="window.location='view.php?id=<?= $circle['id'] ?>'" style="cursor:pointer;">
                    <td><?= htmlspecialchars($circle['city']) ?></td>
                    <td class="font-weight-medium" style="padding:0.6rem 0.5rem"><?= htmlspecialchars($circle['name']) ?></td>
                    <td class="text-end"><?= $circle['block_count'] ?></td>
                    <td class="text-end"><?= $circle['completed_visits'] ?? 0 ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (empty($circles)): ?>
        <div class="empty-state p-3 text-center">
            <i class="fas fa-users-slash text-muted mb-2"></i>
            <p class="text-muted mb-0">该用户暂无互访圈</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-3">
        <small class="text-muted">互访圈列表由 <a href="https://v.58.tl">v.58.tl</a> 生成</small>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>