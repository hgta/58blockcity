<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../includes/header.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Product.php';

// 获取店铺ID
$shopId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($shopId <= 0) {
    header('Location: list.php');
    exit;
}

$shop = new Shop($pdo);
$product = new Product($pdo);

// 获取店铺信息
$shopInfo = $shop->getShopById($shopId);
if (!$shopInfo) {
    header('Location: list.php');
    exit;
}

// 检查店铺状态
if ($shopInfo['status'] !== 'active') {
    $errorMessage = '该店铺暂时无法访问';
}

// 获取店铺商品
$products = $product->getProductsByShop($shopId, 20);

// 获取店铺统计
$productCount = $shop->getProductCount($shopId);
$orderStats = $shop->getOrderStats($shopId);

// 增加店铺浏览数（可选）
// $shop->incrementViewCount($shopId);

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12;
$totalProducts = count($products);
$totalPages = ceil($totalProducts / $perPage);

// 获取当前页的商品
$startIndex = ($page - 1) * $perPage;
$currentPageProducts = array_slice($products, $startIndex, $perPage);
?>

<div class="container mt-4">
    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-warning text-center">
            <h4><i class="fas fa-exclamation-triangle"></i> 店铺无法访问</h4>
            <p class="mb-0"><?= htmlspecialchars($errorMessage) ?></p>
            <a href="list.php" class="btn btn-primary mt-3">返回店铺列表</a>
        </div>
    <?php else: ?>
        <!-- 店铺头部信息 -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="shop-logo mb-3">
                            <?php if (!empty($shopInfo['shop_logo'])): ?>
                                <img src="<?= htmlspecialchars($shopInfo['shop_logo']) ?>" 
                                     alt="<?= htmlspecialchars($shopInfo['shop_name']) ?>" 
                                     class="img-fluid rounded-circle" style="max-width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                                     style="width: 150px; height: 150px;">
                                    <i class="fas fa-store fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h1 class="shop-name mb-2"><?= htmlspecialchars($shopInfo['shop_name']) ?></h1>
                        
                        <!-- 店铺评分 -->
                        <div class="shop-rating mb-3">
                            <div class="stars mb-1">
                                <?php
                                $rating = floatval($shopInfo['rating']);
                                $fullStars = floor($rating);
                                $halfStar = ($rating - $fullStars) >= 0.5;
                                $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
                                
                                for ($i = 0; $i < $fullStars; $i++): ?>
                                    <i class="fas fa-star text-warning"></i>
                                <?php endfor; ?>
                                
                                <?php if ($halfStar): ?>
                                    <i class="fas fa-star-half-alt text-warning"></i>
                                <?php endif; ?>
                                
                                <?php for ($i = 0; $i < $emptyStars; $i++): ?>
                                    <i class="far fa-star text-warning"></i>
                                <?php endfor; ?>
                                
                                <span class="ml-2 text-muted"><?= number_format($rating, 1) ?> (<?= $orderStats ? $orderStats['total_orders'] : 0 ?> 评价)</span>
                            </div>
                        </div>
                        
                        <!-- 店铺描述 -->
                        <div class="shop-description mb-3">
                            <p class="text-muted"><?= nl2br(htmlspecialchars($shopInfo['shop_description'])) ?></p>
                        </div>
                        
                        <!-- 店铺特色 -->
                        <div class="shop-features">
                            <?php if (!empty($shopInfo['contact_info'])): ?>
                                <div class="feature-item mb-1">
                                    <i class="fas fa-phone text-primary mr-2"></i>
                                    <span class="text-muted">联系方式: <?= htmlspecialchars($shopInfo['contact_info']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="feature-item mb-1">
                                <i class="fas fa-clock text-primary mr-2"></i>
                                <span class="text-muted">营业时间: 全天</span>
                            </div>
                            
                            <div class="feature-item">
                                <i class="fas fa-map-marker-alt text-primary mr-2"></i>
                                <span class="text-muted">在线店铺</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <!-- 店铺统计 -->
                        <div class="shop-stats">
                            <div class="stat-item text-center mb-3">
                                <div class="stat-number text-primary"><?= $productCount ?></div>
                                <div class="stat-label text-muted">商品数量</div>
                            </div>
                            <div class="stat-item text-center mb-3">
                                <div class="stat-number text-success"><?= $shopInfo['total_sales'] ?></div>
                                <div class="stat-label text-muted">总销量</div>
                            </div>
                            <div class="stat-item text-center">
                                <div class="stat-number text-warning"><?= $orderStats ? number_format($orderStats['total_orders']) : 0 ?></div>
                                <div class="stat-label text-muted">总订单</div>
                            </div>
                        </div>
                        
                        <!-- 操作按钮 -->
                        <div class="shop-actions mt-4">
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $shopInfo['user_id']): ?>
                                <a href="manage.php?id=<?= $shopInfo['id'] ?>" class="btn btn-primary btn-block mb-2">
                                    <i class="fas fa-cog"></i> 管理店铺
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline-primary btn-block" onclick="shareShop()">
                                <i class="fas fa-share-alt"></i> 分享店铺
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 店铺横幅（如果有） -->
        <?php if (!empty($shopInfo['shop_banner'])): ?>
            <div class="shop-banner mb-4">
                <img src="<?= htmlspecialchars($shopInfo['shop_banner']) ?>" 
                     alt="<?= htmlspecialchars($shopInfo['shop_name']) ?> 横幅" 
                     class="img-fluid rounded" style="width: 100%; max-height: 300px; object-fit: cover;">
            </div>
        <?php endif; ?>

        <!-- 商品筛选和排序 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h4 class="mb-0">店铺商品 (<?= $totalProducts ?>)</h4>
            </div>
            <div class="col-md-6 text-right">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown">
                        排序方式
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="?id=<?= $shopId ?>&sort=newest">最新上架</a>
                        <a class="dropdown-item" href="?id=<?= $shopId ?>&sort=price_asc">价格从低到高</a>
                        <a class="dropdown-item" href="?id=<?= $shopId ?>&sort=price_desc">价格从高到低</a>
                        <a class="dropdown-item" href="?id=<?= $shopId ?>&sort=sales">销量最高</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 商品列表 -->
        <?php if ($currentPageProducts): ?>
            <div class="row">
                <?php foreach ($currentPageProducts as $productItem): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="product-card">
                            <a href="../product/detail.php?id=<?= $productItem['id'] ?>" class="product-link">
                                <div class="product-image">
                                    <img src="../<?= htmlspecialchars($productItem['main_image']) ?>" 
                                         alt="<?= htmlspecialchars($productItem['name']) ?>"
                                         class="img-fluid">
                                    <?php if ($productItem['status'] !== 'active'): ?>
                                        <div class="product-status-overlay">
                                            <span class="badge badge-secondary">
                                                <?= $productItem['status'] == 'sold_out' ? '已售罄' : '已下架' ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h5 class="product-name"><?= htmlspecialchars($productItem['name']) ?></h5>
                                    <div class="product-price">
                                        <span class="bct-price"><?= number_format($productItem['price_bct'], 2) ?> BCT</span>
                                        <?php if ($productItem['price_cny']): ?>
                                            <span class="cny-price">≈ ¥<?= number_format($productItem['price_cny'], 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-meta">
                                        <span class="sales">销量: <?= $productItem['sold_count'] ?></span>
                                        <span class="stock">库存: <?= $productItem['stock'] ?></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 分页 -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="商品分页">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?= $shopId ?>&page=<?= $page - 1 ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?id=<?= $shopId ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?= $shopId ?>&page=<?= $page + 1 ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">暂无商品</h4>
                <p class="text-muted">该店铺还没有上架商品</p>
            </div>
        <?php endif; ?>

        <!-- 店铺公告（可以扩展） -->
        <div class="card mt-5">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bullhorn text-primary mr-2"></i>店铺公告
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">暂无公告信息</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.shop-name {
    font-size: 2rem;
    font-weight: bold;
    color: #2c3e50;
}
.shop-stats .stat-number {
    font-size: 1.8rem;
    font-weight: bold;
    line-height: 1;
}
.shop-stats .stat-label {
    font-size: 0.9rem;
    margin-top: 0.25rem;
}
.product-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
    background: white;
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    border-color: #007bff;
}
.product-link {
    text-decoration: none;
    color: inherit;
    display: block;
}
.product-image {
    height: 200px;
    overflow: hidden;
    position: relative;
}
.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.product-card:hover .product-image img {
    transform: scale(1.05);
}
.product-status-overlay {
    position: absolute;
    top: 10px;
    right: 10px;
}
.product-info {
    padding: 15px;
}
.product-name {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 10px;
    color: #2c3e50;
    line-height: 1.4;
    height: 2.8em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.product-price {
    margin-bottom: 8px;
}
.bct-price {
    font-size: 1.1rem;
    font-weight: bold;
    color: #e74c3c;
}
.cny-price {
    font-size: 0.9rem;
    color: #7f8c8d;
    margin-left: 8px;
}
.product-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #95a5a6;
}
.stars {
    font-size: 1.1rem;
}
.feature-item {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
}
</style>

<script>
function shareShop() {
    const shopUrl = window.location.href;
    const shopName = '<?= addslashes($shopInfo['shop_name']) ?>';
    
    if (navigator.share) {
        navigator.share({
            title: shopName,
            text: '来看看这个不错的店铺！',
            url: shopUrl,
        })
        .then(() => console.log('分享成功'))
        .catch((error) => console.log('分享失败', error));
    } else {
        // 复制链接到剪贴板
        navigator.clipboard.writeText(shopUrl).then(function() {
            alert('店铺链接已复制到剪贴板！');
        }, function() {
            // 备用方案
            const tempInput = document.createElement('input');
            tempInput.value = shopUrl;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            alert('店铺链接已复制到剪贴板！');
        });
    }
}

// 页面加载完成后的一些交互效果
document.addEventListener('DOMContentLoaded', function() {
    // 可以添加更多的交互效果
});
</script>

<?php require_once '../includes/footer.php'; ?>