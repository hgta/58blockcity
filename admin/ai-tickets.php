<?php
/**
 * AI 助手留言工单处理
 * change: help-center-ai-assistant (task 5.6)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

checkAdmin();

$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    try {
        if ($_POST['action'] === 'reply') {
            $reply = trim($_POST['admin_reply'] ?? '');
            if ($reply === '') {
                $actionMsg = '<div class="admin-alert admin-alert-error">回复内容不能为空</div>';
            } else {
                $pdo->prepare("UPDATE ai_feedback_tickets SET admin_reply = ?, status = 'done', handled_at = NOW(), handled_by = ? WHERE id = ?")
                    ->execute([$reply, $_SESSION['user_id'] ?? null, $id]);
                $actionMsg = '<div class="admin-alert admin-alert-success">工单已回复并关闭</div>';
            }
        } elseif ($_POST['action'] === 'reopen') {
            $pdo->prepare("UPDATE ai_feedback_tickets SET status = 'open', handled_at = NULL WHERE id = ?")->execute([$id]);
        } elseif ($_POST['action'] === 'delete') {
            $pdo->prepare("DELETE FROM ai_feedback_tickets WHERE id = ?")->execute([$id]);
        }
    } catch (Exception $ex) {
        $actionMsg = '<div class="admin-alert admin-alert-error">操作失败：' . htmlspecialchars($ex->getMessage()) . '</div>';
    }
}

$fltStatus = ($_GET['status'] ?? '') === 'done' ? 'done' : 'open';

$stmt = $pdo->prepare(
    "SELECT t.*, u.username, l.question AS chat_question
     FROM ai_feedback_tickets t
     LEFT JOIN users u ON u.id = t.user_id
     LEFT JOIN ai_chat_logs l ON l.id = t.chat_log_id
     WHERE t.status = ? ORDER BY t.created_at DESC LIMIT 100"
);
$stmt->execute([$fltStatus]);
$tickets = $stmt->fetchAll();

$admin_site_config = ['site' => 'main', 'page_title' => '留言工单'];
require_once '../shared/admin/admin-header.php';
?>

<?= $actionMsg ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-inbox"></i> 留言工单</span>
        <div style="display:flex;gap:8px;">
            <a href="ai-tickets.php?status=open" class="admin-btn admin-btn-sm <?= $fltStatus === 'open' ? 'admin-btn-primary' : 'admin-btn-secondary' ?>">待处理</a>
            <a href="ai-tickets.php?status=done" class="admin-btn admin-btn-sm <?= $fltStatus === 'done' ? 'admin-btn-primary' : 'admin-btn-secondary' ?>">已处理</a>
        </div>
    </div>
    <div class="admin-card-body">
        <?php if (!$tickets): ?>
        <div style="text-align:center;color:#64748b;padding:30px;">暂无<?= $fltStatus === 'open' ? '待处理' : '已处理' ?>工单</div>
        <?php endif; ?>
        <?php foreach ($tickets as $t): ?>
        <div style="border:1px solid #334155;border-radius:8px;padding:14px;margin-bottom:12px;">
            <div style="display:flex;gap:10px;font-size:12px;color:#94a3b8;flex-wrap:wrap;">
                <b style="color:#f1f5f9;">#<?= $t['id'] ?></b>
                <span><?= htmlspecialchars($t['created_at']) ?></span>
                <span><?= $t['username'] ? '用户：' . htmlspecialchars($t['username']) : '游客' ?></span>
                <?php if ($t['contact']): ?><span>联系：<?= htmlspecialchars($t['contact']) ?></span><?php endif; ?>
                <?php if ($t['chat_question']): ?><span style="color:#818cf8" title="关联的AI对话未能解答">关联AI对话未解决</span><?php endif; ?>
            </div>
            <div style="margin:8px 0;color:#f1f5f9;font-size:14px;"><?= nl2br(htmlspecialchars(mb_substr($t['question'], 0, 300))) ?></div>
            <?php if ($t['admin_reply']): ?>
            <div style="background:#052e16;border:1px solid #14532d;border-radius:6px;padding:10px;font-size:13px;color:#86efac;">
                <b>回复：</b><?= nl2br(htmlspecialchars($t['admin_reply'])) ?>
            </div>
            <?php elseif ($t['status'] === 'open'): ?>
            <form method="POST" style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                <input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                <input name="admin_reply" required placeholder="回复内容（处理结果说明）…" style="flex:1;min-width:260px;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <button class="admin-btn admin-btn-primary admin-btn-sm">回复并关闭</button>
            </form>
            <?php endif; ?>
            <div style="margin-top:8px;display:flex;gap:6px;">
                <?php if ($t['status'] === 'done'): ?>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="reopen"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="admin-btn admin-btn-secondary admin-btn-sm">重新打开</button></form>
                <?php endif; ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('确认删除该工单？')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="admin-btn admin-btn-danger admin-btn-sm">删除</button></form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
