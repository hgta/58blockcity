<?php
/**
 * www.58.tl 站点地图（仅包含本域 URL）
 *
 * 归属约定：sitemap 中的 <loc> 必须与 sitemap 所在站点同域。
 * 子域（mall/block/bct/model/nft/v/bid/club/task）各自提供独立 sitemap，
 * 见各子目录下的 sitemap.php，不混入本文件。
 *
 * 通过 .htaccess / nginx 映射到 /sitemap.xml
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

// 1. 首页与重要列表页（仅 www.58.tl 域）
urlNode('https://www.58.tl/', '1.0', 'daily', $now);
urlNode('https://www.58.tl/top200city.php', '0.8', 'weekly', $now);
urlNode('https://www.58.tl/all-cities.php', '0.8', 'weekly', $now);

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

echo '</urlset>', "\n";

// 主动通知（百度无 sitemap ping 接口；Google ping 受配置开关控制，默认关闭）
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
SeoHelper::pingSitemap('https://www.58.tl/sitemap.xml');

