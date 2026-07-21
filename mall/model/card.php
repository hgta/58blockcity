<?php
// 模特卡片渲染（被 list.php / following.php 复用）
if (!function_exists('renderModelCard')) {
    function renderModelCard($m, $imgStrip = [], $isFollowed = false, $userId = 0) {
        $id = intval($m['id']);
        $nickname = htmlspecialchars($m['nickname'] ?? '模特');
        $url = SeoHelper::modelUrl($id, $m['nickname'] ?? '');

        // 头像
        $avatar = '';
        if (!empty($m['avatar'])) {
            $avatar = '../' . $m['avatar'];
        } elseif (!empty($m['user_avatar'])) {
            $ua = $m['user_avatar'];
            $avatar = (strpos($ua, '/') !== false) ? '../' . $ua : '/assets/images/' . $ua;
        }

        // 图集缩略：商品图 + 日常照片，最多 4 张
        $thumbs = [];
        if (!empty($imgStrip) && is_array($imgStrip)) {
            $thumbs = array_slice($imgStrip, 0, 4);
        }
        if (count($thumbs) < 4 && !empty($m['daily_photos'])) {
            $daily = json_decode($m['daily_photos'], true);
            if (is_array($daily)) {
                foreach ($daily as $dp) {
                    if (count($thumbs) >= 4) break;
                    $thumbs[] = $dp;
                }
            }
        }

        // 元信息
        $meta = [];
        if (!empty($m['gender']) && $m['gender'] !== '保密') $meta[] = htmlspecialchars($m['gender']);
        if (!empty($m['city'])) $meta[] = '📍' . htmlspecialchars($m['city']);
        if (!empty($m['zodiac'])) $meta[] = '★' . htmlspecialchars($m['zodiac']);
        $metaStr = implode(' · ', $meta);

        $follower = intval($m['follower_count'] ?? 0);
        $like = intval($m['like_count'] ?? 0);
        $product = intval($m['product_count'] ?? 0);
        $productStr = $product > 0 ? $product : '—';

        // 关注按钮
        $loginUrl = '../auth/login.php?redirect=' . urlencode($url);
        if ($userId) {
            $btnClass = $isFollowed ? 'model-follow-btn followed' : 'model-follow-btn';
            $btnText = $isFollowed ? '已关注' : '+ 关注';
            $btn = '<button class="' . $btnClass . '" data-model-id="' . $id . '" '
                 . 'data-logged-in="1" data-login-url="' . htmlspecialchars($loginUrl) . '">'
                 . $btnText . '</button>';
        } else {
            $btn = '<a class="model-follow-btn" href="' . htmlspecialchars($loginUrl) . '">+ 关注</a>';
        }

        ob_start();
        ?>
        <div class="model-card">
            <a class="mc-avatar" href="<?= htmlspecialchars($url) ?>">
                <?php if ($avatar): ?>
                    <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= $nickname ?>" loading="lazy">
                <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:40px;"><i class="fas fa-user"></i></div>
                <?php endif; ?>
            </a>
            <?php if (!empty($thumbs)): ?>
            <div class="mc-thumbs">
                <?php foreach ($thumbs as $t): ?>
                    <img src="../<?= htmlspecialchars($t) ?>" alt="" loading="lazy">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="mc-body">
                <a class="mc-name" href="<?= htmlspecialchars($url) ?>"><?= $nickname ?></a>
                <?php if ($metaStr): ?><div class="mc-meta"><?= $metaStr ?></div><?php endif; ?>
                <div class="mc-stats">
                    <span>❤ <b class="like-count"><?= $like ?></b></span>
                    <span>👥 <b class="follower-count"><?= Model::formatFollower($follower) ?></b></span>
                    <span>📦 <b><?= $productStr ?></b></span>
                </div>
                <?= $btn ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
