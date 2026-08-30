<?php
require_once '../config/database.php';
require_once '../classes/Post.php';
require_once '../classes/User.php';
require_once '../classes/SeoHelper.php';
require_once '../includes/auth.php';

$post = new Post($pdo);
$userObj = new User($pdo);
$userId = $_SESSION['user_id'] ?? 0;

// 筛选
$city  = trim($_GET['city'] ?? '');
$topic = in_array($_GET['topic'] ?? '', ['block', 'nft', 'bct'], true) ? $_GET['topic'] : '';
$type  = in_array($_GET['type'] ?? '', ['post', 'moment'], true) ? $_GET['type'] : '';
$sort  = ($_GET['sort'] ?? '') === 'hot' ? 'hot' : 'new';
$page  = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

$result = $post->getFeed($page, $perPage, $city, $topic, $type, $sort);
$list = $result['list'];
$total = $result['total'];
$pages = $result['pages'];

// 当前用户城市（发帖默认）
$myCity = '';
if ($userId) {
    $u = $userObj->getUserById($userId);
    $myCity = $u['city'] ?? '';
}

// 热门城市（城市板块导航）
$hotCities = [];
try {
    $stmt = $pdo->query("SELECT name FROM cities WHERE status='active' AND is_hot = 1 ORDER BY rank ASC LIMIT 12");
    $hotCities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($hotCities)) {
        $stmt = $pdo->query("SELECT name FROM cities WHERE status='active' ORDER BY rank ASC LIMIT 12");
        $hotCities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $hotCities = [];
}

// 内容分类（话题）
$topics = [
    ''      => ['name' => '全部', 'icon' => 'list'],
    'block' => ['name' => '聊区块', 'icon' => 'cubes'],
    'nft'   => ['name' => '聊头像', 'icon' => 'palette'],
    'bct'   => ['name' => '聊人气值', 'icon' => 'coins'],
];

// 当前筛选名（h1 / title / breadcrumb）
$cityName = $city !== '' ? $city : '全部城市';
$topicName = $topic !== '' ? $topics[$topic]['name'] : '全部话题';
$h1Title = $cityName . ' · ' . $topicName;

// 构造筛选 URL（保留当前筛选，仅改某一维度；sort=new 视为默认省略）
function club_filter_url($overrides, $curCity, $curTopic, $curSort, $page = 1) {
    $params = [];
    if ($curCity !== '') $params['city'] = $curCity;
    if ($curTopic !== '') $params['topic'] = $curTopic;
    if ($curSort !== '' && $curSort !== 'new') $params['sort'] = $curSort;
    if ($page > 1) $params['page'] = $page;
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null || ($k === 'sort' && $v === 'new')) { unset($params[$k]); }
        else { $params[$k] = $v; }
    }
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

// 分页 canonical + rel=prev/next
$pageUrl = function ($pg) use ($city, $topic, $sort) {
    $params = [];
    if ($city !== '') $params['city'] = $city;
    if ($topic !== '') $params['topic'] = $topic;
    if ($sort !== 'new') $params['sort'] = $sort;
    if ($pg > 1) $params['page'] = $pg;
    return 'https://club.58.tl/index.php' . ($params ? '?' . http_build_query($params) : '');
};
$canonical = $pageUrl($page);

// ItemList JSON-LD（列表页结构数据）
$itemListItems = [];
foreach ($list as $p) {
    $itemListItems[] = [
        'url'  => SeoHelper::postUrl($p['id'], $p['title'] ?: $p['content']),
        'name' => ($p['type'] === 'post' && $p['title']) ? $p['title'] : mb_substr($p['content'], 0, 40),
    ];
}
$itemListSchema = SeoHelper::itemListSchema($itemListItems, $h1Title . ' - 58区块社区');

$site_config['title'] = SeoHelper::title($h1Title . ' - 58区块社区', '58区块城市');
$site_config['description'] = SeoHelper::description($h1Title . '：' . ($total ? '共 ' . $total . ' 条内容，' : '') . '聊区块、聊头像、聊人气值，发布你的见闻与心情。');
$site_config['canonical_url'] = $canonical;
$site_config['og_url'] = $canonical;
// 分页 rel=prev/next（注入 head）
$paginationRel = '';
if ($pages > 1) {
    if ($page > 1) $paginationRel .= '<link rel="prev" href="' . htmlspecialchars($pageUrl($page - 1)) . '">';
    if ($page < $pages) $paginationRel .= '<link rel="next" href="' . htmlspecialchars($pageUrl($page + 1)) . '">';
}
if ($paginationRel) {
    $site_config['extra_head'] = ($site_config['extra_head'] ?? '') . $paginationRel;
}
require_once 'includes/header.php';
?>

