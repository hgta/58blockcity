<?php
/**
 * 社区子站 · 站点地图（club.58.tl）
 *
 * 仅输出本域 URL。nginx: rewrite ^/sitemap\.xml$ /sitemap.php last;
 */

$__root = dirname(__DIR__);   // .../58blockcity

require_once $__root . '/config/database.php';
require_once $__root . '/classes/SeoHelper.php';

header('Content-Type: application/xml; charset=utf-8');

define('CLUB_BASE', 'https://club.58.tl');
$now = date('Y-m-d');

$urls = [];

function club_node(&$urls, $loc, $priority, $freq, $lastmod)
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod];
}

// 首页
club_node($urls, CLUB_BASE . '/', '1.0', 'daily', $now);

// 帖子详情
try {
    $stmt = $pdo->query("SELECT id, title, content, updated_at FROM posts WHERE status='active' ORDER BY id DESC LIMIT 5000");
    while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : $now;
        $title   = !empty($p['title']) ? $p['title'] : $p['content'];
        club_node($urls, SeoHelper::postUrl($p['id'], $title), '0.7', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // posts 表不存在时跳过
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
