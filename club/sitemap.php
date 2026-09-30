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

function club_node(&$urls, $loc, $priority, $freq, $lastmod, array $images = [])
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod, 'images' => $images];
}

// 首页
club_node($urls, CLUB_BASE . '/', '1.0', 'daily', $now);

// 帖子详情（带正文配图，供图片搜索收录）
try {
    $stmt = $pdo->query("SELECT id, title, content, images, updated_at FROM posts WHERE status='active' ORDER BY id DESC LIMIT 5000");
    while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : $now;
        $title   = !empty($p['title']) ? $p['title'] : $p['content'];

        $images = [];
        $raw = json_decode($p['images'] ?? '', true);
        if (is_array($raw)) {
            foreach ($raw as $img) {
                $img = ltrim((string)$img, '/');
                if ($img === '') continue;
                $images[] = [
                    'loc'   => CLUB_BASE . '/' . $img,
                    'title' => mb_substr(strip_tags((string)$title), 0, 80),
                ];
                if (count($images) >= 10) break;   // 单帖最多 10 张，避免 sitemap 过大
            }
        }

        club_node($urls, SeoHelper::postUrl($p['id'], $title), '0.7', 'weekly', $lastmod, $images);
    }
} catch (Exception $e) {
    // posts 表不存在时跳过
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u['loc']) ?></loc>
    <lastmod><?= htmlspecialchars($u['lastmod']) ?></lastmod>
    <changefreq><?= htmlspecialchars($u['freq']) ?></changefreq>
    <priority><?= htmlspecialchars($u['priority']) ?></priority>
<?php foreach (($u['images'] ?? []) as $img): ?>
    <image:image>
      <image:loc><?= htmlspecialchars($img['loc']) ?></image:loc>
      <image:title><?= htmlspecialchars($img['title']) ?></image:title>
    </image:image>
<?php endforeach; ?>
  </url>
<?php endforeach; ?>
</urlset>
