<?php
/**
 * 模特子站 · 短剧列表页
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/card.php';

$userId  = $modelUserId;
$perPage = 24;
$tag     = trim($_GET['tag'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));

$result = $dramaObj->getPublicList($page, $perPage, $tag);
$list   = $result['list'];
$facets = $dramaObj->getTagFacets();

$site_config = model_site_config([
    'title'       => SeoHelper::title(($tag ? $tag . '短剧' : '短剧库') . ' - 58 模特库'),
    'description' => SeoHelper::description('58 模特库收录模特参演的短剧作品，查看剧集阵容与参演模特，发现你喜欢的演员。', '58 模特库'),
    'keywords'    => '短剧,模特短剧,红果短剧,' . ($tag ?: '短剧库'),
    'canonical_url' => SeoHelper::dramaListUrl($tag),
]);
require_once __DIR__ . '/includes/header.php';
?>

<div class="model-wrap">
    <div class="m-page-head">
        <h1>🎬 短剧库</h1>
        <p>模特们参演的短剧作品。点开一部剧，看看都有谁在演。</p>
        <div class="head-cta">
            <a class="m-btn m-btn-ghost m-btn-sm" href="/rankings.php"><i class="fas fa-trophy"></i> 模特排行榜</a>
            <a class="m-btn m-btn-primary m-btn-sm" href="/apply.php"><i class="fas fa-user-plus"></i> 我要当模特</a>
        </div>
    </div>

    <?php if (!empty($facets)): ?>
    <div class="m-filters" style="margin-top:22px;">
        <div class="filter-row">
            <span class="filter-label">题材</span>
            <a class="chip <?= $tag === '' ? 'active' : '' ?>" href="/dramas.php">全部</a>
            <?php foreach ($facets as $f): ?>
                <a class="chip <?= $tag === $f['tag'] ? 'active' : '' ?>" href="/dramas.php?tag=<?= urlencode($f['tag']) ?>">
                    <?= htmlspecialchars($f['tag']) ?> <small style="opacity:.65"><?= intval($f['c']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="m-count" style="margin-top:20px;">共收录 <b><?= intval($result['total']) ?></b> 部短剧</div>

    <?php if (empty($list)): ?>
        <div class="m-empty"><i class="fas fa-film"></i>暂无短剧数据，敬请期待</div>
    <?php else: ?>
        <div class="m-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));">
            <?php foreach ($list as $d): ?>
            <a class="m-cast-card" href="<?= htmlspecialchars(SeoHelper::dramaUrl($d['id'], $d['title'])) ?>">
                <div class="cc-cover">
                    <?php if (!empty($d['cover'])): ?>
                        <img src="<?= htmlspecialchars(model_media($d['cover'])) ?>" alt="<?= htmlspecialchars($d['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#d6d2ca;font-size:32px;"><i class="fas fa-film"></i></div>
                    <?php endif; ?>
                    <?php if (!empty($d['episodes'])): ?><span class="dc-ep"><?= intval($d['episodes']) ?> 集</span><?php endif; ?>
                </div>
                <div class="cc-body">
                    <div class="cc-title"><?= htmlspecialchars($d['title']) ?></div>
                    <div class="cc-role"><?= intval($d['model_count'] ?? 0) ?> 位演职人员</div>
                    <?php if (!empty($d['tags_arr'])): ?>
                    <div style="margin-top:8px;">
                        <?php foreach (array_slice($d['tags_arr'], 0, 3) as $t): ?><span class="m-tagchip"><?= htmlspecialchars($t) ?></span><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($result['pages'] > 1): ?>
    <div class="m-pager">
        <?php if ($page > 1): ?><a href="?<?= htmlspecialchars(http_build_query(array_filter(['tag' => $tag, 'page' => $page - 1]))) ?>">上一页</a><?php endif; ?>
        <span class="cur"><?= $page ?> / <?= intval($result['pages']) ?></span>
        <?php if ($page < $result['pages']): ?><a href="?<?= htmlspecialchars(http_build_query(array_filter(['tag' => $tag, 'page' => $page + 1]))) ?>">下一页</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
