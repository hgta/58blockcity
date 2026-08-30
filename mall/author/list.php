<?php
// 作者库发现页
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/Author.php';
require_once '../../classes/SeoHelper.php';
require_once './card.php';

$author = new Author($pdo);
$userId = $_SESSION['user_id'] ?? 0;
$perPage = 24;

// 筛选参数
$gender = in_array($_GET['gender'] ?? '', ['男', '女', '保密']) ? $_GET['gender'] : '';
$zodiac = trim($_GET['zodiac'] ?? '');
$city   = trim($_GET['city'] ?? '');
$style  = trim($_GET['style'] ?? '');
$q      = trim($_GET['q'] ?? '');
$sortMap = ['follower' => 1, 'like' => 1, 'product' => 1, 'new' => 1];
$sort = $sortMap[$_GET['sort'] ?? ''] ? $_GET['sort'] : 'follower';
$page  = max(1, intval($_GET['page'] ?? 1));

$filters = ['gender' => $gender, 'zodiac' => $zodiac, 'city' => $city, 'style' => $style, 'q' => $q, 'sort' => $sort];

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
    $result = $author->getFilteredList($filters, $page, $perPage);
    $list = $result['list'];
    $ids = array_column($list, 'id');
    $strips = $author->getAuthorImageStrips($ids, 4);
    $followedIds = [];
    if ($userId && $ids) {
        $ph = implode(',', array_map('intval', $ids));
        $stmt = $pdo->prepare("SELECT author_id FROM author_follows WHERE user_id = ? AND author_id IN ($ph)");
        $stmt->execute([$userId]);
        $followedIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    $html = '';
    foreach ($list as $a) {
        $html .= renderAuthorCard($a, $strips[$a['id']] ?? [], isset($followedIds[$a['id']]), $userId);
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
$facets = $author->getFacets();
$result = $author->getFilteredList($filters, 1, $perPage);
$firstList = $result['list'];
$firstIds = array_column($firstList, 'id');
$firstStrips = $author->getAuthorImageStrips($firstIds, 4);
$followedIds = [];
if ($userId && $firstIds) {
    $ph = implode(',', array_map('intval', $firstIds));
    $stmt = $pdo->prepare("SELECT author_id FROM author_follows WHERE user_id = ? AND author_id IN ($ph)");
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
$site_config['title']       = SeoHelper::title('作者库 - 58人气值商城');
$site_config['description'] = SeoHelper::description('浏览 58人气值商城全部图案作者，按性别、城市、星座、创作风格筛选你喜欢的作者，关注心仪作者获取最新作品。', '58人气值商城');
$site_config['keywords']    = '58,作者库,作者,插画,国潮,商城,区块城市';
$site_config['canonical_url'] = 'https://mall.58.tl/author/list.php';

require_once '../includes/header.php';
?>

<link rel="stylesheet" href="style.css">
<div class="author-board">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
        <h1 style="font-size:24px;margin:0;">🎨 作者库</h1>
        <a href="../apply/author.php" style="display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:999px;background:linear-gradient(135deg,#6c5ce7,#a29bfe);color:#fff;font-size:14px;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(108,92,231,.25);"><i class="fas fa-handshake"></i> 我是作者，我要合作</a>
    </div>

    <!-- 筛选条 -->
    <div class="author-filters">
        <form method="get" class="filter-row" id="search-form">
            <span class="filter-label">搜索</span>
            <input type="text" class="search-input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="输入昵称或简介搜索作者…">
            <button type="submit" style="padding:7px 18px;border:none;border-radius:20px;background:#6c5ce7;color:#fff;cursor:pointer;font-size:14px;">搜索</button>
            <?php foreach (['gender','zodiac','city','style','sort'] as $k): if (isset($_GET[$k])): ?>
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

        <?php if (!empty($facets['styles'])): ?>
        <div class="filter-row">
            <span class="filter-label">风格</span>
            <a class="chip <?= $style===''?'active':'' ?>" href="?<?= buildQuery(['style'=>'']) ?>">全部</a>
            <?php foreach ($facets['styles'] as $s): ?>
            <a class="chip <?= $style===$s['style']?'active':'' ?>" href="?<?= buildQuery(['style'=>$s['style']]) ?>"><?= htmlspecialchars($s['style']) ?> (<?= $s['c'] ?>)</a>
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

    <div class="author-count">共找到 <b><?= $result['total'] ?></b> 位作者</div>

    <!-- 卡片网格 -->
    <div class="author-grid" id="author-grid">
        <?php if (empty($firstList)): ?>
            <div class="author-empty">没有符合条件的作者，换个筛选试试~</div>
        <?php else: ?>
            <?php foreach ($firstList as $a): ?>
                <?= renderAuthorCard($a, $firstStrips[$a['id']] ?? [], isset($followedIds[$a['id']]), $userId) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <button class="author-load-more" id="load-more" data-page="1" data-pages="<?= $result['pages'] ?>">加载更多</button>
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
                var grid = document.getElementById('author-grid');
                grid.insertAdjacentHTML('beforeend', res.html);
                bindAuthorFollowButtons(grid);
                btn.dataset.page = next;
                if (res.hasMore) {
                    btn.disabled = false;
                    btn.textContent = '加载更多';
                } else {
                    btn.textContent = '已加载全部';
                    btn.style.display = 'none';
                }
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
