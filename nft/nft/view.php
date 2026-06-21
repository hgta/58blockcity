<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';
require_once '../../classes/Transaction.php';
require_once '../../classes/City.php';
require_once '../../classes/PurchaseRequest.php';
require_once '../../classes/Comment.php';
require_once '../../classes/Block.php';
require_once '../../classes/SeoHelper.php';

checkLogin();

$nftId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

$nft = new NFT($pdo);
$transaction = new Transaction($pdo);
$city = new City($pdo);
$purchaseRequest = new PurchaseRequest($pdo);
$comment = new Comment($pdo);
$block = new Block($pdo);

// 获取NFT详情
$nftInfo = $nft->getNftById($nftId);
if (!$nftInfo) {
    http_response_code(404);
    include '../../../404.php';
    exit;
}

// 旧 URL 301 跳转到规范 URL
$canonicalUrl = SeoHelper::nftUrl($nftId, $nftInfo['name'] ?? '');
SeoHelper::redirectIfNotCanonical($canonicalUrl);

// SEO 配置
$nftName = htmlspecialchars($nftInfo['name'] ?? 'NFT详情');
$nftDesc = SeoHelper::excerpt($nftInfo['description'] ?? '', 100);
$nftImage = SeoHelper::fullUrl($nftInfo['image_url'] ?? $nftInfo['main_image'] ?? '/assets/images/og-nft.jpg');
$canonicalUrl = SeoHelper::nftUrl($nftId, $nftInfo['name'] ?? '');
$site_config['title']       = SeoHelper::title($nftName . ' - 58 NFT');
$site_config['description'] = SeoHelper::description($nftDesc, '58 NFT头像市场');
$site_config['keywords']    = '58,NFT,头像,数字收藏,' . $nftName . ',区块城市';
$site_config['canonical_url'] = $canonicalUrl;
$site_config['og_image']    = $nftImage;
$site_config['og_type']     = 'website';

// 获取统计数据
$claimedCities = $nft->getClaimedCities($nftId);
$saleCities = $nft->getSaleCities($nftId);
$purchaseCount = $purchaseRequest->getPurchaseRequestCountByNft($nftId);

// 获取用户拥有的该NFT记录
$userClaims = $nft->getUserClaims($userId, $nftId);

// 获取最近活动记录（认领、上架、求购）
$recentActivities = $nft->getRecentActivities($nftId, 10);

// 获取NFT标签
$tags = $nft->getNftTags($nftId);

// 获取当前NFT的所有评论
$comments = $comment->getCommentsByNft($nftId);

// 处理评论提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_text'])) {
    $commentText = trim($_POST['comment_text']);
    if (!empty($commentText)) {
        if ($comment->addComment($userId, $nftId, $commentText)) {
            $_SESSION['message'] = "评论发布成功！";
        } else {
            $_SESSION['error'] = "评论发布失败";
        }
    }
    header("Location: view.php?id=$nftId");
    exit;
}
?>

<?php require_once '../includes/header.php'; ?>

<style>
.view-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px 0;
}

.page-title {
    font-size: 2.2rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 10px;
}

.nft-code {
    font-size: 1.4rem;
    color: #718096;
    font-weight: 500;
}

.content-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 25px;
    align-items: start;
}

/* 左侧样式 */
.left-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.nft-image-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.nft-image-container {
    padding: 30px;
    text-align: center;
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    width: 100%;
}

.nft-image {
    max-width: 100%;
    max-height: 300px;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 12px;
    display: block;
    margin: 0 auto;
}

.tags-container {
    padding: 20px;
    border-top: 1px solid #e2e8f0;
    background: white;
    width: 100%;
    box-sizing: border-box;
}

.tags-title {
    font-size: 0.9rem;
    color: #718096;
    margin-bottom: 12px;
    font-weight: 600;
    text-align: center;
}

.nft-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    align-items: center;
}

.nft-tag {
    /* background: linear-gradient(135deg, #667eea, #764ba2); */
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
    line-height: 1;
    display: inline-block;
	position: static;
}

.nft-tag:hover {
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
    color: white;
    text-decoration: none;
    transform: translateY(-1px);
}

.user-claims-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.user-claims-header {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}

.user-claims-body {
    padding: 15px 20px;
}

.claim-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.claim-item:last-child {
    border-bottom: none;
}

.claim-city {
    font-weight: 600;
    color: #2d3748;
}

.claim-block {
    font-size: 0.85rem;
    color: #718096;
}

/* 右侧样式 */
.right-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* 统计数据 */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 10px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    color: #2d3748;
    display: block;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.85rem;
    color: #718096;
    margin-top: 5px;
    font-weight: 500;
}

/* 操作按钮 */
.action-buttons {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 10px;
}

