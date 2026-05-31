<?php
require_once  '../../config/database.php';
require_once  '../includes/auth.php';
require_once  '../../classes/NFT.php';
require_once  '../../classes/UserPopularity.php';
require_once  '../../classes/Transaction.php';

// Check user login
checkLogin();

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Initialize classes
$nft = new NFT($pdo);
$popularity = new UserPopularity($pdo);
$transaction = new Transaction($pdo);

// Get user data
$totalCollectionCount = $nft->getUserCollectionCount($userId); // Get total count of user's collection
$activeListings = $nft->getUserListings($userId, 'listed');
$totalActiveListingsCount = count($activeListings); // Get actual count of active listings
$soldItems = $transaction->getUserSales($userId);
$purchasedItems = $transaction->getUserPurchases($userId);
$topCity = $popularity->getUserTopCity($userId);

// Get pending transactions that require user action
$pendingTransactions = $transaction->getPendingTransactions($userId);

// Get user collection (limited display)
$userCollection = $nft->getUserCollection($userId, 24); // Get 24 NFTs for display
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container dashboard-container">
    <div class="row">
        <div class="col-md-3">
            <!-- User Profile Sidebar -->
            <div class="card profile-card">
                <div class="card-body text-center">
                    <img src="https://58.tl/assets/images/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.jpg') ?>" 
                         class="avatar-img-large" alt="<?= htmlspecialchars($username) ?>">
                    <h4 class="mt-3"><?= htmlspecialchars($username) ?></h4>
                    
                    <?php if ($topCity): ?>
                    <div class="top-city mt-4">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="city"><?= htmlspecialchars($topCity['city']) ?></span>
                        <span class="popularity-value"><?= number_format($topCity['popularity']) ?> 人气</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="collection-stats mt-4">
                        <div class="stat-item">
                            <span class="value"><?= $totalCollectionCount ?></span>
                            <span class="label">持有NFT</span>
                        </div>
                        <div class="stat-item">
                            <span class="value"><?= $totalActiveListingsCount ?></span>
                            <span class="label">出售中</span>
                        </div>
                        <div class="stat-item">
                            <span class="value"><?= count($soldItems) + count($purchasedItems) ?></span>
                            <span class="label">总交易</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <!-- Dashboard Main Content -->
            <div class="dashboard-content">
                <!-- Pending Actions -->
                <?php if (!empty($pendingTransactions)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5><i class="fas fa-exclamation-circle"></i> 待处理交易</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>NFT编号</th>
                                        <th>交易类型</th>
                                        <th>价格</th>
                                        <th>对方用户</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingTransactions as $tx): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tx['nft_code']) ?></td>
                                        <td>
                                            <?php 
                                            $typeMap = [
                                                'platform' => '平台交易',
                                                'intermediary' => '中介交易',
                                                'direct' => '直接交易'
                                            ];
                                            echo $typeMap[$tx['transaction_type']] ?? $tx['transaction_type'];
                                            ?>
                                        </td>
                                        <td>
                                            <?= number_format($tx['price'], 2) ?>
                                            <?= $tx['currency'] === 'popularity' ? '人气值' : '¥' ?>
                                        </td>
                                        <td>
                                            <?= $tx['buyer_id'] == $userId ? '卖家: '.htmlspecialchars($tx['seller_name']) : '买家: '.htmlspecialchars($tx['buyer_name']) ?>
                                        </td>
                                        <td>
                                            <a href="process_transaction.php?id=<?= $tx['id'] ?>&action=accept" class="btn btn-sm btn-success">接受</a>
                                            <a href="process_transaction.php?id=<?= $tx['id'] ?>&action=reject" class="btn btn-sm btn-danger">拒绝</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card bg-primary text-white">
                            <div class="stat-icon">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $totalCollectionCount ?></h3>
                                <p>持有的NFT头像</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-success text-white">
                            <div class="stat-icon">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= $totalActiveListingsCount ?></h3>
                                <p>出售中的NFT</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-info text-white">
                            <div class="stat-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?= count($soldItems) + count($purchasedItems) ?></h3>
                                <p>总交易数量</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- My Collection -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>我持有的NFT头像</h5>
                        <a href="/user/collection.php" class="btn btn-sm btn-outline-primary">
                            查看全部 <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($userCollection)): ?>
                            <div class="empty-state text-center py-4">
                                <div class="empty-icon text-muted mb-3">
                                    <i class="fas fa-image fa-2x"></i>
                                </div>
                                <h6 class="h5">您还没有NFT头像</h6>
                                <p class="text-muted small">快去认领或购买NFT头像吧！</p>
                                <a href="/nft/claim_list.php" class="btn btn-sm btn-primary mt-2">
                                    <i class="fas fa-hand-holding-heart me-1"></i>去认领NFT
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3">
                                <?php foreach ($userCollection as $item): ?>
                                    <div class="col">
                                        <a href="../nft/view.php?id=<?= $item['id'] ?>" class="text-decoration-none">
                                            <div class="nft-dashboard-card position-relative <?= $currentAvatar == $item['base_image'] ? 'current-avatar' : '' ?>">
                                                <!-- 圆形头像容器 -->
                                                <div class="avatar-circle-dashboard mx-auto">
                                                    <img src="../avatar/<?= htmlspecialchars($item['base_image']) ?>" 
                                                         class="avatar-img" 
                                                         alt="NFT <?= htmlspecialchars($item['code']) ?>"
                                                         loading="lazy">
                                                </div>
                                                
                                                <!-- 城市标记 -->
                                                <div class="city-badge">
                                                    <?= htmlspecialchars($item['city_name']) ?>
                                                </div>
                                                
                                                <!-- 当前头像标记 -->
                                                <?php if ($currentAvatar == $item['base_image']): ?>
                                                    <div class="current-avatar-mark">
                                                        <i class="fas fa-check-circle"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- 编号 -->
                                                <div class="nft-code text-center mt-2">
                                                    <?= htmlspecialchars($item['code']) ?>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Transactions -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exchange-alt"></i> 最近交易</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="transactionTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="sales-tab" data-toggle="tab" href="#sales" role="tab">出售记录</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="purchases-tab" data-toggle="tab" href="#purchases" role="tab">购买记录</a>
                            </li>
                        </ul>
                        <div class="tab-content mt-3" id="transactionTabsContent">
                            <div class="tab-pane fade show active" id="sales" role="tabpanel">
                                <?php if (empty($soldItems)): ?>
                                    <div class="empty-state-sm">
                                        <i class="fas fa-exchange-alt"></i>
                                        <h5>暂无出售记录</h5>
                                        <p>您还没有出售过任何NFT</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>NFT</th>
                                                    <th>买家</th>
                                                    <th>价格</th>
                                                    <th>交易方式</th>
                                                    <th>时间</th>
                                                    <th>状态</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($soldItems as $tx): ?>
                                                <tr>
                                                    <td>
                                                        <a href="../nft/view.php?id=<?= $tx['nft_id'] ?>">
                                                            <?= htmlspecialchars($tx['nft_code']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($tx['buyer_name']) ?></td>
                                                    <td>
                                                        <?= number_format($tx['price'], 2) ?>
                                                        <?= $tx['currency'] === 'popularity' ? '人气值' : '¥' ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $typeMap = [
                                                            'platform' => '平台',
                                                            'intermediary' => '中介',
                                                            'direct' => '直接'
                                                        ];
                                                        echo $typeMap[$tx['transaction_type']] ?? $tx['transaction_type'];
                                                        ?>
                                                    </td>
                                                    <td><?= date('Y-m-d H:i', strtotime($tx['completed_at'])) ?></td>
                                                    <td>
                                                        <span class="badge badge-success">已完成</span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="tab-pane fade" id="purchases" role="tabpanel">
                                <?php if (empty($purchasedItems)): ?>
                                    <div class="empty-state-sm">
                                        <i class="fas fa-exchange-alt"></i>
                                        <h5>暂无购买记录</h5>
                                        <p>您还没有购买过任何NFT</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>NFT</th>
                                                    <th>卖家</th>
                                                    <th>价格</th>
                                                    <th>交易方式</th>
                                                    <th>时间</th>
                                                    <th>状态</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($purchasedItems as $tx): ?>
                                                <tr>
                                                    <td>
                                                        <a href="../nft/view.php?id=<?= $tx['nft_id'] ?>">
                                                            <?= htmlspecialchars($tx['nft_code']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($tx['seller_name']) ?></td>
                                                    <td>
                                                        <?= number_format($tx['price'], 2) ?>
                                                        <?= $tx['currency'] === 'popularity' ? '人气值' : '¥' ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $typeMap = [
                                                            'platform' => '平台',
                                                            'intermediary' => '中介',
                                                            'direct' => '直接'
                                                        ];
                                                        echo $typeMap[$tx['transaction_type']] ?? $tx['transaction_type'];
                                                        ?>
                                                    </td>
                                                    <td><?= date('Y-m-d H:i', strtotime($tx['completed_at'])) ?></td>
                                                    <td>
                                                        <span class="badge badge-success">已完成</span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>