<?php
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Post.php';
require_once '../classes/SeoHelper.php';
require_once '../includes/auth.php';

$post = new Post($pdo);
$userId = $_SESSION['user_id'] ?? 0;

// 支持伪静态 /post/{id}-{slug}.html 与旧动态 URL post.php?id=
$postId = intval($_GET['id'] ?? 0);
if ($postId <= 0) {
    http_response_code(404);
    include '../404.php';
    exit;
}

$p = $post->getPostById($postId);
if (!$p || $p['status'] !== 'active') {
    http_response_code(404);
    include '../404.php';
    exit;
}

// 规范 URL + 301（旧 URL / 无 slug URL 跳转）
// 注意：带 ?cp= 的评论分页 URL 不执行 301，避免分页参数丢失
$commentPage = max(1, intval($_GET['cp'] ?? 1));
$canonical = SeoHelper::postUrl($postId, $p['title'] ?: $p['content']);
if (!isset($_GET['cp'])) {
    SeoHelper::redirectIfNotCanonical($canonical);
}

// 浏览 +1（静默）
$post->incrementView($postId);

// 处理评论/点赞/置顶/关注（POST 后跳回当前页，保留评论分页参数）
$commentMsg = '';
$commentErr = '';
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$redirectUrl = $canonical . ($commentPage > 1 ? '?cp=' . $commentPage : '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    if (isset($_POST['action_like']) && $userId) {
        $post->toggleLike($postId, $userId);
        header('Location: ' . $redirectUrl);
        exit;
    }
    if (isset($_POST['action_comment'])) {
        $content = trim($_POST['content'] ?? '');
        $parentId = intval($_POST['parent_id'] ?? 0);
        $r = $post->addComment($postId, $userId, $content, $parentId);
        if ($r['ok']) {
            $commentMsg = '评论成功';
        } else {
            $commentErr = $r['msg'];
        }
    }
    if (isset($_POST['action_sticky']) && $isAdmin) {
        $post->toggleSticky($postId);
        header('Location: ' . $redirectUrl);
        exit;
    }
    if (isset($_POST['action_follow']) && $userId && intval($p['user_id']) !== $userId) {
        $post->toggleFollow($userId, intval($p['user_id']));
        header('Location: ' . $redirectUrl);
        exit;
    }
}

$isLiked = $userId ? $post->isLiked($postId, $userId) : false;

// 评论分页（每页 50）
$commentLimit = 50;
$commentOffset = ($commentPage - 1) * $commentLimit;
$commentResult = $post->getComments($postId, $commentLimit, $commentOffset);
$comments = $commentResult['list'];
$commentPages = $commentResult['pages'];

// 组织二级回复（仅当前页内）
$topComments = [];
$replyMap = [];
foreach ($comments as $c) {
    if (intval($c['parent_id']) === 0) {
        $c['replies'] = [];
        $topComments[$c['id']] = $c;
    } else {
        $replyMap[$c['parent_id']][] = $c;
    }
}
foreach ($replyMap as $pid => $replies) {
    if (isset($topComments[$pid])) {
        $topComments[$pid]['replies'] = $replies;
    }
}

$imgs = json_decode($p['images'] ?? '', true);
$imgs = is_array($imgs) ? $imgs : [];
$avatarUrl = User::avatarUrl($p['avatar'] ?? '');
$isSticky = !empty($p['is_sticky']);

// 相关推荐
$relatedPosts = [];
try {
    $relatedPosts = $post->getRelatedPosts($p, 5);
} catch (Exception $e) {
    $relatedPosts = [];
}

// 关注状态
$isFollowing = $userId ? $post->isFollowing($userId, intval($p['user_id'])) : false;
$followerCount = $post->getFollowerCount(intval($p['user_id']));

// 面包屑
$breadcrumbs = [
    ['name' => '社区首页', 'url' => 'https://club.58.tl/'],
];
if (!empty($p['city'])) {
    $breadcrumbs[] = ['name' => $p['city'], 'url' => 'https://club.58.tl/index.php?city=' . urlencode($p['city'])];
}
if (!empty($p['topic'])) {
    $topicNames = ['block' => '聊区块', 'nft' => '聊头像', 'bct' => '聊人气值'];
    $breadcrumbs[] = ['name' => $topicNames[$p['topic']] ?? $p['topic'], 'url' => 'https://club.58.tl/index.php?topic=' . urlencode($p['topic'])];
}
$breadcrumbs[] = ['name' => $p['type'] === 'moment' ? '心情' : '帖子', 'url' => null];

