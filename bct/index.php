<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/CityBCT.php';
require_once '../classes/BCTOrder.php';

$cityBCT = new CityBCT($pdo);
$bctOrder = new BCTOrder($pdo);

if (isset($_SESSION['message'])) { echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>'; unset($_SESSION['message']); }
if (isset($_SESSION['error'])) { echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>'; unset($_SESSION['error']); }

$top5Cities = ['北京','上海','广州','深圳','杭州'];

try {
    $allCities = $cityBCT->getAllCitiesBCT();
    $changes = $cityBCT->get24hChanges();
    $citiesWithData = [];
    foreach ($allCities as $city) {
        $city['change_pct'] = $changes[$city['city']] ?? 0;
        $city['volume_24h'] = $cityBCT->getCity24hVolume($city['city']);
        $city['market_cap'] = $city['circulating_supply'] * $city['current_price'];
        $citiesWithData[] = $city;
    }
    usort($citiesWithData, fn($a,$b)=>$b['market_cap']<=>$a['market_cap']);
    $tickerCities = array_slice($citiesWithData, 0, 30);
    $top5Data = [];
    foreach ($top5Cities as $name) {
        foreach ($citiesWithData as $city) { if ($city['city']===$name) { $top5Data[]=$city; break; } }
    }
    $marketStats = $cityBCT->getMarketStats();
    $recentTrades = $bctOrder->getRecentTrades(null, 12);
    $activeBuyOrders = $bctOrder->getActiveOrders('buy', 10);
    $activeSellOrders = $bctOrder->getActiveOrders('sell', 10);

    // 当前挂单汇总：按城市统计买卖挂单数/最高买价/最低卖价/总价
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
        LIMIT 20
    ")->fetchAll();
} catch (Exception $e) {
    $citiesWithData = $tickerCities = $top5Data = [];
    $marketStats = ['total_volume_24h'=>0,'total_market_cap'=>0,'gainers_count'=>0,'losers_count'=>0,'active_orders'=>0];
    $recentTrades = [];
    $activeBuyOrders = [];
    $activeSellOrders = [];
    $orderSummary = [];
    error_log("BCT index error: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

<div class="bct-page-title" style="padding-top:20px;">
    <div>
        <h1><i class="fas fa-coins"></i> 城市人气值交易所</h1>
        <div class="subtitle">城市人气值自由交易 · 实时行情 · 安全便捷</div>
    </div>
    <div>
        <a href="trade.php" class="btn btn-primary"><i class="fas fa-plus"></i> 发布交易</a>
        <a href="market.php" class="btn btn-default" style="margin-left:8px;"><i class="fas fa-chart-line"></i> 行情中心</a>
    </div>
</div>

<!-- 跑马灯 -->
<div class="bct-ticker-wrap">
    <div class="bct-ticker">
        <?php for ($i = 0; $i < 2; $i++): ?>
        <?php foreach ($tickerCities as $city):
            $cls = $city['change_pct'] >= 0 ? 'up' : 'down';
            $icon = $city['change_pct'] >= 0 ? '▲' : '▼';
            $sign = $city['change_pct'] >= 0 ? '+' : '';
        ?>
        <a href="city.php?city=<?= urlencode($city['city']) ?>" class="bct-ticker-item">
            <span class="city"><?= htmlspecialchars($city['city']) ?></span>
            <span class="price">¥<?= number_format($city['current_price'], 2) ?></span>
            <span class="change <?= $cls ?>"><?= $icon ?> <?= $sign ?><?= number_format($city['change_pct'], 2) ?>%</span>
        </a>
        <?php endforeach; ?>
        <?php endfor; ?>
    </div>
</div>

<!-- 市场统计 -->
<div class="bct-stats-grid">
    <div class="bct-stat-card">
        <div class="label">24h 成交额</div>
        <div class="value">¥<?= number_format($marketStats['total_volume_24h'], 2) ?></div>
    </div>
    <div class="bct-stat-card">
        <div class="label">总市值</div>
        <div class="value">¥<?= number_format($marketStats['total_market_cap'] / 10000000, 2) ?> 千万</div>
    </div>
    <div class="bct-stat-card">
        <div class="label">涨跌城市</div>
        <div class="value"><span class="up">▲ <?= $marketStats['gainers_count'] ?></span> / <span class="down">▼ <?= $marketStats['losers_count'] ?></span></div>
    </div>
    <div class="bct-stat-card">
        <div class="label">活跃订单</div>
        <div class="value"><?= number_format($marketStats['active_orders']) ?></div>
    </div>
</div>

<!-- TOP5 热门城市 -->
<h3 style="font-size:16px;color:var(--bct-text);margin-bottom:16px;"><i class="fas fa-fire"></i> TOP5 热门城市</h3>
<div class="bct-top5-grid">
    <?php foreach ($top5Data as $city):
        $cls = $city['change_pct'] >= 0 ? 'up' : 'down';
        $sign = $city['change_pct'] >= 0 ? '+' : '';
    ?>
    <a href="city.php?city=<?= urlencode($city['city']) ?>" class="bct-city-card">
        <div class="city-name"><?= htmlspecialchars($city['city']) ?></div>
        <div class="city-price">¥<?= number_format($city['current_price'], 2) ?></div>
        <div class="city-change <?= $cls ?>"><?= $sign ?><?= number_format($city['change_pct'], 2) ?>%</div>
        <div class="city-meta">
            <span>24h 成交 ¥<?= number_format($city['volume_24h'], 0) ?></span>
            <span>市值 ¥<?= number_format($city['market_cap'] / 1000000, 2) ?> 百万</span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- 买入挂单 / 卖出挂单 / 最新成交 -->
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:16px;"><i class="fas fa-arrow-down" style="color:var(--bct-up);"></i> 买入挂单</h3>
                <a href="market.php" class="btn btn-sm btn-default">更多</a>
            </div>
            <div class="bct-trade-list">
                <?php if (empty($activeBuyOrders)): ?>
                <div class="text-center" style="padding:30px;color:var(--bct-text-secondary);">暂无买入挂单</div>
                <?php else: ?>
                <?php foreach ($activeBuyOrders as $o): ?>
                <a href="city.php?city=<?= urlencode($o['city']) ?>" class="bct-trade-item" style="text-decoration:none;">
                    <span><strong><?= htmlspecialchars($o['city']) ?></strong></span>
                    <span class="price">¥<?= number_format($o['price'], 2) ?></span>
                    <span class="num"><?= number_format($o['amount']) ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:16px;"><i class="fas fa-arrow-up" style="color:var(--bct-down);"></i> 卖出挂单</h3>
                <a href="market.php" class="btn btn-sm btn-default">更多</a>
            </div>
            <div class="bct-trade-list">
                <?php if (empty($activeSellOrders)): ?>
                <div class="text-center" style="padding:30px;color:var(--bct-text-secondary);">暂无卖出挂单</div>
                <?php else: ?>
                <?php foreach ($activeSellOrders as $o): ?>
                <a href="city.php?city=<?= urlencode($o['city']) ?>" class="bct-trade-item" style="text-decoration:none;">
                    <span><strong><?= htmlspecialchars($o['city']) ?></strong></span>
                    <span class="price">¥<?= number_format($o['price'], 2) ?></span>
                    <span class="num"><?= number_format($o['amount']) ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-history"></i> 最新成交</h3></div>
            <div class="bct-trade-list">
                <?php if (empty($recentTrades)): ?>
                <div class="text-center" style="padding:30px;color:var(--bct-text-secondary);">暂无成交记录</div>
                <?php else: ?>
                <?php foreach ($recentTrades as $t):
                    $side = ($t['order_type'] ?? '') === 'buy' ? 'buy' : 'sell';
                    $sideText = $side === 'buy' ? '买' : '卖';
                ?>
                <div class="bct-trade-item">
                    <span><strong><?= htmlspecialchars($t['city']) ?></strong></span>
                    <span class="side <?= $side ?>"><?= $sideText ?></span>
                    <span class="price">¥<?= number_format($t['price'], 2) ?></span>
                    <span class="num"><?= number_format($t['amount']) ?></span>
                    <span class="time"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 当前挂单情况汇总 -->
<div class="card" style="margin-top:24px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;font-size:16px;"><i class="fas fa-layer-group"></i> 当前挂单情况</h3>
        <a href="market.php" class="btn btn-sm btn-default">进入行情中心</a>
    </div>
    <div class="table-responsive">
        <table class="table table-dark bct-order-summary">
            <thead>
                <tr>
                    <th>城市</th>
                    <th class="text-right">出售数量</th>
                    <th class="text-right">求购数量</th>
                    <th class="text-right">最低售价</th>
                    <th class="text-right">最高求购价</th>
                    <th class="text-right">价差</th>
                    <th class="text-center">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orderSummary)): ?>
                <tr><td colspan="7" class="text-center" style="padding:30px;color:var(--bct-text-secondary);">暂无挂单</td></tr>
                <?php else: ?>
                <?php foreach ($orderSummary as $row):
                    $lowestSell = $row['lowest_sell'] ? (float)$row['lowest_sell'] : 0;
                    $highestBuy = $row['highest_buy'] ? (float)$row['highest_buy'] : 0;
                    $spread = ($lowestSell > 0 && $highestBuy > 0) ? $lowestSell - $highestBuy : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['city']) ?></strong></td>
                    <td class="text-right down"><?= $row['sell_amount'] > 0 ? number_format($row['sell_amount']) : '-' ?></td>
                    <td class="text-right up"><?= $row['buy_amount'] > 0 ? number_format($row['buy_amount']) : '-' ?></td>
                    <td class="text-right"><?= $lowestSell > 0 ? '¥'.number_format($lowestSell, 2) : '-' ?></td>
                    <td class="text-right"><?= $highestBuy > 0 ? '¥'.number_format($highestBuy, 2) : '-' ?></td>
                    <td class="text-right <?= $spread > 0 ? 'text-muted' : ($spread < 0 ? 'up' : 'text-muted') ?>">
                        <?= $spread != 0 ? '¥'.number_format(abs($spread), 2) : '-' ?>
                    </td>
                    <td class="text-center">
                        <a href="city.php?city=<?= urlencode($row['city']) ?>" class="btn btn-xs btn-primary">交易</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.bct-ticker-wrap { margin: 0 -15px 24px -15px; }
@media (max-width: 768px) {
    .bct-ticker-wrap { overflow-x: auto; }
    .bct-ticker { animation: none; display: flex; }
}
.bct-order-summary { margin-bottom: 0; }
.bct-order-summary th,
.bct-order-summary td {
    border-color: var(--bct-border) !important;
    color: var(--bct-text);
}
.bct-order-summary thead th {
    background-color: var(--bct-bg-tertiary);
    color: var(--bct-text-secondary);
    font-weight: 500;
    border-bottom: 1px solid var(--bct-border);
}
.bct-order-summary tbody tr:hover { background-color: var(--bct-bg-hover); }
</style>

<?php require_once 'includes/footer.php'; ?>
