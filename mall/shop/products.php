<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../includes/header.php';
require_once '../../classes/Shop.php';
require_once '../../classes/Product.php';
require_once '../../classes/Category.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$shop = new Shop($pdo);
$product = new Product($pdo);
$category = new Category($pdo);

// 获取用户店铺信息
$userShop = $shop->getShopByUserId($_SESSION['user_id']);
if (!$userShop) {
    header('Location: create.php');
    exit;
}

// 获取店铺ID
$shopId = isset($_GET['id']) ? intval($_GET['id']) : $userShop['id'];

// 验证用户是否有权限管理该店铺
if ($userShop['id'] != $shopId) {
    header('Location: products.php?id=' . $userShop['id']);
    exit;
}

// 获取操作类型
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// 获取商品分类
$categories = $category->getAllCategories();

$error = '';
$success = '';

// 处理添加商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $categoryId = intval($_POST['category_id']);
    $priceBct = floatval($_POST['price_bct']);
    $priceCny = floatval($_POST['price_cny']);
    $stock = intval($_POST['stock']);
    $status = $_POST['status'];
    
    // 基本验证
    if (empty($name)) {
        $error = '商品名称不能为空';
    } elseif (empty($description)) {
        $error = '商品描述不能为空';
    } elseif ($categoryId <= 0) {
        $error = '请选择商品分类';
    } elseif ($priceBct <= 0) {
        $error = '人气值价格必须大于0';
    } elseif ($stock < 0) {
        $error = '库存不能为负数';
    } else {
        try {
            // 处理图片上传
            $mainImage = '';
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadImage($_FILES['main_image']);
                if ($uploadResult['success']) {
                    $mainImage = $uploadResult['file_path'];
                } else {
                    $error = $uploadResult['error'];
                }
            }
            
            if (!$error) {
                // 准备商品数据
                $productData = [
                    'shop_id' => $shopId,
                    'category_id' => $categoryId,
                    'name' => $name,
                    'description' => $description,
                    'main_image' => $mainImage ?: 'assets/images/default-product.png',
                    'price_type' => 'fixed',
                    'price_bct' => $priceBct,
                    'price_cny' => $priceCny,
                    'stock' => $stock,
                    'status' => $status
                ];
                
                // 创建商品
                $productId = $product->createProduct($productData);
                
                if ($productId) {
                    $success = '商品添加成功！';
                    // 重定向到商品列表
                    header('Location: products.php?id=' . $shopId . '&success=' . urlencode($success));
                    exit;
                } else {
                    $error = '商品添加失败，请稍后重试';
                }
            }
        } catch (Exception $e) {
            $error = '系统错误：' . $e->getMessage();
        }
    }
}

// 处理编辑商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $productId = intval($_POST['product_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $categoryId = intval($_POST['category_id']);
    $priceBct = floatval($_POST['price_bct']);
    $priceCny = floatval($_POST['price_cny']);
    $stock = intval($_POST['stock']);
    $status = $_POST['status'];
    
    // 基本验证
    if (empty($name)) {
        $error = '商品名称不能为空';
    } elseif (empty($description)) {
        $error = '商品描述不能为空';
    } elseif ($categoryId <= 0) {
        $error = '请选择商品分类';
    } elseif ($priceBct <= 0) {
        $error = '人气值价格必须大于0';
    } elseif ($stock < 0) {
        $error = '库存不能为负数';
    } else {
        try {
            // 处理图片上传
            $mainImage = '';
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadImage($_FILES['main_image']);
                if ($uploadResult['success']) {
                    $mainImage = $uploadResult['file_path'];
                } else {
                    $error = $uploadResult['error'];
                }
            }
            
            if (!$error) {
                // 准备更新数据
                $updateData = [
                    'name' => $name,
                    'description' => $description,
                    'category_id' => $categoryId,
                    'price_bct' => $priceBct,
                    'price_cny' => $priceCny,
                    'stock' => $stock,
                    'status' => $status
                ];
                
                if ($mainImage) {
                    $updateData['main_image'] = $mainImage;
                }
                
                // 更新商品
                if ($product->updateProduct($productId, $updateData)) {
                    $success = '商品更新成功！';
                    // 重定向到商品列表
                    header('Location: products.php?id=' . $shopId . '&success=' . urlencode($success));
                    exit;
                } else {
                    $error = '商品更新失败，请稍后重试';
                }
            }
        } catch (Exception $e) {
            $error = '系统错误：' . $e->getMessage();
        }
    }
}

