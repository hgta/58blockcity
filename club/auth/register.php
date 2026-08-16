<?php
$site_config = [
    'name' => '58区块社区',
    'desc' => '创建账户参与社区交流',
    'redirect_after_login' => '../index.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/register.php';
