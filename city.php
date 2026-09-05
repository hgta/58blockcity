<?php
/**
 * 城市页面动态路由（统一城市门户）
 * .htaccess: RewriteRule ^city/([a-z]+)\.html$ city.php?pinyin=$1 [L,QSA]
 *
 * 命中 city/cache/{pinyin}.html（TTL 内）直接输出缓存；未命中实时渲染并回填缓存。
 * 渲染模板：includes/city-portal-render.php（head/hero/六模块/资料卡/空态）。
 * 数据装配：classes/CityPortal.php（六模块独立容错）。
 * 缓存批量重生成：city/build-static.php（建议 cron 每日执行）。
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/City.php';
require_once __DIR__ . '/includes/city-portal-render.php';

// 缓存有效期（秒）：TTL 内直接吐缓存文件；批量重生成由 city/build-static.php 覆盖
if (!defined('CITY_PORTAL_CACHE_TTL')) {
    define('CITY_PORTAL_CACHE_TTL', 600);
}

$pinyin = trim($_GET['pinyin'] ?? '');

// 仅允许纯小写字母 pinyin，目录穿越/注入天然拦截
if (!preg_match('/^[a-z]+$/', $pinyin)) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

$cityObj = new City($pdo);
$city    = $cityObj->getCityByPinyin($pinyin);

if (!$city) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

// ===== 缓存优先 =====
$cacheDir  = __DIR__ . '/city/cache';
$cacheFile = $cacheDir . '/' . $pinyin . '.html';

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < CITY_PORTAL_CACHE_TTL) {
    readfile($cacheFile);
    exit;
}

// ===== 实时渲染 =====
$ctx  = city_portal_build_ctx($pdo, $city, $pinyin);
$html = city_portal_render($ctx);

// ===== 回填缓存（临时文件 + rename 原子替换；失败静默不影响在线输出）=====
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
if (is_dir($cacheDir) && is_writable($cacheDir)) {
    $tmp = $cacheFile . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $html, LOCK_EX) !== false) {
        @rename($tmp, $cacheFile);
    }
    @unlink($tmp);
}

echo $html;
