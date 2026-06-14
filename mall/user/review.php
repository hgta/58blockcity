<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

require_once '../../config/database.php';
require_once '../../classes/Order.php';
require_once '../../classes/Review.php';

$order = new Order($pdo);
$review = new Review($pdo);
$userId = $_SESSION['user_id'];

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// 验证订单归属和状态
$orderInfo = $order->getOrderById($orderId);
if (!$orderInfo || $orderInfo['user_id'] != $userId || $orderInfo['status'] !== 'completed') {
    header("Location: orders.php");
    exit();
}

// 获取可评价的商品列表
$reviewableItems = $review->getReviewableItems($orderId, $userId);

if (empty($reviewableItems)) {
    header("Location: order_detail.php?id=" . $orderId . "&msg=" . urlencode("该订单暂无需要评价的商品"));
    exit();
}

$error = '';
$success = '';

// 处理评价提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST['reviews'] as $itemId => $reviewData) {
            $itemId = intval($itemId);
            $rating = intval($reviewData['rating'] ?? 5);
            $content = trim($reviewData['content'] ?? '');
            $isAnonymous = isset($reviewData['anonymous']) ? 1 : 0;
            
            if ($rating < 1 || $rating > 5) {
                $rating = 5;
            }
            
            // 找到对应的订单项
            $orderItem = null;
            foreach ($reviewableItems as $item) {
                if ($item['id'] == $itemId) {
                    $orderItem = $item;
                    break;
                }
            }
            
            if (!$orderItem) {
                continue;
            }
            
            // 处理评价图片上传
            $reviewImages = [];
            if (!empty($_FILES['review_images']['name'][$itemId])) {
                $uploadDir = __DIR__ . '/../../assets/uploads/reviews/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['review_images']['name'][$itemId] as $idx => $filename) {
                    if ($_FILES['review_images']['error'][$itemId][$idx] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $newName = uniqid() . '_' . time() . '.' . $ext;
                            if (move_uploaded_file($_FILES['review_images']['tmp_name'][$itemId][$idx], $uploadDir . $newName)) {
                                $reviewImages[] = 'assets/uploads/reviews/' . $newName;
                            }
                        }
                    }
                }
            }
            
            $review->createReview([
                'order_id' => $orderId,
                'order_item_id' => $itemId,
                'product_id' => $orderItem['product_id'],
                'user_id' => $userId,
                'shop_id' => $orderInfo['shop_id'],
                'rating' => $rating,
                'content' => $content,
                'images' => $reviewImages,
                'is_anonymous' => $isAnonymous
            ]);
        }
        
        $success = '评价提交成功！';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = '评价订单';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 58人气值商城</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 22px; color: #333; }
        .page-header p { color: #999; font-size: 14px; margin-top: 5px; }
        
        .review-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .product-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 15px;
        }
        .product-info img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .product-info .name {
            font-size: 15px;
            color: #333;
        }
        
        .star-rating {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 28px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #f39c12;
        }
        .star-rating input:checked ~ label {
            color: #f39c12;
        }
        
        .review-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        .review-textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .image-upload {
            margin-top: 12px;
        }
        .image-upload input[type="file"] {
            display: none;
        }
        .image-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: 1px dashed #ccc;
            border-radius: 6px;
            color: #666;
            font-size: 13px;
            cursor: pointer;
        }
        .image-upload-btn:hover {
            border-color: #3498db;
            color: #3498db;
        }
        
        .anonymous-option {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
            font-size: 13px;
            color: #666;
        }
        .anonymous-option input {
            width: 16px;
            height: 16px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
        }
        .btn-submit:hover {
            background: #c0392b;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        .success-actions {
            text-align: center;
            padding: 30px;
        }
        .success-actions i {
            font-size: 48px;
            color: #27ae60;
            margin-bottom: 15px;
        }
        .success-actions a {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 24px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-pen"></i> <?php echo $pageTitle; ?></h1>
            <p>订单号：<?php echo htmlspecialchars($orderInfo['order_no'] ?? $orderInfo['id']); ?></p>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="success-actions">
                <i class="fas fa-check-circle"></i>
                <h2 style="color:#333;margin-bottom:10px;">评价提交成功！</h2>
                <p style="color:#666;">感谢您的评价，您的反馈对其他买家很有帮助</p>
                <a href="order_detail.php?id=<?php echo $orderId; ?>">返回订单详情</a>
            </div>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <?php foreach ($reviewableItems as $item): ?>
                    <div class="review-card">
                        <div class="product-info">
                            <img src="<?php echo '../../' . htmlspecialchars($item['product_image'] ?: 'assets/images/default-product.jpg'); ?>" alt="">
                            <div class="name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        </div>
                        
                        <div class="star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?php echo $item['id']; ?>_<?php echo $i; ?>" name="reviews[<?php echo $item['id']; ?>][rating]" value="<?php echo $i; ?>" <?php echo $i == 5 ? 'checked' : ''; ?>>
                                <label for="star<?php echo $item['id']; ?>_<?php echo $i; ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                        
                        <textarea class="review-textarea" name="reviews[<?php echo $item['id']; ?>][content]" placeholder="分享您的使用体验，帮助其他买家了解商品..." maxlength="500"></textarea>
                        
                        <div class="image-upload">
                            <label class="image-upload-btn" onclick="document.getElementById('img<?php echo $item['id']; ?>').click()">
                                <i class="fas fa-camera"></i> 添加图片
                            </label>
                            <input type="file" id="img<?php echo $item['id']; ?>" name="review_images[<?php echo $item['id']; ?>][]" accept="image/*" multiple onchange="showPreview(this, <?php echo $item['id']; ?>)">
                            <div id="preview<?php echo $item['id']; ?>" style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;"></div>
                        </div>
                        
                        <div class="anonymous-option">
                            <input type="checkbox" id="anon<?php echo $item['id']; ?>" name="reviews[<?php echo $item['id']; ?>][anonymous]" value="1">
                            <label for="anon<?php echo $item['id']; ?>">匿名评价</label>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" class="btn-submit">提交评价</button>
                <a href="order_detail.php?id=<?php echo $orderId; ?>" style="display:block;text-align:center;margin-top:12px;color:#999;font-size:14px;text-decoration:none;">暂不评价</a>
            </form>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        function showPreview(input, itemId) {
            const preview = document.getElementById('preview' + itemId);
            preview.innerHTML = '';
            if (input.files) {
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.cssText = 'width:60px;height:60px;object-fit:cover;border-radius:6px;';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            }
        }
    </script>
</body>
</html>
