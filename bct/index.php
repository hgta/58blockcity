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
} catch (Exception $e) {
    $citiesWithData = $tickerCities = $top5Data = [];
    $marketStats = ['total_volume_24h'=>0,'total_market_cap'=>0,'gainers_count'=>0,'losers_count'=>0,'active_orders'=>0];
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
            <span class="price">¥<?= number_format($city['current_price'], 4) ?></span>
            <span class="change <?= $cls ?>"><?= $icon ?> <?= $sign ?><?= number_format($city['change_pct'], 2) ?>%</span>
        </a>
        <?php endforeach; ?>
        <?php endfor; ?>
    </div>
</div>

<div class="bct-stats-grid">
    <div class="bct-stat-card">
        <div class="label">24h 成交额</div>
        <div class="value">¥<?= number_format($marketStats['total_volume_24h'], 2) ?></div>
    </div>
    <div class="bct-stat-card">
        <div class="label">总市值</div>
        <div class="value">¥<?= number_format($marketStats['total_market_cap'], 2) ?></div>
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

<h3 style="font-size:16px;color:var(--bct-text);margin-bottom:16px;"><i class="fas fa-fire"></i> TOP5 热门城市</h3>
<div class="bct-top5-grid">
    <?php foreach ($top5Data as $city):
        $cls = $city['change_pct'] >= 0 ? 'up' : 'down';
        $sign = $city['change_pct'] >= 0 ? '+' : '';
    ?>
    <a href="city.php?city=<?= urlencode($city['city']) ?>" class="bct-city-card">
        <div class="city-name"><?= htmlspecialchars($city['city']) ?></div>
        <div class="city-price">¥<?= number_format($city['current_price'], 4) ?></div>
        <div class="city-change <?= $cls ?>"><?= $sign ?><?= number_format($city['change_pct'], 2) ?>%</div>
        <div class="city-meta">
            <span>24h 成交 ¥<?= number_format($city['volume_24h'], 0) ?></span>
            <span>市值 ¥<?= number_format($city['market_cap'], 0) ?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-exchange-alt"></i> 交易市场</h3></div>
            <div class="card-body text-center" style="padding:40px;">
                <p style="color:var(--bct-text-secondary);font-size:16px;">查看全部城市币行情与挂单，进入城市详情页交易</p>
                <a href="market.php" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-chart-line"></i> 进入行情中心</a>
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
                    <span class="price">¥<?= number_format($t['price'], 4) ?></span>
                    <span class="num"><?= number_format($t['amount']) ?> BCT</span>
                    <span class="time"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.bct-ticker-wrap { margin: 0 -15px 24px -15px; }
@media (max-width: 768px) {
    .bct-ticker-wrap { overflow-x: auto; }
    .bct-ticker { animation: none; display: flex; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
