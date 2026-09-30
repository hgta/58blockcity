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

function bid_node(&$urls, $loc, $priority, $freq, $lastmod, array $images = [])
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod, 'images' => $images];
}

/** 图片路径 → 绝对 URL（商品图为 mall 跨域地址，原样保留） */
function bid_abs($path)
{
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return BID_BASE . '/' . ltrim($path, '/');
}

// 首页（拍卖大厅）
bid_node($urls, BID_BASE . '/', '1.0', 'hourly', $now);

// 拍卖详情（进行中与已成交；带拍品图片）
try {
    $stmt = $pdo->query("SELECT id, item_title, item_image, item_images, updated_at FROM auctions WHERE status IN ('active','sold') ORDER BY id DESC LIMIT 2000");
    while ($a = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = !empty($a['updated_at']) ? date('Y-m-d', strtotime($a['updated_at'])) : $now;
        $title   = trim((string)($a['item_title'] ?? '')) ?: ('拍品 #' . $a['id']);

        // 主图 + 图集（商品图为 mall 跨域 URL，原样输出；区块类无图则不输出 image 节点）
        $images = [];
        $main = bid_abs($a['item_image'] ?? '');
        if ($main !== '') {
            $images[] = ['loc' => $main, 'title' => mb_substr(strip_tags($title), 0, 80)];
        }
        $gallery = json_decode($a['item_images'] ?? '', true);
        if (is_array($gallery)) {
            foreach ($gallery as $g) {
                $url = bid_abs($g);
                if ($url === '') continue;
                $images[] = ['loc' => $url, 'title' => mb_substr(strip_tags($title), 0, 80)];
                if (count($images) >= 5) break;
            }
        }

        bid_node($urls, BID_BASE . '/view.php?id=' . intval($a['id']), '0.7', 'daily', $lastmod, $images);
    }
} catch (Exception $e) {
    // auctions 表不存在时跳过
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
