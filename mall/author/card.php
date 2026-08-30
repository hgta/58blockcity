<?php
// 作者卡片渲染（被 list.php / view.php / following.php 复用）
if (!function_exists('renderAuthorCard')) {
    function renderAuthorCard($a, $imgStrip = [], $isFollowed = false, $userId = 0) {
        $id = intval($a['id']);
        $nickname = htmlspecialchars($a['nickname'] ?? '作者');
        $url = SeoHelper::authorUrl($id, $a['nickname'] ?? '');

        // 头像
        $avatar = '';
        if (!empty($a['avatar'])) {
            $avatar = '../' . $a['avatar'];
        } elseif (!empty($a['user_avatar'])) {
            $ua = $a['user_avatar'];
            $avatar = (strpos($ua, '/') !== false) ? '../' . $ua : '/assets/images/' . $ua;
        }

        // 图集缩略：author_works 优先 + 商品图补齐，最多 4 张
        $thumbs = [];
        if (!empty($imgStrip) && is_array($imgStrip)) {
            $thumbs = array_slice($imgStrip, 0, 4);
        }

        // 元信息
        $meta = [];
        if (!empty($a['gender']) && $a['gender'] !== '保密') $meta[] = htmlspecialchars($a['gender']);
        if (!empty($a['city'])) $meta[] = '📍' . htmlspecialchars($a['city']);
        if (!empty($a['zodiac'])) $meta[] = '★' . htmlspecialchars($a['zodiac']);
        $metaStr = implode(' · ', $meta);

        $follower = intval($a['follower_count'] ?? 0);
        $like = intval($a['like_count'] ?? 0);
        $product = intval($a['product_count'] ?? 0);
        $productStr = $product > 0 ? $product : '—';

        // 关注按钮
        $loginUrl = '../auth/login.php?redirect=' . urlencode($url);
        if ($userId) {
            $btnClass = $isFollowed ? 'author-follow-btn followed' : 'author-follow-btn';
            $btnText = $isFollowed ? '已关注' : '+ 关注';
            $btn = '<button class="' . $btnClass . '" data-author-id="' . $id . '" '
                 . 'data-logged-in="1" data-login-url="' . htmlspecialchars($loginUrl) . '">'
                 . $btnText . '</button>';
        } else {
            $btn = '<a class="author-follow-btn" href="' . htmlspecialchars($loginUrl) . '">+ 关注</a>';
        }

        ob_start();
        ?>
        <div class="author-card">
            <a class="ac-avatar" href="<?= htmlspecialchars($url) ?>">
                <?php if ($avatar): ?>
                    <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= $nickname ?>" loading="lazy">
                <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:40px;"><i class="fas fa-palette"></i></div>
                <?php endif; ?>
            </a>
            <?php if (!empty($thumbs)): ?>
            <div class="ac-thumbs">
                <?php foreach ($thumbs as $t): ?>
                    <img src="../<?= htmlspecialchars($t) ?>" alt="" loading="lazy">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="ac-body">
                <div class="ac-name-row">
                    <a class="ac-name" href="<?= htmlspecialchars($url) ?>"><?= $nickname ?></a>
                    <?= $btn ?>
                </div>
                <?php if (!empty($a['style'])): ?>
                <div class="ac-meta">🎨 <?= htmlspecialchars($a['style']) ?><?= $metaStr ? ' · ' . $metaStr : '' ?></div>
                <?php elseif ($metaStr): ?>
                <div class="ac-meta"><?= $metaStr ?></div>
                <?php endif; ?>
                <?php if (!empty($a['bio'])): ?>
                <div class="ac-bio"><?= htmlspecialchars(SeoHelper::excerpt($a['bio'], 60)) ?></div>
                <?php endif; ?>
                <div class="ac-stats">
                    <span>❤ <b class="like-count"><?= $like ?></b></span>
                    <span>👥 <b class="follower-count"><?= Author::formatFollower($follower) ?></b></span>
                    <span>📦 <b><?= $productStr ?></b></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
