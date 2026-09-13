<?php
/**
 * 模特子站 · 模特库列表页
 * 支持：昵称搜索、性别 / 城市 / 星座筛选、排序、分页与加载更多
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/card.php';

$userId  = $modelUserId;
// 首屏 20 条：可被桌面 5 列 / 平板 4 列整除，首屏无半行空位
$perPage = 20;

$gender  = in_array($_GET['gender'] ?? '', ['男', '女', '保密'], true) ? $_GET['gender'] : '';
$zodiac  = trim($_GET['zodiac'] ?? '');
$city    = trim($_GET['city'] ?? '');
$q       = trim($_GET['q'] ?? '');
$sortMap = ['follower', 'like', 'product', 'new'];
$sort    = in_array($_GET['sort'] ?? '', $sortMap, true) ? $_GET['sort'] : 'follower';
$page    = max(1, intval($_GET['page'] ?? 1));

$filters = ['gender' => $gender, 'zodiac' => $zodiac, 'city' => $city, 'q' => $q, 'sort' => $sort];

/** 合并当前 GET 生成筛选链接（重置分页） */
function buildQuery($overrides)
{
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    unset($params['ajax']);
    $params['page'] = 1;
    return http_build_query($params);
}

/* ---------- AJAX：返回一页卡片 ---------- */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // 「加载更多」以偏移量为游标：offset = 页面已渲染的卡片数，
    // limit = 当前网格列数，保证每次追加恰好补满一行、且不重复数据。
    $offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
    $limit  = isset($_GET['limit'])  ? max(1, min(50, intval($_GET['limit']))) : 20;

    $r    = $modelObj->getFilteredListByOffset($filters, $offset, $limit);
    $rows = $r['list'];

    $ids    = array_column($rows, 'id');
    $strips = $modelObj->getModelImageStrips($ids, 4);
    $followedIds = [];
    if ($userId && $ids) {
        $ph = implode(',', array_map('intval', $ids));
        $stmt = $pdo->prepare("SELECT model_id FROM model_follows WHERE user_id = ? AND model_id IN ($ph)");
        $stmt->execute([$userId]);
        $followedIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $html = '';
    foreach ($rows as $m) {
        $html .= renderModelCard($m, $strips[$m['id']] ?? [], isset($followedIds[$m['id']]), $userId);
    }

    echo json_encode([
        'html'    => $html,
        'count'   => count($rows),
        'total'   => $r['total'],
        'hasMore' => $r['hasMore'],
    ]);
    exit;
}

/* ---------- 首屏 ---------- */
$facets = $modelObj->getFacets();
$result = $modelObj->getFilteredList($filters, 1, $perPage);
$firstList   = $result['list'];
$firstIds    = array_column($firstList, 'id');
$firstStrips = $modelObj->getModelImageStrips($firstIds, 4);
$followedIds = [];
if ($userId && $firstIds) {
    $ph = implode(',', array_map('intval', $firstIds));
    $stmt = $pdo->prepare("SELECT model_id FROM model_follows WHERE user_id = ? AND model_id IN ($ph)");
    $stmt->execute([$userId]);
    $followedIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
}

$sortLabels = ['follower' => '🔥 粉丝', 'like' => '❤ 人气', 'product' => '📦 作品', 'new' => '🆕 最新'];

/* ---------- SEO ---------- */
$pageTitle = '模特库';
if ($gender) $pageTitle = $gender . '生模特';
if ($city)   $pageTitle = $city . '模特';
$site_config = model_site_config([
    'title'       => SeoHelper::title($pageTitle . ' - 58 模特库'),
    'description' => SeoHelper::description('浏览 58 模特库全部模特，按性别、城市、星座筛选你喜欢的模特，查看作品图集与参演短剧，关注心仪模特获取最新动态。', '58 模特库'),
    'keywords'    => '58模特,模特库,' . ($city ?: '') . '模特,模特写真,模特招募',
    'canonical_url' => SeoHelper::modelListUrl(['gender' => $gender, 'city' => $city, 'zodiac' => $zodiac, 'q' => $q, 'sort' => $sort]),
]);
require_once __DIR__ . '/includes/header.php';
?>

