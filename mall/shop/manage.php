<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Product.php';
require_once '../../classes/Order.php';

// 检查用户是否已登录（必须在输出 HTML 之前）
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

$shopId = isset($_GET['id']) ? intval($_GET['id']) : $userShop['id'];
if ($userShop['id'] != $shopId) {
    header('Location: manage.php?id=' . $userShop['id']);
    exit;
}

// 刷新店铺信息
$userShop = $shop->getShopById($shopId);

// 获取各类统计数据
$productCount = $shop->getProductCount($shopId);
$productStats = $product->getShopProductStats($shopId);
$orderStats = $shop->getOrderStats($shopId);
$dailyStats = $shop->getShopDailyStats($shopId);
$statusDist = $shop->getShopOrderStatusDistribution($shopId);
$topProducts = $shop->getShopTopProducts($shopId, 5);
$recentOrders = $shop->getShopRecentOrders($shopId, 8);
$chartData = $shop->getShopDailySalesChart($shopId, 7);
$paymentSettings = $shop->getPaymentSettings($shopId);

// 处理店铺信息/装修更新
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_shop') {
        $shopName = trim($_POST['shop_name'] ?? '');
        $shopDescription = trim($_POST['shop_description'] ?? '');
        $contactInfo = trim($_POST['contact_info'] ?? '');
        $announcement = trim($_POST['announcement'] ?? '');
        $themeColor = trim($_POST['theme_color'] ?? '#ff6b00');

        if (empty($shopName) || empty($shopDescription)) {
            $error = '店铺名称和描述不能为空';
        } else {
            $updateData = [
                'shop_name' => $shopName,
                'shop_description' => $shopDescription,
                'contact_info' => $contactInfo,
            ];

            // 处理 Logo 上传
            if (isset($_FILES['shop_logo']) && $_FILES['shop_logo']['error'] === UPLOAD_ERR_OK) {
                $logoResult = uploadShopImage($_FILES['shop_logo'], 'logos');
                if ($logoResult['success']) {
                    $updateData['shop_logo'] = $logoResult['file_path'];
                }
            }

            // 处理 Banner 上传
            if (isset($_FILES['shop_banner']) && $_FILES['shop_banner']['error'] === UPLOAD_ERR_OK) {
                $bannerResult = uploadShopImage($_FILES['shop_banner'], 'banners');
                if ($bannerResult['success']) {
                    $updateData['shop_banner'] = $bannerResult['file_path'];
                }
            }

            if ($shop->updateShop($shopId, $updateData)) {
                $success = '店铺信息更新成功';
                $userShop = $shop->getShopById($shopId);
            } else {
                $error = '店铺信息更新失败';
            }
        }
    }
}

function uploadShopImage($file, $subdir) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => '只允许上传 JPG, PNG, WEBP 格式的图片'];
    }
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => '图片大小不能超过 2MB'];
    }

    $uploadDir = "../assets/uploads/shop/{$subdir}/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'file_path' => "assets/uploads/shop/{$subdir}/" . $fileName];
    }
    return ['success' => false, 'error' => '图片上传失败'];
}

// 计算环比变化
function changePercent($today, $yesterday) {
    if ($yesterday == 0) return $today > 0 ? 100 : 0;
    return round((($today - $yesterday) / $yesterday) * 100, 1);
}

$orderChange = changePercent($dailyStats['today']['orders'], $dailyStats['yesterday']['orders']);
$revenueChange = changePercent($dailyStats['today']['revenue'], $dailyStats['yesterday']['revenue']);

// 订单状态中文映射
$statusLabels = [
    'pending' => '待付款',
    'paid' => '已付款',
    'shipped' => '已发货',
    'completed' => '已完成',
    'cancelled' => '已取消',
    'refunded' => '已退款',
];
$statusColors = [
    'pending' => '#f59e0b',
    'paid' => '#3b82f6',
    'shipped' => '#8b5cf6',
    'completed' => '#10b981',
    'cancelled' => '#ef4444',
    'refunded' => '#6b7280',
];