// 处理删除商品
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['product_id'])) {
    $productId = intval($_GET['product_id']);
    
    // 验证商品属于当前店铺
    $productInfo = $product->getProductById($productId);
    if ($productInfo && $productInfo['shop_id'] == $shopId) {
        if ($product->updateProduct($productId, ['status' => 'inactive'])) {
            $success = '商品已下架';
        } else {
            $error = '商品下架失败';
        }
    } else {
        $error = '商品不存在或无权操作';
    }
    
    header('Location: products.php?id=' . $shopId . '&' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
    exit;
}

// 处理上架商品
if (isset($_GET['action']) && $_GET['action'] === 'activate' && isset($_GET['product_id'])) {
    $productId = intval($_GET['product_id']);
    $productInfo = $product->getProductById($productId);
    if ($productInfo && $productInfo['shop_id'] == $shopId) {
        if ($product->updateProduct($productId, ['status' => 'active'])) {
            $success = '商品已上架';
        } else {
            $error = '商品上架失败';
        }
    } else {
        $error = '商品不存在或无权操作';
    }
    header('Location: products.php?id=' . $shopId . '&' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
    exit;
}

// 处理批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_action']) && !empty($_POST['product_ids'])) {
    $batchAction = $_POST['batch_action'];
    $productIds = array_map('intval', $_POST['product_ids']);

    if ($batchAction === 'activate') {
        $product->batchUpdateStatus($productIds, $shopId, 'active');
        $success = '已批量上架 ' . count($productIds) . ' 个商品';
    } elseif ($batchAction === 'deactivate') {
        $product->batchUpdateStatus($productIds, $shopId, 'inactive');
        $success = '已批量下架 ' . count($productIds) . ' 个商品';
    } elseif ($batchAction === 'delete') {
        $product->batchDeleteProducts($productIds, $shopId);
        $success = '已批量删除 ' . count($productIds) . ' 个商品';
    }

    header('Location: products.php?id=' . $shopId . '&success=' . urlencode($success));
    exit;
}

// 图片上传函数
function uploadImage($file) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => '只允许上传 JPG, PNG, GIF 格式的图片'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => '图片大小不能超过 5MB'];
    }
    
    // 创建上传目录
    $uploadDir = '../assets/uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 生成唯一文件名
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'file_path' => 'assets/uploads/products/' . $fileName];
    } else {
        return ['success' => false, 'error' => '图片上传失败'];
    }
}

