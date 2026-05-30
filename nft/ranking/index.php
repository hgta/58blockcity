<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/NFTRanking.php';

$nft = new NFT($pdo);
$nftRanking = new NFTRanking($pdo);

// 获取各排行榜数据，确保都有默认值
try {
    $topCitiesByClaims = $nftRanking->getTopCitiesByClaims(3) ?: [];
    $topCitiesByTransactions = $nftRanking->getTopCitiesByTransactions(3) ?: [];
    $topNftsByClaims = $nftRanking->getTopNftsByClaims(3) ?: [];
    $topNftsByListings = $nftRanking->getTopNftsByListings(3) ?: [];
    $topNftsByTransactions = $nftRanking->getTopNftsByTransactions(3) ?: [];
    $topUsersByClaims = $nftRanking->getTopUsersByClaims(3) ?: [];
    $topUsersByListings = $nftRanking->getTopUsersByListings(3) ?: [];
    $topUsersByTransactions = $nftRanking->getTopUsersByTransactions(3) ?: [];
} catch (Exception $e) {
    error_log("获取排行榜数据失败: " . $e->getMessage());
    // 设置空数组作为默认值
    $topCitiesByClaims = $topCitiesByTransactions = [];
    $topNftsByClaims = $topNftsByListings = $topNftsByTransactions = [];
    $topUsersByClaims = $topUsersByListings = $topUsersByTransactions = [];
}

