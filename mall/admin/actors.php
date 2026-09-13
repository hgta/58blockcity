<?php
/**
 * 演员表管理（短剧演职人员档案，跨剧复用）
 *
 * 头像支持两种来源：
 *   - 上传：走 GD 等比裁切（正方形），存 mall/assets/uploads/actors/YYYYMM/
 *   - 外链：直接填完整 http(s) URL
 * 两者可任选其一，上传优先。
 */
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Actor.php';
require_once '../../classes/Drama.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$actor = new Actor($pdo);
$drama = new Drama($pdo);

/** 头像上传（正方形等比裁切，统一 jpg）。失败返回 null。 */
function uploadActorAvatar($file)
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] === 0) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed) || $file['size'] > 5 * 1024 * 1024) {
        return null;
    }
    $subDir    = date('Ym') . '/';
    $uploadDir = __DIR__ . '/../assets/uploads/actors/' . $subDir;
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

    $fname   = 'ac_' . uniqid() . '_' . time() . '.jpg';
    $path    = $uploadDir . $fname;
    $relPath = 'assets/uploads/actors/' . $subDir . $fname;

    $src = null;
    switch ($file['type']) {
        case 'image/jpeg': case 'image/jpg': $src = @imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png':  $src = @imagecreatefrompng($file['tmp_name']); break;
        case 'image/gif':  $src = @imagecreatefromgif($file['tmp_name']); break;
        case 'image/webp': $src = @imagecreatefromwebp($file['tmp_name']); break;
    }
    if ($src) {
        $w = imagesx($src); $h = imagesy($src);
        $target = 240;   // 演员头像展示尺寸小，240 足够
        $ratio  = max($target / $w, $target / $h);
        $srcW   = (int)round($target / $ratio);
        $srcH   = (int)round($target / $ratio);
        $srcX   = (int)max(0, ($w - $srcW) / 2);
        $srcY   = (int)max(0, ($h - $srcH) / 2);
        $dst = imagecreatetruecolor($target, $target);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $target, $target, $srcW, $srcH);
        imagejpeg($dst, $path, 85);
        imagedestroy($src); imagedestroy($dst);
        return $relPath;
    }
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $relPath;
    }
    return null;
}

/** 解析头像：上传优先，其次外链 */
function resolveActorAvatar()
{
    $uploaded = uploadActorAvatar($_FILES['avatar_file'] ?? null);
    if ($uploaded) {
        return $uploaded;
    }
    $url = trim($_POST['avatar_url'] ?? '');
    if ($url !== '' && preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return null;
}

$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actorId = intval($_POST['id'] ?? 0);

    if ($_POST['action'] === 'delete' && $actorId > 0) {
        $actor->softDelete($actorId);
        $actionMsg = '<div class="admin-alert admin-alert-success">已停用该演员（历史参演关系保留）</div>';
    } elseif ($_POST['action'] === 'restore' && $actorId > 0) {
        $actor->restore($actorId);
        $actionMsg = '<div class="admin-alert admin-alert-success">已恢复该演员</div>';
    } elseif ($_POST['action'] === 'save') {
        $data = [
            'nickname' => trim($_POST['nickname'] ?? ''),
            'gender'   => $_POST['gender'] ?? '保密',
            'city'     => trim($_POST['city'] ?? ''),
            'bio'      => trim($_POST['bio'] ?? ''),
            'status'   => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];
        $avatar = resolveActorAvatar();
        if ($avatar !== null) {
            $data['avatar'] = $avatar;
        }

        if ($data['nickname'] === '') {
            $actionMsg = '<div class="admin-alert admin-alert-error">演员姓名为必填项</div>';
        } elseif ($actorId > 0) {
            $actionMsg = $actor->update($actorId, $data)
                ? '<div class="admin-alert admin-alert-success">演员信息已更新</div>'
                : '<div class="admin-alert admin-alert-error">更新失败（可能存在同名演员）</div>';
        } else {
            $newId = $actor->create($data);
            $actionMsg = $newId > 0
                ? '<div class="admin-alert admin-alert-success">演员创建成功</div>'
                : '<div class="admin-alert admin-alert-error">创建失败：已存在同名演员</div>';
        }
    }
}

