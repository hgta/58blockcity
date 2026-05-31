<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';

$nft = new NFT($pdo);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24;

$nfts = $nft->getAllNFTs($page, $perPage);
$total = $nft->getNFTCount();
$totalPages = ceil($total / $perPage);

$cities = $nft->getCities();
?>
<?php require_once '../includes/header.php'; ?>

<style>
.container { max-width:1200px; margin:0 auto; padding:20px; }
.page-title { font-size:24px; font-weight:bold; margin-bottom:20px; color:#333; }
.filters { background:white; border-radius:8px; padding:15px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); display:flex; gap:15px; flex-wrap:wrap; align-items:center; }
.nft-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; }
.nft-card { background:white; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.08); text-decoration:none; color:inherit; transition:all .3s; }
.nft-card:hover { transform:translateY(-4px); box-shadow:0 8px 25px rgba(0,0,0,0.12); }
.nft-card img { width:100%; height:200px; object-fit:cover; }
.nft-info { padding:15px; }
.nft-info h3 { font-size:16px; margin-bottom:8px; }
.nft-meta { font-size:12px; color:#666; margin-bottom:8px; }
.nft-price { font-size:18px; color:#ff6b00; font-weight:bold; }
.pagination { display:flex; justify-content:center; gap:8px; margin-top:25px; }
.pagination a { padding:8px 14px; border:1px solid #ddd; border-radius:6px; color:#333; text-decoration:none; }
.pagination a.active { background:#ff6b00; color:white; border-color:#ff6b00; }
.empty-state { text-align:center; padding:60px; color:#999; }
@media(max-width:992px){ .nft-grid{grid-template-columns:repeat(3,1fr)} }
@media(max-width:768px){ .nft-grid{grid-template-columns:repeat(2,1fr)} }
@media(max-width:480px){ .nft-grid{grid-template-columns:1fr} }
</style>

<div class="container">
    <h1 class="page-title"><i class="fas fa-store"></i> NFT头像市场</h1>
    
    <div class="filters">
        <span style="color:#666;">筛选：</span>
        <?php foreach ($cities as $c): ?>
            <a href="?city=<?= $c['id'] ?>" style="padding:5px 12px;border:1px solid #ddd;border-radius:20px;font-size:13px;color:#666;text-decoration:none;"><?= htmlspecialchars($c['name']) ?></a>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($nfts)): ?>
        <div class="empty-state">
            <i class="fas fa-image" style="font-size:48px;display:block;margin-bottom:15px;"></i>
            <p>暂无在售NFT头像</p>
        </div>
    <?php else: ?>
        <div class="nft-grid">
            <?php foreach ($nfts as $item): ?>
                <a href="view.php?id=<?= $item['id'] ?>" class="nft-card">
                    <img src="<?= htmlspecialchars($item['image_url'] ?? '../assets/images/default-nft.jpg') ?>" 
                         alt="<?= htmlspecialchars($item['name']) ?>"
                         onerror="this.src='../assets/images/default-nft.jpg'">
                    <div class="nft-info">
                        <h3><?= htmlspecialchars($item['name']) ?></h3>
                        <div class="nft-meta"><?= htmlspecialchars($item['city_name'] ?? '') ?> · <?= $item['rarity'] ?? '普通' ?></div>
                        <div class="nft-price">¥<?= number_format($item['price'] ?? 0, 2) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i=1; $i<=$totalPages; $i++): ?>
                <a href="?page=<?=$i?>" class="<?=$i==$page?'active':''?>"><?=$i?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
