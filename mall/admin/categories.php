<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../includes/header.php';

// 检查管理员权限
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../classes/Category.php';

$category = new Category($pdo);

$error = '';
$success = '';

// 处理添加分类
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_category') {
        $name = trim($_POST['name']);
        $parentId = intval($_POST['parent_id']);
        $sortOrder = intval($_POST['sort_order']);
        $status = $_POST['status'];
        
        if (empty($name)) {
            $error = '分类名称不能为空';
        } else {
            try {
                // 检查分类名称是否已存在
                $checkStmt = $pdo->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
                $checkStmt->execute([$name, $parentId]);
                if ($checkStmt->fetch()) {
                    $error = '该分类名称已存在';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_categories 
                        (name, parent_id, sort_order, status) 
                        VALUES (?, ?, ?, ?)
                    ");
                    if ($stmt->execute([$name, $parentId, $sortOrder, $status])) {
                        $success = '分类添加成功';
                    } else {
                        $error = '分类添加失败';
                    }
                }
            } catch (Exception $e) {
                $error = '系统错误：' . $e->getMessage();
            }
        }
    }
    
    // 处理编辑分类
    elseif ($_POST['action'] === 'edit_category') {
        $categoryId = intval($_POST['category_id']);
        $name = trim($_POST['name']);
        $parentId = intval($_POST['parent_id']);
        $sortOrder = intval($_POST['sort_order']);
        $status = $_POST['status'];
        
        if (empty($name)) {
            $error = '分类名称不能为空';
        } else {
            try {
                // 检查分类名称是否已存在（排除自己）
                $checkStmt = $pdo->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ? AND id != ?");
                $checkStmt->execute([$name, $parentId, $categoryId]);
                if ($checkStmt->fetch()) {
                    $error = '该分类名称已存在';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE product_categories 
                        SET name = ?, parent_id = ?, sort_order = ?, status = ? 
                        WHERE id = ?
                    ");
                    if ($stmt->execute([$name, $parentId, $sortOrder, $status, $categoryId])) {
                        $success = '分类更新成功';
                    } else {
                        $error = '分类更新失败';
                    }
                }
            } catch (Exception $e) {
                $error = '系统错误：' . $e->getMessage();
            }
        }
    }
    
    // 处理删除分类
    elseif ($_POST['action'] === 'delete_category') {
        $categoryId = intval($_POST['category_id']);
        
        try {
            // 检查是否有子分类
            $childCheck = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE parent_id = ?");
            $childCheck->execute([$categoryId]);
            $childCount = $childCheck->fetchColumn();
            
            // 检查是否有商品使用该分类
            $productCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $productCheck->execute([$categoryId]);
            $productCount = $productCheck->fetchColumn();
            
            if ($childCount > 0) {
                $error = '该分类下有子分类，无法删除';
            } elseif ($productCount > 0) {
                $error = '该分类下有商品，无法删除';
            } else {
                $stmt = $pdo->prepare("DELETE FROM product_categories WHERE id = ?");
                if ($stmt->execute([$categoryId])) {
                    $success = '分类删除成功';
                } else {
                    $error = '分类删除失败';
                }
            }
        } catch (Exception $e) {
            $error = '系统错误：' . $e->getMessage();
        }
    }
    
    // 处理初始化分类
    elseif ($_POST['action'] === 'init_categories') {
        if (initializeCategories()) {
            $success = '分类初始化成功';
        } else {
            $error = '分类初始化失败';
        }
    }
}

// 获取所有分类（树形结构）
function getCategoryTree($pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM product_categories 
        ORDER BY parent_id, sort_order, name
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $tree = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == 0) {
            $tree[$category['id']] = $category;
            $tree[$category['id']]['children'] = [];
        }
    }
    
    foreach ($categories as $category) {
        if ($category['parent_id'] != 0 && isset($tree[$category['parent_id']])) {
            $tree[$category['parent_id']]['children'][] = $category;
        }
    }
    
    return $tree;
}

$categoryTree = getCategoryTree($pdo);

// 获取顶级分类（用于下拉选择）
$topLevelCategories = $category->getTopLevelCategories();

