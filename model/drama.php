<?php
/**
 * 模特子站 · 短剧详情页
 * 聚合该剧全部参演模特（本子站「模特本位」的差异化入口）
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/card.php';

$dramaId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($dramaId <= 0) {
    http_response_code(404);
    include APP_ROOT . '/404.php';
    exit;
}

$drama = $dramaObj->getById($dramaId);
if (!$drama || $drama['status'] !== 'active') {
    http_response_code(404);
    include APP_ROOT . '/404.php';
    exit;
}

$canonicalUrl = SeoHelper::dramaUrl($dramaId, $drama['title']);
SeoHelper::redirectIfNotCanonical($canonicalUrl);

// 完整演职人员（模特 + 非模特演员），主演优先 + 番位排序
$cast      = $dramaObj->getCastByDrama($dramaId, 0, true);
$castCount = count($cast);
$userId    = $modelUserId;

// 关注态（仅模特库成员可关注）
$followedIds = [];
$castModelIds = [];
foreach ($cast as $c) {
    if (!empty($c['model_id'])) {
        $castModelIds[] = intval($c['model_id']);
    }
}
if ($userId && $castModelIds) {
    $ph = implode(',', array_map('intval', $castModelIds));
    $stmt = $pdo->prepare("SELECT model_id FROM model_follows WHERE user_id = ? AND model_id IN ($ph)");
    $stmt->execute([$userId]);
    $followedIds = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
}

$title        = htmlspecialchars($drama['title']);
$coverUrl     = !empty($drama['cover']) ? model_media($drama['cover']) : '';
$ogImage      = $coverUrl ?: 'https://58.tl/assets/images/default.jpg';
$episodes     = intval($drama['episodes'] ?? 0);
$tags         = $drama['tags_arr'] ?? [];
$synopsis     = trim((string)($drama['synopsis'] ?? ''));
$hgUrl        = trim((string)($drama['hg_url'] ?? ''));

$workJsonLd = SeoHelper::tvSeriesSchema([
    'title'       => $drama['title'],
    'url'         => $canonicalUrl,
    'image'       => $ogImage,
    'description' => $synopsis !== '' ? $synopsis : ($drama['title'] . ' - 58 模特库收录的短剧作品'),
    'episodes'    => $episodes,
    'genre'       => $tags,
    'actors'      => array_map(function ($c) {
        $actor = ['name' => $c['name']];
        if (!empty($c['link'])) {
            $actor['url'] = $c['link'];
        }
        return $actor;
    }, $cast),
]);

$breadcrumbJsonLd = SeoHelper::breadcrumbList([
    ['name' => '58 模特库', 'url' => MODEL_BASE_URL . '/'],
    ['name' => '短剧库',    'url' => MODEL_BASE_URL . '/dramas.php'],
    ['name' => $drama['title'], 'url' => null],
]);

$site_config = model_site_config([
    'title'       => SeoHelper::title($drama['title'] . ' - 短剧 - 58 模特库'),
    'description' => SeoHelper::description($synopsis !== '' ? mb_substr($synopsis, 0, 110) : ($drama['title'] . ' 的参演阵容与作品信息。'), '58 模特库'),
    'keywords'    => '短剧,' . $drama['title'] . ',短剧演员,' . implode(',', array_slice($tags, 0, 4)),
    'canonical_url' => $canonicalUrl,
    'og_image'    => $ogImage,
    'og_type'     => 'video.tv_show',
    'og_title'    => $drama['title'] . ' - 58 模特库',
    'extra_head'  => $workJsonLd . $breadcrumbJsonLd,
]);
require_once __DIR__ . '/includes/header.php';
?>

<div class="model-wrap">
    <div class="m-crumb">
        <a href="/">首页</a><i class="fas fa-chevron-right"></i>
        <a href="/dramas.php">短剧库</a><i class="fas fa-chevron-right"></i>
        <span><?= $title ?></span>
    </div>

    <div class="m-drama-hero">
        <?php if ($coverUrl): ?>
        <div class="cover"><img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= $title ?>"></div>
        <?php else: ?>
        <div class="cover ph"><i class="fas fa-film"></i></div>
        <?php endif; ?>

        <div class="body">
            <h1><?= $title ?></h1>
            <div class="m-drama-facts">
                <?php if ($episodes > 0): ?><span><i class="fas fa-list-ol"></i> 共 <b><?= $episodes ?></b> 集</span><?php endif; ?>
                <span><i class="fas fa-users"></i> <b><?= $castCount ?></b> 位演职人员</span>
            </div>

            <?php if (!empty($tags)): ?>
            <div style="margin-bottom:14px;">
                <?php foreach ($tags as $t): ?><span class="m-tagchip"><?= htmlspecialchars($t) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($synopsis !== ''): ?>
                <p class="syn"><?= nl2br(htmlspecialchars($synopsis)) ?></p>
            <?php endif; ?>

            <div class="m-profile-actions">
                <?php if ($hgUrl !== ''): ?>
                    <a class="m-btn m-btn-primary" href="<?= htmlspecialchars($hgUrl) ?>" target="_blank" rel="nofollow noopener">
                        <i class="fas fa-play"></i> 前往红果观看
                    </a>
                <?php endif; ?>
                <?php if ($castCount > 0): ?>
                    <a class="m-btn m-btn-ghost" href="#cast">查看演员阵容</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============ 主要演员（红果风格横向卡片） ============ -->
    <?php if (!empty($cast)): ?>
    <section class="m-sec" id="cast">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>主要演员</h2>
                <p class="sub">共 <?= $castCount ?> 位演职人员<?= $castCount > 5 ? '，按番位展示前 5 位' : '' ?></p>
            </div>
            <?php if ($castCount > 5): ?>
                <a class="m-more" href="#cast-all">全部演员 <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <div class="m-cast-rail">
            <?php foreach (array_slice($cast, 0, 5) as $c): ?>
            <div class="m-cast-person<?= $c['link'] ? ' is-model' : '' ?>">
                <?php if ($c['link']): ?>
                <a class="cp-avatar" href="<?= htmlspecialchars($c['link']) ?>">
                <?php else: ?>
                <span class="cp-avatar">
                <?php endif; ?>
                    <?php if (!empty($c['avatar'])): ?>
                        <img src="<?= htmlspecialchars(model_media($c['avatar'])) ?>" alt="<?= htmlspecialchars($c['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="cp-initial"><?= htmlspecialchars(mb_substr($c['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                <?php if ($c['link']): ?></a><?php else: ?></span><?php endif; ?>

                <div class="cp-name">
                    <?= htmlspecialchars($c['name']) ?>
                    <?php if ($c['link']): ?><i class="fas fa-check-circle cp-badge" title="58 模特库成员"></i><?php endif; ?>
                </div>
                <?php if (!empty($c['role_name'])): ?>
                    <div class="cp-role">饰 <?= htmlspecialchars($c['role_name']) ?><?= !empty($c['is_lead']) ? '（主要演员）' : '' ?></div>
                <?php elseif (!empty($c['is_lead'])): ?>
                    <div class="cp-role">主要演员</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ 全部演职人员（含非模特演员） ============ -->
    <?php if ($castCount > 5): ?>
    <section class="m-sec" id="cast-all">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>全部演职人员</h2>
                <p class="sub">共 <?= $castCount ?> 位</p>
            </div>
        </div>
        <div class="m-cast-list">
            <?php foreach ($cast as $i => $c): ?>
            <div class="m-cast-row">
                <span class="cr-no"><?= $i + 1 ?></span>
                <?php if (!empty($c['avatar'])): ?>
                    <img class="cr-av" src="<?= htmlspecialchars(model_media($c['avatar'])) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="cr-av cr-initial"><?= htmlspecialchars(mb_substr($c['name'], 0, 1)) ?></span>
                <?php endif; ?>
                <span class="cr-name">
                    <?php if ($c['link']): ?>
                        <a href="<?= htmlspecialchars($c['link']) ?>"><?= htmlspecialchars($c['name']) ?></a>
                        <i class="fas fa-check-circle cp-badge" title="58 模特库成员"></i>
                    <?php else: ?>
                        <?= htmlspecialchars($c['name']) ?>
                    <?php endif; ?>
                </span>
                <span class="cr-role">
                    <?= !empty($c['role_name']) ? '饰 ' . htmlspecialchars($c['role_name']) : '' ?>
                    <?php if (!empty($c['is_lead'])): ?><span class="m-lead-tag">主要演员</span><?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ 参演模特（可关注，导流入口） ============ -->
    <?php
    $castModels = [];
    foreach ($cast as $c) {
        if ($c['model_id']) {
            $castModels[] = $c['raw'];
        }
    }
    ?>
    <?php if (!empty($castModels)): ?>
    <section class="m-sec">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>参演模特</h2>
                <p class="sub">点击进入模特主页，可关注 TA 的最新动态</p>
            </div>
        </div>
        <div class="m-grid">
            <?php foreach ($castModels as $cm): ?>
                <?= renderModelCard(
                    $cm,
                    [],
                    isset($followedIds[$cm['id']]),
                    $userId,
                    ['role_name' => $cm['role_name'] ?? '', 'is_lead' => $cm['is_lead'] ?? 0]
                ) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
