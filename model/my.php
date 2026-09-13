<?php
/**
 * 模特子站 · 我的申请（模特申请进度）
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/classes/Application.php';

if (!$modelUserId) {
    header('Location: ' . model_login_url(MODEL_BASE_URL . '/my.php'));
    exit;
}

$userId = $modelUserId;
$app    = new Application($pdo);
$all    = $app->getMyApplications($userId);

// 只展示模特类申请
$list = array_values(array_filter((array)$all, function ($r) {
    return ($r['type'] ?? '') === 'model';
}));

$statusMap = [
    'pending'   => ['label' => '待处理', 'cls' => 'st-pending'],
    'contacted' => ['label' => '已联系', 'cls' => 'st-contacted'],
    'approved'  => ['label' => '已通过', 'cls' => 'st-approved'],
    'rejected'  => ['label' => '已驳回', 'cls' => 'st-rejected'],
];

$site_config = model_site_config([
    'title'       => SeoHelper::title('我的模特申请 - 58 模特库'),
    'description' => SeoHelper::description('查看您提交的模特申请处理进度。', '58 模特库'),
    'keywords'    => '58模特,我的申请,模特申请进度',
    'canonical_url' => MODEL_BASE_URL . '/my.php',
]);
require_once __DIR__ . '/includes/header.php';
?>

<div class="model-wrap">
    <div class="m-form-card">
        <h1>我的申请</h1>
        <p class="sub">模特申请的处理进度</p>

        <?php if (empty($list)): ?>
            <div class="m-empty">
                <i class="fas fa-file-alt"></i>
                你还没有提交过模特申请
                <div style="margin-top:18px;">
                    <a class="m-btn m-btn-primary" href="/apply.php">立即申请加入</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($list as $row):
                $st  = $statusMap[$row['status']] ?? ['label' => $row['status'], 'cls' => 'st-pending'];
                $thumb = '';
                if (!empty($row['photos'])) {
                    $ph = json_decode($row['photos'], true);
                    if (is_array($ph) && !empty($ph[0])) $thumb = $ph[0];
                }
            ?>
            <div class="m-msg" style="align-items:flex-start;">
                <div class="av" style="border-radius:10px;width:68px;height:68px;">
                    <?php if ($thumb): ?>
                        <img src="<?= htmlspecialchars(model_media($thumb)) ?>" alt="">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="bd">
                    <div class="hd" style="flex-wrap:wrap;">
                        <strong style="font-size:15px;"><?= htmlspecialchars($row['nickname'] ?? '模特申请') ?></strong>
                        <span class="<?= $st['cls'] ?>" style="padding:3px 11px;border-radius:999px;font-size:12px;font-weight:600;"><?= $st['label'] ?></span>
                        <span style="font-size:12px;color:var(--muted);"><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></span>
                    </div>
                    <?php if (!empty($row['reject_reason'])): ?>
                        <div class="m-alert err" style="margin:8px 0 0;">驳回原因：<?= htmlspecialchars($row['reject_reason']) ?></div>
                    <?php endif; ?>
                    <?php if ($row['status'] === 'approved' && !empty($row['model_id'])): ?>
                        <a class="m-more" style="margin-top:10px;display:inline-flex;" href="/view.php?id=<?= intval($row['model_id']) ?>">
                            查看我的模特主页 <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="m-form-actions">
            <a href="/apply.php" class="m-back">再申请一次 →</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
