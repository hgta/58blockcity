<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../includes/header.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Order.php';
require_once '../../classes/Product.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$order = new Order($pdo);
$product = new Product($pdo);

$userId = $_SESSION['user_id'];

// 获取用户信息
$userStmt = $pdo->prepare("SELECT username, email, created_at FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

// 获取用户店铺信息
$userShop = $shop->getShopByUserId($userId);
$hasShop = !empty($userShop);

// 获取用户订单统计
$orderStats = $order->getUserOrderStats($userId);

// 获取最近订单
$recentOrders = $order->getUserOrders($userId, 5);

// 获取用户店铺的商品统计（如果有店铺）
$shopProductStats = [];
if ($hasShop) {
    $shopProductStats = $product->getShopProductStats($userShop['id']);
}

// 获取用户收藏或浏览历史（这里简化为最近浏览的商品）
$recentProducts = $product->getRecentProducts(6);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <!-- 用户侧边栏 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">用户中心</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tachometer-alt"></i> 仪表板
                    </a>
                    <a href="orders.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-cart"></i> 我的订单
                    </a>
                    <a href="profile.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-edit"></i> 个人信息
                    </a>
                    <a href="shops.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store"></i> 我的店铺
                    </a>
                    <a href="address.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-address-book"></i> 收货地址
                    </a>
                    <a href="security.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shield-alt"></i> 安全设置
                    </a>
                </div>
            </div>
            
            <!-- 用户信息卡片 -->
            <div class="card mt-3">
                <div class="card-body text-center">
                    <div class="user-avatar mb-3">
                        <i class="fas fa-user-circle fa-3x text-primary"></i>
                    </div>
                    <h6 class="mb-1"><?= htmlspecialchars($userInfo['username']) ?></h6>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($userInfo['email']) ?></p>
                    <p class="text-muted small">
                        注册时间: <?= date('Y-m-d', strtotime($userInfo['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <!-- 欢迎横幅 -->
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="card-title mb-2">欢迎回来，<?= htmlspecialchars($userInfo['username']) ?>！</h4>
                            <p class="card-text mb-0">今天是 <?= date('Y年m月d日') ?>，祝您购物愉快！</p>
                        </div>
                        <div class="col-md-4 text-right">
                            <i class="fas fa-shopping-bag fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 统计卡片 -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart text-primary"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $orderStats['total_orders'] ?? 0 ?></div>
                            <div class="stat-label">总订单</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $orderStats['completed_orders'] ?? 0 ?></div>
                            <div class="stat-label">已完成</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock text-warning"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $orderStats['pending_orders'] ?? 0 ?></div>
                            <div class="stat-label">待处理</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-store text-info"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $hasShop ? '1' : '0' ?></div>
                            <div class="stat-label">我的店铺</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- 最近订单 -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">最近订单</h5>
                            <a href="orders.php" class="btn btn-sm btn-outline-primary">查看全部</a>
                        </div>
                        <div class="card-body">
                            <?php if ($recentOrders && count($recentOrders) > 0): ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <div class="order-item mb-3 pb-3 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">订单号: <?= htmlspecialchars($order['order_no']) ?></h6>
                                                <p class="text-muted small mb-1">
                                                    金额: <?= number_format($order['total_amount'], 2) ?> BCT
                                                </p>
                                                <p class="text-muted small mb-0">
                                                    时间: <?= date('m-d H:i', strtotime($order['created_at'])) ?>
                                                </p>
                                            </div>
                                            <span class="badge badge-<?= 
                                                $order['status'] == 'completed' ? 'success' : 
                                                ($order['status'] == 'pending' ? 'warning' : 
                                                ($order['status'] == 'paid' ? 'info' : 'secondary')) 
                                            ?>">
                                                <?= $order['status'] == 'completed' ? '已完成' : 
                                                    ($order['status'] == 'pending' ? '待支付' : 
                                                    ($order['status'] == 'paid' ? '已支付' : 
                                                    ($order['status'] == 'shipped' ? '已发货' : $order['status']))) 
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                                    <p>暂无订单记录</p>
                                    <a href="../product/list.php" class="btn btn-primary">去购物</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 店铺管理（如果有店铺） -->
                <div class="col-md-6">
                    <?php if ($hasShop): ?>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">店铺管理</h5>
                                <a href="../shop/manage.php?id=<?= $userShop['id'] ?>" class="btn btn-sm btn-outline-primary">管理店铺</a>
                            </div>
                            <div class="card-body">
                                <div class="shop-info mb-3">
                                    <h6 class="text-primary"><?= htmlspecialchars($userShop['shop_name']) ?></h6>
                                    <p class="text-muted small mb-2">
                                        <?= htmlspecialchars(mb_substr($userShop['shop_description'], 0, 50)) ?>...
                                    </p>
                                    <div class="d-flex justify-content-between text-muted small">
                                        <span>状态: 
                                            <span class="badge badge-<?= 
                                                $userShop['status'] == 'active' ? 'success' : 
                                                ($userShop['status'] == 'pending' ? 'warning' : 'danger')
                                            ?>">
                                                <?= $userShop['status'] == 'active' ? '营业中' : 
                                                    ($userShop['status'] == 'pending' ? '审核中' : '已关闭')
                                                ?>
                                            </span>
                                        </span>
                                        <span>评分: <?= number_format($userShop['rating'], 1) ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($shopProductStats): ?>
                                <div class="shop-stats">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="stat-number"><?= $shopProductStats['total_products'] ?? 0 ?></div>
                                            <div class="stat-label small">商品数</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="stat-number"><?= $shopProductStats['active_products'] ?? 0 ?></div>
                                            <div class="stat-label small">在售</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="stat-number"><?= $userShop['total_sales'] ?></div>
                                            <div class="stat-label small">总销量</div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-store fa-2x text-muted mb-3"></i>
                                <h6>您还没有店铺</h6>
                                <p class="text-muted small mb-3">开启您的电商之旅，创建个人店铺</p>
                                <a href="../shop/create.php" class="btn btn-primary">创建店铺</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- 快速操作 -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">快速操作</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <a href="../product/list.php" class="quick-action">
                                        <i class="fas fa-shopping-bag fa-2x text-primary mb-2"></i>
                                        <div class="small">浏览商品</div>
                                    </a>
                                </div>
                                <div class="col-4">
                                    <a href="orders.php" class="quick-action">
                                        <i class="fas fa-list-alt fa-2x text-success mb-2"></i>
                                        <div class="small">我的订单</div>
                                    </a>
                                </div>
                                <div class="col-4">
                                    <a href="profile.php" class="quick-action">
                                        <i class="fas fa-user-cog fa-2x text-info mb-2"></i>
                                        <div class="small">个人设置</div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 推荐商品 -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">猜你喜欢</h5>
                    <a href="../product/list.php" class="btn btn-sm btn-outline-primary">更多推荐</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($recentProducts && count($recentProducts) > 0): ?>
                            <?php foreach ($recentProducts as $product): ?>
                                <div class="col-md-4 col-6 mb-3">
                                    <div class="product-card-small">
                                        <a href="../product/detail.php?id=<?= $product['id'] ?>">
                                            <div class="product-image">
                                                <img src="<?= '../' . htmlspecialchars($product['main_image']) ?>" 
                                                     alt="<?= htmlspecialchars($product['name']) ?>">
                                            </div>
                                            <div class="product-info">
                                                <h6 class="product-name"><?= htmlspecialchars(mb_substr($product['name'], 0, 15)) ?>...</h6>
                                                <div class="product-price">
                                                    <span class="bct-price"><?= number_format($product['price_bct'], 2) ?> BCT</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center text-muted py-4">
                                <i class="fas fa-box-open fa-2x mb-3"></i>
                                <p>暂无推荐商品</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}
.stat-icon {
    font-size: 24px;
    margin-right: 15px;
    width: 50px;
    text-align: center;
}
.stat-content {
    flex: 1;
}
.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
    line-height: 1;
}
.stat-label {
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
}
.order-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
.quick-action {
    display: block;
    color: #495057;
    text-decoration: none;
    transition: all 0.3s;
    padding: 10px 5px;
    border-radius: 5px;
}
.quick-action:hover {
    background: #f8f9fa;
    color: #007bff;
    text-decoration: none;
}
.product-card-small {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}
.product-card-small:hover {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.product-card-small .product-image {
    height: 100px;
    overflow: hidden;
}
.product-card-small .product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.product-card-small .product-info {
    padding: 10px;
}
.product-card-small .product-name {
    font-size: 14px;
    margin-bottom: 5px;
    color: #2c3e50;
}
.product-card-small .product-price {
    font-size: 12px;
    color: #e74c3c;
    font-weight: bold;
}
.shop-stats .stat-number {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
}
.shop-stats .stat-label {
    font-size: 12px;
    color: #6c757d;
    margin-top: 2px;
}
</style>

<?php require_once '../includes/footer.php'; ?>