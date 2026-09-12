<?php
$site_config['footer_name'] = '58拍卖 | BlockCity 拍卖平台';
$site_config['footer_extra'] = ($site_config['footer_extra'] ?? '')
    . '<script src="/assets/js/auction.js?v=20260912"></script>';
require_once __DIR__ . '/../../shared/footer.php';
