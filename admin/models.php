<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../classes/Model.php';
require_once '../classes/User.php';

checkAdmin();

$model = new Model($pdo);
$user = new User($pdo);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 处理 CRUD 操作
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $modelId = intval($_POST['id'] ?? 0);
        if ($_POST['action'] === 'delete' && $modelId > 0) {
            if ($model->delete($modelId)) {
                $actionMsg = '<div class="admin-alert admin-alert-success">模特已停用</div>';
            }
        } elseif ($_POST['action'] === 'save') {
            $data = [
                'user_id'  => intval($_POST['user_id'] ?? 0),
                'nickname'  => trim($_POST['nickname'] ?? ''),
                'gender'    => $_POST['gender'] ?? '保密',
                'age'       => $_POST['age'] !== '' ? intval($_POST['age']) : null,
                'qq'        => trim($_POST['qq'] ?? ''),
                'weixin'    => trim($_POST['weixin'] ?? ''),
                'weibo'     => trim($_POST['weibo'] ?? ''),
                'xiaohongshu' => trim($_POST['xiaohongshu'] ?? ''),
                'height'    => $_POST['height'] !== '' ? $_POST['height'] : null,
                'weight'    => $_POST['weight'] !== '' ? $_POST['weight'] : null,
                'measurements' => trim($_POST['measurements'] ?? ''),
                'hobbies'   => trim($_POST['hobbies'] ?? ''),
            ];

            if ($modelId > 0) {
                // 更新
                $data['status'] = $_POST['status'] ?? 'active';
                if ($model->update($modelId, $data)) {
                    $actionMsg = '<div class="admin-alert admin-alert-success">模特信息已更新</div>';
                }
            } else {
                // 新建
                if (empty($data['nickname']) || $data['user_id'] <= 0) {
                    $actionMsg = '<div class="admin-alert admin-alert-error">昵称和站内用户为必填项</div>';
                } elseif ($model->create($data)) {
                    $actionMsg = '<div class="admin-alert admin-alert-success">模特创建成功</div>';
                } else {
                    $actionMsg = '<div class="admin-alert admin-alert-error">创建失败，该用户可能已是模特</div>';
                }
            }
        }
    }
}

// 列表数据
$listData = $model->getList($page, $perPage, $search);
$models = $listData['list'];
$total = $listData['total'];
$totalPages = $listData['pages'];

// 编辑模式
$editModel = null;
if (isset($_GET['edit'])) {
    $editModel = $model->getById(intval($_GET['edit']));
}

// Admin 站点配置
$admin_site_config = [
    'site'       => 'mall',
    'page_title' => '模特管理 - 58商城后台',
];
require_once '../shared/admin/admin-header.php';
?>

