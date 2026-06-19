<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
 

// 加载相关类
require_once '../../classes/Product.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Cart.php';
require_once '../../classes/Category.php';

$product = new Product($pdo);
$shop = new Shop($pdo);
$cart = new Cart($pdo);
$category = new Category($pdo);

// 获取商品ID
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$productId) {
    header("Location: list.php");
    exit();
}

// 获取商品详情
$productDetail = $product->getProductById($productId);

if (!$productDetail) {
    header("Location: list.php");
    exit();
}

// 增加商品浏览量
$product->incrementViewCount($productId);

// 获取店铺信息
$shopInfo = $shop->getShopById($productDetail['shop_id']);

// 获取店铺支持的支付城市及关联区块（商品继承店铺设置）
$paymentCities = $shop->getShopPaymentSettings($productDetail['shop_id']);
$supportedCitiesMap = $shop->getSupportedCities(); // pinyin => ['id','name']

// 提前解析商品副图，供页面多处使用
$extraImages = [];
if (!empty($productDetail['images'])) {
    $decoded = json_decode($productDetail['images'], true);
    if (is_array($decoded)) {
        $extraImages = $decoded;
    }
}

// 获取相关商品
$relatedProducts = $product->getRelatedProducts($productDetail['category_id'], $productId, 4);

// 获取商品评价
require_once '../../classes/Review.php';
$review = new Review($pdo);
$reviewStats = $review->getProductReviewStats($productId);
$reviews = $review->getProductReviews($productId, 1, 10);
$reviewCount = $review->getProductReviewCount($productId);

