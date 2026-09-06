<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/CityBCT.php';
require_once '../classes/BCTOrder.php';

$cityBCT = new CityBCT($pdo);
$bctOrder = new BCTOrder($pdo);

if (isset($_SESSION['message'])) { echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>'; unset($_SESSION['message']); }
if (isset($_SESSION['error'])) { echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>'; unset($_SESSION['error']); }

// 获取真实订单数据
try {
    $stmtSell = $pdo->prepare("
        SELECT o.*, u.username
        FROM bct_orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.type = 'sell' AND o.status IN ('pending', 'processing')
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $stmtSell->execute();
    $sellOrders = $stmtSell->fetchAll();

    $stmtBuy = $pdo->prepare("
        SELECT o.*, u.username
        FROM bct_orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.type = 'buy' AND o.status IN ('pending', 'processing')
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $stmtBuy->execute();
    $buyOrders = $stmtBuy->fetchAll();

    $stmtStats = $pdo->prepare("
        SELECT
            COUNT(*) as total_orders,
            COUNT(DISTINCT user_id) as active_traders,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as today_trades
        FROM bct_orders
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmtStats->execute();
    $stats = $stmtStats->fetch();

    // 当前挂单汇总：按城市统计买卖挂单数/最高买价/最低卖价
    $orderSummary = $pdo->query("
        SELECT
            city,
            SUM(CASE WHEN type='sell' THEN amount ELSE 0 END) as sell_amount,
            SUM(CASE WHEN type='buy' THEN amount ELSE 0 END) as buy_amount,
            COUNT(CASE WHEN type='sell' THEN 1 END) as sell_orders,
            COUNT(CASE WHEN type='buy' THEN 1 END) as buy_orders,
            MIN(CASE WHEN type='sell' THEN price END) as lowest_sell,
            MAX(CASE WHEN type='buy' THEN price END) as highest_buy
        FROM bct_orders
        WHERE status IN ('pending', 'processing')
        GROUP BY city
        ORDER BY (SUM(CASE WHEN type='sell' THEN amount ELSE 0 END) + SUM(CASE WHEN type='buy' THEN amount ELSE 0 END)) DESC
        LIMIT 10
    ")->fetchAll();

    $recentTrades = $bctOrder->getRecentTrades(null, 6);
} catch (Exception $e) {
    $sellOrders = [];
    $buyOrders = [];
    $stats = ['total_orders' => 0, 'active_traders' => 0, 'today_trades' => 0];
    $orderSummary = [];
    $recentTrades = [];
    error_log("BCT index error: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

<div class="bct-page-title" style="padding-top:20px;">
    <div>
        <h1><i class="fas fa-coins"></i> BCT 人气值交易所</h1>
        <div class="subtitle">城市人气值自由交易 · 实时行情 · 安全便捷</div>
    </div>
    <div>
        <a href="trade.php" class="btn btn-primary"><i class="fas fa-plus"></i> 发布交易</a>
        <a href="market.php" class="btn btn-default" style="margin-left:8px;"><i class="fas fa-chart-line"></i> 行情中心</a>
    </div>
</div>

<!-- 当前挂单统计 -->
<div class="bct-stats-grid" style="margin-bottom:24px;">
    <div class="bct-stat-card">
        <div class="label">出售挂单</div>
        <div class="value" style="color:var(--bct-down);"><?= number_format(count($sellOrders)) ?></div>
    </div>
    <div class="bct-stat-card">
        <div class="label">求购挂单</div>
        <div class="value" style="color:var(--bct-up);"><?= number_format(count($buyOrders)) ?></div>
    </div>
    <div class="bct-stat-card">
        <div class="label">活跃交易者</div>
        <div class="value"><?= number_format($stats['active_traders'] ?? 0) ?></div>
    </div>
    <div class="bct-stat-card">
        <div class="label">今日成交</div>
        <div class="value"><?= number_format($stats['today_trades'] ?? 0) ?> BCT</div>
    </div>
</div>

<!-- 当前挂单情况 -->
<?php if (!empty($orderSummary)): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h3 style="margin:0;font-size:16px;"><i class="fas fa-bolt"></i> 当前挂单情况</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table bct-market-table" style="margin-bottom:0;">
                <thead>
                    <tr>
                        <th>城市</th>
                        <th class="text-right">出售数量</th>
                        <th class="text-right">求购数量</th>
                        <th class="text-right">最低售价</th>
                        <th class="text-right">最高求购价</th>
                        <th class="text-center">价差</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderSummary as $s):
                        $spread = ($s['lowest_sell'] && $s['highest_buy']) ? ($s['lowest_sell'] - $s['highest_buy']) : null;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['city']) ?></strong></td>
                        <td class="text-right" style="color:var(--bct-down);"><?= number_format($s['sell_amount']) ?></td>
                        <td class="text-right" style="color:var(--bct-up);"><?= number_format($s['buy_amount']) ?></td>
                        <td class="text-right"><?= $s['lowest_sell'] ? '¥'.number_format($s['lowest_sell'], 4) : '-' ?></td>
                        <td class="text-right"><?= $s['highest_buy'] ? '¥'.number_format($s['highest_buy'], 4) : '-' ?></td>
                        <td class="text-center">
                            <?php if ($spread !== null): ?>
                                <span style="color:<?= $spread > 0 ? 'var(--bct-text-secondary)' : 'var(--bct-up)' ?>;">
                                    <?= $spread > 0 ? '+' : '' ?><?= number_format($spread, 4) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><a href="city.php?city=<?= urlencode($s['city']) ?>" class="btn btn-sm btn-primary">去交易</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 交易市场 -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <h3 style="margin:0;font-size:16px;"><i class="fas fa-exchange-alt"></i> 交易市场</h3>
        <a href="trade.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> 发布交易</a>
    </div>

    <!-- 筛选栏 -->
    <div class="card-body" style="border-bottom:1px solid var(--bct-border);">
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label style="color:var(--bct-text-secondary);font-size:13px;">城市</label>
                    <select class="form-control" id="cityFilter">
                        <option value="">全部城市</option>
                        <?php
                        $allCities = array_unique(array_merge(
                            array_column($sellOrders, 'city'),
                            array_column($buyOrders, 'city')
                        ));
                        sort($allCities);
                        foreach ($allCities as $city):
                            if (!$city) continue;
                        ?>
                        <option value="<?= htmlspecialchars($city) ?>"><?= htmlspecialchars($city) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label style="color:var(--bct-text-secondary);font-size:13px;">价格范围</label>
                    <select class="form-control" id="priceFilter">
                        <option value="">全部</option>
                        <option value="0.1-0.5">0.1-0.5元</option>
                        <option value="0.5-1">0.5-1元</option>
                        <option value="1-2">1-2元</option>
                        <option value="2-10">2-10元</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label style="color:var(--bct-text-secondary);font-size:13px;">排序</label>
                    <select class="form-control" id="sortFilter">
                        <option value="newest">最新发布</option>
                        <option value="price_low">价格最低</option>
                        <option value="price_high">价格最高</option>
                        <option value="amount_high">数量最多</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="form-group">
                    <label style="color:var(--bct-text-secondary);font-size:13px;">&nbsp;</label>
                    <button class="btn btn-default btn-block" id="resetFilter"><i class="fas fa-refresh"></i> 重置筛选</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 选项卡 -->
    <div class="bct-home-tabs">
        <button class="bct-home-tab active" data-tab="sell">
            <i class="fas fa-arrow-up"></i> 人气售卖
            <span class="badge" style="background:var(--bct-down);color:#fff;margin-left:6px;"><?= count($sellOrders) ?></span>
        </button>
        <button class="bct-home-tab" data-tab="buy">
            <i class="fas fa-arrow-down"></i> 人气求购
            <span class="badge" style="background:var(--bct-up);color:#fff;margin-left:6px;"><?= count($buyOrders) ?></span>
        </button>
    </div>

    <!-- 售卖列表 -->
    <div class="tab-pane active" id="sell-tab">
        <div class="table-responsive">
            <table class="table bct-home-table">
                <thead>
                    <tr>
                        <th>城市</th>
                        <th>售卖数量</th>
                        <th>售卖单价</th>
                        <th>总价</th>
                        <th>出售者</th>
                        <th>联系方式 / 方式</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sellOrders)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:40px;color:var(--bct-text-secondary);">暂无售卖订单</td></tr>
                    <?php else: ?>
                    <?php foreach ($sellOrders as $order): ?>
                    <tr class="order-row" data-city="<?= htmlspecialchars($order['city'] ?? '') ?>" data-price="<?= $order['price'] ?? 0 ?>" data-amount="<?= $order['amount'] ?? 0 ?>" data-time="<?= strtotime($order['created_at'] ?? 'now') ?>">
                        <td><strong><?= htmlspecialchars($order['city'] ?? '未知') ?></strong></td>
                        <td><span class="amount"><?= number_format($order['amount'] ?? 0) ?> BCT</span></td>
                        <td><span class="price" style="color:var(--bct-down);">¥<?= number_format($order['price'] ?? 0, 4) ?></span></td>
                        <td><span class="total-price">¥<?= number_format($order['total_amount'] ?? 0, 2) ?></span></td>
                        <td><?= htmlspecialchars($order['username'] ?? '用户_' . substr($order['user_id'] ?? '', -4)) ?></td>
                        <td>
                            <?php if (!empty($order['contact_info'])): ?>
                            <small style="color:var(--bct-text-secondary);"><i class="fas fa-user"></i> <?= htmlspecialchars($order['contact_info']) ?></small><br>
                            <?php endif; ?>
                            <small style="color:var(--bct-text-muted);"><?= tradeTypeLabel($order['trade_type'] ?? '') ?></small>
                        </td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="buyOrder(<?= $order['id'] ?? 0 ?>, '<?= htmlspecialchars($order['city'] ?? '') ?>', <?= $order['amount'] ?? 0 ?>, <?= $order['price'] ?? 0 ?>)">购买</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 求购列表 -->
    <div class="tab-pane" id="buy-tab" style="display:none;">
        <div class="table-responsive">
            <table class="table bct-home-table">
                <thead>
                    <tr>
                        <th>城市</th>
                        <th>求购数量</th>
                        <th>求购单价</th>
                        <th>总价</th>
                        <th>求购者</th>
                        <th>联系方式 / 方式</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($buyOrders)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:40px;color:var(--bct-text-secondary);">暂无求购订单</td></tr>
                    <?php else: ?>
                    <?php foreach ($buyOrders as $order): ?>
                    <tr class="order-row" data-city="<?= htmlspecialchars($order['city'] ?? '') ?>" data-price="<?= $order['price'] ?? 0 ?>" data-amount="<?= $order['amount'] ?? 0 ?>" data-time="<?= strtotime($order['created_at'] ?? 'now') ?>">
                        <td><strong><?= htmlspecialchars($order['city'] ?? '未知') ?></strong></td>
                        <td><span class="amount"><?= number_format($order['amount'] ?? 0) ?> BCT</span></td>
                        <td><span class="price" style="color:var(--bct-up);">¥<?= number_format($order['price'] ?? 0, 4) ?></span></td>
                        <td><span class="total-price">¥<?= number_format($order['total_amount'] ?? 0, 2) ?></span></td>
                        <td><?= htmlspecialchars($order['username'] ?? '用户_' . substr($order['user_id'] ?? '', -4)) ?></td>
                        <td>
                            <?php if (!empty($order['contact_info'])): ?>
                            <small style="color:var(--bct-text-secondary);"><i class="fas fa-user"></i> <?= htmlspecialchars($order['contact_info']) ?></small><br>
                            <?php endif; ?>
                            <small style="color:var(--bct-text-muted);"><?= tradeTypeLabel($order['trade_type'] ?? '') ?></small>
                        </td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="sellOrder(<?= $order['id'] ?? 0 ?>, '<?= htmlspecialchars($order['city'] ?? '') ?>', <?= $order['amount'] ?? 0 ?>, <?= $order['price'] ?? 0 ?>)">出售</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 交易说明 -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-info-circle"></i> 交易说明</h3></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <h4 style="font-size:15px;"><i class="fas fa-shopping-cart" style="color:var(--bct-accent);"></i> 平台交易</h4>
                <p style="color:var(--bct-text-secondary);font-size:13px;">500BCT以下适用，平台自动撮合交易，手续费10%，安全便捷。</p>
            </div>
            <div class="col-md-4">
                <h4 style="font-size:15px;"><i class="fas fa-user" style="color:var(--bct-up);"></i> 中介交易</h4>
                <p style="color:var(--bct-text-secondary);font-size:13px;">通过平台客服完成交易，手续费2%，资金安全有保障。</p>
            </div>
            <div class="col-md-4">
                <h4 style="font-size:15px;"><i class="fas fa-exchange-alt" style="color:var(--bct-down);"></i> 直接交易</h4>
                <p style="color:var(--bct-text-secondary);font-size:13px;">买卖双方直接联系，无手续费，交易快捷但需注意风险。</p>
            </div>
        </div>
    </div>
</div>

<!-- 最新成交 -->
<?php if (!empty($recentTrades)): ?>
<div style="margin-bottom:24px;">
    <h3 style="font-size:16px;color:var(--bct-text);margin-bottom:16px;"><i class="fas fa-history"></i> 最新成交动态</h3>
    <div class="bct-top5-grid">
        <?php foreach ($recentTrades as $rt):
            $side = ($rt['order_type'] ?? '') === 'buy' ? 'buy' : 'sell';
        ?>
        <div class="bct-city-card">
            <div class="city-name"><?= htmlspecialchars($rt['city']) ?></div>
            <div class="city-price">¥<?= number_format($rt['price'], 4) ?></div>
            <div class="city-meta">
                <span><?= number_format($rt['amount']) ?> BCT</span>
                <span style="color:<?= $side==='buy' ? 'var(--bct-up)' : 'var(--bct-down)' ?>;"><?= $side==='buy' ? '买' : '卖' ?></span>
            </div>
            <div style="font-size:11px;color:var(--bct-text-muted);margin-top:6px;"><?= date('m-d H:i', strtotime($rt['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 交易确认模态框 -->
<div class="modal fade" id="tradeModal" tabindex="-1" role="dialog" aria-labelledby="tradeModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="tradeModalLabel">确认交易</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="tradeInfo" style="margin-bottom:16px;"></div>
                <form id="tradeForm">
                    <input type="hidden" id="tradeOrderId" name="order_id">
                    <input type="hidden" id="tradeType" name="trade_type">
                    <input type="hidden" id="tradeCity" name="city">
                    <div class="form-group">
                        <label>交易数量 (BCT)</label>
                        <input type="number" class="form-control" id="tradeAmount" name="amount" min="1" required>
                        <small style="color:var(--bct-text-secondary);" id="maxAmountHint"></small>
                    </div>
                    <div class="form-group">
                        <label>单价 (元/BCT)</label>
                        <input type="number" class="form-control" id="tradePrice" name="price" step="0.01" min="0.1" required>
                    </div>
                    <div class="form-group">
                        <label>交易方式</label>
                        <div>
                            <label class="radio-inline"><input type="radio" name="execute_type" value="platform" checked> 平台交易</label>
                            <label class="radio-inline"><input type="radio" name="execute_type" value="mediator"> 中介交易</label>
                            <label class="radio-inline"><input type="radio" name="execute_type" value="direct"> 直接交易</label>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <strong>交易预览</strong>
                        <div class="trade-preview-small">
                            <div>数量: <span id="previewAmount">0</span> BCT</div>
                            <div>单价: <span id="previewPrice">0</span> 元</div>
                            <div>总价: <span id="previewTotal">0</span> 元</div>
                            <div>手续费: <span id="previewFee">0</span> 元</div>
                            <div class="total">实付/实收: <span id="previewNet">0</span> 元</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="confirmTrade">确认交易</button>
            </div>
        </div>
    </div>
</div>

<style>
.bct-home-tabs {
    display: flex;
    border-bottom: 1px solid var(--bct-border);
    background: var(--bct-bg-tertiary);
}
.bct-home-tab {
    flex: 1;
    padding: 14px 20px;
    border: none;
    background: transparent;
    color: var(--bct-text-secondary);
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.bct-home-tab.active {
    color: var(--bct-text);
    background: var(--bct-bg-secondary);
    border-bottom: 3px solid var(--bct-accent);
}
.bct-home-tab:hover { color: var(--bct-text); background: var(--bct-bg-hover); }
.bct-home-table { margin-bottom: 0; }
.bct-home-table th {
    background: var(--bct-bg-tertiary);
    color: var(--bct-text-secondary);
    font-size: 12px;
    text-transform: uppercase;
    border-bottom: 1px solid var(--bct-border);
}
.bct-home-table td {
    border-bottom: 1px solid var(--bct-border);
    vertical-align: middle;
    color: var(--bct-text);
}
.bct-home-table .amount { font-weight: 600; font-family: monospace; }
.bct-home-table .price { font-weight: 700; font-family: monospace; }
.bct-home-table .total-price { font-family: monospace; color: var(--bct-accent); }
.trade-preview-small { font-size: 14px; line-height: 1.6; }
.trade-preview-small .total {
    font-weight: 700;
    color: var(--bct-accent);
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid var(--bct-border);
}
.radio-inline { margin-right: 15px; color: var(--bct-text); }
.radio-inline input { margin-right: 5px; }
@media (max-width: 768px) {
    .bct-home-tabs { flex-direction: column; }
    .bct-home-table { font-size: 12px; }
    .bct-home-table th, .bct-home-table td { padding: 8px 6px; white-space: nowrap; }
}
</style>

<script>
$(document).ready(function() {
    $('.bct-home-tab').click(function() {
        const tabId = $(this).data('tab');
        $('.bct-home-tab').removeClass('active');
        $(this).addClass('active');
        $('.tab-pane').hide();
        $('#' + tabId + '-tab').show();
    });

    $('#cityFilter, #priceFilter').change(filterOrders);
    $('#sortFilter').change(sortAndFilterOrders);

    $('#resetFilter').click(function() {
        $('#cityFilter, #priceFilter, #sortFilter').val('');
        $('.order-row').show();
        sortAndFilterOrders();
    });

    $('#tradeAmount, #tradePrice').on('input', updateTradePreview);
    $('input[name="execute_type"]').change(updateTradePreview);
    $('#confirmTrade').click(executeTrade);
});

function buyOrder(orderId, city, maxAmount, price) {
    if (event) event.stopPropagation();
    showTradeModal('buy', orderId, city, maxAmount, price);
}

function sellOrder(orderId, city, maxAmount, price) {
    if (event) event.stopPropagation();
    showTradeModal('sell', orderId, city, maxAmount, price);
}

function showTradeModal(type, orderId, city, maxAmount, price) {
    const title = type === 'buy' ? '购买人气值' : '出售人气值';
    $('#tradeModalLabel').text(title);
    $('#tradeOrderId').val(orderId);
    $('#tradeType').val(type);
    $('#tradeCity').val(city);
    $('#tradeAmount').val(maxAmount).attr('max', maxAmount);
    $('#tradePrice').val(price);
    $('#maxAmountHint').text('最大数量: ' + maxAmount.toLocaleString() + ' BCT');
    $('#tradeInfo').html(
        '<p><strong>订单信息</strong></p>' +
        '<p>城市: ' + city + '</p>' +
        '<p>' + (type === 'buy' ? '出售者' : '求购者') + '报价: ¥' + price + ' /BCT</p>' +
        '<p>可' + (type === 'buy' ? '购买' : '出售') + '数量: ' + maxAmount.toLocaleString() + ' BCT</p>'
    );
    updateTradePreview();
    $('#tradeModal').modal('show');
}

function updateTradePreview() {
    const amount = parseInt($('#tradeAmount').val()) || 0;
    const price = parseFloat($('#tradePrice').val()) || 0;
    const type = $('#tradeType').val();
    const method = $('input[name="execute_type"]:checked').val();
    let feeRate = 0;
    if (method === 'platform') feeRate = 0.10;
    else if (method === 'mediator') feeRate = 0.02;
    const total = amount * price;
    const fee = total * feeRate;
    const net = type === 'buy' ? total : total - fee;
    $('#previewAmount').text(amount.toLocaleString());
    $('#previewPrice').text(price.toFixed(2));
    $('#previewTotal').text(total.toFixed(2));
    $('#previewFee').text(fee.toFixed(2));
    $('#previewNet').text(net.toFixed(2));
}

function executeTrade() {
    const orderId = $('#tradeOrderId').val();
    const amount = $('#tradeAmount').val();
    const price = $('#tradePrice').val();
    if (!amount || amount <= 0) { alert('请输入有效的交易数量'); return; }
    if (!price || price < 0.1) { alert('单价不能低于0.1元'); return; }

    $('#confirmTrade').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 处理中...');
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    $.ajax({
        url: 'api/execute_trade.php',
        type: 'POST',
        dataType: 'json',
        data: {
            order_id: orderId,
            amount: amount,
            price: price,
            execute_type: $('input[name="execute_type"]:checked').val(),
            csrf_token: csrfToken
        },
        success: function(res) {
            if (res.success) {
                alert('交易成功！页面将刷新。');
                location.reload();
            } else {
                alert('交易失败：' + res.message);
                $('#confirmTrade').prop('disabled', false).text('确认交易');
            }
        },
        error: function() {
            alert('网络错误，请重试');
            $('#confirmTrade').prop('disabled', false).text('确认交易');
        }
    });
}

function filterOrders() {
    const city = $('#cityFilter').val();
    const priceRange = $('#priceFilter').val();
    const activeTab = $('.bct-home-tab.active').data('tab');

    $('#' + activeTab + '-tab .order-row').each(function() {
        const rowCity = $(this).data('city');
        const rowPrice = parseFloat($(this).data('price')) || 0;
        let show = true;
        if (city && rowCity !== city) show = false;
        if (priceRange && show) {
            const [min, max] = priceRange.split('-').map(Number);
            if (rowPrice < min || rowPrice > max) show = false;
        }
        $(this).toggle(show);
    });
}

function sortAndFilterOrders() {
    const activeTab = $('.bct-home-tab.active').data('tab');
    const sort = $('#sortFilter').val();
    const $tbody = $('#' + activeTab + '-tab tbody');
    const $rows = $tbody.find('.order-row').get();

    $rows.sort(function(a, b) {
        const $a = $(a), $b = $(b);
        if (sort === 'price_low') return parseFloat($a.data('price')) - parseFloat($b.data('price'));
        if (sort === 'price_high') return parseFloat($b.data('price')) - parseFloat($a.data('price'));
        if (sort === 'amount_high') return parseFloat($b.data('amount')) - parseFloat($a.data('amount'));
        return parseInt($b.data('time')) - parseInt($a.data('time'));
    });

    $.each($rows, function(idx, row) { $tbody.append(row); });
    filterOrders();
}
</script>

<?php
function tradeTypeLabel($type) {
    switch ($type) {
        case 'platform': return '<i class="fas fa-shopping-cart"></i> 平台交易';
        case 'mediator': return '<i class="fas fa-user"></i> 中介交易';
        case 'direct': return '<i class="fas fa-exchange-alt"></i> 直接交易';
        default: return '平台交易';
    }
}
require_once 'includes/footer.php';
?>
