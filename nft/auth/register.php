<?php
$site_config = [
    'name' => 'NFT头像市场',
    'desc' => '创建账户开启数字收藏之旅',
    'redirect_after_login' => '../user/dashboard.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/register.php';
