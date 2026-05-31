<?php
$site_config = [
    'name' => 'NFT头像市场',
    'desc' => '管理您的数字头像资产',
    'redirect_after_login' => '../user/dashboard.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/login.php';
