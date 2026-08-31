<?php
require_once '../config/database.php';
require_once '../classes/Post.php';
require_once '../classes/SeoHelper.php';
require_once '../includes/auth.php';

$post = new Post($pdo);
$userId = $_SESSION['user_id'] ?? 0;

$q = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// 空查询重定向首页
if ($q === '') {
    header('Location: index.php');
    exit;
}

$result = $post->search($q, $page, $perPage);
$list = $result['list'];
$total = $result['total'];
$pages = $result['pages'];

// 搜索页为动态结果，noindex（避免低质量重复内容）
$site_config['title'] = SeoHelper::title('搜索“' . $q . '” - 58区块社区', '58区块城市');
$site_config['description'] = SeoHelper::description('58区块社区搜索“' . $q . '”的结果，共 ' . $total . ' 条内容。');
$site_config['canonical_url'] = 'https://club.58.tl/search.php?q=' . urlencode($q);
$site_config['og_url'] = $site_config['canonical_url'];
$site_config['extra_head'] = ($site_config['extra_head'] ?? '') . '<meta name="robots" content="noindex,follow">';
require_once 'includes/header.php';
?>

<div class="club-layout">

  <!-- ============ 主内容 ============ -->
  <main class="club-main">
    <div class="club-header-bar">
      <h1>搜索「<?= htmlspecialchars($q) ?>」</h1>
      <span style="font-size:13px;color:#999;"><?= $total ?> 条结果</span>
    </div>

    <form action="search.php" method="get" class="club-search" style="margin-bottom:16px;">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="搜标题 / 正文..." maxlength="50" autofocus>
      <button type="submit"><i class="fas fa-search"></i></button>
    </form>

    <?php if (empty($list)): ?>
      <div class="club-card club-empty">
        <i class="fas fa-search"></i>
        <p>没有找到与「<?= htmlspecialchars($q) ?>」相关的内容</p>
        <a href="create.php" class="club-btn ghost"><i class="fas fa-pen"></i> 也许你想发布一条</a>
      </div>
    <?php else: ?>
      <div class="club-list">
        <?php foreach ($list as $p):
          $imgs = json_decode($p['images'] ?? '', true);
          $imgs = is_array($imgs) ? $imgs : [];
          $avatarUrl = User::avatarUrl($p['avatar'] ?? '');
          $pUrl = SeoHelper::postUrl($p['id'], $p['title'] ?: $p['content']);
        ?>
        <div class="club-post-row">
          <div class="club-post-avatar">
            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($p['username'] ?? '') ?>" loading="lazy" onerror="this.onerror=null;this.src='https://58.tl/assets/images/default.jpg'">
          </div>
          <div class="club-post-body">
            <a class="club-post-title" href="<?= $pUrl ?>">
              <?php if (!empty($p['is_sticky'])): ?><span class="club-badge sticky"><i class="fas fa-thumbtack"></i> 置顶</span><?php endif; ?>
              <?php if ($p['type'] === 'moment'): ?>
                <span class="club-badge moment">心情</span>
              <?php else: ?>
                <span class="club-badge">帖子</span>
              <?php endif; ?>
              <?= htmlspecialchars($p['type'] === 'post' && $p['title'] ? $p['title'] : mb_substr($p['content'], 0, 60)) ?>
            </a>
            <div class="club-post-meta">
              <span><?= htmlspecialchars($p['username'] ?? '用户#' . $p['user_id']) ?></span>
              <span><?= htmlspecialchars($p['city'] ?? '') ?></span>
              <span><?= date('m-d H:i', strtotime($p['created_at'])) ?></span>
              <span><i class="far fa-heart"></i> <?= $p['like_count'] ?></span>
              <span><i class="far fa-comment"></i> <?= $p['comment_count'] ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
      <div class="club-pagination">
        <?php if ($page > 1): ?><a href="search.php?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">上一页</a><?php endif; ?>
        <span class="current"><?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="search.php?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">下一页</a><?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <!-- ============ 右侧栏 ============ -->
  <?php require_once 'includes/sidebar.php'; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
