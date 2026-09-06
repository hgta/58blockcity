<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/CityBCT.php';

$cityBCT = new CityBCT($pdo);

// 获取城市拼音映射
$cityPinyin = [];
try {
    $stmt = $pdo->query("SELECT name, pinyin FROM cities WHERE status = 'active'");
    while ($row = $stmt->fetch()) {
        $cityPinyin[$row['name']] = $row['pinyin'];
    }
} catch (Exception $e) {
    $cityPinyin = [];
}

if (isset($_SESSION['message'])) { echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['message']).'</div>'; unset($_SESSION['message']); }
if (isset($_SESSION['error'])) { echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['error']).'</div>'; unset($_SESSION['error']); }

$top5Cities = ['北京','上海','广州','深圳','杭州'];

// 分页、排序、搜索
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$sort = $_GET['sort'] ?? 'market_cap';
$order = $_GET['order'] ?? 'desc';
$search = trim($_GET['search'] ?? '');

$allowedSort = ['market_cap','current_price','change_pct','volume_24h','city'];
if (!in_array($sort, $allowedSort)) $sort = 'market_cap';
$order = strtolower($order) === 'asc' ? 'asc' : 'desc';

try {
    $allCities = $cityBCT->getAllCitiesBCT();
    $changes = $cityBCT->get24hChanges();

    $cities = [];
    foreach ($allCities as $city) {
        $city['change_pct'] = $changes[$city['city']] ?? 0;
        $city['volume_24h'] = $cityBCT->getCity24hVolume($city['city']);
        $city['market_cap'] = $city['circulating_supply'] * $city['current_price'];
        $city['pinyin'] = $cityPinyin[$city['city']] ?? '';
        $cities[] = $city;
    }

    // 搜索过滤
    if ($search !== '') {
        $cities = array_filter($cities, function($c) use ($search) {
            $pinyin = $c['pinyin'] ?? '';
            return stripos($c['city'], $search) !== false || stripos($pinyin, $search) !== false;
        });
    }

    // 排序
    usort($cities, function($a, $b) use ($sort, $order) {
        $av = $a[$sort] ?? 0;
        $bv = $b[$sort] ?? 0;
        if (is_string($av)) { $cmp = strcmp($av, $bv); }
        else { $cmp = $av <=> $bv; }
        return $order === 'asc' ? $cmp : -$cmp;
    });

    // TOP5 数据
    $top5Data = [];
    foreach ($top5Cities as $name) {
        foreach ($cities as $city) { if ($city['city']===$name) { $top5Data[]=$city; break; } }
    }

    // 分页
    $total = count($cities);
    $totalPages = max(1, ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $pagedCities = array_slice($cities, $offset, $perPage);

} catch (Exception $e) {
    $cities = $pagedCities = $top5Data = [];
    $total = 0; $totalPages = 1; $page = 1;
    error_log("BCT market error: " . $e->getMessage());
}

function sortUrl($field, $currentSort, $currentOrder, $search) {
    $newOrder = ($currentSort === $field && $currentOrder === 'desc') ? 'asc' : 'desc';
    $q = ['sort'=>$field, 'order'=>$newOrder];
    if ($search) $q['search'] = $search;
    return '?' . http_build_query($q);
}

function sortIcon($field, $currentSort, $currentOrder) {
    if ($currentSort !== $field) return '⇅';
    return $currentOrder === 'desc' ? '↓' : '↑';
}

require_once 'includes/header.php';
?>

<div class="bct-page-title" style="padding-top:20px;">
    <div>
        <h1><i class="fas fa-chart-line"></i> 城市币行情</h1>
        <div class="subtitle">全部城市 BCT 实时行情 · 市值 · 成交量 · 涨跌幅</div>
    </div>
    <div>
        <a href="trade.php" class="btn btn-primary"><i class="fas fa-plus"></i> 发布交易</a>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="card-body">
        <form method="get" class="form-inline" style="display:flex;gap:10px;flex-wrap:wrap;">
            <div class="form-group" style="flex:1;min-width:200px;">
                <input type="text" name="search" class="form-control bct-market-search" style="width:100%;" placeholder="搜索城市名或拼音..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 搜索</button>
            <?php if ($search): ?>
            <a href="market.php" class="btn btn-default">重置</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($top5Data) && $page === 1 && !$search): ?>
