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
        'tag_ids'    => $_POST['tag_ids'] ?? [],
    ];
    if (empty($data['code'])) {
        $msg = '<div class="admin-alert admin-alert-error">编号为必填</div>';
    } elseif ($edit) {
        $nft->updateNft($edit, $data);
        $msg = '<div class="admin-alert admin-alert-success">NFT 已更新</div>';
        $formData = $nft->getNftWithTags($edit);
    } else {
        $nft->createNft($data);
        $msg = '<div class="admin-alert admin-alert-success">NFT 创建成功</div>';
    }
}

$allTags = $nft->getTagsWithCount();
$currentTagIds = [];
if ($formData && !empty($formData['tags'])) {
    $currentTagIds = array_column($formData['tags'], 'id');
}

$admin_site_config = ['site' => 'nft', 'page_title' => $edit?'编辑NFT':'新增NFT'];
require_once '../../shared/admin/admin-header.php';

$is = 'width:100%;padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;';
$ls = 'display:block;font-size:13px;color:#94a3b8;margin-bottom:4px;';
?>

<div class="admin-content">
<div class="admin-page-header">
    <h1><?= $edit ? '编辑 NFT #' . $edit : '新增 NFT' ?></h1>
    <a href="nfts.php" class="admin-btn admin-btn-secondary">← 返回列表</a>
</div>
<?= $msg ?>

<div class="admin-card">
<div class="admin-card-body">
<form method="post">

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
<div style="margin-bottom:16px;">
    <label style="<?= $ls ?>">标签</label>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
        <?php foreach ($allTags as $t): ?>
        <label style="cursor:pointer;display:flex;align-items:center;gap:4px;font-size:13px;color:#94a3b8;">
            <input type="checkbox" name="tag_ids[]" value="<?= $t['id'] ?>" <?= in_array($t['id'], $currentTagIds)?'checked':'' ?>> <?= htmlspecialchars($t['name']) ?>
        </label>
        <?php endforeach; ?>
    </div>
    <small style="color:#64748b;">标签在 <a href="tags.php" style="color:#60a5fa;">标签管理</a> 中维护</small>
</div>

<button type="submit" name="save" class="admin-btn admin-btn-primary"><?= $edit?'更新':'创建' ?></button>
<a href="nfts.php" class="admin-btn admin-btn-secondary">取消</a>
</form>
</div>
</div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
