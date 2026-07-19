<?php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../config/block_prices.php';

$sort = $_GET['sort'] ?? 'activated';
$allowed = ['activated', 'resident', 'popularity', 'sale', 'purchase', 'my_blocks'];
if (!in_array($sort, $allowed)) $sort = 'activated';

$currentUserId = isLoggedIn() ? $_SESSION['user_id'] : null;

// 「我拥有的」需要登录且是独立数据集
$myMode = ($sort === 'my_blocks');

if ($myMode && $currentUserId) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.pinyin, c.area_code,
               c.activated_blocks, c.resident_count, c.popularity,
               b.block_number, b.zone
        FROM blocks b
        JOIN cities c ON b.city_id = c.id
        WHERE b.owner_id = ? AND b.status = 'sold'
        ORDER BY c.name
    ");
    $stmt->execute([$currentUserId]);
    $allMy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 按城市聚合
    $byCity = [];
    foreach ($allMy as $b) {
        $cid = $b['id'];
        if (!isset($byCity[$cid])) {
            $byCity[$cid] = [
                'id' => $b['id'], 'name' => $b['name'], 'pinyin' => $b['pinyin'],
                'area_code' => $b['area_code'], 'activated_blocks' => $b['activated_blocks'],
                'resident_count' => $b['resident_count'], 'popularity' => $b['popularity'],
                'my_blocks' => 0, 'my_value' => 0,
            ];
        }
        $byCity[$cid]['my_blocks']++;
        $byCity[$cid]['my_value'] += calculateBlockPriceNew((string)$b['zone'], (string)$b['block_number']);
    }
    // 按拥有数降序
    uasort($byCity, function($a, $b) { return $b['my_blocks'] - $a['my_blocks']; });
    $rows = array_slice(array_values($byCity), 0, 200);
} else {
    // 主排序映射
    $sortMap = [
        'activated' => 'claimed_count DESC',
        'resident'  => 'c.resident_count DESC',
        'popularity'=> 'c.popularity DESC',
        'sale'      => 'sale_count DESC',
        'purchase'  => 'purchase_count DESC',
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['activated'];

    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.pinyin, c.area_code,
               c.activated_blocks, c.resident_count, c.popularity, c.rank,
               (SELECT COUNT(*) FROM blocks WHERE city_id = c.id AND status = 'sold') as claimed_count,
               (SELECT COUNT(*) FROM block_listings WHERE city_id = c.id AND status = 'listed') as sale_count,
               (SELECT COUNT(*) FROM purchase_requests WHERE city_id = c.id AND status = 'active') as purchase_count
        FROM cities c
        ORDER BY $orderBy
        LIMIT 200
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$total = count($rows);

// 排序标签定义
$tabs = [
    'activated' => ['label' => '开启区块', 'icon' => 'fa-cubes'],
    'resident'  => ['label' => '人口',     'icon' => 'fa-users'],
    'popularity'=> ['label' => '人气值',   'icon' => 'fa-fire'],
    'sale'      => ['label' => '售卖',     'icon' => 'fa-tag'],
    'purchase'  => ['label' => '求购',     'icon' => 'fa-search-dollar'],
];
if ($currentUserId) {
    $tabs['my_blocks'] = ['label' => '我拥有的', 'icon' => 'fa-star'];
}
?>
<?php require_once 'includes/header.php'; ?>

<style>
.rank-wrap { max-width:1000px; margin:0 auto; padding:20px 15px; }
.rank-header { margin-bottom:20px; }
.rank-title { font-size:24px; font-weight:800; color:#1a1a2e; margin-bottom:4px; }
.rank-title i { color:#ff6b00; }
.rank-sub { font-size:13px; color:#999; }

/* 标签栏 */
.rank-tabs { display:flex; flex-wrap:wrap; gap:6px; margin:16px 0; }
.rank-tab { padding:8px 16px; border-radius:20px; font-size:13px; font-weight:600; text-decoration:none; color:#666; background:#f5f5f5; transition:.2s; white-space:nowrap; }
.rank-tab:hover { background:#fff0e6; color:#ff6b00; }
.rank-tab.active { background:linear-gradient(135deg,#ff6b00,#ff9500); color:#fff; box-shadow:0 3px 10px rgba(255,107,0,.25); }

/* 表格 */
.rank-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
.rank-table th, .rank-table td { padding:12px 14px; text-align:center; font-size:14px; border-bottom:1px solid #f2f2f2; }
.rank-table th { background:#fafbfc; font-size:12px; color:#999; font-weight:600; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
.rank-table th.sort-active { color:#ff6b00; background:#fff8f5; }
.rank-table td { color:#555; }
.rank-table tr:hover td { background:#fafbfc; }

/* 排名数字 */
.rank-num { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:8px; font-size:13px; font-weight:700; color:#999; background:#f5f5f5; }
.rank-num.r1, .rank-num.r2, .rank-num.r3 { color:#fff; width:30px; height:30px; border-radius:10px; font-size:14px; }
.rank-num.r1 { background:linear-gradient(135deg,#f59e0b,#fbbf24); }
.rank-num.r2 { background:linear-gradient(135deg,#94a3b8,#b0bec5); }
.rank-num.r3 { background:linear-gradient(135deg,#d97706,#b45309); }

/* 城市名 */
.rank-city { text-align:left; font-weight:600; }
.rank-city a { color:#1a1a2e; text-decoration:none; display:flex; align-items:center; gap:8px; }
.rank-city a:hover { color:#ff6b00; }
.rank-city-avatar { width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,#1a1a2e,#0f3460); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; }
.rank-city-info { line-height:1.3; }
.rank-city-name { font-size:15px; }
.rank-city-code { font-size:11px; color:#aaa; font-weight:400; }

/* 数据列 */
.rank-val { font-weight:600; color:#333; }
.rank-val.highlight { color:#ff6b00; font-size:15px; }

/* 我的模式 */
.my-stat { font-size:22px; font-weight:800; color:#ff6b00; }
.my-stat small { font-size:13px; font-weight:400; color:#999; }

/* 空态 */
.empty-state { text-align:center; padding:60px 20px; color:#aaa; }
.empty-state i { font-size:40px; display:block; margin-bottom:12px; }

@media(max-width:768px) {
    .rank-table th:nth-child(6),
    .rank-table td:nth-child(6),
    .rank-table th:nth-child(7),
    .rank-table td:nth-child(7) { display:none; }
    .rank-table td, .rank-table th { padding:10px 8px; font-size:13px; }
}
</style>

<div class="rank-wrap">
    <div class="rank-header">
        <h1 class="rank-title"><i class="fas fa-trophy"></i> 城市排行</h1>
        <div class="rank-sub">TOP 200 城市 · 共 <?= number_format($total) ?> 个</div>
    </div>

    <!-- 排序标签 -->
    <div class="rank-tabs">
        <?php foreach ($tabs as $k => $t): ?>
            <a href="?sort=<?= $k ?>" class="rank-tab <?= $sort===$k?'active':'' ?>">
                <?php if ($k !== 'my_blocks'): ?>
                    <i class="fas <?= $t['icon'] ?>"></i> <?= $t['label'] ?>
                <?php else: ?>
                    <i class="fas <?= $t['icon'] ?>"></i> <?= $t['label'] ?>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty-state">
            <i class="fas fa-city"></i>
            <p><?= $myMode ? '你还没有认领任何区块' : '暂无城市数据' ?></p>
        </div>
    <?php elseif ($myMode): ?>
        <!-- ====== 我拥有的城市排行 ====== -->
        <table class="rank-table">
            <thead>
                <tr>
                    <th style="width:60px;">排名</th>
                    <th style="text-align:left;">城市</th>
                    <th class="sort-active">我的区块</th>
                    <th>总价值</th>
                    <th>开启区块</th>
                    <th>人口</th>
                    <th>人气</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $c): $rk = $i + 1; ?>
                <tr>
                    <td><span class="rank-num <?= $rk<=3?'r'.$rk:'' ?>"><?= $rk ?></span></td>
                    <td class="rank-city">
                        <a href="city.php?name=<?= urlencode($c['pinyin']) ?>">
                            <span class="rank-city-avatar"><?= mb_substr($c['name'], 0, 2) ?></span>
                            <span class="rank-city-info">
                                <span class="rank-city-name"><?= htmlspecialchars($c['name']) ?></span>
                                <?php if ($c['area_code']): ?>
                                    <span class="rank-city-code"><?= htmlspecialchars($c['area_code']) ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </td>
                    <td><span class="my-stat"><?= number_format($c['my_blocks']) ?> <small>个</small></span></td>
                    <td><span class="rank-val">¥<?= number_format($c['my_value'] ?? 0) ?></span></td>
                    <td><span class="rank-val"><?= number_format($c['activated_blocks']) ?></span></td>
                    <td><span class="rank-val"><?= number_format($c['resident_count']) ?></span></td>
                    <td><span class="rank-val"><?= number_format($c['popularity']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <!-- ====== 主排行表 ====== -->
        <table class="rank-table">
            <thead>
                <tr>
                    <th style="width:56px;">排名</th>
                    <th style="text-align:left;">城市</th>
                    <th class="<?= $sort==='activated'?'sort-active':'' ?>">开启区块</th>
                    <th class="<?= $sort==='resident'?'sort-active':'' ?>">人口</th>
                    <th class="<?= $sort==='popularity'?'sort-active':'' ?>">人气值</th>
                    <th class="<?= $sort==='sale'?'sort-active':'' ?>">售卖</th>
                    <th class="<?= $sort==='purchase'?'sort-active':'' ?>">求购</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $c): $rk = $i + 1; ?>
                <tr>
                    <td><span class="rank-num <?= $rk<=3?'r'.$rk:'' ?>"><?= $rk ?></span></td>
                    <td class="rank-city">
                        <a href="city.php?name=<?= urlencode($c['pinyin']) ?>">
                            <span class="rank-city-avatar"><?= mb_substr($c['name'], 0, 2) ?></span>
                            <span class="rank-city-info">
                                <span class="rank-city-name"><?= htmlspecialchars($c['name']) ?></span>
                                <?php if ($c['area_code']): ?>
                                    <span class="rank-city-code"><?= htmlspecialchars($c['area_code']) ?></span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </td>
                    <td><span class="rank-val <?= $sort==='activated'?'highlight':'' ?>"><?= number_format($c['claimed_count']) ?></span></td>
                    <td><span class="rank-val <?= $sort==='resident'?'highlight':'' ?>"><?= number_format($c['resident_count']) ?></span></td>
                    <td><span class="rank-val <?= $sort==='popularity'?'highlight':'' ?>"><?= number_format($c['popularity']) ?></span></td>
                    <td><span class="rank-val <?= $sort==='sale'?'highlight':'' ?>"><?= number_format($c['sale_count']) ?></span></td>
                    <td><span class="rank-val <?= $sort==='purchase'?'highlight':'' ?>"><?= number_format($c['purchase_count']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
