<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Model.php';
require_once '../../classes/User.php';
require_once '../../classes/City.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$model = new Model($pdo);
$user = new User($pdo);
$city = new City($pdo);
$allCities = $city->getAllCities();

// 头像上传处理
function uploadModelAvatar($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) return null;
    $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed) || $file['size'] > 5*1024*1024) return null;
    $subDir = date('Ym') . '/';
    $uploadDir = __DIR__ . '/../assets/uploads/models/' . $subDir;
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
    $ext = ($file['type'] === 'image/png') ? 'png' : (($file['type'] === 'image/gif') ? 'gif' : 'jpg');
    $fname = uniqid() . '_' . time() . '.' . $ext;
    $path = $uploadDir . $fname;
    $relPath = 'assets/uploads/models/' . $subDir . $fname;
    // 压缩为 400x400
    $src = null;
    switch($file['type']){
        case 'image/jpeg':case 'image/jpg':$src=@imagecreatefromjpeg($file['tmp_name']);break;
        case 'image/png':$src=@imagecreatefrompng($file['tmp_name']);break;
        case 'image/gif':$src=@imagecreatefromgif($file['tmp_name']);break;
        case 'image/webp':$src=@imagecreatefromwebp($file['tmp_name']);break;
    }
    if ($src) {
        $w = imagesx($src); $h = imagesy($src);
        $size = min($w, $h);
        $dst = imagecreatetruecolor(400, 400);
        imagecopyresampled($dst, $src, 0, 0, ($w-$size)/2, ($h-$size)/2, 400, 400, $size, $size);
        imagejpeg($dst, $path, 80);
        imagedestroy($src); imagedestroy($dst);
        return $relPath;
    }
    if (move_uploaded_file($file['tmp_name'], $path)) return $relPath;
    return null;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $modelId = intval($_POST['id'] ?? 0);
        if ($_POST['action'] === 'delete' && $modelId > 0) {
            if ($model->delete($modelId)) {
                $actionMsg = '<div class="admin-alert admin-alert-success">模特已停用</div>';
            }
        } elseif ($_POST['action'] === 'save') {
            // 处理头像上传
            $avatarPath = uploadModelAvatar($_FILES['avatar_file'] ?? null);
            $data = [
                'user_id'  => !empty($_POST['user_id']) ? intval($_POST['user_id']) : null,
                'nickname'  => trim($_POST['nickname'] ?? ''),
                'avatar'   => $avatarPath,
                'gender'    => $_POST['gender'] ?? '保密',
                'age'       => $_POST['age'] !== '' ? intval($_POST['age']) : null,
                'city'      => trim($_POST['city'] ?? ''),
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
                $data['status'] = $_POST['status'] ?? 'active';
                if ($model->update($modelId, $data)) {
                    $actionMsg = '<div class="admin-alert admin-alert-success">模特信息已更新</div>';
                }
            } else {
                if (empty($data['nickname'])) {
                    $actionMsg = '<div class="admin-alert admin-alert-error">昵称为必填项</div>';
                } elseif ($model->create($data)) {
                    $actionMsg = '<div class="admin-alert admin-alert-success">模特创建成功</div>';
                } else {
                    $actionMsg = '<div class="admin-alert admin-alert-error">创建失败</div>';
                }
            }
        }
    }
}

$listData = $model->getList($page, $perPage, $search);
$models = $listData['list'];
$total = $listData['total'];
$totalPages = $listData['pages'];

$editModel = null;
if (isset($_GET['edit'])) {
    $editModel = $model->getById(intval($_GET['edit']));
}

$admin_site_config = [
    'site'       => 'mall',
    'page_title' => '模特管理 - 58商城后台',
];
require_once '../../shared/admin/admin-header.php';

