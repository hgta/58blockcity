<?php
/**
 * 拍卖子站 · 站点地图（bid.58.tl）
 *
 * 仅输出本域 URL。nginx: rewrite ^/sitemap\.xml$ /sitemap.php last;
 * 详情页实际 URL 为 /view.php?id=N（见 bid/index.php 的链接生成）。
 */

$__root = dirname(__DIR__);   // .../58blockcity

require_once $__root . '/config/database.php';
require_once $__root . '/classes/SeoHelper.php';

header('Content-Type: application/xml; charset=utf-8');

define('BID_BASE', 'https://bid.58.tl');
$now = date('Y-m-d');

$urls = [];

function bid_node(&$urls, $loc, $priority, $freq, $lastmod)
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod];
}

// 首页（拍卖大厅）
bid_node($urls, BID_BASE . '/', '1.0', 'hourly', $now);

// 拍卖详情（进行中与已成交）
try {
    $stmt = $pdo->query("SELECT id, updated_at FROM auctions WHERE status IN ('active','sold') ORDER BY id DESC LIMIT 2000");
    while ($a = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($a['updated_at']) ? date('Y-m-d', strtotime($a['updated_at'])) : $now;
        bid_node($urls, BID_BASE . '/view.php?id=' . intval($a['id']), '0.7', 'daily', $lastmod);
    }
} catch (Exception $e) {
    // auctions 表不存在时跳过
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
