<?php
/**
 * 短剧管理（模特子站 model.58.tl 的内容源）
 */
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Drama.php';
require_once '../../classes/Model.php';
require_once '../../classes/SeoHelper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$drama = new Drama($pdo);
$model = new Model($pdo);

/** 封面上传（与模特头像一致：GD 等比裁切，统一 jpg） */
function uploadDramaCover($file)
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] === 0) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed) || $file['size'] > 5 * 1024 * 1024) {
        return null;
    }
    $subDir    = date('Ym') . '/';
    $uploadDir = __DIR__ . '/../assets/uploads/dramas/' . $subDir;
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

    $fname   = 'dr_' . uniqid() . '_' . time() . '.jpg';
    $path    = $uploadDir . $fname;
    $relPath = 'assets/uploads/dramas/' . $subDir . $fname;

    $src = null;
    switch ($file['type']) {
        case 'image/jpeg': case 'image/jpg': $src = @imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png':  $src = @imagecreatefrompng($file['tmp_name']); break;
        case 'image/gif':  $src = @imagecreatefromgif($file['tmp_name']); break;
        case 'image/webp': $src = @imagecreatefromwebp($file['tmp_name']); break;
    }
    if ($src) {
        $w = imagesx($src); $h = imagesy($src);
        // 短剧封面按 3:4 竖版
        $targetW = 600; $targetH = 800;
        $ratio    = max($targetW / $w, $targetH / $h);
        $srcW     = (int)round($targetW / $ratio);
        $srcH     = (int)round($targetH / $ratio);
        $srcX     = (int)max(0, ($w - $srcW) / 2);
        $srcY     = (int)max(0, ($h - $srcH) / 2);
        $dst = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $targetW, $targetH, $srcW, $srcH);
        imagejpeg($dst, $path, 82);
        imagedestroy($src); imagedestroy($dst);
        return $relPath;
    }
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $relPath;
    }
    return null;
}

$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $dramaId = intval($_POST['id'] ?? 0);

    if ($_POST['action'] === 'delete' && $dramaId > 0) {
        $drama->softDelete($dramaId);
        $actionMsg = '<div class="admin-alert admin-alert-success">短剧已下架</div>';
    } elseif ($_POST['action'] === 'restore' && $dramaId > 0) {
        $drama->restore($dramaId);
        $actionMsg = '<div class="admin-alert admin-alert-success">短剧已恢复上架</div>';
    } elseif ($_POST['action'] === 'attach' && $dramaId > 0) {
        $targetModelId = intval($_POST['model_id'] ?? 0);
        if ($targetModelId <= 0) {
            $actionMsg = '<div class="admin-alert admin-alert-error">请填写有效的模特 ID</div>';
        } elseif (!$model->getById($targetModelId)) {
            $actionMsg = '<div class="admin-alert admin-alert-error">模特不存在，请核对 ID</div>';
        } else {
            $drama->attachModel(
                $dramaId,
                $targetModelId,
                mb_substr(trim($_POST['role_name'] ?? ''), 0, 100),
                !empty($_POST['is_lead']) ? 1 : 0,
                intval($_POST['sort_order'] ?? 0)
            );
            $actionMsg = '<div class="admin-alert admin-alert-success">已添加参演模特</div>';
        }
    } elseif ($_POST['action'] === 'detach' && $dramaId > 0) {
        $drama->detachModel($dramaId, intval($_POST['model_id'] ?? 0));
        $actionMsg = '<div class="admin-alert admin-alert-success">已移除参演关系</div>';
    } elseif ($_POST['action'] === 'save') {
        $data = [
            'title'    => trim($_POST['title'] ?? ''),
            'episodes' => ($_POST['episodes'] ?? '') !== '' ? intval($_POST['episodes']) : null,
            'tags'     => trim($_POST['tags'] ?? ''),
            'hg_url'   => trim($_POST['hg_url'] ?? ''),
            'synopsis' => trim($_POST['synopsis'] ?? ''),
            'status'   => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];
        // 封面：上传优先，其次手填 URL
        $cover = uploadDramaCover($_FILES['cover_file'] ?? null);
        if ($cover) {
            $data['cover'] = $cover;
        } elseif (trim($_POST['cover_url'] ?? '') !== '') {
            $data['cover'] = trim($_POST['cover_url']);
        }

        if ($data['title'] === '') {
            $actionMsg = '<div class="admin-alert admin-alert-error">剧名为必填项</div>';
        } else {
            if ($dramaId > 0) {
                if ($drama->update($dramaId, $data)) {
                    $actionMsg = '<div class="admin-alert admin-alert-success">短剧已更新</div>';
                    SeoHelper::pushContentUrl(SeoHelper::dramaUrl($dramaId, $data['title']));
                }
            } else {
                $newId = $drama->create($data);
                if ($newId > 0) {
                    $actionMsg = '<div class="admin-alert admin-alert-success">短剧创建成功</div>';
                    SeoHelper::pushContentUrl(SeoHelper::dramaUrl($newId, $data['title']));
                } else {
                    $actionMsg = '<div class="admin-alert admin-alert-error">创建失败</div>';
                }
            }
        }
    }
}

