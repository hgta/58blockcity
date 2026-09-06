<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/CityBCT.php';
require_once '../classes/BCTOrder.php';
require_once '../classes/UserBCTAccount.php';

$cityBCT = new CityBCT($pdo);
$bctOrder = new BCTOrder($pdo);

$city = trim($_GET['city'] ?? '');
if (!$city) {
    header('Location: market.php');
    exit;
}

$cityInfo = $cityBCT->getCityBCT($city);
if (!$cityInfo) {
    header('Location: market.php');
    exit;
}

$changes = $cityBCT->get24hChanges();
$cityInfo['change_pct'] = $changes[$city] ?? 0;
$cityInfo['volume_24h'] = $cityBCT->getCity24hVolume($city);
$cityInfo['market_cap'] = $cityInfo['circulating_supply'] * $cityInfo['current_price'];
$highLow = $cityBCT->getCity24hHighLow($city);

$interval = $_GET['interval'] ?? '24h';
$allowedIntervals = ['1h','24h','7d','30d'];
if (!in_array($interval, $allowedIntervals)) $interval = '24h';
$priceHistory = $cityBCT->getPriceHistory($city, $interval);

$orderBookAsks = $bctOrder->getOrderBook($city, 'sell', 50);
$orderBookBids = $bctOrder->getOrderBook($city, 'buy', 50);
$recentTrades = $bctOrder->getRecentTrades($city, 20);

$site_config['title'] = htmlspecialchars($city) . ' BCT 行情 | 58BCT交易市场';
$site_config['description'] = htmlspecialchars($city) . ' 人气值(BCT)实时行情、价格走势、买卖盘深度与快速交易。';

require_once 'includes/header.php';
?>

<div class="bct-page-title" style="padding-top:20px;">
    <div>
        <h1><i class="fas fa-city"></i> <?= htmlspecialchars($city) ?> <span class="city-symbol"><?= htmlspecialchars($city) ?>BCT</span></h1>
        <div class="subtitle">城市人气值交易详情 · <?= htmlspecialchars($city) ?>/CNY</div>
    </div>
    <div>
        <a href="market.php" class="btn btn-default"><i class="fas fa-arrow-left"></i> 返回行情</a>
        <a href="trade.php?city=<?= urlencode($city) ?>" class="btn btn-primary" style="margin-left:8px;"><i class="fas fa-plus"></i> 发布交易</a>
    </div>
</div>

