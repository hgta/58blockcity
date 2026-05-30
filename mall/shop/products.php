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
                        // 获取店铺商品列表
                        $products = $product->getProductsByShop($shopId, 50);
                        ?>
                        
                        <?php if ($products): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>图片</th>
                                            <th>商品信息</th>
                                            <th>价格</th>
                                            <th>库存/销量</th>
                                            <th>状态</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $productItem): ?>
                                            <tr>
                                                <td>
                                                    <img src="../<?= htmlspecialchars($productItem['main_image']) ?>" 
                                                         alt="<?= htmlspecialchars($productItem['name']) ?>" 
                                                         class="product-thumb" style="width: 60px; height: 60px; object-fit: cover;">
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($productItem['name']) ?></strong>
                                                        <?php if ($productItem['is_recommended']): ?>
                                                            <span class="badge badge-success badge-sm ml-1">推荐</span>
                                                        <?php endif; ?>
                                                        <div class="text-muted small mt-1">
                                                            <?= htmlspecialchars(mb_substr($productItem['description'], 0, 50)) ?>...
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div class="text-primary"><?= number_format($productItem['price_bct'], 2) ?> BCT</div>
                                                        <?php if ($productItem['price_cny']): ?>
                                                            <div class="text-muted">¥<?= number_format($productItem['price_cny'], 2) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div>库存: <?= $productItem['stock'] ?></div>
                                                        <div>销量: <?= $productItem['sold_count'] ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= 
                                                        $productItem['status'] == 'active' ? 'success' : 
                                                        ($productItem['status'] == 'draft' ? 'secondary' : 
                                                        ($productItem['status'] == 'sold_out' ? 'warning' : 'danger'))
                                                    ?>">
                                                        <?= $productItem['status'] == 'active' ? '在售' : 
                                                            ($productItem['status'] == 'draft' ? '草稿' : 
                                                            ($productItem['status'] == 'sold_out' ? '售罄' : '下架'))
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="../product/detail.php?id=<?= $productItem['id'] ?>" 
                                                           class="btn btn-outline-primary" target="_blank" title="查看">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="products.php?action=edit&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" 
                                                           class="btn btn-outline-secondary" title="编辑">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($productItem['status'] == 'active'): ?>
                                                            <a href="products.php?action=delete&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" 
                                                               class="btn btn-outline-warning" title="下架" onclick="return confirm('确定要下架这个商品吗？')">
                                                                <i class="fas fa-pause"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="products.php?action=activate&id=<?= $shopId ?>&product_id=<?= $productItem['id'] ?>" 
                                                               class="btn btn-outline-success" title="上架" onclick="return confirm('确定要上架这个商品吗？')">
                                                                <i class="fas fa-play"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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
.product-thumb {
    border-radius: 4px;
    border: 1px solid #dee2e6;
}
.badge-sm {
    font-size: 0.7em;
    padding: 0.25em 0.4em;
}
.table td {
    vertical-align: middle;
}
.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
}
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
</script>

<?php require_once '../includes/footer.php'; ?>