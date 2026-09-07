<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/CityBCT.php';

$cityBCT = new CityBCT($pdo);

// 处理价格更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isBatch = ($action === 'batch');

    if ($isBatch) {
        // ---- 批量设置：每行「城市 数量 价格」，空格/Tab 分隔 ----
        // 规则：数量仅校验不写库；价格仅更新 current_price；
        //      若该城市现有单价 < 录入价，则提示并跳过（不更新）。
        $result = ['updated' => [], 'skipped' => [], 'missing' => [], 'invalid' => []];
        try {
            $batchText = trim((string)($_POST['batch_text'] ?? ''));
            if ($batchText === '') {
                throw new Exception('请输入批量设置内容');
            }
            $batchText = str_replace('　', ' ', $batchText); // 兼容全角空格

            $lines = preg_split('/\r\n|\r|\n/', $batchText);
            $stmtCity = $pdo->prepare("SELECT bct_current_price AS current_price FROM cities WHERE name = ?");
            $stmtPy   = $pdo->prepare("SELECT name FROM cities WHERE pinyin = ? LIMIT 1");

            foreach ($lines as $lineNo => $rawLine) {
                $line = trim($rawLine);
                if ($line === '' || $line[0] === '#' || strpos($line, '//') === 0) continue;

                $parts = preg_split('/\s+/', $line);
                if (count($parts) !== 3) {
                    $result['invalid'][] = ['line' => $lineNo + 1, 'text' => mb_substr(trim($rawLine), 0, 60), 'reason' => '应为「城市 数量 价格」三列'];
                    continue;
                }
                $name  = trim($parts[0]);
                $qty   = $parts[1];
                $price = $parts[2];

                if (!ctype_digit($qty) || (int)$qty <= 0) {
                    $result['invalid'][] = ['line' => $lineNo + 1, 'text' => $name, 'reason' => '数量必须是正整数'];
                    continue;
                }
                if (!is_numeric($price) || (float)$price <= 0) {
                    $result['invalid'][] = ['line' => $lineNo + 1, 'text' => $name, 'reason' => '价格必须是正数'];
                    continue;
                }

                // 定位城市（支持直接城市名，或 cities.pinyin）
                $cityName = '';
                $row = null;
                $stmtCity->execute([$name]);
                $row = $stmtCity->fetch();
                if ($row) {
                    $cityName = $name;
                } else {
                    $stmtPy->execute([strtolower($name)]);
                    $pyName = $stmtPy->fetchColumn();
                    if ($pyName) {
                        $stmtCity->execute([$pyName]);
                        $row = $stmtCity->fetch();
                        if ($row) $cityName = $pyName;
                    }
                }
                if ($cityName === '') {
                    $result['missing'][] = ['line' => $lineNo + 1, 'text' => $name];
                    continue;
                }

                $newPrice = (float)$price;
                $curPrice = (float)$row['current_price'];
                if ($curPrice < $newPrice) {
                    $result['skipped'][] = ['line' => $lineNo + 1, 'city' => $cityName, 'current' => $curPrice, 'new' => $newPrice];
                    continue;
                }

                try {
                    $cityBCT->updatePrice($cityName, $newPrice);
                    $result['updated'][] = ['city' => $cityName, 'price' => $newPrice];
                } catch (Exception $e) {
                    $result['invalid'][] = ['line' => $lineNo + 1, 'text' => $cityName, 'reason' => '更新失败：' . $e->getMessage()];
                }
            }

            if (empty($result['updated']) && empty($result['skipped']) && empty($result['missing']) && empty($result['invalid'])) {
                throw new Exception('没有解析到有效记录（内容为空或全部为注释行）');
            }
            $_SESSION['batch_result'] = $result;
        } catch (Exception $e) {
            $_SESSION['batch_error'] = $e->getMessage();
        }
    } else {
        // ---- 单行更新 ----
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
    }
    $backSearch = trim($_GET['search'] ?? '');
    $backPage = max(1, (int)($_GET['page'] ?? 1));
    header("Location: city_prices.php?sort=" . urlencode($_GET['sort'] ?? 'cap') . "&order=" . urlencode($_GET['order'] ?? 'desc')
        . "&page=" . $backPage
        . ($backSearch !== '' ? "&search=" . urlencode($backSearch) : ''));
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

