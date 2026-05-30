<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';
require_once '../../classes/Block.php';
require_once '../../classes/Transaction.php';

// Check if user is logged in
checkLogin();

$userId = $_SESSION['user_id'];

// Initialize classes
$user = new User($pdo);
$block = new Block($pdo);
$transaction = new Transaction($pdo);

// Get user information
$userInfo = $user->getUserById($userId);

// Get user's blocks
$userBlocks = $block->getUserBlocks($userId);
$blockCount = count($userBlocks);

// Calculate total value of blocks
$totalValue = 0;
foreach ($userBlocks as $b) {
    $totalValue += $b['price'];
}

// Get recent transactions
$recentTransactions = $transaction->getUserTransactions($userId, 5);

// Get active purchase requests
$purchaseRequests = $block->getUserPurchaseRequests($userId);

// Get active votes
$activeVotes = null;//$block->getUserActiveVotes($userId);
?>

<?php require_once '../includes/header.php'; ?>

<div class="container dashboard-container">
    <div class="row">
        <div class="col-md-3">
            <!-- User Profile Sidebar -->
            <div class="panel panel-default profile-card">
                <div class="panel-body text-center">
                    <div class="avatar-container">
                        <img src="../assets/images/<?= htmlspecialchars($userInfo['avatar']) ?>" 
                             alt="<?= htmlspecialchars($userInfo['username']) ?>" 
                             class="avatar-img">
                    </div>
                    <h3><?= htmlspecialchars($userInfo['username']) ?></h3>
                    <p class="text-muted"><?= htmlspecialchars($userInfo['city'] ?? '未设置城市') ?></p>
                    
                    <div class="user-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $blockCount ?></div>
                            <div class="stat-label">拥有区块</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($totalValue, 2) ?></div>
                            <div class="stat-label">区块价值</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $userInfo['popularity'] ?></div>
                            <div class="stat-label">人气值</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <a href="profile.php" class="btn btn-default btn-block">编辑个人资料</a>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="panel panel-default quick-actions">
                <div class="panel-heading">
                    <h3 class="panel-title">快捷操作</h3>
                </div>
                <div class="panel-body">
                    <a href="../block/buy.php" class="btn btn-success btn-block">购买新区块</a>
                    <a href="blocks.php" class="btn btn-primary btn-block">管理我的区块</a>
                    <a href="../city/" class="btn btn-info btn-block">浏览城市地图</a>
                    <a href="purchase_requests.php" class="btn btn-warning btn-block">我的求购</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <!-- Dashboard Main Content -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">我的区块城市仪表盘</h3>
                </div>
                <div class="panel-body">
                    <!-- Active Votes Section -->
                    <?php if (!empty($activeVotes)): ?>
                        <div class="dashboard-section">
                            <h4><i class="fa fa-vote-yea"></i> 待参与的扩容投票</h4>
                            <div class="alert alert-info">
                                <p>您有以下城市区块需要参与扩容投票，参与投票可获得10人气值奖励。</p>
                            </div>
                            
                            <div class="votes-list">
                                <?php foreach ($activeVotes as $vote): ?>
                                    <div class="vote-item">
                                        <div class="vote-info">
                                            <strong><?= htmlspecialchars($vote['city_name']) ?></strong> - 
                                            第<?= $vote['round'] ?>轮<?= $vote['zone'] ?>区扩容投票
                                            <span class="pull-right">
                                                截止: <?= date('m-d H:i', strtotime($vote['end_time'])) ?>
                                            </span>
                                        </div>
                                        <div class="vote-actions">
                                            <span class="vote-stats">
                                                当前票数: 同意 <?= $vote['yes_votes'] ?> / 反对 <?= $vote['no_votes'] ?>
                                            </span>
                                            <div class="btn-group pull-right">
                                                <a href="../city/vote.php?vote_id=<?= $vote['id'] ?>&vote=yes" 
                                                   class="btn btn-xs btn-success">同意</a>
                                                <a href="../city/vote.php?vote_id=<?= $vote['id'] ?>&vote=no" 
                                                   class="btn btn-xs btn-danger">反对</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Recent Blocks Section -->
                    <div class="dashboard-section">
                        <h4><i class="fa fa-map-marked-alt"></i> 最近获得的区块</h4>
                        
                        <?php if (!empty($userBlocks)): ?>
                            <div class="blocks-grid">
                                <?php 
                                $recentBlocks = array_slice($userBlocks, 0, 4);
                                foreach ($recentBlocks as $b): ?>
                                    <div class="block-card">
                                        <div class="block-header">
                                            <?= htmlspecialchars($b['city_name']) ?> - <?= $b['zone'] ?>区
                                        </div>
                                        <div class="block-number">
                                            <?= $b['block_number'] ?>
                                        </div>
                                        <div class="block-price">
                                            <?= number_format($b['price'], 2) ?> 元
                                        </div>
                                        <div class="block-actions">
                                            <a href="../block/view.php?id=<?= $b['id'] ?>" 
                                               class="btn btn-xs btn-default">查看</a>
                                            <a href="../block/sell.php?id=<?= $b['id'] ?>" 
                                               class="btn btn-xs btn-warning">出售</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-right">
                                <a href="blocks.php" class="btn btn-link">查看全部区块 →</a>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa fa-map-marked-alt fa-3x"></i>
                                <p>您还没有任何区块</p>
                                <a href="../block/buy.php" class="btn btn-success">购买第一个区块</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recent Transactions Section -->
                    <div class="dashboard-section">
                        <h4><i class="fa fa-exchange-alt"></i> 最近交易记录</h4>
                        
                        <?php if (!empty($recentTransactions)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped transactions-table">
                                    <thead>
                                        <tr>
                                            <th>区块</th>
                                            <th>类型</th>
                                            <th>价格</th>
                                            <th>时间</th>
                                            <th>状态</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentTransactions as $t): ?>
                                            <tr>
                                                <td>
                                                    <a href="../block/view.php?id=<?= $t['block_id'] ?>">
                                                        <?= $t['city_name'] ?> <?= $t['zone'] ?>-<?= $t['block_number'] ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($t['buyer_id'] == $userId): ?>
                                                        <span class="label label-success">购买</span>
                                                    <?php else: ?>
                                                        <span class="label label-warning">出售</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= number_format($t['price'], 2) ?> 元</td>
                                                <td><?= date('m-d H:i', strtotime($t['created_at'])) ?></td>
                                                <td>
                                                    <?php if ($t['status'] == 'completed'): ?>
                                                        <span class="label label-primary">已完成</span>
                                                    <?php elseif ($t['status'] == 'pending'): ?>
                                                        <span class="label label-info">处理中</span>
                                                    <?php else: ?>
                                                        <span class="label label-default">已取消</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-right">
                                <a href="transactions.php" class="btn btn-link">查看全部交易 →</a>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa fa-exchange-alt fa-3x"></i>
                                <p>暂无交易记录</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Purchase Requests Section -->
                    <?php if (!empty($purchaseRequests)): ?>
                        <div class="dashboard-section">
                            <h4><i class="fa fa-hand-holding-usd"></i> 活跃的求购请求</h4>
                            
                            <div class="table-responsive">
                                <table class="table table-striped requests-table">
                                    <thead>
                                        <tr>
                                            <th>城市</th>
                                            <th>区域</th>
                                            <th>区块</th>
                                            <th>最高出价</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($purchaseRequests as $pr): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($pr['city_name']) ?></td>
                                                <td><?= $pr['zone'] ?>区</td>
                                                <td>
                                                    <?= $pr['block_number'] ? $pr['block_number'] : '任意' ?>
                                                </td>
                                                <td>
                                                    <?= $pr['max_price'] ? number_format($pr['max_price'], 2).' 元' : '面议' ?>
                                                </td>
                                                <td>
                                                    <a href="../block/cancel_request.php?id=<?= $pr['id'] ?>" 
                                                       class="btn btn-xs btn-danger">取消</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>

</style>