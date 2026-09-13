<?php
/**
 * 模特个人页已抽取为独立子站 model.58.tl
 * 本文件保留为 301 跳转壳（nginx/.htaccess 规则未生效时的兜底）
 */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    // 新格式 /m/{id}-{slug}.html；无 slug 时由子站侧自行规范化
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: https://model.58.tl/m/' . $id);
    exit;
}
header('HTTP/1.1 301 Moved Permanently');
header('Location: https://model.58.tl/list.php');
exit;