// 读取城市榜真实排名 cities.rank 与拼音 cities.pinyin（列不存在时容错为空）
$rankMap = [];
$pinyinMap = [];
try {
    $stmt = $pdo->query("SELECT name, `rank`, pinyin FROM cities");
    foreach ($stmt->fetchAll() as $r) {
        $rankMap[$r['name']] = (int)$r['rank'];
        $pinyinMap[$r['name']] = strtolower((string)$r['pinyin']);
    }
} catch (Exception $e) {
    // 兼容没有 pinyin 列的环境
    try {
        $stmt = $pdo->query("SELECT name, `rank` FROM cities");
        foreach ($stmt->fetchAll() as $r) {
            $rankMap[$r['name']] = (int)$r['rank'];
        }
        } catch (Exception $e2) {
            $rankMap = []; // cities 表没有 rank 字段
        }
    }

// 全量词条数（cities），用于标题区口径提示
$cityTotal = 0;
try {
    $cityTotal = (int)$pdo->query('SELECT COUNT(*) FROM cities')->fetchColumn();
} catch (Exception $e) {
    error_log('city_prices stats error: ' . $e->getMessage());
}

// 排序参数（白名单）
$sortOptions = [
    'cap'    => '市值',
    'rank'   => '城市排名',
    'price'  => '当前单价',
    'supply' => '流通量',
];
$sort = $_GET['sort'] ?? 'cap';
if (!isset($sortOptions[$sort])) $sort = 'cap';
$order = $_GET['order'] ?? 'desc';
if (!in_array($order, ['asc', 'desc'], true)) $order = 'desc';

// 搜索关键词：匹配城市名 或 拼音（含拼音前缀/包含）
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $kwName = mb_strtolower($search);
    $kwPy = strtolower($search);
    $allCities = array_values(array_filter($allCities, function ($city) use ($kwName, $kwPy, $pinyinMap) {
        $name = (string)($city['city'] ?? '');
        if (mb_stripos($name, $kwName) !== false) return true;
        $pinyin = $pinyinMap[$name] ?? '';
        if ($pinyin !== '' && strpos($pinyin, $kwPy) !== false) return true;
        return false;
    }));
}

// 各排序维度取值函数
$sortKey = [
    'cap'    => fn($c) => (float)$c['market_cap'],
    'price'  => fn($c) => (float)$c['current_price'],
    'supply' => fn($c) => (float)$c['circulating_supply'],
    'rank'   => fn($c) => $rankMap[$c['city']] ?? PHP_INT_MAX,
];

usort($allCities, function ($a, $b) use ($sortKey, $sort, $order) {
    $va = $sortKey[$sort]($a);
    $vb = $sortKey[$sort]($b);
    if ($va == $vb) return 0;
    if ($order === 'asc') return $va < $vb ? -1 : 1;
    return $va > $vb ? -1 : 1;
});

// ---- 分页：排序/搜索已在全量上完成，这里仅对结果集切片 ----
$perPage = 100;
$total = count($allCities);
$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
$page = max(1, (int)($_GET['page'] ?? 1));
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages; // 越界收敛到末页
}
$offset = ($page - 1) * $perPage;
$pageCities = $total > 0 ? array_slice($allCities, $offset, $perPage) : [];

// 统一构造带查询状态（sort/order/search/page）的页面 URL
$buildUrl = function ($sortKey, $orderKey, $pageNo, $searchKw) {
    $url = 'city_prices.php?sort=' . urlencode($sortKey) . '&order=' . urlencode($orderKey) . '&page=' . (int)$pageNo;
    if ($searchKw !== '') {
        $url .= '&search=' . urlencode($searchKw);
    }
    return $url;
};

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

<?php if (!empty($_SESSION['batch_error'])): ?>
<div class="admin-card" style="border-left:4px solid #ef4444; margin-bottom:16px;">
    <div class="admin-card-body" style="color:#fca5a5;">批量设置失败：<?= htmlspecialchars($_SESSION['batch_error']); unset($_SESSION['batch_error']); ?></div>
</div>
<?php endif; ?>

<?php if (!empty($_SESSION['batch_result'])):
    $br = $_SESSION['batch_result'];
    unset($_SESSION['batch_result']);
    $uCount = count($br['updated']); $sCount = count($br['skipped']);
    $mCount = count($br['missing']); $iCount = count($br['invalid']);
