<?php
/**
 * 模特子站 · 模特个人页
 * 资料区 + 视频/主视觉 + 参演短剧 + 作品图集 + 日常照片 + 留言 + 相关模特 + 关联商品
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/card.php';
require_once APP_ROOT . '/classes/Message.php';

$modelId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($modelId <= 0) {
    http_response_code(404);
    include APP_ROOT . '/404.php';
    exit;
}

$modelInfo = $modelObj->getById($modelId);
if (!$modelInfo || $modelInfo['status'] !== 'active') {
    http_response_code(404);
    include APP_ROOT . '/404.php';
    exit;
}

$modelObj->recordView($modelId);

$canonicalUrl = SeoHelper::modelUrl($modelId, $modelInfo['nickname'] ?? '');
if (empty($_GET['page'])) {
    SeoHelper::redirectIfNotCanonical($canonicalUrl);
}

$page    = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12;

$userId   = $modelUserId;
$nickname = htmlspecialchars($modelInfo['nickname'] ?? '模特');

/* ---------- 关联商品 / 图集 ---------- */
$products      = $modelObj->getModelProducts($modelId, $page, $perPage);
$totalProducts = $modelObj->getProductCount($modelId);
$totalPages    = (int)ceil($totalProducts / $perPage);
$galleryImages = $modelObj->getModelProductImages($modelId, 100);

/* ---------- 参演短剧 ---------- */
$dramas = $dramaObj->getDramasByModel($modelId, true);

/* ---------- 关注 / 点赞状态 ---------- */
$isLiked = false;
$isFollowedModel = false;
if ($userId) {
    $isLiked = $modelObj->isLiked($modelId, $userId);
    $isFollowedModel = $modelObj->isFollowed($modelId, $userId);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like']) && $userId) {
    $modelObj->like($modelId, $userId);
    header('Location: ' . $canonicalUrl);
    exit;
}

/* ---------- 留言 ---------- */
$messageObj  = new Message($pdo);
$msgSuccess  = '';
$modelUserId = (int)($modelInfo['user_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_text']) && $userId && $modelUserId) {
    $msg = trim($_POST['message_text'] ?? '');
    if (mb_strlen($msg) < 1) {
        $msgSuccess = '<div class="m-alert err">请输入留言内容</div>';
    } elseif ($messageObj->send($userId, $modelUserId, $msg)) {
        header('Location: ' . $canonicalUrl . '#messages');
        exit;
    }
}

$messages = [];
$messageCount = 0;
if ($modelUserId && $userId) {
    $messages = $messageObj->getMessages($userId, $modelUserId, 1, 10);
    $messageCount = count($messageObj->getMessages($userId, $modelUserId, 1, 9999));
}

/* ---------- 主视觉 ---------- */
$modelAvatar = model_img($modelInfo, false);
$heroCover   = !empty($modelInfo['video_cover']) ? model_media($modelInfo['video_cover']) : $modelAvatar;
$videoKind   = Model::videoKind($modelInfo['video_url'] ?? '');
$shareUrl    = $canonicalUrl;

/* ---------- SEO ---------- */
$ogImage = $heroCover ? (preg_match('#^https?://#i', $heroCover) ? $heroCover : 'https://58.tl/' . ltrim($heroCover, '/')) : 'https://58.tl/assets/images/og-mall.jpg';

$personExtra = [
    'gender' => ($modelInfo['gender'] === '男') ? 'Male' : (($modelInfo['gender'] === '女') ? 'Female' : ''),
    'height' => $modelInfo['height'] ? ['@type' => 'QuantitativeValue', 'value' => (float)$modelInfo['height'], 'unitCode' => 'CMT'] : [],
    'weight' => $modelInfo['weight'] ? ['@type' => 'QuantitativeValue', 'value' => (float)$modelInfo['weight'], 'unitCode' => 'KGM'] : [],
];
if (!empty($dramas)) {
    $works = [];
    foreach ($dramas as $d) {
        $works[] = [
            '@type' => 'CreativeWork',
            'name'  => $d['title'],
            'url'   => SeoHelper::dramaUrl($d['id'], $d['title']),
        ];
    }
    $personExtra['performerIn'] = $works;
}
if (!empty($modelInfo['video_url'])) {
    $personExtra['video'] = [
        '@type'       => 'VideoObject',
        'name'        => $modelInfo['nickname'] . ' 视频',
        'contentUrl'  => $modelInfo['video_url'],
        'thumbnailUrl' => $ogImage,
    ];
}

