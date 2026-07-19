<?php
// 我的关注（模特）
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Model.php';
require_once '../../classes/SeoHelper.php';
require_once '../model/card.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?redirect=' . urlencode('../user/following.php'));
    exit;
}

$userId = $_SESSION['user_id'];
$model = new Model($pdo);
$perPage = 24;
$page = max(1, intval($_GET['page'] ?? 1));

$list = $model->getFollowedModels($userId, $page, $perPage);
$ids = array_column($list, 'id');
$strips = $model->getModelImageStrips($ids, 4);

// 用户信息（侧栏复用）
$userStmt = $pdo->prepare("SELECT username, email, created_at, avatar FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

$site_config['title'] = '我的关注 - 58人气值商城';

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="../model/style.css">
<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <aside class="shop-sidebar">
                <nav class="sidebar-nav">
                    <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> 仪表板</a>
                    <a href="orders.php" class="nav-item"><i class="fas fa-shopping-cart"></i> 我的订单</a>
                    <a href="profile.php" class="nav-item"><i class="fas fa-user-edit"></i> 个人信息</a>
                    <a href="shops.php" class="nav-item"><i class="fas fa-store"></i> 我的店铺</a>
                    <a href="address.php" class="nav-item"><i class="fas fa-address-book"></i> 收货地址</a>
                    <a href="security.php" class="nav-item"><i class="fas fa-shield-alt"></i> 安全设置</a>
                    <a href="following.php" class="nav-item active"><i class="fas fa-heart"></i> 我的关注</a>
                </nav>
            </aside>
        </div>

        <div class="col-md-9">
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h4>我的关注</h4>
                    <p>共关注 <?= count($list) ?> 位模特，点击查看 TA 的作品与新动态</p>
                </div>
                <div class="welcome-icon"><i class="fas fa-heart"></i></div>
            </div>

            <?php if (empty($list)): ?>
                <div class="model-empty">你还没有关注任何模特，<a href="../model/list.php" style="color:#ff6b00;">去模特库逛逛 →</a></div>
            <?php else: ?>
                <div class="model-grid" style="margin-top:20px;">
                    <?php foreach ($list as $m): ?>
                        <?= renderModelCard($m, $strips[$m['id']] ?? [], true, $userId) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../model/follow.js"></script>

<?php require_once '../includes/footer.php'; ?>
