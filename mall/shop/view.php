<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Product.php';
require_once '../includes/functions.php';

// 兜底：如果公共函数库未部署，在此文件内也定义一次 normalizeImageUrl
if (!function_exists('normalizeImageUrl')) {
    function normalizeImageUrl($imageUrl) {
        if (empty($imageUrl)) {
            return '../assets/images/default-product.jpg';
        }
        $imageUrl = trim($imageUrl);
        if (preg_match('#^(https?:)?//#i', $imageUrl) || substr($imageUrl, 0, 1) === '/') {
            return $imageUrl;
        }
        return '../' . $imageUrl;
    }
}

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

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$currentSort = $_GET['sort'] ?? 'newest';
$perPage = 12;

// 获取店铺统计
$productCount = $shop->getProductCount($shopId);
$orderStats = $shop->getOrderStats($shopId);

// 仅活跃店铺才查询商品
if (!isset($errorMessage)) {
    $totalProducts = $productCount;
    $totalPages = ceil($totalProducts / $perPage);
    $currentPageProducts = $product->getProductsByShopPaged($shopId, $page, $perPage, $currentSort);
} else {
    $totalProducts = 0;
    $totalPages = 0;
    $currentPageProducts = [];
}

// 增加店铺浏览数（可选）
// $shop->incrementViewCount($shopId);

