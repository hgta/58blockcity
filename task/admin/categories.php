<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/TaskCategory.php';
require_once '../../classes/TaskSkill.php';

checkLogin();
$catM = new TaskCategory($pdo);

// 处理增/改/停用启用
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = (string)($_POST['action'] ?? '');
    $msg = ['type' => 'success', 'text' => ''];

    if ($action === 'add') {
        [$ok, $res] = $catM->add((string)($_POST['name'] ?? ''), (int)($_POST['sort_order'] ?? 0));
        $msg = $ok ? ['type' => 'success', 'text' => '类别「' . trim((string)$_POST['name']) . '」已新增'] : ['type' => 'error', 'text' => $res];
    } elseif ($action === 'update') {
        [$ok, $res] = $catM->update((int)($_POST['id'] ?? 0), (string)($_POST['name'] ?? ''), (int)($_POST['sort_order'] ?? 0));
        $msg = $ok ? ['type' => 'success', 'text' => '类别已更新'] : ['type' => 'error', 'text' => $res];
    } elseif ($action === 'status') {
        $ok = $catM->setStatus((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''));
        $msg = $ok
            ? ['type' => 'success', 'text' => ($_POST['status'] === 'inactive' ? '类别已停用（历史任务与旧技能卡仍保留原类别展示）' : '类别已启用')]
            : ['type' => 'error', 'text' => '操作失败'];
    } else {
        $msg = ['type' => 'error', 'text' => '未知操作'];
    }

    $_SESSION['admin_msg'] = $msg;
    header('Location: categories.php');
    exit;
}

$cats = $catM->all();

// 类别被引用次数（任务 + 技能卡）
$usage = [];
try {
    $st = $pdo->query("SELECT category_id, COUNT(*) AS cnt FROM tasks GROUP BY category_id");
    foreach ($st->fetchAll() as $r) { $usage['task'][(int)$r['category_id']] = (int)$r['cnt']; }
} catch (Exception $e) { $usage['task'] = []; }
try {
    $st = $pdo->query("SELECT category_id, COUNT(*) AS cnt FROM task_skill_categories GROUP BY category_id");
    foreach ($st->fetchAll() as $r) { $usage['skill'][(int)$r['category_id']] = (int)$r['cnt']; }
} catch (Exception $e) { $usage['skill'] = []; }

$csrf = generateCsrfToken();

$admin_site_config = ['site' => 'task', 'page_title' => '任务类别管理'];
require_once '../../shared/admin/admin-header.php';

if (isset($_SESSION['admin_msg'])) {
    $m = $_SESSION['admin_msg'];
    unset($_SESSION['admin_msg']);
    echo '<div class="admin-alert ' . ($m['type'] === 'success' ? 'success' : 'danger') . '"><i class="fas fa-' . ($m['type'] === 'success' ? 'check-circle' : 'exclamation-circle') . '"></i> ' . htmlspecialchars($m['text']) . '</div>';
}
?>

<div class="admin-grid-2" style="display:grid;grid-template-columns:340px 1fr;gap:16px;align-items:start;">
    <!-- 新增类别 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-plus-circle"></i> 新增类别</span>
        </div>
        <form method="post" class="admin-form-vertical">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add">
            <div class="admin-form-group">
                <label class="admin-form-label">类别名称</label>
                <input type="text" name="name" class="admin-form-input" maxlength="50" required placeholder="如：代排队 / 代签到">
            </div>
            <div class="admin-form-group">
                <label class="admin-form-label">排序（小在前）</label>
                <input type="number" name="sort_order" class="admin-form-input" value="0">
            </div>
            <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> 新增</button>
        </form>
        <div class="admin-text-muted" style="margin-top:10px;font-size:12px;line-height:1.7;">
            停用的类别不再出现在发布页/广场筛选/技能卡可选项；已发布任务与旧技能卡仍展示原类别名。
        </div>
    </div>

    <!-- 类别列表 -->
    <div class="admin-card">
        <div class="admin-card-header" style="justify-content:space-between;">
            <span class="admin-card-title"><i class="fas fa-tags"></i> 类别列表</span>
            <span class="admin-text-muted">共 <?= count($cats) ?> 个</span>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>名称 / 排序</th><th>状态</th><th>任务引用</th><th>技能卡引用</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$cats): ?>
                        <tr><td colspan="6" class="admin-empty-state"><i class="fas fa-tags"></i><p>暂无类别</p></td></tr>
                    <?php else: foreach ($cats as $c): ?>
                        <tr>
                            <td>#<?= (int)$c['id'] ?></td>
                            <td>
                                <form method="post" style="display:flex;gap:6px;align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <input type="text" name="name" class="admin-form-input" value="<?= htmlspecialchars($c['name']) ?>" maxlength="50" style="width:140px;">
                                    <input type="number" name="sort_order" class="admin-form-input" value="<?= (int)$c['sort_order'] ?>" style="width:70px;" title="排序">
                                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-default" title="保存"><i class="fas fa-save"></i></button>
                                </form>
                            </td>
                            <td>
                                <?php if ($c['status'] === 'active'): ?>
                                    <span class="admin-badge success">启用</span>
                                <?php else: ?>
                                    <span class="admin-badge default">停用</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)($usage['task'][(int)$c['id']] ?? 0) ?></td>
                            <td><?= (int)($usage['skill'][(int)$c['id']] ?? 0) ?></td>
                            <td>
                                <?php if ($c['status'] === 'active'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="status" value="inactive">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-default" title="停用" onclick="return confirm('停用后该类别不再可选，确定？');"><i class="fas fa-ban"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary" title="启用"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
