<?php
// 模特库发现页
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Model.php';
require_once '../../classes/SeoHelper.php';
require_once './card.php';

$model = new Model($pdo);
$userId = $_SESSION['user_id'] ?? 0;
$perPage = 24;

// 筛选参数
$gender = in_array($_GET['gender'] ?? '', ['男', '女', '保密']) ? $_GET['gender'] : '';
$zodiac = trim($_GET['zodiac'] ?? '');
$city   = trim($_GET['city'] ?? '');
$q      = trim($_GET['q'] ?? '');
$sortMap = ['follower' => 1, 'like' => 1, 'product' => 1, 'new' => 1];
$sort = $sortMap[$_GET['sort'] ?? ''] ? $_GET['sort'] : 'follower';
$page  = max(1, intval($_GET['page'] ?? 1));

$filters = ['gender' => $gender, 'zodiac' => $zodiac, 'city' => $city, 'q' => $q, 'sort' => $sort];

// 合并当前 GET 生成筛选链接（重置 page）
function buildQuery($overrides) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    unset($params['ajax']);
    $params['page'] = 1;
    return http_build_query($params);
}

// ---------- AJAX：返回一页卡片片段 ----------
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $result = $model->getFilteredList($filters, $page, $perPage);
    $list = $result['list'];
    $ids = array_column($list, 'id');
    $strips = $model->getModelImageStrips($ids, 4);
    $followedIds = [];
    if ($userId && $ids) {
        $ph = implode(',', array_map('intval', $ids));
        $stmt = $pdo->prepare("SELECT model_id FROM model_follows WHERE user_id = ? AND model_id IN ($ph)");
        $stmt->execute([$userId]);
        $followedIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    $html = '';
    foreach ($list as $m) {
        $html .= renderModelCard($m, $strips[$m['id']] ?? [], isset($followedIds[$m['id']]), $userId);
    }
    echo json_encode([
        'html'    => $html,
        'page'    => $page,
        'pages'   => $result['pages'],
        'total'   => $result['total'],
        'hasMore' => $page < $result['pages'],
    ]);
    exit;
}

// ---------- 首屏渲染 ----------
$facets = $model->getFacets();
$result = $model->getFilteredList($filters, 1, $perPage);
$firstList = $result['list'];
$firstIds = array_column($firstList, 'id');
$firstStrips = $model->getModelImageStrips($firstIds, 4);
$followedIds = [];
if ($userId && $firstIds) {
    $ph = implode(',', array_map('intval', $firstIds));
    $stmt = $pdo->prepare("SELECT model_id FROM model_follows WHERE user_id = ? AND model_id IN ($ph)");
    $stmt->execute([$userId]);
    $followedIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
}

$sortLabels = [
    'follower' => '🔥 粉丝',
    'like'     => '❤ 人气',
    'product'  => '📦 作品',
    'new'      => '🆕 最新',
];

