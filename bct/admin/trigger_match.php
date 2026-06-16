<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';

checkAdmin();

$output = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    require_once '../cron/auto_match.php';
    $output = ob_get_clean();
}

$admin_site_config = ['site' => 'bct', 'page_title' => '触发匹配'];
require_once '../../shared/admin/admin-header.php';
?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-exchange-alt"></i> BCT 自动撮合</span>
    </div>
    <div class="admin-card-body">
        <p style="color:#94a3b8;margin-bottom:12px;">点击下方按钮，系统将遍历所有待处理的平台交易订单，尝试自动撮合。</p>
        <p style="color:#f1f5f9;font-weight:600;margin-bottom:8px;">撮合规则：</p>
        <ul style="color:#94a3b8;margin-bottom:20px;padding-left:20px;">
            <li>同城市 + 相反方向（买/卖）+ 价格交叉（买价 ≥ 卖价）+ 平台交易类型</li>
            <li>撮合后自动更新城市 BCT 价格</li>
        </ul>

        <form method="post" style="text-align:center;margin:30px 0;">
            <button type="submit" class="admin-btn admin-btn-primary" style="padding:12px 32px;font-size:15px;">
                <i class="fas fa-play"></i> 立即执行撮合
            </button>
        </form>

        <?php if ($output): ?>
        <div class="admin-card" style="background:#0f172a;border:1px solid #334155;">
            <div class="admin-card-header">
                <span class="admin-card-title">执行结果</span>
            </div>
            <div class="admin-card-body">
                <pre style="color:#e2e8f0;white-space:pre-wrap;font-size:13px;"><?= htmlspecialchars($output) ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
