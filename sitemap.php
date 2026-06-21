<?php
/**
 * 自动生成全站 Sitemap
 * 包含：首页、城市页、商品页、店铺页、互访圈页、NFT 页
 * 通过 .htaccess 映射到 sitemap.xml
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/SeoHelper.php';

header('Content-Type: application/xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";

$now = date('Y-m-d');

function urlNode($loc, $priority = '0.7', $changefreq = 'weekly', $lastmod = null)
{
    $lastmodAttr = $lastmod ? '<lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' : '';
    echo '  <url>', "\n";
    echo '    <loc>' . htmlspecialchars($loc) . '</loc>', "\n";
    if ($lastmodAttr) {
        echo '    ' . $lastmodAttr, "\n";
    }
    echo '    <changefreq>' . htmlspecialchars($changefreq) . '</changefreq>', "\n";
    echo '    <priority>' . htmlspecialchars($priority) . '</priority>', "\n";
    echo '  </url>', "\n";
}

// 1. 首页与重要列表页
urlNode('https://www.58.tl/', '1.0', 'daily', $now);
urlNode('https://www.58.tl/top200city.php', '0.8', 'weekly', $now);
urlNode('https://www.58.tl/all-cities.php', '0.8', 'weekly', $now);
urlNode('https://block.58.tl/', '0.9', 'daily', $now);
urlNode('https://block.58.tl/top200city.php', '0.8', 'weekly', $now);
urlNode('https://bct.58.tl/', '0.9', 'daily', $now);
urlNode('https://bct.58.tl/market.php', '0.8', 'hourly', $now);
urlNode('https://mall.58.tl/', '0.9', 'daily', $now);
urlNode('https://mall.58.tl/product/list.php', '0.8', 'daily', $now);
urlNode('https://mall.58.tl/shop/list.php', '0.8', 'weekly', $now);
urlNode('https://nft.58.tl/', '0.8', 'daily', $now);
urlNode('https://v.58.tl/', '0.8', 'daily', $now);
urlNode('https://v.58.tl/circles/all.php', '0.8', 'weekly', $now);

// 2. 城市页
$stmt = $pdo->query("SELECT id, name, pinyin, updated_at FROM cities WHERE status = 'active' OR status IS NULL ORDER BY id");
while ($city = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $lastmod = $city['updated_at'] ? date('Y-m-d', strtotime($city['updated_at'])) : $now;
    urlNode(SeoHelper::cityUrl($city['pinyin']), '0.7', 'weekly', $lastmod);
}

// 3. 商品页
$stmt = $pdo->query("SELECT id, name, updated_at FROM products WHERE status = 'active' ORDER BY id DESC LIMIT 5000");
while ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $lastmod = $product['updated_at'] ? date('Y-m-d', strtotime($product['updated_at'])) : $now;
    urlNode(SeoHelper::productUrl($product['id'], $product['name']), '0.7', 'weekly', $lastmod);
}

// 4. 店铺页
$stmt = $pdo->query("SELECT id, shop_name, updated_at FROM shops WHERE status = 'active' ORDER BY id DESC LIMIT 1000");
while ($shop = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $lastmod = $shop['updated_at'] ? date('Y-m-d', strtotime($shop['updated_at'])) : $now;
    urlNode(SeoHelper::shopUrl($shop['id'], $shop['shop_name']), '0.7', 'weekly', $lastmod);
}

// 5. 互访圈页
$stmt = $pdo->query("SELECT id, name, updated_at FROM circles WHERE status = 'active' ORDER BY id DESC LIMIT 2000");
while ($circle = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $lastmod = $circle['updated_at'] ? date('Y-m-d', strtotime($circle['updated_at'])) : $now;
    urlNode(SeoHelper::circleUrl($circle['id'], $circle['name']), '0.6', 'weekly', $lastmod);
}

// 6. NFT 页（基于 nft_avatars）
$stmt = $pdo->query("SELECT id, avatar_id, code, updated_at FROM nft_avatars ORDER BY id DESC LIMIT 2000");
while ($nft = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $lastmod = $nft['updated_at'] ? date('Y-m-d', strtotime($nft['updated_at'])) : $now;
    $name = $nft['avatar_id'] ?: $nft['code'];
    urlNode(SeoHelper::nftUrl($nft['id'], $name), '0.6', 'weekly', $lastmod);
}

echo '</urlset>', "\n";
