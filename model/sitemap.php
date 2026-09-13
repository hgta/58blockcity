<?php
/**
 * 模特子站 · 站点地图（model.58.tl）
 * 输出模特个人页与短剧详情页，便于搜索引擎收录
 */
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$now = date('Y-m-d');
$urls = [
    ['loc' => MODEL_BASE_URL . '/',           'priority' => '1.0', 'freq' => 'daily',  'lastmod' => $now],
    ['loc' => MODEL_BASE_URL . '/list.php',   'priority' => '0.9', 'freq' => 'daily',  'lastmod' => $now],
    ['loc' => MODEL_BASE_URL . '/dramas.php', 'priority' => '0.8', 'freq' => 'weekly', 'lastmod' => $now],
    ['loc' => MODEL_BASE_URL . '/rankings.php', 'priority' => '0.8', 'freq' => 'daily', 'lastmod' => $now],
    ['loc' => MODEL_BASE_URL . '/apply.php',  'priority' => '0.6', 'freq' => 'monthly', 'lastmod' => $now],
];

try {
    $stmt = $pdo->query("SELECT id, nickname, updated_at FROM models WHERE status = 'active' ORDER BY id DESC LIMIT 5000");
    while ($m = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $urls[] = [
            'loc'      => SeoHelper::modelUrl($m['id'], $m['nickname']),
            'priority' => '0.8',
            'freq'     => 'weekly',
            'lastmod'  => $m['updated_at'] ? date('Y-m-d', strtotime($m['updated_at'])) : $now,
        ];
    }
} catch (Exception $e) {
    // models 表不存在时跳过
}

try {
    $stmt = $pdo->query("SELECT id, title, updated_at FROM dramas WHERE status = 'active' ORDER BY id DESC LIMIT 5000");
    while ($d = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $urls[] = [
            'loc'      => SeoHelper::dramaUrl($d['id'], $d['title']),
            'priority' => '0.7',
            'freq'     => 'weekly',
            'lastmod'  => $d['updated_at'] ? date('Y-m-d', strtotime($d['updated_at'])) : $now,
        ];
    }
} catch (Exception $e) {
    // dramas 表不存在时跳过
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u['loc']) ?></loc>
    <lastmod><?= htmlspecialchars($u['lastmod']) ?></lastmod>
    <changefreq><?= htmlspecialchars($u['freq']) ?></changefreq>
    <priority><?= htmlspecialchars($u['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