// 准备图表数据
$chartLabels = json_encode(array_column($chartData, 'date'));
$chartOrders = json_encode(array_column($chartData, 'orders'));
$chartRevenue = json_encode(array_column($chartData, 'revenue'));

// 准备饼图数据
$pieLabels = [];
$pieData = [];
$pieColors = [];
foreach ($statusDist as $s) {
    $pieLabels[] = $statusLabels[$s['status']] ?? $s['status'];
    $pieData[] = (int)$s['count'];
    $pieColors[] = $statusColors[$s['status']] ?? '#6b7280';
}
$pieLabelsJson = json_encode($pieLabels);
$pieDataJson = json_encode($pieData);
$pieColorsJson = json_encode($pieColors);

// 引入共享头部（必须在所有 header() 调用之后）
require_once '../includes/header.php';
?>

<div class="shop-manage-wrapper">
    <!-- 顶部店铺信息栏 -->
    <div class="shop-header-bar">
        <div class="container">
            <div class="shop-header-inner">
                <div class="shop-brand">
                    <div class="shop-logo">
                        <?php if (!empty($userShop['shop_logo'])): ?>
                            <img src="../<?= htmlspecialchars($userShop['shop_logo']) ?>" alt="店铺Logo">
                        <?php else: ?>
                            <div class="shop-logo-placeholder"><?= mb_substr($userShop['shop_name'], 0, 1) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="shop-meta">
                        <h1 class="shop-name"><?= htmlspecialchars($userShop['shop_name']) ?></h1>
                        <div class="shop-tags">
                            <span class="tag tag-status tag-<?= $userShop['status'] ?>">
                                <?= $userShop['status'] == 'active' ? '营业中' : ($userShop['status'] == 'pending' ? '审核中' : '已关闭') ?>
                            </span>
                            <span class="tag">评分 <?= number_format($userShop['rating'] ?? 5, 1) ?></span>
                            <span class="tag">总销量 <?= $userShop['total_sales'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
                <div class="shop-quick-actions">
                    <a href="products.php?action=add&id=<?= $shopId ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> 添加商品
                    </a>
                    <a href="view.php?id=<?= $shopId ?>" class="btn btn-outline" target="_blank">
                        <i class="fas fa-external-link-alt"></i> 查看店铺
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container shop-container">
        <!-- 左侧边栏 -->
        <aside class="shop-sidebar">
            <nav class="sidebar-nav">
                <a href="manage.php?id=<?= $shopId ?>" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i> 数据看板
                </a>
                <a href="products.php?id=<?= $shopId ?>" class="nav-item">
                    <i class="fas fa-box"></i> 商品管理
                    <span class="nav-badge"><?= $productStats['total_products'] ?? 0 ?></span>
                </a>
                <a href="orders.php?id=<?= $shopId ?>" class="nav-item">
                    <i class="fas fa-shopping-cart"></i> 订单管理
                    <span class="nav-badge"><?= $orderStats['total_orders'] ?? 0 ?></span>
                </a>
                <a href="coupons.php?id=<?= $shopId ?>" class="nav-item">
                    <i class="fas fa-ticket-alt"></i> 优惠券
                </a>
                <a href="payment-settings.php?id=<?= $shopId ?>" class="nav-item">
                    <i class="fas fa-credit-card"></i> 支付设置
                    <span class="nav-badge"><?= count($paymentSettings) ?></span>
                </a>
                <a href=" decoration.php?id=<?= $shopId ?>" class="nav-item" onclick="document.getElementById('decoration-panel').scrollIntoView({behavior:'smooth'});return false;">
                    <i class="fas fa-paint-brush"></i> 店铺装修
                </a>
            </nav>

            <div class="sidebar-card">
                <h4>商品概览</h4>
                <div class="sidebar-stat-row">
                    <span class="label">在售商品</span>
                    <span class="value text-success"><?= $productStats['active_products'] ?? 0 ?></span>
                </div>
                <div class="sidebar-stat-row">
                    <span class="label">已售罄</span>
                    <span class="value text-warning"><?= $productStats['sold_out_products'] ?? 0 ?></span>
                </div>
                <div class="sidebar-stat-row">
                    <span class="label">草稿/下架</span>
                    <span class="value text-muted"><?= ($productStats['total_products'] ?? 0) - ($productStats['active_products'] ?? 0) - ($productStats['sold_out_products'] ?? 0) ?></span>
                </div>
            </div>
        </aside>

        <!-- 主内容区 -->
        <main class="shop-main">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- 核心数据卡片 -->
            <div class="stats-grid">
                <div class="stat-card highlight">
                    <div class="stat-icon-bg" style="background:#fff7ed;">
                        <i class="fas fa-yen-sign" style="color:#f97316;"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">今日营收</div>
                        <div class="stat-value">¥<?= number_format($dailyStats['today']['revenue'], 2) ?></div>
                        <div class="stat-change <?= $revenueChange >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-arrow-<?= $revenueChange >= 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($revenueChange) ?>% 较昨日
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bg" style="background:#eff6ff;">
                        <i class="fas fa-shopping-bag" style="color:#3b82f6;"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">今日订单</div>
                        <div class="stat-value"><?= $dailyStats['today']['orders'] ?></div>
                        <div class="stat-change <?= $orderChange >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-arrow-<?= $orderChange >= 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($orderChange) ?>% 较昨日
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bg" style="background:#f0fdf4;">
                        <i class="fas fa-users" style="color:#22c55e;"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">今日买家</div>
                        <div class="stat-value"><?= $dailyStats['today']['buyers'] ?></div>
                        <div class="stat-sub">独立访客数</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-bg" style="background:#faf5ff;">
                        <i class="fas fa-chart-line" style="color:#a855f7;"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">本月累计</div>
                        <div class="stat-value">¥<?= number_format($dailyStats['month']['revenue'], 0) ?></div>
                        <div class="stat-sub"><?= $dailyStats['month']['orders'] ?> 笔订单</div>
                    </div>
                </div>
            </div>

            <!-- 图表区域 -->
            <div class="chart-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-area"></i> 近7天销售趋势</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="salesChart" height="100"></canvas>
                    </div>
                </div>
                <div class="chart-card narrow">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> 订单状态分布</h3>
                        <span class="chart-subtitle">近30天</span>
                    </div>
                    <div class="chart-body">
                        <canvas id="statusChart" height="160"></canvas>
                    </div>
                </div>
            </div>

            <!-- 热销商品 + 最近订单 -->
            <div class="two-column-section">
                <div class="panel-card">
                    <div class="panel-header">
                        <h3><i class="fas fa-fire"></i> 热销商品 TOP5</h3>
                        <a href="products.php?id=<?= $shopId ?>" class="panel-more">查看全部 <i class="fas fa-chevron-right"></i></a>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($topProducts)): ?>
                            <div class="empty-tip">暂无销售数据</div>
                        <?php else: ?>
                            <?php foreach ($topProducts as $index => $p): ?>
                                <div class="top-product-item">
                                    <div class="rank <?= $index < 3 ? 'top' : '' ?>"><?= $index + 1 ?></div>
                                    <img src="../<?= htmlspecialchars($p['main_image'] ?? 'assets/images/default-product.png') ?>" alt="" class="product-thumb-sm">
                                    <div class="product-info">
                                        <div class="product-name"><?= htmlspecialchars(mb_substr($p['name'], 0, 20)) ?></div>
                                        <div class="product-meta">
                                            <span class="sold">已售 <?= (int)$p['sold_count'] ?></span>
                                            <span class="revenue">¥<?= number_format($p['total_revenue'], 0) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel-card">
                    <div class="panel-header">
                        <h3><i class="fas fa-clock"></i> 最近订单</h3>
                        <a href="orders.php?id=<?= $shopId ?>" class="panel-more">查看全部 <i class="fas fa-chevron-right"></i></a>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-tip">暂无订单</div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $o): ?>
                                <div class="recent-order-item">
                                    <div class="order-avatar">
                                        <?php if (!empty($o['buyer_avatar']) && $o['buyer_avatar'] != 'default.jpg'): ?>
                                            <img src="../../assets/images/<?= htmlspecialchars($o['buyer_avatar']) ?>" alt="">
                                        <?php else: ?>
                                            <div class="avatar-placeholder"><i class="fas fa-user"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-info">
                                        <div class="order-top">
                                            <span class="buyer-name"><?= htmlspecialchars($o['buyer_name'] ?? '匿名用户') ?></span>
                                            <span class="order-amount">¥<?= number_format($o['total_amount'], 2) ?></span>
                                        </div>
                                        <div class="order-bottom">
                                            <span class="order-no"><?= htmlspecialchars($o['order_no']) ?></span>
                                            <span class="order-status-badge" style="background:<?= $statusColors[$o['status']] ?? '#6b7280' ?>20;color:<?= $statusColors[$o['status']] ?? '#6b7280' ?>;">
                                                <?= $statusLabels[$o['status']] ?? $o['status'] ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="order-time"><?= date('m-d H:i', strtotime($o['created_at'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 店铺装修面板 -->
            <div class="panel-card" id="decoration-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-paint-brush"></i> 店铺装修</h3>
                </div>
                <div class="panel-body">
                    <form method="POST" enctype="multipart/form-data" class="decoration-form">
                        <input type="hidden" name="action" value="update_shop">

                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>店铺名称</label>
                                <input type="text" name="shop_name" value="<?= htmlspecialchars($userShop['shop_name']) ?>" class="form-control" required>
                            </div>
                            <div class="form-group col-6">
                                <label>联系方式</label>
                                <input type="text" name="contact_info" value="<?= htmlspecialchars($userShop['contact_info'] ?? '') ?>" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>店铺描述</label>
                            <textarea name="shop_description" rows="3" class="form-control" required><?= htmlspecialchars($userShop['shop_description']) ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>店铺 Logo <small class="text-muted">建议 200x200，最大 2MB</small></label>
                                <div class="image-upload-box">
                                    <?php if (!empty($userShop['shop_logo'])): ?>
                                        <img src="../<?= htmlspecialchars($userShop['shop_logo']) ?>" class="preview-img" id="logoPreview">
                                    <?php else: ?>
                                        <div class="upload-placeholder" id="logoPlaceholder">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span>点击上传 Logo</span>
                                        </div>
                                        <img src="" class="preview-img hidden" id="logoPreview">
                                    <?php endif; ?>
                                    <input type="file" name="shop_logo" accept="image/*" class="file-input" onchange="previewImage(this, 'logoPreview', 'logoPlaceholder')">
                                </div>
                            </div>
                            <div class="form-group col-6">
                                <label>店铺 Banner <small class="text-muted">建议 1200x300，最大 2MB</small></label>
                                <div class="image-upload-box banner-box">
                                    <?php if (!empty($userShop['shop_banner'])): ?>
                                        <img src="../<?= htmlspecialchars($userShop['shop_banner']) ?>" class="preview-img" id="bannerPreview">
                                    <?php else: ?>
                                        <div class="upload-placeholder" id="bannerPlaceholder">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span>点击上传 Banner</span>
                                        </div>
                                        <img src="" class="preview-img hidden" id="bannerPreview">
                                    <?php endif; ?>
                                    <input type="file" name="shop_banner" accept="image/*" class="file-input" onchange="previewImage(this, 'bannerPreview', 'bannerPlaceholder')">
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> 保存店铺信息
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// 销售趋势图
const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?= $chartLabels ?>,
        datasets: [{
            label: '订单数',
            data: <?= $chartOrders ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: '#3b82f6',
            yAxisID: 'y'
        }, {
            label: '营收 (¥)',
            data: <?= $chartRevenue ?>,
            borderColor: '#f97316',
            backgroundColor: 'rgba(249, 115, 22, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: '#f97316',
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } }
        },
        scales: {
            x: { grid: { display: false } },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                grid: { color: '#f3f4f6' },
                ticks: { stepSize: 1 }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: { display: false },
                ticks: { callback: function(v) { return '¥' + v; } }
            }
        }
    }
});

