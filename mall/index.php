 
<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

 

// 加载商品和店铺类
require_once '../classes/Product.php';
require_once '../classes/Shop.php';
require_once '../classes/Category.php';

$product = new Product($pdo);
$shop = new Shop($pdo);
$category = new Category($pdo);

// 获取各类数据
$recommendedProducts = $product->getRecommendedProducts(8);
$newProducts = $product->getNewProducts(8);
$popularProducts = $product->getPopularProducts(8);
$featuredShops = $shop->getFeaturedShops(6);
$categories = $category->getPopularCategories(8);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>58人气值购物商城 - BCT商城平台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* 轮播图样式 */
        .banner-section {
            margin: 20px 0 30px;
        }
        
        .banner-slider {
            position: relative;
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .banner-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: bold;
        }
        
        /* 分类导航 */
        .category-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 20px;
        }
        
        .category-item {
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.3s ease;
        }
        
        .category-item:hover {
            transform: translateY(-5px);
        }
        
        .category-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: white;
            font-size: 24px;
        }
        
        .category-name {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* 版块样式 */
        .section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-header h2 {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        
        .more-link {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .more-link:hover {
            color: #2980b9;
        }
        
        /* 商品网格 */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
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
        
        .product-info {
            padding: 15px;
        }
        
        .product-name {
            font-size: 14px;
            font-weight: 500;
            line-height: 1.4;
            margin-bottom: 8px;
            height: 40px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-price {
            margin-bottom: 8px;
        }
        
        .current-price {
            font-size: 18px;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .original-price {
            font-size: 12px;
            color: #999;
            text-decoration: line-through;
            margin-left: 5px;
        }
        
        .product-shop {
            font-size: 12px;
            color: #666;
        }
        
        .product-sales {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        /* 店铺网格 */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .shop-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .shop-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .shop-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .shop-logo {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .shop-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .shop-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .shop-rating {
            color: #f39c12;
            font-size: 14px;
        }
        
        .shop-description {
            font-size: 13px;
            color: #666;
            line-height: 1.4;
            margin-bottom: 10px;
        }
        
        .shop-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #999;
        }
        
        /* 特色区域 */
        .featured-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .promo-banner {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            border-radius: 12px;
            padding: 40px;
            color: white;
            text-align: center;
        }
        
        .promo-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .promo-desc {
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .promo-btn {
            background: white;
            color: #ee5a24;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
        }
        
        /* 响应式设计 */
        @media (max-width: 1024px) {
            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .shop-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .category-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .shop-grid {
                grid-template-columns: 1fr;
            }
            
            .category-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .featured-section {
                grid-template-columns: 1fr;
            }
            
            .banner-slider {
                height: 200px;
            }
        }
        
        @media (max-width: 480px) {
            .product-grid {
                grid-template-columns: 1fr;
            }
            
            .category-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <!-- Banner + 搜索 -->
        <div class="banner-section" style="margin:15px 0 20px;">
            <div style="background:linear-gradient(135deg,#1e3a5f,#2563eb);border-radius:12px;padding:20px 24px;color:#fff;text-align:center;">
                <h1 style="font-size:24px;font-weight:700;margin:0 0 6px;">58人气值购物商城</h1>
                <p style="font-size:14px;opacity:.85;margin:0 0 12px;">基于区块城市BlockCity的BCT商城交易平台</p>
                <form method="GET" action="product/list.php" style="max-width:460px;margin:0 auto;display:flex;gap:8px;">
                    <input type="text" name="search" placeholder="搜索商品..." style="flex:1;padding:10px 14px;border:none;border-radius:8px;font-size:14px;outline:none;">
                    <button type="submit" style="padding:10px 20px;background:#f59e0b;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                        <i class="fas fa-search"></i> 搜索
                    </button>
                </form>
            </div>
        </div>
        
        <!-- 分类导航 -->
        <div class="category-section">
            <div class="category-grid">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <a href="product/list.php?category=<?php echo $cat['id']; ?>" class="category-item">
                            <div class="category-icon">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="category-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- 默认分类 -->
                    <?php $defaultCategories = ['数码电子', '服装鞋帽', '家居百货', '美妆个护', '食品生鲜', '图书文具', '运动户外', '母婴玩具']; ?>
                    <?php foreach ($defaultCategories as $index => $catName): ?>
                        <a href="product/list.php" class="category-item">
                            <div class="category-icon">
                                <i class="fas fa-<?php echo ['mobile', 'tshirt', 'home', 'spa', 'apple-alt', 'book', 'running', 'baby'][$index]; ?>"></i>
                            </div>
                            <div class="category-name"><?php echo $catName; ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 推荐商品 -->
        <div class="section">
            <div class="section-header">
                <h2>🔥 热门推荐</h2>
                <a href="product/list.php?sort=popular" class="more-link">查看更多 &gt;</a>
            </div>
            <div class="product-grid">
                <?php if (!empty($recommendedProducts)): ?>
                    <?php foreach ($recommendedProducts as $product): ?>
                        <a href="product/detail.php?id=<?php echo $product['id']; ?>" class="product-card">
                            <div class="product-image">
                                <img src="<?php echo '../'.htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="product-price">
                                    <span class="current-price">¥<?php echo number_format($product['price'], 2); ?></span>
                                    <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                                        <span class="original-price">¥<?php echo number_format($product['original_price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-shop"><?php echo htmlspecialchars($product['shop_name'] ?: '平台自营'); ?></div>
                                <div class="product-sales">已售 <?php echo $product['sales_count'] ?? 0; ?> 件</div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <div>暂无推荐商品</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 新品上市 -->
        <div class="section">
            <div class="section-header">
                <h2>🆕 新品上市</h2>
                <a href="product/list.php?sort=newest" class="more-link">查看更多 &gt;</a>
            </div>
            <div class="product-grid">
                <?php if (!empty($newProducts)): ?>
                    <?php foreach ($newProducts as $product): ?>
                        <a href="product/detail.php?id=<?php echo $product['id']; ?>" class="product-card">
                            <div class="product-image">
                                <img src="<?php echo '../' . htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <div style="position: absolute; top: 10px; left: 10px; background: #e74c3c; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;">NEW</div>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="product-price">
                                    <span class="current-price">¥<?php echo number_format($product['price'], 2); ?></span>
                                </div>
                                <div class="product-shop"><?php echo htmlspecialchars($product['shop_name'] ?: '平台自营'); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-clock" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <div>暂无新品上架</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 热门店铺 -->
        <div class="section">
            <div class="section-header">
                <h2>🏪 热门店铺</h2>
                <a href="shop/list.php" class="more-link">查看更多 &gt;</a>
            </div>
            <div class="shop-grid">
                <?php if (!empty($featuredShops)): ?>
                    <?php foreach ($featuredShops as $shop): ?>
                        <a href="shop/view.php?id=<?php echo $shop['id']; ?>" class="shop-card">
                            <div class="shop-header">
                                <div class="shop-logo">
                                    <img src="<?php echo htmlspecialchars($shop['shop_logo'] ?: 'assets/images/default-shop.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($shop['shop_name']); ?>">
                                </div>
                                <div>
                                    <div class="shop-name"><?php echo htmlspecialchars($shop['shop_name']); ?></div>
                                    <div class="shop-rating">
                                        <i class="fas fa-star"></i> --
                                    </div>
                                </div>
                            </div>
                            <div class="shop-description">
                                <?php echo htmlspecialchars($shop['description'] ?: '这家店铺还没有描述...'); ?>
                            </div>
                            <div class="shop-stats">
                                <span>商品: <?php echo $shop['product_count'] ?? 0; ?></span>
                                <span>销量: <?php echo $shop['total_sales'] ?? 0; ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-store" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <div>暂无热门店铺</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 购物指南 -->
        <div class="section" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
            <div class="section-header">
                <h2>📖 购物指南</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-top: 10px;">
                <div style="text-align: center; padding: 25px 15px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                        <i class="fas fa-shopping-bag" style="color: white; font-size: 24px;"></i>
                    </div>
                    <h3 style="font-size: 16px; margin-bottom: 8px; color: #333;">① 浏览商品</h3>
                    <p style="font-size: 13px; color: #666; line-height: 1.6;">在商城中浏览商品，点击"加入购物车"</p>
                </div>
                <div style="text-align: center; padding: 25px 15px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #e74c3c, #c0392b); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                        <i class="fas fa-credit-card" style="color: white; font-size: 24px;"></i>
                    </div>
                    <h3 style="font-size: 16px; margin-bottom: 8px; color: #333;">② 提交订单</h3>
                    <p style="font-size: 13px; color: #666; line-height: 1.6;">结算时填写收货地址，选择BCT支付</p>
                </div>
                <div style="text-align: center; padding: 25px 15px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #27ae60, #219a52); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                        <i class="fas fa-link" style="color: white; font-size: 24px;"></i>
                    </div>
                    <h3 style="font-size: 16px; margin-bottom: 8px; color: #333;">③ 确认支付</h3>
                    <p style="font-size: 13px; color: #666; line-height: 1.6;">在 <strong>blockcity.vip</strong> 完成BCT转账后，回到商城点击"我已支付"确认</p>
                </div>
            </div>
            
            <!-- 开店引导 -->
            <div style="margin-top: 25px; padding: 25px; background: linear-gradient(135deg, #f39c12, #e67e22); border-radius: 12px; color: white; text-align: center;">
                <div style="font-size: 20px; font-weight: bold; margin-bottom: 8px;">
                    <i class="fas fa-store-alt"></i> 想开店吗？
                </div>
                <div style="font-size: 14px; margin-bottom: 15px; opacity: 0.9;">
                    免费开通店铺，上架商品接受BCT支付，将你的BlockCity人气值变现
                </div>
                <a href="shop/create.php" style="display: inline-block; padding: 12px 30px; background: white; color: #e67e22; border-radius: 25px; text-decoration: none; font-weight: bold; font-size: 15px;">
                    <i class="fas fa-plus"></i> 立即开店
                </a>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html> 