// 辅助函数：安全遍历数组
function safeLoop($array, $callback) {
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $index => $item) {
            $callback($index, $item);
        }
    } else {
        // 显示无数据提示
        echo '<li class="text-muted text-center py-3">暂无数据</li>';
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-trophy"></i> NFT头像排行榜</h1>
        <p>发现最热门的NFT头像、城市和收藏家</p>
    </div>

    <div class="ranking-tabs">
        <ul class="nav nav-tabs" id="rankingTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="city-tab" data-toggle="tab" href="#city" role="tab">城市榜</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="nft-tab" data-toggle="tab" href="#nft" role="tab">头像榜</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="user-tab" data-toggle="tab" href="#user" role="tab">收藏家榜</a>
            </li>
        </ul>
        
        <div class="tab-content" id="rankingTabsContent">
            <!-- 城市榜 -->
            <div class="tab-pane fade show active" id="city" role="tabpanel">
                <div class="ranking-cards">
                    <div class="ranking-card">
                        <h3><i class="fas fa-map-marker-alt"></i> 认领最多城市</h3>
                        <ol class="ranking-list">
                            <?php safeLoop($topCitiesByClaims, function($index, $city) { ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <span class="name"><?= htmlspecialchars($city['name'] ?? '未知城市') ?></span>
                                <span class="value"><?= $city['claim_count'] ?? 0 ?>次认领</span>
                            </li>
                            <?php }); ?>
                        </ol>
                        <a href="city.php?type=claims" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-chart-line"></i> 成交最多城市</h3>
                        <ol class="ranking-list">
                            <?php safeLoop($topCitiesByTransactions, function($index, $city) { ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <span class="name"><?= htmlspecialchars($city['name'] ?? '未知城市') ?></span>
                                <span class="value"><?= $city['transaction_count'] ?? 0 ?>笔成交</span>
                            </li>
                            <?php }); ?>
                        </ol>
                        <a href="city.php?type=transactions" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-store"></i> 挂售最多城市</h3>
                        <ol class="ranking-list">
                            <?php safeLoop($topCitiesByTransactions, function($index, $city) { ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <span class="name"><?= htmlspecialchars($city['name'] ?? '未知城市') ?></span>
                                <span class="value"><?= $city['listing_count'] ?? 0 ?>个挂售</span>
                            </li>
                            <?php }); ?>
                        </ol>
                        <a href="city.php?type=listings" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                </div>
            </div>
            
            <!-- 头像榜 -->
            <div class="tab-pane fade" id="nft" role="tabpanel">
                <div class="ranking-cards">
                    <div class="ranking-card">
                        <h3><i class="fas fa-star"></i> 认领最多头像</h3>
                        <ol class="ranking-list">
                            <?php safeLoop($topNftsByClaims, function($index, $nftItem) { ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <img src="../avatar/<?= htmlspecialchars($nftItem['base_image'] ?? '../assets/images/default-nft.jpg') ?>" 
                                     class="nft-avatar-xs" 
                                     onerror="this.src='../assets/images/default-nft.jpg'">
                                <span class="name"><?= htmlspecialchars($nftItem['code'] ?? '未知头像') ?></span>
                                <span class="value"><?= $nftItem['claim_count'] ?? 0 ?>次认领</span>
                            </li>
                            <?php }); ?>
                        </ol>
                        <a href="nft.php?type=claims" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-shopping-cart"></i> 挂售最多头像</h3>
                        <ol class="ranking-list">
                            <?php safeLoop($topNftsByListings, function($index, $nftItem) { ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <img src="../avatar/<?= htmlspecialchars($nftItem['base_image'] ?? '../assets/images/default-nft.jpg') ?>" 
                                     class="nft-avatar-xs"
                                     onerror="this.src='../assets/images/default-nft.jpg'">
                                <span class="name"><?= htmlspecialchars($nftItem['code'] ?? '未知头像') ?></span>
                                <span class="value"><?= $nftItem['listing_count'] ?? 0 ?>次挂售</span>
                            </li>
                            <?php }); ?>
                        </ol>
                        <a href="nft.php?type=listings" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-exchange-alt"></i> 成交最多头像</h3>
                        <ol class="ranking-list">
                            <?php safeLoop($topNftsByTransactions, function($index, $nftItem) { ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <img src="../avatar/<?= htmlspecialchars($nftItem['base_image'] ?? '../assets/images/default-nft.jpg') ?>" 
                                     class="nft-avatar-xs"
                                     onerror="this.src='../assets/images/default-nft.jpg'">
                                <span class="name"><?= htmlspecialchars($nftItem['code'] ?? '未知头像') ?></span>
                                <span class="value"><?= $nftItem['transaction_count'] ?? 0 ?>笔成交</span>
                            </li>
                            <?php }); ?>
                        </ol>
                        <a href="nft.php?type=transactions" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                </div>
            </div>
            
            <!-- 收藏家榜 -->
            <div class="tab-pane fade" id="user" role="tabpanel">
                <div class="ranking-cards">
                    <div class="ranking-card">
                        <h3><i class="fas fa-crown"></i> 头像最多用户</h3>
                        <ol class="ranking-list">
                            <?php safeLoop($topUsersByClaims, function($index, $user) { ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <img src="https://v.58.tl/assets/images/<?= htmlspecialchars($user['avatar'] ?? 'default.jpg') ?>" 
                                     class="avatar-xs"
                                     onerror="this.src='../assets/images/default.jpg'">
                                <span class="name"><?= htmlspecialchars($user['username'] ?? '未知用户') ?></span>
                                <span class="value"><?= $user['claim_count'] ?? 0 ?>个头像</span>
                            </li>
                            <?php }); ?>
                        </ol>
                        <a href="user.php?type=claims" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-store"></i> 挂售最多用户</h3>
                        <ol class="ranking-list">
                            <?php safeLoop($topUsersByListings, function($index, $user) { ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <img src="https://v.58.tl/assets/images/<?= htmlspecialchars($user['avatar'] ?? 'default.jpg') ?>" 
                                     class="avatar-xs"
                                     onerror="this.src='../assets/images/default.jpg'">
                                <span class="name"><?= htmlspecialchars($user['username'] ?? '未知用户') ?></span>
                                <span class="value"><?= $user['listing_count'] ?? 0 ?>次挂售</span>
                            </li>
                            <?php }); ?>
                        </ol>
                        <a href="user.php?type=listings" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-chart-line"></i> 成交最多用户</h3>
                        <ol class="ranking-list">
                            <?php safeLoop($topUsersByTransactions, function($index, $user) { ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <img src="https://v.58.tl/assets/images/<?= htmlspecialchars($user['avatar'] ?? 'default.jpg') ?>" 
                                     class="avatar-xs"
                                     onerror="this.src='../assets/images/default.jpg'">
                                <span class="name"><?= htmlspecialchars($user['username'] ?? '未知用户') ?></span>
                                <span class="value"><?= $user['transaction_count'] ?? 0 ?>笔成交</span>
                            </li>
                            <?php }); ?>
                        </ol>
                        <a href="user.php?type=transactions" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.nft-avatar-xs {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    object-fit: cover;
    margin-right: 8px;
}
.avatar-xs {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 8px;
}
.ranking-list li {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
.ranking-list li:last-child {
    border-bottom: none;
}
.rank {
    width: 30px;
    text-align: center;
    font-weight: bold;
    color: #666;
}
.name {
    flex: 1;
    margin: 0 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.value {
    color: #007bff;
    font-weight: bold;
    white-space: nowrap;
}
.ranking-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.ranking-card h3 {
    margin-bottom: 15px;
    color: #333;
    font-size: 1.2em;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}
.ranking-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
</style>

<?php require_once '../includes/footer.php'; ?>