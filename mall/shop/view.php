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
            return '/assets/images/default-product.jpg';
        }
        $imageUrl = trim($imageUrl);
        if (preg_match('#^(https?:)?//#i', $imageUrl) || substr($imageUrl, 0, 1) === '/') {
            return $imageUrl;
        }
        return '/' . ltrim($imageUrl, '/');
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
                             class="shop-logo-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="shop-logo-placeholder" style="display:none;"><i class="fas fa-store"></i></div>
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
                                    <?php $itemImage = $productItem['thumb_image'] ?: $productItem['main_image'] ?: '/assets/images/default-product.jpg'; ?>
                                    <img src="<?= htmlspecialchars(normalizeImageUrl($itemImage)) ?>" 
                                         alt="<?= htmlspecialchars($productItem['name']) ?>"
                                         class="img-fluid" onerror="this.src='/assets/images/default-product.jpg'">
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
                <div class="pagination-wrap">
                    <span class="pagination-info">共 <?= $totalProducts ?> 件，<?= $page ?>/<?= $totalPages ?> 页</span>
                    <div class="pagination-links">
                        <?php $baseUrl = "view.php?id=$shopId&sort=" . urlencode($currentSort); ?>
                        <?php if ($page > 1): ?>
                            <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" class="page-btn">&laquo;</a>
                        <?php endif; ?>
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        if ($start > 1): ?>
                            <a href="<?= $baseUrl ?>&page=1" class="page-btn">1</a>
                            <?php if ($start > 2): ?><span class="page-dots">...</span><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?><span class="page-dots">...</span><?php endif; ?>
                            <a href="<?= $baseUrl ?>&page=<?= $totalPages ?>" class="page-btn"><?= $totalPages ?></a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" class="page-btn">&raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
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
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    margin-bottom: 20px;
    overflow: hidden;
}
.shop-header-body {
    display: flex;
    gap: 24px;
    padding: 24px;
    align-items: flex-start;
}
.shop-logo-wrap { flex-shrink: 0; }
.shop-logo-img,
.shop-logo-placeholder {
    width: 100px; height: 100px;
    border-radius: 16px;
    object-fit: cover;
    background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
}
.shop-logo-placeholder { font-size: 36px; color: #94a3b8; }
.shop-info-wrap { flex: 1; min-width: 0; }
.shop-name {
    font-size: 22px; font-weight: 700; color: #1e293b; margin-bottom: 8px;
}
.shop-rating { font-size: 14px; margin-bottom: 8px; color: #64748b; }
.shop-rating .rating-text { font-size: 13px; color: #94a3b8; margin-left: 6px; }
.shop-description {
    color: #64748b; font-size: 14px; line-height: 1.6; margin-bottom: 10px;
}
.shop-features { display: flex; flex-wrap: wrap; gap: 12px; font-size: 13px; color: #64748b; }
.feature-item { display: flex; align-items: center; gap: 4px; }
.shop-side-wrap { flex-shrink: 0; width: 180px; }
.shop-stats { display: flex; gap: 8px; margin-bottom: 12px; }
.stat-box { flex: 1; text-align: center; background: #f8fafc; border-radius: 10px; padding: 10px 6px; }
.stat-box .stat-number { font-size: 20px; font-weight: 700; line-height: 1; }
.stat-box .stat-label { font-size: 11px; color: #94a3b8; margin-top: 4px; }
.shop-banner { margin-bottom: 20px; }
.shop-banner img { width: 100%; max-height: 200px; object-fit: cover; border-radius: 12px; }
.products-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 12px;
    margin-bottom: 16px; padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}
.toolbar-title { font-size: 17px; font-weight: 700; margin: 0; color: #1e293b; }
.toolbar-title .text-muted { font-weight: 400; font-size: 14px; color: #94a3b8; }
.shop-sort-bar { display: flex; gap: 6px; flex-wrap: wrap; }
.shop-sort-bar .btn-sm { font-size: 12px; padding: 6px 14px; border-radius: 20px; }
.product-grid-row {
    margin-bottom: 10px;
}
.product-card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.25s ease;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}
.product-card.product-disabled {
    opacity: 0.6;
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
    background: #f8f9fa;
}
.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}
.product-card:hover .product-image img {
    transform: scale(1.08);
}
.product-video-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(255,107,0,0.85);
    color: #fff;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    z-index: 2;
}
.product-status-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3;
}
.product-status-overlay span {
    background: #64748b;
    color: #fff;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.product-info {
    padding: 14px;
}
.product-name {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #1e293b;
    line-height: 1.4;
    height: 2.7em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.product-price {
    margin-bottom: 8px;
}
.bct-price {
    font-size: 16px;
    font-weight: 700;
    color: #e74c3c;
}
.cny-price {
    font-size: 12px;
    color: #94a3b8;
    margin-left: 6px;
}
.product-meta {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #94a3b8;
}
/* 分页 */
.pagination-wrap {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 20px; padding: 14px 0; border-top: 1px solid #e9ecef;
    flex-wrap: wrap; gap: 10px;
}
.pagination-info { font-size: 13px; color: #64748b; }
.pagination-links { display: flex; gap: 4px; align-items: center; }
.page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 34px; padding: 0 8px;
    border-radius: 8px; font-size: 13px; font-weight: 500;
    text-decoration: none; color: #475569; background: #f1f5f9;
    transition: all 0.15s;
}
.page-btn:hover { background: #e2e8f0; color: #1e293b; text-decoration: none; }
.page-btn.active { background: #ff6b00; color: #fff; }
.page-dots { padding: 0 4px; color: #94a3b8; font-size: 13px; }

.shop-notice-card {
    background: white;
    border-radius: 12px;
    padding: 18px;
    margin-top: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.shop-notice-card h5 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #1e293b;
}

@media (max-width: 768px) {
    .shop-header-body { flex-direction: column; align-items: center; text-align: center; }
    .shop-info-wrap { width: 100%; }
    .shop-features { justify-content: center; }
    .shop-side-wrap { width: 100%; }
    .shop-stats { justify-content: center; }
    .products-toolbar { flex-direction: column; align-items: flex-start; }
    .product-grid-row .col-lg-3 { width: 50%; }
}
@media (max-width: 480px) {
    .product-grid-row .col-lg-3 { width: 100%; }
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
