<?php
$site_config = [
    'name' => 'BCT交易市场',
    'desc' => '创建您的账户开始人气值交易',
    'redirect_after_login' => '../user/dashboard.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/register.php';