<div class="club-layout">

  <!-- ============ 主信息流 ============ -->
  <main class="club-main">

    <div class="club-header-bar">
      <h1><?= htmlspecialchars($h1Title) ?></h1>
      <div class="club-pills" style="margin:0;">
        <a class="club-pill <?= $sort === 'new' ? 'active' : '' ?>" href="<?= club_filter_url(['sort' => 'new', 'page' => null], $city, $topic, $sort) ?>"><i class="fas fa-clock"></i> 最新</a>
        <a class="club-pill <?= $sort === 'hot' ? 'active' : '' ?>" href="<?= club_filter_url(['sort' => 'hot', 'page' => null], $city, $topic, $sort) ?>"><i class="fas fa-fire"></i> 热帖</a>
      </div>
    </div>

    <!-- 城市 + 话题筛选 -->
    <div class="club-pills">
      <a class="club-pill <?= $city === '' ? 'active' : '' ?>" href="<?= club_filter_url(['city' => '', 'page' => null], $city, $topic, $sort) ?>"><i class="fas fa-globe"></i> 全部城市</a>
      <?php foreach ($hotCities as $c): ?>
        <a class="club-pill <?= $city === $c ? 'active' : '' ?>" href="<?= club_filter_url(['city' => $c, 'page' => null], $city, $topic, $sort) ?>"><?= htmlspecialchars($c) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="club-pills">
      <?php foreach ($topics as $key => $t): ?>
        <a class="club-pill <?= $topic === $key ? 'active' : '' ?>" href="<?= club_filter_url(['topic' => $key, 'page' => null], $city, $topic, $sort) ?>">
          <i class="fas fa-<?= $t['icon'] ?>"></i> <?= $t['name'] ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($list)): ?>
      <div class="club-card club-empty">
        <i class="fas fa-comments"></i>
        <p>暂无内容，来发第一条吧~</p>
        <a href="create.php" class="club-btn primary"><i class="fas fa-pen"></i> 发布内容</a>
      </div>
    <?php else: ?>
      <div class="club-list">
        <?php foreach ($list as $p):
          $imgs = json_decode($p['images'] ?? '', true);
          $imgs = is_array($imgs) ? $imgs : [];
          $avatarUrl = $p['avatar'] ? '/assets/images/' . $p['avatar'] : '';
          $pUrl = SeoHelper::postUrl($p['id'], $p['title'] ?: $p['content']);
          $isSticky = !empty($p['is_sticky']);
        ?>
        <div class="club-post-row">
          <div class="club-post-avatar">
            <?php if ($avatarUrl): ?><img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($p['username'] ?? '') ?>" loading="lazy"><?php else: ?><i class="fas fa-user"></i><?php endif; ?>
          </div>
          <div class="club-post-body">
            <a class="club-post-title" href="<?= $pUrl ?>">
              <?php if ($isSticky): ?><span class="club-badge sticky"><i class="fas fa-thumbtack"></i> 置顶</span><?php endif; ?>
              <?php if ($p['type'] === 'moment'): ?>
                <span class="club-badge moment">心情</span>
              <?php else: ?>
                <span class="club-badge"><?= htmlspecialchars($topics[$p['topic']]['name'] ?? '帖子') ?></span>
              <?php endif; ?>
              <?= htmlspecialchars($p['type'] === 'post' && $p['title'] ? $p['title'] : mb_substr($p['content'], 0, 60)) ?>
            </a>
            <div class="club-post-meta">
              <span><?= htmlspecialchars($p['username'] ?? '用户#' . $p['user_id']) ?></span>
              <span><?= htmlspecialchars($p['city'] ?? '') ?></span>
              <span><?= date('m-d H:i', strtotime($p['created_at'])) ?></span>
              <span><i class="far fa-heart"></i> <?= $p['like_count'] ?></span>
              <span><i class="far fa-comment"></i> <?= $p['comment_count'] ?></span>
              <?php if (!empty($p['view_count'])): ?><span><i class="far fa-eye"></i> <?= $p['view_count'] ?></span><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
      <div class="club-pagination">
        <?php if ($page > 1): ?><a href="<?= club_filter_url([], $city, $topic, $sort, $page - 1) ?>">上一页</a><?php endif; ?>
        <span class="current"><?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="<?= club_filter_url([], $city, $topic, $sort, $page + 1) ?>">下一页</a><?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <!-- ============ 右侧栏 ============ -->
  <?php require_once 'includes/sidebar.php'; ?>

</div>

<?php if (!empty($itemListSchema)): ?>
<?= $itemListSchema ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