?>
<div class="admin-card" style="border-left:4px solid <?= $uCount > 0 ? '#22c55e' : '#f59e0b' ?>; margin-bottom:16px;">
    <div class="admin-card-header">
        <span class="admin-card-title" style="font-size:15px;">批量设置结果</span>
        <span style="margin-left:auto;font-size:13px;color:#94a3b8;">
            成功 <?= $uCount ?> · 跳过 <?= $sCount ?> · 未匹配 <?= $mCount ?> · 无效 <?= $iCount ?>
        </span>
    </div>
    <div class="admin-card-body" style="font-size:13px;line-height:1.9;">
        <?php if ($uCount): ?>
        <div style="color:#86efac;margin-bottom:6px;">
            <?php foreach ($br['updated'] as $it): ?>✓ <?= htmlspecialchars($it['city']) ?> → ¥<?= $it['price'] ?><br><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($sCount): ?>
        <div style="color:#fbbf24;margin-bottom:6px;">
            <?php foreach ($br['skipped'] as $it): ?>⚠ 第 <?= $it['line'] ?> 行 <?= htmlspecialchars($it['city']) ?>：现价 ¥<?= $it['current'] ?> 低于录入价 ¥<?= $it['new'] ?>，暂不更新<br><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($mCount): ?>
        <div style="color:#f87171;margin-bottom:6px;">
            <?php foreach ($br['missing'] as $it): ?>✗ 第 <?= $it['line'] ?> 行「<?= htmlspecialchars($it['text']) ?>」未匹配到城市<br><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($iCount): ?>
        <div style="color:#f87171;">
            <?php foreach ($br['invalid'] as $it): ?>✗ 第 <?= $it['line'] ?> 行「<?= htmlspecialchars($it['text']) ?>」：<?= htmlspecialchars($it['reason']) ?><br><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-tags"></i> 城市人气值单价管理</span>
        <span style="margin-left:auto;color:#4ade80;font-size:13px;"><i class="fas fa-check-circle"></i> BCT 单价存于 cities，全量 <?= (int)$cityTotal ?> 个词条均含行情</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid #1e293b;">
            <span style="color:#94a3b8;font-size:13px;">排序方式：</span>
            <?php foreach ($sortOptions as $key => $label):
                $active = ($sort === $key);
                // 激活态点击切换方向；未激活首次点击按维度给合理默认（排名升序、其余降序）
                if ($active) {
                    $nextOrder = ($order === 'asc') ? 'desc' : 'asc';
                } else {
                    $nextOrder = ($key === 'rank') ? 'asc' : 'desc';
                }
                $arrow = $active ? ($order === 'asc' ? ' ↑' : ' ↓') : '';
                // 切换排序回到第 1 页
                $sortHref = $buildUrl($key, $nextOrder, 1, $search);
            ?>
            <a href="<?= $sortHref ?>"
               class="admin-btn <?= $active ? 'admin-btn-primary' : '' ?> admin-btn-sm">
                <?= $label ?><?= $arrow ?>
            </a>
            <?php endforeach; ?>

            <form method="get" action="city_prices.php" style="display:flex;gap:8px;margin-left:auto;">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索城市名 / 拼音"
                       style="width:180px;padding:6px 10px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;">
                <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm"><i class="fas fa-search"></i> 搜索</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= $buildUrl($sort, $order, 1, '') ?>"
                       class="admin-btn admin-btn-secondary admin-btn-sm">重置</a>
                <?php endif; ?>
            </form>
            <span style="margin-left:auto;color:#64748b;font-size:13px;">共 <?= $total ?> 个城市</span>
        </div>
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
                    <?php foreach ($pageCities as $idx => $city): ?>
                    <tr>
                        <form method="POST" action="<?= $buildUrl($sort, $order, $page, $search) ?>">
                            <input type="hidden" name="city" value="<?= htmlspecialchars($city['city']) ?>">
                            <td><?= $rankMap[$city['city']] ?? ($offset + $idx + 1) ?></td>
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
                            <td>¥<?= number_format($city['market_cap'] / 10000, 2) ?> 万</td>
                            <td><?= $city['last_updated'] ?></td>
                            <td>
                                <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">
                                    <i class="fas fa-save"></i> 保存
                                </button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pageCities)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:24px;color:#64748b;"><?= $search !== '' ? '未找到匹配「' . htmlspecialchars($search) . '」的城市' : '暂无城市数据' ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="admin-pagination" style="justify-content:space-between;padding:12px 16px;border-top:1px solid #1e293b;">
            <div class="admin-page-info">共 <?= $total ?> 个城市 · 第 <?= $page ?> / <?= $totalPages > 0 ? $totalPages : 1 ?> 页</div>
            <?php if ($totalPages > 1): ?>
            <div class="admin-page-buttons">
                <?php if ($page > 1): ?>
                    <a href="<?= $buildUrl($sort, $order, 1, $search) ?>">首页</a>
                    <a href="<?= $buildUrl($sort, $order, $page - 1, $search) ?>">上一页</a>
                <?php else: ?>
                    <span class="disabled">首页</span>
                    <span class="disabled">上一页</span>
                <?php endif; ?>
                <?php $start = max(1, $page - 2); $end = min($totalPages, $page + 2); ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $buildUrl($sort, $order, $i, $search) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= $buildUrl($sort, $order, $page + 1, $search) ?>">下一页</a>
                    <a href="<?= $buildUrl($sort, $order, $totalPages, $search) ?>">末页</a>
                <?php else: ?>
                    <span class="disabled">下一页</span>
                    <span class="disabled">末页</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="admin-card" style="margin-top:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-list-alt"></i> 批量设置城市人气值单价</span>
    </div>
    <div class="admin-card-body">
        <p style="color:#94a3b8;font-size:13px;line-height:1.9;margin:0 0 12px;">
            每行一条，格式：<code style="color:#e2e8f0;">城市 数量 价格</code>（空格或 Tab 分隔，支持全角空格；<code>#</code> 或 <code>//</code> 开头为注释行）。<br>
            「数量」仅做格式校验，不会写入任何字段；「价格」将写入该城市<strong>当前单价（current_price）</strong>，<strong>基础单价（base_price）保持不变</strong>。<br>
            <span style="color:#e2e8f0;">本批量功能按输入行在<strong>全量城市</strong>中匹配（城市名或拼音），与上方列表当前页码、搜索词无关，不会只作用于当前页。</span><br>
            <span style="color:#fbbf24;">保护规则：若该城市现有单价 < 录入价，将提示并跳过，暂不更新。</span>
        </p>
        <form method="POST" action="<?= $buildUrl($sort, $order, $page, $search) ?>">
            <input type="hidden" name="action" value="batch">
            <textarea id="batch_text" name="batch_text" rows="9" placeholder="例如：<?= htmlspecialchars("成都 10000 0.06\n哈尔滨 5000 0.05\n大理 7000 0.05") ?>"
                      style="width:100%;box-sizing:border-box;padding:10px 12px;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#f1f5f9;font-size:13px;font-family:ui-monospace,Menlo,Consolas,monospace;resize:vertical;"></textarea>
            <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
                <button type="submit" class="admin-btn admin-btn-primary" style="padding:7px 14px;"><i class="fas fa-check-double"></i> 执行批量设置</button>
                <button type="button" class="admin-btn admin-btn-secondary" style="padding:7px 14px;" onclick="document.getElementById('batch_text').value='成都 10000 0.06\n哈尔滨 5000 0.05\n大理 7000 0.05\n西双版纳 64000 0.035\n锡林郭勒 38000 0.035\n鲸探 31000 0.05\n庆阳 21000 0.035';">填入示例</button>
            </div>
        </form>
    </div>