$page    = max(1, intval($_GET['page'] ?? 1));
$search  = trim($_GET['search'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';
$listData = $drama->getList($page, 20, $search, $status);
$dramas   = $listData['list'];

$editDrama = null;
if (isset($_GET['edit'])) {
    $editDrama = $drama->getById(intval($_GET['edit']));
}

// 参演模特管理：按剧加载
$castList = [];
if ($editDrama) {
    $castList = $drama->getCredits($editDrama['id']);
}

$admin_site_config = [
    'site'       => 'mall',
    'page_title' => '短剧管理 - 58商城后台',
];
require_once '../../shared/admin/admin-header.php';

$inputStyle = 'width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;';
$labelStyle = 'display:block;font-size:13px;color:#94a3b8;margin-bottom:4px;';
?>

<div class="admin-content">
    <div class="admin-page-header">
        <h1>短剧管理</h1>
        <a href="?add=1" class="admin-btn admin-btn-primary">+ 添加短剧</a>
    </div>

    <?= $actionMsg ?>

    <?php if (isset($_GET['add']) || $editDrama):
        $isEdit   = (bool)$editDrama;
        $formData = $isEdit ? $editDrama : [];
        $tagsStr  = !empty($formData['tags_arr']) ? implode('、', $formData['tags_arr']) : '';
    ?>
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-header">
            <span class="admin-card-title"><?= $isEdit ? '编辑短剧：' . htmlspecialchars($formData['title']) : '添加短剧' ?></span>
        </div>
        <div class="admin-card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $isEdit ? intval($formData['id']) : '' ?>">

                <div style="display:flex;gap:20px;margin-bottom:16px;align-items:flex-start;">
                    <div style="flex-shrink:0;">
                        <label style="<?= $labelStyle ?>">封面（3:4 竖版）</label>
                        <div style="width:120px;height:160px;border-radius:8px;overflow:hidden;background:#1e293b;border:2px solid #334155;display:flex;align-items:center;justify-content:center;margin-bottom:6px;">
                            <?php if (!empty($formData['cover'])): ?>
                                <img src="../<?= htmlspecialchars($formData['cover']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <i class="fas fa-film" style="font-size:30px;color:#475569;"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="cover_file" accept="image/*" style="font-size:12px;color:#94a3b8;max-width:120px;">
                        <?php if ($isEdit): ?><small style="color:#64748b;display:block;">留空则不更换</small><?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label style="<?= $labelStyle ?>">剧名 <span style="color:#ef4444">*</span></label>
                                <input type="text" name="title" maxlength="200" required value="<?= htmlspecialchars($formData['title'] ?? '') ?>" style="<?= $inputStyle ?>">
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">集数</label>
                                <input type="number" name="episodes" min="1" max="9999" value="<?= htmlspecialchars($formData['episodes'] ?? '') ?>" style="<?= $inputStyle ?>">
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">状态</label>
                                <select name="status" style="<?= $inputStyle ?>">
                                    <option value="active" <?= ($formData['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>上架</option>
                                    <option value="inactive" <?= ($formData['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>下架</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="<?= $labelStyle ?>">题材标签</label>
                            <input type="text" name="tags" value="<?= htmlspecialchars($tagsStr) ?>" placeholder="例：都市、逆袭、甜宠（顿号或逗号分隔）" style="<?= $inputStyle ?>">
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="<?= $labelStyle ?>">红果 / 外部观看地址</label>
                            <input type="text" name="hg_url" maxlength="500" value="<?= htmlspecialchars($formData['hg_url'] ?? '') ?>" placeholder="https://hongguoduanju.com/…" style="<?= $inputStyle ?>">
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="<?= $labelStyle ?>">封面地址（可选，优先级低于上传）</label>
                            <input type="text" name="cover_url" maxlength="255" value="<?= htmlspecialchars($formData['cover_url'] ?? '') ?>" placeholder="assets/uploads/dramas/… 或完整 URL" style="<?= $inputStyle ?>">
                        </div>
                        <div>
                            <label style="<?= $labelStyle ?>">剧情简介</label>
                            <textarea name="synopsis" rows="3" style="<?= $inputStyle ?>resize:vertical;"><?= htmlspecialchars($formData['synopsis'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:6px;">
                    <button type="submit" class="admin-btn admin-btn-primary"><?= $isEdit ? '更新' : '创建' ?></button>
                    <a href="dramas.php" class="admin-btn admin-btn-secondary">取消</a>
                </div>
            </form>

            <?php if ($isEdit): ?>
            <div style="margin-top:26px;border-top:1px solid #1e293b;padding-top:18px;">
                <div style="font-size:14px;color:#facc15;font-weight:600;margin-bottom:12px;">
                    <i class="fas fa-users"></i> 参演模特（<?= count($castList) ?>）
                </div>

                <?php if (!empty($castList)): ?>
                <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
                    <thead>
                        <tr style="text-align:left;color:#64748b;font-size:12px;">
                            <th style="padding:6px 8px;">模特</th>
                            <th style="padding:6px 8px;">角色名</th>
                            <th style="padding:6px 8px;">主演</th>
                            <th style="padding:6px 8px;">排序</th>
                            <th style="padding:6px 8px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($castList as $c): ?>
                        <tr style="border-top:1px solid #1e293b;color:#e2e8f0;font-size:13.5px;">
                            <td style="padding:8px;">
                                <a href="models.php?edit=<?= intval($c['model_id']) ?>" style="color:#7dd3fc;">
                                    <?= htmlspecialchars($c['nickname'] ?? ('#' . intval($c['model_id']))) ?>
                                </a>
                                <?php if (($c['model_status'] ?? '') === 'inactive'): ?>
                                    <small style="color:#f87171;">（已停用）</small>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px;"><?= htmlspecialchars($c['role_name'] ?: '—') ?></td>
                            <td style="padding:8px;"><?= !empty($c['is_lead']) ? '✅' : '—' ?></td>
                            <td style="padding:8px;"><?= intval($c['sort_order']) ?></td>
                            <td style="padding:8px;">
                                <form method="post" style="display:inline;" onsubmit="return confirm('确认移除该参演关系？');">
                                    <input type="hidden" name="action" value="detach">
                                    <input type="hidden" name="id" value="<?= intval($editDrama['id']) ?>">
                                    <input type="hidden" name="model_id" value="<?= intval($c['model_id']) ?>">
                                    <button type="submit" class="admin-btn admin-btn-secondary" style="padding:4px 10px;font-size:12px;">移除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div style="color:#64748b;font-size:13px;margin-bottom:16px;">暂无参演模特。</div>
                <?php endif; ?>

                <div style="font-size:13px;color:#94a3b8;margin-bottom:8px;">添加参演模特（输入模特昵称搜索，选择后填写角色名）：</div>
                <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="action" value="attach">
                    <input type="hidden" name="id" value="<?= intval($editDrama['id']) ?>">
                    <div>
                        <label style="<?= $labelStyle ?>">模特 ID</label>
                        <input type="number" name="model_id" min="1" required placeholder="模特 ID" style="<?= $inputStyle ?>width:130px;">
                    </div>
                    <div>
                        <label style="<?= $labelStyle ?>">角色名</label>
                        <input type="text" name="role_name" maxlength="100" placeholder="饰演角色" style="<?= $inputStyle ?>width:150px;">
                    </div>
                    <div>
                        <label style="<?= $labelStyle ?>">主演</label>
                        <input type="checkbox" name="is_lead" value="1" style="width:18px;height:18px;">
                    </div>
                    <div>
                        <label style="<?= $labelStyle ?>">排序</label>
                        <input type="number" name="sort_order" value="0" style="<?= $inputStyle ?>width:80px;">
                    </div>
                    <button type="submit" class="admin-btn admin-btn-primary">添加</button>
                </form>
                <small style="display:block;color:#64748b;margin-top:8px;">
                    提示：也可以在「<a href="models.php" style="color:#7dd3fc;">模特管理</a>」编辑模特时批量勾选其参演短剧。
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 搜索 / 筛选 -->
    <div class="admin-card" style="margin-bottom:20px;">
        <div class="admin-card-body">
            <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索剧名..." style="<?= $inputStyle ?>max-width:260px;">
                <select name="status" style="<?= $inputStyle ?>max-width:140px;">
                    <option value="">全部状态</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>上架</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>下架</option>
                </select>
                <button type="submit" class="admin-btn admin-btn-primary">搜索</button>
                <?php if ($search || $status): ?>
                    <a href="dramas.php" class="admin-btn admin-btn-secondary">重置</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 列表 -->
    <div class="admin-card">
        <div class="admin-card-body">
            <?php if (empty($dramas)): ?>
                <div style="text-align:center;color:#64748b;padding:40px 0;">暂无短剧数据</div>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left;color:#64748b;font-size:12px;border-bottom:1px solid #1e293b;">
                        <th style="padding:10px 8px;">封面</th>
                        <th style="padding:10px 8px;">剧名</th>
                        <th style="padding:10px 8px;">集数</th>
                        <th style="padding:10px 8px;">题材</th>
                        <th style="padding:10px 8px;">参演模特</th>
                        <th style="padding:10px 8px;">状态</th>
                        <th style="padding:10px 8px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dramas as $d): ?>
                    <tr style="border-bottom:1px solid #1e293b;color:#e2e8f0;font-size:13.5px;">
                        <td style="padding:10px 8px;">
                            <?php if (!empty($d['cover'])): ?>
                                <img src="../<?= htmlspecialchars($d['cover']) ?>" style="width:38px;height:50px;object-fit:cover;border-radius:4px;">
                            <?php else: ?>
                                <span style="color:#475569;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px 8px;">
                            <a href="<?= htmlspecialchars(SeoHelper::dramaUrl($d['id'], $d['title'])) ?>" target="_blank" style="color:#7dd3fc;">
                                <?= htmlspecialchars($d['title']) ?>
                            </a>
                        </td>
                        <td style="padding:10px 8px;"><?= $d['episodes'] ? intval($d['episodes']) . ' 集' : '—' ?></td>
                        <td style="padding:10px 8px;">
                            <?php foreach (array_slice($d['tags_arr'] ?? [], 0, 3) as $t): ?>
                                <span style="display:inline-block;background:#1e293b;color:#94a3b8;border-radius:999px;padding:1px 8px;font-size:11px;margin-right:4px;"><?= htmlspecialchars($t) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td style="padding:10px 8px;"><?= intval($d['model_count'] ?? 0) ?></td>
                        <td style="padding:10px 8px;">
                            <?= $d['status'] === 'active'
                                ? '<span style="color:#4ade80;">上架</span>'
                                : '<span style="color:#f87171;">下架</span>' ?>
                        </td>
                        <td style="padding:10px 8px;white-space:nowrap;">
                            <a href="?edit=<?= intval($d['id']) ?>" class="admin-btn admin-btn-secondary" style="padding:4px 10px;font-size:12px;">编辑</a>
                            <?php if ($d['status'] === 'active'): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确认下架该短剧？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= intval($d['id']) ?>">
                                    <button type="submit" class="admin-btn admin-btn-secondary" style="padding:4px 10px;font-size:12px;">下架</button>
                                </form>
                            <?php else: ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="id" value="<?= intval($d['id']) ?>">
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

<?php require_once '../../shared/admin/admin-footer.php'; ?>
