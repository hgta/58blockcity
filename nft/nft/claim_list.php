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
/* 一行显示的头部样式 */
.page-header-one-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    background: linear-gradient(135deg, #ff6b00 0%, #ff8c00 100%);
    color: white;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 3px 15px rgba(255, 107, 0, 0.2);
}

.page-header-one-line h1 {
    font-size: 1.5rem;
    margin: 0;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.page-header-one-line .lead {
    font-size: 0.9rem;
    margin: 0;
    opacity: 0.9;
    flex-grow: 1;
    margin-left: 15px;
    font-weight: 300;
}

/* 一行显示10个头像的网格 */
.nft-grid-10 {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
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

/* 搜索栏样式 */
.search-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    margin-bottom: 25px;
    background: #f8f9fa;
}

.search-card .card-body {
    padding: 20px;
}

/* 统计信息样式 */
.stats-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: white;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.stats-count {
    color: #495057;
    font-weight: 500;
}

.stats-count .badge {
    background: linear-gradient(135deg, #ff6b00, #ff8c00);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.9rem;
}

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

/* 响应式设计 - 保持一行10个在桌面端，移动端自适应 */
@media (max-width: 1400px) {
    .nft-grid-10 {
        grid-template-columns: repeat(8, 1fr);
        gap: 12px;
    }
}

@media (max-width: 1200px) {
    .nft-grid-10 {
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
    }
}

@media (max-width: 992px) {
    .nft-grid-10 {
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
    }
    
    .nft-avatar-circle {
        width: 90px;
        height: 90px;
    }
}

@media (max-width: 768px) {
    .page-header-one-line {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        padding: 15px;
    }
    
    .page-header-one-line .lead {
        margin-left: 0;
        font-size: 0.85rem;
    }
    
    .nft-grid-10 {
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
    }
    
    .nft-avatar-circle {
        width: 80px;
        height: 80px;
    }
    
    .nft-code {
        font-size: 1rem;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 12px;
    }
    
    .search-card .card-body {
        padding: 15px;
    }
}

@media (max-width: 576px) {
    .nft-grid-10 {
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }
    
    .nft-avatar-circle {
        width: 70px;
        height: 70px;
    }
    
    .nft-item {
        padding: 12px 8px;
    }
    
    .nft-code {
        font-size: 0.9rem;
    }
    
    .nft-action-btn {
        padding: 6px 8px;
        font-size: 0.85rem;
    }
    
    .page-header-one-line h1 {
        font-size: 1.3rem;
    }
    
    .stats-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
}

@media (max-width: 400px) {
    .nft-grid-10 {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="container">
    <!-- 一行显示的头部 -->
    <div class="page-header-one-line">
        <h1><i class="fas fa-hand-holding-heart"></i> NFT头像认领列表</h1>
        <p class="lead">寻找您在BlockCity拥有的NFT头像，填入区块信息认领后可挂售展示并接收求购信息。</p>
    </div>
    
    <!-- 搜索和筛选栏 -->
    <div class="card search-card">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="codeSearch" class="form-label">编号查询</label>
                    <input type="text" class="form-control" id="codeSearch" name="code" 
                           placeholder="输入编号(如AB01)" value="<?= htmlspecialchars($searchCode) ?>">
                </div>
                <div class="col-md-4">
                    <label for="tagFilter" class="form-label">标签筛选</label>
                    <select class="form-select" id="tagFilter" name="tag">
                        <option value="">所有标签</option>
                        <?php foreach ($allTags as $tag): ?>
                            <option value="<?= htmlspecialchars($tag) ?>" 
                                <?= $searchTag === $tag ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tag) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> 搜索
                    </button>
                    <a href="claim_list.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt"></i> 重置
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 统计信息 -->
    <div class="stats-header">
        <div class="stats-count">
            <i class="fas fa-image me-1"></i> 共 <span class="badge"><?= number_format($totalNfts) ?></span> 个NFT头像
            <?php if ($searchCode || $searchTag): ?>
                <span class="text-primary ms-2">
                    (筛选结果: <?= count($nfts) ?>)
                </span>
            <?php endif; ?>
        </div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/user/collection.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-user me-1"></i> 我的收藏
            </a>
        <?php endif; ?>
    </div>
    
    <!-- 顶部分页导航 -->
    <?php if ($totalPages > 1  && false): ?>
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
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == $page ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
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