</div>

<div class="admin-card" style="margin-top:20px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-info-circle"></i> 说明</span>
    </div>
    <div class="admin-card-body" style="color:#94a3b8;font-size:13px;line-height:1.8;">
        <p>1. <strong>当前单价</strong>：城市人气值在交易市场的实时单价，首页/行情页/城市详情页均显示此价格。</p>
        <p>2. <strong>基础单价</strong>：自动调价算法的底价，autoAdjustPrice 不会把价格压到基础单价以下。</p>
        <p>3. <strong>人气流通量</strong>：实时按 <code>cities.popularity - cities.popularity_consume</code> 计算，不落库、无双写，用于计算流通市值。</p>
        <p>4. 修改后点击「保存」即可生效，无需重启服务，保存后停留在当前排序。</p>
        <p>5. <strong>排序方式</strong>：顶部「市值 / 城市排名 / 当前单价 / 流通量」按钮可切换排序，再点一次可切换升/降序。</p>
        <p>6. <strong>排名</strong>列：取自 <code>cities.rank</code> 城市榜名次；若该城市不在榜单，则按当前排列序号显示。</p>
        <p>7. <strong>批量设置</strong>：按「城市 数量 价格」每行一条；数量仅校验、价格只写入当前单价；匹配范围为<strong>全量城市</strong>（支持城市名或拼音），与上方列表分页/搜索无关；若某城市现有单价低于录入价则提示并跳过，不会覆盖。</p>
        <p>8. <strong>数据范围</strong>：BCT 单价直接存在 <code>cities</code> 表的 <code>bct_current_price</code> / <code>bct_base_price</code> 字段，列表即 <code>cities</code> 全量 <?= (int)$cityTotal ?> 个词条（含城市与品牌/数字资产），所有词条天然拥有行情、默认单价 ¥0.10，无需再单独「开通」；在 <code>cities</code> 新增词条即自动按默认价进入行情市场，逐页即可修改其单价。</p>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
