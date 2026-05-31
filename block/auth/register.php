<?php
$site_config = [
    'name' => '区块交易市场',
    'desc' => '创建您的账户开始区块链城市之旅',
    'redirect_after_login' => '../user/dashboard.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/register.php';
