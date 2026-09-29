<?php
/**
 * 区块子站 · 找回密码（代理到共享页）
 * 原页面是"发送重置链接"的假表单（无任何后端处理），已废弃。
 * 现行走「微信通道重置 + 人工兜底」，见 auth/forgot_password.php。
 */
$site_config = [
    'name'                   => '区块交易市场',
    'desc'                   => '找回您的账户密码',
    'redirect_after_login'   => '../user/dashboard.php',
    'db_path'                => '../../config/database.php',
    'class_path'             => '../../classes/',
    'includes_path'          => '../includes/',
];
require_once '../../auth/forgot_password.php';
