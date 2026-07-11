<?php
/**
 * 城市页面路由
 * .htaccess 规则: RewriteRule ^city/([a-z]+)\.html$ city.php?pinyin=$1 [L,QSA]
 * 从 city/ 目录读取对应的静态 HTML 文件
 */

$pinyin = trim($_GET['pinyin'] ?? '');
// 安全校验：只允许小写字母
if (!preg_match('/^[a-z]+$/', $pinyin)) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

$file = __DIR__ . "/city/{$pinyin}.html";

if (file_exists($file)) {
    readfile($file);
} else {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
}
