<?php
require_once '../config/database.php';
require_once '../classes/Post.php';
require_once '../includes/auth.php';
checkLogin();

$post = new Post($pdo);
$userId = $_SESSION['user_id'];

$page = max(1, intval($_GET['page'] ?? 1));
$myPosts = $post->getUserPosts($userId, $page, 20);

$site_config['title'] = '我的内容 - 58区块社区';
require_once 'includes/header.php';
?>
<style>
.my-wrap { max-width: 760px; margin: 24px auto; padding: 0 15px; }
.post-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 12px; }
.post-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.post-title { font-size: 16px; font-weight: bold; color: #222; margin: 6px 0; }
.post-content { font-size: 14px; color: #444; line-height: 1.6; margin-bottom: 8px; }
.post-meta { font-size: 12px; color: #999; }
.post-tag { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 4px; background: #eef2ff; color: #4f46e5; }
.post-tag.moment { background: #fce7f3; color: #db2777; }
.post-actions { display: flex; gap: 16px; font-size: 13px; color: #999; margin-top: 8px; }
.post-actions a { color: #999; text-decoration: none; }
.empty { text-align: center; padding: 60px; color: #999; }
</style>

<div class="my-wrap">
    <h1 style="font-size: 24px; margin: 0 0 16px;">📋 我的内容</h1>

    <?php if (empty($myPosts)): ?>
        <div class="empty"><i class="fas fa-pen" style="font-size:48px;opacity:.4;"></i><p style="margin-top:12px;">你还没有发布过内容</p><a href="create.php" style="color:#ff6b00;">去发第一条 →</a></div>
    <?php else: ?>
        <?php foreach ($myPosts as $p): ?>
        <div class="post-card">
            <div class="post-head">
                <span class="post-tag <?= $p['type'] === 'moment' ? 'moment' : '' ?>"><?= $p['type'] === 'moment' ? '心情' : '帖子' ?></span>
                <span class="post-meta"><?= htmlspecialchars($p['city'] ?? '') ?> · <?= date('m-d H:i', strtotime($p['created_at'])) ?></span>
            </div>
            <?php if ($p['type'] === 'post' && $p['title']): ?><div class="post-title"><?= htmlspecialchars($p['title']) ?></div><?php endif; ?>
            <div class="post-content"><?= nl2br(htmlspecialchars(mb_substr($p['content'], 0, 150))) ?></div>
            <div class="post-actions">
                <a href="post.php?id=<?= $p['id'] ?>">查看详情</a>
                <span>❤ <?= $p['like_count'] ?></span>
                <span>💬 <?= $p['comment_count'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
