<?php
$site_config = [
    'name' => '人气商城',
    'desc' => '创建账户开始购物',
    'redirect_after_login' => '../user/dashboard.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/register.php';
