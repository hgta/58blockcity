<?php
/**
 * 互访圈子站 · 微信扫码注册补全（代理到共享页）
 */
$site_config = [
    'name'                   => '互访圈',
    'desc'                   => '完成注册，开启城市之旅',
    'redirect_after_login'   => '../user/dashboard.php',
    'db_path'                => '../../config/database.php',
    'class_path'             => '../../classes/',
    'includes_path'          => '../includes/',
];
require_once '../../auth/wechat_complete.php';
