<?php
/**
 * 社区子站 · 微信扫码注册补全（代理到共享页）
 */
$site_config = [
    'name'                   => '58区块社区',
    'desc'                   => '完成注册，开启城市之旅',
    'redirect_after_login'   => '../index.php',
    'home_url'               => '../index.php',
    'db_path'                => '../../config/database.php',
    'class_path'             => '../../classes/',
    'includes_path'          => '../includes/',
];
require_once '../../auth/wechat_complete.php';
