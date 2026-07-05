<?php
session_start();
require_once '../config/database.php';
require_once '../classes/Message.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header('Location: /auth/login.php');
    exit;
}

$username = $_SESSION['username'] ?? '';
$msg = new Message($pdo);
$conversations = $msg->getConversations($userId);
$unreadTotal = $msg->getUnreadCount($userId);

$withUser = intval($_GET['with'] ?? 0);
$chatMessages = [];
if ($withUser > 0) {
    $msg->markRead($userId, $withUser);
    $chatMessages = $msg->getMessages($userId, $withUser, 1, 50);
    $unreadTotal = $msg->getUnreadCount($userId);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>站内信 - 58区块城市</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Microsoft YaHei',Arial,sans-serif;background:#f5f5f5;color:#333;display:flex;height:100vh}
        .sidebar{width:320px;background:#fff;border-right:1px solid #eee;display:flex;flex-direction:column;flex-shrink:0}
        .sidebar-header{padding:16px 20px;border-bottom:1px solid #eee;font-size:18px;font-weight:bold;display:flex;justify-content:space-between;align-items:center}
        .sidebar-header a{font-size:14px;color:#999;text-decoration:none}
        .conv-list{flex:1;overflow-y:auto}
        .conv-item{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid #f5f5f5;cursor:pointer;text-decoration:none;color:inherit;transition:background 0.15s}
        .conv-item:hover,.conv-item.active{background:#fff8f3}
        .conv-avatar{width:44px;height:44px;border-radius:50%;background:#f0f0f0;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center}
        .conv-avatar img{width:100%;height:100%;object-fit:cover}
        .conv-avatar i{font-size:20px;color:#ccc}
        .conv-info{flex:1;min-width:0}
        .conv-name{font-size:15px;font-weight:bold;margin-bottom:3px}
        .conv-preview{font-size:13px;color:#999;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .conv-meta{text-align:right;flex-shrink:0}
        .conv-time{font-size:11px;color:#ccc;margin-bottom:4px}
        .conv-badge{display:inline-block;background:#ff6b00;color:#fff;font-size:11px;padding:1px 7px;border-radius:10px;min-width:18px;text-align:center}

        .chat-area{flex:1;display:flex;flex-direction:column;background:#fff}
        .chat-header{padding:16px 24px;border-bottom:1px solid #eee;font-size:16px;font-weight:bold}
        .chat-body{flex:1;overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:16px}
        .chat-empty{text-align:center;color:#ccc;margin-top:100px;font-size:16px}
        .chat-empty i{font-size:48px;display:block;margin-bottom:16px}
        .chat-msg{display:flex;gap:10px;max-width:70%}
        .chat-msg.me{align-self:flex-end;flex-direction:row-reverse}
        .chat-msg .chat-bubble{padding:10px 14px;border-radius:12px;font-size:14px;line-height:1.6;word-break:break-all}
        .chat-msg:not(.me) .chat-bubble{background:#f0f0f0}
        .chat-msg.me .chat-bubble{background:#ff6b00;color:#fff}
        .chat-msg .chat-time{font-size:11px;color:#ccc;margin-top:4px;text-align:center}

        .chat-form{display:flex;gap:10px;padding:16px 24px;border-top:1px solid #eee}
        .chat-form textarea{flex:1;padding:12px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:none;height:44px;outline:none;font-family:inherit}
        .chat-form button{padding:10px 24px;background:#ff6b00;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;white-space:nowrap}
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header">站内信 <?= $unreadTotal ? "<span style=\"color:#ff6b00;font-size:14px\">{$unreadTotal}未读</span>" : '' ?> <a href="/">← 返回</a></div>
    <div class="conv-list">
        <?php if (empty($conversations)): ?>
        <div style="padding:40px;text-align:center;color:#ccc;">暂无会话</div>
        <?php else: ?>
        <?php foreach ($conversations as $c): ?>
        <a href="?with=<?= $c['partner_id'] ?>" class="conv-item <?= $withUser == $c['partner_id'] ? 'active' : '' ?>">
            <div class="conv-avatar"><?= $c['user_avatar'] ? '<img src="/assets/images/'.$c['user_avatar'].'">' : '<i class="fas fa-user"></i>' ?></div>
            <div class="conv-info">
                <div class="conv-name"><?= htmlspecialchars($c['username'] ?? '用户'.$c['partner_id']) ?></div>
                <div class="conv-preview">点击查看对话</div>
            </div>
            <div class="conv-meta">
                <div class="conv-time"><?= date('m-d', strtotime($c['last_time'])) ?></div>
                <?php if ($c['unread_count'] > 0): ?>
                <div class="conv-badge"><?= $c['unread_count'] ?></div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="chat-area">
    <?php if ($withUser > 0): 
        // 获取对方信息
        $partnerStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $partnerStmt->execute([$withUser]);
        $partner = $partnerStmt->fetch();
        $partnerName = htmlspecialchars($partner['username'] ?? '用户');
    ?>
    <div class="chat-header"><?= $partnerName ?></div>
    <div class="chat-body" id="chat-body">
        <?php if (empty($chatMessages)): ?>
        <div class="chat-empty"><i class="fas fa-comments"></i>暂无消息</div>
        <?php else: ?>
        <?php foreach ($chatMessages as $cm): $isMe = $cm['from_user_id'] == $userId; ?>
        <div class="chat-msg <?= $isMe ? 'me' : '' ?>">
            <div>
                <div class="chat-bubble"><?= nl2br(htmlspecialchars($cm['message'])) ?></div>
                <div class="chat-time"><?= date('H:i', strtotime($cm['created_at'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <form class="chat-form" method="post" action="">
        <input type="hidden" name="to_user_id" value="<?= $withUser ?>">
        <textarea name="message" placeholder="输入消息..." required></textarea>
        <button type="submit">发送</button>
    </form>
    <?php else: ?>
    <div class="chat-header">消息</div>
    <div class="chat-empty"><i class="fas fa-inbox"></i>选择一个会话开始聊天</div>
    <?php endif; ?>
</div>

<?php
// 处理发送
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $toId = intval($_POST['to_user_id'] ?? 0);
    $text = trim($_POST['message'] ?? '');
    if ($toId > 0 && !empty($text)) {
        $msg->send($userId, $toId, $text);
        header("Location: ?with=$toId");
        exit;
    }
}
?>
</body>
</html>
