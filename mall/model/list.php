<?php
/**
 * 模特库已抽取为独立子站 model.58.tl
 * 本文件保留为 301 跳转壳（nginx/.htaccess 规则未生效时的兜底）
 */
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = 'https://model.58.tl/list.php' . ($qs !== '' ? '?' . $qs : '');
header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . $target);
exit;