.btn-action {
    padding: 14px 16px;
    border-radius: 12px;
    text-align: center;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-claim { 
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
}

.btn-sell { 
    background: linear-gradient(135deg, #4299e1, #3182ce);
    color: white;
}

.btn-purchase { 
    background: linear-gradient(135deg, #ed8936, #dd6b20);
    color: white;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    color: white;
    text-decoration: none;
}

/* 动态播报 */
.activity-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.activity-header {
    background: linear-gradient(135deg, #ed8936, #dd6b20);
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}

.activity-body {
    padding: 0;
}

.activity-list {
    max-height: 250px;
    overflow-y: auto;
    padding: 15px;
}

.activity-item {
    padding: 12px 0;
    border-bottom: 1px solid #f7fafc;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-type {
    font-weight: 600;
    font-size: 0.8rem;
    padding: 4px 8px;
    border-radius: 6px;
    margin-right: 8px;
}

.activity-type.claim { 
    background: #c6f6d5;
    color: #22543d;
}

.activity-type.sale { 
    background: #bee3f8;
    color: #1a365d;
}

.activity-type.purchase { 
    background: #fed7d7;
    color: #742a2a;
}

.activity-description {
    color: #4a5568;
    font-size: 0.9rem;
}

.activity-time {
    font-size: 0.8rem;
    color: #a0aec0;
    text-align: right;
}

/* 评论区域 */
.comments-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.comments-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 15px 20px;
    font-weight: 600;
}

.comments-body {
    padding: 20px;
}

.comment-form {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.comment-item {
    padding: 15px 0;
    border-bottom: 1px solid #f7fafc;
}

.comment-item:last-child {
    border-bottom: none;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.comment-author {
    font-weight: 600;
    color: #2d3748;
}

.comment-time {
    font-size: 0.8rem;
    color: #a0aec0;
}

.comment-content {
    color: #4a5568;
    line-height: 1.5;
}

/* 响应式设计 */
@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .left-sidebar {
        order: 2;
    }
    
    .right-content {
        order: 1;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
    
    .view-container {
        padding: 15px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .nft-image-container {
        padding: 20px;
        min-height: 250px;
    }
    
    .nft-image {
        max-height: 200px;
    }
}
</style>

<div class="view-container">
    <!-- 页面标题 -->
    <div class="page-header">
        <h1 class="page-title">NFT头像详情</h1>
        <div class="nft-code">#<?= htmlspecialchars($nftInfo['code']) ?></div>
    </div>

    <div class="content-grid">
        <!-- 左侧内容 -->
        <div class="left-sidebar">
            <!-- NFT图片和标签 -->
            <div class="nft-image-card">
                <div class="nft-image-container">
                    <img src="../avatar/<?= htmlspecialchars($nftInfo['base_image']) ?>" 
                         class="nft-image"
                         alt="NFT <?= htmlspecialchars($nftInfo['code']) ?>">
                </div>
                
                <!-- 标签显示 -->
                <?php if (!empty($tags)): ?>
                    <div class="tags-container">
                        <div class="tags-title">标签</div>
                        <div class="nft-tags">
                            <?php foreach ($tags as $tag): ?>
                                <?php if (isset($tag['name'])): ?>
                                <a href="list.php?tag=<?= urlencode($tag['name']) ?>" 
                                   class="nft-tag">
                                    <?= htmlspecialchars($tag['name']) ?>
                                </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 用户拥有的NFT -->
            <?php if (!empty($userClaims)): ?>
                <div class="user-claims-card">
                    <div class="user-claims-header">
                        <i class="fas fa-check-circle"></i> 您拥有的该NFT
                    </div>
                    <div class="user-claims-body">
                        <?php foreach ($userClaims as $claim): ?>
                            <div class="claim-item">
                                <div>
                                    <div class="claim-city"><?= htmlspecialchars($claim['city_name']) ?></div>
                                    <div class="claim-block">区块: <?= htmlspecialchars($claim['block_id']) ?></div>
                                </div>
                                <div class="text-success">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 右侧内容 -->
        <div class="right-content">
            <!-- 统计数据和操作按钮 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number"><?= count($claimedCities) ?></span>
                    <span class="stat-label">已认领城市</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= count($saleCities) ?></span>
                    <span class="stat-label">售卖中城市</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= $purchaseCount ?></span>
                    <span class="stat-label">求购数量</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= count($comments) ?></span>
                    <span class="stat-label">评论数量</span>
                </div>
            </div>

            <!-- 操作按钮 -->
            <div class="action-buttons">
                <a href="claim_detail.php?id=<?= $nftId ?>" class="btn-action btn-claim">
                    <i class="fas fa-hand-holding-heart"></i> 认领
                </a>
                <a href="sell.php?id=<?= $nftId ?>" class="btn-action btn-sell">
                    <i class="fas fa-tag"></i> 出售
                </a>
                <a href="purchase_detail.php?id=<?= $nftId ?>" class="btn-action btn-purchase">
                    <i class="fas fa-hand-holding-usd"></i> 求购
                </a>
            </div>

            <!-- 动态播报 -->
            <div class="activity-card">
                <div class="activity-header">
                    <i class="fas fa-broadcast-tower"></i> 最新动态
                </div>
                <div class="activity-body">
                    <div class="activity-list">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <span class="activity-type <?= $activity['type'] ?>">
                                                <?= $activity['type'] === 'claim' ? '认领' : 
                                                   ($activity['type'] === 'sale' ? '上架' : '求购') ?>
                                            </span>
                                            <span class="activity-description"><?= htmlspecialchars($activity['description']) ?></span>
                                        </div>
                                        <div class="activity-time">
                                            <?= date('m-d H:i', strtotime($activity['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-info-circle"></i> 暂无动态记录
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 评论区域 -->
            <div class="comments-card">
                <div class="comments-header">
                    <i class="fas fa-comments"></i> 用户评价
                </div>
                <div class="comments-body">
                    <!-- 评论表单 -->
                    <form method="post" class="comment-form">
                        <div class="mb-3">
                            <label for="commentText" class="form-label">发表您的评价</label>
                            <textarea class="form-control" id="commentText" name="comment_text" rows="3" 
                                      placeholder="请输入您的评价..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> 提交评价
                        </button>
                    </form>
                    
                    <!-- 评论列表 -->
                    <div class="comments-section">
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $commentItem): ?>
                                <div class="comment-item">
                                    <div class="comment-header">
                                        <span class="comment-author"><?= htmlspecialchars($commentItem['username']) ?></span>
                                        <span class="comment-time"><?= date('m-d H:i', strtotime($commentItem['created_at'])) ?></span>
                                    </div>
                                    <div class="comment-content"><?= htmlspecialchars($commentItem['content']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-comment-slash"></i> 暂无评价，快来发表第一条评论吧！
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>