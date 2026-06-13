<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';

// 管理员鉴权：只有管理员可访问
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = '';
$error = '';

// 预设分类
$defaultCategories = [
    ['name' => '数码电子', 'description' => '手机、电脑、数码配件等'],
    ['name' => '服装鞋帽', 'description' => '男装、女装、童装、鞋帽配饰'],
    ['name' => '家居百货', 'description' => '家居用品、日用百货、家纺'],
    ['name' => '美妆个护', 'description' => '护肤品、化妆品、个人护理'],
    ['name' => '食品生鲜', 'description' => '零食、饮料、生鲜食材'],
    ['name' => '图书文具', 'description' => '图书、文具、办公用品'],
    ['name' => '运动户外', 'description' => '运动装备、户外用品、健身器材'],
    ['name' => '母婴玩具', 'description' => '母婴用品、儿童玩具'],
];

// 处理初始化分类
if (isset($_POST['init_categories'])) {
    try {
        $count = 0;
        foreach ($defaultCategories as $cat) {
            // 检查是否已存在
            $stmt = $pdo->prepare("SELECT id FROM product_categories WHERE name = ?");
            $stmt->execute([$cat['name']]);
            if ($stmt->fetch()) continue;
            
            $stmt = $pdo->prepare("INSERT INTO product_categories (name, description, status, sort_order, created_at) VALUES (?, ?, 'active', ?, NOW())");
            $stmt->execute([$cat['name'], $cat['description'], $count + 1]);
            $count++;
        }
        $message = "成功初始化 {$count} 个分类";
    } catch (Exception $e) {
        $error = "初始化分类失败：" . $e->getMessage();
    }
}

// 处理创建店铺
if (isset($_POST['create_shop'])) {
    $shopName = trim($_POST['shop_name'] ?? '');
    $shopDesc = trim($_POST['shop_description'] ?? '');
    $shopLogo = trim($_POST['shop_logo'] ?? '');
    
    if (empty($shopName)) {
        $error = "店铺名称不能为空";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO shops (user_id, shop_name, description, avatar_url, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([$_SESSION['user_id'], $shopName, $shopDesc, $shopLogo]);
            $message = "店铺 '" . htmlspecialchars($shopName) . "' 创建成功";
        } catch (Exception $e) {
            $error = "创建店铺失败：" . $e->getMessage();
        }
    }
}

// 处理上架商品
if (isset($_POST['create_product'])) {
    $shopId = intval($_POST['shop_id'] ?? 0);
    $categoryId = intval($_POST['category_id'] ?? 0);
    $productName = trim($_POST['product_name'] ?? '');
    $productDesc = trim($_POST['product_description'] ?? '');
    $priceBCT = intval($_POST['price_bct'] ?? 0);
    $stock = intval($_POST['stock'] ?? 999);
    $mainImage = trim($_POST['main_image'] ?? '');
    
    if (empty($productName) || $shopId <= 0 || $priceBCT <= 0) {
        $error = "请填写商品名称、选择店铺和价格";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (shop_id, category_id, name, description, main_image, price_type, price_bct, stock, status, created_at) VALUES (?, ?, ?, ?, ?, 'bct', ?, ?, 'active', NOW())");
            $stmt->execute([$shopId, $categoryId, $productName, $productDesc, $mainImage, $priceBCT, $stock]);
            $message = "商品 '" . htmlspecialchars($productName) . "' 上架成功";
        } catch (Exception $e) {
            $error = "上架商品失败：" . $e->getMessage();
        }
    }
}

// 获取已有店铺列表
$shops = $pdo->query("SELECT * FROM shops WHERE status = 'active' ORDER BY created_at DESC")->fetchAll();

