<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';

$nft = new NFT($pdo);

// 筛选参数
$searchCode = trim($_GET['code'] ?? '');
$searchTag  = $_GET['tag'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 48;

// 数据
$nfts = $nft->getAllNfts($perPage, ($page - 1) * $perPage, $searchCode, $searchTag);
$total = $nft->getTotalNftCount($searchCode, $searchTag);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = max(1, min($page, $totalPages));

$allTags = $nft->getAllTags();

// 分页基址（保留筛选参数）
$keep = $_GET;
unset($keep['page']);
$qs = http_build_query($keep);
$baseUrl = '?' . ($qs ? $qs . '&' : '');
?>
<?php require_once '../includes/header.php'; ?>

<style>
/* ===== 顶部一栏式信息栏 ===== */
.mk-hero {
    background: linear-gradient(135deg, #ff6b00, #e55a00);
    color: #fff;
    padding: 12px 18px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 3px 15px rgba(255,107,0,0.2);
}
.mk-hero h1 {
    font-size: 18px;
    margin: 0;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.mk-stats { display: flex; gap: 18px; flex-wrap: wrap; }
.mk-stat { font-size: 13px; opacity: 0.95; }
.mk-stat strong { font-size: 17px; font-weight: 800; margin-right: 2px; }

/* ===== 紧凑筛选栏 ===== */
.mk-filters {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    padding: 12px 14px;
    background: #f8f9fa;
    border: 1px solid #eee;
    border-radius: 10px;
    margin-bottom: 16px;
}
.mk-filters input,
.mk-filters select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    background: #fff;
    color: #333;
}
.mk-filters input:focus,
.mk-filters select:focus {
    border-color: #ff6b00;
    box-shadow: 0 0 0 3px rgba(255,107,0,0.08);
}
.mk-filters input { flex: 1; min-width: 160px; }
.mk-btn {
    padding: 8px 18px;
    background: #ff6b00;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}
.mk-btn:hover { background: #e55a00; }
.mk-reset {
    padding: 8px 14px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    color: #666;
    font-size: 14px;
    text-decoration: none;
}
.mk-reset:hover { border-color: #ff6b00; color: #ff6b00; text-decoration: none; }

/* ===== 卡片网格 ===== */
.nft-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 10px;
}
.nft-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    text-decoration: none;
    color: inherit;
    transition: transform 0.2s, box-shadow 0.2s;
    display: block;
}
.nft-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 28px rgba(0,0,0,0.12);
    color: inherit;
    text-decoration: none;
}
.nft-card img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    display: block;
    background: #f5f5f5;
}
.nft-info { padding: 10px 12px 12px; text-align: center; }
.nft-code {
    font-size: 14px;
    font-weight: 700;
    color: #ff6b00;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.nft-meta { font-size: 12px; color: #999; margin-top: 4px; }

/* ===== 分页 ===== */
.mk-pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
    margin: 26px 0 40px;
}
.mk-pagination a,
.mk-pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    color: #555;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.15s;
}
.mk-pagination a:hover {
    background: #fff3f0;
    border-color: #ff6b00;
    color: #ff6b00;
    text-decoration: none;
}
.mk-pagination .active {
    background: linear-gradient(135deg, #ff6b00, #f97316);
    border-color: transparent;
    color: #fff;
    font-weight: 700;
}
.mk-pagination .disabled { color: #ccc; background: #fafafa; pointer-events: none; }

/* ===== 空状态 ===== */
.mk-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #999;
}
.mk-empty i { font-size: 48px; display: block; margin-bottom: 16px; opacity: 0.35; }

@media (max-width: 480px) {
    .nft-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .mk-filters input { min-width: 100%; }
}
</style>

<div class="container">
    <!-- 顶部信息栏 -->
    <div class="mk-hero">
        <h1><i class="fas fa-store"></i> NFT头像市场</h1>
        <div class="mk-stats">
            <div class="mk-stat"><strong><?= number_format($total) ?></strong> 个头像</div>
            <div class="mk-stat"><strong><?= $totalPages ?></strong> 页</div>
        </div>
    </div>

    <!-- 搜索筛选 -->
    <form method="get" class="mk-filters">
        <input type="text" name="code" placeholder="🔍 搜索编号..." value="<?= htmlspecialchars($searchCode) ?>">
        <select name="tag">
            <option value="">全部标签</option>
            <?php foreach ($allTags as $tag): ?>
            <option value="<?= htmlspecialchars($tag) ?>" <?= $searchTag === $tag ? 'selected' : '' ?>><?= htmlspecialchars($tag) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="mk-btn"><i class="fas fa-search"></i> 搜索</button>
        <?php if ($searchCode || $searchTag): ?>
        <a href="marketplace.php" class="mk-reset"><i class="fas fa-sync-alt"></i> 重置</a>
        <?php endif; ?>
    </form>

    <!-- NFT 网格 -->
    <div class="nft-grid">
        <?php if (empty($nfts)): ?>
        <div class="mk-empty">
            <i class="fas fa-image"></i>
            <p>暂无相关NFT头像</p>
            <a href="claim_list.php" class="mk-btn" style="display:inline-block;margin-top:12px;text-decoration:none;">浏览可认领头像</a>
        </div>
        <?php else: foreach ($nfts as $item): ?>
        <a href="view.php?id=<?= (int)$item['id'] ?>" class="nft-card">
            <img src="../avatar/<?= htmlspecialchars($item['base_image']) ?>"
                 alt="NFT <?= htmlspecialchars($item['code']) ?>"
                 loading="lazy">
            <div class="nft-info">
                <div class="nft-code"><?= htmlspecialchars($item['code']) ?></div>
                <div class="nft-meta">查看详情</div>
            </div>
        </a>
        <?php endforeach; endif; ?>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
    <nav class="mk-pagination">
        <?php if ($page > 1): ?>
        <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
        <?php else: ?>
        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
        <?php endif; ?>

        <?php
        $last = 0;
        $window = 2;
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == 1 || $i == $totalPages || abs($i - $page) <= $window) {
                if ($last && $i - $last > 1) echo '<span class="disabled">…</span>';
                $last = $i;
                if ($i == $page) echo '<span class="active">' . $i . '</span>';
                else echo '<a href="' . $baseUrl . 'page=' . $i . '">' . $i . '</a>';
            }
        }
        ?>

        <?php if ($page < $totalPages): ?>
        <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
        <?php else: ?>
        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
