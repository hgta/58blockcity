<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Block.php';
require_once '../../config/block_prices.php';
checkLogin();

$block = new Block($pdo);
$userId = $_SESSION['user_id'];
$userBlocks = $block->getUserBlocks($userId);
foreach ($userBlocks as &$b) {
    $b['calc_price'] = calculateBlockPriceNew((string)($b['zone'] ?? 'A'), (string)($b['block_number'] ?? '0101'));
}
unset($b);

// 拉取用户参与的合并块（owner_id = 当前用户），分组展示，避免把合并块拆散成子块
$mergedStmt = $pdo->prepare("SELECT * FROM merged_blocks WHERE owner_id = ?");
$mergedStmt->execute([$userId]);
$userMerged = $mergedStmt->fetchAll(PDO::FETCH_ASSOC);

$mergedNumSet = [];   // key: city_id|zone|block_number（实际区块数口径：合并子块跳过）
$mergedByCity = [];  // city_id => [ merged group ... ]
foreach ($userMerged as $mg) {
    $nums = array_map('trim', explode(',', $mg['merged_blocks']));
    $mergedByCity[$mg['city_id']][] = $mg;
    foreach ($nums as $n) {
        $mergedNumSet[$block->normalizeBlockKey($mg['city_id'], $mg['zone'], $n)] = true;
    }
}

// 拥有区块数：实际区块数口径（多块合并的按 1 块计）
$actualStats = $block->getUserActualBlockStats($userId);
$actualBlockCount = $actualStats['block_count'];
$voteCount = count($userBlocks); // 投票数口径（合并组拆开后的单块数）

