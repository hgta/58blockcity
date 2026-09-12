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

/**
 * 静态内容页节点
 * lastmod 取自文件真实修改时间（filemtime），反映内容真实更新，
 * 避免使用生成时间导致「每天全站都更新」的噪声信号。
 *
 * @param string $relPath 相对站点根目录的路径，如 'help/help.html'
 */
function staticNode($relPath, $priority = '0.7', $changefreq = 'weekly')
{
    $relPath = ltrim($relPath, '/');
    $abs     = __DIR__ . '/' . $relPath;
    $lastmod = is_file($abs) ? date('Y-m-d', filemtime($abs)) : null;
    urlNode('https://www.58.tl/' . $relPath, $priority, $changefreq, $lastmod);
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
urlNode('https://mall.58.tl/model/list.php', '0.8', 'daily', $now);
urlNode('https://mall.58.tl/author/list.php', '0.8', 'daily', $now);
urlNode('https://nft.58.tl/', '0.8', 'daily', $now);
urlNode('https://v.58.tl/', '0.8', 'daily', $now);
urlNode('https://v.58.tl/hufang/circles/all.php', '0.8', 'weekly', $now);
urlNode('https://bid.58.tl/', '0.8', 'daily', $now);
urlNode('https://club.58.tl/', '0.8', 'daily', $now);

// 1b. 静态内容页（帮助中心 / 术语表 / 排行榜 / 新闻 / 城市入口）
// 说明：静态页无数据库 updated_at，lastmod 统一取自 filemtime()
// 帮助中心（9 篇解释型文章 + 术语表）
staticNode('help/help.html', '0.8', 'monthly');
staticNode('help/buy-blocks-guide.html', '0.8', 'monthly');
staticNode('help/create-city.html', '0.8', 'monthly');
staticNode('help/why-create-city.html', '0.8', 'monthly');
staticNode('help/why-create-city-business.html', '0.7', 'monthly');
staticNode('help/why-create-city-community.html', '0.7', 'monthly');
staticNode('help/why-create-city-celebrity.html', '0.7', 'monthly');
staticNode('help/why-create-city-influencer.html', '0.7', 'monthly');
staticNode('help/why-create-city-organization.html', '0.7', 'monthly');
staticNode('help/glossary.html', '0.8', 'monthly');
// 排行榜 / 数据页
staticNode('rankings/rankings.html', '0.8', 'weekly');
staticNode('rankings/gdp-total.html', '0.7', 'weekly');
// 新闻
staticNode('news.php', '0.7', 'daily');
staticNode('news/blockcity-slogan-upgrade-metaverse-pioneer.html', '0.6', 'monthly');
// 城市入口页
staticNode('top100city.html', '0.7', 'weekly');

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

// 7. 模特页
try {
    $stmt = $pdo->query("SELECT id, nickname, updated_at FROM models WHERE status='active' ORDER BY id DESC LIMIT 2000");
    while ($model = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $model['updated_at'] ? date('Y-m-d', strtotime($model['updated_at'])) : $now;
        urlNode(SeoHelper::modelUrl($model['id'], $model['nickname']), '0.7', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // models 表尚未创建，跳过
}

// 8. 作者页
try {
    $stmt = $pdo->query("SELECT id, nickname, updated_at FROM authors WHERE status='active' ORDER BY id DESC LIMIT 2000");
    while ($author = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $author['updated_at'] ? date('Y-m-d', strtotime($author['updated_at'])) : $now;
        urlNode(SeoHelper::authorUrl($author['id'], $author['nickname']), '0.7', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // authors 表尚未创建，跳过
}

// 9. 社区帖子（club.58.tl）
try {
    $stmt = $pdo->query("SELECT id, title, content, updated_at FROM posts WHERE status='active' ORDER BY id DESC LIMIT 5000");
    while ($post = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lastmod = $post['updated_at'] ? date('Y-m-d', strtotime($post['updated_at'])) : $now;
        $title = !empty($post['title']) ? $post['title'] : $post['content'];
        urlNode(SeoHelper::postUrl($post['id'], $title), '0.7', 'weekly', $lastmod);
    }
} catch (Exception $e) {
    // posts 表尚未创建，跳过
}

echo '</urlset>', "\n";

// 主动通知百度 sitemap 已更新（PHP 请求结束时异步执行，不影响响应速度）
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
SeoHelper::pingSitemap('https://www.58.tl/sitemap.xml');
