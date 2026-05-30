<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
 

// 加载店铺类
require_once '../../classes/Shop.php';
require_once '../../classes/Category.php';

$shop = new Shop($pdo);
$category = new Category($pdo);

// 获取查询参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;

// 每页显示数量
$itemsPerPage = 12;

// 获取店铺列表
$shops = $shop->getShops([
    'search' => $search,
    'sort' => $sort,
    'category_id' => $categoryId,
    'page' => $page,
    'per_page' => $itemsPerPage
]);

// 获取店铺总数用于分页
$totalShops = $shop->getShopCount([
    'search' => $search,
    'category_id' => $categoryId
]);

// 获取所有分类
$categories = $category->getAllCategories();

// 计算总页数
$totalPages = ceil($totalShops / $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>店铺列表 - 58人气值商城</title>
    <style>
        .shops-container {
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
        
        .shops-layout {
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
        
        /* 店铺网格 */
        .shops-main {
            flex: 1;
        }
        
        .shops-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .shops-count {
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
        
        .shops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .shop-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .shop-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .shop-cover {
            width: 100%;
            height: 120px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .shop-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid white;
            background: white;
            margin: -40px auto 15px;
            position: relative;
            overflow: hidden;
        }
        
        .shop-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .shop-info {
            padding: 0 20px 20px;
            text-align: center;
        }
        
        .shop-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        
        .shop-description {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
            margin-bottom: 10px;
            height: 40px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .shop-stats {
            display: flex;
            justify-content: space-around;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        
        .shop-category {
            background: #f8f9fa;
            color: #666;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .shop-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 16px;
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
        
        .btn-visit {
            background: #3498db;
            color: white;
        }
        
        .btn-visit:hover {
            background: #2980b9;
        }
        
        .btn-products {
            background: #e74c3c;
            color: white;
        }
        
        .btn-products:hover {
            background: #c0392b;
        }
        
        /* 特色店铺 */
        .featured-shops {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
            border-left: 4px solid #3498db;
            padding-left: 10px;
        }
        
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .featured-shop-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
        }
        
        .featured-avatar {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            margin: 20px;
            object-fit: cover;
        }
        
        .featured-info {
            flex: 1;
            padding: 20px 20px 20px 0;
        }
        
        .featured-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        
        .featured-description {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
            margin-bottom: 10px;
        }
        
        .featured-stats {
            display: flex;
            gap: 15px;
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
            .shops-layout {
                grid-template-columns: 1fr;
            }
            
            .shops-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .shops-toolbar {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .featured-grid {
                grid-template-columns: 1fr;
            }
            
            .featured-shop-card {
                flex-direction: column;
                text-align: center;
            }
            
            .featured-avatar {
                margin: 20px auto;
            }
            
            .featured-info {
                padding: 0 20px 20px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="shops-container">
        <div class="page-header">
            <h1 class="page-title">店铺列表</h1>
            <p class="page-description">发现优质店铺，探索更多商品</p>
        </div>
        
        <!-- 搜索框 -->
        <div class="search-box">
            <form method="GET" action="">
                <input type="text" name="search" class="search-input" placeholder="搜索店铺名称..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
                <?php if ($categoryId): ?>
                    <input type="hidden" name="category" value="<?php echo $categoryId; ?>">
                <?php endif; ?>
            </form>
        </div>
        
        <!-- 特色店铺 -->
        <?php 
        $featuredShops = $shop->getFeaturedShops(3);
        if (!empty($featuredShops)): 
        ?>
            <div class="featured-shops">
                <h2 class="section-title">特色店铺</h2>
                <div class="featured-grid">
                    <?php foreach ($featuredShops as $featuredShop): ?>
                        <div class="featured-shop-card">
                            <img src="<?php echo htmlspecialchars($featuredShop['avatar_url'] ?: '../assets/images/default-shop.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($featuredShop['shop_name']); ?>" 
                                 class="featured-avatar">
                            <div class="featured-info">
                                <h3 class="featured-name"><?php echo htmlspecialchars($featuredShop['shop_name']); ?></h3>
                                <p class="featured-description">
                                    <?php echo htmlspecialchars($featuredShop['description'] ?: '这家店铺还没有描述...'); ?>
                                </p>
                                <div class="featured-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $featuredShop['product_count'] ?? 0; ?></div>
                                        <div class="stat-label">商品</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $featuredShop['sales_count'] ?? 0; ?></div>
                                        <div class="stat-label">销量</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">4.8</div>
                                        <div class="stat-label">评分</div>
                                    </div>
                                </div>
                                <a href="detail.php?id=<?php echo $featuredShop['id']; ?>" class="btn btn-visit" style="margin-top: 10px;">
                                    进入店铺
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="shops-layout">
            <!-- 筛选侧边栏 -->
            <aside class="filter-sidebar">
                <div class="filter-section">
                    <h3 class="filter-title">店铺分类</h3>
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
                                    <span style="float: right; color: #999;">(<?php echo $cat['shop_count'] ?? 0; ?>)</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="filter-section">
                    <h3 class="filter-title">快速操作</h3>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        $hasShop = getUserShopStatus($pdo, $_SESSION['user_id']);
                        if ($hasShop): ?>
                            <a href="manage.php" class="btn btn-visit" style="display: block; text-align: center; margin-bottom: 10px;">
                                管理我的店铺
                            </a>
                        <?php else: ?>
                            <a href="create.php" class="btn btn-visit" style="display: block; text-align: center; margin-bottom: 10px;">
                                创建我的店铺
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="../auth/login.php" class="btn btn-visit" style="display: block; text-align: center; margin-bottom: 10px;">
                            登录后创建店铺
                        </a>
                    <?php endif; ?>
                    <a href="?" class="btn btn-products" style="display: block; text-align: center;">
                        重置筛选
                    </a>
                </div>
            </aside>
            
            <!-- 店铺列表主区域 -->
            <main class="shops-main">
                <div class="shops-toolbar">
                    <div class="shops-count">
                        找到 <?php echo $totalShops; ?> 家店铺
                        <?php if ($search): ?>
                            ，搜索关键词: "<?php echo htmlspecialchars($search); ?>"
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <select class="sort-select" onchange="window.location.href = this.value">
                            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest', 'page' => 1])); ?>" 
                                    <?php echo $sort == 'newest' ? 'selected' : ''; ?>>最新入驻</option>
                            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'popular', 'page' => 1])); ?>" 
                                    <?php echo $sort == 'popular' ? 'selected' : ''; ?>>人气最高</option>
                            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'product_count', 'page' => 1])); ?>" 
                                    <?php echo $sort == 'product_count' ? 'selected' : ''; ?>>商品最多</option>
                            <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'sales', 'page' => 1])); ?>" 
                                    <?php echo $sort == 'sales' ? 'selected' : ''; ?>>销量最高</option>
                        </select>
                    </div>
                </div>
                
                <?php if (empty($shops)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="empty-text">没有找到符合条件的店铺</div>
                        <a href="?" class="btn btn-visit">查看所有店铺</a>
                    </div>
                <?php else: ?>
                    <div class="shops-grid">
                        <?php foreach ($shops as $shopItem): ?>
                            <div class="shop-card">
                                <div class="shop-cover"></div>
                                <div class="shop-avatar">
                                    <img src="<?php echo htmlspecialchars($shopItem['avatar_url'] ?: '../assets/images/default-shop.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($shopItem['shop_name']); ?>">
                                </div>
                                
                                <div class="shop-info">
                                    <h3 class="shop-name"><?php echo htmlspecialchars($shopItem['shop_name']); ?></h3>
                                    
                                    <div class="shop-category">
                                        <?php echo htmlspecialchars($shopItem['category_name'] ?: '未分类'); ?>
                                    </div>
                                    
                                    <p class="shop-description">
                                        <?php echo htmlspecialchars($shopItem['shop_description'] ?: '这家店铺还没有描述...'); ?>
                                    </p>
                                    
                                    <div class="shop-stats">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $shopItem['product_count'] ?? 0; ?></div>
                                            <div class="stat-label">商品</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $shopItem['sales_count'] ?? 0; ?></div>
                                            <div class="stat-label">销量</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value">4.8</div>
                                            <div class="stat-label">评分</div>
                                        </div>
                                    </div>
                                    
                                    <div class="shop-actions">
                                        <a href="detail.php?id=<?php echo $shopItem['id']; ?>" class="btn btn-visit">
                                            <i class="fas fa-store"></i> 进入店铺
                                        </a>
                                        <a href="../product/list.php?shop=<?php echo $shopItem['id']; ?>" class="btn btn-products">
                                            <i class="fas fa-box"></i> 查看商品
                                        </a>
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
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
 