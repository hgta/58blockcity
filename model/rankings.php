<?php
/**
 * 模特子站 · 模特排行榜（四维）
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/card.php';

$userId = $modelUserId;

$tabs = [
    'follower' => ['label' => '🔥 粉丝榜', 'unit' => '粉丝'],
    'like'     => ['label' => '❤ 人气榜', 'unit' => '点赞'],
    'drama'    => ['label' => '🎬 短剧榜', 'unit' => '部短剧'],
    'product'  => ['label' => '📦 作品榜', 'unit' => '件作品'],
];
$type = isset($tabs[$_GET['type'] ?? '']) ? $_GET['type'] : 'follower';
$meta = $tabs[$type];

$list    = $modelObj->getRankingBy($type, 50);
$summary = $modelObj->getRankingSummary();

$podium = array_slice($list, 0, 3);
$rest   = array_slice($list, 3);

$site_config = model_site_config([
    'title'       => SeoHelper::title('模特排行榜 - ' . $meta['label'] . ' - 58 模特库'),
    'description' => SeoHelper::description('58 模特库排行榜，按粉丝数、人气点赞、参演短剧数与作品数排名，看看谁是本期最受欢迎的模特。', '58 模特库'),
    'keywords'    => '模特排行榜,模特榜,58模特,人气模特',
    'canonical_url' => SeoHelper::modelRankingUrl($type),
]);
require_once __DIR__ . '/includes/header.php';
?>

<div class="model-wrap">
    <div class="m-page-head">
        <h1>🏆 模特排行榜</h1>
        <p>按不同维度看看谁最受欢迎。数据实时统计，不含水分。</p>
    </div>

    <div class="m-rank-tabs" style="margin-top:22px;">
        <?php foreach ($tabs as $k => $t): ?>
            <a class="m-rank-tab <?= $type === $k ? 'active' : '' ?>" href="/rankings.php?type=<?= $k ?>"><?= $t['label'] ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($list)): ?>
        <div class="m-empty"><i class="fas fa-trophy"></i>暂无排行数据</div>
    <?php else: ?>

        <div class="m-podium">
            <?php
            $order = [0, 1, 2];
            // 视觉上第 1 名居中：2-1-3
            $visual = array_values(array_filter([$podium[1] ?? null, $podium[0] ?? null, $podium[2] ?? null]));
            $rankMap = [];
            if (isset($podium[0])) $rankMap[$podium[0]['id']] = 1;
            if (isset($podium[1])) $rankMap[$podium[1]['id']] = 2;
            if (isset($podium[2])) $rankMap[$podium[2]['id']] = 3;
            foreach ($visual as $p):
                $rk = $rankMap[$p['id']];
                $av = model_img($p, false);
                $val = intval($p['sort_value'] ?? 0);
            ?>
            <a class="m-pod m-pod-<?= $rk ?>" href="<?= htmlspecialchars(SeoHelper::modelUrl($p['id'], $p['nickname'])) ?>">
                <div class="crown"><?= $rk === 1 ? '👑' : ($rk === 2 ? '🥈' : '🥉') ?></div>
                <?php if ($av): ?>
                    <img class="av" src="<?= htmlspecialchars($av) ?>" alt="<?= htmlspecialchars($p['nickname']) ?>">
                <?php else: ?>
                    <div class="av ph"><i class="fas fa-user"></i></div>
                <?php endif; ?>
                <div class="nm"><?= htmlspecialchars($p['nickname']) ?></div>
                <div class="ct">
                    第 <?= $rk ?> 名<?= !empty($p['city']) ? ' · ' . htmlspecialchars($p['city']) : '' ?>
                </div>
                <div class="val"><?= $type === 'follower' ? Model::formatFollower($val) : number_format($val) ?><small><?= $meta['unit'] ?></small></div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($rest)): ?>
        <div class="m-rank-list">
            <?php foreach ($rest as $i => $m): $av = model_img($m, false); $rk = $i + 4; ?>
            <a class="m-rank-item" href="<?= htmlspecialchars(SeoHelper::modelUrl($m['id'], $m['nickname'])) ?>">
                <span class="m-rank-no"><?= $rk ?></span>
                <?php if ($av): ?>
                    <img class="av" src="<?= htmlspecialchars($av) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="av ph"><i class="fas fa-user"></i></span>
                <?php endif; ?>
                <div class="info">
                    <div class="nm"><?= htmlspecialchars($m['nickname']) ?></div>
                    <div class="mt">
                        <?= !empty($m['city']) ? '📍' . htmlspecialchars($m['city']) : '' ?>
                        <?= !empty($m['zodiac']) ? ' ★' . htmlspecialchars($m['zodiac']) : '' ?>
                        <?= intval($m['drama_count']) > 0 ? ' 🎬' . intval($m['drama_count']) : '' ?>
                    </div>
                </div>
                <span class="vl">
                    <?= $type === 'follower' ? Model::formatFollower(intval($m['sort_value'])) : number_format(intval($m['sort_value'])) ?>
                    <small><?= $meta['unit'] ?></small>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