// 订单状态饼图
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?= $pieLabelsJson ?>,
        datasets: [{
            data: <?= $pieDataJson ?>,
            backgroundColor: <?= $pieColorsJson ?>,
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { usePointStyle: true, padding: 15, font: { size: 12 } }
            }
        }
    }
});

// 图片预览
function previewImage(input, previewId, placeholderId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            const placeholder = document.getElementById(placeholderId);
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<style>
/* 整体布局 */
.shop-manage-wrapper { background: #f8fafc; min-height: 100vh; }
.shop-header-bar { background: linear-gradient(135deg, #1e293b, #334155); color: #fff; padding: 24px 0; }
.shop-header-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.shop-brand { display: flex; align-items: center; gap: 16px; }
.shop-logo { width: 56px; height: 56px; border-radius: 12px; overflow: hidden; background: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.shop-logo img { width: 100%; height: 100%; object-fit: cover; }
.shop-logo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; font-size: 22px; font-weight: 700; }
.shop-name { font-size: 22px; font-weight: 700; margin: 0 0 6px; }
.shop-tags { display: flex; gap: 8px; flex-wrap: wrap; }
.tag { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 12px; background: rgba(255,255,255,0.15); color: #e2e8f0; }
.tag-status.tag-active { background: #22c55e; color: #fff; }
.tag-status.tag-pending { background: #f59e0b; color: #fff; }
.tag-status.tag-closed { background: #ef4444; color: #fff; }
.shop-quick-actions { display: flex; gap: 10px; }
.shop-quick-actions .btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
.shop-quick-actions .btn-primary { background: #f97316; color: #fff; border: none; }
.shop-quick-actions .btn-primary:hover { background: #ea580c; }
.shop-quick-actions .btn-outline { background: transparent; color: #e2e8f0; border: 1px solid rgba(255,255,255,0.3); }
.shop-quick-actions .btn-outline:hover { background: rgba(255,255,255,0.1); }

/* 主容器 */
.shop-container { display: flex; gap: 24px; padding: 24px 20px; max-width: 1400px; margin: 0 auto; }

/* 侧边栏 */
.shop-sidebar { width: 240px; flex-shrink: 0; }
.sidebar-nav { background: #fff; border-radius: 12px; padding: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 8px; color: #475569; text-decoration: none; font-size: 14px; font-weight: 500; transition: all .2s; position: relative; }
.nav-item:hover { background: #f1f5f9; color: #1e293b; }
.nav-item.active { background: #fff7ed; color: #ea580c; }
.nav-badge { margin-left: auto; background: #f1f5f9; color: #64748b; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.nav-item.active .nav-badge { background: #fed7aa; color: #c2410c; }
.sidebar-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.sidebar-card h4 { font-size: 14px; font-weight: 600; color: #1e293b; margin: 0 0 12px; }
.sidebar-stat-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.sidebar-stat-row:last-child { border-bottom: none; }
.sidebar-stat-row .label { color: #64748b; }
.sidebar-stat-row .value { font-weight: 600; }

/* 主内容区 */
.shop-main { flex: 1; min-width: 0; }

/* 统计卡片 */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: #fff; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.stat-card.highlight { border: 1px solid #fed7aa; }
.stat-icon-bg { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.stat-info { flex: 1; min-width: 0; }
.stat-label { font-size: 13px; color: #64748b; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; color: #1e293b; line-height: 1.2; }
.stat-change { font-size: 12px; margin-top: 4px; display: flex; align-items: center; gap: 3px; }
.stat-change.up { color: #22c55e; }
.stat-change.down { color: #ef4444; }
.stat-sub { font-size: 12px; color: #94a3b8; margin-top: 4px; }

/* 图表区域 */
.chart-section { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 24px; }
.chart-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden; }
.chart-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
.chart-header h3 { font-size: 15px; font-weight: 600; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px; }
.chart-header h3 i { color: #f97316; }
.chart-subtitle { font-size: 12px; color: #94a3b8; }
.chart-body { padding: 16px 20px 20px; }

/* 双栏区域 */
.two-column-section { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
.panel-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden; }
.panel-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
.panel-header h3 { font-size: 15px; font-weight: 600; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px; }
.panel-header h3 i { color: #f97316; }
.panel-more { font-size: 13px; color: #3b82f6; text-decoration: none; display: flex; align-items: center; gap: 4px; }
.panel-more:hover { color: #2563eb; }
.panel-body { padding: 12px 20px 16px; }
.empty-tip { text-align: center; padding: 40px 20px; color: #94a3b8; font-size: 14px; }

/* 热销商品 */
.top-product-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f8fafc; }
.top-product-item:last-child { border-bottom: none; }
.rank { width: 24px; height: 24px; border-radius: 6px; background: #f1f5f9; color: #64748b; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.rank.top { background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; }
.product-thumb-sm { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
.product-info { flex: 1; min-width: 0; }
.product-name { font-size: 13px; font-weight: 500; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.product-meta { font-size: 12px; margin-top: 2px; display: flex; gap: 12px; }
.product-meta .sold { color: #64748b; }
.product-meta .revenue { color: #f97316; font-weight: 600; }

/* 最近订单 */
.recent-order-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f8fafc; }
.recent-order-item:last-child { border-bottom: none; }
.order-avatar { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; flex-shrink: 0; background: #f1f5f9; }
.order-avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 14px; }
.order-info { flex: 1; min-width: 0; }
.order-top, .order-bottom { display: flex; justify-content: space-between; align-items: center; }
.order-top { margin-bottom: 3px; }
.buyer-name { font-size: 13px; font-weight: 500; color: #1e293b; }
.order-amount { font-size: 13px; font-weight: 600; color: #f97316; }
.order-no { font-size: 11px; color: #94a3b8; }
.order-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 500; }
.order-time { font-size: 11px; color: #94a3b8; flex-shrink: 0; margin-left: 8px; }

/* 店铺装修表单 */
.decoration-form { padding: 8px 0; }
.form-row { display: flex; gap: 16px; margin-bottom: 16px; }
.form-group { margin-bottom: 16px; flex: 1; }
.form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all .2s; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #f97316; box-shadow: 0 0 0 3px rgba(249,115,22,0.1); }
textarea.form-control { resize: vertical; min-height: 80px; }
.image-upload-box { position: relative; border: 2px dashed #e5e7eb; border-radius: 12px; overflow: hidden; cursor: pointer; transition: all .2s; height: 160px; display: flex; align-items: center; justify-content: center; }
.image-upload-box:hover { border-color: #f97316; }
.image-upload-box.banner-box { height: 120px; }
.upload-placeholder { text-align: center; color: #94a3b8; }
.upload-placeholder i { font-size: 28px; display: block; margin-bottom: 6px; }
.upload-placeholder span { font-size: 13px; }
.preview-img { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; }
.preview-img.hidden { display: none; }
.file-input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
.form-actions { padding-top: 8px; }
.form-actions .btn-lg { padding: 12px 28px; font-size: 15px; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: all .2s; }
.btn-primary { background: #f97316; color: #fff; }
.btn-primary:hover { background: #ea580c; }

/* 提示 */
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
.alert-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

/* 响应式 */
@media (max-width: 1100px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .chart-section { grid-template-columns: 1fr; }
    .two-column-section { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .shop-container { flex-direction: column; }
    .shop-sidebar { width: 100%; }
    .stats-grid { grid-template-columns: 1fr; }
    .shop-header-inner { flex-direction: column; align-items: flex-start; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
