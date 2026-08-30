<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Application.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$app = new Application($pdo);
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$row = $id > 0 ? $app->getById($id) : null;

if (!$row) {
    header('Location: applications.php');
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'contacted') {
        if ($app->updateStatus($id, 'contacted')) {
            $msg = '<div class="admin-alert admin-alert-success">已标记为「已联系」</div>';
            $row['status'] = 'contacted';
        }
    } elseif ($action === 'rejected') {
        $reason = trim($_POST['reject_reason'] ?? '');
        if ($reason === '') {
            $msg = '<div class="admin-alert admin-alert-error">请填写驳回原因</div>';
        } elseif ($app->updateStatus($id, 'rejected', ['reject_reason' => $reason])) {
            $msg = '<div class="admin-alert admin-alert-success">已驳回该申请</div>';
            $row['status'] = 'rejected';
            $row['reject_reason'] = $reason;
        }
    } elseif ($action === 'remark') {
        if ($app->updateRemark($id, $_POST['admin_remark'] ?? '')) {
            $msg = '<div class="admin-alert admin-alert-success">备注已保存</div>';
            $row['admin_remark'] = trim($_POST['admin_remark'] ?? '');
        }
    }
}

$isModel = $row['type'] === 'model';
$statusColors = [
    'pending'   => ['#f59e0b', '#fef3c7'],
    'contacted' => ['#2563eb', '#dbeafe'],
    'approved'  => ['#16a34a', '#dcfce7'],
    'rejected'  => ['#dc2626', '#fee2e2'],
];
$sc = $statusColors[$row['status']] ?? $statusColors['pending'];

$admin_site_config = [
    'site'       => 'mall',
    'page_title' => '申请详情 #' . $row['id'] . ' - 58商城后台',
];
require_once '../../shared/admin/admin-header.php';

$inputStyle = 'width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;';
$labelStyle = 'display:block;font-size:13px;color:#94a3b8;margin-bottom:4px;';
$kvLabel = 'font-size:12px;color:#64748b;';
$kvValue = 'font-size:14px;color:#e2e8f0;margin-top:2px;';
?>

