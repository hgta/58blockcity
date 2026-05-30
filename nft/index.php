<?php
require_once '../config/database.php';
require_once '../classes/NFT.php';
require_once '../classes/User.php';
require_once '../classes/City.php';
require_once '../classes/PurchaseRequest.php';
require_once '../classes/Comment.php';
require_once '../includes/auth.php';

// 已移除登录检查: checkLogin();

$nft = new NFT($pdo);
$user = new User($pdo);
$cityObj = new City($pdo);
$purchaseRequest = new PurchaseRequest($pdo);
$comment = new Comment($pdo);

// 获取全站统计数据
$stats = $nft->getGlobalStats(); // 需要添加此方法到NFT类

// 分页设置
$nftsPerPage = 90; // 修改：每页显示90个NFT (15行 × 6个/行)
$page = max(1, (int)($_GET['page'] ?? 1));

// 获取分页的NFT数据
$totalNfts = $nft->getTotalTopNftsCount(); // 需要添加此方法到NFT类
$totalPages = ceil($totalNfts / $nftsPerPage);
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $nftsPerPage;

// 获取当前页的NFT数据
$topNfts = $nft->getPaginatedTopNfts($offset, $nftsPerPage); // 需要修改此方法
?>

<?php require_once 'includes/header.php'; ?>

<style>
.top-nfts-container {
    max-width: 1800px; /* 增加最大宽度以适应更多列 */
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
    padding: 40px 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.1)"><polygon points="0,0 1000,50 1000,100 0,100"/></svg>');
    background-size: cover;
}

.page-header h1 {
    font-size: 2.8rem;
    margin-bottom: 15px;
    font-weight: 800;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    position: relative;
}

.page-header .subtitle {
    font-size: 1.3rem;
    opacity: 0.95;
    font-weight: 300;
    position: relative;
}

/* 全局统计卡片样式 */
.global-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 25px 20px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border: 1px solid #f0f0f0;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.stat-card i {
    font-size: 2.5rem;
    margin-bottom: 15px;
    display: block;
}

.stat-card.claim-stat i { color: #48bb78; }
.stat-card.sale-stat i { color: #667eea; }
.stat-card.purchase-stat i { color: #ed8936; }
.stat-card.comment-stat i { color: #9f7aea; }

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #2d3748;
    display: block;
    line-height: 1.2;
}

.stat-label {
    font-size: 1rem;
    color: #718096;
    display: block;
    margin-top: 8px;
    font-weight: 500;
}

/* 修改网格布局为6列 */
.nfts-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr); /* 固定6列 */
    gap: 15px; /* 减小间隙以适应更多列 */
    margin-bottom: 40px;
}

/* 调整卡片尺寸以适应6列 */
.nft-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    border: 1px solid #f0f0f0;
    position: relative;
}

.nft-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
}

