<?php
/**
 * 人气值子站 · 站点地图（bct.58.tl）
 *
 * 仅输出本域 URL。nginx: rewrite ^/sitemap\.xml$ /sitemap.php last;
 */

$__root = dirname(__DIR__);   // .../58blockcity

require_once $__root . '/config/database.php';
require_once $__root . '/classes/SeoHelper.php';

header('Content-Type: application/xml; charset=utf-8');

define('BCT_BASE', 'https://bct.58.tl');
$now = date('Y-m-d');

$urls = [];

function bct_node(&$urls, $loc, $priority, $freq, $lastmod)
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod];
}

// 首页、行情与交易
bct_node($urls, BCT_BASE . '/', '1.0', 'daily', $now);
bct_node($urls, BCT_BASE . '/market.php', '0.9', 'hourly', $now);
bct_node($urls, BCT_BASE . '/trade.php', '0.8', 'hourly', $now);

// 城市页（bct/city.php 接受 ?city= 参数）
try {
    $stmt = $pdo->query("SELECT id, name, pinyin, updated_at FROM cities WHERE status = 'active' OR status IS NULL ORDER BY id");
    while ($city = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = !empty($city['pinyin']) ? $city['pinyin'] : $city['name'];
        if ($key === '') {
            continue;
        }
        $lastmod = $city['updated_at'] ? date('Y-m-d', strtotime($city['updated_at'])) : $now;
        bct_node($urls, BCT_BASE . '/city.php?city=' . urlencode($key), '0.6', 'weekly', $lastmod);
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