<div class="bct-city-hero">
    <div style="display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap;">
        <div class="city-price-large">¥<?= number_format($cityInfo['current_price'], 4) ?></div>
        <?php $cls = $cityInfo['change_pct'] >= 0 ? 'up' : 'down'; $sign = $cityInfo['change_pct'] >= 0 ? '+' : ''; ?>
        <div class="city-change-large <?= $cls ?>"><?= $sign ?><?= number_format($cityInfo['change_pct'], 2) ?>%</div>
    </div>
    <div class="bct-hero-stats">
        <div class="bct-hero-stat">
            <div class="label">24h 最高</div>
            <div class="value">¥<?= number_format($highLow['high'] ?? 0, 4) ?></div>
        </div>
        <div class="bct-hero-stat">
            <div class="label">24h 最低</div>
            <div class="value">¥<?= number_format($highLow['low'] ?? 0, 4) ?></div>
        </div>
        <div class="bct-hero-stat">
            <div class="label">24h 成交量</div>
            <div class="value">¥<?= number_format($cityInfo['volume_24h'], 2) ?></div>
        </div>
        <div class="bct-hero-stat">
            <div class="label">流通市值</div>
            <div class="value">¥<?= number_format($cityInfo['market_cap'], 2) ?></div>
        </div>
        <div class="bct-hero-stat">
            <div class="label">流通量</div>
            <div class="value"><?= number_format($cityInfo['circulating_supply']) ?></div>
        </div>
        <div class="bct-hero-stat">
            <div class="label">总供应量</div>
            <div class="value"><?= number_format($cityInfo['total_supply']) ?></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:16px;"><i class="fas fa-chart-area"></i> 价格走势</h3>
                <div class="btn-group">
                    <?php foreach ($allowedIntervals as $iv): ?>
                    <a href="?city=<?= urlencode($city) ?>&interval=<?= $iv ?>" class="btn btn-sm <?= $interval===$iv?'btn-primary':'btn-default' ?>"><?= $iv ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($priceHistory) < 2): ?>
                <div class="text-center" style="padding:60px 20px;color:var(--bct-text-secondary);">
                    <i class="fas fa-chart-line" style="font-size:48px;display:block;margin-bottom:16px;opacity:.3;"></i>
                    <p>该城市成交数据不足，无法绘制走势图</p>
                    <a href="trade.php?city=<?= urlencode($city) ?>" class="btn btn-primary">成为第一个交易者</a>
                </div>
                <?php else: ?>
                <div id="priceChart" style="width:100%;height:360px;"></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-book"></i> 买卖盘</h3></div>
                    <div class="card-body" style="padding:0;">
                        <div class="bct-order-book" id="orderBook">
                            <div class="bct-order-book-header">
                                <span>价格(CNY)</span>
                                <span class="text-right">数量</span>
                                <span class="text-right">累计</span>
                            </div>
                            <div class="order-book-asks">
                                <?php foreach ($orderBookAsks as $i => $ask): ?>
                                <div class="bct-order-book-row ask <?= $i >= 10 ? 'hidden-row' : '' ?>">
                                    <span class="bar" style="width:<?= min(100, ($ask['cumulative_amount']/max(1,$orderBookAsks[count($orderBookAsks)-1]['cumulative_amount'])*100)) ?>%;"></span>
                                    <span class="price"><?= number_format($ask['price'], 4) ?></span>
                                    <span class="text-right"><?= number_format($ask['total_amount']) ?></span>
                                    <span class="text-right"><?= number_format($ask['cumulative_amount']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="height:1px;background:var(--bct-border);margin:8px 0;"></div>
                            <div class="order-book-bids">
                                <?php foreach ($orderBookBids as $i => $bid): ?>
                                <div class="bct-order-book-row bid <?= $i >= 10 ? 'hidden-row' : '' ?>">
                                    <span class="bar" style="width:<?= min(100, ($bid['cumulative_amount']/max(1,$orderBookBids[count($orderBookBids)-1]['cumulative_amount'])*100)) ?>%;"></span>
                                    <span class="price"><?= number_format($bid['price'], 4) ?></span>
                                    <span class="text-right"><?= number_format($bid['total_amount']) ?></span>
                                    <span class="text-right"><?= number_format($bid['cumulative_amount']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($orderBookAsks) > 10 || count($orderBookBids) > 10): ?>
                            <div style="text-align:center;padding:10px;">
                                <button type="button" class="btn btn-sm btn-default" id="expandOrderBook">展开更多</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card" style="margin-bottom:24px;">
                    <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-history"></i> 最新成交</h3></div>
                    <div class="bct-trade-list" id="recentTrades" data-city="<?= htmlspecialchars($city) ?>">
                        <?php if (empty($recentTrades)): ?>
                        <div class="text-center empty-trades" style="padding:30px;color:var(--bct-text-secondary);">暂无成交</div>
                        <?php else: ?>
                        <?php foreach ($recentTrades as $t):
                            $side = ($t['order_type'] ?? '') === 'buy' ? 'buy' : 'sell';
                            $sideText = $side === 'buy' ? '买' : '卖';
                        ?>
                        <div class="bct-trade-item">
                            <span class="side <?= $side ?>"><?= $sideText ?></span>
                            <span class="price">¥<?= number_format($t['price'], 4) ?></span>
                            <span class="num"><?= number_format($t['amount']) ?></span>
                            <span class="time"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="bct-trade-panel">
            <h3 style="margin:0 0 16px 0;font-size:16px;"><i class="fas fa-bolt"></i> 快速交易</h3>
            <div class="tab-buttons">
                <button type="button" class="tab-btn active buy" data-trade="buy">买入</button>
                <button type="button" class="tab-btn sell" data-trade="sell">卖出</button>
            </div>
            <form id="quickTradeForm" method="post" action="process_order.php">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="city" value="<?= htmlspecialchars($city) ?>">
                <input type="hidden" name="type" id="quickTradeType" value="buy">

                <div class="form-group">
                    <label>单价 (CNY/BCT)</label>
                    <input type="number" name="price" id="quickPrice" class="form-control" step="0.01" min="0.01" value="<?= number_format($cityInfo['current_price'], 4) ?>" required>
                </div>
                <div class="form-group">
                    <label>数量 (BCT)</label>
                    <input type="number" name="amount" id="quickAmount" class="form-control" min="1" max="100000" required placeholder="请输入数量">
                </div>
                <div class="form-group">
                    <label>交易方式</label>
                    <select name="trade_type" id="quickMethod" class="form-control">
                        <option value="direct">直接交易（0% 手续费）</option>
                        <option value="platform">平台交易（10% 手续费）</option>
                        <option value="mediator">中介交易（2% 手续费）</option>
                    </select>
                </div>
                <div class="form-group" id="quickContactGroup">
                    <label>联系方式</label>
                    <input type="text" name="contact_info" class="form-control" placeholder="手机号 / 微信 / QQ">
                </div>

                <div class="preview-row">
                    <span>总价</span>
                    <span id="quickTotal">0.00 CNY</span>
                </div>
                <div class="preview-row">
                    <span>手续费</span>
                    <span id="quickFee">0.00 CNY</span>
                </div>
                <div class="preview-row total">
                    <span id="quickNetLabel">实付</span>
                    <span id="quickNet">0.00 CNY</span>
                </div>

                <button type="submit" class="btn btn-lg btn-block" id="quickSubmit" style="margin-top:16px;background:var(--bct-up);color:#fff;">立即买入</button>
            </form>
        </div>
    </div>
</div>

<script>
$(function() {
    // 图表
    <?php if (count($priceHistory) >= 2): ?>
    var chartData = <?= json_encode($priceHistory) ?>;
    BCTCharts.initPriceChart('priceChart', chartData, '<?= addslashes($city) ?>');
    <?php endif; ?>

    // 快速交易 Tab
    $('.tab-btn').click(function() {
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        var type = $(this).data('trade');
        $('#quickTradeType').val(type);
        if (type === 'buy') {
            $('#quickSubmit').text('立即买入').css('background', 'var(--bct-up)');
            $('#quickNetLabel').text('实付');
        } else {
            $('#quickSubmit').text('立即卖出').css('background', 'var(--bct-down)');
            $('#quickNetLabel').text('实收');
        }
        updateQuickPreview();
    });

    // 交易方式切换显示联系方式
    $('#quickMethod').change(function() {
        $('#quickContactGroup').toggle($(this).val() === 'direct');
        updateQuickPreview();
    }).trigger('change');

    // 实时计算
    $('#quickPrice, #quickAmount, #quickMethod').on('input change', updateQuickPreview);

    function updateQuickPreview() {
        var amount = parseInt($('#quickAmount').val()) || 0;
        var price = parseFloat($('#quickPrice').val()) || 0;
        var type = $('#quickTradeType').val();
        var method = $('#quickMethod').val();
        var feeRate = method === 'platform' ? 0.10 : (method === 'mediator' ? 0.02 : 0);
        var total = amount * price;
        var fee = total * feeRate;
        var net = type === 'buy' ? total + fee : total - fee;
        $('#quickTotal').text(total.toFixed(2) + ' CNY');
        $('#quickFee').text(fee.toFixed(2) + ' CNY');
        $('#quickNet').text(net.toFixed(2) + ' CNY');
    }
    updateQuickPreview();

    // 展开买卖盘
    $('#expandOrderBook').click(function() {
        $('#orderBook .hidden-row').removeClass('hidden-row');
        $(this).hide();
    });

    // 最新成交 30 秒轮询
    var tradeCity = $('#recentTrades').data('city');
    if (tradeCity) {
        setInterval(function() {
            $.getJSON('api/recent_trades.php?city=' + encodeURIComponent(tradeCity) + '&limit=20', function(data) {
                if (data.success && data.trades.length > 0) {
                    var html = '';
                    $.each(data.trades, function(i, t) {
                        var side = t.order_type === 'buy' ? 'buy' : 'sell';
                        var text = t.order_type === 'buy' ? '买' : '卖';
                        html += '<div class="bct-trade-item">' +
                            '<span class="side ' + side + '">' + text + '</span>' +
                            '<span class="price">¥' + parseFloat(t.price).toFixed(4) + '</span>' +
                            '<span class="num">' + parseInt(t.amount).toLocaleString() + '</span>' +
                            '<span class="time">' + t.time + '</span>' +
                        '</div>';
                    });
                    $('#recentTrades').html(html);
                }
            });
        }, 30000);
    }
});
</script>

<style>
.bct-order-book-row.hidden-row { display: none; }
#orderBook.expanded .hidden-row { display: grid; }
</style>

<?php require_once 'includes/footer.php'; ?>
