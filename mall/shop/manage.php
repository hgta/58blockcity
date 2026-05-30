<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../includes/header.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Product.php';
require_once '../../classes/Order.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$product = new Product($pdo);
$order = new Order($pdo);

// 获取用户店铺信息
$userShop = $shop->getShopByUserId($_SESSION['user_id']);
if (!$userShop) {
    header('Location: create.php');
    exit;
}

// 获取店铺ID（支持URL参数和用户店铺）
$shopId = isset($_GET['id']) ? intval($_GET['id']) : $userShop['id'];

// 验证用户是否有权限管理该店铺
if ($userShop['id'] != $shopId) {
    header('Location: manage.php?id=' . $userShop['id']);
    exit;
}

// 获取店铺统计信息
$productCount = $shop->getProductCount($shopId);
$orderStats = $shop->getOrderStats($shopId);

// 获取店铺支付设置
$paymentSettings = $shop->getPaymentSettings($shopId);

// 获取店铺商品
$shopProducts = $product->getProductsByShop($shopId, 10);

// 获取月销售额统计
$monthlySales = $shop->getMonthlySales($shopId, 6);

$error = '';
$success = '';

// 处理店铺信息更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_shop') {
        $shopName = trim($_POST['shop_name']);
        $shopDescription = trim($_POST['shop_description']);
        $contactInfo = trim($_POST['contact_info']);
        
        if (empty($shopName) || empty($shopDescription)) {
            $error = '店铺名称和描述不能为空';
        } else {
            $updateData = [
                'shop_name' => $shopName,
                'shop_description' => $shopDescription,
                'contact_info' => $contactInfo
            ];
            
            if ($shop->updateShop($shopId, $updateData)) {
                $success = '店铺信息更新成功';
                // 刷新店铺信息
                $userShop = $shop->getShopById($shopId);
            } else {
                $error = '店铺信息更新失败';
            }
        }
    }
    
    // 处理支付设置更新
    elseif ($_POST['action'] === 'update_payment_settings') {
        $paymentSettings = [];
        
        if (isset($_POST['payment_cities']) && is_array($_POST['payment_cities'])) {
            foreach ($_POST['payment_cities'] as $city) {
                $blockId = $_POST['block_id'][$city] ?? '';
                $isActive = isset($_POST['is_active'][$city]) ? 1 : 0;
                $minAmount = floatval($_POST['min_amount'][$city] ?? 0.01);
                $exchangeRate = floatval($_POST['exchange_rate'][$city] ?? 1.0000);
                
                if (!empty($blockId)) {
                    $paymentSettings[] = [
                        'city' => $city,
                        'block_id' => $blockId,
                        'is_active' => $isActive,
                        'min_amount' => $minAmount,
                        'exchange_rate' => $exchangeRate
                    ];
                }
            }
        }
        
        if ($shop->updatePaymentSettings($shopId, $paymentSettings)) {
            $success = '支付设置更新成功';
            // 刷新支付设置
            $paymentSettings = $shop->getPaymentSettings($shopId);
        } else {
            $error = '支付设置更新失败';
        }
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <!-- 店铺管理侧边栏 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">店铺管理</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="manage.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tachometer-alt"></i> 店铺概览
                    </a>
                    <a href="products.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-box"></i> 商品管理
                    </a>
                    <a href="orders.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-cart"></i> 订单管理
                    </a>
                    <a href="payment-settings.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog"></i> 支付设置
                    </a>
                    <a href="view.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action" target="_blank">
                        <i class="fas fa-external-link-alt"></i> 查看店铺
                    </a>
                </div>
            </div>
            
            <!-- 店铺状态 -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6>店铺状态</h6>
                    <span class="badge badge-<?= $userShop['status'] == 'active' ? 'success' : ($userShop['status'] == 'pending' ? 'warning' : 'danger') ?>">
                        <?= $userShop['status'] == 'active' ? '已激活' : ($userShop['status'] == 'pending' ? '审核中' : '已关闭') ?>
                    </span>
                    <?php if ($userShop['status'] == 'pending'): ?>
                        <p class="small text-muted mt-2 mb-0">您的店铺正在审核中，请耐心等待</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 快速操作 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">快速操作</h6>
                </div>
                <div class="card-body">
                    <a href="products.php?action=add&id=<?= $shopId ?>" class="btn btn-primary btn-sm btn-block mb-2">
                        <i class="fas fa-plus"></i> 添加商品
                    </a>
                    <a href="orders.php?id=<?= $shopId ?>" class="btn btn-outline-primary btn-sm btn-block mb-2">
                        <i class="fas fa-list"></i> 查看订单
                    </a>
                    <a href="payment-settings.php?id=<?= $shopId ?>" class="btn btn-outline-success btn-sm btn-block">
                        <i class="fas fa-cog"></i> 支付设置
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <!-- 店铺概览 -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">店铺概览</h4>
                    <span class="text-muted">ID: <?= $userShop['id'] ?></span>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <!-- 统计信息 -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-value"><?= $productCount ?></div>
                                <div class="stat-label">商品数量</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-value"><?= $orderStats ? number_format($orderStats['total_orders']) : 0 ?></div>
                                <div class="stat-label">总订单</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-value"><?= $userShop['total_sales'] ?? 0 ?></div>
                                <div class="stat-label">总销量</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-value"><?= number_format($userShop['rating'] ?? 5.0, 1) ?></div>
                                <div class="stat-label">店铺评分</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 店铺信息编辑和最近商品 -->
                    <div class="row">
                        <div class="col-md-6">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_shop">
                                
                                <div class="form-group">
                                    <label for="shop_name">店铺名称</label>
                                    <input type="text" class="form-control" id="shop_name" name="shop_name" 
                                           value="<?= htmlspecialchars($userShop['shop_name']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contact_info">联系方式</label>
                                    <input type="text" class="form-control" id="contact_info" name="contact_info" 
                                           value="<?= htmlspecialchars($userShop['contact_info'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="shop_description">店铺描述</label>
                                    <textarea class="form-control" id="shop_description" name="shop_description" 
                                              rows="4" required><?= htmlspecialchars($userShop['shop_description']) ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">更新店铺信息</button>
                            </form>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6>最近商品</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($shopProducts): ?>
                                        <?php foreach ($shopProducts as $productItem): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <img src="<?= '../' . htmlspecialchars($productItem['main_image']) ?>" 
                                                     alt="<?= htmlspecialchars($productItem['name']) ?>" 
                                                     class="product-thumb mr-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                <div class="flex-grow-1">
                                                    <div class="small"><?= htmlspecialchars(mb_substr($productItem['name'], 0, 20)) ?>...</div>
                                                    <div class="text-muted small">
                                                        <?= number_format($productItem['price_bct'], 2) ?> BCT
                                                        <?php if ($productItem['price_cny']): ?>
                                                            / ¥<?= number_format($productItem['price_cny'], 2) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <span class="badge badge-<?= $productItem['status'] == 'active' ? 'success' : 'secondary' ?>">
                                                    <?= $productItem['status'] == 'active' ? '在售' : '下架' ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="products.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-primary btn-block mt-2">
                                            管理所有商品
                                        </a>
                                    <?php else: ?>
                                        <p class="text-muted">暂无商品</p>
                                        <a href="products.php?action=add&id=<?= $shopId ?>" class="btn btn-sm btn-primary">
                                            添加商品
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 支付设置概览 -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6>支付设置</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($paymentSettings): ?>
                                        <?php foreach ($paymentSettings as $setting): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="small"><?= htmlspecialchars($setting['city']) ?></span>
                                                <span class="badge badge-<?= $setting['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $setting['is_active'] ? '启用' : '禁用' ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="payment-settings.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-success btn-block mt-2">
                                            管理支付设置
                                        </a>
                                    <?php else: ?>
                                        <p class="text-muted small">暂无支付设置</p>
                                        <a href="payment-settings.php?id=<?= $shopId ?>" class="btn btn-sm btn-success">
                                            设置支付方式
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 销售统计图表（简单版本） -->
            <?php if ($monthlySales): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">销售统计</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>月份</th>
                                    <th>订单数</th>
                                    <th>销售额 (BCT)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthlySales as $sales): ?>
                                <tr>
                                    <td><?= $sales['month'] ?></td>
                                    <td><?= $sales['order_count'] ?></td>
                                    <td><?= number_format($sales['total_sales'] ?? 0, 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.stat-card {
    text-align: center;
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #007bff;
}
.stat-label {
    font-size: 14px;
    color: #6c757d;
}
.product-thumb {
    border-radius: 3px;
}
.table td {
    vertical-align: middle;
}
</style>

<?php require_once '../includes/footer.php'; ?>