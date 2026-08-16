<?php
require_once '../config/database.php';
require_once '../classes/Post.php';
require_once '../includes/auth.php';

$post = new Post($pdo);
$userId = $_SESSION['user_id'] ?? 0;

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

// 处理评论/点赞
$commentMsg = '';
$commentErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_like']) && $userId) {
        $post->toggleLike($postId, $userId);
        header('Location: post.php?id=' . $postId);
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
}

$isLiked = $userId ? $post->isLiked($postId, $userId) : false;
$comments = $post->getComments($postId, 200);

// 组织二级回复
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
$avatarUrl = $p['avatar'] ? '/assets/images/' . $p['avatar'] : '';

$site_config['title'] = ($p['title'] ?? mb_substr($p['content'], 0, 30)) . ' - 58区块社区';
require_once 'includes/header.php';
?>
<style>
.post-wrap { max-width: 760px; margin: 24px auto; padding: 0 15px; }
.post-detail { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.post-head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.post-avatar { width: 48px; height: 48px; border-radius: 50%; overflow: hidden; background: #f0f0f0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.post-avatar img { width: 100%; height: 100%; object-fit: cover; }
.post-user { font-size: 15px; font-weight: 600; color: #333; }
.post-meta { font-size: 12px; color: #999; }
.post-title { font-size: 20px; font-weight: bold; color: #222; margin: 12px 0; }
.post-content { font-size: 15px; color: #333; line-height: 1.8; word-break: break-word; margin-bottom: 16px; }
.post-images { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.post-images img { max-width: 200px; max-height: 200px; object-fit: cover; border-radius: 8px; }
.post-actions { display: flex; gap: 20px; padding: 12px 0; border-top: 1px solid #f0f0f0; }
.like-btn { background: #fff; border: 1px solid #ff6b00; color: #ff6b00; padding: 8px 20px; border-radius: 20px; cursor: pointer; font-size: 14px; }
.like-btn.liked { background: #ff6b00; color: #fff; }
.like-btn:disabled { background: #f0f0f0; color: #999; border-color: #ddd; cursor: not-allowed; }

.comments-section { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-top: 16px; }
.comments-section h2 { font-size: 18px; margin-bottom: 16px; }
.comment-form { display: flex; gap: 10px; margin-bottom: 20px; }
.comment-input { flex: 1; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
.btn-primary { background: #ff6b00; color: #fff; border: none; border-radius: 8px; padding: 10px 20px; font-size: 14px; cursor: pointer; font-weight: bold; white-space: nowrap; }
.comment-item { padding: 12px 0; border-bottom: 1px solid #f2f2f2; }
.comment-head { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.comment-user { font-size: 13px; font-weight: 600; color: #333; }
.comment-time { font-size: 11px; color: #999; }
.comment-body { font-size: 14px; color: #444; line-height: 1.6; }
.replies { margin-left: 30px; padding-left: 12px; border-left: 2px solid #f0f0f0; }
.alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 14px; }
.alert-ok { background: #d4edda; color: #155724; }
.alert-err { background: #f8d7da; color: #721c24; }
</style>

<div class="post-wrap">
    <div class="post-detail">
        <div class="post-head">
            <div class="post-avatar">
                <?php if ($avatarUrl): ?><img src="<?= htmlspecialchars($avatarUrl) ?>" alt=""><?php else: ?><i class="fas fa-user" style="color:#ccc;"></i><?php endif; ?>
            </div>
            <div>
                <div class="post-user"><?= htmlspecialchars($p['username'] ?? '用户#' . $p['user_id']) ?></div>
                <div class="post-meta"><?= htmlspecialchars($p['city'] ?? '') ?> · <?= $p['created_at'] ?></div>
            </div>
        </div>

        <?php if ($p['type'] === 'post' && $p['title']): ?><div class="post-title"><?= htmlspecialchars($p['title']) ?></div><?php endif; ?>
        <div class="post-content"><?= nl2br(htmlspecialchars($p['content'])) ?></div>

        <?php if (!empty($imgs)): ?>
        <div class="post-images">
            <?php foreach ($imgs as $img): ?>
                <img src="/<?= htmlspecialchars($img) ?>" alt="">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="post-actions">
            <?php if ($userId): ?>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action_like" value="1">
                <button type="submit" class="like-btn <?= $isLiked ? 'liked' : '' ?>">
                    <?= $isLiked ? '❤️ 已赞' : '🤍 点赞' ?> <?= $p['like_count'] ?>
                </button>
            </form>
            <?php else: ?>
            <button class="like-btn" disabled>🤍 点赞 <?= $p['like_count'] ?>（<a href="auth/login.php?redirect=<?= urlencode('post.php?id=' . $postId) ?>">登录</a>）</button>
            <?php endif; ?>
            <span style="color:#999;font-size:14px;align-self:center;">💬 <?= $p['comment_count'] ?> 评论</span>
        </div>
    </div>

    <div class="comments-section">
        <h2>评论（<?= count($comments) ?>）</h2>

        <?php if ($commentMsg): ?><div class="alert alert-ok"><?= htmlspecialchars($commentMsg) ?></div><?php endif; ?>
        <?php if ($commentErr): ?><div class="alert alert-err"><?= htmlspecialchars($commentErr) ?></div><?php endif; ?>

        <?php if ($userId): ?>
        <form method="POST" class="comment-form">
            <input type="hidden" name="action_comment" value="1">
            <input type="hidden" name="parent_id" value="0">
            <input type="text" name="content" class="comment-input" maxlength="500" placeholder="写下你的评论..." required>
            <button type="submit" class="btn-primary">评论</button>
        </form>
        <?php else: ?>
        <p style="color:#999;margin-bottom:16px;">请<a href="auth/login.php?redirect=<?= urlencode('post.php?id=' . $postId) ?>" style="color:#ff6b00;">登录</a>后评论</p>
        <?php endif; ?>

        <?php if (empty($topComments)): ?>
            <div style="color:#999;padding:20px;text-align:center;">暂无评论</div>
        <?php else: ?>
            <?php foreach ($topComments as $c): 
                $cAvatar = $c['avatar'] ? '/assets/images/' . $c['avatar'] : '';
            ?>
            <div class="comment-item">
                <div class="comment-head">
                    <span class="comment-user"><?= htmlspecialchars($c['username'] ?? '用户#' . $c['user_id']) ?></span>
                    <span class="comment-time"><?= date('m-d H:i', strtotime($c['created_at'])) ?></span>
                </div>
                <div class="comment-body"><?= nl2br(htmlspecialchars($c['content'])) ?></div>

                <?php if (!empty($c['replies'])): ?>
                <div class="replies">
                    <?php foreach ($c['replies'] as $r): ?>
                    <div class="comment-item">
                        <div class="comment-head">
                            <span class="comment-user"><?= htmlspecialchars($r['username'] ?? '用户#' . $r['user_id']) ?></span>
                            <span class="comment-time"><?= date('m-d H:i', strtotime($r['created_at'])) ?></span>
                        </div>
                        <div class="comment-body"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
