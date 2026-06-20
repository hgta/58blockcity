<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Visit.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Notification.php';

checkLogin();

$visitId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

$visit = new Visit($pdo);
$circle = new Circle($pdo);
$notification = new Notification($pdo);
$visitDetails = $visit->getVisitById($visitId);

// 验证：必须是访问者且状态为confirmed
if (!$visitDetails || $visitDetails['visitor_id'] != $userId ||
    $visitDetails['status'] != Visit::STATUS_CONFIRMED) {
    $_SESSION['error_message'] = '无效操作';
    header('Location: visits.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($visit->completeVisit($visitId)) {
        // 通知圈主回访已完成
        $message = "{$visitDetails['visitor_name']} 已完成对您圈子「{$visitDetails['circle_name']}」的回访";
        $notification->create(
            $visitDetails['owner_id'],
            'return_confirm',
            $visitId,
            $message
        );
        $_SESSION['success_message'] = '回访已完成确认';
        header('Location: visits.php');
        exit;
    } else {
        $_SESSION['error_message'] = '操作失败';
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <h2>确认回访完成</h2>

    <div class="card mt-3">
        <div class="card-body">
            <p>请确认您已完成对以下互访圈的回访：</p>

            <div class="visit-info">
                <h4><?= htmlspecialchars($visitDetails['circle_name']) ?></h4>
                <p><strong>圈主：</strong><?= htmlspecialchars($visitDetails['owner_name']) ?></p>
                <p><strong>访问日期：</strong><?= htmlspecialchars($visitDetails['visit_date'] ?? '') ?></p>
            </div>

            <form method="POST" class="mt-4">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> 确认回访已完成
                </button>
                <a href="visits.php" class="btn btn-secondary">取消</a>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>