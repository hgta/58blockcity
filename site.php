<?php
/**
 * 百度主动推送工具
 * 使用方式：
 *   1. 命令行：php site.php                    （推送 sitemap 中所有 URL）
 *   2. 命令行：php site.php <url1> <url2> ...  （推送指定 URL）
 * 注意：请先在 config/seo.php 中填入百度搜索资源平台的真实 token。
 */
require_once __DIR__ . '/classes/SeoHelper.php';

$urls = [];

if ($argc > 1) {
    // 命令行参数传入指定 URL
    $urls = array_slice($argv, 1);
} else {
    // 自动从当前站点生成一批重要 URL（与 sitemap 保持一致的入口页）
    $urls = [
        'https://www.58.tl/',
        'https://www.58.tl/top200city.php',
        'https://www.58.tl/all-cities.php',
        'https://block.58.tl/',
        'https://bct.58.tl/',
        'https://bct.58.tl/market.php',
        'https://mall.58.tl/',
        'https://mall.58.tl/product/list.php',
        'https://mall.58.tl/shop/list.php',
        'https://nft.58.tl/',
        'https://v.58.tl/',
        'https://v.58.tl/circles/all.php',
    ];
}

$result = SeoHelper::baiduPush($urls);
if ($result === false) {
    echo "推送失败：config/seo.php 中未配置百度 token 或 URL 为空。\n";
    exit(1);
}

echo $result . "\n";
