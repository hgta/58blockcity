<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';

checkLogin();

/** 标量查询兜底（表未建/查询失败返回 null） */
function _tq1($pdo, $sql, $params = []) {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === null ? 0 : (int)$v;
    } catch (Exception $e) {
        return null;
    }
}
function _trows($pdo, $sql, $params = []) {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

$stat = [
    'tasks_open'   => _tq1($pdo, "SELECT COUNT(*) FROM tasks WHERE status='open'"),
    'tasks_closed' => _tq1($pdo, "SELECT COUNT(*) FROM tasks WHERE status='closed'"),
    'claims_total' => _tq1($pdo, "SELECT COUNT(*) FROM task_claims"),
    'claims_submitted' => _tq1($pdo, "SELECT COUNT(*) FROM task_claims WHERE status='submitted'"),
    'claims_settling'  => _tq1($pdo, "SELECT COUNT(*) FROM task_claims WHERE status='settling'"),
    'disputes_open' => _tq1($pdo, "SELECT COUNT(*) FROM task_disputes WHERE status='open'"),
    'cats'         => _tq1($pdo, "SELECT COUNT(*) FROM task_categories"),
    'skills_active' => _tq1($pdo, "SELECT COUNT(*) FROM task_skills WHERE status='active'"),
];

// 未执行 migrate-task.sql 时给出提示
$migrateHint = ($stat['tasks_open'] === null);

$recentTasks = _trows($pdo,
    "SELECT t.*, u.username AS employer_name, c.name AS category_name,
            (SELECT COUNT(*) FROM task_claims x WHERE x.task_id = t.id) AS claim_count
     FROM tasks t
     LEFT JOIN users u ON t.employer_id = u.id
     LEFT JOIN task_categories c ON t.category_id = c.id
     ORDER BY t.id DESC LIMIT 6");

$recentDisputes = _trows($pdo,
    "SELECT d.*, t.title AS task_title, e.username AS employer_name, w.username AS worker_name,
            u.username AS initiator_name
     FROM task_disputes d
     JOIN task_claims c ON d.claim_id = c.id
     JOIN tasks t ON c.task_id = t.id
     LEFT JOIN users e ON t.employer_id = e.id
     LEFT JOIN users w ON c.worker_id = w.id
     LEFT JOIN users u ON d.initiator_id = u.id
     ORDER BY d.id DESC LIMIT 6");

$admin_site_config = ['site' => 'task', 'page_title' => '任务广场管理看板'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if ($migrateHint): ?>
    <div class="admin-alert warning" style="margin-bottom:16px;">
        <i class="fas fa-exclamation-triangle"></i>
        任务相关数据表尚未创建，请在服务器执行 <code>init/migrate-task.sql</code> 后刷新本页。
    </div>
<?php endif; ?>

<!-- 统计卡片 -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-tasks"></i></div>
        <div class="stat-value"><?= (int)($stat['tasks_open'] ?? 0) ?></div>
        <div class="stat-label">进行中任务</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-archive"></i></div>
        <div class="stat-value"><?= (int)($stat['tasks_closed'] ?? 0) ?></div>
        <div class="stat-label">已结束任务</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-hand-paper"></i></div>
        <div class="stat-value"><?= (int)($stat['claims_total'] ?? 0) ?></div>
        <div class="stat-label">认领总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-value"><?= (int)($stat['claims_submitted'] ?? 0) ?></div>
        <div class="stat-label">待验收</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-coins"></i></div>
        <div class="stat-value"><?= (int)($stat['claims_settling'] ?? 0) ?></div>
        <div class="stat-label">结算中</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon danger"><i class="fas fa-gavel"></i></div>
        <div class="stat-value"><?= (int)($stat['disputes_open'] ?? 0) ?></div>
        <div class="stat-label">待仲裁争议</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-tags"></i></div>
        <div class="stat-value"><?= (int)($stat['cats'] ?? 0) ?></div>
        <div class="stat-label">类别数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-id-card"></i></div>
        <div class="stat-value"><?= (int)($stat['skills_active'] ?? 0) ?></div>
        <div class="stat-label">展示中技能卡</div>
    </div>
</div>

<div class="admin-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <!-- 最新任务 -->
    <div class="admin-card">
        <div class="admin-card-header" style="justify-content:space-between;">
            <span class="admin-card-title"><i class="fas fa-clock"></i> 最新任务</span>
            <a href="tasks.php" class="admin-btn admin-btn-sm admin-btn-default">查看全部</a>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead><tr><th>ID</th><th>任务</th><th>发布者</th><th>赏金</th><th>领取</th><th>状态</th></tr></thead>
                <tbody>
                    <?php if (!$recentTasks): ?>
                        <tr><td colspan="6" class="admin-empty-state"><i class="fas fa-inbox"></i><p>暂无任务</p></td></tr>
                    <?php else: foreach ($recentTasks as $t): ?>
                        <tr>
                            <td>#<?= (int)$t['id'] ?></td>
                            <td>
                                <a href="https://task.58.tl/view.php?id=<?= (int)$t['id'] ?>" target="_blank" style="font-weight:600;color:#1f2937;text-decoration:none;"><?= htmlspecialchars($t['title']) ?></a>
                                <small class="admin-text-muted" style="display:block;"><?= htmlspecialchars($t['category_name'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($t['employer_name'] ?? '#' . $t['employer_id']) ?></td>
                            <td>
                                <?= $t['reward_type'] === 'cash' ? '¥ ' . number_format($t['reward_amount'] / 100, 2) : number_format($t['reward_amount']) . ' 人气' ?>
                            </td>
                            <td><?= (int)$t['claim_count'] ?>/<?= (int)$t['quota'] ?></td>
                            <td><span class="admin-badge <?= $t['status'] === 'open' ? 'success' : 'default' ?>"><?= $t['status'] === 'open' ? '进行中' : '已结束' ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 最新争议 -->
    <div class="admin-card">
        <div class="admin-card-header" style="justify-content:space-between;">
            <span class="admin-card-title"><i class="fas fa-gavel"></i> 最新争议</span>
            <a href="disputes.php" class="admin-btn admin-btn-sm <?= (int)($stat['disputes_open'] ?? 0) > 0 ? 'admin-btn-danger' : 'admin-btn-default' ?>">去仲裁</a>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead><tr><th>ID</th><th>任务</th><th>雇主</th><th>接单人</th><th>发起</th><th>状态</th></tr></thead>
                <tbody>
                    <?php if (!$recentDisputes): ?>
                        <tr><td colspan="6" class="admin-empty-state"><i class="fas fa-balance-scale"></i><p>暂无争议</p></td></tr>
                    <?php else: foreach ($recentDisputes as $d): ?>
                        <tr>
                            <td>#<?= (int)$d['id'] ?></td>
                            <td><?= htmlspecialchars(mb_substr((string)$d['task_title'], 0, 18)) ?></td>
                            <td><?= htmlspecialchars($d['employer_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['worker_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['initiator_name'] ?? '') ?></td>
                            <td><span class="admin-badge <?= $d['status'] === 'open' ? 'danger' : 'default' ?>"><?= $d['status'] === 'open' ? '待仲裁' : '已裁决' ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
