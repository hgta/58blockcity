<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Notification.php';

checkLogin();

$userId = $_SESSION['user_id'];
$notification = new Notification($pdo);

// 标记单条或全部已读
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
        ->execute([$userId]);
    $_SESSION['flash_message'] = '所有通知已标记为已读';
    header('Location: notifications.php');
    exit;
}

if (isset($_GET['mark_read'])) {
    $nid = intval($_GET['mark_read']);
    $notification->markAsRead($nid, $userId);
    header('Location: notifications.php');
    exit;
}

$notifications = $notification->getUserNotifications($userId, 50);
$unreadCount = $notification->getUnreadCount($userId);

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-bell"></i> 通知中心</h1>
        <?php if ($unreadCount > 0): ?>
            <form method="post" class="d-inline">
                            <?= csrfField() ?>
                <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                    <i class="fas fa-check-double"></i> 全部标记已读
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
        <div class="empty-state text-center py-5">
            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
            <p class="text-muted">暂无通知</p>
        </div>
    <?php else: ?>
        <div class="list-group notification-list">
            <?php foreach ($notifications as $n): ?>
                <?php
                $link = '#';
                if ($n['type'] === 'visit_request') {
                    $link = "../user/visits.php?circle_id=" . intval($n['related_id']);
                } elseif ($n['type'] === 'visit_confirm') {
                    $link = "../user/visit_detail.php?id=" . intval($n['related_id']);
                } elseif ($n['type'] === 'return_confirm') {
                    $link = "../user/visit_detail.php?id=" . intval($n['related_id']);
                }
                $badge = empty($n['is_read']) ? '<span class="badge badge-primary">未读</span>' : '';
                ?>
                <a href="<?= $link ?><?= empty($n['is_read']) ? '&mark_read=' . (int)$n['id'] : '' ?>"
                   class="list-group-item list-group-item-action <?= empty($n['is_read']) ? 'list-group-item-primary' : '' ?>">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1"><?= htmlspecialchars($n['type'] ?? '通知') ?></h5>
                        <small><?= htmlspecialchars($n['created_at'] ?? '') ?></small>
                    </div>
                    <p class="mb-1"><?= htmlspecialchars($n['content']) ?></p>
                    <div><?= $badge ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>