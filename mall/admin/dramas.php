<?php
/**
 * 短剧管理（模特子站 model.58.tl 的内容源）
 */
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Drama.php';
require_once '../../classes/Model.php';
require_once '../../classes/Actor.php';
require_once '../../classes/SeoHelper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$drama = new Drama($pdo);
$model = new Model($pdo);
$actor = new Actor($pdo);

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
    } elseif ($_POST['action'] === 'detachcredit' && $dramaId > 0) {
        // 按参演关系 ID 移除（模特演员 / 非模特演员通用）
        $drama->detachCredit(intval($_POST['credit_id'] ?? 0));
        $actionMsg = '<div class="admin-alert admin-alert-success">已移除该演职人员</div>';
    } elseif ($_POST['action'] === 'attachactor' && $dramaId > 0) {
        // 关联演员表成员（可复用、带头像）
        $actorId = intval($_POST['actor_id'] ?? 0);
        if ($actorId <= 0) {
            $actionMsg = '<div class="admin-alert admin-alert-error">请先搜索并选择演员</div>';
        } else {
            $res = $drama->attachActorRecord(
                $dramaId,
                $actorId,
                trim($_POST['actor_role'] ?? ''),
                !empty($_POST['actor_lead']) ? 1 : 0,
                intval($_POST['actor_sort'] ?? 0)
            );
            $actorInfo = $actor->getById($actorId);
            $actionMsg = $res
                ? '<div class="admin-alert admin-alert-success">已添加演员「' . htmlspecialchars($actorInfo['nickname'] ?? '') . '」</div>'
                : '<div class="admin-alert admin-alert-error">添加失败：该演员已在本剧参演</div>';
        }
    } elseif ($_POST['action'] === 'createattachactor' && $dramaId > 0) {
        // 新建演员并直接加入本剧（一步完成）
        $newName = trim($_POST['new_actor_name'] ?? '');
        if ($newName === '') {
            $actionMsg = '<div class="admin-alert admin-alert-error">请填写演员姓名</div>';
        } else {
            $newId = $actor->create([
                'nickname' => $newName,
                'gender'   => $_POST['new_actor_gender'] ?? '保密',
                'status'   => 'active',
            ]);
            if ($newId <= 0) {
                $actionMsg = '<div class="admin-alert admin-alert-error">创建失败：已存在同名演员，请改用上方搜索</div>';
            } else {
                $drama->attachActorRecord(
                    $dramaId,
                    $newId,
                    trim($_POST['new_actor_role'] ?? ''),
                    !empty($_POST['new_actor_lead']) ? 1 : 0,
                    intval($_POST['new_actor_sort'] ?? 0)
                );
                $actionMsg = '<div class="admin-alert admin-alert-success">已新建演员「' . htmlspecialchars($newName) . '」并加入本剧（可到<a href="actors.php?edit=' . $newId . '" style="color:#7dd3fc;">演员表</a>补充头像）</div>';
            }
        }
    } elseif ($_POST['action'] === 'updatecredit' && $dramaId > 0) {
        // 逐行更新角色名 / 主演 / 排序
        $creditIds = (array)($_POST['credit_id'] ?? []);
        $ok = 0;
        foreach ($creditIds as $cid) {
            $cid = intval($cid);
            if ($cid <= 0) {
                continue;
            }
            $drama->updateCredit(
                $cid,
                trim($_POST['credit_role'][$cid] ?? ''),
                !empty($_POST['credit_lead'][$cid]) ? 1 : 0,
                intval($_POST['credit_sort'][$cid] ?? 0)
            );
            $ok++;
        }
        $actionMsg = '<div class="admin-alert admin-alert-success">已更新 ' . $ok . ' 条参演信息</div>';
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

/* ---------- AJAX：统一搜索「模特 + 演员」（供添加演职人员用，避免手填 ID） ---------- */
if (isset($_GET['ajax']) && in_array($_GET['ajax'], ['search_cast', 'search_models'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $kw = trim($_GET['q'] ?? '');
    if (mb_strlen($kw) < 1) {
        echo json_encode(['list' => []]);
        exit;
    }
    $out = [];

    // 模特库成员
    $found = $model->getList(1, 10, $kw);
    foreach ($found['list'] as $m) {
        $out[] = [
            'type'     => 'model',
            'id'       => intval($m['id']),
            'nickname' => $m['nickname'],
            'city'     => $m['city'] ?? '',
            'gender'   => $m['gender'] ?? '',
            'avatar'   => $m['avatar'] ? ('../' . $m['avatar']) : '',
            'status'   => $m['status'] ?? 'active',
        ];
    }

    // 演员表成员
    foreach ($actor->searchByNickname($kw, 10) as $a) {
        $out[] = [
            'type'     => 'actor',
            'id'       => intval($a['id']),
            'nickname' => $a['nickname'],
            'city'     => $a['city'] ?? '',
            'gender'   => $a['gender'] ?? '',
            'avatar'   => $a['avatar'] ? (preg_match('#^https?://#i', $a['avatar']) ? $a['avatar'] : '../' . ltrim($a['avatar'], '/')) : '',
            'status'   => $a['status'] ?? 'active',
        ];
    }

    echo json_encode(['list' => $out]);
    exit;
}

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
                <form method="post">
                    <input type="hidden" name="action" value="updatecredit">
                    <input type="hidden" name="id" value="<?= intval($editDrama['id']) ?>">
                    <table style="width:100%;border-collapse:collapse;margin-bottom:12px;">
                        <thead>
                            <tr style="text-align:left;color:#64748b;font-size:12px;">
                                <th style="padding:6px 8px;">演职人员</th>
                                <th style="padding:6px 8px;">角色名</th>
                                <th style="padding:6px 8px;">主要演员</th>
                                <th style="padding:6px 8px;">排序</th>
                                <th style="padding:6px 8px;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($castList as $c): $cid = intval($c['id']); $isModel = !empty($c['model_id']); ?>
                            <tr style="border-top:1px solid #1e293b;color:#e2e8f0;font-size:13.5px;">
                                <td style="padding:8px;white-space:nowrap;">
                                    <input type="hidden" name="credit_id[]" value="<?= $cid ?>">
                                    <?php
                                    // 头像：模特取 models.avatar，演员取 actors.avatar（可能是外链）
                                    $castAvatar = '';
                                    if ($isModel && !empty($c['avatar'])) {
                                        $castAvatar = '../' . ltrim($c['avatar'], '/');
                                    } elseif (($c['cast_type'] ?? '') === 'actor' && !empty($c['actor_avatar'])) {
                                        $av = $c['actor_avatar'];
                                        $castAvatar = preg_match('#^https?://#i', $av) ? $av : '../' . ltrim($av, '/');
                                    }
                                    ?>
                                    <?php if ($castAvatar): ?>
                                        <img src="<?= htmlspecialchars($castAvatar) ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:6px;background:#1e293b;">
                                    <?php else: ?>
                                        <span style="display:inline-flex;width:30px;height:30px;border-radius:50%;background:#1e293b;align-items:center;justify-content:center;color:#475569;vertical-align:middle;margin-right:6px;"><i class="fas fa-user" style="font-size:13px;"></i></span>
                                    <?php endif; ?>
                                    <?php if ($isModel): ?>
                                        <a href="models.php?edit=<?= intval($c['model_id']) ?>" style="color:#7dd3fc;">
                                            <?= htmlspecialchars($c['nickname'] ?? ('#' . intval($c['model_id']))) ?>
                                        </a>
                                        <span style="display:inline-block;margin-left:5px;padding:1px 7px;border-radius:999px;background:#1e3a8a;color:#93c5fd;font-size:11px;">模特</span>
                                        <?php if (($c['model_status'] ?? '') === 'inactive'): ?>
                                            <small style="color:#f87171;">（已停用）</small>
                                        <?php endif; ?>
                                    <?php elseif (($c['cast_type'] ?? '') === 'actor'): ?>
                                        <a href="actors.php?edit=<?= intval($c['actor_id']) ?>" style="color:#fbbf24;">
                                            <?= htmlspecialchars($c['actor_nickname'] ?? '') ?>
                                        </a>
                                        <span style="display:inline-block;margin-left:5px;padding:1px 7px;border-radius:999px;background:#3f2d1a;color:#fbbf24;font-size:11px;">演员</span>
                                        <?php if (($c['actor_status'] ?? '') === 'inactive'): ?>
                                            <small style="color:#f87171;">（已停用）</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;"><?= htmlspecialchars($c['actor_name'] ?? '') ?></span>
                                        <span style="display:inline-block;margin-left:5px;padding:1px 7px;border-radius:999px;background:#334155;color:#94a3b8;font-size:11px;">无档案</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:8px;">
                                    <input type="text" name="credit_role[<?= $cid ?>]" maxlength="100"
                                           value="<?= htmlspecialchars($c['role_name'] ?? '') ?>" placeholder="饰演角色名"
                                           style="<?= $inputStyle ?>max-width:180px;">
                                </td>
                                <td style="padding:8px;">
                                    <label style="display:flex;align-items:center;gap:5px;color:#94a3b8;font-size:13px;cursor:pointer;white-space:nowrap;">
                                        <input type="checkbox" name="credit_lead[<?= $cid ?>]" value="1"
                                               style="width:17px;height:17px;" <?= !empty($c['is_lead']) ? 'checked' : '' ?>>
                                        主要演员
                                    </label>
                                </td>
                                <td style="padding:8px;">
                                    <input type="number" name="credit_sort[<?= $cid ?>]" value="<?= intval($c['sort_order']) ?>"
                                           title="数值小者靠前" style="<?= $inputStyle ?>width:80px;">
                                </td>
                                <td style="padding:8px;">
                                    <button type="button" class="admin-btn admin-btn-secondary" style="padding:4px 10px;font-size:12px;"
                                            onclick="detachCreditForm(<?= intval($editDrama['id']) ?>, <?= $cid ?>)">移除</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="admin-btn admin-btn-primary">保存参演信息</button>
                    <small style="display:block;color:#64748b;margin-top:8px;">
                        主要演员（主演）会在短剧详情页靠前展示，并标注「主要演员」；排序数值越小番位越靠前。
                    </small>
                </form>
                <?php else: ?>
                    <div style="color:#64748b;font-size:13px;margin-bottom:16px;">暂无演职人员。</div>
                <?php endif; ?>

                <!-- 添加参演模特：按昵称搜索选择，无需手填 ID -->
                <div style="margin-top:20px;border-top:1px solid #1e293b;padding-top:16px;">
                    <div style="font-size:13px;color:#7dd3fc;margin-bottom:8px;">添加模特库成员（输入昵称搜索，可跳转其主页）：</div>
                    <div style="position:relative;max-width:520px;">
                        <input type="text" id="model-search-input" autocomplete="off"
                               placeholder="输入模特昵称，如「言」" style="<?= $inputStyle ?>">
                        <div id="model-search-results"
                             style="display:none;position:absolute;left:0;right:0;top:100%;z-index:30;margin-top:4px;
                                    background:#0f172a;border:1px solid #334155;border-radius:8px;max-height:300px;overflow-y:auto;
                                    box-shadow:0 12px 30px rgba(0,0,0,.5);"></div>
                    </div>

                    <form method="post" id="attach-form" style="display:none;margin-top:14px;padding:14px;background:#0b1220;border:1px solid #334155;border-radius:8px;">
                        <input type="hidden" name="action" value="attach">
                        <input type="hidden" name="id" value="<?= intval($editDrama['id']) ?>">
                        <input type="hidden" name="model_id" id="attach-model-id" value="">
                        <div style="font-size:13px;color:#e2e8f0;margin-bottom:10px;">
                            已选择：<b id="attach-model-name" style="color:#f472b6;"></b>
                            <a href="#" onclick="resetAttach();return false;" style="color:#64748b;margin-left:8px;font-size:12px;">重新选择</a>
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                            <div>
                                <label style="<?= $labelStyle ?>">角色名</label>
                                <input type="text" name="role_name" maxlength="100" placeholder="饰演角色" style="<?= $inputStyle ?>width:170px;">
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">主要演员</label>
                                <label style="display:flex;align-items:center;gap:5px;color:#94a3b8;font-size:13px;height:38px;cursor:pointer;">
                                    <input type="checkbox" name="is_lead" value="1" style="width:17px;height:17px;"> 是
                                </label>
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">排序</label>
                                <input type="number" name="sort_order" value="0" style="<?= $inputStyle ?>width:90px;">
                            </div>
                            <button type="submit" class="admin-btn admin-btn-primary">添加参演</button>
                        </div>
                    </form>
                    <small style="display:block;color:#64748b;margin-top:8px;">
                        提示：也可以在「<a href="models.php" style="color:#7dd3fc;">模特管理</a>」编辑模特时批量勾选其参演短剧。
                    </small>
                </div>

                <!-- 添加演员表成员：搜索已有（可复用）/ 新建并加入 -->
                <div style="margin-top:18px;border-top:1px solid #1e293b;padding-top:16px;">
                    <div style="font-size:13px;color:#fbbf24;margin-bottom:8px;">
                        添加演员（从演员表搜索复用，或新建一个）：
                    </div>

                    <!-- 方式一：搜索已有演员 -->
                    <div style="position:relative;max-width:520px;margin-bottom:10px;">
                        <input type="text" id="actor-search-input" autocomplete="off"
                               placeholder="输入演员姓名搜索…" style="<?= $inputStyle ?>">
                        <div id="actor-search-results"
                             style="display:none;position:absolute;left:0;right:0;top:100%;z-index:30;margin-top:4px;
                                    background:#0f172a;border:1px solid #334155;border-radius:8px;max-height:300px;overflow-y:auto;
                                    box-shadow:0 12px 30px rgba(0,0,0,.5);"></div>
                    </div>

                    <form method="post" id="attach-actor-form" style="display:none;margin-bottom:14px;padding:14px;background:#0b1220;border:1px solid #334155;border-radius:8px;">
                        <input type="hidden" name="action" value="attachactor">
                        <input type="hidden" name="id" value="<?= intval($editDrama['id']) ?>">
                        <input type="hidden" name="actor_id" id="attach-actor-id" value="">
                        <div style="font-size:13px;color:#e2e8f0;margin-bottom:10px;">
                            已选择：<b id="attach-actor-name" style="color:#fbbf24;"></b>
                            <a href="#" onclick="resetAttachActor();return false;" style="color:#64748b;margin-left:8px;font-size:12px;">重新选择</a>
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                            <div>
                                <label style="<?= $labelStyle ?>">角色名</label>
                                <input type="text" name="actor_role" maxlength="100" placeholder="饰演角色" style="<?= $inputStyle ?>width:170px;">
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">主要演员</label>
                                <label style="display:flex;align-items:center;gap:5px;color:#94a3b8;font-size:13px;height:38px;cursor:pointer;">
                                    <input type="checkbox" name="actor_lead" value="1" style="width:17px;height:17px;"> 是
                                </label>
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">排序</label>
                                <input type="number" name="actor_sort" value="0" style="<?= $inputStyle ?>width:90px;">
                            </div>
                            <button type="submit" class="admin-btn admin-btn-primary">加入本剧</button>
                        </div>
                    </form>

                    <!-- 方式二：新建演员并加入 -->
                    <details style="margin-top:6px;">
                        <summary style="cursor:pointer;color:#94a3b8;font-size:13px;outline:none;">找不到？新建一个演员并加入本剧</summary>
                        <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:12px;padding:14px;background:#0b1220;border:1px solid #334155;border-radius:8px;">
                            <input type="hidden" name="action" value="createattachactor">
                            <input type="hidden" name="id" value="<?= intval($editDrama['id']) ?>">
                            <div>
                                <label style="<?= $labelStyle ?>">演员姓名 <span style="color:#ef4444">*</span></label>
                                <input type="text" name="new_actor_name" maxlength="100" required placeholder="如 张小明" style="<?= $inputStyle ?>width:170px;">
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">性别</label>
                                <select name="new_actor_gender" style="<?= $inputStyle ?>width:90px;">
                                    <?php foreach (['女', '男', '保密'] as $g): ?>
                                        <option value="<?= $g ?>"><?= $g ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">角色名</label>
                                <input type="text" name="new_actor_role" maxlength="100" placeholder="饰演角色" style="<?= $inputStyle ?>width:150px;">
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">主要演员</label>
                                <label style="display:flex;align-items:center;gap:5px;color:#94a3b8;font-size:13px;height:38px;cursor:pointer;">
                                    <input type="checkbox" name="new_actor_lead" value="1" style="width:17px;height:17px;"> 是
                                </label>
                            </div>
                            <div>
                                <label style="<?= $labelStyle ?>">排序</label>
                                <input type="number" name="new_actor_sort" value="0" style="<?= $inputStyle ?>width:80px;">
                            </div>
                            <button type="submit" class="admin-btn admin-btn-primary">新建并加入</button>
                        </form>
                        <small style="display:block;color:#64748b;margin-top:8px;">
                            新建后可在「<a href="actors.php" style="color:#7dd3fc;">演员表管理</a>」中补充头像与简介；该演员之后可在其它短剧中直接搜索复用。
                        </small>
                    </details>
                </div>
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
                        <th style="padding:10px 8px;">演职人员</th>
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

<script>
/* ---------- 按昵称搜索模特并选择 ---------- */
(function () {
    var input = document.getElementById('model-search-input');
    var box   = document.getElementById('model-search-results');
    if (!input || !box) return;
    var timer = null;

    function render(list) {
        list = (list || []).filter(function (m) { return m.type === 'model'; });
        if (!list.length) {
            box.innerHTML = '<div style="padding:14px;color:#64748b;font-size:13px;">未找到匹配的模特</div>';
        } else {
            box.innerHTML = list.map(function (m) {
                var av = m.avatar
                    ? '<img src="' + m.avatar + '" style="width:34px;height:34px;border-radius:50%;object-fit:cover;">'
                    : '<span style="display:inline-flex;width:34px;height:34px;border-radius:50%;background:#1e293b;align-items:center;justify-content:center;color:#475569;"><i class="fas fa-user"></i></span>';
                var extra = [];
                if (m.gender) extra.push(m.gender);
                if (m.city)   extra.push(m.city);
                if (m.status === 'inactive') extra.push('已停用');
                return '<div class="m-search-item" data-id="' + m.id + '" data-name="' + m.nickname.replace(/"/g, '&quot;') + '" '
                     + 'style="display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;border-bottom:1px solid #1e293b;">'
                     + av
                     + '<div><div style="color:#e2e8f0;font-size:13.5px;">' + m.nickname + '</div>'
                     + '<div style="color:#64748b;font-size:12px;">' + extra.join(' · ') + '</div></div>'
                     + '</div>';
            }).join('');
        }
        box.style.display = 'block';
        box.querySelectorAll('.m-search-item').forEach(function (el) {
            el.addEventListener('click', function () {
                document.getElementById('attach-model-id').value = el.dataset.id;
                document.getElementById('attach-model-name').textContent = el.dataset.name;
                document.getElementById('attach-form').style.display = 'block';
                box.style.display = 'none';
                input.value = el.dataset.name;
            });
            el.addEventListener('mouseenter', function () { el.style.background = '#1e293b'; });
            el.addEventListener('mouseleave', function () { el.style.background = 'transparent'; });
        });
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 1) { box.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch('dramas.php?ajax=search_cast&q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (res) { render(res.list || []); })
                .catch(function () { box.style.display = 'none'; });
        }, 260);
    });

    document.addEventListener('click', function (e) {
        if (!box.contains(e.target) && e.target !== input) box.style.display = 'none';
    });
})();

/* ---------- 演员选择器：按姓名搜索已有演员 ---------- */
(function () {
    var input = document.getElementById('actor-search-input');
    var box   = document.getElementById('actor-search-results');
    if (!input || !box) return;
    var timer = null;

    function render(list) {
        var actors = (list || []).filter(function (m) { return m.type === 'actor'; });
        if (!actors.length) {
            box.innerHTML = '<div style="padding:14px;color:#64748b;font-size:13px;">未找到该演员，可在下方「新建一个演员」</div>';
        } else {
            box.innerHTML = actors.map(function (m) {
                var av = m.avatar
                    ? '<img src="' + m.avatar + '" style="width:34px;height:34px;border-radius:50%;object-fit:cover;background:#1e293b;">'
                    : '<span style="display:inline-flex;width:34px;height:34px;border-radius:50%;background:#1e293b;align-items:center;justify-content:center;color:#475569;"><i class="fas fa-user"></i></span>';
                var extra = [];
                if (m.gender) extra.push(m.gender);
                if (m.city)   extra.push(m.city);
                extra.push('参演 ' + m.drama_count + ' 部');
                if (m.status === 'inactive') extra.push('已停用');
                return '<div class="a-search-item" data-id="' + m.id + '" data-name="' + m.nickname.replace(/"/g, '&quot;') + '" '
                     + 'style="display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;border-bottom:1px solid #1e293b;">'
                     + av
                     + '<div><div style="color:#e2e8f0;font-size:13.5px;">' + m.nickname + '</div>'
                     + '<div style="color:#64748b;font-size:12px;">' + extra.join(' · ') + '</div></div>'
                     + '</div>';
            }).join('');
        }
        box.style.display = 'block';
        box.querySelectorAll('.a-search-item').forEach(function (el) {
            el.addEventListener('click', function () {
                document.getElementById('attach-actor-id').value = el.dataset.id;
                document.getElementById('attach-actor-name').textContent = el.dataset.name;
                document.getElementById('attach-actor-form').style.display = 'block';
                box.style.display = 'none';
                input.value = el.dataset.name;
            });
            el.addEventListener('mouseenter', function () { el.style.background = '#1e293b'; });
            el.addEventListener('mouseleave', function () { el.style.background = 'transparent'; });
        });
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 1) { box.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch('dramas.php?ajax=search_cast&q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (res) { render(res.list || []); })
                .catch(function () { box.style.display = 'none'; });
        }, 260);
    });

    document.addEventListener('click', function (e) {
        if (!box.contains(e.target) && e.target !== input) box.style.display = 'none';
    });
})();

function resetAttachActor() {
    document.getElementById('attach-actor-form').style.display = 'none';
    document.getElementById('attach-actor-id').value = '';
    var input = document.getElementById('actor-search-input');
    input.value = '';
    input.focus();
}

/* 重新选择模特 */
function resetAttach() {
    document.getElementById('attach-form').style.display = 'none';
    document.getElementById('attach-model-id').value = '';
    var input = document.getElementById('model-search-input');
    input.value = '';
    input.focus();
}

/* 移除演职人员（按参演关系 ID，模特/演员通用） */
function detachCreditForm(dramaId, creditId) {
    if (!confirm('确认移除该演职人员？')) return;
    var f = document.createElement('form');
    f.method = 'post';
    f.innerHTML = '<input type="hidden" name="action" value="detachcredit">'
                + '<input type="hidden" name="id" value="' + dramaId + '">'
                + '<input type="hidden" name="credit_id" value="' + creditId + '">';
    document.body.appendChild(f);
    f.submit();
}
</script>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
