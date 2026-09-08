<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Task.php';
require_once '../../classes/TaskCategory.php';

checkLogin();
$taskM = new Task($pdo);
$taskM->expireOverdue();

$status = (string)($_GET['status'] ?? 'all');
$rtype  = (string)($_GET['rtype'] ?? 'all');
$kw     = trim((string)($_GET['kw'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 15;

$where  = ['1=1'];
$params = [];
if (in_array($status, ['open', 'closed'], true)) { $where[] = 't.status = ?'; $params[] = $status; }
if (in_array($rtype, ['popularity', 'cash'], true)) { $where[] = 't.reward_type = ?'; $params[] = $rtype; }
if ($kw !== '') { $where[] = '(t.title LIKE ? OR u.username LIKE ?)'; $params[] = '%' . $kw . '%'; $params[] = '%' . $kw . '%'; }
$w = implode(' AND ', $where);

$total = 0;
$rows  = [];
$pages = 1;
$dbOk  = true;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM tasks t LEFT JOIN users u ON t.employer_id = u.id WHERE $w");
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $pages = max(1, (int)ceil($total / $per));
    if ($page > $pages) $page = $pages;

    $sql = "SELECT t.*, u.username AS employer_name, c.name AS category_name,
                   (SELECT COUNT(*) FROM task_claims x WHERE x.task_id = t.id) AS claim_count,
                   (SELECT COUNT(*) FROM task_claims x JOIN task_disputes d ON d.claim_id = x.id
                     WHERE x.task_id = t.id AND d.status = 'open') AS dispute_count
            FROM tasks t
            LEFT JOIN users u ON t.employer_id = u.id
            LEFT JOIN task_categories c ON t.category_id = c.id
            WHERE $w
            ORDER BY t.id DESC
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

$admin_site_config = ['site' => 'task', 'page_title' => '任务列表'];
require_once '../../shared/admin/admin-header.php';

if (!$dbOk): ?>
    <div class="admin-alert warning"><i class="fas fa-exclamation-triangle"></i> 任务数据表尚未创建，请先执行 <code>init/migrate-task.sql</code>。</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-filter"></i> 筛选任务</span>
    </div>
    <form method="get" class="admin-form-row">
        <div class="admin-form-group" style="flex:2;">
            <input type="text" name="kw" class="admin-form-input" placeholder="搜索任务标题 / 发布者用户名" value="<?= htmlspecialchars($kw) ?>">
        </div>
        <div class="admin-form-group">
            <select name="status" class="admin-form-select">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>所有状态</option>
                <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>进行中</option>
                <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>已结束</option>
            </select>
        </div>
        <div class="admin-form-group">
            <select name="rtype" class="admin-form-select">
                <option value="all" <?= $rtype === 'all' ? 'selected' : '' ?>>全部赏金</option>
                <option value="popularity" <?= $rtype === 'popularity' ? 'selected' : '' ?>>人气值</option>
                <option value="cash" <?= $rtype === 'cash' ? 'selected' : '' ?>>现金（线下）</option>
            </select>
        </div>
        <div class="admin-form-group">
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> 搜索</button>
            <a href="tasks.php" class="admin-btn admin-btn-default"><i class="fas fa-undo"></i> 重置</a>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;">
        <span class="admin-card-title"><i class="fas fa-tasks"></i> 任务列表</span>
        <span class="admin-text-muted">共 <?= number_format($total) ?> 条</span>
    </div>
    <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>ID</th><th>任务</th><th>发布者</th><th>类别</th><th>赏金</th>
                    <th>名额</th><th>领取/争议</th><th>状态</th><th>截止</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="10" class="admin-empty-state"><i class="fas fa-inbox"></i><p>没有找到任务</p></td></tr>
                <?php else: foreach ($rows as $t): ?>
                    <tr>
                        <td>#<?= (int)$t['id'] ?></td>
                        <td style="max-width:220px;">
                            <strong><?= htmlspecialchars(mb_substr((string)$t['title'], 0, 28)) ?></strong>
                            <small class="admin-text-muted" style="display:block;">
                                <?= $t['reward_type'] === 'cash' ? '现金·线下' : '人气值' ?><?= !empty($t['city']) ? ' · ' . htmlspecialchars($t['city']) : '' ?>
                            </small>
                        </td>
                        <td><?= htmlspecialchars($t['employer_name'] ?? '#' . $t['employer_id']) ?></td>
                        <td><span class="admin-badge info"><?= htmlspecialchars($t['category_name'] ?? '未分类') ?></span></td>
                        <td>
                            <b style="color:#e11d48;"><?= $t['reward_type'] === 'cash' ? '¥' . number_format($t['reward_amount'] / 100, 2) : number_format($t['reward_amount']) . '人气' ?></b>
                        </td>
                        <td><?= (int)$t['claimed_count'] ?> / <?= (int)$t['quota'] ?></td>
                        <td>
                            <?= (int)$t['claim_count'] ?> 认领
                            <?php if ((int)$t['dispute_count'] > 0): ?><br><span class="admin-badge danger">争议 <?= (int)$t['dispute_count'] ?></span><?php endif; ?>
                        </td>
                        <td><span class="admin-badge <?= $t['status'] === 'open' ? 'success' : 'default' ?>"><?= $t['status'] === 'open' ? '进行中' : '已结束' ?></span></td>
                        <td><?= $t['expire_at'] ? date('m-d H:i', strtotime($t['expire_at'])) : '—' ?></td>
                        <td>
                            <div class="admin-btn-group">
                                <a href="https://task.58.tl/view.php?id=<?= (int)$t['id'] ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-default" title="前台查看"><i class="fas fa-eye"></i></a>
                                <a href="claims.php?task_id=<?= (int)$t['id'] ?>" class="admin-btn admin-btn-sm admin-btn-default" title="认领"><i class="fas fa-hand-paper"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <?php $qs = http_build_query(array_filter(['kw' => $kw ?: null, 'status' => $status === 'all' ? null : $status, 'rtype' => $rtype === 'all' ? null : $rtype])); ?>
        <div class="admin-pagination">
            <?php if ($page > 1): ?><a href="tasks.php?<?= $qs ?><?= $qs ? '&' : '' ?>page=<?= $page - 1 ?>">上一页</a><?php endif; ?>
            <span class="current"><?= $page ?> / <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="tasks.php?<?= $qs ?><?= $qs ? '&' : '' ?>page=<?= $page + 1 ?>">下一页</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
