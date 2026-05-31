<?php
$site_config = [
    'name' => '区块交易市场',
    'desc' => '管理您的城市区块',
    'redirect_after_login' => '../user/dashboard.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/login.php';
