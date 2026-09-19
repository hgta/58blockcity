<?php
/**
 * AI 对话记录（脱敏查看 + 未命中转 FAQ）
 * change: help-center-ai-assistant (task 5.6)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

checkAdmin();

$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    if ($_POST['action'] === 'to_faq') {
        $stmt = $pdo->prepare("SELECT question FROM ai_chat_logs WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $q = (string)$stmt->fetchColumn();
        if ($q !== '') {
            header('Location: help-faq.php?from_chat=' . (int)$_POST['id']);
            exit;
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$fltStatus = (string)($_GET['status'] ?? '');
$fltProvider = (int)($_GET['provider'] ?? 0);

$where = '1=1'; $params = [];
if ($fltStatus !== '') { $where .= " AND l.status = :st"; $params[':st'] = $fltStatus; }
if ($fltProvider > 0) { $where .= " AND l.provider_id = :pv"; $params[':pv'] = $fltProvider; }

$cnt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_logs l WHERE $where");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);

$stmt = $pdo->prepare(
    "SELECT l.*, p.name AS provider_name, u.username
     FROM ai_chat_logs l
     LEFT JOIN ai_providers p ON p.id = l.provider_id
     LEFT JOIN users u ON u.id = l.user_id
     WHERE $where ORDER BY l.created_at DESC LIMIT " . (($page - 1) * $per) . ", $per"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$providers = $pdo->query("SELECT id, name FROM ai_providers ORDER BY sort_order")->fetchAll();

$statusMap = ['ok' => '已回答', 'unmatched' => '未命中', 'error' => '出错', 'limited' => '限流'];
$admin_site_config = ['site' => 'main', 'page_title' => 'AI 对话记录'];
require_once '../shared/admin/admin-header.php';
?>

<?= $actionMsg ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-comments-dollar"></i> AI 对话记录 (<?= $total ?> 条)</span>
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
            <select name="status" style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <option value="">全部状态</option>
                <?php foreach ($statusMap as $k => $v): ?>
                <option value="<?= $k ?>" <?= $fltStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <select name="provider" style="padding:6px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <option value="">全部渠道</option>
                <?php foreach ($providers as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $fltProvider == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="admin-btn admin-btn-primary admin-btn-sm">筛选</button>
        </form>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-data-table">
            <thead><tr><th>ID</th><th>时间</th><th>用户</th><th>来源页面</th><th>问题（摘要）</th><th>命中</th><th>渠道</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= $l['id'] ?></td>
                    <td style="font-size:12px;white-space:nowrap;"><?= htmlspecialchars($l['created_at']) ?></td>
                    <td style="font-size:12px;"><?= $l['username'] ? htmlspecialchars($l['username']) : '<span style="color:#64748b">游客</span>' ?></td>
                    <td style="font-size:11px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($l['source_page']) ?>"><?= htmlspecialchars(mb_substr($l['source_page'], 0, 26)) ?: '-' ?></td>
                    <td style="max-width:260px;"><?= htmlspecialchars(mb_substr($l['question'], 0, 36)) ?></td>
                    <td><?= $l['matched'] ? '<span style="color:#22c55e">✓</span>' : '<span style="color:#f59e0b">未命中</span>' ?></td>
                    <td style="font-size:12px;"><?= $l['provider_name'] ? htmlspecialchars($l['provider_name']) : '-' ?></td>
                    <td>
                        <?php if ($l['matched'] == 0): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="to_faq"><input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button class="admin-btn admin-btn-secondary admin-btn-sm" title="以此为题创建FAQ草稿">转FAQ</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?><tr><td colspan="8" style="text-align:center;color:#64748b;padding:24px;">暂无对话记录</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php if ($pages > 1): ?>
        <div style="padding:12px;text-align:center;">
            <?php for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($fltStatus) ?>&provider=<?= $fltProvider ?>" class="admin-btn admin-btn-sm <?= $i == $page ? 'admin-btn-primary' : 'admin-btn-secondary' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
