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

// 总关注数（准确，不受分页影响）
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM model_follows WHERE user_id = ?");
$totalStmt->execute([$userId]);
$totalCount = intval($totalStmt->fetchColumn());

// 用户信息（侧栏复用，与其他用户页一致）
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

                <div class="sidebar-card user-sidebar-card text-center">
                    <div class="user-avatar mb-3">
                        <?php if (!empty($userInfo['avatar'])): ?>
                            <img src="../<?= htmlspecialchars($userInfo['avatar']) ?>" alt="" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
                        <?php else: ?>
                            <div class="avatar-placeholder-lg"><?= mb_substr($userInfo['username'], 0, 1) ?></div>
                        <?php endif; ?>
                    </div>
                    <h6 class="mb-1"><?= htmlspecialchars($userInfo['username']) ?></h6>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($userInfo['email']) ?></p>
                    <p class="text-muted small">注册于 <?= date('Y-m-d', strtotime($userInfo['created_at'])) ?></p>
                </div>
            </aside>
        </div>

        <div class="col-md-9">
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h4>我的关注</h4>
                    <p>共关注 <?= $totalCount ?> 位模特，点击查看 TA 的作品与新动态</p>
                </div>
                <div class="welcome-icon"><i class="fas fa-heart"></i></div>
            </div>

            <?php if (empty($list)): ?>
                <div class="model-empty">
                    <i class="fas fa-heart fa-2x mb-3"></i>
                    <p>你还没有关注任何模特</p>
                    <a href="../model/list.php" class="btn btn-primary">去模特库逛逛 →</a>
                </div>
            <?php else: ?>
                <div class="section-header">
                    <h5><i class="fas fa-heart text-primary mr-2"></i>关注的模特</h5>
                    <a href="../model/list.php" class="more-link">发现更多模特 &gt;</a>
                </div>
                <div class="model-grid" style="margin-top:16px;">
                    <?php foreach ($list as $m): ?>
                        <?= renderModelCard($m, $strips[$m['id']] ?? [], true, $userId) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ===== 与其他用户页统一风格 ===== */
.welcome-banner {
    background: linear-gradient(135deg, #ff6b00 0%, #ff8533 100%);
    color: #fff;
    border-radius: 12px;
    padding: 24px 28px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.welcome-banner h4 { margin-bottom: 6px; font-weight: 700; }
.welcome-banner p { margin: 0; opacity: 0.9; }
.welcome-icon { font-size: 48px; opacity: 0.25; }

.avatar-placeholder-lg {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg, #ff6b00, #ff8533);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 700; margin: 0 auto;
}

.shop-sidebar { width: 100%; }
.sidebar-nav { background: #fff; border-radius: 12px; padding: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 8px; color: #475569; text-decoration: none; font-size: 14px; font-weight: 500; transition: all .2s; position: relative; }
.nav-item:hover { background: #f1f5f9; color: #1e293b; text-decoration: none; }
.nav-item.active { background: #fff7ed; color: #ea580c; }
.sidebar-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.user-sidebar-card .avatar-placeholder-lg { margin: 0 auto; }

.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.section-header h5 { margin: 0; font-weight: 700; color: #1a1a2e; }
.more-link { font-size: 13px; color: #ff6b00; text-decoration: none; font-weight: 500; }
.more-link:hover { color: #e55d00; }

.model-empty {
    text-align: center; color: #6c757d;
    background: #fff; border-radius: 12px;
    padding: 48px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.model-empty p { margin: 12px 0 20px; }

@media (max-width: 768px) {
    .welcome-banner { flex-direction: column; text-align: center; gap: 10px; }
}
</style>

<script src="../model/follow.js"></script>

<?php require_once '../includes/footer.php'; ?>