// 总价值：普通区块 + 合并组（合并组的子块不再单独计入，避免重复）
$totalValue = 0;
foreach ($userBlocks as $b) {
    if (isset($mergedNumSet[$block->normalizeBlockKey($b['city_id'], $b['zone'], $b['block_number'])])) continue;
    $totalValue += $b['calc_price'] ?? 0;
}
foreach ($userMerged as $mg) {
    foreach (explode(',', $mg['merged_blocks']) as $mn) {
        $totalValue += calculateBlockPriceNew((string)$mg['zone'], (string)trim($mn));
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<style>
.ub-wrap { max-width:1080px; margin:0 auto; }

/* 概览统计条 */
.ub-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
.ub-stat { background:#fff; border-radius:12px; padding:16px 18px; box-shadow:0 2px 10px rgba(0,0,0,.05); border:1px solid #f0f0f0; }
.ub-stat .num { font-size:24px; font-weight:800; color:#ff6b00; line-height:1.2; }
.ub-stat .num small { font-size:13px; font-weight:600; color:#9aa0a6; margin-left:2px; }
.ub-stat .lbl { font-size:12px; color:#8a9099; margin-top:4px; }
.ub-stat.accent .num { color:#1a1a2e; }

/* 工具条 */
.ub-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px; }
.ub-toolbar .tb-search { position:relative; flex:1 1 200px; min-width:180px; }
.ub-toolbar .tb-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#b4b9c0; font-size:13px; }
.ub-toolbar .tb-search input { width:100%; height:40px; padding:0 12px 0 34px; border:1px solid #e3e6ea; border-radius:9px; font-size:14px; background:#fff; outline:none; transition:.2s; }
.ub-toolbar .tb-search input:focus { border-color:#ff6b00; box-shadow:0 0 0 3px rgba(255,107,0,.1); }
.ub-toolbar select { height:40px; padding:0 30px 0 12px; border:1px solid #e3e6ea; border-radius:9px; font-size:14px; background:#fff; color:#333; outline:none; appearance:none;
    background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6'><path d='M0 0l5 6 5-6z' fill='%239aa0a6'/></svg>");
    background-repeat:no-repeat; background-position:right 12px center; cursor:pointer; }
.ub-toolbar select:focus { border-color:#ff6b00; }
.ub-toolbar .tb-actions { display:flex; gap:8px; margin-left:auto; }
.ub-btn { display:inline-flex; align-items:center; gap:6px; height:40px; padding:0 14px; border-radius:9px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid #e3e6ea; background:#fff; color:#555; cursor:pointer; transition:.2s; white-space:nowrap; }
.ub-btn:hover { border-color:#ff6b00; color:#ff6b00; text-decoration:none; }
.ub-btn.primary { background:#ff6b00; border-color:#ff6b00; color:#fff; }
.ub-btn.primary:hover { background:#e55f00; color:#fff; }

/* 展开/收起全部 */
.ub-toggle-all { display:flex; gap:8px; margin-bottom:14px; }
.ub-toggle-all button { border:none; background:none; color:#ff6b00; font-size:13px; cursor:pointer; padding:0; }
.ub-toggle-all button:hover { text-decoration:underline; }
.ub-toggle-all span { color:#d5d8dd; }

/* 城市分组 */
.ub-city { margin-bottom:16px; background:#fff; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,.05); border:1px solid #f0f0f0; overflow:hidden; }
.ub-city-head { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:14px 18px; cursor:pointer; user-select:none; transition:.15s; }
.ub-city-head:hover { background:#fffaf5; }
.ub-city-title { display:flex; align-items:center; gap:10px; min-width:0; }
.ub-city-title .name { font-size:16px; font-weight:700; color:#1a1a2e; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ub-city-title .name a { color:#1a1a2e; text-decoration:none; }
.ub-city-title .name a:hover { color:#ff6b00; }
.ub-city-title .cnt { font-size:12px; color:#9aa0a6; background:#f5f6f8; border-radius:20px; padding:2px 10px; white-space:nowrap; }
.ub-city-right { display:flex; align-items:center; gap:14px; flex-shrink:0; }
.ub-city-right .val { font-size:14px; font-weight:700; color:#ff6b00; }
.ub-city-head .caret { color:#c2c7ce; font-size:13px; transition:transform .2s; }
.ub-city.collapsed .caret { transform:rotate(-90deg); }
.ub-city.collapsed .ub-grid { display:none; }

.ub-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:10px; padding:0 18px 18px; }
.ub-card { display:block; padding:12px 14px; border-radius:9px; background:#fafbfc; border:1px solid #eef0f3; text-decoration:none; color:inherit; transition:.18s; }
.ub-card:hover { background:#fff; border-color:#ff6b00; box-shadow:0 4px 14px rgba(255,107,0,.12); transform:translateY(-1px); }
.ub-card .c-top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.ub-card .c-zone { font-size:11px; font-weight:700; color:#fff; background:#ff6b00; border-radius:6px; padding:2px 8px; }
.ub-card .c-num { font-size:13px; color:#8a9099; font-variant-numeric:tabular-nums; }
.ub-card .c-price { margin-top:8px; font-size:15px; font-weight:700; color:#e74c3c; }
.ub-card .c-price .cur { font-size:11px; font-weight:500; color:#b4b9c0; }
.ub-card.merged { background:linear-gradient(180deg,#f4f8ff,#eef4ff); border-color:#cfe0ff; }
.ub-card.merged:hover { border-color:#1976d2; box-shadow:0 4px 14px rgba(25,118,210,.15); }
.ub-card.merged .c-zone { background:#1976d2; }
.ub-card.merged .c-tags { margin-top:6px; font-size:11px; color:#6b8fc7; }

/* 空态 */
.ub-empty { text-align:center; padding:70px 20px; background:#fff; border-radius:12px; border:1px solid #f0f0f0; }
.ub-empty i { font-size:46px; color:#d5d8dd; display:block; margin-bottom:14px; }
.ub-empty p { color:#8a9099; margin-bottom:16px; }

/* 加载更多 */
.ub-more { text-align:center; margin:22px 0 8px; }
.ub-more button { min-width:180px; }
.ub-none { text-align:center; color:#9aa0a6; padding:40px 0; display:none; }
.ub-city.ub-hidden, .ub-city.ub-overflow { display:none; }

@media(max-width:768px){
    .ub-stats { grid-template-columns:repeat(2,1fr); }
    .ub-grid { grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); }
    .ub-toolbar .tb-actions { margin-left:0; width:100%; }
    .ub-toolbar .tb-actions .ub-btn { flex:1; justify-content:center; }
}
</style>

<div class="ub-wrap">
    <h1 class="page-title" style="margin-bottom:18px;">我的区块</h1>

    <?php if (empty($userBlocks)): ?>
        <div class="ub-empty">
            <i class="fas fa-map-marked-alt"></i>
            <p>还没有任何区块</p>
            <a href="../city.php?name=beijing" class="ub-btn primary"><i class="fas fa-compass"></i> 浏览区块城市</a>
        </div>
    <?php else:
        // 按城市分组
        $grouped = [];
        foreach ($userBlocks as $b) {
            // 合并块的子块已在合并组卡片中展示，这里跳过避免拆散
            if (isset($mergedNumSet[$block->normalizeBlockKey($b['city_id'], $b['zone'], $b['block_number'])])) continue;
            $cityId = $b['city_id'] ?? 0;
            $cityName = $b['city_name'] ?? '未知城市';
            $cityPinyin = $b['city_pinyin'] ?? '';
            if (!isset($grouped[$cityId])) {
                $grouped[$cityId] = [
                    'name'    => $cityName,
                    'pinyin'  => $cityPinyin,
                    'blocks'  => [],
                    'total'   => 0,
                ];
            }
            $grouped[$cityId]['blocks'][] = $b;
            $grouped[$cityId]['total']   += $b['calc_price'] ?? 0;
        }

        // 城市按“实际区块数”降序（合并组也算 1 块）
        foreach ($grouped as $cid => &$cg) {
            $cg['mg'] = $mergedByCity[$cid] ?? [];
            $mgPrice = 0;
            foreach ($cg['mg'] as $mg) {
                foreach (explode(',', $mg['merged_blocks']) as $mn) {
                    $mgPrice += calculateBlockPriceNew((string)$mg['zone'], (string)trim($mn));
                }
            }
            $cg['mg_price'] = $mgPrice;
            $cg['owned']    = count($cg['blocks']) + count($cg['mg']);
        }
        unset($cg);
        uasort($grouped, function ($a, $b) { return $b['owned'] - $a['owned']; });
    ?>
        <!-- 概览 -->
        <div class="ub-stats">
            <div class="ub-stat">
                <div class="num"><?= number_format($actualBlockCount) ?><small>个</small></div>
                <div class="lbl">实际拥有区块（合并按 1 块计）</div>
            </div>
            <div class="ub-stat">
                <div class="num"><?= number_format($voteCount) ?><small>票</small></div>
                <div class="lbl">投票数</div>
            </div>
            <div class="ub-stat accent">
                <div class="num"><?= count($grouped) ?><small>个</small></div>
                <div class="lbl">覆盖城市</div>
            </div>
            <div class="ub-stat accent">
                <div class="num" style="font-size:20px;">¥<?= number_format($totalValue, 0) ?></div>
                <div class="lbl">区块总价值</div>
            </div>
        </div>

        <!-- 工具条 -->
        <div class="ub-toolbar">
            <div class="tb-search">
                <i class="fas fa-search"></i>
                <input type="text" id="ubSearch" placeholder="搜索城市 / 区块编号…" autocomplete="off">
            </div>
            <select id="ubSort">
                <option value="owned">按拥有区块数</option>
                <option value="value">按总价值</option>
                <option value="name">按城市名称</option>
            </select>
            <select id="ubZone">
                <option value="">全部区域</option>
                <?php foreach (['A','B','C','D','E','F','G','H','Z'] as $z): ?>
                <option value="<?= $z ?>"><?= $z ?>区</option>
                <?php endforeach; ?>
            </select>
            <div class="tb-actions">
                <a href="../city.php?name=beijing" class="ub-btn"><i class="fas fa-compass"></i> 去认领</a>
            </div>
        </div>

        <div class="ub-toggle-all">
            <button type="button" id="ubExpandAll">全部展开</button>
            <span>|</span>
            <button type="button" id="ubCollapseAll">全部收起</button>
        </div>

        <!-- 城市列表 -->
        <div id="ubList">
        <?php foreach ($grouped as $cityId => $city): ?>
        <section class="ub-city"
                 data-name="<?= htmlspecialchars($city['name']) ?>"
                 data-owned="<?= (int)$city['owned'] ?>"
                 data-value="<?= round($city['total'] + $city['mg_price'], 2) ?>"
                 data-search="<?= htmlspecialchars(mb_strtolower($city['name'] . ' ' . implode(' ', array_column($city['blocks'], 'block_number')))) ?>">
            <div class="ub-city-head">
                <div class="ub-city-title">
                    <span class="name">
                        <?php if ($city['pinyin']): ?>
                            <a href="../city.php?name=<?= urlencode($city['pinyin']) ?>" onclick="event.stopPropagation();"><?= htmlspecialchars($city['name']) ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars($city['name']) ?>
                        <?php endif; ?>
                    </span>
                    <span class="cnt"><?= count($city['blocks']) ?> 区块<?= $city['mg'] ? ' + ' . count($city['mg']) . ' 合并组' : '' ?></span>
                </div>
                <div class="ub-city-right">
                    <span class="val">¥<?= number_format($city['total'] + $city['mg_price'], 0) ?></span>
                    <i class="fas fa-chevron-down caret"></i>
                </div>
            </div>
            <div class="ub-grid">
                <?php foreach ($city['blocks'] as $b): ?>
                    <a href="../block/view.php?id=<?= $b['id'] ?>" class="ub-card" data-zone="<?= htmlspecialchars($b['zone']) ?>">
                        <div class="c-top">
                            <span class="c-zone"><?= htmlspecialchars($b['zone']) ?>区</span>
                            <span class="c-num">#<?= htmlspecialchars($b['block_number']) ?></span>
                        </div>
                        <div class="c-price">¥<?= number_format($b['calc_price'] ?? 0, 0) ?></div>
                    </a>
                <?php endforeach; ?>
                <?php foreach ($city['mg'] as $mg):
                    $mgNums = array_map('trim', explode(',', $mg['merged_blocks']));
                    $mgPrice = 0;
                    foreach ($mgNums as $mn) { $mgPrice += calculateBlockPriceNew((string)$mg['zone'], (string)$mn); }
                ?>
                    <a href="../block/manage.php?merged_id=<?= $mg['id'] ?>" class="ub-card merged" data-zone="<?= htmlspecialchars($mg['zone']) ?>">
                        <div class="c-top">
                            <span class="c-zone">合并 <?= htmlspecialchars($mg['merge_size']) ?> 块</span>
                            <span class="c-num">#<?= htmlspecialchars(min($mgNums)) ?></span>
                        </div>
                        <div class="c-tags"><?= htmlspecialchars($mg['zone']) ?>区 · <?= $mgNums[0] ?>-<?= end($mgNums) ?></div>
                        <div class="c-price">¥<?= number_format($mgPrice, 0) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
        </div>

        <div class="ub-none" id="ubNone">没有匹配的区块</div>

        <div class="ub-more" id="ubMoreWrap">
            <button type="button" class="ub-btn" id="ubMore">加载更多城市</button>
        </div>
    <?php endif; ?>
</div>

<script>
$(function () {
    var PAGE_SIZE = 6;
    var shown = PAGE_SIZE;

    var $list = $('#ubList');
    var $cities = $list.children('.ub-city');

    function applyFilter() {
        var q = ($('#ubSearch').val() || '').trim().toLowerCase();
        var zone = $('#ubZone').val();
        var visible = 0;

        $cities.each(function () {
            var $c = $(this);
            var ok = true;

            if (q && $c.data('search').indexOf(q) === -1) ok = false;
            if (ok && zone) {
                // 该城市下是否存在该区的卡片
                ok = $c.find('.ub-card[data-zone="' + zone + '"]').length > 0;
            }
            $c.toggleClass('ub-hidden', !ok);
            if (ok) visible++;
        });

        $('#ubNone').toggle(visible === 0);

        // 恢复分页显示
        shown = PAGE_SIZE;
        paintPagination();
    }

    function paintPagination() {
        // 参与分页的是“通过筛选”的城市
        var matching = $cities.not('.ub-hidden');
        var count = matching.length;

        matching.each(function (i) {
            $(this).toggleClass('ub-overflow', i >= shown);
        });

        if (count > shown) {
            $('#ubMoreWrap').show();
            $('#ubMore').text('加载更多城市（还有 ' + (count - shown) + ' 个）');
        } else {
            $('#ubMoreWrap').hide();
        }
    }

    function sortCities() {
        var mode = $('#ubSort').val();
        var arr = $cities.get();
        arr.sort(function (a, b) {
            var $a = $(a), $b = $(b);
            if (mode === 'value') return parseFloat($b.data('value')) - parseFloat($a.data('value'));
            if (mode === 'name')  return String($a.data('name')).localeCompare(String($b.data('name')), 'zh');
            return parseInt($b.data('owned'), 10) - parseInt($a.data('owned'), 10);
        });
        $.each(arr, function (i, el) { $list.append(el); });
        paintPagination();
    }

    $cities.on('click', '.ub-city-head', function () {
        $(this).closest('.ub-city').toggleClass('collapsed');
    });

    $('#ubExpandAll').on('click', function () { $cities.removeClass('collapsed'); });
    $('#ubCollapseAll').on('click', function () { $cities.addClass('collapsed'); });

    $('#ubSearch').on('input', applyFilter);
    $('#ubZone').on('change', applyFilter);
    $('#ubSort').on('change', sortCities);

    $('#ubMore').on('click', function () {
        shown += PAGE_SIZE;
        paintPagination();
    });

    // 首次渲染：先按默认排序，再套用筛选/分页
    sortCities();
    applyFilter();
});
</script>

<?php require_once '../includes/footer.php'; ?>