<div class="model-wrap">
    <div class="m-page-head">
        <h1>📸 模特库</h1>
        <p>每一位模特都有自己的风格。按城市、星座或关键词找到你想认识的 TA。</p>
        <div class="head-cta">
            <a class="m-btn m-btn-primary m-btn-sm" href="/apply.php"><i class="fas fa-user-plus"></i> 我要当模特</a>
            <a class="m-btn m-btn-ghost m-btn-sm" href="/rankings.php"><i class="fas fa-trophy"></i> 模特排行榜</a>
        </div>
    </div>

    <!-- 筛选条 -->
    <div class="m-filters" style="margin-top:22px;">
        <form method="get" class="filter-row" id="search-form">
            <span class="filter-label">搜索</span>
            <input type="text" name="q" class="search-input" value="<?= htmlspecialchars($q) ?>" placeholder="输入模特昵称…" maxlength="50">
            <?php if ($gender): ?><input type="hidden" name="gender" value="<?= htmlspecialchars($gender) ?>"><?php endif; ?>
            <?php if ($city): ?><input type="hidden" name="city" value="<?= htmlspecialchars($city) ?>"><?php endif; ?>
            <?php if ($zodiac): ?><input type="hidden" name="zodiac" value="<?= htmlspecialchars($zodiac) ?>"><?php endif; ?>
            <button type="submit" class="search-btn">搜索</button>
        </form>

        <div class="filter-row">
            <span class="filter-label">性别</span>
            <a class="gender-opt <?= $gender === '' ? 'active' : '' ?>" href="?<?= buildQuery(['gender' => '']) ?>">全部</a>
            <a class="gender-opt <?= $gender === '女' ? 'active' : '' ?>" href="?<?= buildQuery(['gender' => '女']) ?>">女生</a>
            <a class="gender-opt <?= $gender === '男' ? 'active' : '' ?>" href="?<?= buildQuery(['gender' => '男']) ?>">男生</a>
        </div>

        <?php if (!empty($facets['cities'])): ?>
        <div class="filter-row">
            <span class="filter-label">城市</span>
            <a class="chip <?= $city === '' ? 'active' : '' ?>" href="?<?= buildQuery(['city' => '']) ?>">全部</a>
            <?php foreach (array_slice($facets['cities'], 0, 16) as $c): ?>
                <a class="chip <?= $city === $c['city'] ? 'active' : '' ?>" href="?<?= buildQuery(['city' => $c['city']]) ?>">
                    <?= htmlspecialchars($c['city']) ?> <small style="opacity:.65"><?= intval($c['c']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($facets['zodiacs'])): ?>
        <div class="filter-row">
            <span class="filter-label">星座</span>
            <a class="chip <?= $zodiac === '' ? 'active' : '' ?>" href="?<?= buildQuery(['zodiac' => '']) ?>">全部</a>
            <?php foreach ($facets['zodiacs'] as $z): ?>
                <a class="chip <?= $zodiac === $z['zodiac'] ? 'active' : '' ?>" href="?<?= buildQuery(['zodiac' => $z['zodiac']]) ?>">
                    <?= htmlspecialchars($z['zodiac']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="filter-row">
            <span class="filter-label">排序</span>
            <?php foreach ($sortLabels as $k => $label): ?>
                <a class="chip <?= $sort === $k ? 'active' : '' ?>" href="?<?= buildQuery(['sort' => $k]) ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="m-count">共找到 <b><?= intval($result['total']) ?></b> 位模特</div>

    <div class="m-grid" id="model-grid">
        <?php if (empty($firstList)): ?>
            <div class="m-empty" style="grid-column:1/-1;"><i class="fas fa-search"></i>没有符合条件的模特，换个筛选试试~</div>
        <?php else: ?>
            <?php foreach ($firstList as $m): ?>
                <?= renderModelCard($m, $firstStrips[$m['id']] ?? [], isset($followedIds[$m['id']]), $userId) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <button class="m-load-more" id="load-more" data-total="<?= intval($result['total']) ?>">加载更多</button>
    <?php endif; ?>
</div>

<div class="m-lightbox" id="lightbox">
    <button class="m-lb-close" onclick="closeLightbox()">&times;</button>
    <button class="m-lb-nav m-lb-prev" onclick="lbStep(-1)">‹</button>
    <img id="lightbox-img" src="" alt="">
    <button class="m-lb-nav m-lb-next" onclick="lbStep(1)">›</button>
    <div class="m-lb-count" id="lightbox-count"></div>
</div>

<script src="<?= htmlspecialchars(model_asset('assets/js/model.js')) ?>"></script>
<script>
(function () {
    // 当前筛选参数（从地址栏继承，供加载更多复用）
    var sp = new URLSearchParams(window.location.search);
    sp.delete('page');
    sp.delete('ajax');
    var args = {};
    sp.forEach(function (v, k) { args[k] = v; });

    var opts = {
        buttonId: 'load-more',
        gridId:   'model-grid',
        url:      '/list.php',
        args:     args,
        onLoaded: function (grid, res) {
            bindFollowButtons(grid);
            // 同步地址栏，便于分享当前浏览进度（无数据时不写）
            if (typeof res.page !== 'undefined') {
                var share = new URLSearchParams(window.location.search);
                share.set('page', res.page);
                history.replaceState(null, '', '?' + share.toString());
            }
        }
    };
    // 首屏在窄屏下可能不满一行，自动补齐
    autoFillFirstRow(opts);
    bindLoadMore(opts);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
