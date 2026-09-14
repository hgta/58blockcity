<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';
require_once '../../classes/BCTOrder.php';

checkLogin();

$type = $_GET['type'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$userId = $_SESSION['user_id'];

$order = new BCTOrder($pdo);
$orders = $order->getUserOrders($userId, $type, 'all', $page, $perPage);
$total = $order->getUserOrderCount($userId, $type);
$totalPages = max(1, (int)ceil($total / $perPage));

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

<div class="orders-page">
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
                    <td><?= number_format($order['price'], 2) ?></td>
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
                            <button onclick="cancelBctOrder(<?= $order['id'] ?>)" class="btn btn-danger" title="取消订单">
                                <i class="glyphicon glyphicon-remove"></i>
                            </button>
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
    <?php if ($totalPages > 1): ?>
    <div class="text-center">
        <ul class="pagination">
            <li class="<?= $page <= 1 ? 'disabled' : '' ?>">
                <a href="?type=<?= $type ?>&page=<?= max(1, $page-1) ?>">&laquo;</a>
            </li>
            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
            <li class="<?= $i == $page ? 'active' : '' ?>">
                <a href="?type=<?= $type ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="<?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a href="?type=<?= $type ?>&page=<?= min($totalPages, $page+1) ?>">&raquo;</a>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>

<!-- 页面特定样式 -->
<style>
.orders-page h2 {
    font-size: 22px;
    margin: 0 0 18px;
    color: var(--bct-text);
}

/* 类型选项卡 */
.orders-page .nav-tabs {
    border-bottom: 1px solid var(--bct-border);
    margin-bottom: 20px;
}
.orders-page .nav-tabs > li > a {
    color: var(--bct-text-secondary);
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    padding: 10px 18px;
    font-weight: 500;
}
.orders-page .nav-tabs > li > a:hover {
    color: var(--bct-text);
    background: var(--bct-bg-hover);
    border-bottom-color: transparent;
}
.orders-page .nav-tabs > li.active > a,
.orders-page .nav-tabs > li.active > a:hover,
.orders-page .nav-tabs > li.active > a:focus {
    color: var(--bct-accent);
    background: transparent;
    border: none;
    border-bottom: 2px solid var(--bct-accent);
}

/* 表格 */
.orders-page .table-responsive {
    border: 1px solid var(--bct-border);
    border-radius: var(--bct-radius);
    overflow-x: auto;
}
.orders-page .table {
    margin-bottom: 0;
    color: var(--bct-text);
}
.orders-page .table > thead > tr > th {
    background: var(--bct-bg-tertiary);
    color: var(--bct-text-secondary);
    border-bottom: 1px solid var(--bct-border);
    font-weight: 500;
    font-size: 12px;
    white-space: nowrap;
}
.orders-page .table > tbody > tr > td {
    border-top: 1px solid var(--bct-border);
    color: var(--bct-text);
    vertical-align: middle;
    font-size: 13px;
    white-space: nowrap;
}
.orders-page .table-striped > tbody > tr:nth-of-type(odd) {
    background: rgba(255, 255, 255, 0.02);
}
.orders-page .table-hover > tbody > tr:hover {
    background: var(--bct-bg-hover);
}
.orders-page .table > tbody > tr > td .num,
.orders-page .table > tbody > tr > td:nth-child(5),
.orders-page .table > tbody > tr > td:nth-child(6),
.orders-page .table > tbody > tr > td:nth-child(7) {
    font-family: 'Roboto Mono', 'SF Mono', Monaco, 'Courier New', monospace;
    font-variant-numeric: tabular-nums;
}

/* 分页条 */
.orders-page .pagination {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 20px 0 0;
}
.orders-page .pagination > li {
    display: inline-block;
}
.orders-page .pagination > li > a {
    background: var(--bct-bg-tertiary);
    border: 1px solid var(--bct-border);
    color: var(--bct-text-secondary);
    border-radius: var(--bct-radius);
    padding: 7px 13px;
    transition: all 0.2s;
}
.orders-page .pagination > li > a:hover {
    background: var(--bct-bg-hover);
    border-color: var(--bct-accent);
    color: var(--bct-text);
}
.orders-page .pagination > li.active > a {
    background: var(--bct-accent);
    border-color: var(--bct-accent);
    color: #0b0e11;
    font-weight: 600;
}
.orders-page .pagination > li.disabled > a {
    background: var(--bct-bg-secondary);
    border-color: var(--bct-border);
    color: var(--bct-text-muted);
    opacity: 0.6;
    pointer-events: none;
}
</style>

<!-- 页面特定JavaScript -->
<script>
$(document).ready(function() {
    $('[title]').tooltip();
    $('.nav-tabs a').click(function(e) {
        e.preventDefault();
        window.location.href = 'orders.php' + $(this).attr('href') + '&page=1';
    });
});

function cancelBctOrder(orderId) {
    if (!confirm('确定要取消此订单吗？')) return;
    var csrf = $('meta[name="csrf-token"]').attr('content') || '';
    $.ajax({
        url: 'cancel_order.php',
        type: 'POST',
        dataType: 'json',
        data: { order_id: orderId, csrf_token: csrf },
        success: function(res) {
            alert(res.message || (res.success ? '已取消' : '取消失败'));
            if (res.success) location.reload();
        },
        error: function() { alert('请求失败，请重试'); }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>