<div class="admin-content">
    <div class="admin-page-header">
        <h1>模特管理</h1>
        <a href="?add=1" class="admin-btn admin-btn-primary">+ 添加模特</a>
    </div>

    <?= $actionMsg ?>

    <?php if (isset($_GET['add']) || $editModel):
        $isEdit = (bool)$editModel;
        $formData = $isEdit ? $editModel : [];
    ?>
    <div class="admin-card">
        <h3><?= $isEdit ? '编辑模特' : '添加模特' ?></h3>
        <form method="post" class="admin-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $isEdit ? $formData['id'] : '' ?>">

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>昵称 <span style="color:red">*</span></label>
                    <input type="text" name="nickname" value="<?= htmlspecialchars($formData['nickname'] ?? '') ?>" required maxlength="100">
                </div>
                <div class="admin-form-group">
                    <label>站内用户 <span style="color:red">*</span></label>
                    <?php if ($isEdit): ?>
                        <input type="text" value="<?= htmlspecialchars($formData['username']) ?>" disabled>
                        <small>关联用户：<?= htmlspecialchars($formData['username']) ?></small>
                        <input type="hidden" name="user_id" value="<?= $formData['user_id'] ?>">
                    <?php else: ?>
                        <input type="number" name="user_id" placeholder="输入用户ID" required min="1">
                        <small>输入站内用户的数字ID</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>性别</label>
                    <select name="gender">
                        <?php foreach (['保密','男','女'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($formData['gender'] ?? '保密') === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>年龄</label>
                    <input type="number" name="age" value="<?= htmlspecialchars($formData['age'] ?? '') ?>" min="1" max="120">
                </div>
                <div class="admin-form-group">
                    <label>身高 (cm)</label>
                    <input type="number" step="0.1" name="height" value="<?= htmlspecialchars($formData['height'] ?? '') ?>" min="100" max="220">
                </div>
                <div class="admin-form-group">
                    <label>体重 (kg)</label>
                    <input type="number" step="0.1" name="weight" value="<?= htmlspecialchars($formData['weight'] ?? '') ?>" min="30" max="150">
                </div>
            </div>

            <div class="admin-form-group">
                <label>三围</label>
                <input type="text" name="measurements" value="<?= htmlspecialchars($formData['measurements'] ?? '') ?>" placeholder="例：86-60-88">
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>QQ</label>
                    <input type="text" name="qq" value="<?= htmlspecialchars($formData['qq'] ?? '') ?>">
                </div>
                <div class="admin-form-group">
                    <label>微信</label>
                    <input type="text" name="weixin" value="<?= htmlspecialchars($formData['weixin'] ?? '') ?>">
                </div>
                <div class="admin-form-group">
                    <label>微博</label>
                    <input type="text" name="weibo" value="<?= htmlspecialchars($formData['weibo'] ?? '') ?>">
                </div>
                <div class="admin-form-group">
                    <label>小红书</label>
                    <input type="text" name="xiaohongshu" value="<?= htmlspecialchars($formData['xiaohongshu'] ?? '') ?>">
                </div>
            </div>

            <div class="admin-form-group">
                <label>爱好</label>
                <textarea name="hobbies" rows="3"><?= htmlspecialchars($formData['hobbies'] ?? '') ?></textarea>
            </div>

            <?php if ($isEdit): ?>
            <div class="admin-form-group">
                <label>状态</label>
                <select name="status">
                    <option value="active" <?= ($formData['status'] ?? '') === 'active' ? 'selected' : '' ?>>启用</option>
                    <option value="inactive" <?= ($formData['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>停用</option>
                </select>
            </div>
            <?php endif; ?>

            <button type="submit" class="admin-btn admin-btn-primary"><?= $isEdit ? '更新' : '创建' ?></button>
            <a href="models.php" class="admin-btn">取消</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- 搜索 -->
    <div class="admin-card">
        <form method="get" class="admin-search-form">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索昵称或用户名...">
            <button type="submit" class="admin-btn">搜索</button>
            <?php if ($search): ?>
            <a href="models.php" class="admin-btn">清除</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 模特列表 -->
    <div class="admin-card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>昵称</th>
                    <th>用户</th>
                    <th>性别</th>
                    <th>身高</th>
                    <th>关联商品</th>
                    <th>点赞</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($models)): ?>
                <tr><td colspan="9" style="text-align:center;padding:30px;">暂无数据</td></tr>
                <?php else: ?>
                <?php foreach ($models as $m): ?>
                <tr>
                    <td><?= $m['id'] ?></td>
                    <td><strong><?= htmlspecialchars($m['nickname']) ?></strong></td>
                    <td><?= htmlspecialchars($m['username']) ?></td>
                    <td><?= $m['gender'] ?></td>
                    <td><?= $m['height'] ? $m['height'] . 'cm' : '-' ?></td>
                    <td><?= $m['product_count'] ?></td>
                    <td><?= $m['like_count'] ?></td>
                    <td><span class="admin-badge <?= $m['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= $m['status'] === 'active' ? '启用' : '停用' ?></span></td>
                    <td>
                        <a href="?edit=<?= $m['id'] ?>" class="admin-btn admin-btn-sm">编辑</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('确定停用该模特？')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">停用</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
    <div class="admin-pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" class="admin-btn">上一页</a>
        <?php endif; ?>
        <span class="admin-page-info">第 <?= $page ?> / <?= $totalPages ?> 页（共 <?= $total ?> 条）</span>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" class="admin-btn">下一页</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../shared/admin/admin-footer.php'; ?>