// 获取已有分类列表
$categories = $pdo->query("SELECT * FROM product_categories WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>种子数据管理 - 58人气值商城</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .page-title { font-size: 24px; font-weight: bold; margin-bottom: 20px; }
        
        .message { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; }
        .message-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 20px; }
        .card-title { font-size: 18px; font-weight: bold; border-bottom: 2px solid #f0f0f0; padding-bottom: 12px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
        
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-size: 14px; color: #555; margin-bottom: 5px; font-weight: 500; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #3498db; }
        .form-textarea { resize: vertical; min-height: 80px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-warning:hover { background: #e67e22; }
        
        .hint { font-size: 13px; color: #999; margin-top: 5px; }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 8px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); flex: 1; text-align: center; }
        .stat-num { font-size: 28px; font-weight: bold; color: #3498db; }
        .stat-label { font-size: 13px; color: #666; margin-top: 4px; }
        
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .stats { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1 class="page-title"><i class="fas fa-tools"></i> 种子数据管理</h1>
    
    <?php if ($message): ?>
        <div class="message message-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message message-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- 数据统计 -->
    <?php
    $totalShops = $pdo->query("SELECT COUNT(*) FROM shops WHERE status = 'active'")->fetchColumn();
    $totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $totalCategories = $pdo->query("SELECT COUNT(*) FROM product_categories WHERE status = 'active'")->fetchColumn();
    ?>
    <div class="stats">
        <div class="stat-card">
            <div class="stat-num"><?php echo $totalCategories; ?></div>
            <div class="stat-label">商品分类</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?php echo $totalShops; ?></div>
            <div class="stat-label">活跃店铺</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?php echo $totalProducts; ?></div>
            <div class="stat-label">在售商品</div>
        </div>
    </div>
    
    <div class="grid-2">
        <!-- 分类初始化 -->
        <div class="card">
            <div class="card-title"><i class="fas fa-tags"></i> 初始化分类</div>
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">一键创建 8 个默认商品分类（已存在的会跳过）</p>
            <form method="POST">
                <button type="submit" name="init_categories" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> 初始化 8 个默认分类
                </button>
            </form>
            <?php if ($categories): ?>
                <div style="margin-top: 15px; font-size: 13px; color: #666;">
                    已有分类：<?php echo implode('、', array_map(function($c){ return $c['name']; }, $categories)); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 创建店铺 -->
        <div class="card">
            <div class="card-title"><i class="fas fa-store"></i> 创建店铺</div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">店铺名称 *</label>
                    <input type="text" name="shop_name" class="form-input" placeholder="例如：数字潮品店" required>
                </div>
                <div class="form-group">
                    <label class="form-label">店铺描述</label>
                    <input type="text" name="shop_description" class="form-input" placeholder="简短描述你的店铺">
                </div>
                <div class="form-group">
                    <label class="form-label">店铺Logo URL</label>
                    <input type="text" name="shop_logo" class="form-input" placeholder="https://... 或留空使用默认">
                    <div class="hint">可不填，留空使用默认Logo</div>
                </div>
                <button type="submit" name="create_shop" class="btn btn-success"><i class="fas fa-store-alt"></i> 创建店铺</button>
            </form>
        </div>
        
        <!-- 上架商品 -->
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-title"><i class="fas fa-box-open"></i> 上架商品</div>
            <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px;">
                <div class="form-group">
                    <label class="form-label">选择店铺 *</label>
                    <select name="shop_id" class="form-select" required>
                        <option value="">-- 选择店铺 --</option>
                        <?php foreach ($shops as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['shop_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($shops)): ?>
                        <div class="hint" style="color: #e74c3c;">请先创建店铺</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">选择分类</label>
                    <select name="category_id" class="form-select">
                        <option value="0">-- 无分类 --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">商品名称 *</label>
                    <input type="text" name="product_name" class="form-input" placeholder="例如：限量版数字艺术品" required>
                </div>
                <div class="form-group">
                    <label class="form-label">BCT价格 *</label>
                    <input type="number" name="price_bct" class="form-input" placeholder="例如：100" step="1" min="1" required>
                    <div class="hint">人气值(BCT)价格</div>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">商品描述</label>
                    <textarea name="product_description" class="form-textarea" placeholder="对商品进行详细描述..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">商品图片URL</label>
                    <input type="text" name="main_image" class="form-input" placeholder="https://... 或留空使用默认图">
                    <div class="hint">建议 800x800，支持外链图片</div>
                </div>
                <div class="form-group">
                    <label class="form-label">库存数量</label>
                    <input type="number" name="stock" class="form-input" value="999" min="1">
                </div>
                <div style="grid-column: 1 / -1;">
                    <button type="submit" name="create_product" class="btn btn-warning"><i class="fas fa-upload"></i> 上架商品</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
