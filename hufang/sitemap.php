<?php
/**
 * 互访圈子站 · 站点地图（v.58.tl）
 *
 * 注意：v.58.tl 的 nginx root 指向项目根目录（与 www 共用），
 * 因此本文件放在 hufang/ 下，由 v.58.tl 的 vhost 将
 * /sitemap.xml 重写到 /hufang/sitemap.php，避免与 www 的 sitemap 冲突。
 *
 * nginx（v.58.tl server 块）:
 *   rewrite ^/sitemap\.xml$ /hufang/sitemap.php last;
 */

$__root = dirname(__DIR__);   // .../58blockcity

require_once $__root . '/config/database.php';
require_once $__root . '/classes/SeoHelper.php';

header('Content-Type: application/xml; charset=utf-8');

define('V_BASE', 'https://v.58.tl');
$now = date('Y-m-d');

$urls = [];

function v_node(&$urls, $loc, $priority, $freq, $lastmod)
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod];
}

// 首页与列表
v_node($urls, V_BASE . '/', '1.0', 'daily', $now);
v_node($urls, V_BASE . '/hufang/circles/all.php', '0.8', 'weekly', $now);

// 互访圈详情（SeoHelper::circleUrl 生成 v.58.tl 规范地址）
try {
    $stmt = $pdo->query("SELECT id, name, updated_at FROM circles WHERE status = 'active' ORDER BY id DESC LIMIT 2000");
    while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($c['updated_at']) ? date('Y-m-d', strtotime($c['updated_at'])) : $now;
        v_node($urls, SeoHelper::circleUrl($c['id'], $c['name']), '0.7', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // circles 表不存在时跳过
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