/* ---------- AJAX：搜索演员（供短剧管理页选择器） ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    $kw = trim($_GET['q'] ?? '');
    $out = [];
    foreach ($actor->searchByNickname($kw, 15) as $a) {
        $out[] = [
            'id'         => intval($a['id']),
            'nickname'   => $a['nickname'],
            'role'       => '演员',
            'city'       => $a['city'] ?? '',
            'gender'     => $a['gender'] ?? '',
            'avatar'     => $a['avatar'] ? $a['avatar'] : '',
            'drama_count' => intval($a['drama_count'] ?? 0),
            'status'     => $a['status'] ?? 'active',
        ];
    }
    echo json_encode(['list' => $out]);
    exit;
}

/* ---------- 列表 ---------- */
$page   = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$status = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';
$listData = $actor->getList($page, 30, $search, $status);
$actors   = $listData['list'];

$editActor = null;
if (isset($_GET['edit'])) {
    $editActor = $actor->getById(intval($_GET['edit']));
    if ($editActor) {
        // 该演员参演的短剧
        $editDramas = $drama->getDramasByActor($editActor['id']);
    }
}

/** 头像预览地址（后台相对路径） */
function actorAvatarPreview($avatar)
{
    if (!$avatar) {
        return '';
    }
    if (preg_match('#^https?://#i', $avatar)) {
        return $avatar;
    }
    return '../' . ltrim($avatar, '/');
}

$admin_site_config = [
    'site'       => 'mall',
    'page_title' => '演员表管理 - 58商城后台',
];
require_once '../../shared/admin/admin-header.php';

$inputStyle = 'width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;';
$labelStyle = 'display:block;font-size:13px;color:#94a3b8;margin-bottom:4px;';
?>

