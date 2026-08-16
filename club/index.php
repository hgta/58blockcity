<?php
require_once '../config/database.php';
require_once '../classes/Post.php';
require_once '../classes/User.php';
require_once '../includes/auth.php';

$post = new Post($pdo);
$userObj = new User($pdo);
$userId = $_SESSION['user_id'] ?? 0;

// 筛选
$city  = trim($_GET['city'] ?? '');
$topic = $_GET['topic'] ?? '';
$type  = $_GET['type'] ?? '';
$page  = max(1, intval($_GET['page'] ?? 1));

$result = $post->getFeed($page, 20, $city, $topic, $type);
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

// 内容分类（城市板块内的二级分类）
$topics = [
    ''      => ['name'=>'全部', 'icon'=>'list'],
    'block' => ['name'=>'聊区块', 'icon'=>'cubes'],
    'nft'   => ['name'=>'聊头像', 'icon'=>'palette'],
    'bct'   => ['name'=>'聊人气值', 'icon'=>'coins'],
];

// 当前城市板块名（用于标题展示）
$cityName = $city !== '' ? $city : '全部城市';

$site_config['title'] = '社区首页 - 58区块社区';
require_once 'includes/header.php';
?>
<style>
.club-wrap { max-width: 760px; margin: 24px auto; padding: 0 15px; }
.club-actions { display: flex; gap: 10px; margin-bottom: 16px; }
.club-btn { padding: 10px 18px; border-radius: 20px; background: #ff6b00; color: #fff; text-decoration: none; font-size: 14px; font-weight: 600; }
.club-btn.ghost { background: #fff; color: #ff6b00; border: 1px solid #ff6b00; }

/* 城市板块导航（一级） */
.city-board { margin-bottom: 16px; }
.city-board-title { font-size: 13px; color: #999; margin-bottom: 8px; }
.city-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.city-tab { padding: 8px 16px; border-radius: 8px; border: 1px solid #e0e0e0; background: #fff; color: #555; font-size: 14px; cursor: pointer; text-decoration: none; transition: all .15s; }
.city-tab:hover { border-color: #ff6b00; color: #ff6b00; }
.city-tab.active { background: #ff6b00; color: #fff; border-color: #ff6b00; font-weight: 600; }

/* 内容分类（二级） */
.club-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.club-tab { padding: 7px 16px; border-radius: 20px; border: 1px solid #ddd; background: #fff; color: #555; font-size: 14px; cursor: pointer; text-decoration: none; }
.club-tab.active { background: #ff6b00; color: #fff; border-color: #ff6b00; }
.post-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 12px; }
.post-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.post-avatar { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: #f0f0f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.post-avatar img { width: 100%; height: 100%; object-fit: cover; }
.post-user { font-size: 14px; font-weight: 600; color: #333; }
.post-meta { font-size: 12px; color: #999; }
.post-tag { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 4px; background: #eef2ff; color: #4f46e5; }
.post-tag.moment { background: #fce7f3; color: #db2777; }
.post-title { font-size: 16px; font-weight: bold; color: #222; margin: 6px 0; }
.post-content { font-size: 14px; color: #444; line-height: 1.6; word-break: break-word; margin-bottom: 8px; }
.post-images { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.post-images img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; }
.post-actions { display: flex; gap: 16px; font-size: 13px; color: #999; }
.post-actions a { color: #999; text-decoration: none; }
.post-actions a:hover { color: #ff6b00; }
.empty { text-align: center; padding: 60px; color: #999; }
</style>

<div class="club-wrap">
    <div class="club-actions">
        <a href="create.php" class="club-btn"><i class="fas fa-pen"></i> 发帖</a>
        <a href="create.php?type=moment" class="club-btn ghost"><i class="fas fa-smile"></i> 发心情</a>
    </div>

    <!-- 城市板块（一级） -->
    <div class="city-board">
        <div class="city-board-title">📍 选择城市板块</div>
        <div class="city-tabs">
            <a class="city-tab <?= $city === '' ? 'active' : '' ?>" href="index.php?<?= $topic ? 'topic=' . $topic : '' ?>">全部城市</a>
            <?php foreach ($hotCities as $c): ?>
                <a class="city-tab <?= $city === $c ? 'active' : '' ?>" href="index.php?city=<?= urlencode($c) ?><?= $topic ? '&topic=' . $topic : '' ?>"><?= htmlspecialchars($c) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 内容分类（二级，城市板块内） -->
    <div class="club-tabs">
        <?php foreach ($topics as $key => $t): ?>
            <a class="club-tab <?= $topic === $key ? 'active' : '' ?>" href="index.php?<?= $city ? 'city=' . urlencode($city) . '&' : '' ?>topic=<?= $key ?>">
                <i class="fas fa-<?= $t['icon'] ?>"></i> <?= $t['name'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div style="font-size:14px;color:#666;margin-bottom:12px;"><?= htmlspecialchars($cityName) ?> · <?= $total ?> 条内容</div>

    <?php if (empty($list)): ?>
        <div class="empty"><i class="fas fa-comments" style="font-size:48px;opacity:.4;"></i><p style="margin-top:12px;">暂无内容，来发第一条吧~</p></div>
    <?php else: ?>
        <?php foreach ($list as $p): 
            $imgs = json_decode($p['images'] ?? '', true);
            $imgs = is_array($imgs) ? $imgs : [];
            $avatarUrl = $p['avatar'] ? '/assets/images/' . $p['avatar'] : '';
        ?>
        <div class="post-card">
            <div class="post-head">
                <div class="post-avatar">
                    <?php if ($avatarUrl): ?><img src="<?= htmlspecialchars($avatarUrl) ?>" alt=""><?php else: ?><i class="fas fa-user" style="color:#ccc;"></i><?php endif; ?>
                </div>
                <div>
                    <div class="post-user"><?= htmlspecialchars($p['username'] ?? '用户#' . $p['user_id']) ?></div>
                    <div class="post-meta"><?= htmlspecialchars($p['city'] ?? '') ?> · <?= date('m-d H:i', strtotime($p['created_at'])) ?></div>
                </div>
            </div>
            <span class="post-tag <?= $p['type'] === 'moment' ? 'moment' : '' ?>"><?= $p['type'] === 'moment' ? '心情' : ($topics[$p['topic']]['name'] ?? '帖子') ?></span>
            <?php if ($p['type'] === 'post' && $p['title']): ?><div class="post-title"><?= htmlspecialchars($p['title']) ?></div><?php endif; ?>
            <div class="post-content"><?= nl2br(htmlspecialchars(mb_substr($p['content'], 0, 200))) ?></div>
            <?php if (!empty($imgs)): ?>
            <div class="post-images">
                <?php foreach ($imgs as $img): ?>
                    <img src="/<?= htmlspecialchars($img) ?>" alt="" loading="lazy">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="post-actions">
                <a href="post.php?id=<?= $p['id'] ?>"><i class="far fa-heart"></i> <?= $p['like_count'] ?></a>
                <a href="post.php?id=<?= $p['id'] ?>"><i class="far fa-comment"></i> <?= $p['comment_count'] ?></a>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($pages > 1): ?>
        <div style="text-align:center;margin-top:16px;">
            <?php if ($page > 1): ?><a class="club-tab" href="index.php?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">上一页</a><?php endif; ?>
            <span style="margin:0 10px;color:#666;"><?= $page ?> / <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="club-tab" href="index.php?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">下一页</a><?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
