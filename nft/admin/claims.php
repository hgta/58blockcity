<?php
/**
 * NFT 认领管理 — 稽核与订正
 * 用于定位因注册缺陷导致的错误归属认领，并修正到正确的用户名下
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php'); exit;
}

$nft = new NFT($pdo);
$adminId = (int)$_SESSION['user_id'];

// 审计表是否就绪
$tableReady = true;
try {
    $pdo->query("SELECT 1 FROM nft_claim_corrections LIMIT 1");
} catch (PDOException $e) {
    $tableReady = false;
}

$msg = '';
$msgType = 'success';

// ---------------- 订正操作 ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {
    $action = $_POST['action'] ?? '';

    if ($action === 'correct_one') {
        $r = $nft->correctClaimOwner(
            $_POST['claim_id'] ?? 0,
            $_POST['to_user_id'] ?? 0,
            $adminId,
            $_POST['reason'] ?? '',
            $defaultReason
        );
        $msg = $r['message'];
        $msgType = $r['success'] ? 'success' : 'error';
    } elseif ($action === 'correct_batch') {
        $fromUserId = $_POST['from_user_id'] ?? 0;
        $toUserId   = $_POST['to_user_id'] ?? 0;
        $reason     = $_POST['reason'] ?? '';
        $dateFrom   = trim($_POST['date_from'] ?? '');
        $dateTo     = trim($_POST['date_to'] ?? '');

        if (!isset($_POST['confirmed'])) {
            // 未确认时先展示影响面
            $count = $nft->countCorrectableClaims($fromUserId, $dateFrom, $dateTo);
            $msg = "批量订正将影响 {$count} 条认领记录（来源用户 #" . intval($fromUserId) . " → 目标用户 #" . intval($toUserId) . "），请确认后执行。";
            $msgType = 'warning';
            $_POST['need_confirm'] = 1;
        } else {
            $r = $nft->batchCorrectClaimOwner($fromUserId, $toUserId, $adminId, $reason, $dateFrom, $dateTo);
            $msg = "批量订正完成：成功 {$r['success']} 条，失败 {$r['failed']} 条。";
            if (!empty($r['errors'])) {
                $msg .= ' 失败明细：' . implode('；', array_slice($r['errors'], 0, 5));
                if (count($r['errors']) > 5) $msg .= ' …';
            }
            $msgType = $r['failed'] > 0 ? 'warning' : 'success';
        }
    }
}

// ---------------- 视图与筛选 ----------------
$view = $_GET['view'] ?? 'records';
// 订正后保留回到原视图
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['return_view'])) {
    $view = $_POST['return_view'];
}
$defaultReason = '注册缺陷导致误归属，订正回真实用户';

$filters = [
    'nft_code'   => trim($_GET['nft_code'] ?? ''),
    'city_id'    => intval($_GET['city_id'] ?? 0),
    'user'       => trim($_GET['user'] ?? ''),
    'block_id'   => trim($_GET['block_id'] ?? ''),
    'date_from'  => trim($_GET['date_from'] ?? ''),
    'date_to'    => trim($_GET['date_to'] ?? ''),
    'is_current' => $_GET['is_current'] ?? '1',
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

$threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 300;
if ($threshold <= 0) $threshold = 300;

$cities = $pdo->query("SELECT id, name FROM cities ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$records = ['list' => [], 'total' => 0, 'pages' => 0];
$suspicious = [];
$logs = ['list' => [], 'total' => 0, 'pages' => 0];

$fatal = '';
try {
    if ($view === 'suspicious') {
        $suspicious = $nft->getSuspiciousClaims($threshold);
    } elseif ($view === 'logs') {
        $logs = $nft->getCorrectionLogs($page, $perPage);
    } else {
        $records = $nft->getClaimRecords($filters, $page, $perPage);
    }
} catch (PDOException $e) {
    $fatal = '查询失败：' . $e->getMessage();
}

$admin_site_config = ['site' => 'nft', 'page_title' => '认领管理'];
require_once '../../shared/admin/admin-header.php';

$inputStyle = 'padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;';

function claims_qs($filters, $threshold) {
    $q = [];
    foreach ($filters as $k => $v) {
        if ($v !== '' && $v !== 0 && $v !== null) $q[$k] = $v;
    }
    $q['threshold'] = $threshold;
    return http_build_query($q);
}
$baseQs = claims_qs($filters, $threshold);
?>

<div class="admin-content">
<div class="admin-page-header">
    <h1>认领管理</h1>
    <div class="admin-btn-group">
        <a href="?view=records<?= $baseQs ? '&' . $baseQs : '' ?>" class="admin-btn admin-btn-sm <?= $view === 'records' ? 'admin-btn-primary' : '' ?>">认领记录</a>
        <a href="?view=suspicious&threshold=<?= $threshold ?>" class="admin-btn admin-btn-sm <?= $view === 'suspicious' ? 'admin-btn-primary' : '' ?>">可疑认领</a>
        <a href="?view=logs" class="admin-btn admin-btn-sm <?= $view === 'logs' ? 'admin-btn-primary' : '' ?>">订正历史</a>
    </div>
</div>

<?php if (!$tableReady): ?>
<div class="admin-alert admin-alert-error">
    <i class="fas fa-exclamation-triangle"></i> 订正审计表 <code>nft_claim_corrections</code> 尚未创建，订正功能不可用。请先执行 <code>init/nft_claim_corrections.sql</code>。
</div>
<?php endif; ?>

<?php if ($fatal): ?>
<div class="admin-alert admin-alert-error"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($fatal) ?></div>
<?php endif; ?>

<?php if ($msg): ?>
<div class="admin-alert admin-alert-<?= $msgType === 'error' ? 'error' : ($msgType === 'warning' ? 'warning' : 'success') ?>">
    <i class="fas fa-info-circle"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if ($view === 'records'): ?>
<!-- 检索 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="view" value="records">
            <input type="text" name="nft_code" value="<?= htmlspecialchars($filters['nft_code']) ?>" placeholder="NFT 编号" style="<?= $inputStyle ?>width:120px;">
            <select name="city_id" style="<?= $inputStyle ?>">
                <option value="0">全部城市</option>
                <?php foreach ($cities as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filters['city_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="user" value="<?= htmlspecialchars($filters['user']) ?>" placeholder="归属用户(用户名/ID)" style="<?= $inputStyle ?>width:160px;">
            <input type="text" name="block_id" value="<?= htmlspecialchars($filters['block_id']) ?>" placeholder="区块ID" style="<?= $inputStyle ?>width:120px;">
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" style="<?= $inputStyle ?>">
            <span style="color:#94a3b8;">~</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" style="<?= $inputStyle ?>">
            <select name="is_current" style="<?= $inputStyle ?>">
                <option value="1" <?= $filters['is_current'] === '1' ? 'selected' : '' ?>>仅当前关联</option>
                <option value="0" <?= $filters['is_current'] === '0' ? 'selected' : '' ?>>仅历史记录</option>
                <option value=""  <?= $filters['is_current'] === '' ? 'selected' : '' ?>>全部</option>
            </select>
            <button class="admin-btn admin-btn-primary admin-btn-sm">检索</button>
            <a href="?view=records" class="admin-btn admin-btn-secondary admin-btn-sm">重置</a>
        </form>
    </div>
</div>

<!-- 认领记录 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">认领记录</span>
        <span class="admin-page-info">共 <?= $records['total'] ?> 条</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead><tr>
                <th>ID</th><th>NFT</th><th>城市</th><th>归属用户</th><th>区块ID</th><th>状态</th><th>认领时间</th><th>操作</th>
            </tr></thead>
            <tbody>
            <?php if (empty($records['list'])): ?>
            <tr><td colspan="8" class="admin-empty-state" style="padding:30px;">暂无数据</td></tr>
            <?php else: foreach ($records['list'] as $r): ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <?php if (!empty($r['base_image'])): ?>
                        <img src="../avatar/<?= htmlspecialchars($r['base_image']) ?>" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">
                        <?php endif; ?>
                        <a href="../nft/<?= urlencode($r['nft_code']) ?>.html" target="_blank" style="color:#60a5fa;"><strong><?= htmlspecialchars($r['nft_code']) ?></strong></a>
                    </div>
                </td>
                <td><?= htmlspecialchars($r['city_name'] ?? '-') ?></td>
                <td>
                    <?= htmlspecialchars($r['username'] ?? '已删除') ?>
                    <span style="color:#64748b;font-size:12px;">#<?= $r['user_id'] ?></span>
                </td>
                <td style="font-size:12px;color:#94a3b8;"><?= htmlspecialchars($r['block_id'] ?? '-') ?></td>
                <td>
                    <?php if ($r['is_current']): ?><span class="admin-badge success">当前</span>
                    <?php else: ?><span class="admin-badge default">历史</span><?php endif; ?>
                    <?php if ($r['is_listed']): ?><span class="admin-badge info">挂售</span><?php endif; ?>
                </td>
                <td style="font-size:12px;color:#94a3b8;"><?= $r['created_at'] ?></td>
                <td>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-primary"
                            onclick="openCorrect(<?= $r['id'] ?>,'<?= htmlspecialchars($r['nft_code'], ENT_QUOTES) ?>','<?= htmlspecialchars($r['username'] ?? '', ENT_QUOTES) ?>',<?= $r['user_id'] ?>)">订正</button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php if ($records['pages'] > 1): ?>
<nav class="admin-pagination">
    <?php
    $qs = $baseQs;
    for ($i = 1; $i <= $records['pages']; $i++):
        if ($i == 1 || $i == $records['pages'] || abs($i - $page) <= 2):
    ?>
        <?php if ($i == $page): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?view=records&<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endif; endfor; ?>
</nav>
<?php endif; ?>

<?php elseif ($view === 'suspicious'): ?>
<!-- 可疑认领筛查 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="view" value="suspicious">
            <label style="font-size:14px;color:#94a3b8;">认领时间与注册时间相差不超过</label>
            <input type="number" name="threshold" value="<?= $threshold ?>" min="1" style="<?= $inputStyle ?>width:100px;">
            <span style="font-size:14px;color:#94a3b8;">秒</span>
            <button class="admin-btn admin-btn-primary admin-btn-sm">重新筛查</button>
        </form>
        <p style="margin:12px 0 0;font-size:13px;color:#64748b;">
            该特征为注册缺陷导致误归属的指纹：用户注册后同一请求周期内即完成认领。默认阈值 300 秒，用于容忍服务器时钟偏差与真实用户的快速认领。
        </p>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">可疑认领</span>
        <span class="admin-page-info"><?= count($suspicious) ?> 条</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead><tr>
                <th>ID</th><th>NFT</th><th>城市</th><th>归属用户</th><th>注册时间</th><th>认领时间</th><th>相差</th><th>操作</th>
            </tr></thead>
            <tbody>
            <?php if (empty($suspicious)): ?>
            <tr><td colspan="8" class="admin-empty-state" style="padding:30px;">未发现可疑认领</td></tr>
            <?php else: foreach ($suspicious as $r): ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <?php if (!empty($r['base_image'])): ?>
                        <img src="../avatar/<?= htmlspecialchars($r['base_image']) ?>" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">
                        <?php endif; ?>
                        <a href="../nft/<?= urlencode($r['nft_code']) ?>.html" target="_blank" style="color:#60a5fa;"><strong><?= htmlspecialchars($r['nft_code']) ?></strong></a>
                    </div>
                </td>
                <td><?= htmlspecialchars($r['city_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['username'] ?? '已删除') ?> <span style="color:#64748b;font-size:12px;">#<?= $r['user_id'] ?></span></td>
                <td style="font-size:12px;color:#94a3b8;"><?= $r['user_registered_at'] ?></td>
                <td style="font-size:12px;color:#94a3b8;"><?= $r['created_at'] ?></td>
                <td><span class="admin-badge warning"><?= intval($r['diff_seconds']) ?> 秒</span></td>
                <td>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-primary"
                            onclick="openCorrect(<?= $r['id'] ?>,'<?= htmlspecialchars($r['nft_code'], ENT_QUOTES) ?>','<?= htmlspecialchars($r['username'] ?? '', ENT_QUOTES) ?>',<?= $r['user_id'] ?>)">订正</button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php else: ?>
<!-- 批量订正 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title">批量订正（按来源用户）</span>
    </div>
    <div class="admin-card-body">
        <form method="post" <?= isset($_POST['need_confirm']) ? '' : 'onsubmit="return confirm(\'确认提交批量订正？系统会先统计影响条数。\')"' ?>>
            <input type="hidden" name="action" value="correct_batch">
            <?php if (isset($_POST['need_confirm'])): ?>
            <input type="hidden" name="confirmed" value="1">
            <?php endif; ?>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <label style="display:block;font-size:13px;color:#94a3b8;margin-bottom:6px;">来源用户 ID *</label>
                    <input type="number" name="from_user_id" required min="1" value="<?= isset($_POST['need_confirm']) ? intval($_POST['from_user_id']) : '' ?>" placeholder="例如 1" style="<?= $inputStyle ?>width:140px;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;color:#94a3b8;margin-bottom:6px;">目标用户 ID *</label>
                    <input type="number" name="to_user_id" required min="1" value="<?= isset($_POST['need_confirm']) ? intval($_POST['to_user_id']) : '' ?>" placeholder="例如 390" style="<?= $inputStyle ?>width:140px;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;color:#94a3b8;margin-bottom:6px;">认领时间从</label>
                    <input type="date" name="date_from" value="<?= isset($_POST['need_confirm']) ? htmlspecialchars($_POST['date_from']) : '' ?>" style="<?= $inputStyle ?>">
                </div>
                <div>
                    <label style="display:block;font-size:13px;color:#94a3b8;margin-bottom:6px;">到</label>
                    <input type="date" name="date_to" value="<?= isset($_POST['need_confirm']) ? htmlspecialchars($_POST['date_to']) : '' ?>" style="<?= $inputStyle ?>">
                </div>
            </div>
            <div style="margin:14px 0;">
                <label style="display:block;font-size:13px;color:#94a3b8;margin-bottom:6px;">订正原因（选填）</label>
                <textarea name="reason" rows="2" placeholder="留空则记录为默认原因：后台批量认领归属订正" style="<?= $inputStyle ?>width:100%;max-width:600px;resize:vertical;"><?= isset($_POST['need_confirm']) ? htmlspecialchars($_POST['reason']) : '' ?></textarea>
            </div>
            <button type="submit" class="admin-btn admin-btn-primary" <?= $tableReady ? '' : 'disabled' ?>>
                <?= isset($_POST['need_confirm']) ? '确认执行订正' : '统计影响并提交' ?>
            </button>
            <p style="margin:12px 0 0;font-size:13px;color:#64748b;">
                时间范围留空表示不限时间。批量订正逐条处理，单条失败不影响其余记录，完成后会报告成功与失败条数。
            </p>
        </form>
    </div>
</div>

<!-- 订正历史 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">订正历史</span>
        <span class="admin-page-info">共 <?= $logs['total'] ?> 条</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead><tr>
                <th>时间</th><th>NFT</th><th>城市</th><th>原归属</th><th>新归属</th><th>操作人</th><th>原因</th>
            </tr></thead>
            <tbody>
            <?php if (empty($logs['list'])): ?>
            <tr><td colspan="7" class="admin-empty-state" style="padding:30px;">暂无订正记录</td></tr>
            <?php else: foreach ($logs['list'] as $l): ?>
            <tr>
                <td style="font-size:12px;color:#94a3b8;"><?= $l['created_at'] ?></td>
                <td><?= htmlspecialchars($l['nft_code'] ?? '-') ?></td>
                <td><?= htmlspecialchars($l['city_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($l['from_username'] ?? '已删除') ?> <span style="color:#64748b;font-size:12px;">#<?= $l['from_user_id'] ?></span></td>
                <td><?= htmlspecialchars($l['to_username'] ?? '已删除') ?> <span style="color:#64748b;font-size:12px;">#<?= $l['to_user_id'] ?></span></td>
                <td><?= htmlspecialchars($l['admin_name'] ?? ('#' . $l['admin_id'])) ?></td>
                <td style="font-size:13px;color:#94a3b8;"><?= htmlspecialchars($l['reason']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php if ($logs['pages'] > 1): ?>
<nav class="admin-pagination">
    <?php for ($i = 1; $i <= $logs['pages']; $i++): ?>
        <?php if ($i == $page): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?view=logs&page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif; ?>

<?php endif; ?>
</div>

<?php if ($view === 'records' || $view === 'suspicious'): ?>
<!-- 单条订正弹窗（页面级共用，仅定义一次） -->
<div id="correctModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;">
    <div class="admin-card" style="width:460px;max-width:92vw;margin:0;">
        <div class="admin-card-header">
            <span class="admin-card-title">认领归属订正</span>
            <a href="javascript:void(0)" onclick="closeCorrect()" style="color:#94a3b8;">关闭</a>
        </div>
        <div class="admin-card-body">
            <div style="margin-bottom:14px;font-size:14px;color:#94a3b8;">
                记录 <strong id="cmClaimId" style="color:#e2e8f0;"></strong> ·
                NFT <strong id="cmNft" style="color:#e2e8f0;"></strong> ·
                当前归属 <strong id="cmFrom" style="color:#e2e8f0;"></strong>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="correct_one">
                <input type="hidden" name="return_view" value="<?= htmlspecialchars($view) ?>">
                <input type="hidden" name="claim_id" id="cmClaimIdInput">
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:13px;color:#94a3b8;margin-bottom:6px;">目标用户 ID *</label>
                    <input type="number" name="to_user_id" required min="1" placeholder="例如 390" style="<?= $inputStyle ?>width:100%;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;color:#94a3b8;margin-bottom:6px;">订正原因（选填）</label>
                    <textarea name="reason" rows="3" placeholder="留空则记录为默认原因：<?= htmlspecialchars($defaultReason) ?>" style="<?= $inputStyle ?>width:100%;resize:vertical;"><?= htmlspecialchars($defaultReason) ?></textarea>
                </div>
                <button type="submit" class="admin-btn admin-btn-primary" <?= $tableReady ? '' : 'disabled' ?>>确认订正</button>
            </form>
        </div>
    </div>
</div>

<script>
function openCorrect(id, code, username, userId) {
    document.getElementById('cmClaimId').textContent = '#' + id;
    document.getElementById('cmNft').textContent = code;
    document.getElementById('cmFrom').textContent = (username || '已删除') + ' (#' + userId + ')';
    document.getElementById('cmClaimIdInput').value = id;
    document.getElementById('correctModal').style.display = 'flex';
}
function closeCorrect() {
    document.getElementById('correctModal').style.display = 'none';
}
</script>
<?php endif; ?>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
