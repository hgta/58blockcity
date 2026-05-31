<?php
/**
 * BCT 撮合手动触发页（管理员专用）
 * 
 * 访问：bct/admin/trigger_match.php
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';

// 检查管理员权限
checkLogin();
checkAdmin();

require_once '../includes/header.php';

$output = '';
$error = '';

// 执行撮合
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_start();
    require_once '../cron/auto_match.php';
    $output = ob_get_clean();
}
?>

<div class="container">
    <div class="page-header">
        <h1><i class="glyphicon glyphicon-flash"></i> BCT 自动撮合</h1>
        <p class="text-muted">手动触发平台交易自动撮合</p>
    </div>

    <div class="card">
        <div class="card-body">
            <p>点击下方按钮，系统将遍历所有待处理的平台交易订单，尝试自动撮合。</p>
            <p><strong>撮合规则：</strong></p>
            <ul>
                <li>同城市 + 相反方向（买/卖）+ 价格交叉（买价 ≥ 卖价）+ 平台交易类型</li>
                <li>撮合后自动更新城市 BCT 价格</li>
            </ul>
            
            <form method="post" class="text-center" style="margin: 30px 0;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="glyphicon glyphicon-play"></i> 立即执行撮合
                </button>
            </form>
            
            <?php if ($output): ?>
            <div class="alert alert-success">
                <pre style="margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($output) ?></pre>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="bct_management.php" class="btn btn-default">
            <i class="glyphicon glyphicon-arrow-left"></i> 返回管理页
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
