<?php
/**
 * 共享消息中心 — 全站通用
 * 子站调用: require_once $sharedPath . 'user/messages.php';
 */

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/classes/Notification.php';

$userId = $_SESSION['user_id'];
$notify = new Notification($pdo);

// 标记所有为已读
if (isset($_GET['mark_all_read'])) {
    $notify->markAllAsRead($userId);
    header('Location: messages.php');
    exit;
}

// 标记单条已读
if (isset($_GET['mark_read'])) {
    $notify->markAsRead(intval($_GET['mark_read']), $userId);
    header('Location: messages.php');
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$data = $notify->getUserNotificationsAll($userId, $page, $perPage);
$messages = $data['rows'];
$total = $data['total'];
$totalPages = ceil($total / $perPage);
$unreadCount = $notify->getUnreadCount($userId);

$typeLabels = [
    'visit_request'  => ['label' => '互访请求', 'icon' => 'handshake', 'color' => '#f59e0b'],
    'visit_confirm'  => ['label' => '互访确认', 'icon' => 'check-circle', 'color' => '#10b981'],
    'return_confirm' => ['label' => '回访确认', 'icon' => 'sync', 'color' => '#8b5cf6'],
    'system'         => ['label' => '系统通知', 'icon' => 'info-circle', 'color' => '#6b7280'],
    'order_paid'     => ['label' => '订单已付', 'icon' => 'credit-card', 'color' => '#3b82f6'],
    'order_shipped'  => ['label' => '已发货', 'icon' => 'truck', 'color' => '#10b981'],
    'order_done'     => ['label' => '交易完成', 'icon' => 'check-double', 'color' => '#6366f1'],
    'new_review'     => ['label' => '新评价', 'icon' => 'comment-dots', 'color' => '#f97316'],
    'dm'             => ['label' => '私信', 'icon' => 'envelope', 'color' => '#ec4899'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>消息中心</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; }
        .msg-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .msg-header {
            display: flex; justify-content: space-between; align-items: center;
            background: #fff; padding: 20px 24px; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 16px;
        }
        .msg-header h1 { font-size: 20px; }
        .msg-header-actions { display: flex; gap: 8px; align-items: center; }
        .btn { display: inline-flex; align-items: center; gap: 4px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; text-decoration: none; border: none; cursor: pointer; transition: all 0.15s; }
        .btn-back { background: #f1f5f9; color: #475569; }
        .btn-back:hover { background: #e2e8f0; }
        .btn-read { background: #dbeafe; color: #1e40af; }
        .btn-read:hover { background: #bfdbfe; }
        .unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; flex-shrink: 0; }
        .msg-list { display: flex; flex-direction: column; gap: 2px; }
        .msg-item {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 16px 20px; background: #fff;
            transition: background 0.15s; text-decoration: none; color: inherit;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
        }
        .msg-item:first-child { border-radius: 12px 12px 0 0; }
        .msg-item:last-child { border-radius: 0 0 12px 12px; border-bottom: none; }
        .msg-item:hover { background: #f8fafc; }
        .msg-item.unread { background: #eff6ff; }
        .msg-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; color: #fff; font-size: 16px;
        }
        .msg-body { flex: 1; min-width: 0; }
        .msg-type { font-size: 12px; font-weight: 600; margin-bottom: 4px; }
        .msg-content { font-size: 14px; line-height: 1.5; color: #475569; }
        .msg-time { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; }
        .pagination { display: flex; gap: 4px; justify-content: center; padding: 20px 0; }
        .page-link { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; border-radius: 8px; font-size: 13px; text-decoration: none; color: #475569; background: #fff; border: 1px solid #e2e8f0; }
        .page-link.active { background: #ff6b00; color: #fff; border-color: #ff6b00; }
        @media(max-width:768px){
            .msg-header { flex-direction: column; gap: 10px; align-items: flex-start; }
            .msg-item { padding: 12px 16px; gap: 10px; }
        }
    </style>
</head>
<body>
<div class="msg-container">
    <div class="msg-header">
        <div>
            <h1><i class="fas fa-bell"></i> 消息中心</h1>
            <?php if ($unreadCount > 0): ?>
                <span style="font-size:13px;color:#3b82f6;"><?= $unreadCount ?> 条未读</span>
            <?php endif; ?>
        </div>
        <div class="msg-header-actions">
            <?php if ($unreadCount > 0): ?>
                <a href="?mark_all_read=1" class="btn btn-read"><i class="fas fa-check-double"></i> 全部已读</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> 返回</a>
        </div>
    </div>

    <?php if (empty($messages)): ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <p>暂无消息</p>
        </div>
    <?php else: ?>
        <div class="msg-list">
            <?php foreach ($messages as $m): 
                $typeInfo = $typeLabels[$m['type']] ?? ['label' => $m['type'], 'icon' => 'bell', 'color' => '#6b7280'];
                $link = $m['related_url'] ?? '#';
                if ($m['type'] === 'visit_request') {
                    $link = "visits.php?circle_id=" . intval($m['related_id']);
                } elseif (in_array($m['type'], ['visit_confirm', 'return_confirm'])) {
                    $link = "visit_detail.php?id=" . intval($m['related_id']);
                } elseif ($m['type'] === 'order_paid' || $m['type'] === 'order_done') {
                    $link = "../shop/orders.php?id=" . intval($m['related_id']);
                } elseif ($m['type'] === 'order_shipped') {
                    $link = "order_detail.php?id=" . intval($m['related_id']);
                } elseif ($m['type'] === 'new_review') {
                    $link = "../product/detail.php?id=" . intval($m['related_id']);
                }
            ?>
                <a href="<?= $link ?>" class="msg-item <?= empty($m['is_read']) ? 'unread' : '' ?>">
                    <?php if (empty($m['is_read'])): ?><span class="unread-dot"></span><?php endif; ?>
                    <div class="msg-icon" style="background:<?= $typeInfo['color'] ?>">
                        <i class="fas fa-<?= $typeInfo['icon'] ?>"></i>
                    </div>
                    <div class="msg-body">
                        <div class="msg-type" style="color:<?= $typeInfo['color'] ?>"><?= $typeInfo['label'] ?></div>
                        <div class="msg-content"><?= htmlspecialchars($m['content']) ?></div>
                        <div class="msg-time"><?= date('m-d H:i', strtotime($m['created_at'])) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                <a href="?page=<?= $i ?>" class="page-link <?= $i==$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
