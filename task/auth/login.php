<?php
$site_config = [
    'name' => '任务广场',
    'desc' => '登录后发布/领取悬赏任务',
    'redirect_after_login' => '../index.php',
    'home_url' => '../index.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/login.php';
