<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/UserBCTAccount.php';
require_once '../../classes/BCTOrder.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';

checkLogin();

$userId = $_SESSION['user_id'];

// 初始化各业务类
$account = new UserBCTAccount($pdo);
$order = new BCTOrder($pdo);
$circle = new Circle($pdo);
$visit = new Visit($pdo);

// 获取用户数据
$userAccounts = $account->getUserAccounts($userId); 
$buyOrders = $order->getUserOrders($userId, 'buy');
$sellOrders = $order->getUserOrders($userId, 'sell');
$userCircles = $circle->getUserCircles($userId);
$pendingVisits = $visit->getCircleVisits($userId, 'pending');//$visit->getPendingVisits($userId);
$visitHistory = $visit->getUserVisits($userId);//$visit->getVisitHistory($userId);

// 显示消息
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>';
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>';
    unset($_SESSION['error']);
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <!-- 用户欢迎区域 -->
    <div class="page-header">
        <div class="row">
            <div class="col-md-8">
                <h1>
                    <i class="glyphicon glyphicon-user"></i>
                    欢迎回来，<?= htmlspecialchars($_SESSION['username']) ?>
                </h1>
                <p class="text-muted">您的58区块城市个人中心</p>
            </div>
            <div class="col-md-4 text-right">
                <a href="../circles/create.php" class="btn btn-primary">
                    <i class="glyphicon glyphicon-plus"></i> 创建互访圈
                </a>
                <a href="profile.php" class="btn btn-default">
                    <i class="glyphicon glyphicon-cog"></i> 账户设置
                </a>
            </div>
        </div>
    </div>

    <!-- 快速概览 -->
    <div class="row quick-stats">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="glyphicon glyphicon-piggy-bank"></i>
                </div>
                <div class="stat-info">
                    <h3><?= count($userAccounts) ?></h3>
                    <p>持有城市数量</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-success">
                    <i class="glyphicon glyphicon-transfer"></i>
                </div>
                <div class="stat-info">
                    <h3><?= count($buyOrders) + count($sellOrders) ?></h3>
                    <p>交易订单</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning">
                    <i class="glyphicon glyphicon-home"></i>
                </div>
                <div class="stat-info">
                    <h3><?= count($userCircles) ?></h3>
                    <p>我的互访圈</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon bg-info">
                    <i class="glyphicon glyphicon-time"></i>
                </div>
                <div class="stat-info">
                    <h3><?= count($pendingVisits) ?></h3>
                    <p>待处理访问</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 主要内容区域 -->
    <div class="row dashboard-content">
        <!-- 左侧内容 -->
        <div class="col-md-8">
            <!-- 我的BCT账户 -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-credit-card"></i> 我的BCT账户</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($userAccounts)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="glyphicon glyphicon-warning-sign"></i>
                            </div>
                            <h4>暂无BCT账户</h4>
                            <p>您尚未在任何城市持有BCT人气值</p>
                            <a href="../market.php" class="btn btn-primary">去交易</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>城市</th>
                                        <th>余额</th>
                                        <th>冻结</th>
                                        <th>当前价格</th>
                                        <th>估值</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userAccounts as $account): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($account['city']) ?></strong>
                                        </td>
                                        <td><?= number_format($account['balance']) ?> BCT</td>
                                        <td><?= number_format($account['frozen']) ?> BCT</td>
                                        <td><?= number_format($account['current_price'], 4) ?>元</td>
                                        <td><?= number_format($account['balance'] * $account['current_price'], 2) ?>元</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../bct/trade.php?city=<?= urlencode($account['city']) ?>" class="btn btn-default">
                                                    交易
                                                </a>
                                                <a href="transfer.php?city=<?= urlencode($account['city']) ?>" class="btn btn-default">
                                                    转账
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 我的交易订单 -->
            <div class="card mt-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="active"><a href="#buy-orders" data-toggle="tab">购买订单</a></li>
                        <li><a href="#sell-orders" data-toggle="tab">出售订单</a></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane active" id="buy-orders">
                            <?php if (empty($buyOrders)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="glyphicon glyphicon-shopping-cart"></i>
                                    </div>
                                    <h4>暂无购买订单</h4>
                                    <p>您尚未创建任何购买订单</p>
                                    <a href="../bct/market.php" class="btn btn-primary">去购买</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>订单号</th>
                                                <th>城市</th>
                                                <th>数量</th>
                                                <th>价格</th>
                                                <th>总金额</th>
                                                <th>状态</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($buyOrders as $order): ?>
                                            <tr>
                                                <td><?= substr($order['order_no'], 0, 8) ?>...</td>
                                                <td><?= htmlspecialchars($order['city']) ?></td>
                                                <td><?= number_format($order['amount']) ?> BCT</td>
                                                <td><?= number_format($order['price'], 4) ?>元</td>
                                                <td><?= number_format($order['total_amount'], 2) ?>元</td>
                                                <td>
                                                    <span class="status-badge <?= $order['status'] ?>">
                                                        <?= $order['status'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-default">
                                                        详情
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane" id="sell-orders">
                            <?php if (empty($sellOrders)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="glyphicon glyphicon-usd"></i>
                                    </div>
                                    <h4>暂无出售订单</h4>
                                    <p>您尚未创建任何出售订单</p>
                                    <a href="../bct/market.php" class="btn btn-primary">去出售</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>订单号</th>
                                                <th>城市</th>
                                                <th>数量</th>
                                                <th>价格</th>
                                                <th>总金额</th>
                                                <th>状态</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sellOrders as $order): ?>
                                            <tr>
                                                <td><?= substr($order['order_no'], 0, 8) ?>...</td>
                                                <td><?= htmlspecialchars($order['city']) ?></td>
                                                <td><?= number_format($order['amount']) ?> BCT</td>
                                                <td><?= number_format($order['price'], 4) ?>元</td>
                                                <td><?= number_format($order['total_amount'], 2) ?>元</td>
                                                <td>
                                                    <span class="status-badge <?= $order['status'] ?>">
                                                        <?= $order['status'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-default">
                                                        详情
                                                    </a>
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

        <!-- 右侧边栏 -->
        <div class="col-md-4">
            <!-- 我的互访圈 -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-home"></i> 我的互访圈</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($userCircles)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="glyphicon glyphicon-home"></i>
                            </div>
                            <h4>暂无互访圈</h4>
                            <p>您尚未创建任何互访圈</p>
                            <a href="../circles/create.php" class="btn btn-primary">创建互访圈</a>
                        </div>
                    <?php else: ?>
                        <div class="circle-list">
                            <?php foreach ($userCircles as $circle): ?>
                            <div class="circle-item">
                                <div class="circle-info">
                                    <h4><?= htmlspecialchars($circle['name']) ?></h4>
                                    <p>
                                        <i class="glyphicon glyphicon-map-marker"></i>
                                        <?= htmlspecialchars($circle['city']) ?>
                                        <span class="pull-right">
                                            <?= $circle['block_count'] ?>区块
                                        </span>
                                    </p>
                                </div>
                                <div class="circle-actions">
                                    <a href="../circles/view.php?id=<?= $circle['id'] ?>" class="btn btn-sm btn-default">
                                        管理
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 待处理访问 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-time"></i> 待处理访问</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingVisits)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="glyphicon glyphicon-ok"></i>
                            </div>
                            <h4>暂无待处理访问</h4>
                            <p>当前没有需要您处理的访问请求</p>
                        </div>
                    <?php else: ?>
                        <div class="visit-list">
                            <?php foreach ($pendingVisits as $visit): ?>
                            <div class="visit-item">
                                <div class="visitor-info">
                                    <img src="../assets/images/<?= htmlspecialchars($visit['avatar']) ?>" class="visitor-avatar">
                                    <div class="visitor-details">
                                        <h5><?= htmlspecialchars($visit['visitor_name']) ?></h5>
                                        <p>
                                            <i class="glyphicon glyphicon-home"></i>
                                            <?= htmlspecialchars($visit['circle_name']) ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="visit-actions">
                                    <a href="confirm_visit.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-success">
                                        确认
                                    </a>
                                    <a href="reject_visit.php?id=<?= $visit['id'] ?>" class="btn btn-sm btn-danger">
                                        拒绝
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 最近访问记录 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-list-alt"></i> 最近访问记录</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($visitHistory)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="glyphicon glyphicon-search"></i>
                            </div>
                            <h4>暂无访问记录</h4>
                            <p>您还没有访问过任何互访圈</p>
                            <a href="../circles/" class="btn btn-primary">浏览互访圈</a>
                        </div>
                    <?php else: ?>
                        <div class="visit-history">
                            <?php foreach ($visitHistory as $visit): ?>
                            <div class="history-item">
                                <div class="history-icon">
                                    <?php if ($visit['status'] === 'completed'): ?>
                                        <i class="glyphicon glyphicon-ok-circle text-success"></i>
                                    <?php elseif ($visit['status'] === 'pending'): ?>
                                        <i class="glyphicon glyphicon-time text-warning"></i>
                                    <?php else: ?>
                                        <i class="glyphicon glyphicon-remove-circle text-danger"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="history-details">
                                    <h5><?= htmlspecialchars($visit['circle_name']) ?></h5>
                                    <p>
                                        <span class="text-muted">
                                            <?= date('Y-m-d', strtotime($visit['created_at'])) ?>
                                        </span>
                                        <span class="pull-right status-badge <?= $visit['status'] ?>">
                                            <?= $visit['status'] ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="visits.php" class="btn btn-default">查看全部</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>



<?php require_once '../includes/footer.php'; ?>