// 显示成功消息
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <!-- 店铺管理侧边栏 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">店铺管理</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="manage.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> 店铺概览
                    </a>
                    <a href="products.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action <?= $action === 'list' ? 'active' : '' ?>">
                        <i class="fas fa-box"></i> 商品管理
                    </a>
                    <a href="products.php?action=add&id=<?= $shopId ?>" class="list-group-item list-group-item-action <?= $action === 'add' ? 'active' : '' ?>">
                        <i class="fas fa-plus"></i> 添加商品
                    </a>
                    <a href="orders.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-cart"></i> 订单管理
                    </a>
                    <a href="payment-settings.php?id=<?= $shopId ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog"></i> 支付设置
                    </a>
                </div>
            </div>
            
            <!-- 商品统计 -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6>商品统计</h6>
                    <?php
                    $productStats = $product->getShopProductStats($shopId);
                    ?>
                    <div class="small">
                        <div class="d-flex justify-content-between">
                            <span>总商品:</span>
                            <span class="text-primary"><?= $productStats['total_products'] ?? 0 ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>在售:</span>
                            <span class="text-success"><?= $productStats['active_products'] ?? 0 ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>售罄:</span>
                            <span class="text-warning"><?= $productStats['sold_out_products'] ?? 0 ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>总销量:</span>
                            <span class="text-info"><?= $productStats['total_sales'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- 添加/编辑商品表单 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0"><?= $action === 'add' ? '添加商品' : '编辑商品' ?></h4>
                        <a href="products.php?id=<?= $shopId ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> 返回列表
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" id="productForm">
                            <?php if ($action === 'edit' && isset($_GET['product_id'])): ?>
                                <?php
                                $editProductId = intval($_GET['product_id']);
                                $editProduct = $product->getProductById($editProductId);
                                if (!$editProduct || $editProduct['shop_id'] != $shopId) {
                                    echo '<div class="alert alert-danger">商品不存在或无权编辑</div>';
                                    require_once '../includes/footer.php';
                                    exit;
                                }
                                ?>
                                <input type="hidden" name="product_id" value="<?= $editProductId ?>">
                                <input type="hidden" name="action" value="edit">
                            <?php else: ?>
                                <input type="hidden" name="action" value="add">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="name">商品名称 *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= isset($editProduct) ? htmlspecialchars($editProduct['name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '') ?>" 
                                               required maxlength="200">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="description">商品描述 *</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="6" required><?= isset($editProduct) ? htmlspecialchars($editProduct['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '') ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="category_id">商品分类 *</label>
                                                <select class="form-control" id="category_id" name="category_id" required>
                                                    <option value="">请选择分类</option>
                                                    <?php foreach ($categories as $cat): ?>
                                                        <option value="<?= $cat['id'] ?>" 
                                                            <?= (isset($editProduct) && $editProduct['category_id'] == $cat['id']) || (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($cat['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="stock">库存数量 *</label>
                                                <input type="number" class="form-control" id="stock" name="stock" 
                                                       value="<?= isset($editProduct) ? $editProduct['stock'] : (isset($_POST['stock']) ? $_POST['stock'] : 0) ?>" 
                                                       min="0" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="price_bct">人气值价格 (BCT) *</label>
                                                <input type="number" class="form-control" id="price_bct" name="price_bct" 
                                                       value="<?= isset($editProduct) ? $editProduct['price_bct'] : (isset($_POST['price_bct']) ? $_POST['price_bct'] : '') ?>" 
                                                       step="0.01" min="0.01" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="price_cny">人民币价格 (¥)</label>
                                                <input type="number" class="form-control" id="price_cny" name="price_cny" 
                                                       value="<?= isset($editProduct) ? $editProduct['price_cny'] : (isset($_POST['price_cny']) ? $_POST['price_cny'] : '') ?>" 
                                                       step="0.01" min="0">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="status">商品状态 *</label>
                                        <select class="form-control" id="status" name="status" required>
                                            <option value="draft" <?= (isset($editProduct) && $editProduct['status'] == 'draft') || (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : '' ?>>草稿</option>
                                            <option value="active" <?= (isset($editProduct) && $editProduct['status'] == 'active') || (isset($_POST['status']) && $_POST['status'] == 'active') || !isset($editProduct) ? 'selected' : '' ?>>上架销售</option>
                                            <option value="inactive" <?= (isset($editProduct) && $editProduct['status'] == 'inactive') || (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : '' ?>>下架</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="main_image">商品主图</label>
                                        <div class="image-upload-container">
                                            <?php if (isset($editProduct) && !empty($editProduct['main_image'])): ?>
                                                <div class="current-image mb-3">
                                                    <img src="../<?= htmlspecialchars($editProduct['main_image']) ?>" 
                                                         alt="当前图片" class="img-fluid rounded" style="max-height: 200px;">
                                                    <div class="text-center mt-2">
                                                        <small class="text-muted">当前图片</small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="main_image" name="main_image" accept="image/*">
                                                <label class="custom-file-label" for="main_image">选择图片</label>
                                            </div>
                                            <small class="form-text text-muted">
                                                支持 JPG, PNG, GIF 格式，大小不超过 5MB
                                            </small>
                                            
                                            <div class="image-preview mt-3 d-none">
                                                <img id="imagePreview" src="#" alt="图片预览" class="img-fluid rounded" style="max-height: 200px;">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="is_recommended" name="is_recommended" value="1"
                                                <?= (isset($editProduct) && $editProduct['is_recommended']) || (isset($_POST['is_recommended']) && $_POST['is_recommended']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_recommended">推荐商品</label>
                                        </div>
                                        <small class="form-text text-muted">推荐商品会在首页展示</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> <?= $action === 'add' ? '添加商品' : '更新商品' ?>
                                </button>
                                <a href="products.php?id=<?= $shopId ?>" class="btn btn-secondary btn-lg">取消</a>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- 商品列表 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">商品管理</h4>
                        <a href="products.php?action=add&id=<?= $shopId ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> 添加商品
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        
                        <?php
                        // 筛选与搜索参数
                        $listFilter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
                        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

                        // 获取店铺商品列表（支持筛选和搜索）
                        $products = $product->getProductsByShopWithFilter($shopId, $listFilter, $search, 100);
                        ?>
                        
                        <?php if ($products): ?>
                            <!-- 筛选与批量操作栏 -->
                            <div class="product-toolbar">
                                <div class="toolbar-left">
                                    <label class="check-all-wrap">
                                        <input type="checkbox" id="checkAll" onchange="toggleAll(this)">
                                        <span>全选</span>
                                    </label>
                                    <div class="batch-actions" id="batchActions" style="display:none;">
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="batchAction('activate')">
                                            <i class="fas fa-play"></i> 批量上架
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="batchAction('deactivate')">
                                            <i class="fas fa-pause"></i> 批量下架
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="batchAction('delete')">
                                            <i class="fas fa-trash"></i> 批量删除
                                        </button>
                                    </div>
                                </div>
                                <div class="toolbar-right">
                                    <div class="filter-tabs">
                                        <a href="?id=<?= $shopId ?>" class="filter-tab <?= $listFilter == 'all' ? 'active' : '' ?>">全部</a>
                                        <a href="?id=<?= $shopId ?>&filter=active" class="filter-tab <?= $listFilter == 'active' ? 'active' : '' ?>">在售</a>
                                        <a href="?id=<?= $shopId ?>&filter=inactive" class="filter-tab <?= $listFilter == 'inactive' ? 'active' : '' ?>">下架</a>
                                        <a href="?id=<?= $shopId ?>&filter=draft" class="filter-tab <?= $listFilter == 'draft' ? 'active' : '' ?>">草稿</a>
                                    </div>
                                    <div class="search-box-sm">
                                        <input type="text" id="productSearch" placeholder="搜索商品名称..." value="<?= htmlspecialchars($search) ?>">
                                        <button onclick="doSearch()"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                            </div>

                            <form id="batchForm" method="POST" action="products.php?id=<?= $shopId ?>">
                                <input type="hidden" name="batch_action" id="batchActionInput" value="">
                                <div class="product-grid">
                                    <?php foreach ($products as $productItem): ?>
                                        <div class="product-card" data-status="<?= $productItem['status'] ?>" data-name="<?= htmlspecialchars($productItem['name']) ?>">
                                            <div class="product-select">
                                                <input type="checkbox" name="product_ids[]" value="<?= $productItem['id'] ?>" class="product-checkbox">
                                            </div>
                                            <div class="product-image-wrap">
                                                <img src="../<?= htmlspecialchars($productItem['main_image']) ?>" alt="">
                                                <?php if ($productItem['is_recommended']): ?>
                                                    <span class="product-badge">推荐</span>
                                                <?php endif; ?>
                                                <div class="product-overlay">
                                                    <a href="../product/detail.php?id=<?= $productItem['id'] ?>" target="_blank" class="overlay-btn" title="查看">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="products.php?action=edit&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" class="overlay-btn" title="编辑">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="product-body">
                                                <h4 class="product-title"><?= htmlspecialchars($productItem['name']) ?></h4>
                                                <div class="product-prices">
                                                    <span class="price-bct"><?= number_format($productItem['price_bct'], 2) ?> BCT</span>
                                                    <?php if ($productItem['price_cny']): ?>
                                                        <span class="price-cny">¥<?= number_format($productItem['price_cny'], 2) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="product-metrics">
                                                    <span>库存 <?= $productItem['stock'] ?></span>
                                                    <span>已售 <?= $productItem['sold_count'] ?></span>
                                                </div>
                                                <div class="product-footer">
                                                    <span class="status-pill status-<?= $productItem['status'] ?>">
                                                        <?= $productItem['status'] == 'active' ? '在售' : ($productItem['status'] == 'draft' ? '草稿' : ($productItem['status'] == 'sold_out' ? '售罄' : '下架')) ?>
                                                    </span>
                                                    <div class="quick-actions">
                                                        <?php if ($productItem['status'] == 'active'): ?>
                                                            <a href="products.php?action=delete&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" class="qa-btn qa-warning" title="下架" onclick="return confirm('下架此商品？')">
                                                                <i class="fas fa-pause"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="products.php?action=activate&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" class="qa-btn qa-success" title="上架" onclick="return confirm('上架此商品？')">
                                                                <i class="fas fa-play"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">暂无商品</h5>
                                <p class="text-muted">您还没有添加任何商品</p>
                                <a href="products.php?action=add&id=<?= $shopId ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> 添加第一个商品
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* 商品卡片网格 */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
    margin-top: 12px;
}
.product-card {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    overflow: hidden;
    transition: all 0.2s;
    position: relative;
}
.product-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.product-select {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 3;
}
.product-select input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #ff6b00;
}
.product-image-wrap {
    position: relative;
    width: 100%;
    height: 160px;
    background: #f8f9fa;
    overflow: hidden;
}
.product-image-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.product-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #28a745;
    color: #fff;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    z-index: 2;
}
.product-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    opacity: 0;
    transition: opacity 0.2s;
    z-index: 2;
}
.product-card:hover .product-overlay {
    opacity: 1;
}
.overlay-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #fff;
    color: #333;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s;
}
.overlay-btn:hover {
    background: #ff6b00;
    color: #fff;
}
.product-body {
    padding: 12px;
}
.product-title {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 6px;
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    min-height: 40px;
}
.product-prices {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 6px;
}
.price-bct {
    font-size: 15px;
    font-weight: 700;
    color: #ff6b00;
}
.price-cny {
    font-size: 12px;
    color: #6b7280;
}
.product-metrics {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 10px;
}
.product-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.status-pill {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 12px;
    font-weight: 500;
}
.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.status-draft { background: #e2e3e5; color: #383d41; }
.status-sold_out { background: #fff3cd; color: #856404; }
.quick-actions {
    display: flex;
    gap: 4px;
}
.qa-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    text-decoration: none;
    transition: all 0.15s;
}
.qa-warning { background: #fff3cd; color: #856404; }
.qa-warning:hover { background: #ffc107; color: #000; }
.qa-success { background: #d4edda; color: #155724; }
.qa-success:hover { background: #28a745; color: #fff; }

/* 工具栏 */
.product-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 8px;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}
.toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.check-all-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #495057;
    cursor: pointer;
    user-select: none;
}
.batch-actions {
    display: flex;
    gap: 6px;
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.filter-tabs {
    display: flex;
    gap: 2px;
    background: #f1f3f5;
    border-radius: 8px;
    padding: 3px;
}
.filter-tab {
    padding: 5px 14px;
    border-radius: 6px;
    font-size: 13px;
    color: #6c757d;
    text-decoration: none;
    transition: all 0.15s;
}
.filter-tab:hover {
    color: #495057;
    background: rgba(255,255,255,0.6);
}
.filter-tab.active {
    background: #fff;
    color: #ff6b00;
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.search-box-sm {
    display: flex;
    border: 1px solid #ced4da;
    border-radius: 8px;
    overflow: hidden;
    min-width: 200px;
}
.search-box-sm input {
    border: none;
    outline: none;
    padding: 6px 10px;
    font-size: 13px;
    flex: 1;
}
.search-box-sm button {
    border: none;
    background: #f8f9fa;
    padding: 0 10px;
    color: #6c757d;
    cursor: pointer;
}
.search-box-sm button:hover {
    background: #e9ecef;
}

/* 图片上传 */
.image-upload-container {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}
</style>

<script>
// 图片预览功能
document.getElementById('main_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    const previewContainer = document.querySelector('.image-preview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.classList.remove('d-none');
        }
        reader.readAsDataURL(file);
        
        // 更新文件输入标签
        const fileName = document.querySelector('.custom-file-label');
        fileName.textContent = file.name;
    }
});

// 表单验证
document.getElementById('productForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const description = document.getElementById('description').value.trim();
    const categoryId = document.getElementById('category_id').value;
    const priceBct = document.getElementById('price_bct').value;
    const stock = document.getElementById('stock').value;
    
    if (!name) {
        alert('请输入商品名称');
        e.preventDefault();
        return;
    }
    
    if (!description) {
        alert('请输入商品描述');
        e.preventDefault();
        return;
    }
    
    if (!categoryId) {
        alert('请选择商品分类');
        e.preventDefault();
        return;
    }
    
    if (!priceBct || parseFloat(priceBct) <= 0) {
        alert('请输入有效的人气值价格');
        e.preventDefault();
        return;
    }
    
    if (stock < 0) {
        alert('库存不能为负数');
        e.preventDefault();
        return;
    }
});

/* ========== 商品列表批量操作与搜索 ========== */
function toggleAll(checkbox) {
    const boxes = document.querySelectorAll('.product-checkbox');
    boxes.forEach(b => b.checked = checkbox.checked);
    updateBatchActions();
}

function updateBatchActions() {
    const checked = document.querySelectorAll('.product-checkbox:checked');
    const batchActions = document.getElementById('batchActions');
    batchActions.style.display = checked.length > 0 ? 'flex' : 'none';
}

function batchAction(action) {
    const checked = document.querySelectorAll('.product-checkbox:checked');
    if (checked.length === 0) {
        alert('请至少选择一个商品');
        return;
    }
    const labels = { activate: '上架', deactivate: '下架', delete: '删除' };
    if (!confirm('确定要批量' + labels[action] + ' ' + checked.length + ' 个商品吗？')) return;
    document.getElementById('batchActionInput').value = action;
    document.getElementById('batchForm').submit();
}

function doSearch() {
    const keyword = document.getElementById('productSearch').value.trim();
    const url = new URL(window.location.href);
    if (keyword) {
        url.searchParams.set('search', keyword);
    } else {
        url.searchParams.delete('search');
    }
    window.location.href = url.toString();
}

// 监听各商品复选框变化
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('product-checkbox')) {
        updateBatchActions();
    }
});

// 搜索框回车触发
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') doSearch();
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>