<div class="admin-content">
    <div class="admin-page-header">
        <h1>申请详情 #<?= $row['id'] ?></h1>
        <a href="applications.php?type=<?= $row['type'] ?>" class="admin-btn admin-btn-secondary"><i class="fas fa-arrow-left"></i> 返回列表</a>
    </div>

    <?= $msg ?>

    <!-- 状态卡 -->
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="width:64px;height:64px;border-radius:10px;overflow:hidden;background:#1e293b;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <?php if (!empty($row['photos_arr'][0])): ?>
                <img src="../<?= htmlspecialchars($row['photos_arr'][0]) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                <i class="fas <?= $isModel ? 'fa-user-circle' : 'fa-palette' ?>" style="font-size:26px;color:#475569;"></i>
                <?php endif; ?>
            </div>
            <div style="flex:1;min-width:200px;">
                <div style="font-size:17px;font-weight:700;color:#e2e8f0;"><?= htmlspecialchars($row['nickname']) ?></div>
                <div style="font-size:13px;color:#64748b;margin-top:4px;">
                    <?= Application::typeLabel($row['type']) ?> · 提交于 <?= date('Y-m-d H:i', strtotime($row['created_at'])) ?>
                    · 用户：<?= htmlspecialchars($row['username'] ?? 'ID ' . $row['user_id']) ?>
                </div>
            </div>
            <div>
                <span style="display:inline-block;padding:4px 14px;border-radius:999px;font-size:13px;font-weight:700;color:<?= $sc[0] ?>;background:<?= $sc[1] ?>;"><?= Application::statusLabel($row['status']) ?></span>
                <?php if ($row['status'] === 'approved'): ?>
                <div style="font-size:12px;color:#64748b;margin-top:6px;">处理于 <?= date('Y-m-d H:i', strtotime($row['reviewed_at'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 基本资料 -->
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-header"><span class="admin-card-title">基本资料</span></div>
        <div class="admin-card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;">
            <div><div style="<?= $kvLabel ?>">昵称</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['nickname']) ?></div></div>
            <div><div style="<?= $kvLabel ?>">性别</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['gender']) ?></div></div>
            <?php if ($isModel): ?>
            <div><div style="<?= $kvLabel ?>">年龄</div><div style="<?= $kvValue ?>"><?= $row['age'] ? htmlspecialchars($row['age']) : '-' ?></div></div>
            <div><div style="<?= $kvLabel ?>">身高</div><div style="<?= $kvValue ?>"><?= $row['height'] ? htmlspecialchars($row['height']) . ' cm' : '-' ?></div></div>
            <div><div style="<?= $kvLabel ?>">体重</div><div style="<?= $kvValue ?>"><?= $row['weight'] ? htmlspecialchars($row['weight']) . ' kg' : '-' ?></div></div>
            <div><div style="<?= $kvLabel ?>">三围</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['measurements'] ?? '-') ?></div></div>
            <div><div style="<?= $kvLabel ?>">爱好</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['hobbies'] ?? '-') ?></div></div>
            <?php else: ?>
            <div><div style="<?= $kvLabel ?>">创作风格</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['style'] ?? '-') ?></div></div>
            <div><div style="<?= $kvLabel ?>">简介</div><div style="<?= $kvValue ?>"><?= nl2br(htmlspecialchars($row['bio'] ?? '-')) ?></div></div>
            <?php endif; ?>
            <div><div style="<?= $kvLabel ?>">城市</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['city'] ?? '-') ?></div></div>
            <div><div style="<?= $kvLabel ?>">星座</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['zodiac'] ?? '-') ?></div></div>
        </div>
    </div>

    <!-- 联系方式 -->
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-header"><span class="admin-card-title">联系方式</span></div>
        <div class="admin-card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;">
            <div><div style="<?= $kvLabel ?>">手机号</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['phone'] ?? '-') ?></div></div>
            <div><div style="<?= $kvLabel ?>">微信</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['weixin'] ?? '-') ?></div></div>
            <div><div style="<?= $kvLabel ?>">QQ</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['qq'] ?? '-') ?></div></div>
            <div><div style="<?= $kvLabel ?>">微博</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['weibo'] ?? '-') ?></div></div>
            <div><div style="<?= $kvLabel ?>">小红书</div><div style="<?= $kvValue ?>"><?= htmlspecialchars($row['xiaohongshu'] ?? '-') ?></div></div>
        </div>
    </div>

    <!-- 照片墙 -->
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-header"><span class="admin-card-title">照片 / 作品（<?= count($row['photos_arr']) ?>）</span></div>
        <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php if (empty($row['photos_arr'])): ?>
            <div style="color:#64748b;padding:10px;">暂无照片</div>
            <?php else: ?>
            <?php foreach ($row['photos_arr'] as $p): ?>
            <a href="../<?= htmlspecialchars($p) ?>" target="_blank" title="点击查看大图">
                <img src="../<?= htmlspecialchars($p) ?>" style="width:110px;height:110px;border-radius:8px;object-fit:cover;border:1px solid #1e293b;">
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($row['status'] === 'rejected' && !empty($row['reject_reason'])): ?>
    <div class="admin-card" style="margin-bottom:20px;border-color:#7f1d1d;">
        <div class="admin-card-header"><span class="admin-card-title" style="color:#f87171;">驳回原因</span></div>
        <div class="admin-card-body" style="color:#fecaca;"><?= htmlspecialchars($row['reject_reason']) ?></div>
    </div>
    <?php endif; ?>

    <!-- 处理操作 -->
    <?php if (in_array($row['status'], ['pending', 'contacted'])): ?>
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-header"><span class="admin-card-title">处理操作</span></div>
        <div class="admin-card-body">
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="contacted">
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-phone"></i> 标记已联系</button>
                </form>
                <a href="<?= $isModel ? 'models.php' : 'authors.php' ?>?apply_id=<?= $row['id'] ?>" class="admin-btn admin-btn-primary">
                    <i class="fas fa-<?= $isModel ? 'user-plus' : 'palette' ?>"></i> 录入为<?= $isModel ? '模特' : '作者' ?>
                </a>
            </div>
            <form method="post" style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;">
                <input type="hidden" name="action" value="rejected">
                <input type="text" name="reject_reason" placeholder="驳回原因（必填，将展示给申请人）" maxlength="255"
                       style="<?= $inputStyle ?>max-width:360px;" required>
                <button type="submit" class="admin-btn admin-btn-danger" onclick="return confirm('确认驳回该申请？申请人将看到驳回原因。')"><i class="fas fa-times"></i> 驳回</button>
            </form>
        </div>
    </div>
    <?php elseif ($row['status'] === 'approved'): ?>
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-header"><span class="admin-card-title">已录入关联</span></div>
        <div class="admin-card-body">
            <?php if ($isModel && !empty($row['model_id'])): ?>
                <a href="models.php?edit=<?= (int)$row['model_id'] ?>" class="admin-btn admin-btn-secondary"><i class="fas fa-user-circle"></i> 查看模特 #<?= (int)$row['model_id'] ?></a>
            <?php elseif (!$isModel && !empty($row['author_id'])): ?>
                <a href="authors.php?edit=<?= (int)$row['author_id'] ?>" class="admin-btn admin-btn-secondary"><i class="fas fa-palette"></i> 查看作者 #<?= (int)$row['author_id'] ?></a>
            <?php else: ?>
                <div style="color:#64748b;">已通过，未关联正式记录</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 后台备注 -->
    <div class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">后台备注</span></div>
        <div class="admin-card-body">
            <form method="post">
                <input type="hidden" name="action" value="remark">
                <textarea name="admin_remark" rows="3" style="<?= $inputStyle ?>resize:vertical;" placeholder="记录联系情况、录入进展等（仅后台可见）"><?= htmlspecialchars($row['admin_remark'] ?? '') ?></textarea>
                <div style="margin-top:10px;">
                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-primary">保存备注</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
