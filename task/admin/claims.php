<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/TaskClaim.php';
require_once '../../classes/User.php';

checkLogin();
$tc = new TaskClaim($pdo);

$status = (string)($_GET['status'] ?? 'all');
$taskId = (int)($_GET['task_id'] ?? 0);
$kw     = trim((string)($_GET['kw'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 15;

$allStatus = ['accepted', 'submitted', 'rejected', 'settling', 'completed', 'cancelled', 'disputed'];

$where  = ['1=1'];
$params = [];
if (in_array($status, $allStatus, true)) { $where[] = 'c.status = ?'; $params[] = $status; }
if ($taskId > 0) { $where[] = 'c.task_id = ?'; $params[] = $taskId; }
if ($kw !== '') { $where[] = '(t.title LIKE ? OR w.username LIKE ?)'; $params[] = '%' . $kw . '%'; $params[] = '%' . $kw . '%'; }
$w = implode(' AND ', $where);

$total = 0; $rows = []; $pages = 1; $dbOk = true;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM task_claims c JOIN tasks t ON c.task_id = t.id LEFT JOIN users w ON c.worker_id = w.id WHERE $w");
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $pages = max(1, (int)ceil($total / $per));
    if ($page > $pages) $page = $pages;

    $sql = "SELECT c.*, t.title AS task_title, t.employer_id,
                   t.reward_type, t.reward_amount, t.review_days,
                   e.username AS employer_name, w.username AS worker_name,
                   (SELECT d.status FROM task_disputes d WHERE d.claim_id = c.id LIMIT 1) AS dispute_status
            FROM task_claims c
            JOIN tasks t ON c.task_id = t.id
            LEFT JOIN users e ON t.employer_id = e.id
            LEFT JOIN users w ON c.worker_id = w.id
            WHERE $w
            ORDER BY c.id DESC
            LIMIT :lim OFFSET :off";
    $st = $pdo->prepare($sql);
    foreach ($params as $i => $p) $st->bindValue($i + 1, $p);
    $st->bindValue(':lim', $per, PDO::PARAM_INT);
    $st->bindValue(':off', ($page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
} catch (Exception $e) {
    $dbOk = false;
}

$statusName = [
    'accepted' => '已领取', 'submitted' => '待验收', 'rejected' => '被驳回', 'settling' => '结算中',
    'completed' => '已完成', 'cancelled' => '已取消', 'disputed' => '争议中',
];

$admin_site_config = ['site' => 'task', 'page_title' => '认领管理'];
require_once '../../shared/admin/admin-header.php';

if (!$dbOk): ?>
    <div class="admin-alert warning"><i class="fas fa-exclamation-triangle"></i> 认领数据表尚未创建，请先执行 <code>init/migrate-task.sql</code>。</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-filter"></i> 筛选认领</span>
    </div>
    <form method="get" class="admin-form-row">
        <div class="admin-form-group" style="flex:2;">
            <input type="text" name="kw" class="admin-form-input" placeholder="搜索任务标题 / 接单人" value="<?= htmlspecialchars($kw) ?>">
        </div>
        <div class="admin-form-group">
            <select name="status" class="admin-form-select">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>所有状态</option>
                <?php foreach ($statusName as $k => $n): ?>
                    <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($taskId > 0): ?>
            <input type="hidden" name="task_id" value="<?= $taskId ?>">
            <span class="admin-badge info">任务 #<?= $taskId ?></span>
        <?php endif; ?>
        <div class="admin-form-group">
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> 搜索</button>
            <a href="claims.php" class="admin-btn admin-btn-default"><i class="fas fa-undo"></i> 重置</a>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;">
        <span class="admin-card-title"><i class="fas fa-hand-paper"></i> 认领列表</span>
        <span class="admin-text-muted">共 <?= number_format($total) ?> 条</span>
    </div>
    <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>认领#</th><th>任务</th><th>雇主</th><th>接单人</th><th>状态</th><th>交付凭证</th><th>验收截止</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="admin-empty-state"><i class="fas fa-inbox"></i><p>没有找到认领</p></td></tr>
                <?php else: foreach ($rows as $c):
                    $isOverdue = $c['status'] === 'submitted' && $c['review_due_at'] && strtotime($c['review_due_at']) < time(); ?>
                    <tr>
                        <td>#<?= (int)$c['id'] ?></td>
                        <td style="max-width:200px;">
                            <a href="https://task.58.tl/view.php?id=<?= (int)$c['task_id'] ?>" target="_blank" style="font-weight:600;color:#1f2937;text-decoration:none;"><?= htmlspecialchars(mb_substr((string)$c['task_title'], 0, 22)) ?></a>
                            <small class="admin-text-muted" style="display:block;">
                                <?= $c['reward_type'] === 'cash' ? '¥' . number_format($c['reward_amount'] / 100, 2) : number_format($c['reward_amount']) . ' 人气' ?>
                                · <?= $c['task_id'] ? '任务#' . $c['task_id'] : '' ?>
                            </small>
                        </td>
                        <td><?= htmlspecialchars($c['employer_name'] ?? '#' . $c['employer_id']) ?></td>
                        <td><?= htmlspecialchars($c['worker_name'] ?? '#' . $c['worker_id']) ?></td>
                        <td>
                            <span class="admin-badge <?= $c['status'] === 'disputed' ? 'danger' : ($c['status'] === 'submitted' ? 'warning' : ($c['status'] === 'completed' ? 'success' : ($c['status'] === 'rejected' ? 'danger' : 'default'))) ?>">
                                <?= $statusName[$c['status']] ?? $c['status'] ?>
                            </span>
                            <?php if ($isOverdue): ?><div style="font-size:11px;color:#ef4444;">⚠ 已超验收期</div><?php endif; ?>
                            <?php if (($c['dispute_status'] ?? '') === 'open'): ?><div class="admin-badge danger" style="margin-top:4px;">争议仲裁中</div><?php endif; ?>
                        </td>
                        <td style="max-width:220px;">
                            <?php if (!empty($c['proof_text'])): ?>
                                <small class="admin-text-muted" style="display:block;">📎 <?= htmlspecialchars(mb_substr((string)$c['proof_text'], 0, 50)) ?></small>
                            <?php endif; ?>
                            <?php if (!empty($c['proof_image'])): ?>
                                <a href="https://task.58.tl/<?= ltrim($c['proof_image'], '/') ?>" target="_blank" title="查看凭证大图">
                                    <img src="https://task.58.tl/<?= ltrim($c['proof_image'], '/') ?>" style="height:34px;border-radius:4px;margin-top:4px;" alt="凭证">
                                </a>
                            <?php endif; ?>
                            <?php if (empty($c['proof_text']) && empty($c['proof_image'])): ?><span class="admin-text-muted">未提交</span><?php endif; ?>
                        </td>
                        <td><?= $c['review_due_at'] ? date('m-d H:i', strtotime($c['review_due_at'])) : '—' ?></td>
                        <td>
                            <div class="admin-btn-group">
                                <a href="https://task.58.tl/view.php?id=<?= (int)$c['task_id'] ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-default" title="前台查看"><i class="fas fa-eye"></i></a>
                                <a href="disputes.php" class="admin-btn admin-btn-sm admin-btn-default" title="仲裁"><i class="fas fa-gavel"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <?php $qs = http_build_query(array_filter(['kw' => $kw ?: null, 'status' => $status === 'all' ? null : $status, 'task_id' => $taskId ?: null])); ?>
        <div class="admin-pagination">
            <?php if ($page > 1): ?><a href="claims.php?<?= $qs ?><?= $qs ? '&' : '' ?>page=<?= $page - 1 ?>">上一页</a><?php endif; ?>
            <span class="current"><?= $page ?> / <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="claims.php?<?= $qs ?><?= $qs ? '&' : '' ?>page=<?= $page + 1 ?>">下一页</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
