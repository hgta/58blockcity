<?php
$site_config = [
    'name' => '58拍卖',
    'desc' => '登录后参与区块/NFT头像拍卖',
    'redirect_after_login' => '../index.php',
    'home_url' => '../index.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/login.php';
