<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';

$nft = new NFT($pdo);
$tag = $_GET['tag'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24;

$nfts = $nft->getAllNfts($perPage, ($page - 1) * $perPage, '', $tag);
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:1200px; margin:0 auto; padding:20px; }
.page-title { font-size:22px; font-weight:bold; margin-bottom:16px; color:#1a1a2e; display:flex; align-items:center; gap:10px; }
.tag-badge { display:inline-block; background:#ff6b00; color:white; padding:4px 14px; border-radius:20px; font-size:13px; margin-bottom:16px; }
.nft-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; }
.nft-card { background:white; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.06); text-decoration:none; color:inherit; transition:all .2s; display:flex; flex-direction:column; }
.nft-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,0.1); }
.nft-card img { width:100%; aspect-ratio:1; object-fit:cover; display:block; background:#f5f5f5; }
.nft-info { padding:10px 12px; flex:1; }
.nft-info h3 { font-size:14px; margin-bottom:4px; color:#333; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.nft-meta { font-size:11px; color:#999; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.nft-price { font-size:14px; color:#ff6b00; font-weight:600; margin-top:4px; }
.empty-state { text-align:center; padding:60px; color:#999; }
@media(max-width:992px){ .nft-grid{grid-template-columns:repeat(4,1fr)} }
@media(max-width:768px){ .nft-grid{grid-template-columns:repeat(3,1fr)} }
@media(max-width:480px){ .nft-grid{grid-template-columns:repeat(2,1fr); gap:8px } .nft-info{padding:8px 10px} .nft-info h3{font-size:12px} }
</style>

<div class="container">
    <h1 class="page-title">
        <i class="fas fa-list"></i>
        <?= $tag ? "标签: " : "全部NFT头像" ?>
    </h1>
    
    <?php if ($tag): ?>
        <span class="tag-badge"><?= htmlspecialchars($tag) ?></span>
    <?php endif; ?>
    
    <?php if (empty($nfts)): ?>
        <div class="empty-state">
            <i class="fas fa-images" style="font-size:48px;display:block;margin-bottom:15px;"></i>
            <p>暂无相关NFT头像</p>
            <a href="claim_list.php" style="display:inline-block;margin-top:15px;padding:10px 20px;background:#ff6b00;color:white;border-radius:6px;text-decoration:none;">浏览可认领头像</a>
        </div>
    <?php else: ?>
        <div class="nft-grid">
            <?php foreach ($nfts as $item): ?>
                <a href="view.php?id=<?= $item['id'] ?>" class="nft-card">
                    <img src="../avatar/<?= htmlspecialchars($item['base_image']) ?>" 
                         alt="<?= htmlspecialchars($item['name']) ?>"
                         loading="lazy">
                    <div class="nft-info">
                        <h3><?= htmlspecialchars($item['name'] ?? $item['code'] ?? '') ?></h3>
                        <div class="nft-meta">#<?= htmlspecialchars($item['code'] ?? '') ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
