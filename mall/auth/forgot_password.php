<?php
/**
 * 商城子站 · 找回密码（代理到共享页：微信通道重置 + 人工兜底）
 */
$site_config = [
    'name'                   => '人气商城',
    'desc'                   => '找回您的账户密码',
    'redirect_after_login'   => '../user/dashboard.php',
    'db_path'                => '../../config/database.php',
    'class_path'             => '../../classes/',
    'includes_path'          => '../includes/',
];
require_once '../../auth/forgot_password.php';
