<?php
/**
 * 模特卡片渲染（列表页 / 首页 / 相关模特共用）
 */

if (!function_exists('renderModelCard')) {
    /**
     * @param array $m            模特记录（需含 id/nickname/avatar/follower_count/like_count/drama_count）
     * @param array $imgStrip     作品图集缩略（最多 4 张），路径为相对仓库根
     * @param bool  $isFollowed   当前用户是否已关注
     * @param int   $userId       当前用户 ID（0 = 未登录）
     * @param array $credit       可选。短剧参演信息 ['role_name'=>, 'is_lead'=>]，
     *                            有值时在卡片上展示「饰 XX / 主演」徽标（短剧详情页用）
     */
    function renderModelCard($m, $imgStrip = [], $isFollowed = false, $userId = 0, $credit = null)
    {
        $id       = intval($m['id']);
        $nickname = htmlspecialchars($m['nickname'] ?? '模特');
        $url      = SeoHelper::modelUrl($id, $m['nickname'] ?? '');
        $avatar   = model_img($m, false);

        // 图集缩略：商品图 / 日常照片，最多 4 张
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
        if (!empty($m['city']))   $meta[] = '📍' . htmlspecialchars($m['city']);
        if (!empty($m['zodiac'])) $meta[] = '★' . htmlspecialchars($m['zodiac']);
        $metaStr = implode(' · ', $meta);

        $follower = intval($m['follower_count'] ?? 0);
        $like     = intval($m['like_count'] ?? 0);
        $product  = intval($m['product_count'] ?? 0);
        $dramaCnt = intval($m['drama_count'] ?? 0);

        // 关注按钮
        $loginUrl = htmlspecialchars(model_login_url($url));
        if ($userId) {
            $btnClass = $isFollowed ? 'model-follow-btn followed' : 'model-follow-btn';
            $btnText  = $isFollowed ? '已关注' : '+ 关注';
            $btn = '<button class="' . $btnClass . '" data-model-id="' . $id . '" '
                 . 'data-logged-in="1" data-login-url="' . $loginUrl . '">' . $btnText . '</button>';
        } else {
            $btn = '<a class="model-follow-btn" href="' . $loginUrl . '">+ 关注</a>';
        }

        $hasVideo = !empty($m['video_url']);

        ob_start();
        ?>
        <div class="model-card">
            <a class="mc-avatar" href="<?= htmlspecialchars($url) ?>">
                <?php if ($hasVideo): ?><span class="m-rail-tag">▶ 视频</span><?php endif; ?>
                <?php if ($avatar): ?>
                    <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= $nickname ?>" loading="lazy">
                <?php else: ?>
                    <div class="ph"><i class="fas fa-user"></i></div>
                <?php endif; ?>
            </a>
            <?php if (!empty($thumbs)): ?>
            <div class="mc-thumbs">
                <?php foreach ($thumbs as $t): ?>
                    <img src="<?= htmlspecialchars(model_media($t)) ?>" alt="" loading="lazy">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="mc-body">
                <div class="mc-name-row">
                    <a class="mc-name" href="<?= htmlspecialchars($url) ?>"><?= $nickname ?></a>
                    <?= $btn ?>
                </div>
                <?php if ($metaStr): ?><div class="mc-meta"><?= $metaStr ?></div><?php endif; ?>
                <?php if (is_array($credit) && (!empty($credit['role_name']) || !empty($credit['is_lead']))): ?>
                    <div class="mc-credit">
                        <?php if (!empty($credit['role_name'])): ?>
                            <span class="mc-role">饰 <?= htmlspecialchars($credit['role_name']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($credit['is_lead'])): ?>
                            <span class="mc-lead">主演</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($dramaCnt > 0): ?>
                    <span class="mc-drama-tag"><i class="fas fa-film"></i> 参演 <?= $dramaCnt ?> 部短剧</span>
                <?php endif; ?>
                <div class="mc-stats">
                    <span>❤ <b class="like-count"><?= $like ?></b></span>
                    <span>👥 <b class="follower-count"><?= Model::formatFollower($follower) ?></b></span>
                    <span>📦 <b><?= $product > 0 ? $product : '—' ?></b></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('renderRailModelCard')) {
    /**
     * 首页横向滑轨模特卡（竖版大图）
     */
    function renderRailModelCard($m)
    {
        $id       = intval($m['id']);
        $nickname = htmlspecialchars($m['nickname'] ?? '模特');
        $url      = SeoHelper::modelUrl($id, $m['nickname'] ?? '');
        $avatar   = model_img($m, true);
        $hasVideo = !empty($m['video_url']);
        $follower = intval($m['follower_count'] ?? 0);
        ?>
        <a class="m-rail-card" href="<?= htmlspecialchars($url) ?>">
            <?php if ($hasVideo): ?><span class="m-rail-tag">▶ 视频</span><?php endif; ?>
            <?php if (intval($m['drama_count'] ?? 0) > 0): ?><span class="m-rail-tag gold" style="left:auto;right:10px;">🎬 <?= intval($m['drama_count']) ?></span><?php endif; ?>
            <div class="rc-img">
                <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= $nickname ?>" loading="lazy">
            </div>
            <div class="rc-body">
                <div class="rc-name"><?= $nickname ?></div>
                <div class="rc-meta">
                    <?php if (!empty($m['city'])): ?><span>📍<?= htmlspecialchars($m['city']) ?></span><?php endif; ?>
                    <span>👥 <b><?= Model::formatFollower($follower) ?></b></span>
                </div>
            </div>
        </a>
        <?php
    }
}

if (!function_exists('renderCompactModelCard')) {
    /**
     * 紧凑横卡（「相关模特」等次要推荐位用）
     *
     * 与 renderModelCard 的区别：无大图、无缩略图墙，
     * 圆形小头像 + 单行关键信息 + 可选关注按钮，
     * 整卡高度约 100px，避免在页面底部抢占主视觉。
     *
     * @param array $m          模特记录
     * @param bool  $isFollowed 当前用户是否已关注
     * @param int   $userId     当前用户 ID（0 = 未登录）
     */
    function renderCompactModelCard($m, $isFollowed = false, $userId = 0)
    {
        $id       = intval($m['id']);
        $nickname = htmlspecialchars($m['nickname'] ?? '模特');
        $url      = SeoHelper::modelUrl($id, $m['nickname'] ?? '');
        $avatar   = model_img($m, false);
        $follower = intval($m['follower_count'] ?? 0);
        $like     = intval($m['like_count'] ?? 0);
        $dramaCnt = intval($m['drama_count'] ?? 0);

        // 元信息片段
        $bits = [];
        if (!empty($m['gender']) && $m['gender'] !== '保密') $bits[] = htmlspecialchars($m['gender']);
        if (!empty($m['city']))   $bits[] = htmlspecialchars($m['city']);
        if (!empty($m['height'])) $bits[] = intval($m['height']) . 'cm';

        // 关注按钮
        $loginUrl = htmlspecialchars(model_login_url($url));
        if ($userId) {
            $btnClass = $isFollowed ? 'model-follow-btn followed' : 'model-follow-btn';
            $btnText  = $isFollowed ? '已关注' : '+ 关注';
            $btn = '<button class="' . $btnClass . '" data-model-id="' . $id . '" '
                 . 'data-logged-in="1" data-login-url="' . $loginUrl . '">' . $btnText . '</button>';
        } else {
            $btn = '<a class="model-follow-btn" href="' . $loginUrl . '">+ 关注</a>';
        }
        ?>
        <div class="m-cmodel">
            <a class="cm-av" href="<?= htmlspecialchars($url) ?>">
                <?php if ($avatar): ?>
                    <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= $nickname ?>" loading="lazy">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
                <?php if (!empty($m['video_url'])): ?><span class="cm-video">▶</span><?php endif; ?>
            </a>
            <div class="cm-info">
                <a class="cm-name" href="<?= htmlspecialchars($url) ?>"><?= $nickname ?></a>
                <div class="cm-meta">
                    <?php if ($bits): ?><span><?= implode(' · ', $bits) ?></span><?php endif; ?>
                    <span class="cm-stat">👥 <b class="follower-count"><?= Model::formatFollower($follower) ?></b></span>
                    <span class="cm-stat">❤ <b class="like-count"><?= $like ?></b></span>
                </div>
                <?php if ($dramaCnt > 0): ?>
                    <span class="cm-drama"><i class="fas fa-film"></i> 参演 <?= $dramaCnt ?> 部短剧</span>
                <?php endif; ?>
            </div>
            <div class="cm-action"><?= $btn ?></div>
        </div>
        <?php
    }
}

if (!function_exists('renderDramaCard')) {
    /**
     * 短剧卡片（列表 / 滑轨共用）
     * @param array $d    短剧记录（含 id/title/cover/episodes/tags_arr/models/model_count）
     * @param bool  $rail true = 滑轨卡（固定宽度）
     */
    function renderDramaCard($d, $rail = true)
    {
        $id    = intval($d['id']);
        $title = htmlspecialchars($d['title'] ?? '未命名短剧');
        $url   = SeoHelper::dramaUrl($id, $d['title'] ?? '');
        $cls   = $rail ? 'm-drama-card' : 'm-cast-card';
        $coverCls = $rail ? 'dc-cover' : 'cc-cover';
        $bodyCls  = $rail ? 'dc-body' : 'cc-body';
        $titleCls = $rail ? 'dc-title' : 'cc-title';
        $episodes = intval($d['episodes'] ?? 0);
        $models   = $d['models'] ?? [];
        $count    = isset($d['model_count']) ? intval($d['model_count']) : count($models);
        ?>
        <a class="<?= $cls ?>" href="<?= htmlspecialchars($url) ?>">
            <div class="<?= $coverCls ?>">
                <?php if (!empty($d['cover'])): ?>
                    <img src="<?= htmlspecialchars(model_media($d['cover'])) ?>" alt="<?= $title ?>" loading="lazy">
                <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#d6d2ca;font-size:32px;"><i class="fas fa-film"></i></div>
                <?php endif; ?>
                <?php if ($episodes > 0): ?><span class="dc-ep"><?= $episodes ?> 集</span><?php endif; ?>
            </div>
            <div class="<?= $bodyCls ?>">
                <div class="<?= $titleCls ?>"><?= $title ?></div>
                <?php if ($rail && $count > 0): ?>
                <div class="m-dc-cast">
                    <div class="avs">
                        <?php foreach (array_slice($models, 0, 3) as $mm): ?>
                            <?php $av = model_img($mm, false); ?>
                            <?php if ($av): ?>
                                <img src="<?= htmlspecialchars($av) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="ph"></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <span class="cnt"><?= $count ?> 位演职人员</span>
                </div>
                <?php elseif (!$rail): ?>
                    <?php if (!empty($d['role_name'])): ?>
                    <div class="cc-role">
                        饰 <?= htmlspecialchars($d['role_name']) ?>
                        <?php if (!empty($d['is_lead'])): ?><span class="m-lead-tag">主演</span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </a>
        <?php
    }
}
