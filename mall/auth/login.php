<?php
$site_config = [
    'name' => '人气商城',
    'desc' => 'BCT商城交易平台',
    'redirect_after_login' => '../user/dashboard.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/login.php';
