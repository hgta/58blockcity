<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/City.php';

$nft = new NFT($pdo);
$city = new City($pdo);

// 搜索参数
$searchCode = $_GET['code'] ?? '';
$searchTag = $_GET['tag'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 200; // 每页200个，更紧凑

// 获取NFT列表（带搜索条件）
$nfts = $nft->getAllNfts($perPage, ($page - 1) * $perPage, $searchCode, $searchTag);
$totalNfts = $nft->getTotalNftCount($searchCode, $searchTag);
$totalPages = ceil($totalNfts / $perPage);
$page = max(1, min($page, $totalPages));

// 获取所有标签用于筛选
$allTags = $nft->getAllTags();
?>

<?php require_once '../includes/header.php'; ?>

<style>
/* ===== 顶部一栏式信息栏 ===== */
.cl-hero {
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

.cl-hero h1 {
    font-size: 18px;
    margin: 0;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cl-stats { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
.cl-stat { font-size: 13px; opacity: 0.95; }
.cl-stat strong { font-size: 17px; font-weight: 800; margin-right: 2px; }

.cl-hero-link {
    font-size: 13px;
    color: #fff;
    border: 1px solid rgba(255,255,255,0.5);
    padding: 4px 12px;
    border-radius: 16px;
    text-decoration: none;
    transition: background 0.2s;
}
.cl-hero-link:hover { background: rgba(255,255,255,0.18); color: #fff; }

@media (max-width: 576px) {
    .cl-hero { padding: 12px 16px; }
    .cl-stats { gap: 14px; }
    .cl-search input { min-width: 100%; }
}

/* 每行固定8个头像 */
.nft-grid-10 {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

/* 保持原有的NFT卡片样式 */
.nft-item {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 2px solid #ff6b00;
    overflow: hidden;
    text-align: center;
    padding: 15px 10px;
}

.nft-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 107, 0, 0.2);
    border-color: #ff8c00;
}

/* 圆形头像容器 */
.nft-avatar-circle {
    width: 100px;
    height: 100px;
    margin: 0 auto 15px;
    border: 3px solid #ff6b00;
    border-radius: 50%;
    padding: 5px;
    background: white;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nft-avatar-circle img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    border-radius: 50%;
}

/* 编码始终显示在下方 */
.nft-code {
    font-size: 1.1rem;
    font-weight: 700;
    color: #ff6b00;
    margin-bottom: 10px;
    text-align: center;
    padding: 5px 0;
    background: #fff9f0;
    border-radius: 8px;
    border: 1px solid #ffd8b3;
}

/* 操作按钮 */
.nft-action-btn {
    width: 100%;
    padding: 8px 12px;
    font-size: 0.9rem;
    font-weight: 600;
    border-radius: 8px;
    background: linear-gradient(135deg, #ff6b00 0%, #ff8c00 100%);
    color: white;
    border: none;
    transition: all 0.3s ease;
    text-decoration: none;
    display: block;
    text-align: center;
}

.nft-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 107, 0, 0.3);
    background: linear-gradient(135deg, #ff8c00 0%, #ff6b00 100%);
    color: white;
    text-decoration: none;
}

/* 分页样式 */
.pagination-section {
    background: #f8f9fa;
    padding: 18px;
    border-radius: 10px;
    margin: 25px 0;
    border: 1px solid #e9ecef;
}

.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.page-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.page-select {
    width: 70px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 5px 8px;
    font-size: 0.9rem;
    cursor: pointer;
}

.page-select:focus {
    outline: none;
    border-color: #ff6b00;
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.btn-pagination {
    background: white;
    border: 1px solid #dee2e6;
    color: #495057;
    padding: 7px 14px;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
}

.btn-pagination:hover {
    background: #ff6b00;
    color: white;
    border-color: #ff6b00;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(255, 107, 0, 0.2);
}

.btn-pagination.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
}

.stats-summary {
    text-align: center;
    color: #6c757d;
    font-size: 0.85rem;
    margin-top: 6px;
}

/* 搜索栏样式（紧凑单行） */
.cl-search {
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

.cl-search input,
.cl-search select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    background: #fff;
    color: #333;
}

.cl-search input:focus,
.cl-search select:focus {
    border-color: #ff6b00;
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.08);
}

.cl-search input { flex: 1; min-width: 160px; }

.cl-btn {
    padding: 8px 18px;
    background: #ff6b00;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.cl-btn:hover { background: #e55a00; }

.cl-reset {
    padding: 8px 14px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    color: #666;
    font-size: 14px;
    text-decoration: none;
}

.cl-reset:hover { border-color: #ff6b00; color: #ff6b00; text-decoration: none; }

/* 空状态样式 */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-icon {
    font-size: 3.5rem;
    margin-bottom: 20px;
    opacity: 0.4;
    color: #adb5bd;
}

.empty-state h3 {
    color: #495057;
    margin-bottom: 10px;
    font-weight: 600;
}

.empty-state p {
    color: #adb5bd;
    font-size: 0.95rem;
}


/* 固定每行8个，不因屏幕放大而增加 */</style>

<div class="container">
    <!-- 顶部一栏式信息栏 -->
    <div class="cl-hero">
        <h1><i class="fas fa-hand-holding-heart"></i> NFT头像认领列表</h1>
        <div class="cl-stats">
            <div class="cl-stat"><strong><?= number_format($totalNfts) ?></strong> 个头像</div>
            <div class="cl-stat"><strong><?= $totalPages ?></strong> 页</div>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/user/collection.php" class="cl-hero-link"><i class="fas fa-user"></i> 我的收藏</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 搜索和筛选栏 -->
    <form method="get" class="cl-search">
        <input type="text" name="code" placeholder="🔍 输入编号(如AB01)"
               value="<?= htmlspecialchars($searchCode) ?>">
        <select name="tag">
            <option value="">全部标签</option>
            <?php foreach ($allTags as $tag): ?>
                <option value="<?= htmlspecialchars($tag) ?>"
                    <?= $searchTag === $tag ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tag) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="cl-btn"><i class="fas fa-search"></i> 搜索</button>
        <?php if ($searchCode || $searchTag): ?>
            <a href="claim_list.php" class="cl-reset"><i class="fas fa-sync-alt"></i> 重置</a>
        <?php endif; ?>
    </form>
    
    
    <!-- NFT列表 - 一行显示10个头像，圆形边框 -->
    <div class="nft-grid-10">
        <?php if (empty($nfts)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-image"></i>
                </div>
                <h3>没有找到符合条件的NFT头像</h3>
                <p>尝试修改搜索条件或查看其他页面</p>
            </div>
        <?php else: ?>
            <?php foreach ($nfts as $item) { ?>
                <div class="nft-item">
                    <div class="nft-avatar-circle">
                        <img src="../avatar/<?= htmlspecialchars($item['base_image']) ?>" 
                             alt="NFT <?= htmlspecialchars($item['code']) ?>"
                             loading="lazy">
                    </div>
                    <div class="nft-code"><?= htmlspecialchars($item['code']) ?></div>
                    <a href="/nft/claim_detail.php?id=<?= $item['id'] ?>" 
                       class="nft-action-btn">
                        认领
                    </a>
                </div>
            <?php } ?>
        <?php endif; ?>
    </div>
    
    <!-- 底部分页导航 -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-section">
            <div class="pagination-container">
                <!-- 上一页 -->
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                   class="btn-pagination <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left"></i> 上一页
                </a>
                
                <!-- 页码信息 -->
                <div class="page-info">
                    <span>第</span>
                    <select class="form-select page-select" onchange="location.href='?<?= 
                        http_build_query(array_diff_key($_GET, ['page' => ''])) ?>&page='+this.value">
                        <?php for ($i = 1; $i <= $totalPages; $i++) { ?>
                            <option value="<?= $i ?>" <?= $i == $page ? 'selected' : '' ?>><?= $i ?></option>
                        <?php } ?>
                    </select>
                    <span>页，共 <?= $totalPages ?> 页</span>
                </div>
                
                <!-- 下一页 -->
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                   class="btn-pagination <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    下一页 <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <!-- 统计信息 -->
            <div class="stats-summary">
                显示 <?= min(($page - 1) * $perPage + 1, $totalNfts) ?>-<?= min($page * $perPage, $totalNfts) ?> 个头像，共 <?= $totalNfts ?> 个头像
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>