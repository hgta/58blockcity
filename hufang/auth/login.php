<?php
$site_config = [
    'name' => '互访圈',
    'desc' => '城市间互访交流平台',
    'redirect_after_login' => '../user/dashboard.php',
    'db_path' => '../../config/database.php',
    'class_path' => '../../classes/',
    'includes_path' => '../includes/',
];
require_once '../../auth/login.php';