// SEO
$site_config['title']       = SeoHelper::title('模特库 - 58人气值商城');
$site_config['description'] = SeoHelper::description('浏览 58人气值商城全部模特，按性别、城市、星座筛选你喜欢的模特，关注心仪模特获取最新作品。', '58人气值商城');
$site_config['keywords']    = '58,模特库,模特,商城,区块城市';
$site_config['canonical_url'] = 'https://mall.58.tl/model/list.php';

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="style.css">
<div class="model-board">
    <h1 style="font-size:24px;margin:0 0 16px;">📸 模特库</h1>

    <!-- 筛选条 -->
    <div class="model-filters">
        <form method="get" class="filter-row" id="search-form">
            <span class="filter-label">搜索</span>
            <input type="text" class="search-input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="输入昵称搜索模特…">
            <button type="submit" style="padding:7px 18px;border:none;border-radius:20px;background:#ff6b00;color:#fff;cursor:pointer;font-size:14px;">搜索</button>
            <?php foreach (['gender','zodiac','city','sort'] as $k): if (isset($_GET[$k])): ?>
                <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_GET[$k]) ?>">
            <?php endif; endforeach; ?>
        </form>

        <div class="filter-row">
            <span class="filter-label">性别</span>
            <a class="gender-opt <?= $gender===''?'active':'' ?>" href="?<?= buildQuery(['gender'=>'']) ?>">全部</a>
            <a class="gender-opt <?= $gender==='女'?'active':'' ?>" href="?<?= buildQuery(['gender'=>'女']) ?>">女</a>
            <a class="gender-opt <?= $gender==='男'?'active':'' ?>" href="?<?= buildQuery(['gender'=>'男']) ?>">男</a>
        </div>

        <?php if (!empty($facets['cities'])): ?>
        <div class="filter-row">
            <span class="filter-label">城市</span>
            <a class="chip <?= $city===''?'active':'' ?>" href="?<?= buildQuery(['city'=>'']) ?>">全部</a>
            <?php foreach ($facets['cities'] as $c): ?>
            <a class="chip <?= $city===$c['city']?'active':'' ?>" href="?<?= buildQuery(['city'=>$c['city']]) ?>"><?= htmlspecialchars($c['city']) ?> (<?= $c['c'] ?>)</a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($facets['zodiacs'])): ?>
        <div class="filter-row">
            <span class="filter-label">星座</span>
            <a class="chip <?= $zodiac===''?'active':'' ?>" href="?<?= buildQuery(['zodiac'=>'']) ?>">全部</a>
            <?php foreach ($facets['zodiacs'] as $z): ?>
            <a class="chip <?= $zodiac===$z['zodiac']?'active':'' ?>" href="?<?= buildQuery(['zodiac'=>$z['zodiac']]) ?>"><?= htmlspecialchars($z['zodiac']) ?> (<?= $z['c'] ?>)</a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="filter-row">
            <span class="filter-label">排序</span>
            <?php foreach ($sortLabels as $k => $label): ?>
            <a class="chip <?= $sort===$k?'active':'' ?>" href="?<?= buildQuery(['sort'=>$k]) ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="model-count">共找到 <b><?= $result['total'] ?></b> 位模特</div>

    <!-- 卡片网格 -->
    <div class="model-grid" id="model-grid">
        <?php if (empty($firstList)): ?>
            <div class="model-empty">没有符合条件的模特，换个筛选试试~</div>
        <?php else: ?>
            <?php foreach ($firstList as $m): ?>
                <?= renderModelCard($m, $firstStrips[$m['id']] ?? [], isset($followedIds[$m['id']]), $userId) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <button class="model-load-more" id="load-more" data-page="1" data-pages="<?= $result['pages'] ?>">加载更多</button>
    <?php endif; ?>
</div>

<script src="follow.js"></script>
<script>
(function () {
    var btn = document.getElementById('load-more');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var next = parseInt(btn.dataset.page, 10) + 1;
        var pages = parseInt(btn.dataset.pages, 10);
        btn.disabled = true;
        btn.textContent = '加载中…';

        var qs = new URLSearchParams(window.location.search);
        qs.delete('page');
        qs.set('ajax', '1');
        qs.set('page', next);

        fetch('list.php?' + qs.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var grid = document.getElementById('model-grid');
                grid.insertAdjacentHTML('beforeend', res.html);
                bindFollowButtons(grid);
                btn.dataset.page = next;
                if (res.hasMore) {
                    btn.disabled = false;
                    btn.textContent = '加载更多';
                } else {
                    btn.textContent = '已加载全部';
                    btn.style.display = 'none';
                }
                // 同步 URL（可分享/刷新保持）
                var share = new URLSearchParams(window.location.search);
                share.set('page', next);
                history.replaceState(null, '', '?' + share.toString());
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = '加载失败，点击重试';
            });
    });
})();
</script>

<?php require_once '../includes/footer.php'; ?>
