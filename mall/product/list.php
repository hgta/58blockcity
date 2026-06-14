<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
 

// 加载商品类
require_once '../../classes/Product.php';
require_once '../../classes/Category.php';

$product = new Product($pdo);
$category = new Category($pdo);

// 获取查询参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$minPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$maxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

// 每页显示数量
$itemsPerPage = 12;

// 获取商品列表
$products = $product->getProducts([
    'category_id' => $categoryId,
    'search' => $search,
    'sort' => $sort,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'page' => $page,
    'per_page' => $itemsPerPage
]);

// 获取商品总数用于分页
$totalProducts = $product->getProductCount([
    'category_id' => $categoryId,
    'search' => $search,
    'min_price' => $minPrice,
    'max_price' => $maxPrice
]);

// 获取所有分类
$categories = $category->getAllCategories();

// 计算总页数
$totalPages = ceil($totalProducts / $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品浏览 - 58人气值商城</title>
    <style>
        .products-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-description {
            color: #666;
            font-size: 16px;
        }
        
        .products-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }
        
        /* 筛选侧边栏 */
        .filter-sidebar {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            height: fit-content;
        }
        
        .filter-section {
            margin-bottom: 25px;
        }
        
        .filter-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 8px;
        }
        
        .category-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .category-item {
            margin-bottom: 8px;
        }
        
        .category-link {
            display: block;
            padding: 8px 12px;
            color: #555;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .category-link:hover, .category-link.active {
            background: #3498db;
            color: white;
        }
        
        .price-filter {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .price-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-btn {
            width: 100%;
            padding: 10px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .filter-btn:hover {
            background: #2980b9;
        }
        
        /* 商品网格 */
        .products-main {
            flex: 1;
        }
        
        .products-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .products-count {
            color: #666;
            font-size: 14px;
        }
        
        .sort-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 14px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .product-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .product-info {
            padding: 15px;
        }
        
        .product-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            line-height: 1.4;
            height: 44px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-price {
            font-size: 18px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 8px;
        }
        
        .product-shop {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .product-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-detail {
            background: #3498db;
            color: white;
        }
        
        .btn-detail:hover {
            background: #2980b9;
        }
        
        .btn-cart {
            background: #e74c3c;
            color: white;
        }
        
        .btn-cart:hover {
            background: #c0392b;
        }
        
        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .page-link:hover, .page-link.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .page-link.disabled {
            color: #999;
            cursor: not-allowed;
        }
        
        /* 搜索框 */
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #3498db;
            border-radius: 25px;
            font-size: 16px;
            outline: none;
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-text {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .products-layout {
                grid-template-columns: 1fr;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .products-toolbar {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .price-filter {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="products-container">
        <div class="page-header">
            <h1 class="page-title">商品浏览</h1>
            <p class="page-description">发现优质商品，享受购物乐趣</p>
        </div>
        
        <!-- 搜索框 -->
        <div class="search-box">
            <form method="GET" action="">
                <input type="text" name="search" class="search-input" placeholder="搜索商品..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
                <?php if ($categoryId): ?>
                    <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
                <?php endif; ?>
            </form>
        </div>
        
        <div class="products-layout">
            <!-- 筛选侧边栏 -->
            <aside class="filter-sidebar">
                <div class="filter-section">
                    <h3 class="filter-title">商品分类</h3>
                    <ul class="category-list">
                        <li class="category-item">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => 0, 'page' => 1])); ?>" 
                               class="category-link <?php echo $categoryId == 0 ? 'active' : ''; ?>">
                                全部分类
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): ?>
                            <li class="category-item">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => $cat['id'], 'page' => 1])); ?>" 
                                   class="category-link <?php echo $categoryId == $cat['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                    <span style="float: right; color: #999;">(<?php echo $cat['product_count']; ?>)</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="filter-section">
                    <h3 class="filter-title">价格范围</h3>
                    <form method="GET" action="">
                        <div class="price-filter">
                            <input type="number" name="min_price" class="price-input" placeholder="最低价"
                                   value="<?php echo $minPrice ?: ''; ?>" min="0" step="1">
                            <input type="number" name="max_price" class="price-input" placeholder="最高价"
                                   value="<?php echo $maxPrice ?: ''; ?>" min="0" step="1">
                        </div>
                        <?php if ($categoryId): ?>
                            <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
                        <?php endif; ?>
                        <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        <?php if ($sort): ?>
                            <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                        <?php endif; ?>
                        <button type="submit" class="filter-btn">应用筛选</button>
                    </form>
                </div>
                
                <div class="filter-section">
                    <h3 class="filter-title">快速操作</h3>
                    <a href="?" class="btn btn-detail" style="display: block; text-align: center; margin-bottom: 10px;">
                        重置筛选
                    </a>
                </div>
            </aside>
            
            <!-- 商品列表主区域 -->
            <main class="products-main">
                <div class="products-toolbar">
                    <div class="products-count">
                        找到 <?php echo $totalProducts; ?> 件商品
                        <?php if ($search): ?>
                            ，搜索关键词: "<?php echo htmlspecialchars($search); ?>"
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <select class="sort-select" onchange="window.location.href = this.value">
                            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest', 'page' => 1])); ?>" 
                                    <?php echo $sort == 'newest' ? 'selected' : ''; ?>>最新上架</option>
                            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_asc', 'page' => 1])); ?>" 
                                    <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>价格从低到高</option>
                            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_desc', 'page' => 1])); ?>" 
                                    <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>价格从高到低</option>
                            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'popular', 'page' => 1])); ?>" 
                                    <?php echo $sort == 'popular' ? 'selected' : ''; ?>>人气最高</option>
                        </select>
                    </div>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="empty-text">没有找到符合条件的商品</div>
                        <a href="?" class="btn btn-detail">查看所有商品</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <img src="<?php echo '../'.htmlspecialchars($product['thumb_image'] ?: $product['image_url'] ?: '../assets/images/default-product.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="product-image">
                                
                                <div class="product-info">
                                    <h3 class="product-name">
                                        <a href="detail.php?id=<?php echo $product['id']; ?>" 
                                           style="color: inherit; text-decoration: none;">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="product-price">
                                        <?php if ($product['price_bct'] > 0): ?>
                                            <span style="color: #e74c3c; font-size: 16px; font-weight: bold;"><?php echo number_format($product['price_bct'], 0); ?> 人气</span>
                                            <?php if ($product['price_cny'] > 0): ?>
                                                <span style="color: #999; font-size: 12px; margin-left: 5px;">≈ ¥<?php echo number_format($product['price_cny'], 2); ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($product['price_cny'] > 0): ?>
                                            <span style="color: #e74c3c; font-size: 16px; font-weight: bold;">¥<?php echo number_format($product['price_cny'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-shop">
                                        <i class="fas fa-store"></i>
                                        <?php echo htmlspecialchars($product['shop_name'] ?: '平台自营'); ?>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <a href="detail.php?id=<?php echo $product['id']; ?>" class="btn btn-detail">
                                            <i class="fas fa-eye"></i> 查看详情
                                        </a>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <button class="btn btn-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-cart-plus"></i> 加购物车
                                            </button>
                                        <?php else: ?>
                                            <a href="../auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-cart">
                                                <i class="fas fa-cart-plus"></i> 加购物车
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- 分页 -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <!-- 上一页 -->
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                                    <i class="fas fa-chevron-left"></i> 上一页
                                </a>
                            <?php else: ?>
                                <span class="page-link disabled">
                                    <i class="fas fa-chevron-left"></i> 上一页
                                </span>
                            <?php endif; ?>
                            
                            <!-- 页码 -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <span class="page-link">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <!-- 下一页 -->
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                                    下一页 <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="page-link disabled">
                                    下一页 <i class="fas fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script>
        function addToCart(productId) {
            fetch('../cart/add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('已添加到购物车', 'success');
                    if (data.cart_count !== undefined) {
                        const cartBadge = document.querySelector('.cart-badge');
                        if (cartBadge) cartBadge.textContent = data.cart_count;
                    }
                } else {
                    showToast(data.message || '添加失败', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('网络错误，请重试', 'error');
            });
        }
        function showToast(msg, type) {
            var t = document.createElement('div');
            t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:8px;color:#fff;font-size:14px;animation:fadeIn .3s;' + (type==='success'?'background:#27ae60;':'background:#e74c3c;');
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(function(){ t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(function(){ t.remove(); },300); },2000);
        }
        }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html> 