// 统一输入框样式
$inputStyle = 'width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;';
$labelStyle = 'display:block;font-size:13px;color:#94a3b8;margin-bottom:4px;';
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
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-header">
            <span class="admin-card-title"><?= $isEdit ? '编辑模特' : '添加模特' ?></span>
        </div>
        <div class="admin-card-body">
            <form method="post">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $isEdit ? $formData['id'] : '' ?>">

                <div style="display:flex;gap:20px;margin-bottom:16px;align-items:flex-start;">
                    <div style="flex-shrink:0;">
                        <label style="<?= $labelStyle ?>">头像</label>
                        <div style="width:100px;height:100px;border-radius:8px;overflow:hidden;background:#1e293b;border:2px solid #334155;display:flex;align-items:center;justify-content:center;margin-bottom:6px;">
                            <?php $avatarUrl = !empty($formData['avatar']) ? '../' . $formData['avatar'] : ''; ?>
                            <?php if ($avatarUrl): ?>
                            <img src="<?= htmlspecialchars($avatarUrl) ?>" style="width:100%;height:100%;object-fit:cover;" id="avatar-preview">
                            <?php else: ?>
                            <i class="fas fa-camera" style="font-size:28px;color:#475569;" id="avatar-placeholder"></i>
                            <img src="" style="width:100%;height:100%;object-fit:cover;display:none;" id="avatar-preview">
                            <?php endif; ?>
                        </div>
                        <input type="file" name="avatar_file" accept="image/*" style="font-size:12px;color:#94a3b8;max-width:100px;" onchange="previewAvatar(this)">
                        <?php if ($avatarUrl): ?><small style="color:#64748b;">留空则不更换</small><?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">

                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="<?= $labelStyle ?>">昵称 <span style="color:#ef4444">*</span></label>
                        <input type="text" name="nickname" value="<?= htmlspecialchars($formData['nickname'] ?? '') ?>" required maxlength="100" style="<?= $inputStyle ?>">
                    </div>
                    <div>
                        <label style="<?= $labelStyle ?>">站内用户</label>
                        <?php if ($isEdit): ?>
                            <input type="text" value="<?= htmlspecialchars($formData['username'] ?? '-') ?>" disabled style="width:100%;padding:8px 12px;background:#1e293b;border:1px solid #334155;border-radius:6px;color:#64748b;font-size:14px;">
                            <input type="hidden" name="user_id" value="<?= $formData['user_id'] ?>">
                        <?php else: ?>
                            <input type="number" name="user_id" placeholder="输入用户ID（可选）" min="1" style="<?= $inputStyle ?>">
                        <?php endif; ?>
                    </div>
                    <div>
                        <label style="<?= $labelStyle ?>">所在城市</label>
                        <select name="city" style="<?= $inputStyle ?>">
                            <option value="">-- 选择城市 --</option>
                            <?php foreach ($allCities as $c): ?>
                            <option value="<?= htmlspecialchars($c['name']) ?>" <?= ($formData['city'] ?? '') === $c['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="<?= $labelStyle ?>">性别</label>
                        <select name="gender" style="<?= $inputStyle ?>">
                            <?php foreach (['保密','男','女'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($formData['gender'] ?? '保密') === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="<?= $labelStyle ?>">年龄</label>
                        <input type="number" name="age" value="<?= htmlspecialchars($formData['age'] ?? '') ?>" min="1" max="120" style="<?= $inputStyle ?>">
                    </div>
                    <div>
                        <label style="<?= $labelStyle ?>">身高 (cm)</label>
                        <input type="number" step="0.1" name="height" value="<?= htmlspecialchars($formData['height'] ?? '') ?>" min="100" max="220" style="<?= $inputStyle ?>">
                    </div>
                    <div>
                        <label style="<?= $labelStyle ?>">体重 (kg)</label>
                        <input type="number" step="0.1" name="weight" value="<?= htmlspecialchars($formData['weight'] ?? '') ?>" min="30" max="150" style="<?= $inputStyle ?>">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="<?= $labelStyle ?>">三围</label>
                    <input type="text" name="measurements" value="<?= htmlspecialchars($formData['measurements'] ?? '') ?>" placeholder="例：86-60-88" style="<?= $inputStyle ?>max-width:300px;">
                </div>

                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
                    <div><label style="<?= $labelStyle ?>">QQ</label><input type="text" name="qq" value="<?= htmlspecialchars($formData['qq'] ?? '') ?>" style="<?= $inputStyle ?>"></div>
                    <div><label style="<?= $labelStyle ?>">微信</label><input type="text" name="weixin" value="<?= htmlspecialchars($formData['weixin'] ?? '') ?>" style="<?= $inputStyle ?>"></div>
                    <div><label style="<?= $labelStyle ?>">微博</label><input type="text" name="weibo" value="<?= htmlspecialchars($formData['weibo'] ?? '') ?>" style="<?= $inputStyle ?>"></div>
                    <div><label style="<?= $labelStyle ?>">小红书</label><input type="text" name="xiaohongshu" value="<?= htmlspecialchars($formData['xiaohongshu'] ?? '') ?>" style="<?= $inputStyle ?>"></div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="<?= $labelStyle ?>">爱好</label>
                    <textarea name="hobbies" rows="3" style="<?= $inputStyle ?>resize:vertical;"><?= htmlspecialchars($formData['hobbies'] ?? '') ?></textarea>
                </div>

                <?php if ($isEdit): ?>
                <div style="margin-bottom:16px;">
                    <label style="<?= $labelStyle ?>">状态</label>
                    <select name="status" style="padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;">
                        <option value="active" <?= ($formData['status'] ?? '') === 'active' ? 'selected' : '' ?>>启用</option>
                        <option value="inactive" <?= ($formData['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>停用</option>
                    </select>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:10px;margin-top:20px;">
                    <button type="submit" class="admin-btn admin-btn-primary"><?= $isEdit ? '更新' : '创建' ?></button>
                    <a href="models.php" class="admin-btn admin-btn-secondary">取消</a>
                </div>
                    </div><!-- close flex inner -->
                </div><!-- close flex container -->
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 搜索 -->
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-body">
            <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索昵称或用户名..."
                       style="padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;min-width:200px;">
                <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm"><i class="fas fa-search"></i> 搜索</button>
                <?php if ($search): ?>
                <a href="models.php" class="admin-btn admin-btn-secondary admin-btn-sm"><i class="fas fa-redo"></i> 清除</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 模特列表（卡片式） -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">模特列表 (<?= $total ?>)</span>
        </div>
        <div class="admin-card-body">
            <?php if (empty($models)): ?>
            <div style="text-align:center;padding:40px;color:#64748b;">
                <i class="fas fa-user-circle" style="font-size:48px;display:block;margin-bottom:12px;"></i>
                暂无模特数据
            </div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
                <?php foreach ($models as $m): ?>
                <div style="background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:16px;display:flex;gap:14px;align-items:center;transition:border-color 0.2s;" onmouseover="this.style.borderColor='#334155'" onmouseout="this.style.borderColor='#1e293b'">
                    <!-- 头像 -->
                    <div style="width:52px;height:52px;border-radius:50%;overflow:hidden;background:#1e293b;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid #334155;">
                        <?php if (!empty($m['avatar'])): ?>
                        <img src="<?= htmlspecialchars($m['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                        <i class="fas fa-user" style="font-size:22px;color:#475569;"></i>
                        <?php endif; ?>
                    </div>
                    <!-- 信息 -->
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                            <strong style="font-size:15px;color:#e2e8f0;"><?= htmlspecialchars($m['nickname']) ?></strong>
                            <span class="admin-badge <?= $m['status']==='active'?'badge-success':'badge-danger' ?>" style="font-size:11px;"><?= $m['status']==='active'?'启用':'停用' ?></span>
                        </div>
                        <div style="font-size:12px;color:#64748b;margin-bottom:6px;">
                            <?php if ($m['username']): ?><span style="margin-right:8px;"><i class="fas fa-at"></i> <?= htmlspecialchars($m['username']) ?></span><?php endif; ?>
                            <?= $m['gender'] !== '保密' ? $m['gender'] : '' ?>
                            <?= $m['height'] ? ' · ' . $m['height'] . 'cm' : '' ?>
                            <?= $m['city'] ? ' · ' . htmlspecialchars($m['city']) : '' ?>
                        </div>
                        <div style="display:flex;gap:16px;font-size:12px;color:#94a3b8;margin-bottom:8px;">
                            <span><i class="fas fa-box"></i> <?= $m['product_count'] ?> 商品</span>
                            <span><i class="fas fa-heart" style="color:#f87171;"></i> <?= $m['like_count'] ?> 赞</span>
                        </div>
                        <div style="display:flex;gap:6px;">
                            <a href="?edit=<?= $m['id'] ?>" class="admin-btn admin-btn-sm"><i class="fas fa-edit"></i> 编辑</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('确定停用该模特？')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger" style="font-size:12px;">停用</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
    <div style="text-align:center;margin-top:20px;">
        <div class="admin-pagination" style="display:inline-flex;gap:8px;align-items:center;">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" class="admin-btn admin-btn-sm admin-btn-secondary">上一页</a>
            <?php endif; ?>
            <span class="admin-page-info" style="padding:0 12px;color:#94a3b8;"><?= $page ?> / <?= $totalPages ?> (<?= $total ?>条)</span>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" class="admin-btn admin-btn-sm admin-btn-secondary">下一页</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function previewAvatar(input) {
    var preview = document.getElementById('avatar-preview');
    var placeholder = document.getElementById('avatar-placeholder');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            if (placeholder) placeholder.style.display = 'none';
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
