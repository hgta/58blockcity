<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/TaskDispute.php';

checkLogin();
$dm = new TaskDispute($pdo);

// 裁决提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $id   = (int)($_POST['id'] ?? 0);
    $res  = (string)($_POST['resolution'] ?? '');
    $note = (string)($_POST['note'] ?? '');
    [$ok, $text] = $dm->resolve($id, $res, (int)($_SESSION['user_id'] ?? 0), $note);
    $_SESSION['admin_msg'] = $ok
        ? ['type' => 'success', 'text' => '仲裁完成：' . $text]
        : ['type' => 'error', 'text' => '仲裁失败：' . $text];
    header('Location: disputes.php');
    exit;
}

$view = ($_GET['v'] ?? 'open') === 'all' ? 'all' : 'open';
$csrf = generateCsrfToken();

$dbOk = true;
try {
    $open = $view === 'open' ? $dm->openList() : [];
    $all  = $view === 'all' ? $dm->allList() : [];
} catch (Exception $e) {
    $dbOk = false;
    $open = []; $all = [];
}

$admin_site_config = ['site' => 'task', 'page_title' => '争议仲裁'];
require_once '../../shared/admin/admin-header.php';

if (isset($_SESSION['admin_msg'])) {
    $m = $_SESSION['admin_msg'];
    unset($_SESSION['admin_msg']);
    echo '<div class="admin-alert ' . ($m['type'] === 'success' ? 'success' : 'danger') . '"><i class="fas fa-' . ($m['type'] === 'success' ? 'check-circle' : 'exclamation-circle') . '"></i> ' . htmlspecialchars($m['text']) . '</div>';
}
?>

<div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;gap:8px;flex-wrap:wrap;">
        <span class="admin-card-title"><i class="fas fa-gavel"></i> 争议仲裁</span>
        <div class="admin-btn-group">
            <a href="disputes.php?v=open" class="admin-btn admin-btn-sm <?= $view === 'open' ? 'admin-btn-primary' : 'admin-btn-default' ?>"><i class="fas fa-hourglass-half"></i> 待仲裁</a>
            <a href="disputes.php?v=all" class="admin-btn admin-btn-sm <?= $view === 'all' ? 'admin-btn-primary' : 'admin-btn-default' ?>"><i class="fas fa-list"></i> 全部记录</a>
        </div>
    </div>
</div>

<?php if (!$dbOk): ?>
    <div class="admin-alert warning"><i class="fas fa-exclamation-triangle"></i> 任务相关数据表尚未创建，请先执行 <code>init/migrate-task.sql</code>。</div>
<?php elseif ($view === 'open' && !$open): ?>
    <div class="admin-card">
        <div class="admin-empty-state" style="padding:48px 0;"><i class="fas fa-balance-scale"></i><p>暂无待仲裁的争议</p></div>
    </div>
<?php elseif ($view === 'all' && !$all): ?>
    <div class="admin-card">
        <div class="admin-empty-state" style="padding:48px 0;"><i class="fas fa-balance-scale"></i><p>暂无争议记录</p></div>
    </div>
<?php else: $list = $view === 'open' ? $open : $all; ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">
                <i class="fas fa-file-contract"></i> <?= $view === 'open' ? '待仲裁争议（含双方主张与交付凭证）' : '全部争议记录' ?>
            </span>
            <span class="admin-text-muted">共 <?= count($list) ?> 条</span>
        </div>
        <div class="admin-table-responsive">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>争议#</th><th>任务</th><th>雇主</th><th>接单人</th><th>发起方</th><th>事由 / 交付凭证</th><th>状态</th><th style="min-width:190px;"><?= $view === 'open' ? '仲裁' : '结果' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $d): ?>
                        <tr>
                            <td>#<?= (int)$d['id'] ?></td>
                            <td style="max-width:190px;">
                                <a href="https://task.58.tl/view.php?id=<?= (int)$d['task_id'] ?>" target="_blank" style="font-weight:600;color:#1f2937;text-decoration:none;"><?= htmlspecialchars(mb_substr((string)$d['task_title'], 0, 20)) ?></a>
                                <small class="admin-text-muted" style="display:block;">
                                    <?php if ($d['reward_type'] === 'cash'): ?>现金 ¥ <?= number_format((float)$d['reward_amount'] / 100, 2) ?>
                                    <?php else: ?><?= number_format((float)$d['reward_amount']) ?> 人气<?= !empty($d['task_city']) ? ' / ' . htmlspecialchars($d['task_city']) : '' ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td><?= htmlspecialchars($d['employer_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['worker_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($d['initiator_name'] ?? '') ?></td>
                            <td style="max-width:220px;">
                                <div style="font-size:12px;line-height:1.5;color:#374151;word-break:break-all;">🗣 <?= htmlspecialchars(mb_substr((string)$d['reason'], 0, 60)) ?></div>
                                <?php if (!empty($d['proof_text'])): ?>
                                    <small class="admin-text-muted" style="display:block;margin-top:2px;">📎 <?= htmlspecialchars(mb_substr((string)$d['proof_text'], 0, 40)) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($d['proof_image'])): ?>
                                    <a href="https://task.58.tl/<?= ltrim((string)$d['proof_image'], '/') ?>" target="_blank" title="查看凭证大图">
                                        <img src="https://task.58.tl/<?= ltrim((string)$d['proof_image'], '/') ?>" style="height:32px;border-radius:4px;margin-top:4px;" alt="凭证">
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($d['status'] === 'open'): ?>
                                    <span class="admin-badge danger">待仲裁</span>
                                    <div style="font-size:11px;color:#6b7280;margin-top:3px;"><?= date('m-d H:i', strtotime($d['created_at'])) ?> 发起</div>
                                <?php else: ?>
                                    <span class="admin-badge default">已裁决</span>
                                    <div style="font-size:11px;color:#6b7280;margin-top:3px;"><?= date('m-d H:i', strtotime($d['resolved_at'] ?: $d['created_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($d['status'] === 'open'): ?>
                                    <form method="post" class="admin-form-vertical" onsubmit="return confirm('确认执行该裁决？将通知雇主与接单人。');" style="gap:6px;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <input type="text" name="note" class="admin-form-input" required maxlength="500" placeholder="裁决说明（必填，将通知双方）" style="width:100%;">
                                        <div class="admin-btn-group">
                                            <button type="submit" name="resolution" value="settle" class="admin-btn admin-btn-sm admin-btn-success" title="认领成立，完成结算"><i class="fas fa-check"></i> 通过结算</button>
                                            <button type="submit" name="resolution" value="cancel" class="admin-btn admin-btn-sm admin-btn-danger" title="认领不成立，取消"><i class="fas fa-times"></i> 取消认领</button>
                                        </div>
                                    </form>
                                    <?php if ($d['reward_type'] !== 'cash'): ?>
                                        <div style="font-size:11px;color:#b45309;margin-top:4px;">人气值任务：通过结算将即时划转；雇主余额不足时自动转“结算中”待补足。</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="admin-badge <?= $d['resolution'] === 'settle' ? 'success' : 'default' ?>">
                                        <?= $d['resolution'] === 'settle' ? '完成结算' : '取消认领' ?>
                                    </span>
                                    <?php if (!empty($d['admin_note'])): ?>
                                        <div style="font-size:11px;color:#6b7280;margin-top:3px;word-break:break-all;"><?= htmlspecialchars(mb_substr((string)$d['admin_note'], 0, 40)) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
