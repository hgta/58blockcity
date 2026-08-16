<?php
$site_config = [
    'name' => '58拍卖',
    'desc' => '创建账户参与区块/NFT头像拍卖',
    'redirect_after_login' => '../index.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/register.php';
