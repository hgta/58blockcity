<?php
require_once '../config/database.php';
require_once '../classes/Post.php';
require_once '../classes/SeoHelper.php';
require_once '../includes/auth.php';
checkLogin();

$post = new Post($pdo);
$userId = $_SESSION['user_id'];

$page = max(1, intval($_GET['page'] ?? 1));
$myPosts = $post->getUserPosts($userId, $page, 20);

$site_config['title'] = '我的内容 - 58区块社区';
$site_config['description'] = SeoHelper::description('查看我在58区块社区发布的所有帖子和心情。');
$site_config['canonical_url'] = 'https://club.58.tl/my.php';
$site_config['og_url'] = $site_config['canonical_url'];
require_once 'includes/header.php';
?>

<div class="club-layout">

  <!-- ============ 主内容 ============ -->
  <main class="club-main">
    <div class="club-header-bar">
      <h1>我的内容</h1>
    </div>

    <?php if (empty($myPosts)): ?>
      <div class="club-card club-empty">
        <i class="fas fa-pen"></i>
        <p>你还没有发布过内容</p>
        <a href="create.php" class="club-btn primary"><i class="fas fa-plus"></i> 去发第一条</a>
      </div>
    <?php else: ?>
      <div class="club-list">
        <?php foreach ($myPosts as $p): ?>
        <div class="club-post-row">
          <div class="club-post-avatar">
            <i class="fas fa-user"></i>
          </div>
          <div class="club-post-body">
            <a class="club-post-title" href="<?= SeoHelper::postUrl($p['id'], $p['title'] ?: $p['content']) ?>">
              <?php if (!empty($p['is_sticky'])): ?><span class="club-badge sticky"><i class="fas fa-thumbtack"></i> 置顶</span><?php endif; ?>
              <?php if ($p['type'] === 'moment'): ?>
                <span class="club-badge moment">心情</span>
              <?php else: ?>
                <span class="club-badge">帖子</span>
              <?php endif; ?>
              <?= htmlspecialchars($p['type'] === 'post' && $p['title'] ? $p['title'] : mb_substr($p['content'], 0, 60)) ?>
            </a>
            <div class="club-post-meta">
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

      <?php if (count($myPosts) >= 20): ?>
      <div class="club-pagination">
        <?php if ($page > 1): ?><a href="my.php?page=<?= $page - 1 ?>">上一页</a><?php endif; ?>
        <span class="current">第 <?= $page ?> 页</span>
        <?php if (count($myPosts) === 20): ?><a href="my.php?page=<?= $page + 1 ?>">下一页</a><?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <!-- ============ 右侧栏 ============ -->
  <?php require_once 'includes/sidebar.php'; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