// 处理添加到购物车
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    $userId = $_SESSION['user_id'];
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // 验证数量
    if ($quantity < 1) {
        $error = '数量必须大于0';
    } elseif ($quantity > $productDetail['stock']) {
        $error = '库存不足，最多可购买 ' . $productDetail['stock'] . ' 件';
    } else {
        try {
            // 使用数据库存储购物车数据
            $result = $cart->addItem($userId, $productId, $quantity);
            
            if ($result) {
                $success = '商品已成功加入购物车！';
                // 更新购物车数量显示
                $cartCount = $cart->getItemCount($userId);
            } else {
                $error = '添加到购物车失败，请稍后重试';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// 处理评价提交
$reviewError = '';
$reviewSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    $rating = intval($_POST['rating'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $reviewImages = [];

    // 处理图片上传
    if (isset($_FILES['review_images']) && !empty($_FILES['review_images']['name'][0])) {
        foreach ($_FILES['review_images']['tmp_name'] as $i => $tmp) {
            if ($_FILES['review_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = pathinfo($_FILES['review_images']['name'][$i], PATHINFO_EXTENSION);
            $name = uniqid() . '_' . time() . '.' . $ext;
            $dir = __DIR__ . '/../../assets/uploads/reviews/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $path = $dir . $name;
            if (move_uploaded_file($tmp, $path)) {
                $reviewImages[] = 'assets/uploads/reviews/' . $name;
            }
        }
    }

    if ($rating < 1 || $rating > 5) {
        $reviewError = '请选择评分';
    } elseif (empty($content)) {
        $reviewError = '请输入评价内容';
    } else {
        try {
            $review->createProductReview([
                'user_id' => $_SESSION['user_id'],
                'product_id' => $productId,
                'shop_id' => $productDetail['shop_id'],
                'rating' => $rating,
                'content' => $content,
                'images' => $reviewImages,
                'is_anonymous' => $isAnonymous,
            ]);
            $reviewSuccess = '评价发布成功！';
            // 刷新评价数据
            $reviews = $review->getProductReviews($productId, 1, 10);
            $reviewCount = $review->getProductReviewCount($productId);
            $reviewStats = $review->getProductReviewStats($productId);
        } catch (Exception $e) {
            $reviewError = $e->getMessage();
        }
    }
}

// 获取购物车数量（用于显示）
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    $cartCount = $cart->getItemCount($_SESSION['user_id']);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($productDetail['name']); ?> - 58人气值商城</title>
    <style>
        /* 人气值符号样式 */
        .bct-symbol {
            font-family: Arial, sans-serif;
            font-weight: bold;
            color: #e74c3c;
        }
        
        .product-detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* 商品标题 */
        .product-title {
            font-size: 20px !important;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.4;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* 销量浏览横排 */
        .product-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 14px;
            font-size: 13px;
            color: #999;
        }
        .product-meta > div { display: flex; align-items: center; gap: 4px; }
        .product-meta i { font-size: 12px; }
        
        .breadcrumb {
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        /* 价格区域样式 */
        .product-price {
            margin-bottom: 16px;
            padding: 16px 20px;
            background: linear-gradient(135deg, #fff8f0, #fff);
            border-radius: 10px;
            border: 1px solid #ffe8d0;
        }

        .bct-price {
            font-size: 28px;
            font-weight: bold;
            color: #e74c3c;
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .reference-price {
            font-size: 13px;
            color: #999;
            font-weight: normal;
        }

        .price-info {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        .payment-notice {
            background: #fff3cd;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
            color: #856404;
        }
        
        /* 其余样式保持不变 */
        .product-stock {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .stock-label {
            color: #666;
            margin-bottom: 5px;
        }
        
        .stock-count {
            font-weight: bold;
            color: #27ae60;
        }
        
        .stock-warning {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        
        /* 购买选项 */
        .purchase-options {
            margin-bottom: 25px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        /* 店铺信息卡片 */
        .shop-info {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid #f0f0f0;
        }
        .shop-info-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
        }
        .shop-info-header .shop-logo {
            width: 56px;
            height: 56px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #eee;
        }
        .shop-info-name {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .shop-info-meta {
            font-size: 13px;
            color: #64748b;
        }
        .shop-info-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-shop {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #334155;
        }
        .btn-shop:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #2563eb;
            text-decoration: none;
        }
        .btn-shop.primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }
        .btn-shop.primary:hover {
            background: #1d4ed8;
        }

        /* 右侧信息卡片 */
        .info-card {
            background: #fff;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid #f1f5f9;
        }
        .info-card-title {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .city-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            margin: 0 6px 6px 0;
        }
        .city-tag i { font-size: 10px; }

        /* 商品详情图片画廊 */
        .detail-image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 20px;
        }
        .detail-image-gallery img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            cursor: zoom-in;
            transition: transform .2s;
        }
        .detail-image-gallery img:hover {
            transform: scale(1.02);
        }
        
        .quantity-label {
            font-weight: bold;
            color: #333;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
        }

        .quantity-btn {
            width: 36px;
            height: 36px;
            border: none !important;
            background: #f8f9fa;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            padding: 0 !important;
            border-radius: 0 !important;
            flex: none !important;
        }

        .quantity-btn:hover {
            background: #e9ecef;
        }

        .quantity-input {
            width: 50px;
            height: 36px;
            border: none;
            text-align: center;
            font-size: 14px;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            outline: none;
            -moz-appearance: textfield;
        }
        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-cart {
            background: #ff6b6b;
            color: white;
            border: 2px solid #ff6b6b;
        }
        
        .btn-cart:hover {
            background: #ff5252;
            transform: translateY(-2px);
        }
        
        .btn-buy {
            background: #e74c3c;
            color: white;
            border: 2px solid #e74c3c;
        }
        
        .btn-buy:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        /* 消息样式 */
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
		
		/* 商品图片大小限制 */
.product-gallery {
    position: relative;
}

.main-image {
    width: 100%;
    max-width: 500px;
    height: auto;
    max-height: 500px;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    margin-bottom: 15px;
}

.image-thumbnails {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.thumbnail {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 4px;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.thumbnail.active,
.thumbnail:hover {
    border-color: #e74c3c;
    transform: scale(1.05);
}

/* 店铺logo大小限制 */
.shop-logo {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 15px;
    border: 2px solid #f0f0f0;
}

/* 相关商品图片大小限制 */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.product-card {
    text-decoration: none;
    color: inherit;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
}

.product-card-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
}

.product-card-info {
    padding: 15px;
}

.product-card-name {
    font-weight: bold;
    margin-bottom: 8px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.product-card-price {
    color: #e74c3c;
    font-weight: bold;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .product-detail {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .main-image {
        max-width: 100%;
        max-height: 400px;
    }
    
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .product-card-image {
        height: 150px;
    }
}
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="product-detail-container">
        <!-- 面包屑导航 -->
        <div class="breadcrumb">
            <a href="../index.php">首页</a> &gt; 
            <a href="list.php">商品列表</a> &gt; 
            <span><?php echo htmlspecialchars($productDetail['name']); ?></span>
        </div>
        
        <!-- 消息显示 -->
        <?php if ($success): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="product-detail">
            <!-- 商品图片/视频区域 -->
            <div class="product-gallery">
                <?php $mainImagePath = $productDetail['main_image'] ?: 'assets/images/default-product.jpg'; ?>

                <?php if (!empty($productDetail['video_url'])): ?>
                    <!-- 有视频时优先展示视频 -->
                    <div class="product-video-wrapper" style="position:relative;margin-bottom:15px;">
                        <span style="position:absolute;top:8px;left:8px;background:rgba(0,0,0,0.6);color:#fff;padding:3px 10px;border-radius:4px;font-size:12px;z-index:2;">🎬 视频介绍</span>
                        <video controls poster="<?php echo '../' . htmlspecialchars($mainImagePath); ?>" style="width:100%;max-height:400px;border-radius:8px;background:#000;">
                            <source src="<?php echo '../' . htmlspecialchars($productDetail['video_url']); ?>" type="video/mp4">
                            您的浏览器不支持视频播放
                        </video>
                    </div>
                <?php else: ?>
                    <!-- 无视频时展示主图 -->
                    <img src="<?php echo '../' . htmlspecialchars($mainImagePath); ?>" 
                         alt="<?php echo htmlspecialchars($productDetail['name']); ?>" 
                         class="main-image" id="main-image">
                <?php endif; ?>
                
                <!-- 商品图集（主图 + 副图） -->
                <div class="image-thumbnails">
                    <img src="<?php echo '../' . htmlspecialchars($mainImagePath); ?>" 
                         alt="<?php echo htmlspecialchars($productDetail['name']); ?>" 
                         class="thumbnail active" 
                         onclick="changeMainImage(this.src)">
                    <?php foreach ($extraImages as $img):
                        $imgPath = '../' . htmlspecialchars($img);
                    ?>
                        <img src="<?php echo $imgPath; ?>" 
                             alt="商品图片" 
                             class="thumbnail" 
                             onclick="changeMainImage(this.src)">
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- 商品信息区域 -->
            <div class="product-info">
                <h1 class="product-title"><?php echo htmlspecialchars($productDetail['name']); ?></h1>

                <div class="product-meta">
                    <div><i class="fas fa-chart-line"></i> 销量: <?php echo number_format($productDetail['sold_count']); ?></div>
                    <div><i class="fas fa-eye"></i> 浏览: <?php echo number_format($productDetail['view_count']); ?></div>
                </div>

                <!-- 价格 + 支付信息合并卡片 -->
                <div class="product-price">
                    <?php if ($productDetail['price_bct'] > 0): ?>
                        <div class="bct-price">
                            <span class="bct-symbol">Ⓟ</span><?php echo number_format($productDetail['price_bct'], 0); ?> 人气值
                            <?php if ($productDetail['price_cny']): ?>
                                <span class="reference-price">参考 ¥<?php echo number_format($productDetail['price_cny'], 2); ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:10px;font-size:13px;color:#666;">
                            <i class="fas fa-coins" style="color:#ff6b00;"></i> 人气值支付
                            <?php if (!empty($paymentCities)): ?>
                                · 支持城市:
                                <?php foreach ($paymentCities as $pc):
                                    $cityName = $supportedCitiesMap[$pc['city']]['name'] ?? $pc['city'];
                                ?>
                                    <span class="city-tag" title="<?php echo htmlspecialchars($cityName); ?> · <?php echo htmlspecialchars($pc['block_zone'] ?: '?'); ?>区 #<?php echo htmlspecialchars($pc['block_number'] ?: '未设置'); ?>">
                                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($cityName); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="bct-price" style="color: #27ae60;">
                            ¥<?php echo number_format($productDetail['price_cny'], 2); ?>
                        </div>
                        <div style="margin-top:8px;font-size:13px;color:#666;">
                            <i class="fas fa-yen-sign" style="color:#27ae60;"></i> 人民币支付
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 库存（紧凑一行） -->
                <div style="margin-bottom:16px;font-size:14px;color:#666;">
                    库存: <strong style="color:<?php echo $productDetail['stock'] > 0 ? '#27ae60' : '#e74c3c'; ?>;"><?php echo number_format($productDetail['stock']); ?> 件</strong>
                    <?php if ($productDetail['stock'] > 0 && $productDetail['stock'] < 10): ?>
                        <span style="color:#e74c3c;font-size:12px;margin-left:8px;">⚠ 库存紧张</span>
                    <?php elseif ($productDetail['stock'] == 0): ?>
                        <span style="color:#e74c3c;font-size:12px;margin-left:8px;">暂时缺货</span>
                    <?php endif; ?>
                </div>

                <!-- 购买选项 -->
                <form method="POST" action="" class="purchase-options">
                    <div class="quantity-selector">
                        <div class="quantity-label">数量</div>
                        <div class="quantity-controls">
                            <button type="button" class="quantity-btn" onclick="decreaseQuantity()">-</button>
                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $productDetail['stock']; ?>"
                                   class="quantity-input" id="quantity-input">
                            <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                        </div>
                        <div style="color: #999; font-size: 13px;">
                            最多 <?php echo number_format($productDetail['stock']); ?> 件
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" name="add_to_cart" class="btn btn-cart">
                            <i class="fas fa-shopping-cart"></i> 加入购物车
                        </button>
                        <button type="button" class="btn btn-buy" onclick="buyNow(<?php echo $productId; ?>, this)">
                            <i class="fas fa-bolt"></i> 立即购买
                        </button>
                    </div>
                </form>

                <!-- 店铺信息（右侧精简版） -->
                <?php if ($shopInfo): ?>
                <div class="info-card">
                    <div class="info-card-title"><i class="fas fa-store"></i> 所属店铺</div>
                    <div class="shop-info-header" style="margin-bottom:0;">
                        <img src="../<?php echo htmlspecialchars($shopInfo['shop_logo'] ?: 'assets/images/default-shop.jpg'); ?>"
                             alt="<?php echo htmlspecialchars($shopInfo['shop_name']); ?>"
                             class="shop-logo" style="width:44px;height:44px;border-radius:8px;">
                        <div>
                            <div class="shop-info-name" style="font-size:15px;"><?php echo htmlspecialchars($shopInfo['shop_name']); ?></div>
                            <div class="shop-info-meta"><?php echo htmlspecialchars(mb_strimwidth($shopInfo['description'] ?? '', 0, 40, '...')); ?></div>
                        </div>
                    </div>
                    <div class="shop-info-actions">
                        <a href="../shop/view.php?id=<?php echo $shopInfo['id']; ?>" class="btn-shop primary">
                            <i class="fas fa-store"></i> 进入店铺
                        </a>
                        <a href="../product/list.php?shop=<?php echo $shopInfo['id']; ?>" class="btn-shop">
                            <i class="fas fa-th-large"></i> 全部商品
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 商品详情 -->
        <div class="product-description">
            <h3 class="description-title">商品详情</h3>
            <div class="description-content">
                <?php if ($productDetail['description']): ?>
                    <?php echo nl2br(htmlspecialchars($productDetail['description'])); ?>
                <?php else: ?>
                    <p style="color: #999; text-align: center; padding: 40px;">
                        该商品暂无详细描述
                    </p>
                <?php endif; ?>

                <!-- 商品图片画廊（详情正文展示） -->
                <?php if (!empty($extraImages) || !empty($productDetail['main_image'])): ?>
                    <div style="margin-top: 30px;">
                        <h4 style="font-size: 16px; color: #333; margin-bottom: 15px;"><i class="fas fa-images"></i> 商品图集</h4>
                        <div class="detail-image-gallery">
                            <?php if (!empty($productDetail['main_image'])): ?>
                                <img src="<?php echo '../' . htmlspecialchars($productDetail['main_image']); ?>" alt="商品主图" onclick="showImageModal(this.src)">
                            <?php endif; ?>
                            <?php foreach ($extraImages as $img): ?>
                                <img src="<?php echo '../' . htmlspecialchars($img); ?>" alt="商品图片" onclick="showImageModal(this.src)">
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 商品视频（详情正文展示） -->
                <?php if (!empty($productDetail['video_url'])): ?>
                    <div style="margin-top: 30px;">
                        <h4 style="font-size: 16px; color: #333; margin-bottom: 15px;"><i class="fas fa-video"></i> 视频介绍</h4>
                        <video controls poster="<?php echo '../' . htmlspecialchars($mainImagePath); ?>" style="width:100%;max-width:640px;border-radius:8px;background:#000;">
                            <source src="<?php echo '../' . htmlspecialchars($productDetail['video_url']); ?>" type="video/mp4">
                            您的浏览器不支持视频播放
                        </video>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 商品评价 -->
        <div class="product-reviews" style="background:white;border-radius:12px;padding:25px;margin-top:20px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <h3 style="font-size:18px;margin:0 0 20px;color:#333;"><i class="fas fa-comment-dots"></i> 商品评价 <?php if ($reviewCount > 0): ?><span style="color:#999;font-size:14px;">(<?php echo $reviewCount; ?>)</span><?php endif; ?></h3>
            
            <?php if ($reviewCount > 0): ?>
                <!-- 评分统计 -->
                <div style="display:flex;align-items:center;gap:30px;margin-bottom:25px;padding-bottom:20px;border-bottom:1px solid #f0f0f0;">
                    <div style="text-align:center;">
                        <div style="font-size:36px;font-weight:bold;color:#e74c3c;"><?php echo number_format($reviewStats['avg_rating'], 1); ?></div>
                        <div style="color:#f39c12;font-size:16px;margin-top:4px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= round($reviewStats['avg_rating']) ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div style="flex:1;">
                        <?php foreach ([5,4,3,2,1] as $star): 
                            $count = $reviewStats["{$star}_star"] ?? 0;
                            $percent = $reviewCount > 0 ? round($count / $reviewCount * 100) : 0;
                        ?>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                <span style="font-size:12px;color:#666;width:24px;"><?php echo $star; ?>星</span>
                                <div style="flex:1;height:8px;background:#f0f0f0;border-radius:4px;overflow:hidden;">
                                    <div style="width:<?php echo $percent; ?>%;height:100%;background:#f39c12;border-radius:4px;"></div>
                                </div>
                                <span style="font-size:12px;color:#999;width:32px;text-align:right;"><?php echo $count; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- 评价列表 -->
                <div style="display:flex;flex-direction:column;gap:20px;">
                    <?php foreach ($reviews as $r): ?>
                        <div style="padding:15px;background:#fafafa;border-radius:8px;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#3498db,#2980b9);color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:bold;">
                                    <?php echo $r['is_anonymous'] ? '匿' : mb_substr($r['nickname'] ?? '用', 0, 1); ?>
                                </div>
                                <div>
                                    <div style="font-size:14px;color:#333;"><?php echo $r['is_anonymous'] ? '匿名用户' : htmlspecialchars($r['nickname'] ?? '用户'); ?></div>
                                    <div style="color:#f39c12;font-size:12px;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $r['rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div style="margin-left:auto;font-size:12px;color:#999;"><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></div>
                            </div>
                            <div style="font-size:14px;color:#555;line-height:1.6;margin-bottom:8px;"><?php echo nl2br(htmlspecialchars($r['content'])); ?></div>
                            <?php if (!empty($r['images'])): 
                                $reviewImages = json_decode($r['images'], true);
                                if (is_array($reviewImages) && !empty($reviewImages)): 
                            ?>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <?php foreach ($reviewImages as $img): ?>
                                        <img src="<?php echo '../' . htmlspecialchars($img); ?>" style="width:80px;height:80px;object-fit:cover;border-radius:6px;cursor:pointer;" onclick="showImageModal(this.src)">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; endif; ?>
                            <?php if (!empty($r['reply_content'])): ?>
                                <div style="margin-top:10px;padding:10px 12px;background:#e8f4f8;border-radius:6px;border-left:3px solid #3498db;">
                                    <div style="font-size:12px;color:#3498db;margin-bottom:4px;"><i class="fas fa-store"></i> 商家回复</div>
                                    <div style="font-size:13px;color:#555;"><?php echo htmlspecialchars($r['reply_content']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:30px;color:#999;">
                    <i class="fas fa-comment-slash" style="font-size:32px;margin-bottom:10px;opacity:0.5;"></i>
                    <div>暂无评价，快来发表第一条评价吧~</div>
                </div>
            <?php endif; ?>

            <!-- 评价表单 -->
            <?php if (!empty($reviewError)): ?>
                <div style="background:#fef2f2;color:#dc2626;padding:10px 14px;border-radius:6px;margin-bottom:12px;font-size:13px;">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($reviewError) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($reviewSuccess)): ?>
                <div style="background:#f0fdf4;color:#16a34a;padding:10px 14px;border-radius:6px;margin-bottom:12px;font-size:13px;">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($reviewSuccess) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
            <div style="margin-top:20px;padding:16px;background:#f8fafc;border-radius:10px;">
                <h4 style="font-size:15px;margin-bottom:12px;color:#333;">发表评价</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="submit_review" value="1">
                    <div style="margin-bottom:10px;">
                        <label style="font-size:13px;color:#666;">评分</label>
                        <div id="star-rating" style="display:flex;gap:4px;font-size:24px;cursor:pointer;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span data-star="<?= $i ?>" style="color:#ddd;" onclick="setRating(<?= $i ?>)">★</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="rating-input" value="0">
                    </div>
                    <div style="margin-bottom:10px;">
                        <textarea name="content" rows="3" placeholder="分享您的使用体验..." required
                                  style="width:100%;padding:10px;border:1px solid #e0e0e0;border-radius:8px;font-size:13px;resize:vertical;"></textarea>
                    </div>
                    <div style="margin-bottom:10px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                        <label style="font-size:13px;cursor:pointer;color:#3498db;">
                            <i class="fas fa-image"></i> 上传图片
                            <input type="file" name="review_images[]" accept="image/*" multiple style="display:none;" onchange="previewReviewImages(this)">
                        </label>
                        <label style="font-size:13px;cursor:pointer;color:#666;">
                            <input type="checkbox" name="is_anonymous" value="1" style="margin-right:4px;">匿名评价
                        </label>
                    </div>
                    <div id="review-image-preview" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;"></div>
                    <button type="submit" style="padding:8px 24px;background:#ff6b00;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;">
                        <i class="fas fa-paper-plane"></i> 提交评价
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div style="margin-top:20px;padding:20px;background:#f8fafc;border-radius:10px;text-align:center;">
                <p style="color:#666;margin-bottom:10px;">登录后即可评价</p>
                <a href="../auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" style="display:inline-block;padding:8px 24px;background:#ff6b00;color:#fff;border-radius:6px;text-decoration:none;font-size:14px;">登录</a>
                <span style="color:#999;margin:0 8px;">或</span>
                <a href="../auth/register.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" style="display:inline-block;padding:8px 24px;background:#3498db;color:#fff;border-radius:6px;text-decoration:none;font-size:14px;">注册</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- 相关商品 -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="related-products">
                <h3 class="section-title">相关推荐</h3>
                <div class="products-grid">
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                        <a href="view.php?id=<?php echo $relatedProduct['id']; ?>" class="product-card">
                            <?php $relatedImage = $relatedProduct['thumb_image'] ?: $relatedProduct['main_image'] ?: 'assets/images/default-product.jpg'; ?>
                            <img src="<?php echo '../' . htmlspecialchars($relatedImage); ?>" 
                                 alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>" 
                                 class="product-card-image">
                            <div class="product-card-info">
                                <div class="product-card-name"><?php echo htmlspecialchars($relatedProduct['name']); ?></div>
                                <div class="product-card-price">
                                    <?php if ($relatedProduct['price_type'] == 'bct'): ?>
                                        <span class="bct-symbol">Ⓟ</span><?php echo number_format($relatedProduct['price_bct'], 0); ?>
                                    <?php else: ?>
                                        ¥<?php echo number_format($relatedProduct['price_cny'], 2); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // 切换主图
        function changeMainImage(src) {
            document.getElementById('main-image').src = src;
            
            // 更新缩略图激活状态
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // 增加数量
        function increaseQuantity() {
            const input = document.getElementById('quantity-input');
            const max = parseInt(input.max);
            let value = parseInt(input.value) || 1;
            
            if (value < max) {
                input.value = value + 1;
            } else {
                alert('已达到最大购买数量');
            }
        }
        
        // 减少数量
        function decreaseQuantity() {
            const input = document.getElementById('quantity-input');
            let value = parseInt(input.value) || 1;
            
            if (value > 1) {
                input.value = value - 1;
            }
        }
        
        // 数量输入验证
        document.getElementById('quantity-input').addEventListener('change', function() {
            let value = parseInt(this.value) || 1;
            const max = parseInt(this.max);
            
            if (value < 1) {
                this.value = 1;
            } else if (value > max) {
                this.value = max;
                alert('最多可购买 ' + max + ' 件');
            }
        });
        
        // 防止表单重复提交
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 添加中...';
        });
        
        // 立即购买：先加入购物车，再跳转结算
        function buyNow(productId, btn) {
            var qty = document.getElementById('quantity-input').value;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
            
            fetch('../cart/add_to_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + productId + '&quantity=' + qty + '&clear_first=1'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.href = '../cart/checkout.php';
                } else {
                    alert(data.message || '操作失败');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-bolt"></i> 立即购买';
                }
            })
            .catch(function() {
                alert('网络错误，请重试');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-bolt"></i> 立即购买';
            });
        }

        // 图片放大查看
        function showImageModal(src) {
            const modal = document.createElement('div');
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
            modal.innerHTML = '<img src="' + src + '" style="max-width:90%;max-height:90%;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,0.5);">';
            modal.onclick = function() { modal.remove(); };
            document.body.appendChild(modal);
        }
    </script>
    
    <script>
    // 星级评分
    function setRating(star) {
        document.getElementById('rating-input').value = star;
        document.querySelectorAll('#star-rating span').forEach(function(s, i) {
            s.style.color = i < star ? '#f59e0b' : '#ddd';
        });
    }
    // 评价图片预览
    function previewReviewImages(input) {
        var container = document.getElementById('review-image-preview');
        container.innerHTML = '';
        Array.from(input.files).forEach(function(file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width:60px;height:60px;object-fit:cover;border-radius:6px;';
                container.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html> 