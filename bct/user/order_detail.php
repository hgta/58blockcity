<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// 检查登录
checkLogin();

// 获取订单ID
$orderId = $_GET['id'] ?? 0;
if (!$orderId) {
    $_SESSION['error'] = '订单ID不能为空';
    header('Location: ../index.php');
    exit;
}

try {
    // 获取订单详细信息
    $stmt = $pdo->prepare("
        SELECT o.*, u.username, u.email, u.phone,
               cb.current_price as city_current_price
        FROM bct_orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN city_bct cb ON o.city = cb.city
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        $_SESSION['error'] = '订单不存在';
        header('Location: ../index.php');
        exit;
    }

    // 检查用户权限（只能查看自己的订单或交易对手的订单）
    $isOwner = $order['user_id'] == $_SESSION['user_id'];
    $isCounterparty = false;
    
    // 如果是交易中的订单，检查是否是交易对手
    if (in_array($order['status'], ['processing', 'completed'])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM bct_transactions 
            WHERE order_id = ? AND (from_user = ? OR to_user = ?)
        ");
        $stmt->execute([$orderId, $_SESSION['user_id'], $_SESSION['user_id']]);
        $isCounterparty = $stmt->fetchColumn() > 0;
    }

    if (!$isOwner && !$isCounterparty) {
        $_SESSION['error'] = '无权查看此订单';
        header('Location: ../index.php');
        exit;
    }

    // 获取交易记录
    $stmt = $pdo->prepare("
        SELECT t.*, 
               from_user.username as from_username,
               to_user.username as to_username
        FROM bct_transactions t
        LEFT JOIN users from_user ON t.from_user = from_user.id
        LEFT JOIN users to_user ON t.to_user = to_user.id
        WHERE t.order_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$orderId]);
    $transactions = $stmt->fetchAll();

    // 获取相关订单（匹配的订单）
    $relatedOrders = [];
    if (in_array($order['status'], ['processing', 'completed'])) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT o.*, u.username
            FROM bct_orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id IN (
                SELECT order_id FROM bct_transactions 
                WHERE order_id != ? AND tx_no IN (
                    SELECT tx_no FROM bct_transactions WHERE order_id = ?
                )
            )
        ");
        $stmt->execute([$orderId, $orderId]);
        $relatedOrders = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    $_SESSION['error'] = '获取订单信息失败';
    header('Location: ..//index.php');
    exit;
}

// 显示消息
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>';
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>';
    unset($_SESSION['error']);
}

// 状态文本映射
$statusText = [
    'pending' => '等待成交',
    'processing' => '交易中',
    'completed' => '已完成',
    'canceled' => '已取消'
];

$typeText = [
    'buy' => '购买',
    'sell' => '出售'
];

$tradeTypeText = [
    'platform' => '平台交易',
    'mediator' => '中介交易',
    'direct' => '直接交易'
];
?>

