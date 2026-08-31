<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$avatar = $_SESSION['avatar'] ?? 'default.jpg';

// 统计我的帖子数、获赞数、评论数、粉丝数
$stats = ['posts' => 0, 'likes' => 0, 'comments' => 0, 'followers' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['posts'] = intval($stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(like_count),0) FROM posts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['likes'] = intval($stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_comments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['comments'] = intval($stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_follows WHERE target_id = ?");
    $stmt->execute([$userId]);
    $stats['followers'] = intval($stmt->fetchColumn());
} catch (Exception $e) {
    // 表可能未创建，忽略
}

$site_config['title'] = '个人中心 - 58区块社区';
$site_config['canonical_url'] = 'https://club.58.tl/user/dashboard.php';
require_once '../includes/header.php';
?>
<div class="club-layout">

  <main class="club-main">
    <div class="club-header-bar">
      <h1>个人中心</h1>
    </div>

    <div class="club-card">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
        <div class="club-author-avatar" style="width:64px;height:64px;font-size:24px;">
          <img src="<?= htmlspecialchars(User::avatarUrl($avatar)) ?>" alt="<?= htmlspecialchars($username) ?>" onerror="this.onerror=null;this.src='https://58.tl/assets/images/default.jpg'">
        </div>
        <div>
          <div class="club-author-name" style="font-size:20px;"><?= htmlspecialchars($username) ?></div>
          <div style="font-size:13px;color:#999;">58区块社区</div>
        </div>
      </div>
      <div class="club-dash-stats">
        <div class="club-dash-stat"><div class="num"><?= $stats['posts'] ?></div><div class="lbl">我的内容</div></div>
        <div class="club-dash-stat"><div class="num"><?= $stats['likes'] ?></div><div class="lbl">获赞</div></div>
        <div class="club-dash-stat"><div class="num"><?= $stats['comments'] ?></div><div class="lbl">我的评论</div></div>
        <div class="club-dash-stat"><div class="num"><?= $stats['followers'] ?></div><div class="lbl">粉丝</div></div>
      </div>
    </div>

    <div class="club-card">
      <div class="club-dash-links">
        <a href="../my.php"><i class="fas fa-clipboard-list" style="width:20px;"></i> 我的内容 <span class="arrow">›</span></a>
        <a href="../create.php"><i class="fas fa-pen" style="width:20px;"></i> 发布新内容 <span class="arrow">›</span></a>
        <a href="../index.php"><i class="fas fa-home" style="width:20px;"></i> 返回社区首页 <span class="arrow">›</span></a>
      </div>
    </div>
  </main>

  <?php require_once '../includes/sidebar.php'; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
