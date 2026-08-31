<?php
/**
 * club 右侧栏组件（V2EX 风格）
 *
 * 依赖外部已准备好的变量：
 *   $pdo        PDO 实例
 *   $post       Post 实例（可选，自动创建）
 *   $userId     当前登录用户 ID（可选，默认 0）
 *   $hotCities  热门城市数组（可选，自动查询）
 *
 * 用法：<?php require_once 'includes/sidebar.php'; ?>
 */
if (!isset($pdo)) { return; }
require_once __DIR__ . '/../../classes/SeoHelper.php';
if (!isset($post)) {
    require_once __DIR__ . '/../../classes/Post.php';
    $post = new Post($pdo);
}
$userId = $userId ?? ($_SESSION['user_id'] ?? 0);

// 热门话题（固定话题 + 热门城市，作为导航）
$sidebarTopics = [
    ''      => ['name' => '全部', 'icon' => 'list'],
    'block' => ['name' => '聊区块', 'icon' => 'cubes'],
    'nft'   => ['name' => '聊头像', 'icon' => 'palette'],
    'bct'   => ['name' => '聊人气值', 'icon' => 'coins'],
];

if (!isset($hotCities)) {
    $hotCities = [];
    try {
        $stmt = $pdo->query("SELECT name FROM cities WHERE status='active' AND is_hot = 1 ORDER BY rank ASC LIMIT 8");
        $hotCities = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($hotCities)) {
            $stmt = $pdo->query("SELECT name FROM cities WHERE status='active' ORDER BY rank ASC LIMIT 8");
            $hotCities = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        $hotCities = [];
    }
}

// 本周热帖 / 活跃用户（静默降级：表或列不存在时返回空）
$hotPosts = [];
$activeUsers = [];
try {
    $hotPosts = $post->getHotPosts(8);
    $activeUsers = $post->getActiveUsers(6);
} catch (Exception $e) {
    // 忽略，侧栏降级为空
}

// 当前筛选（用于导航高亮）
$curCity  = $_GET['city'] ?? '';
$curTopic = $_GET['topic'] ?? '';
$curSort  = $_GET['sort'] ?? '';
$curQ     = trim($_GET['q'] ?? '');

function club_side_link($params) {
    // 根相对路径：从 user/ 子目录复用时也能正确指向 club 首页
    $q = http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
    return '/index.php' . ($q ? '?' . $q : '');
}
?>
<aside class="club-side">

    <!-- 搜索 -->
    <div class="club-card">
        <h3><i class="fas fa-search"></i> 搜索</h3>
        <form action="/search.php" method="get" class="club-search">
            <input type="text" name="q" value="<?= htmlspecialchars($curQ) ?>" placeholder="搜标题 / 正文..." maxlength="50">
            <button type="submit" aria-label="搜索"><i class="fas fa-arrow-right"></i></button>
        </form>
    </div>

    <!-- 发帖引导 -->
    <div class="club-card club-cta">
        <p>分享你的见闻、问题与心情<br>和同城的朋友一起交流</p>
        <a href="/create.php" class="club-btn primary"><i class="fas fa-pen"></i> 发布内容</a>
    </div>

    <!-- 热门话题 -->
    <div class="club-card">
        <h3><i class="fas fa-fire"></i> 话题板块</h3>
        <div class="club-pills">
            <?php foreach ($sidebarTopics as $key => $t): ?>
                <a class="club-pill <?= $curTopic === $key && $curSort === '' ? 'active' : '' ?>"
                   href="<?= club_side_link(['topic' => $key]) ?>">
                    <i class="fas fa-<?= $t['icon'] ?>"></i> <?= $t['name'] ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="club-pills" style="margin-top:8px;">
            <a class="club-pill <?= $curCity === '' ? 'active' : '' ?>" href="<?= club_side_link([]) ?>">全部城市</a>
            <?php foreach ($hotCities as $c): ?>
                <a class="club-pill <?= $curCity === $c ? 'active' : '' ?>" href="<?= club_side_link(['city' => $c]) ?>"><?= htmlspecialchars($c) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 本周热帖 -->
    <div class="club-card">
        <h3><i class="fas fa-chart-line"></i> 本周热帖</h3>
        <?php if (empty($hotPosts)): ?>
            <div style="font-size:13px;color:#999;">暂无热帖</div>
        <?php else: ?>
            <ol class="club-side-list">
                <?php foreach ($hotPosts as $i => $hp): ?>
                    <li>
                        <span class="rank <?= $i < 3 ? 'top' : '' ?>"><?= $i + 1 ?></span>
                        <a href="<?= SeoHelper::postUrl($hp['id'], $hp['title'] ?: $hp['content']) ?>"
                           title="<?= htmlspecialchars($hp['title'] ?: $hp['content']) ?>">
                            <?= htmlspecialchars(mb_substr($hp['title'] ?: $hp['content'], 0, 20)) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>

    <!-- 活跃用户 -->
    <div class="club-card">
        <h3><i class="fas fa-users"></i> 活跃用户</h3>
        <?php if (empty($activeUsers)): ?>
            <div style="font-size:13px;color:#999;">暂无数据</div>
        <?php else: ?>
            <ul class="club-side-list">
                <?php foreach ($activeUsers as $au): ?>
                    <li>
                        <span class="side-user">
                            <img src="<?= htmlspecialchars(User::avatarUrl($au['avatar'] ?? '')) ?>" alt="<?= htmlspecialchars($au['username'] ?? '') ?>" loading="lazy" onerror="this.onerror=null;this.src='https://58.tl/assets/images/default.jpg'">
                            <span><?= htmlspecialchars($au['username'] ?? '用户#' . $au['id']) ?></span>
                        </span>
                        <span class="cnt"><?= intval($au['post_cnt']) + intval($au['comment_cnt']) ?> 互动</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

</aside>
