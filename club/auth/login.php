<?php
$site_config = [
    'name' => '58区块社区',
    'desc' => '登录后发帖、分享心情',
    'redirect_after_login' => '../index.php',
    'home_url' => '../index.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/login.php';