<div class="container">
    <!-- 页面标题 -->
    <div class="page-header">
        <h1>
            <i class="glyphicon glyphicon-file"></i>
            订单详情
        </h1>
        <p class="text-muted">订单号: <?= htmlspecialchars($order['order_no']) ?></p>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- 订单基本信息 -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-info-sign"></i> 订单信息</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <label>订单号:</label>
                                <span class="info-value"><?= htmlspecialchars($order['order_no']) ?></span>
                            </div>
                            <div class="info-group">
                                <label>交易类型:</label>
                                <span class="info-value">
                                    <span class="label label-<?= $order['type'] == 'buy' ? 'success' : 'warning' ?>">
                                        <?= $typeText[$order['type']] ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-group">
                                <label>城市:</label>
                                <span class="info-value">
                                    <span class="city-badge"><?= htmlspecialchars($order['city']) ?></span>
                                </span>
                            </div>
                            <div class="info-group">
                                <label>交易方式:</label>
                                <span class="info-value"><?= $tradeTypeText[$order['trade_type']] ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <label>订单状态:</label>
                                <span class="info-value">
                                    <span class="label label-<?= 
                                        $order['status'] == 'completed' ? 'success' : 
                                        ($order['status'] == 'canceled' ? 'danger' : 'primary')
                                    ?>">
                                        <?= $statusText[$order['status']] ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-group">
                                <label>创建时间:</label>
                                <span class="info-value"><?= date('Y-m-d H:i:s', strtotime($order['created_at'])) ?></span>
                            </div>
                            <div class="info-group">
                                <label>更新时间:</label>
                                <span class="info-value"><?= date('Y-m-d H:i:s', strtotime($order['updated_at'])) ?></span>
                            </div>
                            <?php if ($order['mediator_id']): ?>
                            <div class="info-group">
                                <label>中介客服:</label>
                                <span class="info-value">ID: <?= $order['mediator_id'] ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 交易详情 -->
            <div class="card mt-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3><i class="glyphicon glyphicon-yen"></i> 交易详情</h3>
                        <?php if ($isOwner && $order['status'] == 'pending'): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="showEditModal()">
                            <i class="glyphicon glyphicon-edit"></i> 编辑
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="trade-stat">
                                <div class="stat-number"><?= number_format($order['amount']) ?></div>
                                <div class="stat-label">订单数量 (BCT)</div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="trade-stat">
                                <div class="stat-number"><?= number_format($order['price'], 4) ?></div>
                                <div class="stat-label">单价 (元)</div>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="trade-stat">
                                <div class="stat-number"><?= number_format($order['total_amount'], 2) ?></div>
                                <div class="stat-label">总金额 (元)</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($order['type'] == 'sell' && $order['contact_info']): ?>
                    <div class="contact-info mt-3">
                        <h5><i class="glyphicon glyphicon-user"></i> 联系方式</h5>
                        <p class="contact-detail"><?= htmlspecialchars($order['contact_info']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 交易记录 -->
            <?php if (!empty($transactions)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-list"></i> 交易记录</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>交易号</th>
                                    <th>时间</th>
                                    <th>方向</th>
                                    <th>数量</th>
                                    <th>单价</th>
                                    <th>手续费</th>
                                    <th>状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><small><?= htmlspecialchars($tx['tx_no']) ?></small></td>
                                    <td><?= date('m-d H:i', strtotime($tx['created_at'])) ?></td>
                                    <td>
                                        <?php if ($tx['from_user'] == $_SESSION['user_id']): ?>
                                        <span class="label label-danger">转出</span>
                                        <?php else: ?>
                                        <span class="label label-success">转入</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($tx['amount']) ?> BCT</td>
                                    <td><?= number_format($tx['price'], 4) ?> 元</td>
                                    <td><?= number_format($tx['fee'], 2) ?> 元</td>
                                    <td>
                                        <span class="label label-success">已完成</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 相关订单 -->
            <?php if (!empty($relatedOrders)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="glyphicon glyphicon-link"></i> 相关订单</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>订单号</th>
                                    <th>类型</th>
                                    <th>用户</th>
                                    <th>数量</th>
                                    <th>单价</th>
                                    <th>状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($relatedOrders as $related): ?>
                                <tr>
                                    <td>
                                        <a href="order_detail.php?id=<?= $related['id'] ?>">
                                            <?= htmlspecialchars($related['order_no']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="label label-<?= $related['type'] == 'buy' ? 'success' : 'warning' ?>">
                                            <?= $typeText[$related['type']] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($related['username']) ?></td>
                                    <td><?= number_format($related['amount']) ?> BCT</td>
                                    <td><?= number_format($related['price'], 4) ?> 元</td>
                                    <td>
                                        <span class="label label-<?= 
                                            $related['status'] == 'completed' ? 'success' : 
                                            ($related['status'] == 'canceled' ? 'danger' : 'primary')
                                        ?>">
                                            <?= $statusText[$related['status']] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <!-- 用户信息 -->
            <div class="card">
                <div class="card-header">
                    <h4><i class="glyphicon glyphicon-user"></i> 
                        <?= $isOwner ? '我的订单' : '对方信息' ?>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="user-info">
                        <div class="info-group">
                            <label>用户名:</label>
                            <span class="info-value"><?= htmlspecialchars($order['username']) ?></span>
                        </div>
                        <?php if ($isOwner && $order['email']): ?>
                        <div class="info-group">
                            <label>邮箱:</label>
                            <span class="info-value"><?= htmlspecialchars($order['email']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($isOwner && $order['phone']): ?>
                        <div class="info-group">
                            <label>手机:</label>
                            <span class="info-value"><?= htmlspecialchars($order['phone']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-group">
                            <label>用户ID:</label>
                            <span class="info-value"><?= $order['user_id'] ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 操作按钮 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h4><i class="glyphicon glyphicon-cog"></i> 操作</h4>
                </div>
                <div class="card-body">
                    <div class="action-buttons">
                        <?php if ($isOwner && $order['status'] == 'pending'): ?>
                        <button class="btn btn-warning btn-block" onclick="cancelOrder(<?= $order['id'] ?>)">
                            <i class="glyphicon glyphicon-remove"></i> 取消订单
                        </button>
                        <button class="btn btn-info btn-block" onclick="showEditModal()">
                            <i class="glyphicon glyphicon-edit"></i> 编辑订单
                        </button>
                        <?php endif; ?>
                        
                        <?php if (!$isOwner && $order['status'] == 'pending'): ?>
                        <?php if ($order['type'] == 'sell'): ?>
                        <button class="btn btn-primary btn-block" onclick="buyFromOrder(<?= $order['id'] ?>)">
                            <i class="glyphicon glyphicon-shopping-cart"></i> 立即购买
                        </button>
                        <?php else: ?>
                        <button class="btn btn-success btn-block" onclick="sellToOrder(<?= $order['id'] ?>)">
                            <i class="glyphicon glyphicon-yen"></i> 立即出售
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <a href="../index.php" class="btn btn-default btn-block">
                            <i class="glyphicon glyphicon-arrow-left"></i> 返回市场
                        </a>
                        
                        <?php if ($order['trade_type'] == 'direct' && !$isOwner && $order['contact_info']): ?>
                        <div class="alert alert-info mt-3">
                            <strong>直接交易提示:</strong>
                            <p>请通过以下方式联系对方：</p>
                            <p class="contact-highlight"><?= htmlspecialchars($order['contact_info']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 城市信息 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h4><i class="glyphicon glyphicon-map-marker"></i> 城市行情</h4>
                </div>
                <div class="card-body">
                    <div class="city-market-info">
                        <div class="info-group">
                            <label>城市:</label>
                            <span class="info-value"><?= htmlspecialchars($order['city']) ?></span>
                        </div>
                        <div class="info-group">
                            <label>当前市价:</label>
                            <span class="info-value"><?= number_format($order['city_current_price'] ?? 0.01, 4) ?> 元</span>
                        </div>
                        <div class="info-group">
                            <label>订单价格:</label>
                            <span class="info-value <?= ($order['price'] < ($order['city_current_price'] ?? 0.01)) ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($order['price'], 4) ?> 元
                            </span>
                        </div>
                        <?php if ($order['city_current_price']): ?>
                        <div class="price-difference">
                            <?php
                            $marketPrice = $order['city_current_price'];
                            $difference = $order['price'] - $marketPrice;
                            $percentage = $marketPrice > 0 ? ($difference / $marketPrice) * 100 : 0;
                            ?>
                            <small class="<?= $difference < 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $difference < 0 ? '低于' : '高于' ?> 市价 <?= number_format(abs($percentage), 2) ?>%
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 交易说明 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h4><i class="glyphicon glyphicon-info-sign"></i> 交易说明</h4>
                </div>
                <div class="card-body">
                    <div class="trade-tips">
                        <div class="tip-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>订单创建后无法修改价格和数量</span>
                        </div>
                        <div class="tip-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>平台交易自动撮合，手续费10%</span>
                        </div>
                        <div class="tip-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>中介交易请联系客服，手续费2%</span>
                        </div>
                        <div class="tip-item">
                            <i class="glyphicon glyphicon-ok text-success"></i>
                            <span>直接交易请自行联系对方</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 编辑订单模态框 -->
<div class="modal fade" id="editOrderModal" tabindex="-1" role="dialog" aria-labelledby="editOrderModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="editOrderModalLabel">编辑订单</h4>
            </div>
            <div class="modal-body">
                <form id="editOrderForm">
                    <input type="hidden" id="editOrderId" name="order_id" value="<?= $order['id'] ?>">
                    
                    <div class="form-group">
                        <label for="editAmount">交易数量 (BCT)</label>
                        <input type="number" class="form-control" id="editAmount" name="amount" 
                               min="1" max="100000" required 
                               value="<?= $order['amount'] ?>">
                        <small class="form-text text-muted">单次交易数量范围：1 - 100,000 BCT</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPrice">单价 (元/BCT)</label>
                        <input type="number" class="form-control" id="editPrice" name="price" 
                               min="0.01" max="100" step="0.01" required 
                               value="<?= $order['price'] ?>">
                        <small class="form-text text-muted">最低价格: 0.01 元</small>
                    </div>
                    
                    <div class="form-group">
                        <label>交易方式</label>
                        <div>
                            <label class="radio-inline">
                                <input type="radio" name="trade_type" value="platform" <?= $order['trade_type'] == 'platform' ? 'checked' : '' ?>> 平台交易
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="trade_type" value="mediator" <?= $order['trade_type'] == 'mediator' ? 'checked' : '' ?>> 中介交易
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="trade_type" value="direct" <?= $order['trade_type'] == 'direct' ? 'checked' : '' ?>> 直接交易
                            </label>
                        </div>
                    </div>
                    
                    <?php if ($order['type'] == 'sell'): ?>
                    <div class="form-group" id="editContactInfoGroup">
                        <label for="editContactInfo">联系方式</label>
                        <input type="text" class="form-control" id="editContactInfo" name="contact_info" 
                               value="<?= htmlspecialchars($order['contact_info'] ?? '') ?>"
                               placeholder="请输入您的手机号、微信或QQ等联系方式">
                        <small class="form-text text-muted">此信息将展示给交易对方</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <strong>修改预览</strong>
                        <div class="trade-preview-small">
                            <div>原数量: <?= number_format($order['amount']) ?> BCT</div>
                            <div>原单价: <?= number_format($order['price'], 4) ?> 元</div>
                            <div>原总价: <?= number_format($order['total_amount'], 2) ?> 元</div>
                            <div class="total">新总价: <span id="editPreviewTotal">0.00</span> 元</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="confirmEdit">确认修改</button>
            </div>
        </div>
    </div>
</div>

<style>
.info-group {
    margin-bottom: 15px;
    display: flex;
    justify-content: between;
}

.info-group label {
    font-weight: 600;
    color: #555;
    min-width: 100px;
    margin-right: 10px;
}

.info-value {
    color: #333;
    flex: 1;
}

.trade-stat {
    padding: 15px;
}

.trade-stat .stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #ff6b00;
    margin-bottom: 5px;
}

.trade-stat .stat-label {
    color: #666;
    font-size: 14px;
}

.city-badge {
    background-color: #e3f2fd;
    color: #1976d2;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.contact-detail {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    border-left: 4px solid #ff6b00;
    font-weight: 500;
}

.contact-highlight {
    background: #fff3cd;
    padding: 8px;
    border-radius: 4px;
    font-weight: bold;
    color: #856404;
    text-align: center;
}

.action-buttons .btn {
    margin-bottom: 10px;
}

.trade-tips {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.tip-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #666;
}

.price-difference {
    text-align: center;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.label {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.label-success { background: #28a745; color: white; }
.label-warning { background: #ffc107; color: #212529; }
.label-primary { background: #007bff; color: white; }
.label-danger { background: #dc3545; color: white; }

.table th {
    background: #f8f9fa;
    font-weight: 600;
}

@media (max-width: 768px) {
    .info-group {
        flex-direction: column;
    }
    
    .info-group label {
        min-width: auto;
        margin-bottom: 5px;
    }
    
    .trade-stat .stat-number {
        font-size: 20px;
    }
}


</style>

<!-- 在文件末尾的JavaScript部分替换为以下代码 -->
<script>
// 模态框管理函数
function showEditModal() {
    // 更新预览
    updateEditPreview();
    
    // 显示模态框
    const modal = document.getElementById('editOrderModal');
    modal.style.display = 'block';
    modal.classList.add('show');
    
    // 添加背景遮罩
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    document.body.appendChild(backdrop);
    document.body.style.overflow = 'hidden';
}

function hideEditModal() {
    const modal = document.getElementById('editOrderModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
    
    // 移除背景遮罩
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
        backdrop.remove();
    }
    document.body.style.overflow = '';
}

function updateEditPreview() {
    const amount = parseInt(document.getElementById('editAmount').value) || 0;
    const price = parseFloat(document.getElementById('editPrice').value) || 0;
    const total = amount * price;
    document.getElementById('editPreviewTotal').textContent = total.toFixed(2);
}

function cancelOrder(orderId) {
    if (confirm('确定要取消这个订单吗？此操作不可撤销。')) {
        // 创建表单数据
        const formData = new FormData();
        formData.append('order_id', orderId);
        
        // 使用fetch API发送请求
        fetch('cancel_order.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('网络响应不正常');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('订单已取消');
                // 刷新页面
                location.reload();
            } else {
                alert('取消失败: ' + data.message);
            }
        })
        .catch(error => {
            console.error('错误:', error);
            alert('网络错误，请重试');
        });
    }
}

function buyFromOrder(orderId) {
    window.location.href = '../bct/trade.php?action=buy&order_id=' + orderId;
}

function sellToOrder(orderId) {
    window.location.href = '../bct/trade.php?action=sell&order_id=' + orderId;
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 编辑表单实时计算
    const editAmount = document.getElementById('editAmount');
    const editPrice = document.getElementById('editPrice');
    
    if (editAmount && editPrice) {
        editAmount.addEventListener('input', updateEditPreview);
        editPrice.addEventListener('input', updateEditPreview);
    }
    
    // 交易方式切换显示联系方式
    const tradeTypeRadios = document.querySelectorAll('input[name="trade_type"]');
    const contactInfoGroup = document.getElementById('editContactInfoGroup');
    
    tradeTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const method = this.value;
            if (contactInfoGroup) {
                contactInfoGroup.style.display = method === 'direct' ? 'block' : 'none';
            }
        });
    });
    
    // 确认编辑按钮
    const confirmEdit = document.getElementById('confirmEdit');
    if (confirmEdit) {
        confirmEdit.addEventListener('click', function() {
            const form = document.getElementById('editOrderForm');
            const formData = new FormData(form);
            
            fetch('update_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('订单更新成功');
                    hideEditModal();
                    location.reload();
                } else {
                    alert('更新失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('错误:', error);
                alert('网络错误，请重试');
            });
        });
    }
    
    // 关闭模态框的按钮
    const closeButtons = document.querySelectorAll('#editOrderModal .close, #editOrderModal .btn-default');
    closeButtons.forEach(button => {
        button.addEventListener('click', hideEditModal);
    });
    
    // 点击模态框背景关闭
    const modal = document.getElementById('editOrderModal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                hideEditModal();
            }
        });
    }
    
    // 初始隐藏联系方式输入框（如果不是直接交易）
    const currentMethod = document.querySelector('input[name="trade_type"]:checked');
    if (currentMethod && currentMethod.value !== 'direct' && contactInfoGroup) {
        contactInfoGroup.style.display = 'none';
    }
    
    // 初始更新预览
    updateEditPreview();
});
</script>

<!-- 添加模态框CSS样式 -->
<style>
/* 模态框基础样式 */
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    outline: 0;
}

.modal.show {
    display: block;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1040;
    width: 100vw;
    height: 100vh;
    background-color: #000;
}

.modal-backdrop.fade {
    opacity: 0;
}

.modal-backdrop.show {
    opacity: 0.5;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 500px;
    pointer-events: none;
}

.modal.show .modal-dialog {
    transform: none;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 0.3rem;
    outline: 0;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1rem 1rem;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: calc(0.3rem - 1px);
    border-top-right-radius: calc(0.3rem - 1px);
}

.modal-header .close {
    padding: 1rem 1rem;
    margin: -1rem -1rem -1rem auto;
    background-color: transparent;
    border: 0;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    color: #000;
    text-shadow: 0 1px 0 #fff;
    opacity: .5;
    cursor: pointer;
}

.modal-header .close:hover {
    opacity: .75;
}

.modal-title {
    margin-bottom: 0;
    line-height: 1.5;
    font-size: 1.25rem;
    font-weight: 500;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
}

.modal-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    padding: 0.75rem;
    border-top: 1px solid #dee2e6;
    border-bottom-right-radius: calc(0.3rem - 1px);
    border-bottom-left-radius: calc(0.3rem - 1px);
}

