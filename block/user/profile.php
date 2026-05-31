<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';
require_once '../../classes/Block.php';

checkLogin();
$userId = $_SESSION['user_id'];
$user = new User($pdo);
$block = new Block($pdo);

$userInfo = $user->getUserById($userId);
$userBlocks = $block->getUserBlocks($userId);
$blockCount = count($userBlocks);
$totalValue = 0;
foreach ($userBlocks as $b) { $totalValue += $b['price'] ?? 0; }
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:800px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; }
.card { background:white; border-radius:8px; padding:25px; box-shadow:0 2px 10px rgba(0,0,0,0.08); margin-bottom:20px; }
.card-title { font-size:18px; font-weight:bold; border-bottom:2px solid #f0f0f0; padding-bottom:10px; margin-bottom:15px; }
.info-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dotted #eee; font-size:14px; }
.info-label { color:#666; }
.info-value { font-weight:500; }
.stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:15px; margin-top:15px; }
.stat-item { text-align:center; padding:15px; background:#f8f9fa; border-radius:8px; }
.stat-value { font-size:24px; font-weight:bold; color:#ff6b00; }
.stat-label { font-size:12px; color:#666; margin-top:4px; }
@media(max-width:600px){ .stat-grid { grid-template-columns:repeat(2,1fr); } }
</style>

<div class="container">
    <h1 class="page-title">个人资料</h1>
    
    <div class="card">
        <div class="card-title"><i class="fas fa-user"></i> 基本信息</div>
        <div class="info-row">
            <span class="info-label">用户名</span>
            <span class="info-value"><?= htmlspecialchars($userInfo['username']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">邮箱</span>
            <span class="info-value"><?= htmlspecialchars($userInfo['email'] ?? '未设置') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">城市</span>
            <span class="info-value"><?= htmlspecialchars($userInfo['city'] ?? '未设置') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">注册时间</span>
            <span class="info-value"><?= date('Y-m-d', strtotime($userInfo['created_at'] ?? 'now')) ?></span>
        </div>
        
        <div class="stat-grid">
            <div class="stat-item">
                <div class="stat-value"><?= $blockCount ?></div>
                <div class="stat-label">拥有区块</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">¥<?= number_format($totalValue) ?></div>
                <div class="stat-label">区块价值</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $userInfo['popularity'] ?? 0 ?></div>
                <div class="stat-label">人气值</div>
            </div>
        </div>
    </div>
    
    <a href="dashboard.php" class="btn" style="display:inline-block;padding:10px 20px;background:#3498db;color:white;border-radius:6px;text-decoration:none;">
        <i class="fas fa-arrow-left"></i> 返回仪表盘
    </a>
</div>

<?php require_once '../includes/footer.php'; ?>
