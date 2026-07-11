<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/NFT.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php'); exit;
}

$nft = new NFT($pdo);
$msg = '';
$edit = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$formData = $edit ? $nft->getNftWithTags($edit) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $data = [
        'code'       => trim($_POST['code'] ?? ''),
        'base_image' => trim($_POST['base_image'] ?? ''),
        'avatar_id'  => trim($_POST['avatar_id'] ?? ''),
        'avatar_key' => trim($_POST['avatar_key'] ?? ''),
    ];

    if (empty($data['code'])) {
        $msg = '<div class="admin-alert admin-alert-error">编号为必填</div>';
    } else {
        // 处理标签：已有 tag_ids + 新建 new_tags → 合并为 tag_ids
        $tagIds = array_map('intval', $_POST['tag_ids'] ?? []);
        $newTags = array_filter(array_map('trim', $_POST['new_tags'] ?? []));

        foreach ($newTags as $tagName) {
            if ($tagName === '') continue;
            // 查一下是否已存在（防御性）
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([$tagName]);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                $tagIds[] = (int)$existing;
            } else {
                $tagIds[] = (int)$nft->createTag($tagName);
            }
        }
        $tagIds = array_unique($tagIds);
        $data['tag_ids'] = array_values($tagIds);

        if ($edit) {
            $nft->updateNft($edit, $data);
            $msg = '<div class="admin-alert admin-alert-success">NFT 已更新</div>';
            $formData = $nft->getNftWithTags($edit);
        } else {
            $nft->createNft($data);
            $msg = '<div class="admin-alert admin-alert-success">NFT 创建成功</div>';
            header('Location: nft_form.php?edit=' . $pdo->lastInsertId());
            exit;
        }
    }
}

// 当前 NFT 的标签
$currentTags = [];
$currentTagIds = [];
if ($formData && !empty($formData['tags'])) {
    $currentTags = $formData['tags'];
    $currentTagIds = array_column($formData['tags'], 'id');
}

// 全部标签（给前端做 autocomplete）
$allTags = $nft->getTagsWithCount();

$admin_site_config = ['site' => 'nft', 'page_title' => $edit ? '编辑NFT #' . $edit : '新增NFT'];
require_once '../../shared/admin/admin-header.php';