$articleJsonLd = '';
if ($p['type'] === 'post') {
    $firstImage = $imgs[0] ?? '';
    $articleJsonLd = '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => mb_substr($p['title'] ?: $p['content'], 0, 100),
        'description' => SeoHelper::excerpt($p['content'], 120),
        'author' => ['@type' => 'Person', 'name' => $p['username'] ?? '用户#' . $p['user_id']],
        'publisher' => ['@type' => 'Organization', 'name' => '58区块城市'],
        'datePublished' => $p['created_at'],
        'dateModified' => $p['updated_at'] ?? $p['created_at'],
        'mainEntityOfPage' => $canonical,
        'image' => $firstImage ? 'https://58.tl/' . ltrim($firstImage, '/') : 'https://58.tl/assets/images/og-club.jpg',
        'interactionStatistic' => [
            ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/LikeAction', 'userInteractionCount' => intval($p['like_count'])],
            ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/CommentAction', 'userInteractionCount' => intval($p['comment_count'])],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}
$breadcrumbSchema = SeoHelper::breadcrumbList($breadcrumbs);

$site_config['title'] = SeoHelper::title(($p['type'] === 'post' && $p['title'] ? $p['title'] : mb_substr($p['content'], 0, 40)) . ' - 58区块社区', '58区块城市');
$site_config['description'] = SeoHelper::description(($p['type'] === 'post' && $p['title'] ? $p['title'] . '：' : '') . $p['content']);
$site_config['canonical_url'] = $canonical;
$site_config['og_url'] = $canonical;
$site_config['og_type'] = $p['type'] === 'post' ? 'article' : 'website';
if (!empty($imgs)) {
    $site_config['og_image'] = 'https://58.tl/' . ltrim($imgs[0], '/');
}
require_once 'includes/header.php';
?>

<div class="club-layout">

  <!-- ============ 主内容 ============ -->
  <main class="club-main">

    <div class="club-detail">
      <div class="club-breadcrumb">
        <a href="index.php">社区首页</a>
        <?php if (!empty($p['city'])): ?>
          <span>/</span> <a href="index.php?city=<?= urlencode($p['city']) ?>"><?= htmlspecialchars($p['city']) ?></a>
        <?php endif; ?>
        <?php if (!empty($p['topic'])): ?>
          <span>/</span> <a href="index.php?topic=<?= urlencode($p['topic']) ?>"><?= htmlspecialchars($topicNames[$p['topic']] ?? $p['topic']) ?></a>
        <?php endif; ?>
      </div>

      <h1>
        <?php if ($isSticky): ?><span class="club-badge sticky"><i class="fas fa-thumbtack"></i> 置顶</span><?php endif; ?>
        <?php if ($p['type'] === 'moment'): ?><span class="club-badge moment">心情</span><?php endif; ?>
        <?= htmlspecialchars($p['type'] === 'post' && $p['title'] ? $p['title'] : mb_substr($p['content'], 0, 60)) ?>
      </h1>

      <div class="club-author-row">
        <div class="club-author-avatar">
          <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($p['username'] ?? '') ?>" onerror="this.onerror=null;this.src='https://58.tl/assets/images/default.jpg'">
        </div>
        <div style="flex:1;">
          <div class="club-author-name">
            <?= htmlspecialchars($p['username'] ?? '用户#' . $p['user_id']) ?>
            <span class="club-badge moment" style="vertical-align:2px;">楼主</span>
            <?php if ($isAdmin): ?>
              <form method="POST" style="display:inline;margin-left:8px;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action_sticky" value="1">
                <button type="submit" class="club-btn ghost sm"><?= $isSticky ? '取消置顶' : '置顶' ?></button>
              </form>
            <?php endif; ?>
          </div>
          <div class="club-author-meta"><?= htmlspecialchars($p['city'] ?? '') ?> · <?= $p['created_at'] ?> · 浏览 <?= intval($p['view_count'] ?? 0) ?></div>
        </div>
        <?php if ($userId && intval($p['user_id']) !== $userId): ?>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action_follow" value="1">
            <button type="submit" class="club-btn <?= $isFollowing ? 'ghost' : 'primary' ?> sm">
              <i class="<?= $isFollowing ? 'fas fa-check' : 'fas fa-plus' ?>"></i>
              <?= $isFollowing ? '已关注' : '关注' ?> <span style="font-weight:400;"><?= $followerCount ?></span>
            </button>
          </form>
        <?php endif; ?>
      </div>

      <div class="club-detail-content"><?= nl2br(htmlspecialchars($p['content'])) ?></div>

      <?php if (!empty($imgs)): ?>
      <div class="club-detail-images">
        <?php foreach ($imgs as $img): ?>
          <img src="/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['title'] ?: '心情图片') ?>" loading="lazy">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="club-detail-actions">
        <?php if ($userId): ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <input type="hidden" name="action_like" value="1">
          <button type="submit" class="club-btn <?= $isLiked ? 'ghost' : 'primary' ?> sm">
            <i class="<?= $isLiked ? 'fas fa-heart' : 'far fa-heart' ?>"></i> <?= $isLiked ? '已赞' : '点赞' ?>
          </button>
        </form>
        <?php else: ?>
        <a href="auth/login.php?redirect=<?= urlencode($canonical) ?>" class="club-btn ghost sm"><i class="far fa-heart"></i> 登录后点赞</a>
        <?php endif; ?>
        <span class="count"><i class="far fa-heart"></i> <?= $p['like_count'] ?></span>
        <span class="count"><i class="far fa-comment"></i> <?= $commentResult['total'] ?></span>
        <span class="count"><i class="far fa-eye"></i> <?= intval($p['view_count'] ?? 0) ?></span>
      </div>
    </div>

    <!-- 评论 -->
    <div class="club-comments">
      <h2>评论（<?= $commentResult['total'] ?>）</h2>

      <?php if ($commentMsg): ?><div class="club-alert ok"><?= htmlspecialchars($commentMsg) ?></div><?php endif; ?>
      <?php if ($commentErr): ?><div class="club-alert err"><?= htmlspecialchars($commentErr) ?></div><?php endif; ?>

      <?php if ($userId): ?>
      <form method="POST" class="club-comment-input">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action_comment" value="1">
        <input type="hidden" name="parent_id" value="0">
        <input type="text" name="content" maxlength="500" placeholder="写下你的评论..." required>
        <button type="submit" class="club-btn primary">评论</button>
      </form>
      <?php else: ?>
      <p style="color:#999;margin-bottom:16px;font-size:14px;">请<a href="auth/login.php?redirect=<?= urlencode($canonical) ?>" style="color:#ff6b00;">登录</a>后评论</p>
      <?php endif; ?>

      <?php if (empty($topComments)): ?>
        <div style="color:#999;padding:20px;text-align:center;font-size:14px;">暂无评论，来抢沙发~</div>
      <?php else: ?>
        <?php foreach ($topComments as $c):
          $cAvatar = User::avatarUrl($c['avatar'] ?? '');
        ?>
        <div class="club-comment-item">
          <div class="club-comment-head">
            <img src="<?= htmlspecialchars($cAvatar) ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" onerror="this.onerror=null;this.src='https://58.tl/assets/images/default.jpg'">
            <span class="club-comment-user"><?= htmlspecialchars($c['username'] ?? '用户#' . $c['user_id']) ?></span>
            <span class="club-comment-time"><?= date('m-d H:i', strtotime($c['created_at'])) ?></span>
          </div>
          <div class="club-comment-body"><?= nl2br(htmlspecialchars($c['content'])) ?></div>

          <?php if (!empty($c['replies'])): ?>
          <div class="club-comment-replies">
            <?php foreach ($c['replies'] as $r): ?>
            <div class="club-comment-item">
              <div class="club-comment-head">
                <span class="club-comment-user"><?= htmlspecialchars($r['username'] ?? '用户#' . $r['user_id']) ?></span>
                <span class="club-comment-time"><?= date('m-d H:i', strtotime($r['created_at'])) ?></span>
              </div>
              <div class="club-comment-body"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if ($commentPages > 1): ?>
        <div class="club-pagination">
          <?php if ($commentPage > 1): ?>
            <a href="<?= htmlspecialchars(SeoHelper::postUrl($postId, $p['title'] ?: $p['content'])) ?>?cp=<?= $commentPage - 1 ?>">上一页</a>
          <?php endif; ?>
          <span class="current"><?= $commentPage ?> / <?= $commentPages ?></span>
          <?php if ($commentPage < $commentPages): ?>
            <a href="<?= htmlspecialchars(SeoHelper::postUrl($postId, $p['title'] ?: $p['content'])) ?>?cp=<?= $commentPage + 1 ?>">下一页</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- 相关推荐 -->
    <?php if (!empty($relatedPosts)): ?>
    <div class="club-side-box">
      <h3 style="font-size:14px;font-weight:600;margin:0 0 8px;padding-bottom:8px;border-bottom:1px solid #f0f0f0;">相关推荐</h3>
      <?php foreach ($relatedPosts as $rp): ?>
      <div class="club-related-item">
        <a href="<?= SeoHelper::postUrl($rp['id'], $rp['title'] ?: $rp['content']) ?>" title="<?= htmlspecialchars($rp['title'] ?: $rp['content']) ?>">
          <?= htmlspecialchars(mb_substr($rp['title'] ?: $rp['content'], 0, 30)) ?>
        </a>
        <span class="cnt"><?= $rp['comment_count'] ?> 评</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>

  <!-- ============ 右侧栏 ============ -->
  <?php require_once 'includes/sidebar.php'; ?>

</div>

<?= $articleJsonLd ?>
<?= $breadcrumbSchema ?>

<?php require_once 'includes/footer.php'; ?>
