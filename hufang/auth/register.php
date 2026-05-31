<?php
$site_config = [
    'name' => '互访圈',
    'desc' => '加入城市互访网络',
    'redirect_after_login' => '../user/dashboard.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/register.php';
