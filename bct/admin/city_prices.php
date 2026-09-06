<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/CityBCT.php';

$cityBCT = new CityBCT($pdo);

// 处理价格更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $city = trim($_POST['city'] ?? '');
        $currentPrice = (float)($_POST['current_price'] ?? 0);
        $basePrice = (float)($_POST['base_price'] ?? 0);

        if (empty($city) || $currentPrice <= 0 || $basePrice <= 0) {
            throw new Exception("参数不完整或价格无效");
        }

        $cityBCT->updatePrice($city, $currentPrice);
        $cityBCT->updateBasePrice($city, $basePrice);

        $_SESSION['message'] = "【{$city}】人气值单价已更新为 ¥" . number_format($currentPrice, 2);
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: city_prices.php");
    exit();
}

// 获取全部城市数据（含 cities.popularity 作为流通量）
$allCities = $cityBCT->getAllCitiesBCT();
$changes = $cityBCT->get24hChanges();

foreach ($allCities as &$city) {
    $city['change_pct'] = $changes[$city['city']] ?? 0;
    $city['market_cap'] = $city['circulating_supply'] * $city['current_price'];
}
unset($city);

// 按市值降序
usort($allCities, fn($a, $b) => $b['market_cap'] <=> $a['market_cap']);

$admin_site_config = ['site' => 'bct', 'page_title' => '城市人气值单价管理'];
require_once '../../shared/admin/admin-header.php';
?>

<?php if (!empty($_SESSION['message'])): ?>
<div class="admin-card" style="border-left:4px solid #22c55e; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#86efac;"><?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
</div>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
<div class="admin-card" style="border-left:4px solid #ef4444; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#fca5a5;"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-tags"></i> 城市人气值单价管理</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <div style="overflow-x:auto;">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th style="min-width:80px;">排名</th>
                        <th style="min-width:100px;">城市</th>
                        <th style="min-width:120px;">当前单价</th>
                        <th style="min-width:120px;">基础单价</th>
                        <th style="min-width:120px;">24h 涨跌</th>
                        <th style="min-width:120px;">人气流通量</th>
                        <th style="min-width:140px;">流通市值</th>
                        <th style="min-width:150px;">更新时间</th>
                        <th style="min-width:120px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allCities as $idx => $city): ?>
                    <tr>
                        <form method="POST">
                            <input type="hidden" name="city" value="<?= htmlspecialchars($city['city']) ?>">
                            <td><?= $idx + 1 ?></td>
                            <td><strong><?= htmlspecialchars($city['city']) ?></strong></td>
                            <td>
                                <input type="number" name="current_price" step="0.01" min="0.01" required
                                       value="<?= number_format($city['current_price'], 2) ?>"
                                       style="width:110px;padding:6px 8px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                            </td>
                            <td>
                                <input type="number" name="base_price" step="0.01" min="0.01" required
                                       value="<?= number_format($city['base_price'], 2) ?>"
                                       style="width:110px;padding:6px 8px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                            </td>
                            <td style="color:<?= $city['change_pct'] >= 0 ? '#4ade80' : '#f87171' ?>;">
                                <?= $city['change_pct'] >= 0 ? '▲' : '▼' ?> <?= number_format(abs($city['change_pct']), 2) ?>%
                            </td>
                            <td><?= number_format($city['circulating_supply']) ?></td>
                            <td>¥<?= number_format($city['market_cap'], 2) ?></td>
                            <td><?= $city['last_updated'] ?></td>
                            <td>
                                <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">
                                    <i class="fas fa-save"></i> 保存
                                </button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($allCities)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:24px;color:#64748b;">暂无城市数据</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="admin-card" style="margin-top:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-info-circle"></i> 说明</span>
    </div>
    <div class="admin-card-body" style="color:#94a3b8;font-size:13px;line-height:1.8;">
        <p>1. <strong>当前单价</strong>：城市人气值在交易市场的实时单价，首页/行情页/城市详情页均显示此价格。</p>
        <p>2. <strong>基础单价</strong>：自动调价算法的底价，autoAdjustPrice 不会把价格压到基础单价以下。</p>
        <p>3. <strong>人气流通量</strong>：取自 <code>cities.popularity</code>（已产生人气值），用于计算流通市值。</p>
        <p>4. 修改后点击「保存」即可生效，无需重启服务。</p>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
