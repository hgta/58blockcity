<?php
/**
 * 商城子站 · 站点地图（mall.58.tl）
 *
 * 仅输出本域（mall.58.tl）URL，供百度等搜索引擎收录。
 * nginx: rewrite ^/sitemap\.xml$ /sitemap.php last;
 */

$__root = dirname(__DIR__);   // .../58blockcity

require_once $__root . '/config/database.php';
require_once $__root . '/classes/SeoHelper.php';

header('Content-Type: application/xml; charset=utf-8');

define('MALL_BASE', 'https://mall.58.tl');
$now = date('Y-m-d');

$urls = [];

function mall_node(&$urls, $loc, $priority, $freq, $lastmod)
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod];
}

// 首页与列表页
mall_node($urls, MALL_BASE . '/', '1.0', 'daily', $now);
mall_node($urls, MALL_BASE . '/product/list.php', '0.9', 'daily', $now);
mall_node($urls, MALL_BASE . '/shop/list.php', '0.8', 'weekly', $now);
mall_node($urls, MALL_BASE . '/author/list.php', '0.8', 'daily', $now);
mall_node($urls, MALL_BASE . '/rankings/', '0.8', 'daily', $now);

// 商品详情
try {
    $stmt = $pdo->query("SELECT id, name, updated_at FROM products WHERE status = 'active' ORDER BY id DESC LIMIT 5000");
    while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $p['updated_at'] ? date('Y-m-d', strtotime($p['updated_at'])) : $now;
        mall_node($urls, SeoHelper::productUrl($p['id'], $p['name']), '0.8', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // products 表不存在时跳过
}

// 店铺详情
try {
    $stmt = $pdo->query("SELECT id, shop_name, updated_at FROM shops WHERE status = 'active' ORDER BY id DESC LIMIT 2000");
    while ($s = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $s['updated_at'] ? date('Y-m-d', strtotime($s['updated_at'])) : $now;
        mall_node($urls, SeoHelper::shopUrl($s['id'], $s['shop_name']), '0.7', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // shops 表不存在时跳过
}

// 作者详情
try {
    $stmt = $pdo->query("SELECT id, nickname, updated_at FROM authors WHERE status = 'active' ORDER BY id DESC LIMIT 2000");
    while ($a = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $a['updated_at'] ? date('Y-m-d', strtotime($a['updated_at'])) : $now;
        mall_node($urls, SeoHelper::authorUrl($a['id'], $a['nickname']), '0.7', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // authors 表不存在时跳过
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