<div class="admin-content">
    <div class="admin-page-header">
        <h1>演员表管理</h1>
        <a href="?add=1" class="admin-btn admin-btn-primary">+ 添加演员</a>
    </div>

    <?= $actionMsg ?>

    <?php if (isset($_GET['add']) || $editActor):
        $isEdit   = (bool)$editActor;
        $formData = $isEdit ? $editActor : [];
        $preview  = actorAvatarPreview($formData['avatar'] ?? '');
    ?>
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-header">
            <span class="admin-card-title"><?= $isEdit ? '编辑演员：' . htmlspecialchars($formData['nickname']) : '添加演员' ?></span>
        </div>
        <div class="admin-card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $isEdit ? intval($formData['id']) : '' ?>">

                <div style="display:flex;gap:24px;align-items:flex-start;margin-bottom:16px;">
                    <!-- 头像 -->
                    <div style="flex-shrink:0;width:150px;">
                        <label style="<?= $labelStyle ?>">头像</label>
                        <div style="width:120px;height:120px;border-radius:50%;overflow:hidden;background:#1e293b;border:2px solid #334155;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                            <?php if ($preview): ?>
                                <img id="avatar-preview-img" src="<?= htmlspecialchars($preview) ?>" style="width:100%;height:100%;object-fit:cover;"
                                     onerror="this.style.display='none';this.parentNode.querySelector('.ph').style.display='flex';">
                                <i class="fas fa-user ph" style="display:none;font-size:36px;color:#475569;"></i>
                            <?php else: ?>
                                <img id="avatar-preview-img" src="" style="width:100%;height:100%;object-fit:cover;display:none;">
                                <i class="fas fa-user ph" style="font-size:36px;color:#475569;"></i>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:12px;color:#64748b;margin-bottom:6px;">上传图片（自动裁为正方形）</div>
                        <input type="file" name="avatar_file" accept="image/*" style="font-size:12px;color:#94a3b8;max-width:130px;">
                        <?php if ($isEdit): ?><small style="color:#64748b;display:block;margin-top:4px;">留空则不更换</small><?php endif; ?>
                    </div>

                    <div style="flex:1;min-width:0;">
                        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label style="<?= $labelStyle ?>">演员姓名 <span style="color:#ef4444">*</span></label>
                                <input type="text" name="nickname" maxlength="100" required value="<?= htmlspecialchars($formData['nickname'] ?? '') ?>" style="<?= $inputStyle ?>">
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">性别</label>
                                <select name="gender" style="<?= $inputStyle ?>">
                                    <?php foreach (['女', '男', '保密'] as $g): ?>
                                        <option value="<?= $g ?>" <?= ($formData['gender'] ?? '保密') === $g ? 'selected' : '' ?>><?= $g ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">状态</label>
                                <select name="status" style="<?= $inputStyle ?>">
                                    <option value="active" <?= ($formData['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>正常</option>
                                    <option value="inactive" <?= ($formData['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>停用</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="<?= $labelStyle ?>">头像链接（可选，填写后无需上传）</label>
                            <input type="text" name="avatar_url" id="avatar-url-input" maxlength="500"
                                   value="<?= (!empty($formData['avatar']) && preg_match('#^https?://#i', $formData['avatar'])) ? htmlspecialchars($formData['avatar']) : '' ?>"
                                   placeholder="https://…/actor.jpg" style="<?= $inputStyle ?>">
                            <small style="color:#64748b;">支持外链；同时上传文件时以文件为准</small>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;">
                            <div>
                                <label style="<?= $labelStyle ?>">所在城市</label>
                                <input type="text" name="city" maxlength="100" value="<?= htmlspecialchars($formData['city'] ?? '') ?>" style="<?= $inputStyle ?>">
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">一句话简介</label>
                                <input type="text" name="bio" maxlength="500" value="<?= htmlspecialchars($formData['bio'] ?? '') ?>" placeholder="如：短剧男演员，擅长都市、逆袭题材" style="<?= $inputStyle ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="submit" class="admin-btn admin-btn-primary"><?= $isEdit ? '更新' : '创建' ?></button>
                    <a href="actors.php" class="admin-btn admin-btn-secondary">取消</a>
                </div>
            </form>

            <?php if ($isEdit && !empty($editDramas)): ?>
            <div style="margin-top:24px;border-top:1px solid #1e293b;padding-top:16px;">
                <div style="font-size:13px;color:#94a3b8;margin-bottom:10px;">
                    参演短剧（共 <?= count($editDramas) ?> 部）
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left;color:#64748b;font-size:12px;">
                            <th style="padding:6px 8px;">剧名</th>
                            <th style="padding:6px 8px;">角色名</th>
                            <th style="padding:6px 8px;">主要演员</th>
                            <th style="padding:6px 8px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($editDramas as $dd): ?>
                        <tr style="border-top:1px solid #1e293b;color:#e2e8f0;font-size:13.5px;">
                            <td style="padding:8px;">
                                <a href="dramas.php?edit=<?= intval($dd['id']) ?>" style="color:#7dd3fc;"><?= htmlspecialchars($dd['title']) ?></a>
                            </td>
                            <td style="padding:8px;"><?= htmlspecialchars($dd['role_name'] ?: '—') ?></td>
                            <td style="padding:8px;"><?= !empty($dd['is_lead']) ? '✅' : '—' ?></td>
                            <td style="padding:8px;">
                                <a href="dramas.php?edit=<?= intval($dd['id']) ?>" style="color:#94a3b8;font-size:12px;">前往该剧编辑参演</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($isEdit): ?>
            <div style="margin-top:24px;border-top:1px solid #1e293b;padding-top:16px;color:#64748b;font-size:13px;">
                该演员暂无参演短剧。可在「<a href="dramas.php" style="color:#7dd3fc;">短剧管理</a>」中选择本演员加入剧组。
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 搜索 -->
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-body">
            <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索演员姓名..." style="<?= $inputStyle ?>max-width:240px;">
                <select name="status" style="<?= $inputStyle ?>max-width:140px;">
                    <option value="">全部状态</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>正常</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>停用</option>
                </select>
                <button type="submit" class="admin-btn admin-btn-primary">搜索</button>
                <?php if ($search || $status): ?>
                    <a href="actors.php" class="admin-btn admin-btn-secondary">重置</a>
                <?php endif; ?>
                <span style="color:#64748b;font-size:13px;margin-left:auto;">共 <?= intval($listData['total']) ?> 位演员</span>
            </form>
        </div>
    </div>

    <!-- 列表 -->
    <div class="admin-card">
        <div class="admin-card-body">
            <?php if (empty($actors)): ?>
                <div style="text-align:center;color:#64748b;padding:40px 0;">
                    暂无演员数据。演员维护一次可在多部短剧中复用。
                </div>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left;color:#64748b;font-size:12px;border-bottom:1px solid #1e293b;">
                        <th style="padding:10px 8px;">头像</th>
                        <th style="padding:10px 8px;">姓名</th>
                        <th style="padding:10px 8px;">性别</th>
                        <th style="padding:10px 8px;">城市</th>
                        <th style="padding:10px 8px;">参演短剧</th>
                        <th style="padding:10px 8px;">状态</th>
                        <th style="padding:10px 8px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actors as $a): $prev = actorAvatarPreview($a['avatar']); ?>
                    <tr style="border-bottom:1px solid #1e293b;color:#e2e8f0;font-size:13.5px;">
                        <td style="padding:10px 8px;">
                            <?php if ($prev): ?>
                                <img src="<?= htmlspecialchars($prev) ?>" style="width:42px;height:42px;border-radius:50%;object-fit:cover;background:#1e293b;">
                            <?php else: ?>
                                <span style="display:inline-flex;width:42px;height:42px;border-radius:50%;background:#1e293b;align-items:center;justify-content:center;color:#475569;"><i class="fas fa-user"></i></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px 8px;font-weight:600;"><?= htmlspecialchars($a['nickname']) ?></td>
                        <td style="padding:10px 8px;color:#94a3b8;"><?= htmlspecialchars($a['gender']) ?></td>
                        <td style="padding:10px 8px;color:#94a3b8;"><?= htmlspecialchars($a['city'] ?: '—') ?></td>
                        <td style="padding:10px 8px;"><?= intval($a['drama_count']) ?> 部</td>
                        <td style="padding:10px 8px;">
                            <?= $a['status'] === 'active'
                                ? '<span style="color:#4ade80;">正常</span>'
                                : '<span style="color:#f87171;">停用</span>' ?>
                        </td>
                        <td style="padding:10px 8px;white-space:nowrap;">
                            <a href="?edit=<?= intval($a['id']) ?>" class="admin-btn admin-btn-secondary" style="padding:4px 10px;font-size:12px;">编辑</a>
                            <?php if ($a['status'] === 'active'): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确认停用该演员？历史参演关系会保留。');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= intval($a['id']) ?>">
                                    <button type="submit" class="admin-btn admin-btn-secondary" style="padding:4px 10px;font-size:12px;">停用</button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="id" value="<?= intval($a['id']) ?>">
                                    <button type="submit" class="admin-btn admin-btn-secondary" style="padding:4px 10px;font-size:12px;">恢复</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($listData['pages'] > 1): ?>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:20px;align-items:center;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status ? '&status=' . $status : '' ?>" class="admin-btn admin-btn-secondary">上一页</a>
                <?php endif; ?>
                <span style="color:#94a3b8;font-size:13px;"><?= $page ?> / <?= intval($listData['pages']) ?></span>
                <?php if ($page < $listData['pages']): ?>
                    <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $status ? '&status=' . $status : '' ?>" class="admin-btn admin-btn-secondary">下一页</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
/* 填了头像链接即时预览 */
(function () {
    var urlInput = document.getElementById('avatar-url-input');
    var img = document.getElementById('avatar-preview-img');
    if (!urlInput || !img) return;
    urlInput.addEventListener('input', function () {
        var v = urlInput.value.trim();
        if (/^https?:\/\//i.test(v)) {
            img.src = v;
            img.style.display = 'block';
            var ph = img.parentNode.querySelector('.ph');
            if (ph) ph.style.display = 'none';
        }
    });
})();
</script>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
