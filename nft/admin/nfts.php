<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/NFT.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php'); exit;
}

$nft = new NFT($pdo);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$search = trim($_GET['search'] ?? '');
$tagId = intval($_GET['tag_id'] ?? 0);

// POST 操作
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = intval($_POST['id'] ?? 0);
    if ($_POST['action'] === 'delete' && $id > 0) {
        $msg = $nft->deleteNft($id) ? '<div class="admin-alert admin-alert-success">NFT 已删除</div>' : '<div class="admin-alert admin-alert-error">删除失败</div>';
    }
    if ($_POST['action'] === 'delete_comment' && $id > 0) {
        // redirect to handle comment deletion
    }
}

// 评论操作
if (isset($_GET['del_comment'])) {
    $cid = intval($_GET['del_comment']);
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$cid]);
    $msg = '<div class="admin-alert admin-alert-success">评论已删除</div>';
}

$data = $nft->getAllNftsAdmin($page, $perPage, $search, $tagId);
$nfts = $data['list'];
$total = $data['total'];
$totalPages = $data['pages'];
$allTags = $nft->getTagsWithCount();

// 查看单个 NFT 详情（求购/售卖）
$detailNft = null;
$detailPurchases = [];
$detailSales = [];
$detailComments = [];
if (isset($_GET['view'])) {
    $vid = intval($_GET['view']);
    $detailNft = $nft->getNftWithTags($vid);
    $stmt = $pdo->prepare("SELECT pr.*, u.username FROM nft_purchase_requests pr JOIN users u ON pr.user_id = u.id WHERE pr.nft_id = ? ORDER BY pr.created_at DESC LIMIT 50");
    $stmt->execute([$vid]);
    $detailPurchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT s.*, u.username FROM nft_sales s JOIN users u ON s.seller_id = u.id WHERE s.nft_id = ? ORDER BY s.created_at DESC LIMIT 50");
    $stmt->execute([$vid]);
    $detailSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.nft_id = ? ORDER BY c.created_at DESC LIMIT 50");
    $stmt->execute([$vid]);
    $detailComments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$admin_site_config = ['site' => 'nft', 'page_title' => 'NFT管理'];
require_once '../../shared/admin/admin-header.php';

$inputStyle = 'padding:8px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;font-size:14px;';
?>

<div class="admin-content">
<div class="admin-page-header">
    <h1>NFT 管理</h1>
    <a href="nft_form.php" class="admin-btn admin-btn-primary">+ 新增 NFT</a>
</div>

<?= $msg ?>

<?php if ($detailNft): ?>
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header"><span class="admin-card-title">NFT 详情: <?= htmlspecialchars($detailNft['code']) ?></span> <a href="nfts.php" style="color:#94a3b8;font-size:13px;">关闭</a></div>
    <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
            <div>
                <strong>编号:</strong> <?= htmlspecialchars($detailNft['code']) ?><br>
                <strong>Avatar ID:</strong> <?= htmlspecialchars($detailNft['avatar_id']) ?><br>
                <strong>Avatar Key:</strong> <?= htmlspecialchars($detailNft['avatar_key']) ?><br>
                <strong>创建:</strong> <?= $detailNft['created_at'] ?>
            </div>
            <div>
                <strong>图片:</strong><br>
                <?php if ($detailNft['base_image']): ?>
                <img src="../avatar/<?= htmlspecialchars($detailNft['base_image']) ?>" style="width:100px;height:100px;border-radius:8px;object-fit:cover;">
                <?php else: ?><span style="color:#64748b;">无</span><?php endif; ?>
            </div>
            <div>
                <strong>标签:</strong>
                <?php foreach ($detailNft['tags'] as $t): ?>
                <span style="display:inline-block;padding:2px 8px;background:#1e293b;border-radius:4px;font-size:12px;margin:2px;"><?= htmlspecialchars($t['name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <hr style="border-color:#1e293b;margin:16px 0;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
            <div><h4>求购 (<?= count($detailPurchases) ?>)</h4>
                <?php foreach ($detailPurchases as $p): ?>
                <div style="font-size:13px;color:#94a3b8;padding:4px 0;">@<?= htmlspecialchars($p['username']) ?> Ⓟ<?= number_format($p['price']) ?> <?= $p['status'] ?></div>
                <?php endforeach; ?>
            </div>
            <div><h4>售卖 (<?= count($detailSales) ?>)</h4>
                <?php foreach ($detailSales as $s): ?>
                <div style="font-size:13px;color:#94a3b8;padding:4px 0;">@<?= htmlspecialchars($s['username']) ?> Ⓟ<?= number_format($s['price']) ?></div>
                <?php endforeach; ?>
            </div>
            <div><h4>评论 (<?= count($detailComments) ?>)</h4>
                <?php foreach ($detailComments as $c): ?>
                <div style="font-size:13px;color:#94a3b8;padding:4px 0;border-bottom:1px solid #1e293b;">@<?= htmlspecialchars($c['username']) ?>: <?= htmlspecialchars(mb_substr($c['content'],0,30)) ?>
                <a href="?del_comment=<?= $c['id'] ?>" onclick="return confirm('确定删除?')" style="color:#ef4444;font-size:11px;">删除</a></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 搜索 -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索编号或 Avatar ID" style="<?= $inputStyle ?>min-width:200px;">
            <select name="tag_id" style="<?= $inputStyle ?>">
                <option value="">全部标签</option>
                <?php foreach ($allTags as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $tagId==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['name']) ?> (<?= $t['nft_count'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <button class="admin-btn admin-btn-primary admin-btn-sm">搜索</button>
            <a href="nfts.php" class="admin-btn admin-btn-secondary admin-btn-sm">重置</a>
        </form>
    </div>
</div>

<!-- NFT 列表 -->
<div class="admin-card">
    <div class="admin-card-header"><span class="admin-card-title">NFT 列表 (<?= $total ?>)</span></div>
    <div class="admin-card-body" style="padding:0;">
        <table class="admin-table">
            <thead><tr>
                <th>ID</th><th>图片</th><th>编号</th><th>求购</th><th>售卖</th><th>评论</th><th>操作</th>
            </tr></thead>
            <tbody>
            <?php if (empty($nfts)): ?>
            <tr><td colspan="7" style="text-align:center;padding:30px;">暂无数据</td></tr>
            <?php else: foreach ($nfts as $n): ?>
            <tr>
                <td><?= $n['id'] ?></td>
                <td><?php if($n['base_image']): ?><img src="../avatar/<?= htmlspecialchars($n['base_image']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"><?php endif; ?></td>
                <td><strong><?= htmlspecialchars($n['code']) ?></strong></td>
                <td><?= $n['buy_count'] ?></td>
                <td><?= $n['sale_count'] ?></td>
                <td><?= $n['comment_count'] ?></td>
                <td>
                    <a href="?view=<?= $n['id'] ?>" class="admin-btn admin-btn-sm">查看</a>
                    <a href="nft_form.php?edit=<?= $n['id'] ?>" class="admin-btn admin-btn-sm">编辑</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('确定删除该NFT?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $n['id'] ?>">
                        <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">删除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<div style="text-align:center;margin-top:20px;">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tag_id=<?= $tagId ?>" class="admin-btn admin-btn-sm <?= $i==$page?'admin-btn-primary':'admin-btn-secondary' ?>" style="margin:0 2px;"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
