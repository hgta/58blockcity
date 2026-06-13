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

// 获取相关商品
$relatedProducts = $product->getRelatedProducts($productDetail['category_id'], $productId, 4);

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
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .bct-price {
            font-size: 32px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 10px;
        }
        
        .reference-price {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
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
        
        .quantity-label {
            font-weight: bold;
            color: #333;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: #f8f9fa;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .quantity-btn:hover {
            background: #e9ecef;
        }
        
        .quantity-input {
            width: 60px;
            height: 40px;
            border: none;
            text-align: center;
            font-size: 16px;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
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
                    <?php
                    $extraImages = [];
                    if (!empty($productDetail['images'])) {
                        $decoded = json_decode($productDetail['images'], true);
                        if (is_array($decoded)) {
                            $extraImages = $decoded;
                        }
                    }
                    foreach ($extraImages as $img):
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
                    <div class="product-sales">
                        <i class="fas fa-chart-line"></i> 销量: <?php echo number_format($productDetail['sold_count']); ?>
                    </div>
                    <div class="product-views">
                        <i class="fas fa-eye"></i> 浏览: <?php echo number_format($productDetail['view_count']); ?>
                    </div>
                </div>
                
                <!-- 修正价格显示 -->
                <div class="product-price">
                    <?php if ($productDetail['price_bct'] > 0): ?>
                        <!-- 人气值支付商品 -->
                        <div class="bct-price">
                            <span class="bct-symbol">Ⓟ</span><?php echo number_format($productDetail['price_bct'], 0); ?> 人气值
                        </div>
                        
                        <?php if ($productDetail['price_cny']): ?>
                            <div class="reference-price">
                                参考人民币价格: ¥<?php echo number_format($productDetail['price_cny'], 2); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="price-info">
                            <strong>支付方式: 人气值支付</strong>
                        </div>
                        
                        <div class="payment-notice">
                            <i class="fas fa-info-circle"></i>
                            此商品使用人气值支付，不同城市的人气值汇率可能不同
                        </div>
                        
                    <?php else: ?>
                        <!-- 人民币支付商品 -->
                        <div class="bct-price" style="color: #27ae60;">
                            ¥<?php echo number_format($productDetail['price_cny'], 2); ?>
                        </div>
                        <div class="price-info">
                            <strong>支付方式: 人民币支付</strong>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="product-stock">
                    <div class="stock-label">库存</div>
                    <div class="stock-count"><?php echo number_format($productDetail['stock']); ?> 件</div>
                    <?php if ($productDetail['stock'] < 10 && $productDetail['stock'] > 0): ?>
                        <div class="stock-warning">库存紧张，欲购从速</div>
                    <?php elseif ($productDetail['stock'] == 0): ?>
                        <div class="stock-warning">暂时缺货</div>
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
                        <div style="color: #666; font-size: 14px;">
                            最多可购买 <?php echo number_format($productDetail['stock']); ?> 件
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
            </div>
        </div>
        
        <!-- 店铺信息 -->
        <?php if ($shopInfo): ?>
            <div class="shop-info">
                <div class="shop-header">
                    <img src="<?php echo htmlspecialchars($shopInfo['avatar_url'] ?: '../assets/images/default-shop.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($shopInfo['shop_name']); ?>" 
                         class="shop-logo">
                    <div style="flex: 1;">
                        <div class="shop-name"><?php echo htmlspecialchars($shopInfo['shop_name']); ?></div>
                        <div class="shop-rating">
                            <i class="fas fa-star"></i> --
                        </div>
                    </div>
                    <div class="shop-actions">
                        <a href="../shop/view.php?id=<?php echo $shopInfo['id']; ?>" class="btn-shop">
                            进入店铺
                        </a>
                        <a href="../product/list.php?shop=<?php echo $shopInfo['id']; ?>" class="btn-shop">
                            查看所有商品
                        </a>
                    </div>
                </div>
                <?php if ($shopInfo['description']): ?>
                    <div style="color: #666; line-height: 1.6;">
                        <?php echo htmlspecialchars($shopInfo['description']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
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
            </div>
        </div>
        
        <!-- 相关商品 -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="related-products">
                <h3 class="section-title">相关推荐</h3>
                <div class="products-grid">
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                        <a href="view.php?id=<?php echo $relatedProduct['id']; ?>" class="product-card">
                            <?php $relatedImage = $relatedProduct['main_image'] ?: 'assets/images/default-product.jpg'; ?>
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
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html> 