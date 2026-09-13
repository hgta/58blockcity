<?php
/**
 * 区块子站 · 站点地图（block.58.tl）
 *
 * 仅输出本域 URL。nginx: rewrite ^/sitemap\.xml$ /sitemap.php last;
 */

$__root = dirname(__DIR__);   // .../58blockcity

require_once $__root . '/config/database.php';
require_once $__root . '/classes/SeoHelper.php';

header('Content-Type: application/xml; charset=utf-8');

define('BLOCK_BASE', 'https://block.58.tl');
$now = date('Y-m-d');

$urls = [];

function block_node(&$urls, $loc, $priority, $freq, $lastmod)
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod];
}

// 首页与榜单
block_node($urls, BLOCK_BASE . '/', '1.0', 'daily', $now);
block_node($urls, BLOCK_BASE . '/top200city.php', '0.8', 'weekly', $now);

// 城市页
try {
    $stmt = $pdo->query("SELECT id, name, pinyin, updated_at FROM cities WHERE status = 'active' OR status IS NULL ORDER BY id");
    while ($city = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (empty($city['pinyin'])) {
            continue;
        }
        $lastmod = $city['updated_at'] ? date('Y-m-d', strtotime($city['updated_at'])) : $now;
        block_node($urls, BLOCK_BASE . '/city/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $city['pinyin']) . '.html', '0.7', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // cities 表不存在时跳过
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
