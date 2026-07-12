<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';
require_once '../../classes/SeoHelper.php';

$nft = new NFT($pdo);
$tag = $_GET['tag'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24;

$nfts = $nft->getAllNfts($perPage, ($page - 1) * $perPage, '', $tag);
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:1200px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; color:#333; }
.tag-badge { display:inline-block; background:#ff6b00; color:white; padding:4px 14px; border-radius:20px; font-size:14px; margin-bottom:20px; }
.nft-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; }
.nft-card { background:white; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.08); text-decoration:none; color:inherit; transition:all .3s; }
.nft-card:hover { transform:translateY(-4px); box-shadow:0 8px 25px rgba(0,0,0,0.12); }
.nft-card img { width:100%; height:200px; object-fit:cover; }
.nft-info { padding:15px; }
.nft-info h3 { font-size:16px; margin-bottom:8px; }
.nft-meta { font-size:12px; color:#666; }
.nft-price { font-size:18px; color:#ff6b00; font-weight:bold; margin-top:5px; }
.empty-state { text-align:center; padding:60px; color:#999; }
@media(max-width:992px){ .nft-grid{grid-template-columns:repeat(3,1fr)} }
@media(max-width:768px){ .nft-grid{grid-template-columns:repeat(2,1fr)} }
@media(max-width:480px){ .nft-grid{grid-template-columns:1fr} }
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
                    <img src="<?= SeoHelper::fullUrl($item['image_url'] ?? $item['main_image'] ?? '') ?>" 
                         alt="<?= htmlspecialchars($item['name']) ?>"
                         loading="lazy">
                    <div class="nft-info">
                        <h3><?= htmlspecialchars($item['name']) ?></h3>
                        <div class="nft-meta"><?= htmlspecialchars($item['city_name'] ?? '') ?></div>
                        <div class="nft-price">¥<?= number_format($item['price'] ?? 0, 2) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
