<?php
/**
 * 模特子站首页（model.58.tl）
 * 结构：Hero 主推 → 本期主推模特 → 正在热播短剧 → 发现模特 → 新人加入引导
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/card.php';

$userId = $modelUserId;

// ---------- 数据 ----------
$heroModel  = $modelObj->getHeroModel();
$featured   = $modelObj->getFeaturedModels(8);
$topDramas  = $dramaObj->getTopDramas(10);
$facets     = $modelObj->getFacets();

// 首页发现区：首屏 15 个（桌面 5 列 × 3 行，满行不留空位）
$perPage = 15;
$filters = ['gender' => '', 'zodiac' => '', 'city' => '', 'q' => '', 'sort' => 'follower'];
$result  = $modelObj->getFilteredList($filters, 1, $perPage);
$list    = $result['list'];
$ids     = array_column($list, 'id');
$strips  = $modelObj->getModelImageStrips($ids, 4);

$followedIds = [];
if ($userId && $ids) {
    $ph = implode(',', array_map('intval', $ids));
    $stmt = $pdo->prepare("SELECT model_id FROM model_follows WHERE user_id = ? AND model_id IN ($ph)");
    $stmt->execute([$userId]);
    $followedIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
}

// ---------- SEO ----------
$site_config = model_site_config([
    'title'       => '58 模特库 - 发现好看的模特与短剧 | model.58.tl',
    'description' => '58 模特库汇集人气模特个人主页、作品图集与短视频，并可发现模特参演的短剧作品，支持关注与在线申请加入模特库。',
    'keywords'    => '58模特,模特库,模特招募,短剧,红果短剧,模特申请,模特写真',
    'canonical_url' => MODEL_BASE_URL . '/',
    'og_title'    => '58 模特库 - 发现好看的模特与短剧',
    'schema_search' => MODEL_BASE_URL . '/list.php?q={search_term_string}',
]);
require_once __DIR__ . '/includes/header.php';

// ---------- Hero 渲染 ----------
$heroAvatar = $heroModel ? model_img($heroModel, true) : '';
$heroKind   = $heroModel ? Model::videoKind($heroModel['video_url'] ?? '') : '';
$heroUrl    = $heroModel ? SeoHelper::modelUrl($heroModel['id'], $heroModel['nickname'] ?? '') : '';
$heroFans   = $heroModel ? Model::formatFollower(intval($heroModel['follower_count'] ?? 0)) : '0';

// Hero 大图：视频封面 → 作品图集首图 → 日常照片首图 → 头像
// （Hero 是全屏展示位，需要足够大的视觉主体，不能只有小头像）
$heroCover = '';
if ($heroModel) {
    if (!empty($heroModel['video_cover'])) {
        $heroCover = model_media($heroModel['video_cover']);
    }
    if ($heroCover === '') {
        $heroStrips = $modelObj->getModelImageStrips([intval($heroModel['id'])], 1);
        if (!empty($heroStrips[$heroModel['id']][0])) {
            $heroCover = model_media($heroStrips[$heroModel['id']][0]);
        }
    }
    if ($heroCover === '' && !empty($heroModel['daily_photos'])) {
        $daily = json_decode($heroModel['daily_photos'], true);
        if (is_array($daily) && !empty($daily[0])) {
            $heroCover = model_media($daily[0]);
        }
    }
    if ($heroCover === '' && $heroAvatar !== '') {
        $heroCover = $heroAvatar;
    }
}
?>

<?php if ($heroModel): ?>
<!-- ============ Hero ============ -->
<section class="m-hero" id="hero">
    <div class="m-hero-media" id="heroMedia">
        <?php if ($heroKind === 'file'): ?>
            <video id="heroVideo" src="<?= htmlspecialchars($heroModel['video_url']) ?>"
                   poster="<?= htmlspecialchars($heroCover) ?>"
                   muted loop playsinline preload="metadata"></video>
        <?php elseif ($heroKind === 'embed'): ?>
            <iframe src="<?= htmlspecialchars($heroModel['video_url']) ?>" loading="lazy"
                    allow="autoplay; fullscreen" allowfullscreen></iframe>
        <?php elseif ($heroCover): ?>
            <img src="<?= htmlspecialchars($heroCover) ?>" alt="<?= htmlspecialchars($heroModel['nickname'] ?? '模特') ?>">
        <?php endif; ?>
    </div>
    <div class="m-hero-shade"></div>

    <div class="m-hero-inner">
        <span class="m-hero-badge">✨ 本期主推 · <?= $heroKind ? '视频出镜' : '人气模特' ?></span>
        <h1 class="m-hero-name"><?= htmlspecialchars($heroModel['nickname'] ?? '模特') ?></h1>
        <?php if (!empty($heroModel['intro'])): ?>
            <p class="m-hero-intro"><?= htmlspecialchars($heroModel['intro']) ?></p>
        <?php elseif (!empty($heroModel['hobbies'])): ?>
            <p class="m-hero-intro"><?= htmlspecialchars(mb_substr($heroModel['hobbies'], 0, 80)) ?></p>
        <?php endif; ?>
        <div class="m-hero-meta">
            <?php if (!empty($heroModel['city'])): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($heroModel['city']) ?></span><?php endif; ?>
            <?php if (!empty($heroModel['gender']) && $heroModel['gender'] !== '保密'): ?><span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($heroModel['gender']) ?></span><?php endif; ?>
            <?php if (!empty($heroModel['height'])): ?><span><i class="fas fa-ruler-vertical"></i> <?= htmlspecialchars($heroModel['height']) ?>cm</span><?php endif; ?>
            <span><i class="fas fa-users"></i> <b><?= $heroFans ?></b> 粉丝</span>
            <?php if (intval($heroModel['drama_count'] ?? 0) > 0): ?>
                <span><i class="fas fa-film"></i> 参演 <b><?= intval($heroModel['drama_count']) ?></b> 部短剧</span>
            <?php endif; ?>
        </div>
        <div class="m-hero-cta">
            <a class="m-btn m-btn-primary" href="<?= htmlspecialchars($heroUrl) ?>">查看 TA 的主页 <i class="fas fa-arrow-right"></i></a>
            <?php if (intval($heroModel['drama_count'] ?? 0) > 0): ?>
                <a class="m-btn m-btn-ghost" href="#dramas" style="background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.4);color:#fff;">🎬 TA 的短剧</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($heroKind === 'file'): ?>
    <button class="m-hero-sound" id="heroSound" aria-label="切换声音"><i class="fas fa-volume-mute"></i></button>
    <?php endif; ?>
</section>
<?php else: ?>
<section class="m-hero-empty">
    <h2>58 模特库</h2>
    <p>发现好看的模特，遇见正在热播的短剧</p>
    <a class="m-btn m-btn-primary" href="/list.php">浏览模特库 <i class="fas fa-arrow-right"></i></a>
</section>
<?php endif; ?>

<div class="model-wrap">

    <?php if (!empty($featured)): ?>
    <!-- ============ 本期主推模特 ============ -->
    <section class="m-sec">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>本期主推模特</h2>
                <p class="sub">视频出镜优先，其次人气最高</p>
            </div>
            <a class="m-more" href="/list.php">查看全部 <i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="m-rail">
            <?php foreach ($featured as $m): renderRailModelCard($m); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($topDramas)): ?>
    <!-- ============ 正在热播短剧 ============ -->
    <section class="m-sec" id="dramas">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>正在热播短剧</h2>
                <p class="sub">模特参演的短剧作品，点开看阵容</p>
            </div>
            <a class="m-more" href="/dramas.php">全部短剧 <i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="m-rail">
            <?php foreach ($topDramas as $d): renderDramaCard($d, true); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ 发现模特 ============ -->
    <section class="m-sec">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>发现模特</h2>
                <p class="sub">按城市、星座、性别找到你喜欢的类型</p>
            </div>
            <a class="m-more" href="/list.php">高级筛选 <i class="fas fa-chevron-right"></i></a>
        </div>

        <div class="m-filters">
            <form method="get" action="/list.php" class="filter-row" style="margin-bottom:12px;">
                <span class="filter-label">搜索</span>
                <input type="text" name="q" class="search-input" placeholder="输入模特昵称…" maxlength="50">
                <button type="submit" class="search-btn">搜索</button>
            </form>
            <div class="filter-row">
                <span class="filter-label">性别</span>
                <a class="gender-opt" href="/list.php?gender=女">女生</a>
                <a class="gender-opt" href="/list.php?gender=男">男生</a>
            </div>
            <?php if (!empty($facets['cities'])): ?>
            <div class="filter-row">
                <span class="filter-label">城市</span>
                <?php foreach (array_slice($facets['cities'], 0, 12) as $c): ?>
                    <a class="chip" href="/list.php?city=<?= urlencode($c['city']) ?>"><?= htmlspecialchars($c['city']) ?> <small style="opacity:.6"><?= intval($c['c']) ?></small></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($facets['zodiacs'])): ?>
            <div class="filter-row">
                <span class="filter-label">星座</span>
                <?php foreach (array_slice($facets['zodiacs'], 0, 12) as $z): ?>
                    <a class="chip" href="/list.php?zodiac=<?= urlencode($z['zodiac']) ?>"><?= htmlspecialchars($z['zodiac']) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="m-grid" id="model-grid">
            <?php if (empty($list)): ?>
                <div class="m-empty" style="grid-column:1/-1;"><i class="fas fa-user-slash"></i>暂时还没有模特入驻，欢迎成为第一位</div>
            <?php else: ?>
                <?php foreach ($list as $m): ?>
                    <?= renderModelCard($m, $strips[$m['id']] ?? [], isset($followedIds[$m['id']]), $userId) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($result['pages'] > 1): ?>
        <button class="m-load-more" id="load-more" data-total="<?= intval($result['total']) ?>">加载更多</button>
        <?php endif; ?>
    </section>

    <!-- ============ 新人加入引导 ============ -->
    <section class="m-sec">
        <div class="m-join">
            <h2>想成为 58 模特库的一员？</h2>
            <p class="lead">无论你是专业模特，还是想尝试出镜的新人，我们都欢迎。三步即可开始。</p>
            <div class="m-steps">
                <div class="m-step">
                    <div class="no">1</div>
                    <h4>提交资料</h4>
                    <p>填写昵称、身高体重三围等基本资料，上传你的照片。</p>
                </div>
                <div class="m-step">
                    <div class="no">2</div>
                    <h4>审核沟通</h4>
                    <p>工作人员会与你联系确认信息，沟通拍摄与合作方向。</p>
                </div>
                <div class="m-step">
                    <div class="no">3</div>
                    <h4>上线展示</h4>
                    <p>审核通过后拥有专属主页，作品与短剧都会在这里展示。</p>
                </div>
            </div>
            <div class="m-join-cta">
                <a class="m-btn m-btn-primary" href="/apply.php">立即申请加入 <i class="fas fa-arrow-right"></i></a>
                <a class="m-back" href="/rankings.php" style="font-size:14px;">先看看排行榜 →</a>
            </div>
        </div>
    </section>

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
(function () {
    // Hero 视频：静音自动播放 + 声音开关
    var v = document.getElementById('heroVideo');
    var sb = document.getElementById('heroSound');
    if (v && sb) {
        v.play().catch(function () {});
        sb.addEventListener('click', function () {
            v.muted = !v.muted;
            sb.innerHTML = v.muted ? '<i class="fas fa-volume-mute"></i>' : '<i class="fas fa-volume-up"></i>';
            if (!v.muted) v.play().catch(function () {});
        });
    }

    // 发现区：首屏自动补齐整行 + 加载更多（按当前列数补一行）
    var discoverOpts = {
        buttonId: 'load-more',
        gridId:   'model-grid',
        url:      '/list.php',
        args:     { sort: 'follower' },
        onLoaded: function (grid) { bindFollowButtons(grid); }
    };
    autoFillFirstRow(discoverOpts);
    bindLoadMore(discoverOpts);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
