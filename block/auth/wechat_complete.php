<?php
/**
 * 区块子站 · 微信扫码注册补全（代理到共享页）
 */
$site_config = [
    'name'                   => '区块交易市场',
    'desc'                   => '完成注册，开启城市之旅',
    'redirect_after_login'   => '../user/dashboard.php',
    'db_path'                => '../../config/database.php',
    'class_path'             => '../../classes/',
    'includes_path'          => '../includes/',
];
require_once '../../auth/wechat_complete.php';