$personJsonLd = SeoHelper::personSchema([
    'name'        => $modelInfo['nickname'] ?? '',
    'url'         => $canonicalUrl,
    'image'       => $ogImage,
    'jobTitle'    => '模特',
    'description' => "58 模特库模特{$modelInfo['nickname']}的专属展示页，展示其作品图集" . (!empty($dramas) ? '与参演的短剧作品' : '') . '。',
    'extra'       => $personExtra,
]);

$breadcrumbJsonLd = SeoHelper::breadcrumbList([
    ['name' => '58 模特库', 'url' => MODEL_BASE_URL . '/'],
    ['name' => '模特库',    'url' => MODEL_BASE_URL . '/list.php'],
    ['name' => $modelInfo['nickname'], 'url' => null],
]);

$site_config = model_site_config([
    'title'       => SeoHelper::title($modelInfo['nickname'] . ' - 58 模特库'),
    'description' => SeoHelper::description("模特{$modelInfo['nickname']}的专属展示页，查看 TA 的作品图集" . (!empty($dramas) ? '、参演短剧' : '') . '与关联商品。', '58 模特库'),
    'keywords'    => '58模特,' . $modelInfo['nickname'] . ',模特写真,模特主页' . (!empty($dramas) ? ',短剧' : ''),
    'canonical_url' => $canonicalUrl,
    'og_image'    => $ogImage,
    'og_type'     => 'profile',
    'og_title'    => $modelInfo['nickname'] . ' - 58 模特库',
    'extra_head'  => $personJsonLd . $breadcrumbJsonLd,
]);
require_once __DIR__ . '/includes/header.php';

// 日常照片
$dailyPhotos = [];
if (!empty($modelInfo['daily_photos'])) {
    $decoded = json_decode($modelInfo['daily_photos'], true);
    if (is_array($decoded)) $dailyPhotos = array_values(array_filter($decoded));
}
?>

