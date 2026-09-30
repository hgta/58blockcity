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

function mall_node(&$urls, $loc, $priority, $freq, $lastmod, array $images = [])
{
    $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod, 'images' => $images];
}

/** 图片路径 → 绝对 URL（已是 http 的原样返回） */
function mall_abs($path)
{
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return MALL_BASE . '/' . ltrim($path, '/');
}

/** 组装 image 节点数据 */
function mall_img($url, $title)
{
    return $url === '' ? null : ['loc' => $url, 'title' => mb_substr(strip_tags((string)$title), 0, 80)];
}

// 首页与列表页
mall_node($urls, MALL_BASE . '/', '1.0', 'daily', $now);
mall_node($urls, MALL_BASE . '/product/list.php', '0.9', 'daily', $now);
mall_node($urls, MALL_BASE . '/shop/list.php', '0.8', 'weekly', $now);
mall_node($urls, MALL_BASE . '/author/list.php', '0.8', 'daily', $now);
mall_node($urls, MALL_BASE . '/rankings/', '0.8', 'daily', $now);

// 商品详情（带主图与图集）
try {
    $stmt = $pdo->query("SELECT id, name, main_image, images, updated_at FROM products WHERE status = 'active' ORDER BY id DESC LIMIT 5000");
    while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $p['updated_at'] ? date('Y-m-d', strtotime($p['updated_at'])) : $now;

        $images = [];
        $main = mall_img(mall_abs($p['main_image'] ?? ''), $p['name']);
        if ($main) $images[] = $main;
        $gallery = json_decode($p['images'] ?? '', true);
        if (is_array($gallery)) {
            foreach ($gallery as $g) {
                $it = mall_img(mall_abs($g), $p['name']);
                if ($it) $images[] = $it;
                if (count($images) >= 5) break;
            }
        }

        mall_node($urls, SeoHelper::productUrl($p['id'], $p['name']), '0.8', 'weekly', $lastmod, $images);
    }
} catch (Exception $e) {
    // products 表不存在时跳过
}

// 店铺详情（带店铺 Logo 与横幅）
try {
    $stmt = $pdo->query("SELECT id, shop_name, shop_logo, shop_banner, updated_at FROM shops WHERE status = 'active' ORDER BY id DESC LIMIT 2000");
    while ($s = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $s['updated_at'] ? date('Y-m-d', strtotime($s['updated_at'])) : $now;

        $images = [];
        foreach ([$s['shop_logo'] ?? '', $s['shop_banner'] ?? ''] as $sp) {
            $it = mall_img(mall_abs($sp), $s['shop_name']);
            if ($it) $images[] = $it;
        }

        mall_node($urls, SeoHelper::shopUrl($s['id'], $s['shop_name']), '0.7', 'weekly', $lastmod, $images);
    }
} catch (Exception $e) {
    // shops 表不存在时跳过
}

// 作者详情（带头像与作品图集）
try {
    $stmt = $pdo->query("SELECT id, nickname, avatar, author_works, updated_at FROM authors WHERE status = 'active' ORDER BY id DESC LIMIT 2000");
    while ($a = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $a['updated_at'] ? date('Y-m-d', strtotime($a['updated_at'])) : $now;

        $images = [];
        $av = mall_img(mall_abs($a['avatar'] ?? ''), $a['nickname']);
        if ($av) $images[] = $av;
        $works = json_decode($a['author_works'] ?? '', true);
        if (is_array($works)) {
            foreach ($works as $w) {
                $it = mall_img(mall_abs($w), $a['nickname'] . ' 作品');
                if ($it) $images[] = $it;
                if (count($images) >= 5) break;
            }
        }

        mall_node($urls, SeoHelper::authorUrl($a['id'], $a['nickname']), '0.7', 'weekly', $lastmod, $images);
    }
} catch (Exception $e) {
    // authors 表不存在时跳过
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