$is = 'width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;';
$ls = 'display:block;font-size:13px;color:#94a3b8;margin-bottom:4px;';
?>
<style>
.tag-wrapper { margin-bottom:16px; }
.tag-current { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px; min-height:32px; }
.tag-badge {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 10px; background:#1e293b; border:1px solid #334155;
    border-radius:6px; font-size:13px; color:#e2e8f0;
}
.tag-badge .tag-remove {
    cursor:pointer; color:#94a3b8; font-size:14px; line-height:1;
    transition:color 0.15s;
}
.tag-badge .tag-remove:hover { color:#ef4444; }
.tag-input-wrap { position:relative; }
.tag-input {
    width:100%; padding:9px 12px; background:#0f172a;
    border:1px solid #334155; border-radius:6px; color:#e2e8f0; font-size:14px;
    outline:none; transition:border-color 0.2s;
}
.tag-input:focus { border-color:#ff6b00; }
.tag-dropdown {
    position:absolute; top:100%; left:0; right:0;
    background:#1e293b; border:1px solid #334155; border-top:none;
    border-radius:0 0 6px 6px; max-height:200px; overflow-y:auto;
    z-index:100; display:none;
}
.tag-dropdown.show { display:block; }
.tag-dropdown-item {
    padding:9px 12px; cursor:pointer; font-size:13px; color:#e2e8f0;
    border-bottom:1px solid #0f172a; transition:background 0.1s;
    display:flex; justify-content:space-between;
}
.tag-dropdown-item:hover, .tag-dropdown-item.active { background:#0f172a; }
.tag-dropdown-item .count { color:#94a3b8; font-size:11px; }
.tag-dropdown-item.new-tag { color:#60a5fa; }
.tag-dropdown-item.new-tag .plus { font-weight:bold; }
.tag-dropdown-empty { padding:10px 12px; font-size:13px; color:#94a3b8; }
</style>

<div class="admin-content">
<div class="admin-page-header">
    <h1><?= $edit ? '编辑 NFT #' . $edit : '新增 NFT' ?></h1>
    <a href="nfts.php" class="admin-btn admin-btn-secondary">← 返回列表</a>
</div>
<?= $msg ?>

<div class="admin-card">
<div class="admin-card-body">
<form method="post" id="nftForm">

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
    <div>
        <label style="<?= $ls ?>">编号 <span style="color:#ef4444">*</span></label>
        <input type="text" name="code" value="<?= htmlspecialchars($formData['code'] ?? '') ?>" required style="<?= $is ?>">
    </div>
    <div>
        <label style="<?= $ls ?>">头像图片文件名</label>
        <input type="text" name="base_image" value="<?= htmlspecialchars($formData['base_image'] ?? '') ?>" placeholder="例: 898.svg" style="<?= $is ?>">
        <?php if (!empty($formData['base_image'])): ?>
        <img src="../avatar/<?= htmlspecialchars($formData['base_image']) ?>" style="width:60px;height:60px;border-radius:8px;margin-top:6px;">
        <?php endif; ?>
    </div>
    <div>
        <label style="<?= $ls ?>">Avatar ID</label>
        <input type="text" name="avatar_id" value="<?= htmlspecialchars($formData['avatar_id'] ?? '') ?>" style="<?= $is ?>">
    </div>
    <div>
        <label style="<?= $ls ?>">Avatar Key</label>
        <input type="text" name="avatar_key" value="<?= htmlspecialchars($formData['avatar_key'] ?? '') ?>" style="<?= $is ?>">
    </div>
</div>

<!-- 标签 -->
<div class="tag-wrapper">
    <label style="<?= $ls ?>">标签</label>

    <!-- 当前标签列表 -->
    <div class="tag-current" id="currentTags">
        <?php foreach ($currentTags as $t): ?>
        <span class="tag-badge" data-tag-id="<?= $t['id'] ?>" data-tag-name="<?= htmlspecialchars($t['name']) ?>">
            <?= htmlspecialchars($t['name']) ?>
            <span class="tag-remove" onclick="removeTag(this)">×</span>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- 标签输入 + 下拉建议 -->
    <div class="tag-input-wrap">
        <input type="text" class="tag-input" id="tagInput" 
               placeholder="输入标签名搜索或新建…" autocomplete="off">
        <div class="tag-dropdown" id="tagDropdown"></div>
    </div>
    <small style="color:#64748b;margin-top:4px;display:block;">输入关键词自动匹配已有标签；没有匹配时可新建</small>

    <!-- 隐藏字段：已有标签ID -->
    <div id="hiddenTagIds">
        <?php foreach ($currentTagIds as $id): ?>
        <input type="hidden" name="tag_ids[]" value="<?= $id ?>">
        <?php endforeach; ?>
    </div>
</div>

<button type="submit" name="save" class="admin-btn admin-btn-primary"><?= $edit?'更新':'创建' ?></button>
<a href="nfts.php" class="admin-btn admin-btn-secondary">取消</a>
</form>
</div>
</div>
</div>

<script>
// 全部标签数据
var allTags = <?= json_encode($allTags) ?>;
// 当前选中的标签 ID 集合
var selectedTagIds = new Set(<?= json_encode(array_values($currentTagIds)) ?>);

var input = document.getElementById('tagInput');
var dropdown = document.getElementById('tagDropdown');
var currentTags = document.getElementById('currentTags');
var hiddenIds = document.getElementById('hiddenTagIds');

// 输入事件：搜索匹配
var debounceTimer;
input.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(searchTags, 150);
});

input.addEventListener('focus', function() {
    if (input.value.trim() !== '') searchTags();
});

// 点击其他地方关闭下拉
document.addEventListener('click', function(e) {
    if (!e.target.closest('.tag-input-wrap')) {
        dropdown.classList.remove('show');
    }
});

// 删除标签
function removeTag(el) {
    var badge = el.closest('.tag-badge');
    var tagId = parseInt(badge.dataset.tagId);
    selectedTagIds.delete(tagId);
    badge.remove();
    // 移除对应的 hidden input
    var h = hiddenIds.querySelector('input[value="' + tagId + '"]');
    if (h) h.remove();
}

// 添加已有标签
function addExistingTag(tagId, tagName) {
    if (selectedTagIds.has(tagId)) return;
    selectedTagIds.add(tagId);

    // 添加到显示
    var span = document.createElement('span');
    span.className = 'tag-badge';
    span.dataset.tagId = tagId;
    span.dataset.tagName = tagName;
    span.innerHTML = tagName + '<span class="tag-remove" onclick="removeTag(this)">×</span>';
    currentTags.appendChild(span);

    // 添加到隐藏字段
    var inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = 'tag_ids[]';
    inp.value = tagId;
    hiddenIds.appendChild(inp);

    // 清理
    input.value = '';
    dropdown.classList.remove('show');
}

// 新建标签
function createNewTag(tagName) {
    tagName = tagName.trim();
    if (!tagName) return;
    // 新建的标签先以 new_xxx 形式加入，后端会创建
    var span = document.createElement('span');
    span.className = 'tag-badge';
    span.dataset.tagName = tagName;
    span.innerHTML = tagName + '<span class="tag-remove" onclick="removeNewTag(this)">×</span>';
    currentTags.appendChild(span);

    // 作为新建标签提交
    var inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = 'new_tags[]';
    inp.value = tagName;
    inp.dataset.tagName = tagName;
    hiddenIds.appendChild(inp);

    input.value = '';
    dropdown.classList.remove('show');
}

// 删除新建的标签
function removeNewTag(el) {
    var badge = el.closest('.tag-badge');
    var tagName = badge.dataset.tagName;
    badge.remove();
    var h = hiddenIds.querySelector('input[data-tag-name="' + tagName.replace(/"/g, '\\"') + '"]');
    if (h) h.remove();
}

function searchTags() {
    var q = input.value.trim().toLowerCase();
    dropdown.innerHTML = '';

    if (q === '') {
        dropdown.classList.remove('show');
        return;
    }

    // 过滤匹配标签（排除已选的）
    var matches = allTags.filter(function(t) {
        return t.name.toLowerCase().indexOf(q) !== -1 && !selectedTagIds.has(parseInt(t.id));
    });
    var exactMatch = allTags.some(function(t) {
        return t.name.toLowerCase() === q;
    });

    if (matches.length === 0 && exactMatch) {
        // 精确匹配但已被选中或已选完
        dropdown.innerHTML = '<div class="tag-dropdown-empty">没有更多匹配标签</div>';
    } else {
        matches.slice(0, 8).forEach(function(t) {
            var div = document.createElement('div');
            div.className = 'tag-dropdown-item';
            div.innerHTML = '<span>' + t.name + '</span><span class="count">' + t.nft_count + '个NFT</span>';
            div.onclick = function() { addExistingTag(parseInt(t.id), t.name); };
            dropdown.appendChild(div);
        });

        // 如果没有精确匹配，显示新建选项
        if (!exactMatch) {
            var newDiv = document.createElement('div');
            newDiv.className = 'tag-dropdown-item new-tag';
            newDiv.innerHTML = '<span><span class="plus">+</span> 新建 "' + q + '"</span>';
            newDiv.onclick = function() { createNewTag(q); };
            dropdown.appendChild(newDiv);
        }
    }

    dropdown.classList.add('show');
}

// 键盘导航
var activeIdx = -1;
input.addEventListener('keydown', function(e) {
    var items = dropdown.querySelectorAll('.tag-dropdown-item');
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx = Math.min(activeIdx + 1, items.length - 1);
        updateActive(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx = Math.max(activeIdx - 1, 0);
        updateActive(items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIdx >= 0 && items[activeIdx]) {
            items[activeIdx].click();
        } else if (input.value.trim()) {
            createNewTag(input.value.trim());
        }
        activeIdx = -1;
    }
});

function updateActive(items) {
    items.forEach(function(el, i) {
        el.classList.toggle('active', i === activeIdx);
    });
}
</script>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
