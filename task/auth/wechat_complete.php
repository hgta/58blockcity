<?php
/**
 * 任务广场子站 · 微信扫码注册补全（代理到共享页）
 */
$site_config = [
    'name'                   => '任务广场',
    'desc'                   => '完成注册，开启城市之旅',
    'redirect_after_login'   => '../index.php',
    'home_url'               => '../index.php',
    'db_path'                => '../../config/database.php',
    'class_path'             => '../../classes/',
    'includes_path'          => '../includes/',
];
require_once '../../auth/wechat_complete.php';
