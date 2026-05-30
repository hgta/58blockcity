<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../../classes/BCTOrder.php';

checkLogin();

$type = $_GET['type'] ?? 'all';
$userId = $_SESSION['user_id'];

// 实例化订单类
$order = new BCTOrder($pdo);

// 获取用户订单数据
$orders = $order->getUserOrders($userId, $type);

// 显示成功/错误消息
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>';
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>';
    unset($_SESSION['error']);
}
?>

<div class="container">
    <h2>我的交易订单</h2>
    
    <!-- 订单类型选项卡 -->
    <ul class="nav nav-tabs">
        <li class="<?= $type === 'all' ? 'active' : '' ?>">
            <a href="?type=all">全部订单</a>
        </li>
        <li class="<?= $type === 'buy' ? 'active' : '' ?>">
            <a href="?type=buy">购买订单</a>
        </li>
        <li class="<?= $type === 'sell' ? 'active' : '' ?>">
            <a href="?type=sell">出售订单</a>
        </li>
    </ul>
    
    <!-- 订单列表 -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>订单编号</th>
                    <th>创建时间</th>
                    <th>类型</th>
                    <th>城市</th>
                    <th>数量(BCT)</th>
                    <th>单价(元)</th>
                    <th>总金额(元)</th>
                    <th>交易方式</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="10" class="text-center">暂无订单记录</td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= htmlspecialchars(substr($order['order_no'], 0, 8).'...') ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                    <td>
                        <span class="label label-<?= $order['type'] === 'buy' ? 'primary' : 'success' ?>">
                            <?= $order['type'] === 'buy' ? '购买' : '出售' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($order['city']) ?></td>
                    <td><?= number_format($order['amount']) ?></td>
                    <td><?= number_format($order['price'], 4) ?></td>
                    <td><?= number_format($order['total_amount'], 2) ?></td>
                    <td>
                        <?php 
                        $tradeTypes = [
                            'platform' => '平台交易',
                            'mediator' => '中介交易',
                            'direct' => '直接交易'
                        ];
                        echo $tradeTypes[$order['trade_type']] ?? '未知';
                        ?>
                    </td>
                    <td>
                        <?php 
                        $statusLabels = [
                            'pending' => ['label' => '待处理', 'class' => 'warning'],
                            'processing' => ['label' => '处理中', 'class' => 'info'],
                            'completed' => ['label' => '已完成', 'class' => 'success'],
                            'canceled' => ['label' => '已取消', 'class' => 'danger']
                        ];
                        $status = $order['status'];
                        ?>
                        <span class="label label-<?= $statusLabels[$status]['class'] ?>">
                            <?= $statusLabels[$status]['label'] ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-xs">
                            <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-default" title="查看详情">
                                <i class="glyphicon glyphicon-eye-open"></i>
                            </a>
                            <?php if ($order['status'] === 'pending'): ?>
                            <a href="cancel_order.php?id=<?= $order['id'] ?>" class="btn btn-danger" title="取消订单" 
                               onclick="return confirm('确定要取消此订单吗？')">
                                <i class="glyphicon glyphicon-remove"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- 分页导航 -->
    <div class="text-center">
        <ul class="pagination">
            <li class="disabled"><a href="#">&laquo;</a></li>
            <li class="active"><a href="#">1</a></li>
            <li><a href="#">2</a></li>
            <li><a href="#">3</a></li>
            <li><a href="#">&raquo;</a></li>
        </ul>
    </div>
</div>

<!-- 页面特定JavaScript -->
<script>
$(document).ready(function() {
    // 初始化工具提示
    $('[title]').tooltip();
    
    // 选项卡切换保持URL参数
    $('.nav-tabs a').click(function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        window.location.href = 'orders.php' + (url.includes('?') ? url.replace('?', '?') : url);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>