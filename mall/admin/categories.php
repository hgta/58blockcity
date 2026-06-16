<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// 检查管理员权限
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../classes/Category.php';

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
                $checkStmt = $pdo->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
                $checkStmt->execute([$name, $parentId]);
                if ($checkStmt->fetch()) {
                    $error = '该分类名称已存在';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO product_categories (name, parent_id, sort_order, status) VALUES (?, ?, ?, ?)");
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
                $checkStmt = $pdo->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ? AND id != ?");
                $checkStmt->execute([$name, $parentId, $categoryId]);
                if ($checkStmt->fetch()) {
                    $error = '该分类名称已存在';
                } else {
                    $stmt = $pdo->prepare("UPDATE product_categories SET name = ?, parent_id = ?, sort_order = ?, status = ? WHERE id = ?");
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
    elseif ($_POST['action'] === 'delete_category') {
        $categoryId = intval($_POST['category_id']);
        try {
            $childCheck = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE parent_id = ?");
            $childCheck->execute([$categoryId]);
            $childCount = $childCheck->fetchColumn();

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
    $stmt = $pdo->prepare("SELECT * FROM product_categories ORDER BY parent_id, sort_order, name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tree = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == 0) {
            $tree[$cat['id']] = $cat;
            $tree[$cat['id']]['children'] = [];
        }
    }
    foreach ($categories as $cat) {
        if ($cat['parent_id'] != 0 && isset($tree[$cat['parent_id']])) {
            $tree[$cat['parent_id']]['children'][] = $cat;
        }
    }
    return $tree;
}

$categoryTree = getCategoryTree($pdo);
$topLevelCategories = $category->getTopLevelCategories();

function initializeCategories() {
    global $pdo;
    try {
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM product_categories");

        $categories = [
            ['name' => '数码电子', 'parent_id' => 0, 'sort_order' => 10],
            ['name' => '服装鞋帽', 'parent_id' => 0, 'sort_order' => 20],
            ['name' => '家居百货', 'parent_id' => 0, 'sort_order' => 30],
            ['name' => '美妆个护', 'parent_id' => 0, 'sort_order' => 40],
            ['name' => '食品生鲜', 'parent_id' => 0, 'sort_order' => 50],
            ['name' => '图书文娱', 'parent_id' => 0, 'sort_order' => 60],
            ['name' => '运动户外', 'parent_id' => 0, 'sort_order' => 70],
            ['name' => '虚拟商品', 'parent_id' => 0, 'sort_order' => 80],
            ['name' => '手机通讯', 'parent_id' => 1, 'sort_order' => 11],
            ['name' => '电脑办公', 'parent_id' => 1, 'sort_order' => 12],
            ['name' => '摄影摄像', 'parent_id' => 1, 'sort_order' => 13],
            ['name' => '智能设备', 'parent_id' => 1, 'sort_order' => 14],
            ['name' => '影音娱乐', 'parent_id' => 1, 'sort_order' => 15],
            ['name' => '男装', 'parent_id' => 2, 'sort_order' => 21],
            ['name' => '女装', 'parent_id' => 2, 'sort_order' => 22],
            ['name' => '鞋类', 'parent_id' => 2, 'sort_order' => 23],
            ['name' => '配饰', 'parent_id' => 2, 'sort_order' => 24],
            ['name' => '厨房用品', 'parent_id' => 3, 'sort_order' => 31],
            ['name' => '家纺家饰', 'parent_id' => 3, 'sort_order' => 32],
            ['name' => '家具', 'parent_id' => 3, 'sort_order' => 33],
            ['name' => '日用百货', 'parent_id' => 3, 'sort_order' => 34],
            ['name' => '护肤', 'parent_id' => 4, 'sort_order' => 41],
            ['name' => '彩妆', 'parent_id' => 4, 'sort_order' => 42],
            ['name' => '香水', 'parent_id' => 4, 'sort_order' => 43],
            ['name' => '个人护理', 'parent_id' => 4, 'sort_order' => 44],
            ['name' => '休闲零食', 'parent_id' => 5, 'sort_order' => 51],
            ['name' => '生鲜果蔬', 'parent_id' => 5, 'sort_order' => 52],
            ['name' => '酒水饮料', 'parent_id' => 5, 'sort_order' => 53],
            ['name' => '粮油调味', 'parent_id' => 5, 'sort_order' => 54],
            ['name' => '图书', 'parent_id' => 6, 'sort_order' => 61],
            ['name' => '文具', 'parent_id' => 6, 'sort_order' => 62],
            ['name' => '音像制品', 'parent_id' => 6, 'sort_order' => 63],
            ['name' => '游戏', 'parent_id' => 6, 'sort_order' => 64],
            ['name' => '运动服饰', 'parent_id' => 7, 'sort_order' => 71],
            ['name' => '健身器材', 'parent_id' => 7, 'sort_order' => 72],
            ['name' => '户外装备', 'parent_id' => 7, 'sort_order' => 73],
            ['name' => '体育用品', 'parent_id' => 7, 'sort_order' => 74],
            ['name' => '游戏点卡', 'parent_id' => 8, 'sort_order' => 81],
            ['name' => '会员服务', 'parent_id' => 8, 'sort_order' => 82],
            ['name' => '软件服务', 'parent_id' => 8, 'sort_order' => 83],
            ['name' => '在线课程', 'parent_id' => 8, 'sort_order' => 84],
        ];

        $stmt = $pdo->prepare("INSERT INTO product_categories (name, parent_id, sort_order, status) VALUES (?, ?, ?, 'active')");
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

// 分类统计
$statsStmt = $pdo->prepare("SELECT COUNT(*) as total_categories, COUNT(CASE WHEN parent_id = 0 THEN 1 END) as top_level_categories, COUNT(CASE WHEN parent_id > 0 THEN 1 END) as sub_categories FROM product_categories");
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// 统一后台框架
$admin_site_config = ['site' => 'mall', 'page_title' => '分类管理'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if ($error): ?>
<div class="admin-card" style="border-left:4px solid #ef4444; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#fca5a5;"><?= htmlspecialchars($error) ?></div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="admin-card" style="border-left:4px solid #22c55e; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#86efac;"><?= htmlspecialchars($success) ?></div>
</div>
<?php endif; ?>

<!-- 统计卡片 -->
<div class="admin-stats-grid" style="margin-bottom:20px;">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-tags"></i></div>
        <div class="stat-value"><?= $stats['total_categories'] ?></div>
        <div class="stat-label">总分类</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-folder"></i></div>
        <div class="stat-value"><?= $stats['top_level_categories'] ?></div>
        <div class="stat-label">顶级分类</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-folder-open"></i></div>
        <div class="stat-value"><?= $stats['sub_categories'] ?></div>
        <div class="stat-label">子分类</div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 2fr; gap:20px;">
    <!-- 添加分类表单 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">添加分类</span>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:6px;">分类名称 *</label>
                    <input type="text" name="name" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required maxlength="50"
                           style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:6px;">父级分类</label>
                    <select name="parent_id" style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                        <option value="0">作为顶级分类</option>
                        <?php foreach ($topLevelCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($_POST['parent_id']) && $_POST['parent_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:6px;">排序顺序</label>
                    <input type="number" name="sort_order" value="<?= isset($_POST['sort_order']) ? $_POST['sort_order'] : 0 ?>" min="0"
                           style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:6px;">状态</label>
                    <select name="status" style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                        <option value="active" <?= (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : '' ?>>激活</option>
                        <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : '' ?>>禁用</option>
                    </select>
                </div>
                <button type="submit" class="admin-btn admin-btn-primary" style="width:100%;justify-content:center;">
                    <i class="fas fa-plus"></i> 添加分类
                </button>
            </form>
        </div>
    </div>

    <!-- 分类列表 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">分类列表</span>
            <form method="POST" onsubmit="return confirm('确定要初始化分类吗？这将清空现有分类数据！')" style="margin:0;">
                <input type="hidden" name="action" value="init_categories">
                <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">
                    <i class="fas fa-sync"></i> 初始化分类
                </button>
            </form>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if ($categoryTree): ?>
                <div style="max-height:600px;overflow-y:auto;">
                    <?php foreach ($categoryTree as $cat): ?>
                        <div style="border-left:3px solid #3b82f6;padding-left:12px;margin:12px 16px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:#0f172a;border-radius:8px;">
                                <div>
                                    <strong style="color:#f1f5f9;"><?= htmlspecialchars($cat['name']) ?></strong>
                                    <span class="admin-badge info" style="margin-left:8px;">顶级分类</span>
                                    <span class="admin-badge <?= $cat['status'] == 'active' ? 'success' : 'default' ?>" style="margin-left:4px;"><?= $cat['status'] == 'active' ? '激活' : '禁用' ?></span>
                                    <small style="color:#64748b;margin-left:8px;">排序: <?= $cat['sort_order'] ?></small>
                                </div>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary"
                                            onclick="editCategory(<?= $cat['id'] ?>,'<?= addslashes($cat['name']) ?>',<?= $cat['parent_id'] ?>,<?= $cat['sort_order'] ?>,'<?= $cat['status'] ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('确定要删除吗？')">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="admin-btn admin-btn-sm" style="background:#7f1d1d;color:#fca5a5;border:none;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php if (!empty($cat['children'])): ?>
                                <div style="border-left:2px dashed #334155;margin-left:16px;margin-top:8px;">
                                    <?php foreach ($cat['children'] as $child): ?>
                                        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;margin-bottom:6px;border:1px solid #1e293b;border-radius:8px;">
                                            <div>
                                                <span style="color:#e2e8f0;"><?= htmlspecialchars($child['name']) ?></span>
                                                <span class="admin-badge <?= $child['status'] == 'active' ? 'success' : 'default' ?>" style="margin-left:8px;"><?= $child['status'] == 'active' ? '激活' : '禁用' ?></span>
                                                <small style="color:#64748b;margin-left:8px;">排序: <?= $child['sort_order'] ?></small>
                                            </div>
                                            <div style="display:flex;gap:4px;">
                                                <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary"
                                                        onclick="editCategory(<?= $child['id'] ?>,'<?= addslashes($child['name']) ?>',<?= $child['parent_id'] ?>,<?= $child['sort_order'] ?>,'<?= $child['status'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('确定要删除吗？')">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="category_id" value="<?= $child['id'] ?>">
                                                    <button type="submit" class="admin-btn admin-btn-sm" style="background:#7f1d1d;color:#fca5a5;border:none;">
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
                <div class="admin-empty-state">
                    <i class="fas fa-tags"></i>
                    <h4>暂无分类</h4>
                    <p>请添加商品分类或初始化分类数据</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 编辑分类模态框 -->
<div id="editCategoryModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#1e293b;border-radius:12px;padding:0;width:480px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.4);">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #334155;">
            <h3 style="font-size:16px;color:#f1f5f9;margin:0;">编辑分类</h3>
            <button onclick="closeModal()" style="background:none;border:none;color:#94a3b8;font-size:20px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST" id="editCategoryForm" style="padding:20px;">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="category_id" id="edit_category_id">
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:6px;">分类名称 *</label>
                <input type="text" name="name" id="edit_name" required maxlength="50"
                       style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:6px;">父级分类</label>
                <select name="parent_id" id="edit_parent_id"
                        style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                    <option value="0">作为顶级分类</option>
                    <?php foreach ($topLevelCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:6px;">排序顺序</label>
                <input type="number" name="sort_order" id="edit_sort_order" min="0"
                       style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:500;color:#94a3b8;margin-bottom:6px;">状态</label>
                <select name="status" id="edit_status"
                        style="width:100%;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:8px;color:#f1f5f9;font-size:14px;">
                    <option value="active">激活</option>
                    <option value="inactive">禁用</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="closeModal()">取消</button>
                <button type="submit" class="admin-btn admin-btn-primary">保存更改</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(id, name, parentId, sortOrder, status) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_parent_id').value = parentId;
    document.getElementById('edit_sort_order').value = sortOrder;
    document.getElementById('edit_status').value = status;
    document.getElementById('editCategoryModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editCategoryModal').style.display = 'none';
}

document.getElementById('editCategoryModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// 表单验证
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const nameInput = form.querySelector('input[name="name"]');
            if (nameInput && !nameInput.value.trim()) {
                alert('请输入分类名称');
                e.preventDefault();
            }
        });
    });
});
</script>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