/* 调整图片容器高度 */
.nft-image-container {
    position: relative;
    height: 140px; /* 减小高度以适应6列 */
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nft-image {
    width: auto;
    height: auto;
    max-width: 85%;
    max-height: 85%;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.nft-card:hover .nft-image {
    transform: scale(1.05); /* 减小缩放比例 */
}

.nft-rank {
    position: absolute;
    top: 10px;
    right: 10px;
    background: linear-gradient(135deg, #FF6B6B, #FF8E53);
    color: white;
    width: 28px; /* 减小尺寸 */
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 12px; /* 减小字体 */
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
    z-index: 2;
}

/* 调整内容区域 */
.nft-content {
    padding: 15px; /* 减小内边距 */
}

.nft-code {
    font-size: 1rem; /* 减小字体 */
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 10px;
    text-align: center;
    line-height: 1.2;
    word-break: break-word;
    background-color: rgba(255,255,255,0.7);
}

/* 调整统计数据网格 */
.nft-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px; /* 减小间隙 */
    margin-bottom: 12px;
}

.stat-item {
    text-align: center;
    padding: 8px 6px; /* 减小内边距 */
    background: #f8fafc;
    border-radius: 8px;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.stat-item:hover {
    background: #edf2f7;
    transform: translateY(-2px);
}

.stat-value {
    font-size: 0.9rem; /* 减小字体 */
    font-weight: 800;
    color: #2d3748;
    display: block;
    line-height: 1.1;
}

.stat-label {
    font-size: 0.7rem; /* 减小字体 */
    color: #718096;
    display: block;
    margin-top: 3px;
    font-weight: 500;
}

.nft-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px; /* 减小间隙 */
    margin-bottom: 12px;
    justify-content: center;
    min-height: 20px;
}

.nft-tag {
    color: white;
    padding: 3px 8px; /* 减小内边距 */
    border-radius: 12px;
    font-size: 0.65rem; /* 减小字体 */
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
    line-height: 1;
    white-space: nowrap;
    position: static;
}

.nft-tag:hover {
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
    color: white;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.nft-actions {
    text-align: center;
}

.btn-view-details {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
    border: none;
    padding: 10px 16px; /* 减小内边距 */
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    width: 100%;
    font-size: 0.8rem; /* 减小字体 */
    box-shadow: 0 4px 15px rgba(72, 187, 120, 0.3);
}

.btn-view-details:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(72, 187, 120, 0.4);
    color: white;
}

/* 分页样式 */
.pagination-section {
    background: #f8fafc;
    padding: 25px;
    border-radius: 16px;
    margin-bottom: 40px;
    border: 1px solid #e2e8f0;
}

.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.page-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-select {
    width: 80px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.9rem;
}

.page-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-pagination {
    background: white;
    border: 1px solid #e2e8f0;
    color: #4a5568;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-pagination:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
    text-decoration: none;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.btn-pagination.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.stats-summary {
    text-align: center;
    color: #718096;
    font-size: 0.9rem;
    margin-top: 10px;
}

.ranking-criteria {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 40px;
    border: 1px solid #e2e8f0;
}

.ranking-criteria h3 {
    color: #2d3748;
    margin-bottom: 25px;
    text-align: center;
    font-weight: 700;
    font-size: 1.5rem;
}

.criteria-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
}

.criteria-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: transform 0.3s ease;
}

.criteria-item:hover {
    transform: translateY(-3px);
}

.criteria-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.criteria-item strong {
    color: #2d3748;
    font-size: 1rem;
}

.criteria-item .text-muted {
    font-size: 0.85rem;
    color: #718096;
    margin-top: 2px;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #718096;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    color: #4a5568;
    margin-bottom: 10px;
}

/* 响应式设计 */
@media (max-width: 1600px) {
    .nfts-grid {
        grid-template-columns: repeat(5, 1fr); /* 大屏幕下显示5列 */
    }
}

@media (max-width: 1400px) {
    .nfts-grid {
        grid-template-columns: repeat(5, 1fr); /* 中等屏幕下显示4列 */
    }
    
    .top-nfts-container {
        max-width: 1400px;
    }
}

@media (max-width: 1200px) {
    .nfts-grid {
        grid-template-columns: repeat(3, 1fr); /* 较小屏幕下显示3列 */
    }
    
    .top-nfts-container {
        max-width: 1200px;
    }
}

@media (max-width: 992px) {
    .nfts-grid {
        grid-template-columns: repeat(2, 1fr); /* 平板显示2列 */
        gap: 20px;
    }
    
    .top-nfts-container {
        padding: 15px;
        max-width: 100%;
    }
}

@media (max-width: 768px) {
    .top-nfts-container {
        padding: 15px;
    }
    
    .nfts-grid {
        grid-template-columns: repeat(2, 1fr); /* 手机显示2列 */
        gap: 15px;
    }
    
    .page-header {
        padding: 30px 20px;
        margin-bottom: 30px;
    }
    
    .page-header h1 {
        font-size: 2.2rem;
    }
    
    .page-header .subtitle {
        font-size: 1.1rem;
    }
    
    .criteria-list {
        grid-template-columns: 1fr;
    }
    
    .nft-stats {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .global-stats {
        grid-template-columns: repeat(2, 1fr); /* 手机显示2列统计卡片 */
        gap: 15px;
    }
    
    .stat-card {
        padding: 20px 15px;
    }
    
    .stat-value {
        font-size: 1.8rem;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 15px;
    }
}

@media (max-width: 576px) {
    .nfts-grid {
        grid-template-columns: 1fr; /* 小手机显示1列 */
        gap: 12px;
    }
    
    .nft-image-container {
        height: 160px; /* 单列时增大图片高度 */
    }
    
    .nft-content {
        padding: 20px; /* 单列时增大内边距 */
    }
    
    .nft-code {
        font-size: 1.2rem; /* 单列时增大字体 */
    }
    
    .nft-stats {
        grid-template-columns: repeat(2, 1fr); /* 单列时显示2列统计 */
        gap: 10px;
    }
    
    .stat-item {
        padding: 10px 8px;
    }
    
    .stat-value {
        font-size: 1.1rem;
    }
    
    .global-stats {
        grid-template-columns: 1fr; /* 小手机显示1列统计卡片 */
    }
}

@media (max-width: 480px) {
    .nft-image-container {
        height: 140px;
    }
    
    .nft-content {
        padding: 15px;
    }
    
    .nft-code {
        font-size: 1rem;
    }
    
    .stat-item {
        padding: 8px 6px;
    }
    
    .stat-value {
        font-size: 1rem;
    }
}

.nft-image {
    left: unset !important;
}
</style>
 

<div class="top-nfts-container">
    <!-- 页面标题 -->
    <!--<div class="page-header">
        <h1>🏆 NFT头像综合排行榜</h1>
        <div class="subtitle">基于认领城市数、售卖数、求购数、评论数综合计算</div>
    </div>-->

    <!-- 全站统计数据 -->
    <div class="global-stats">
        <div class="stat-card claim-stat">
            <i class="fas fa-city"></i>
            <span class="stat-label">全站头像认领 </span>
            <span class="stat-value"><?= $stats['total_claims'] ?? 0 ?></span>
        </div>
        <div class="stat-card sale-stat">
            <i class="fas fa-store"></i>
            <span class="stat-label">全站头像挂售 </span>
            <span class="stat-value"><?= $stats['total_sales'] ?? 0 ?></span>
        </div>
        <div class="stat-card purchase-stat">
            <i class="fas fa-hand-holding-usd"></i>
            <span class="stat-label">全站头像求购 </span>
            <span class="stat-value"><?= $stats['total_purchases'] ?? 0 ?></span>
        </div>
        <div class="stat-card comment-stat">
            <i class="fas fa-comments"></i>
            <span class="stat-label">全站评论 </span>
            <span class="stat-value"><?= $stats['total_comments'] ?? 0 ?></span>
        </div>
    </div>

    <!-- 分页导航 -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-section">
            <div class="pagination-container">
                <!-- 上一页 -->
                <a href="?page=<?= $page - 1 ?>" 
                   class="btn-pagination <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left"></i> 上一页
                </a>
                
                <!-- 页码信息 -->
                <div class="page-info">
                    <span>第</span>
                    <select class="form-select page-select" onchange="location.href='?page='+this.value">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == $page ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <span>页，共 <?= $totalPages ?> 页</span>
                </div>
                
                <!-- 下一页 -->
                <a href="?page=<?= $page + 1 ?>" 
                   class="btn-pagination <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    下一页 <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <!-- 统计信息 -->
            <div class="stats-summary">
                显示 <?= min(($page - 1) * $nftsPerPage + 1, $totalNfts) ?>-<?= min($page * $nftsPerPage, $totalNfts) ?> 个头像，共 <?= $totalNfts ?> 个头像
            </div>
        </div>
    <?php endif; ?>

    <!-- NFT网格 -->
    <div class="nfts-grid">
        <?php if (empty($topNfts)): ?>
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <h3>暂无排名数据</h3>
                <p>还没有NFT头像达到排名标准</p>
            </div>
        <?php else: ?>
            <?php foreach ($topNfts as $index => $nftItem): ?>
                <?php $globalRank = ($page - 1) * $nftsPerPage + $index + 1; ?>
                <div class="nft-card">
                    <div class="nft-image-container">
                        <img src="../avatar/<?= htmlspecialchars($nftItem['base_image']) ?>" 
                             alt="NFT <?= htmlspecialchars($nftItem['code']) ?>"
                             class="nft-image"
                             loading="lazy">
                        <div class="nft-rank">#<?= $globalRank ?></div>
                    </div>
                    
                    <div class="nft-content">
                        <div class="nft-code"><?= htmlspecialchars($nftItem['code']) ?></div>
                        
                        <!-- 统计数据 -->
                        <div class="nft-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?= $nftItem['claim_city_count'] ?? 0 ?></span>
                                <span class="stat-label">认领城市</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?= $nftItem['sale_city_count'] ?? 0 ?></span>
                                <span class="stat-label">售卖城市</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?= $nftItem['purchase_count'] ?? 0 ?></span>
                                <span class="stat-label">求购数</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?= $nftItem['comment_count'] ?? 0 ?></span>
                                <span class="stat-label">评论数</span>
                            </div>
                        </div>
                        
                        <!-- 标签 -->
                        <?php 
                        $tags = $nft->getNftTags($nftItem['id']);
                        if (!empty($tags)): 
                        ?>
                            <div class="nft-tags">
                                <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                                    <?php if (isset($tag['name'])): ?>
                                        <a href="nft/list.php?tag=<?= urlencode($tag['name']) ?>" 
                                           class="nft-tag">
                                            <?= htmlspecialchars($tag['name']) ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (count($tags) > 3): ?>
                                    <span class="nft-tag">+<?= count($tags) - 3 ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 查看详情按钮 -->
                        <div class="nft-actions">
                            <a href="nft/view.php?id=<?= $nftItem['id'] ?>" 
                               class="btn-view-details">
                                <i class="fas fa-external-link-alt"></i> 查看详情
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- 底部分页导航 -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-section">
            <div class="pagination-container">
                <!-- 上一页 -->
                <a href="?page=<?= $page - 1 ?>" 
                   class="btn-pagination <?= $page <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left"></i> 上一页
                </a>
                
                <!-- 页码信息 -->
                <div class="page-info">
                    <span>第</span>
                    <select class="form-select page-select" onchange="location.href='?page='+this.value">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == $page ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <span>页，共 <?= $totalPages ?> 页</span>
                </div>
                
                <!-- 下一页 -->
                <a href="?page=<?= $page + 1 ?>" 
                   class="btn-pagination <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    下一页 <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <!-- 统计信息 -->
            <div class="stats-summary">
                显示 <?= min(($page - 1) * $nftsPerPage + 1, $totalNfts) ?>-<?= min($page * $nftsPerPage, $totalNfts) ?> 个头像，共 <?= $totalNfts ?> 个头像
            </div>
        </div>
    <?php endif; ?>
	
	<!-- 排名标准说明 -->
    <div class="ranking-criteria">
        <h3>📊 NFT头像综合排行榜计算标准</h3>
        <div class="criteria-list">
            <div class="criteria-item">
                <div class="criteria-icon">
                    <i class="fas fa-city"></i>
                </div>
                <div>
                    <strong>认领城市数</strong>
                    <div class="text-muted">被认领的不同城市数量 × 3倍权重</div>
                </div>
            </div>
            <div class="criteria-item">
                <div class="criteria-icon">
                    <i class="fas fa-store"></i>
                </div>
                <div>
                    <strong>售卖城市数</strong>
                    <div class="text-muted">正在售卖的城市数量 × 2倍权重</div>
                </div>
            </div>
            <div class="criteria-item">
                <div class="criteria-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div>
                    <strong>求购数量</strong>
                    <div class="text-muted">用户求购的总次数 × 1.5倍权重</div>
                </div>
            </div>
            <div class="criteria-item">
                <div class="criteria-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <div>
                    <strong>评论数量</strong>
                    <div class="text-muted">用户评价的总条数 × 1倍权重</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>