<div class="model-wrap">
    <div class="m-crumb">
        <a href="/">首页</a><i class="fas fa-chevron-right"></i>
        <a href="/list.php">模特库</a><i class="fas fa-chevron-right"></i>
        <span><?= $nickname ?></span>
    </div>

    <!-- ============ 资料区 ============ -->
    <div class="m-profile-head">
        <div class="m-avatar-lg">
            <?php if ($modelAvatar): ?>
                <img src="<?= htmlspecialchars($modelAvatar) ?>" alt="<?= $nickname ?>">
            <?php else: ?>
                <i class="fas fa-user"></i>
            <?php endif; ?>
        </div>
        <div class="m-profile-info">
            <h1><?= $nickname ?></h1>
            <?php if (!empty($modelInfo['intro'])): ?>
                <p class="m-profile-intro"><?= htmlspecialchars($modelInfo['intro']) ?></p>
            <?php elseif (!empty($modelInfo['hobbies'])): ?>
                <p class="m-profile-intro"><?= nl2br(htmlspecialchars($modelInfo['hobbies'])) ?></p>
            <?php endif; ?>

            <div class="m-attrs">
                <?php if (!empty($modelInfo['username'])): ?><span><i class="fas fa-at"></i> <?= htmlspecialchars($modelInfo['username']) ?></span><?php endif; ?>
                <?php if (!empty($modelInfo['gender']) && $modelInfo['gender'] !== '保密'): ?><span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($modelInfo['gender']) ?></span><?php endif; ?>
                <?php if (!empty($modelInfo['city'])): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($modelInfo['city']) ?></span><?php endif; ?>
                <?php if (!empty($modelInfo['age'])): ?><span><i class="fas fa-birthday-cake"></i> <?= intval($modelInfo['age']) ?>岁</span><?php endif; ?>
                <?php if (!empty($modelInfo['height'])): ?><span><i class="fas fa-ruler-vertical"></i> <?= htmlspecialchars($modelInfo['height']) ?>cm</span><?php endif; ?>
                <?php if (!empty($modelInfo['weight'])): ?><span><i class="fas fa-weight-hanging"></i> <?= htmlspecialchars($modelInfo['weight']) ?>kg</span><?php endif; ?>
                <?php if (!empty($modelInfo['measurements'])): ?><span><i class="fas fa-ruler-combined"></i> <?= htmlspecialchars($modelInfo['measurements']) ?></span><?php endif; ?>
                <?php if (!empty($modelInfo['zodiac'])): ?><span><i class="fas fa-star"></i> <?= htmlspecialchars($modelInfo['zodiac']) ?></span><?php endif; ?>
            </div>

            <?php if (!empty($modelInfo['qq']) || !empty($modelInfo['weixin']) || !empty($modelInfo['weibo']) || !empty($modelInfo['xiaohongshu'])): ?>
            <div class="m-socials">
                <?php if (!empty($modelInfo['qq'])): ?><span class="s-qq"><i class="fab fa-qq"></i> <?= htmlspecialchars($modelInfo['qq']) ?></span><?php endif; ?>
                <?php if (!empty($modelInfo['weixin'])): ?><span class="s-wx"><i class="fab fa-weixin"></i> <?= htmlspecialchars($modelInfo['weixin']) ?></span><?php endif; ?>
                <?php if (!empty($modelInfo['weibo'])): ?><span class="s-wb"><i class="fab fa-weibo"></i> <?= htmlspecialchars($modelInfo['weibo']) ?></span><?php endif; ?>
                <?php if (!empty($modelInfo['xiaohongshu'])): ?><span class="s-xhs">📕 <?= htmlspecialchars($modelInfo['xiaohongshu']) ?></span><?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="m-profile-stats">
                <span class="st"><b class="follower-count"><?= Model::formatFollower(intval($modelInfo['follower_count'] ?? 0)) ?></b>粉丝</span>
                <span class="st"><b><?= intval($modelInfo['like_count'] ?? 0) ?></b>点赞</span>
                <span class="st"><b><?= count($dramas) ?></b>参演短剧</span>
                <span class="st"><b><?= intval($totalProducts) ?></b>作品</span>
                <span class="st"><b><?= number_format(intval($modelInfo['view_count'] ?? 0) + 1) ?></b>次访问</span>
            </div>

            <div class="m-profile-actions">
                <?php if ($userId && $userId != $modelUserId): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="like" value="1">
                        <button type="submit" class="m-btn m-btn-ghost m-btn-sm" style="<?= $isLiked ? 'background:var(--brand);color:#fff;border-color:var(--brand);' : '' ?>">
                            <?= $isLiked ? '❤️ 已赞' : '🤍 点赞' ?>
                        </button>
                    </form>
                    <button class="model-follow-btn <?= $isFollowedModel ? 'followed' : '' ?>"
                            data-model-id="<?= $modelId ?>" data-logged-in="1"
                            data-login-url="<?= htmlspecialchars(model_login_url($canonicalUrl)) ?>">
                        <?= $isFollowedModel ? '已关注' : '+ 关注' ?>
                    </button>
                <?php elseif (!$userId): ?>
                    <a class="m-btn m-btn-primary m-btn-sm" href="<?= htmlspecialchars(model_login_url($canonicalUrl)) ?>">+ 关注 TA</a>
                <?php endif; ?>
                <button class="m-btn m-btn-ghost m-btn-sm" onclick="shareModel()">📤 分享</button>
                <a class="m-btn m-btn-ghost m-btn-sm" target="_blank" rel="noopener nofollow"
                   href="https://service.weibo.com/share/share.php?url=<?= urlencode($shareUrl) ?>&title=<?= urlencode($modelInfo['nickname'] . ' - 58 模特库') ?>">微博</a>
            </div>
        </div>
    </div>

    <!-- ============ 视频 / 主视觉 ============ -->
    <?php if ($videoKind === 'file'): ?>
    <div class="m-stage">
        <span class="m-stage-tag">▶ 视频出镜</span>
        <video id="stageVideo" src="<?= htmlspecialchars($modelInfo['video_url']) ?>"
               poster="<?= htmlspecialchars($heroCover) ?>" controls muted loop playsinline preload="metadata"></video>
    </div>
    <?php elseif ($videoKind === 'embed'): ?>
    <div class="m-stage">
        <span class="m-stage-tag">▶ 视频出镜</span>
        <iframe src="<?= htmlspecialchars($modelInfo['video_url']) ?>" loading="lazy"
                allow="autoplay; fullscreen" allowfullscreen></iframe>
    </div>
    <?php elseif ($heroCover): ?>
    <div class="m-stage">
        <img src="<?= htmlspecialchars($heroCover) ?>" alt="<?= $nickname ?>">
    </div>
    <?php endif; ?>

    <!-- ============ 参演短剧 ============ -->
    <?php if (!empty($dramas)): ?>
    <section class="m-sec">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>参演短剧</h2>
                <p class="sub">共参演 <?= count($dramas) ?> 部作品</p>
            </div>
            <a class="m-more" href="/dramas.php">全部短剧 <i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="m-cast-grid">
            <?php foreach ($dramas as $d): ?>
            <a class="m-cast-card" href="<?= htmlspecialchars(SeoHelper::dramaUrl($d['id'], $d['title'])) ?>">
                <div class="cc-cover">
                    <?php if (!empty($d['cover'])): ?>
                        <img src="<?= htmlspecialchars(model_media($d['cover'])) ?>" alt="<?= htmlspecialchars($d['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#d6d2ca;font-size:32px;"><i class="fas fa-film"></i></div>
                    <?php endif; ?>
                    <?php if (!empty($d['episodes'])): ?>
                        <span class="dc-ep"><?= intval($d['episodes']) ?> 集</span>
                    <?php endif; ?>
                </div>
                <div class="cc-body">
                    <div class="cc-title"><?= htmlspecialchars($d['title']) ?></div>
                    <div class="cc-role">
                        <?php if (!empty($d['role_name'])): ?>饰 <?= htmlspecialchars($d['role_name']) ?><?php else: ?>参演<?php endif; ?>
                        <?php if (!empty($d['is_lead'])): ?><span class="m-lead-tag">主演</span><?php endif; ?>
                    </div>
                    <?php if (!empty($d['tags_arr'])): ?>
                    <div style="margin-top:8px;">
                        <?php foreach (array_slice($d['tags_arr'], 0, 3) as $t): ?><span class="m-tagchip"><?= htmlspecialchars($t) ?></span><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ 作品图集 ============ -->
    <?php if (!empty($galleryImages)): $totalGallery = count($galleryImages); ?>
    <section class="m-sec">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>作品图集</h2>
                <p class="sub">共 <?= $totalGallery ?> 张，点击查看大图</p>
            </div>
        </div>
        <div class="m-gallery" id="gallery-grid">
            <?php foreach ($galleryImages as $i => $img): ?>
            <div class="gi gallery-item" data-src="<?= htmlspecialchars(model_media($img)) ?>"
                 style="<?= $i >= 12 ? 'display:none' : '' ?>">
                <img src="<?= htmlspecialchars(model_media($img)) ?>" alt="作品图片" loading="lazy">
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($totalGallery > 12): ?>
        <div style="text-align:center;margin-top:16px;">
            <button class="m-load-more" id="gallery-load-btn" style="margin:0 auto;">加载更多（剩余 <span id="gallery-remain"><?= $totalGallery - 12 ?></span> 张）</button>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ============ 日常照片 ============ -->
    <?php if (!empty($dailyPhotos)): ?>
    <section class="m-sec">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>日常照片</h2>
                <p class="sub">镜头之外的样子</p>
            </div>
        </div>
        <div class="m-gallery">
            <?php foreach ($dailyPhotos as $dp): ?>
            <div class="gi gallery-item" data-src="<?= htmlspecialchars(model_media($dp)) ?>">
                <img src="<?= htmlspecialchars(model_media($dp)) ?>" alt="日常照片" loading="lazy">
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ 留言 ============ -->
    <section class="m-sec" id="messages">
        <div class="m-sec-head">
            <h2><span class="bar"></span>留言 <span style="font-size:14px;color:var(--muted);font-weight:500;">（<?= $messageCount ?> 条）</span></h2>
        </div>
        <?php if ($userId): ?>
        <form method="post" class="m-msg-form">
            <input type="text" name="message_text" maxlength="500" placeholder="给 <?= $nickname ?> 留言…">
            <button type="submit">发送</button>
        </form>
        <?php else: ?>
        <p style="color:var(--muted);margin-bottom:16px;">请 <a href="<?= htmlspecialchars(model_login_url($canonicalUrl . '#messages')) ?>" style="color:var(--brand-ink);">登录</a> 后留言</p>
        <?php endif; ?>
        <?= $msgSuccess ?>

        <?php if (empty($messages)): ?>
            <div class="m-empty"><i class="fas fa-comment-dots"></i>暂无留言，快来抢沙发~</div>
        <?php else: ?>
            <?php foreach ($messages as $msg): $msgAvatar = User::avatarUrl($msg['user_avatar'] ?? ''); ?>
            <div class="m-msg">
                <div class="av">
                    <?php if ($msgAvatar): ?><img src="<?= htmlspecialchars($msgAvatar) ?>" alt="" onerror="this.src='https://58.tl/assets/images/default.jpg'"><?php else: ?><i class="fas fa-user"></i><?php endif; ?>
                </div>
                <div class="bd">
                    <div class="hd">
                        <strong><?= htmlspecialchars($msg['username'] ?? '匿名') ?></strong>
                        <span><?= date('m-d H:i', strtotime($msg['created_at'])) ?></span>
                    </div>
                    <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <!-- ============ 关联商品 ============ -->
    <?php if ($totalProducts > 0): ?>
    <section class="m-sec">
        <div class="m-sec-head">
            <h2><span class="bar"></span>TA 推荐</h2>
            <p class="sub">共 <?= intval($totalProducts) ?> 件商品</p>
        </div>
        <div class="m-cast-grid">
            <?php foreach ($products as $p): $pUrl = SeoHelper::productUrl($p['id'], $p['name']); ?>
            <a class="m-cast-card" href="<?= $pUrl ?>">
                <div class="cc-cover" style="aspect-ratio:1;">
                    <?php if (!empty($p['main_image'])): ?>
                        <img src="<?= htmlspecialchars(model_media($p['main_image'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#d6d2ca;font-size:30px;"><i class="fas fa-box"></i></div>
                    <?php endif; ?>
                </div>
                <div class="cc-body">
                    <div class="cc-title"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="cc-role" style="color:var(--brand-ink);font-weight:700;">Ⓟ <?= number_format($p['price_bct'] ?? 0, 0) ?> 人气值</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="m-pager">
            <?php if ($page > 1): ?><a href="?id=<?= $modelId ?>&page=<?= $page - 1 ?>">上一页</a><?php endif; ?>
            <span class="cur"><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?><a href="?id=<?= $modelId ?>&page=<?= $page + 1 ?>">下一页</a><?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- ============ 相关模特 ============ -->
    <?php
    $relatedModels = $modelObj->getRelated($modelId, 5);
    if (!empty($relatedModels)):
        $relIds = array_column($relatedModels, 'id');
        $relFollowed = [];
        if ($userId && $relIds) {
            $ph = implode(',', array_map('intval', $relIds));
            $stmt = $pdo->prepare("SELECT model_id FROM model_follows WHERE user_id = ? AND model_id IN ($ph)");
            $stmt->execute([$userId]);
            $relFollowed = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
        }
    ?>
    <section class="m-sec">
        <div class="m-sec-head">
            <h2><span class="bar"></span>相关模特</h2>
            <a class="m-more" href="/list.php">更多模特 <i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="m-grid">
            <?php foreach ($relatedModels as $rm): ?>
                <?= renderModelCard($rm, [], isset($relFollowed[$rm['id']]), $userId) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>

