<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// 检查管理员权限
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
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
    $priceBCT = floatval($_POST['price_bct'] ?? 0);
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

$shops = $pdo->query("SELECT * FROM shops WHERE status = 'active' ORDER BY created_at DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM product_categories WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();

$totalShops = $pdo->query("SELECT COUNT(*) FROM shops WHERE status = 'active'")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM product_categories WHERE status = 'active'")->fetchColumn();

// 统一后台框架
$admin_site_config = ['site' => 'mall', 'page_title' => '数据种子'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if ($message): ?>
<div class="admin-card" style="border-left:4px solid #22c55e; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#86efac;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="admin-card" style="border-left:4px solid #ef4444; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#fca5a5;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
</div>
<?php endif; ?>

<!-- 统计 -->
<div class="admin-stats-grid" style="margin-bottom:20px;">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-tags"></i></div>
        <div class="stat-value"><?= $totalCategories ?></div>
        <div class="stat-label">商品分类</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-store"></i></div>
        <div class="stat-value"><?= $totalShops ?></div>
        <div class="stat-label">活跃店铺</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-box"></i></div>
        <div class="stat-value"><?= $totalProducts ?></div>
        <div class="stat-label">在售商品</div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
    <!-- 初始化分类 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-tags"></i> 初始化分类</span>
        </div>
        <div class="admin-card-body">
            <p style="color:#94a3b8;font-size:13px;margin-bottom:14px;">一键创建 8 个默认商品分类（已存在的会跳过）</p>
            <form method="POST">
                <button type="submit" name="init_categories" class="admin-btn admin-btn-primary">
                    <i class="fas fa-plus-circle"></i> 初始化 8 个默认分类
                </button>
            </form>
            <?php if ($categories): ?>
                <div style="margin-top:12px;font-size:12px;color:#64748b;">
                    已有分类：<?= implode('、', array_map(function($c){ return $c['name']; }, $categories)) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 创建店铺 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-store"></i> 创建店铺</span>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">店铺名称 *</label>
                    <input type="text" name="shop_name" placeholder="例如：数字潮品店" required
                           style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">店铺描述</label>
                    <input type="text" name="shop_description" placeholder="简短描述你的店铺"
                           style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">店铺Logo URL</label>
                    <input type="text" name="shop_logo" placeholder="https://... 或留空使用默认"
                           style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                </div>
                <button type="submit" name="create_shop" class="admin-btn" style="background:#14532d;color:#86efac;">
                    <i class="fas fa-store-alt"></i> 创建店铺
                </button>
            </form>
        </div>
    </div>

    <!-- 上架商品 -->
    <div class="admin-card" style="grid-column:1/-1;">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-box-open"></i> 上架商品</span>
        </div>
        <div class="admin-card-body">
            <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">选择店铺 *</label>
                    <select name="shop_id" required style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                        <option value="">-- 选择店铺 --</option>
                        <?php foreach ($shops as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['shop_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($shops)): ?>
                        <div style="font-size:12px;color:#ef4444;margin-top:4px;">请先创建店铺</div>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">选择分类</label>
                    <select name="category_id" style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                        <option value="0">-- 无分类 --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">商品名称 *</label>
                    <input type="text" name="product_name" placeholder="例如：限量版数字艺术品" required
                           style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">BCT价格 *</label>
                    <input type="number" name="price_bct" placeholder="例如：100" step="0.01" min="0.01" required
                           style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">商品描述</label>
                    <textarea name="product_description" placeholder="对商品进行详细描述..." rows="3"
                              style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;resize:vertical;"></textarea>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">商品图片URL</label>
                    <input type="text" name="main_image" placeholder="https://... 或留空使用默认图"
                           style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:4px;">库存数量</label>
                    <input type="number" name="stock" value="999" min="1"
                           style="width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                </div>
                <div style="grid-column:1/-1;">
                    <button type="submit" name="create_product" class="admin-btn" style="background:#78350f;color:#fde68a;">
                        <i class="fas fa-upload"></i> 上架商品
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
