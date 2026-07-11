<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/NFT.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php'); exit;
}

$nft = new NFT($pdo);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = intval($_POST['id'] ?? 0);
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name && $nft->createTag($name)) {
            $msg = '<div class="admin-alert admin-alert-success">标签已添加</div>';
        }
    } elseif ($_POST['action'] === 'update' && $id > 0) {
        $nft->updateTag($id, trim($_POST['name'] ?? ''));
        $msg = '<div class="admin-alert admin-alert-success">标签已更新</div>';
    } elseif ($_POST['action'] === 'delete' && $id > 0) {
        $nft->deleteTag($id);
        $msg = '<div class="admin-alert admin-alert-success">标签已删除</div>';
    }
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$tagData = $nft->getTagsForAdmin($page, $perPage);
$tags = $tagData['list'];
$totalTags = $tagData['total'];
$totalTagPages = $tagData['pages'];
$editTag = null;
if (isset($_GET['edit'])) {
    foreach ($tags as $t) { if ($t['id'] == intval($_GET['edit'])) { $editTag = $t; break; } }
}

$admin_site_config = ['site' => 'nft', 'page_title' => '标签管理'];
require_once '../../shared/admin/admin-header.php';
$is = 'padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;';
?>

<div class="admin-content">
<div class="admin-page-header">
    <h1>标签管理</h1>
</div>
<?= $msg ?>

<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body">
        <form method="post" style="display:flex;gap:10px;align-items:center;">
            <input type="hidden" name="action" value="<?= $editTag ? 'update' : 'add' ?>">
            <input type="hidden" name="id" value="<?= $editTag ? $editTag['id'] : '' ?>">
            <input type="text" name="name" value="<?= htmlspecialchars($editTag['name'] ?? '') ?>" placeholder="标签名" required style="<?= $is ?>min-width:200px;">
            <button class="admin-btn admin-btn-primary admin-btn-sm"><?= $editTag ? '更新' : '新增' ?></button>
            <?php if ($editTag): ?><a href="tags.php" class="admin-btn admin-btn-secondary admin-btn-sm">取消</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">标签列表</span>
        <span class="admin-page-info">共 <?= $totalTags ?> 个标签</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <div class="admin-table-responsive">
        <table class="admin-data-table">
            <thead><tr><th>ID</th><th>标签名</th><th>关联NFT数</th><th>操作</th></tr></thead>
            <tbody>
            <?php if (empty($tags)): ?>
            <tr><td colspan="4" class="admin-empty-state" style="padding:30px;">暂无标签</td></tr>
            <?php else: foreach ($tags as $t): ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                <td><span class="admin-badge info"><?= $t['nft_count'] ?></span></td>
                <td>
                    <div class="admin-btn-group">
                        <a href="?edit=<?= $t['id'] ?>" class="admin-btn admin-btn-sm">编辑</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('确定删除?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button class="admin-btn admin-btn-sm admin-btn-danger">删除</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php if ($totalTagPages > 1): ?>
<nav class="admin-pagination">
    <?php
    $qs = $editTag ? '&edit=' . intval($editTag['id']) : '';
    $showPrev = $page > 1;
    $showNext = $page < $totalTagPages;
    ?>
    <?php if ($showPrev): ?><a href="?page=<?= $page - 1 ?><?= $qs ?>"><i class="fas fa-chevron-left"></i></a>
    <?php else: ?><span class="disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
    <?php
    $last = 0;
    $window = 2;
    for ($i = 1; $i <= $totalTagPages; $i++) {
        if ($i == 1 || $i == $totalTagPages || abs($i - $page) <= $window) {
            if ($last && $i - $last > 1) echo '<span class="disabled">…</span>';
            $last = $i;
            if ($i == $page) echo '<span class="current">' . $i . '</span>';
            else echo '<a href="?page=' . $i . $qs . '">' . $i . '</a>';
        }
    }
    ?>
    <?php if ($showNext): ?><a href="?page=<?= $page + 1 ?><?= $qs ?>"><i class="fas fa-chevron-right"></i></a>
    <?php else: ?><span class="disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
</nav>
<?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