require_once '../includes/header.php';
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
        <div class="shop-header-card">
            <div class="shop-header-body">
                <div class="shop-logo-wrap">
                    <?php if (!empty($shopInfo['shop_logo'])): ?>
                        <img src="<?= htmlspecialchars(normalizeImageUrl($shopInfo['shop_logo'])) ?>" 
                             alt="<?= htmlspecialchars($shopInfo['shop_name']) ?>" 
                             class="shop-logo-img">
                    <?php else: ?>
                        <div class="shop-logo-placeholder">
                            <i class="fas fa-store"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="shop-info-wrap">
                    <h1 class="shop-name"><?= htmlspecialchars($shopInfo['shop_name']) ?></h1>
                    <div class="shop-rating">
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
                        <span class="rating-text"><?= number_format($rating, 1) ?> (<?= $orderStats ? number_format($orderStats['total_orders']) : 0 ?> 订单)</span>
                    </div>
                    <?php if (!empty($shopInfo['shop_description'])): ?>
                        <p class="shop-description"><?= nl2br(htmlspecialchars($shopInfo['shop_description'])) ?></p>
                    <?php endif; ?>
                    <div class="shop-features">
                        <?php if (!empty($shopInfo['contact_info'])): ?>
                            <span class="feature-item"><i class="fas fa-phone text-primary"></i> <?= htmlspecialchars($shopInfo['contact_info']) ?></span>
                        <?php endif; ?>
                        <span class="feature-item"><i class="fas fa-clock text-primary"></i> 营业时间：全天</span>
                        <span class="feature-item"><i class="fas fa-map-marker-alt text-primary"></i> 在线店铺</span>
                    </div>
                </div>

                <div class="shop-side-wrap">
                    <div class="shop-stats">
                        <div class="stat-box">
                            <div class="stat-number text-primary"><?= number_format($productCount) ?></div>
                            <div class="stat-label">商品数量</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-success"><?= number_format($shopInfo['total_sales'] ?? 0) ?></div>
                            <div class="stat-label">总销量</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number text-warning"><?= $orderStats ? number_format($orderStats['total_orders']) : 0 ?></div>
                            <div class="stat-label">总订单</div>
                        </div>
                    </div>
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

        <!-- 店铺横幅（如果有） -->
        <?php if (!empty($shopInfo['shop_banner'])): ?>
            <div class="shop-banner">
                <img src="<?= htmlspecialchars(normalizeImageUrl($shopInfo['shop_banner'])) ?>" 
                     alt="<?= htmlspecialchars($shopInfo['shop_name']) ?> 横幅" 
                     class="img-fluid rounded">
            </div>
        <?php endif; ?>

        <!-- 商品筛选和排序 -->
        <div class="products-toolbar">
            <h4 class="toolbar-title">店铺商品 <span class="text-muted">(<?= number_format($totalProducts) ?>)</span></h4>
            <div class="shop-sort-bar">
                <a href="?id=<?= $shopId ?>&page=1&sort=newest" class="btn btn-sm <?= $currentSort=='newest'?'btn-primary':'btn-outline-secondary' ?>">最新</a>
                <a href="?id=<?= $shopId ?>&page=1&sort=price_asc" class="btn btn-sm <?= $currentSort=='price_asc'?'btn-primary':'btn-outline-secondary' ?>">价格↑</a>
                <a href="?id=<?= $shopId ?>&page=1&sort=price_desc" class="btn btn-sm <?= $currentSort=='price_desc'?'btn-primary':'btn-outline-secondary' ?>">价格↓</a>
                <a href="?id=<?= $shopId ?>&page=1&sort=sales" class="btn btn-sm <?= $currentSort=='sales'?'btn-primary':'btn-outline-secondary' ?>">销量</a>
            </div>
        </div>

        <!-- 商品列表 -->
        <?php if ($currentPageProducts): ?>
            <div class="row product-grid-row">
                <?php foreach ($currentPageProducts as $productItem): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="product-card <?= $productItem['status'] !== 'active' ? 'product-disabled' : '' ?>">
                            <a href="../product/detail.php?id=<?= $productItem['id'] ?>" class="product-link">
                                <div class="product-image">
                                    <?php $itemImage = $productItem['thumb_image'] ?: $productItem['main_image'] ?: '../assets/images/default-product.jpg'; ?>
                                    <img src="<?= htmlspecialchars(normalizeImageUrl($itemImage)) ?>" 
                                         alt="<?= htmlspecialchars($productItem['name']) ?>"
                                         class="img-fluid">
                                    <?php if (!empty($productItem['video_url'])): ?>
                                        <div class="product-video-badge">
                                            <i class="fas fa-video"></i> 视频
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($productItem['status'] !== 'active'): ?>
                                        <div class="product-status-overlay">
                                            <span><?= $productItem['status'] == 'sold_out' ? '已售罄' : '已下架' ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h5 class="product-name"><?= htmlspecialchars($productItem['name']) ?></h5>
                                    <div class="product-price">
                                        <span class="bct-price"><?= number_format($productItem['price_bct'], 0) ?> BCT</span>
                                        <?php if ($productItem['price_cny']): ?>
                                            <span class="cny-price">≈ ¥<?= number_format($productItem['price_cny'], 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-meta">
                                        <span>销量 <?= number_format($productItem['sold_count']) ?></span>
                                        <span>库存 <?= number_format($productItem['stock']) ?></span>
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
                                <a class="page-link" href="?id=<?= $shopId ?>&page=<?= $page - 1 ?>&sort=<?= htmlspecialchars($currentSort) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?id=<?= $shopId ?>&page=<?= $i ?>&sort=<?= htmlspecialchars($currentSort) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?= $shopId ?>&page=<?= $page + 1 ?>&sort=<?= htmlspecialchars($currentSort) ?>">
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

        <!-- 店铺公告 -->
        <div class="shop-notice-card">
            <h5><i class="fas fa-bullhorn text-primary"></i> 店铺公告</h5>
            <p class="text-muted mb-0">暂无公告信息</p>
        </div>
    <?php endif; ?>
</div>

<style>
.shop-header-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
}
.shop-header-body {
    display: flex;
    gap: 24px;
    padding: 20px;
    align-items: flex-start;
}
.shop-logo-wrap {
    flex-shrink: 0;
}
.shop-logo-img,
.shop-logo-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}
.shop-logo-placeholder {
    font-size: 42px;
    color: #adb5bd;
}
.shop-info-wrap {
    flex: 1;
    min-width: 0;
}
.shop-name {
    font-size: 1.6rem;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 8px;
}
.shop-rating {
    font-size: 1rem;
    margin-bottom: 10px;
}
.shop-rating .rating-text {
    font-size: 0.85rem;
    color: #6c757d;
    margin-left: 6px;
}
.shop-description {
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 10px;
}
.shop-features {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.85rem;
    color: #6c757d;
}
.feature-item {
    display: flex;
    align-items: center;
    gap: 4px;
}
.shop-side-wrap {
    flex-shrink: 0;
    width: 200px;
}
.shop-stats {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
}
.stat-box {
    flex: 1;
    text-align: center;
}
.stat-box .stat-number {
    font-size: 1.4rem;
    font-weight: bold;
    line-height: 1;
}
.stat-box .stat-label {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 4px;
}
.shop-banner {
    margin-bottom: 20px;
}
.shop-banner img {
    width: 100%;
    max-height: 220px;
    object-fit: cover;
    border-radius: 12px;
}
.products-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
    padding: 12px 0;
    border-bottom: 2px solid #f0f0f0;
}
.toolbar-title {
    font-size: 1.1rem;
    font-weight: bold;
    margin: 0;
}
.toolbar-title .text-muted {
    font-weight: normal;
    font-size: 0.85rem;
}
.shop-sort-bar {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.product-grid-row {
    margin-bottom: 10px;
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
.product-card.product-disabled {
    opacity: 0.75;
}
.product-link {
    text-decoration: none;
    color: inherit;
    display: block;
}
.product-image {
    height: 180px;
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
.product-video-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    background: rgba(0,0,0,0.6);
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    z-index: 2;
}
.product-status-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3;
}
.product-status-overlay span {
    background: #6c757d;
    color: #fff;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}
.product-info {
    padding: 12px;
}
.product-name {
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 8px;
    color: #2c3e50;
    line-height: 1.4;
    height: 2.7em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.product-price {
    margin-bottom: 6px;
}
.bct-price {
    font-size: 1.05rem;
    font-weight: bold;
    color: #e74c3c;
}
.cny-price {
    font-size: 0.85rem;
    color: #7f8c8d;
    margin-left: 6px;
}
.product-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #95a5a6;
}
.shop-notice-card {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-top: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.shop-notice-card h5 {
    font-size: 1rem;
    margin-bottom: 10px;
}
.pagination .page-link {
    color: #007bff;
}
.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}

@media (max-width: 768px) {
    .shop-header-body {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .shop-info-wrap {
        width: 100%;
    }
    .shop-features {
        justify-content: center;
    }
    .shop-side-wrap {
        width: 100%;
    }
    .shop-stats {
        justify-content: center;
    }
    .products-toolbar {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script>
function shareShop() {
    const shopUrl = window.location.href;
    const shopName = <?= json_encode($shopInfo['shop_name'] ?? '') ?>;
    
    if (navigator.share) {
        navigator.share({
            title: shopName,
            text: '来看看这个不错的店铺！',
            url: shopUrl,
        })
        .then(() => console.log('分享成功'))
        .catch((error) => console.log('分享失败', error));
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(shopUrl).then(function() {
            alert('店铺链接已复制到剪贴板！');
        }, function() {
            fallbackCopy(shopUrl);
        });
    } else {
        fallbackCopy(shopUrl);
    }
}

function fallbackCopy(text) {
    const tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    alert('店铺链接已复制到剪贴板！');
}
</script>

<?php require_once '../includes/footer.php'; ?>
