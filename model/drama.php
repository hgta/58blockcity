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

$cast   = $dramaObj->getModelsByDrama($dramaId, true);
$userId = $modelUserId;

// 关注态
$followedIds = [];
$castIds = array_column($cast, 'id');
if ($userId && $castIds) {
    $ph = implode(',', array_map('intval', $castIds));
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
        return [
            'name' => $c['nickname'],
            'url'  => SeoHelper::modelUrl($c['id'], $c['nickname']),
        ];
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
                <span><i class="fas fa-users"></i> <b><?= count($cast) ?></b> 位模特参演</span>
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
                <?php if (!empty($cast)): ?>
                    <a class="m-btn m-btn-ghost" href="#cast">查看参演模特</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <section class="m-sec" id="cast">
        <div class="m-sec-head">
            <div>
                <h2><span class="bar"></span>参演阵容</h2>
                <p class="sub">主演优先展示，点击进入模特主页</p>
            </div>
        </div>
        <?php if (empty($cast)): ?>
            <div class="m-empty"><i class="fas fa-user-slash"></i>该剧暂未关联参演模特</div>
        <?php else: ?>
            <div class="m-grid">
                <?php foreach ($cast as $cm): ?>
                    <?= renderModelCard(
                        $cm,
                        [],
                        isset($followedIds[$cm['id']]),
                        $userId,
                        ['role_name' => $cm['role_name'] ?? '', 'is_lead' => $cm['is_lead'] ?? 0]
                    ) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