<div class="m-lightbox" id="lightbox">
    <button class="m-lb-close" onclick="closeLightbox()">&times;</button>
    <button class="m-lb-nav m-lb-prev" onclick="lbStep(-1)">‹</button>
    <img id="lightbox-img" src="" alt="">
    <button class="m-lb-nav m-lb-next" onclick="lbStep(1)">›</button>
    <div class="m-lb-count" id="lightbox-count"></div>
</div>

<script src="<?= htmlspecialchars(model_asset('assets/js/model.js')) ?>"></script>
<script>
function shareModel() {
    var url = <?= json_encode($shareUrl) ?>;
    var title = <?= json_encode($modelInfo['nickname'] . ' - 58 模特库') ?>;
    var text = <?= json_encode('快来看看' . $modelInfo['nickname'] . '的主页！') ?>;
    if (navigator.share) {
        navigator.share({ title: title, text: text, url: url }).catch(function () {});
    } else {
        var input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        alert('链接已复制到剪贴板');
    }
}

(function () {
    mInitLightbox('.gallery-item');

    // 图集分批展示
    var btn = document.getElementById('gallery-load-btn');
    if (btn) {
        var perPage = 12, shown = 12;
        btn.addEventListener('click', function () {
            var items = document.querySelectorAll('#gallery-grid .gallery-item');
            var end = Math.min(shown + perPage, items.length);
            for (var i = shown; i < end; i++) items[i].style.display = '';
            shown = end;
            var remain = items.length - shown;
            document.getElementById('gallery-remain').textContent = remain;
            if (remain <= 0) { btn.textContent = '已加载全部'; btn.disabled = true; }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
