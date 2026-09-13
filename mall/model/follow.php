<?php
/**
 * 模特关注接口已迁移至独立子站 model.58.tl/follow.php
 * 本文件保留为 301 壳，前端应改用子站接口
 */
header('HTTP/1.1 301 Moved Permanently');
header('Location: https://model.58.tl/follow.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