// 初始化分类数据函数
function initializeCategories() {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 清空现有分类
        $pdo->exec("DELETE FROM product_categories");
        
        // 初始化分类数据
        $categories = [
            // 顶级分类
            ['name' => '数码电子', 'parent_id' => 0, 'sort_order' => 10],
            ['name' => '服装鞋帽', 'parent_id' => 0, 'sort_order' => 20],
            ['name' => '家居百货', 'parent_id' => 0, 'sort_order' => 30],
            ['name' => '美妆个护', 'parent_id' => 0, 'sort_order' => 40],
            ['name' => '食品生鲜', 'parent_id' => 0, 'sort_order' => 50],
            ['name' => '图书文娱', 'parent_id' => 0, 'sort_order' => 60],
            ['name' => '运动户外', 'parent_id' => 0, 'sort_order' => 70],
            ['name' => '虚拟商品', 'parent_id' => 0, 'sort_order' => 80],
            
            // 数码电子 - 子分类
            ['name' => '手机通讯', 'parent_id' => 1, 'sort_order' => 11],
            ['name' => '电脑办公', 'parent_id' => 1, 'sort_order' => 12],
            ['name' => '摄影摄像', 'parent_id' => 1, 'sort_order' => 13],
            ['name' => '智能设备', 'parent_id' => 1, 'sort_order' => 14],
            ['name' => '影音娱乐', 'parent_id' => 1, 'sort_order' => 15],
            
            // 服装鞋帽 - 子分类
            ['name' => '男装', 'parent_id' => 2, 'sort_order' => 21],
            ['name' => '女装', 'parent_id' => 2, 'sort_order' => 22],
            ['name' => '鞋类', 'parent_id' => 2, 'sort_order' => 23],
            ['name' => '配饰', 'parent_id' => 2, 'sort_order' => 24],
            
            // 家居百货 - 子分类
            ['name' => '厨房用品', 'parent_id' => 3, 'sort_order' => 31],
            ['name' => '家纺家饰', 'parent_id' => 3, 'sort_order' => 32],
            ['name' => '家具', 'parent_id' => 3, 'sort_order' => 33],
            ['name' => '日用百货', 'parent_id' => 3, 'sort_order' => 34],
            
            // 美妆个护 - 子分类
            ['name' => '护肤', 'parent_id' => 4, 'sort_order' => 41],
            ['name' => '彩妆', 'parent_id' => 4, 'sort_order' => 42],
            ['name' => '香水', 'parent_id' => 4, 'sort_order' => 43],
            ['name' => '个人护理', 'parent_id' => 4, 'sort_order' => 44],
            
            // 食品生鲜 - 子分类
            ['name' => '休闲零食', 'parent_id' => 5, 'sort_order' => 51],
            ['name' => '生鲜果蔬', 'parent_id' => 5, 'sort_order' => 52],
            ['name' => '酒水饮料', 'parent_id' => 5, 'sort_order' => 53],
            ['name' => '粮油调味', 'parent_id' => 5, 'sort_order' => 54],
            
            // 图书文娱 - 子分类
            ['name' => '图书', 'parent_id' => 6, 'sort_order' => 61],
            ['name' => '文具', 'parent_id' => 6, 'sort_order' => 62],
            ['name' => '音像制品', 'parent_id' => 6, 'sort_order' => 63],
            ['name' => '游戏', 'parent_id' => 6, 'sort_order' => 64],
            
            // 运动户外 - 子分类
            ['name' => '运动服饰', 'parent_id' => 7, 'sort_order' => 71],
            ['name' => '健身器材', 'parent_id' => 7, 'sort_order' => 72],
            ['name' => '户外装备', 'parent_id' => 7, 'sort_order' => 73],
            ['name' => '体育用品', 'parent_id' => 7, 'sort_order' => 74],
            
            // 虚拟商品 - 子分类
            ['name' => '游戏点卡', 'parent_id' => 8, 'sort_order' => 81],
            ['name' => '会员服务', 'parent_id' => 8, 'sort_order' => 82],
            ['name' => '软件服务', 'parent_id' => 8, 'sort_order' => 83],
            ['name' => '在线课程', 'parent_id' => 8, 'sort_order' => 84],
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO product_categories 
            (name, parent_id, sort_order, status) 
            VALUES (?, ?, ?, 'active')
        ");
        
        foreach ($categories as $cat) {
            $stmt->execute([$cat['name'], $cat['parent_id'], $cat['sort_order']]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-2">
            <!-- 管理侧边栏 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">管理后台</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> 仪表板
                    </a>
                    <a href="shops.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store"></i> 店铺管理
                    </a>
                    <a href="products.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-box"></i> 商品管理
                    </a>
                    <a href="categories.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tags"></i> 分类管理
                    </a>
                    <a href="orders.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-cart"></i> 订单管理
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users"></i> 用户管理
                    </a>
                </div>
            </div>
            
            <!-- 分类统计 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">分类统计</h6>
                </div>
                <div class="card-body">
                    <?php
                    $statsStmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_categories,
                            COUNT(CASE WHEN parent_id = 0 THEN 1 END) as top_level_categories,
                            COUNT(CASE WHEN parent_id > 0 THEN 1 END) as sub_categories
                        FROM product_categories
                    ");
                    $statsStmt->execute();
                    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="small">
                        <div class="d-flex justify-content-between">
                            <span>总分类:</span>
                            <span class="text-primary"><?= $stats['total_categories'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>顶级分类:</span>
                            <span class="text-success"><?= $stats['top_level_categories'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>子分类:</span>
                            <span class="text-info"><?= $stats['sub_categories'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 初始化分类 -->
            <div class="card mt-3">
                <div class="card-body">
                    <form method="POST" onsubmit="return confirm('确定要初始化分类吗？这将清空现有分类数据！')">
                        <input type="hidden" name="action" value="init_categories">
                        <button type="submit" class="btn btn-warning btn-sm btn-block">
                            <i class="fas fa-sync"></i> 初始化分类
                        </button>
                        <small class="text-muted d-block mt-1">一键创建常用商品分类</small>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>分类管理</h2>
            </div>
            
            <!-- 消息提示 -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- 添加分类表单 -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">添加分类</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_category">
                                
                                <div class="form-group">
                                    <label for="name">分类名称 *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" 
                                           required maxlength="50">
                                </div>
                                
                                <div class="form-group">
                                    <label for="parent_id">父级分类</label>
                                    <select class="form-control" id="parent_id" name="parent_id">
                                        <option value="0">作为顶级分类</option>
                                        <?php foreach ($topLevelCategories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" 
                                                <?= (isset($_POST['parent_id']) && $_POST['parent_id'] == $cat['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sort_order">排序顺序</label>
                                    <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                           value="<?= isset($_POST['sort_order']) ? $_POST['sort_order'] : 0 ?>" 
                                           min="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">状态</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" <?= (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : '' ?>>激活</option>
                                        <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : '' ?>>禁用</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-plus"></i> 添加分类
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- 分类列表 -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">分类列表</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($categoryTree): ?>
                                <div class="category-tree">
                                    <?php foreach ($categoryTree as $category): ?>
                                        <div class="category-item mb-3">
                                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                                <div>
                                                    <strong><?= htmlspecialchars($category['name']) ?></strong>
                                                    <span class="badge badge-primary ml-2">顶级分类</span>
                                                    <span class="badge badge-<?= $category['status'] == 'active' ? 'success' : 'secondary' ?> ml-1">
                                                        <?= $category['status'] == 'active' ? '激活' : '禁用' ?>
                                                    </span>
                                                    <small class="text-muted ml-2">排序: <?= $category['sort_order'] ?></small>
                                                </div>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-secondary" 
                                                            onclick="editCategory(<?= $category['id'] ?>, '<?= addslashes($category['name']) ?>', <?= $category['parent_id'] ?>, <?= $category['sort_order'] ?>, '<?= $category['status'] ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('确定要删除这个分类吗？')">
                                                        <input type="hidden" name="action" value="delete_category">
                                                        <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($category['children'])): ?>
                                                <div class="children-categories ml-4 mt-2">
                                                    <?php foreach ($category['children'] as $child): ?>
                                                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded">
                                                            <div>
                                                                <span class="ml-2"><?= htmlspecialchars($child['name']) ?></span>
                                                                <span class="badge badge-<?= $child['status'] == 'active' ? 'success' : 'secondary' ?> ml-2">
                                                                    <?= $child['status'] == 'active' ? '激活' : '禁用' ?>
                                                                </span>
                                                                <small class="text-muted ml-2">排序: <?= $child['sort_order'] ?></small>
                                                            </div>
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                                        onclick="editCategory(<?= $child['id'] ?>, '<?= addslashes($child['name']) ?>', <?= $child['parent_id'] ?>, <?= $child['sort_order'] ?>, '<?= $child['status'] ?>')">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('确定要删除这个分类吗？')">
                                                                    <input type="hidden" name="action" value="delete_category">
                                                                    <input type="hidden" name="category_id" value="<?= $child['id'] ?>">
                                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">暂无分类</h5>
                                    <p class="text-muted">请添加商品分类或初始化分类数据</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 编辑分类模态框 -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">编辑分类</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" id="editCategoryForm">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name">分类名称 *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required maxlength="50">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_parent_id">父级分类</label>
                        <select class="form-control" id="edit_parent_id" name="parent_id">
                            <option value="0">作为顶级分类</option>
                            <?php foreach ($topLevelCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_sort_order">排序顺序</label>
                        <input type="number" class="form-control" id="edit_sort_order" name="sort_order" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">状态</label>
                        <select class="form-control" id="edit_status" name="status">
                            <option value="active">激活</option>
                            <option value="inactive">禁用</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">保存更改</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.category-tree {
    max-height: 600px;
    overflow-y: auto;
}
.category-item {
    border-left: 3px solid #007bff;
    padding-left: 10px;
}
.children-categories {
    border-left: 2px dashed #dee2e6;
}
.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
}
</style>

<script>
function editCategory(id, name, parentId, sortOrder, status) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_parent_id').value = parentId;
    document.getElementById('edit_sort_order').value = sortOrder;
    document.getElementById('edit_status').value = status;
    
    $('#editCategoryModal').modal('show');
}

// 表单验证
document.addEventListener('DOMContentLoaded', function() {
    const addForm = document.querySelector('form[action="add_category"]');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            if (!name) {
                alert('请输入分类名称');
                e.preventDefault();
                return;
            }
        });
    }
    
    const editForm = document.getElementById('editCategoryForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const name = document.getElementById('edit_name').value.trim();
            if (!name) {
                alert('请输入分类名称');
                e.preventDefault();
                return;
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>