<?php
/**
 * 城市页面动态路由
 * .htaccess: RewriteRule ^city/([a-z]+)\.html$ city.php?pinyin=$1 [L,QSA]
 *
 * 读取 city/ 的静态 HTML 作为内容模板，用数据库实时数据替换其中的统计数字。
 * 后续可逐步迁移丰富内容到数据库。
 */

require_once __DIR__ . '/config/database.php';

$pinyin = trim($_GET['pinyin'] ?? '');

// 安全校验
if (!preg_match('/^[a-z]+$/', $pinyin)) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

$file = __DIR__ . "/city/{$pinyin}.html";
if (!file_exists($file)) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

// 读取静态 HTML
$html = file_get_contents($file);

// --- 城市名映射（拼音 → 中文名）---
$pinyinToName = [
    'aba' => '阿坝', 'akesu' => '阿克苏', 'alashanmeng' => '阿拉善盟', 'aletai' => '阿勒泰',
    'ankang' => '安康', 'anqing' => '安庆', 'anshan' => '鞍山', 'anshun' => '安顺',
    'anyang' => '安阳', 'baise' => '百色', 'baishan' => '白山', 'baoding' => '保定',
    'baoji' => '宝鸡', 'baotou' => '包头', 'beihai' => '北海', 'beijing' => '北京',
    'bengbu' => '蚌埠', 'bijie' => '毕节', 'binzhou' => '滨州', 'bozhou' => '亳州',
    'changsha' => '长沙', 'changzhou' => '常州', 'chaozhou' => '潮州', 'chengdu' => '成都',
    'chongqing' => '重庆', 'dalian' => '大连', 'dongguan' => '东莞', 'foshan' => '佛山',
    'fuzhou' => '福州', 'guangzhou' => '广州', 'guiyang' => '贵阳', 'haerbin' => '哈尔滨',
    'haikou' => '海口', 'hangzhou' => '杭州', 'hefei' => '合肥', 'huizhou' => '惠州',
    'huzhou' => '湖州', 'jiaxing' => '嘉兴', 'jinan' => '济南', 'jinhua' => '金华',
    'jining' => '济宁', 'kaifeng' => '开封', 'kunming' => '昆明', 'linyi' => '临沂',
    'luoyang' => '洛阳', 'nanjing' => '南京', 'ningbo' => '宁波', 'ningde' => '宁德',
    'qingdao' => '青岛', 'shanghai' => '上海', 'shenyang' => '沈阳', 'shenzhen' => '深圳',
    'suzhou' => '苏州', 'taiyuan' => '太原', 'tianjin' => '天津', 'wuhan' => '武汉',
    'wuxi' => '无锡', 'xiamen' => '厦门', 'xian' => '西安', 'yantai' => '烟台',
    'zhengzhou' => '郑州', 'zhoukou' => '周口', 'zhuhai' => '珠海',
];
$cityName = $pinyinToName[$pinyin] ?? '';

// --- 查数据库获取城市实时数据 ---
$dbStats = null;
if ($cityName && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, rank, resident_count, activated_blocks, 
                   total_fund, current_balance, popularity
            FROM cities WHERE name = ?
        ");
        $stmt->execute([$cityName]);
        $dbStats = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // DB 不可用时降级使用静态数据
    }
}

// --- 用数据库数据替换 HTML 中的硬编码统计 ---
if ($dbStats) {
    // 排名
    if ($dbStats['rank']) {
        $html = preg_replace(
            '/<div class="stat-value">第\d+名<\/div>/',
            '<div class="stat-value">第' . $dbStats['rank'] . '名</div>',
            $html
        );
    }
    
    // 现有居民数
    if (isset($dbStats['resident_count'])) {
        $html = preg_replace(
            '/<div class="stat-label">现有居民<\/div>\s*<div class="stat-value">[^<]+<\/div>/s',
            '<div class="stat-label">现有居民</div><div class="stat-value">' . 
            number_format((int)$dbStats['resident_count']) . '人</div>',
            $html
        );
    }
    
    // 开启区块数
    if (isset($dbStats['activated_blocks'])) {
        $html = preg_replace(
            '/<div class="stat-label">开启区块数<\/div>\s*<div class="stat-value">[^<]+<\/div>/s',
            '<div class="stat-label">开启区块数</div><div class="stat-value">' . 
            number_format((int)$dbStats['activated_blocks']) . '</div>',
            $html
        );
    }
    
    // 基金余额
    if (isset($dbStats['current_balance'])) {
        $balance = number_format((float)$dbStats['current_balance'], 1);
        $html = preg_replace(
            '/<div class="stat-label">基金余额<\/div>\s*<div class="stat-value">[^<]+<\/div>/s',
            '<div class="stat-label">基金余额</div><div class="stat-value">¥' . $balance . '</div>',
            $html
        );
    }
}

echo $html;