.modal-footer > * {
    margin: 0.25rem;
}

/* 表单样式 */
.form-group {
    margin-bottom: 1rem;
}

.form-control {
    display: block;
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    color: #495057;
    background-color: #fff;
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-text {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: #6c757d;
}

.radio-inline {
    display: inline-block;
    margin-right: 1rem;
    margin-bottom: 0;
    vertical-align: middle;
    cursor: pointer;
}

.radio-inline input[type="radio"] {
    margin-right: 0.25rem;
}

/* 按钮样式 */
.btn {
    display: inline-block;
    font-weight: 400;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    user-select: none;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, 
                border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    cursor: pointer;
}

.btn-block {
    display: block;
    width: 100%;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    color: #fff;
    background-color: #0069d9;
    border-color: #0062cc;
}

.btn-default {
    color: #333;
    background-color: #fff;
    border-color: #ccc;
}

.btn-default:hover {
    color: #333;
    background-color: #e6e6e6;
    border-color: #adadad;
}

.btn-info {
    color: #fff;
    background-color: #17a2b8;
    border-color: #17a2b8;
}

.btn-info:hover {
    color: #fff;
    background-color: #138496;
    border-color: #117a8b;
}

.btn-warning {
    color: #212529;
    background-color: #ffc107;
    border-color: #ffc107;
}

.btn-warning:hover {
    color: #212529;
    background-color: #e0a800;
    border-color: #d39e00;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 0.2rem;
}

.btn-outline-primary {
    color: #007bff;
    background-color: transparent;
    border-color: #007bff;
}

.btn-outline-primary:hover {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

/* 响应式调整 */
@media (max-width: 576px) {
    .modal-dialog {
        margin: 0.5rem;
        max-width: none;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>