<div class="bct-top5-grid" style="margin-bottom:24px;">
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
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3 style="margin:0;font-size:16px;"><i class="fas fa-list-ol"></i> 全部城市币</h3></div>
    <div class="table-responsive">
        <table class="table bct-market-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>城市</th>
                    <th><a href="<?= sortUrl('current_price', $sort, $order, $search) ?>">价格 <span class="sort-icon"><?= sortIcon('current_price', $sort, $order) ?></span></a></th>
                    <th><a href="<?= sortUrl('change_pct', $sort, $order, $search) ?>">24h 涨跌 <span class="sort-icon"><?= sortIcon('change_pct', $sort, $order) ?></span></a></th>
                    <th><a href="<?= sortUrl('volume_24h', $sort, $order, $search) ?>">24h 成交量 <span class="sort-icon"><?= sortIcon('volume_24h', $sort, $order) ?></span></a></th>
                    <th><a href="<?= sortUrl('market_cap', $sort, $order, $search) ?>">流通市值 <span class="sort-icon"><?= sortIcon('market_cap', $sort, $order) ?></span></a></th>
                    <th>流通量 / 总量</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pagedCities)): ?>
                <tr><td colspan="8" class="text-center" style="padding:40px;color:var(--bct-text-secondary);">暂无城市数据</td></tr>
                <?php else: ?>
                <?php foreach ($pagedCities as $idx => $city):
                    $rank = $offset + $idx + 1;
                    $isTop5 = in_array($city['city'], $top5Cities);
                    $cls = $city['change_pct'] >= 0 ? 'up' : 'down';
                    $sign = $city['change_pct'] >= 0 ? '+' : '';
                ?>
                <tr class="<?= $isTop5 ? 'top5' : '' ?>">
                    <td><span class="rank"><?= $rank ?></span></td>
                    <td><strong><?= htmlspecialchars($city['city']) ?></strong></td>
                    <td class="price">¥<?= number_format($city['current_price'], 4) ?></td>
                    <td class="change <?= $cls ?>"><?= $sign ?><?= number_format($city['change_pct'], 2) ?>%</td>
                    <td class="volume">¥<?= number_format($city['volume_24h'], 2) ?></td>
                    <td class="market-cap">¥<?= number_format($city['market_cap'], 2) ?></td>
                    <td><?= number_format($city['circulating_supply']) ?> / <?= number_format($city['total_supply']) ?></td>
                    <td>
                        <a href="city.php?city=<?= urlencode($city['city']) ?>" class="btn btn-sm btn-primary">交易</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="padding:16px;display:flex;justify-content:center;">
        <ul class="pagination">
            <?php if ($page > 1): ?>
            <li><a href="?<?= http_build_query(['page'=>$page-1,'sort'=>$sort,'order'=>$order]+($search?['search'=>$search]:[])) ?>">上一页</a></li>
            <?php else: ?><li class="disabled"><span>上一页</span></li><?php endif; ?>

            <?php for ($i=1;$i<=$totalPages;$i++):
                if ($i==1 || $i==$totalPages || abs($i-$page)<=2):
                    $active = $i==$page ? 'class="active"' : '';
                    $q = ['page'=>$i,'sort'=>$sort,'order'=>$order]+($search?['search'=>$search]:[]);
            ?>
            <li <?= $active ?>><a href="?<?= http_build_query($q) ?>"><?= $i ?></a></li>
            <?php elseif (abs($i-$page)==3): ?><li class="disabled"><span>...</span></li><?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <li><a href="?<?= http_build_query(['page'=>$page+1,'sort'=>$sort,'order'=>$order]+($search?['search'=>$search]:[])) ?>">下一页</a></li>
            <?php else: ?><li class="disabled"><span>下一页</span></li><?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
