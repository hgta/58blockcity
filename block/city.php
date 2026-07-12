<?php
// city/[城市拼音].php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/City.php';
require_once '../classes/Block.php';
require_once '../classes/User.php';
require_once '../classes/SeoHelper.php';

// 获取城市拼音从URL
//$city_pinyin = basename($_SERVER['PHP_SELF'], '.php');
$city_pinyin = $_GET['name'] ?? 'beijing';

// 初始化类
$city = new City($pdo);
$block = new Block($pdo);
$user = new User($pdo);

// 根据拼音获取城市信息
$city_info = $city->getCityByPinyin($city_pinyin);

if (!$city_info) {
    header("HTTP/1.0 404 Not Found");
    include '../404.php';
    exit();
}

$city_id = $city_info['id'];
$city_name = $city_info['name'];

// 获取当前用户ID（如果已登录）
$current_user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

// 定义分区价格逻辑
$zones = [
    'A' => ['start' => 101, 'end' => 1299, 'base_price' => 1286],
    'B' => ['start' => 1301, 'end' => 2499, 'base_price' => 1690],
    'C' => ['start' => 2501, 'end' => 3699, 'base_price' => 2220],
    'D' => ['start' => 3701, 'end' => 4899, 'base_price' => 2918],
    'E' => ['start' => 4901, 'end' => 6099, 'base_price' => 3834],
    'F' => ['start' => 6101, 'end' => 7299, 'base_price' => 5038],
    'G' => ['start' => 7301, 'end' => 8499, 'base_price' => 6619],
    'H' => ['start' => 8501, 'end' => 9699, 'base_price' => 8698],
    'Z' => [
        'part1' => ['start' => 9701, 'end' => 9999, 'base_price' => 11429],
        'part2' => ['start' => 1, 'end' => 99, 'base_price' => 34101],
        'part3' => ['start' => 100, 'end' => 9900, 'base_price' => 34020]
    ]
];

// 视图模式：默认单区(A区)，?view=all=全景模式，?zone=X=指定区
$current_zone = $_GET['zone'] ?? null;
$view_all = isset($_GET['view']) && $_GET['view'] === 'all';

if ($view_all) {
    $view_mode = 'panorama';
    $current_zone = null;
} elseif ($current_zone) {
    $view_mode = 'zone';
} else {
    // 默认进入 A 区单区模式
    $view_mode = 'zone';
    $current_zone = 'A';
}

// 各区的列范围（用于单区网格渲染）
$zone_col_ranges = [
    'A' => [1, 12], 'B' => [13, 24], 'C' => [25, 36], 'D' => [37, 48],
    'E' => [49, 60], 'F' => [61, 72], 'G' => [73, 84], 'H' => [85, 96],
    'Z' => [97, 99],
];

// 列号到区的映射（用于全景网格分区着色）
$col_to_zone = [];
foreach ($zone_col_ranges as $z => $r) {
    for ($c = $r[0]; $c <= $r[1]; $c++) $col_to_zone[$c] = $z;
}

// 全景模式：一次性加载全部9区数据
$all_zones_data = [];
$total_sold_all = 0;
if ($view_mode === 'panorama') {
    foreach (['A','B','C','D','E','F','G','H','Z'] as $zone) {
        $zone_blocks_data = $block->getBlocksByCityZone($city_id, $zone);
        $merged_data = $block->getMergedBlocks($city_id, $zone);
        
        $sold_count = 0;
        $merged_block_numbers = [];
        foreach ($merged_data as $m) {
            $merged_block_numbers = array_merge($merged_block_numbers, explode(',', $m['merged_blocks']));
        }
        
        foreach ($zone_blocks_data as $zb) {
            if ($zb['status'] === 'sold' || $zb['status'] === 'reserved') $sold_count++;
        }
        $total_sold_all += $sold_count;
        
        $all_zones_data[$zone] = [
            'blocks' => $zone_blocks_data,
            'merged' => $merged_data,
            'sold_count' => $sold_count,
            'merged_count' => count($merged_data),
            'merged_numbers' => $merged_block_numbers,
        ];
    }
}

// 单区模式：加载指定区域数据
$zone_blocks = [];
$merged_blocks = [];
$owners_map = [];
if ($view_mode === 'zone') {
    $zone_blocks = $block->getBlocksByCityZone($city_id, $current_zone);
    $merged_blocks = $block->getMergedBlocks($city_id, $current_zone);

    // 预加载拥有者用户名映射
    $owner_ids = array_unique(array_column($zone_blocks, 'owner_id'));
    $owner_ids = array_filter($owner_ids);
    if (!empty($owner_ids)) {
        $placeholders = implode(',', array_fill(0, count($owner_ids), '?'));
        $owner_stmt = $pdo->prepare("SELECT id, username FROM users WHERE id IN ($placeholders)");
        $owner_stmt->execute(array_values($owner_ids));
        while ($o = $owner_stmt->fetch(PDO::FETCH_ASSOC)) {
            $owners_map[$o['id']] = $o['username'];
        }
    }
}

// 处理区块操作（仅单区模式）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user_id && $view_mode === 'zone') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'claim_block') {
        $block_number = $_POST['block_number'] ?? '';
        $selected_blocks = $_POST['selected_blocks'] ?? [];
        
        if (!empty($selected_blocks)) {
            $result = $block->claimMultipleBlocks($current_user_id, $city_id, $current_zone, $selected_blocks);
            if ($result) {
                $success_message = "成功认领 " . count($selected_blocks) . " 个区块！";
                // 百度主动推送
                SeoHelper::baiduPush(SeoHelper::cityUrl($city_info['pinyin'] ?? $city_pinyin));
            } else {
                $error_message = "认领失败，请重试";
            }
        } elseif ($block_number) {
            $result = $block->claimBlock($current_user_id, $city_id, $current_zone, $block_number);
            if ($result) {
                $success_message = "成功认领区块 {$block_number}！";
                // 百度主动推送
                SeoHelper::baiduPush(SeoHelper::cityUrl($city_info['pinyin'] ?? $city_pinyin));
            } else {
                $error_message = "认领失败，区块可能已被认领";
            }
        }
    }
}
?>

<?php
// ========== 城市详情页个性化 SEO 元数据 ==========
$cityName      = htmlspecialchars($city_info['name'] ?? $city_name);
$cityPinyin    = htmlspecialchars($city_info['pinyin'] ?? $city_pinyin);
$cityArea      = htmlspecialchars($city_info['area_code'] ?? '');
$cityResident  = intval($city_info['resident_count'] ?? 0);
$cityBlocks    = intval($city_info['activated_blocks'] ?? 0);
$blockCount    = $city_info['total_blocks'] ?? 89;
$canonicalUrl  = SeoHelper::cityUrl($cityPinyin);

$site_config['title']       = SeoHelper::title("{$cityName}区块城市 - 58区块城市");
$site_config['description'] = SeoHelper::description(
    "{$cityName}区块城市详情页，提供{$cityName}市人口{$cityResident}万、激活{$cityBlocks}个区块等关键数据，展示{$cityName}{$blockCount}大区块地图，是了解{$cityName}数字经济与元宇宙发展的重要门户。",
    '58区块城市'
);
$site_config['keywords']    = "{$cityName}区块城市,{$cityName}元宇宙,58同城{$cityName},{$cityName}数字经济,{$cityName}区块地图" . ($cityArea ? ",{$cityArea}" : '');
$site_config['canonical_url'] = $canonicalUrl;
$site_config['og_image']    = 'https://58.tl/assets/images/og-city-' . SeoHelper::slug($cityName) . '.jpg';
$site_config['og_type']     = 'website';
$site_config['city_name_for_schema'] = $cityName;

// 面包屑结构化数据
$cityBreadcrumbJsonLd = SeoHelper::breadcrumbList([
    ['name' => '58区块城市', 'url' => 'https://www.58.tl/'],
    ['name' => '城市列表', 'url' => 'https://www.58.tl/all-cities.php'],
    ['name' => $cityName, 'url' => null],
]);

// City 结构化数据 (Schema.org City)
$cityJsonLd = '<script type="application/ld+json">' . json_encode([
    '@context'      => 'https://schema.org',
    '@type'         => 'City',
    'name'          => $cityName,
    'url'           => $canonicalUrl,
    'containedInPlace' => ['@type' => 'Country', 'name' => '中国'],
    'description'   => "{$cityName}区块链城市元宇宙服务平台",
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
$site_config['extra_head'] = ($site_config['extra_head'] ?? '') . $cityBreadcrumbJsonLd . $cityJsonLd;
?>

<?php require_once 'includes/header.php'; ?>

<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Microsoft YaHei', Arial, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* 头部样式 */
        .city-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            padding: 40px 0 32px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .city-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255,107,0,0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .city-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
            position: relative;
            letter-spacing: -0.5px;
        }

        .city-stats {
            display: flex;
            gap: 28px;
            font-size: 14px;
            position: relative;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.7);
        }
        .stat-item i {
            color: #ff9500;
            font-size: 12px;
        }

        /* 区域选择器 */
        .zone-selector {
            background-color: white;
            padding: 18px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #f0f0f0;
        }

        .zone-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .zone-tab {
            padding: 10px 24px;
            background-color: #f8f9fa;
            border-radius: 12px;
            text-decoration: none;
            color: #555;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.25s ease;
            border: 1px solid transparent;
        }

        .zone-tab:hover {
            background-color: #fff8f5;
            color: #ff6b00;
            border-color: rgba(255,107,0,0.2);
            transform: translateY(-2px);
        }
        .zone-tab.active {
            background: linear-gradient(135deg, #ff6b00, #ff9500);
            color: white;
            box-shadow: 0 4px 15px rgba(255,107,0,0.3);
        }
        
        /* 区块地图容器 */
        .block-map-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: auto;
        }
        
        .map-header {
            display: flex;
            margin-bottom: 10px;
        }
        
        .row-numbers {
            width: 40px;
        }
        
        .row-number {
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #666;
            border: 1px solid #eee;
        }
        
        .col-numbers {
            display: flex;
            margin-left: 40px;
        }
        
        .col-number {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #666;
            border: 1px solid #eee;
        }
        
        .block-map {
            display: flex;
        }
        
        .map-rows {
            flex: 1;
        }
        
        .map-row {
            display: flex;
            height: 30px;
        }
        
        .block-cell {
            width: 30px;
            height: 30px;
            border: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .block-cell:hover {
            transform: scale(1.1);
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .block-cell.available {
            background-color: #e8f5e8;
            color: #2e7d32;
        }
        
        .block-cell.sold {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .block-cell.reserved {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        /* 区块详情面板 */
        .block-detail-panel {
            background-color: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.06);
            position: sticky;
            top: 20px;
            border: 1px solid #f5f5f5;
        }

        .block-info h3 {
            color: #1a1a2e;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 700;
            padding-bottom: 12px;
            border-bottom: 2px solid #f5f5f5;
        }

        .block-meta {
            margin-bottom: 20px;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f8f8f8;
        }

        .meta-label {
            font-weight: 600;
            color: #888;
            font-size: 13px;
        }

        .meta-value {
            color: #333;
            font-weight: 500;
        }

        .price-highlight {
            color: #ff6b00;
            font-weight: 800;
            font-size: 20px;
        }

        .block-actions {
            margin-top: 24px;
        }

        .btn-buy {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #ff6b00, #ff9500);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255,107,0,0.25);
        }

        .btn-buy:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255,107,0,0.35);
        }

        .btn-buy:disabled {
            background: #e0e0e0;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        /* 响应式设计 */
        @media (max-width: 1200px) {
            .block-cell {
                width: 25px;
                height: 25px;
                font-size: 8px;
            }
            
            .row-number, .col-number {
                height: 25px;
                font-size: 10px;
            }
        }
        
        @media (max-width: 992px) {
            .block-map-container {
                overflow-x: auto;
            }
            
            .block-cell {
                width: 20px;
                height: 20px;
                font-size: 7px;
            }
            
            .row-number, .col-number {
                height: 20px;
                font-size: 9px;
            }
        }
        
        @media (max-width: 768px) {
            .city-stats {
                flex-direction: column;
                gap: 10px;
            }

            .zone-tabs {
                justify-content: center;
            }

            /* 移动端隐藏桌面地图，显示列表 */
            #desktopMap { display: none !important; }
            #mobileList { display: block !important; }
        }

        /* 移动端区块列表样式 */
        .mobile-block-list {
            display: none;
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .mobile-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .mobile-list-header h4 { margin: 0; font-size: 16px; }
        .mobile-list-count { font-size: 12px; color: #999; }
        .mobile-list-filter {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            overflow-x: auto;
        }
        .mobile-filter-btn {
            padding: 6px 14px;
            border: 1px solid #ddd;
            border-radius: 16px;
            background: white;
            font-size: 13px;
            color: #666;
            cursor: pointer;
            white-space: nowrap;
        }
        .mobile-filter-btn.active {
            background: #ff6b00;
            color: white;
            border-color: #ff6b00;
        }
        .mobile-list-items {
            max-height: 65vh;
            overflow-y: auto;
        }
        .mobile-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            border-bottom: 1px solid #f5f5f5;
            transition: background .2s;
        }
        .mobile-list-item:active { background: #fff8f5; }
        .mobile-item-main { display: flex; align-items: center; gap: 8px; }
        .mobile-item-number { font-family: monospace; font-size: 14px; font-weight: bold; color: #333; }
        .mobile-item-status { font-size: 11px; padding: 2px 8px; border-radius: 10px; }
        .mobile-item-status.status-available { background: #e8f5e8; color: #2e7d32; }
        .mobile-item-status.status-sold { background: #ffebee; color: #c62828; }
        .mobile-item-status.status-reserved { background: #fff3e0; color: #ef6c00; }
        .mobile-item-merged { font-size: 10px; background: #e3f2fd; color: #1976d2; padding: 1px 6px; border-radius: 8px; }
        .mobile-item-meta { display: flex; align-items: center; gap: 10px; }
        .mobile-item-price { color: #ff6b00; font-weight: bold; font-size: 13px; }
        .mobile-item-owner { font-size: 12px; color: #999; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        
        /* ======== 九区全景样式 ======== */
        .pano-container {
            max-width: 1050px;
            margin: 0 auto;
            padding: 10px 0 30px;
        }
        .pano-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding: 0 5px;
        }
        .pano-title {
            font-size: 24px;
            font-weight: 800;
            color: #1a1a2e;
            letter-spacing: -0.3px;
        }
        .pano-total {
            font-size: 14px;
            color: #888;
            background: #f8f9fa;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .pano-total strong {
            color: #ff6b00;
            font-size: 18px;
            font-weight: 800;
        }

        .pano-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .pano-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            text-decoration: none;
            color: inherit;
            transition: all 0.35s ease;
            border: 1px solid #f0f0f0;
            display: block;
        }
        .pano-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
            border-color: transparent;
        }
        .pano-card.pano-hot { border-color: rgba(255,107,0,0.3); }
        .pano-card.pano-warm { border-color: rgba(255,152,0,0.3); }
        .pano-card.pano-cool { border-color: #e8e8e8; }
        
        .pano-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .pano-zone-label {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .pano-zone-stats {
            font-size: 13px;
            color: #666;
        }
        .pano-pct {
            color: #ff6b00;
            font-weight: bold;
        }
        .pano-card.pano-hot .pano-pct { color: #d84315; }
        .pano-card.pano-warm .pano-pct { color: #e65100; }
        
        .pano-mini-map {
            line-height: 0;
            margin-bottom: 8px;
        }
        
        /* 热力图图片 */
        .pano-heatmap {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 4px;
            image-rendering: pixelated;
        }
        
        .pano-merged-badge {
            font-size: 12px;
            color: #1976d2;
            font-weight: 500;
        }
        
        .pano-legend {
            display: flex;
            gap: 24px;
            justify-content: center;
            padding: 16px;
            font-size: 13px;
            color: #888;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .legend-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 4px;
            margin-right: 6px;
            vertical-align: middle;
        }
        .legend-dot.avail { background: #e8f5e8; border: 1px solid #c8e6c9; }
        .legend-dot.sold { background: #ff6b00; }
        .legend-dot.reserved { background: #ffca28; }
        .legend-dot.merged { background: #1976d2; }
        .legend-dot.cross { background: #a5d6a7; border: 2px dashed #ff9800; }

        .pano-cross-hint {
            text-align: center;
            padding: 16px 24px;
            background: linear-gradient(135deg, #f0f7ff, #fff8f0);
            border-radius: 12px;
            font-size: 13px;
            color: #666;
            margin: 0 0 16px 0;
            line-height: 1.7;
            border: 1px solid rgba(255,107,0,0.08);
        }

        /* ========== 全城九区合并大网格样式 ========== */
        .panorama-map-container {
            overflow-x: auto;
            padding: 16px;
        }
        .panorama-map {
            min-width: 800px;
        }
        .panorama-map .block-cell {
            width: 8px;
            height: 8px;
            border: none;
            font-size: 0;
            margin: 0;
        }
        .panorama-map .map-row {
            height: 8px;
        }
        .pano-zone-bar {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .pano-zone-tag {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: white;
            transition: transform 0.2s;
        }
        .pano-zone-tag:hover { transform: scale(1.05); text-decoration: none; color: white; }
        .zone-tag-A { background: #4caf50; }
        .zone-tag-B { background: #2196f3; }
        .zone-tag-C { background: #ff9800; }
        .zone-tag-D { background: #9c27b0; }
        .zone-tag-E { background: #009688; }
        .zone-tag-F { background: #e91e63; }
        .zone-tag-G { background: #3f51b5; }
        .zone-tag-H { background: #ffc107; color: #333; }
        .zone-tag-H:hover { color: #333; }
        .zone-tag-Z { background: #f44336; }

        /* 全景网格各区背景色 */
        .pano-cell.zone-bg-A { background-color: #e8f5e9; }
        .pano-cell.zone-bg-B { background-color: #e3f2fd; }
        .pano-cell.zone-bg-C { background-color: #fff3e0; }
        .pano-cell.zone-bg-D { background-color: #f3e5f5; }
        .pano-cell.zone-bg-E { background-color: #e0f2f1; }
        .pano-cell.zone-bg-F { background-color: #fce4ec; }
        .pano-cell.zone-bg-G { background-color: #e8eaf6; }
        .pano-cell.zone-bg-H { background-color: #fff8e1; }
        .pano-cell.zone-bg-Z { background-color: #ffebee; }

        .pano-cell.sold { background-color: #ff6b00 !important; }
        .pano-cell.reserved { background-color: #ffca28 !important; }
        .pano-cell.zone-boundary { border-right: 1px solid rgba(0,0,0,0.25) !important; }

        /* 单区网格优化 */
        .single-zone-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 12px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #fff8f5, #f0f7ff);
            border-radius: 8px;
            display: inline-block;
        }
        
        /* 全景响应式 */
        @media (max-width: 768px) {
            .panorama-map .block-cell {
                width: 6px;
                height: 6px;
            }
            .panorama-map .map-row {
                height: 6px;
            }
            .pano-zone-bar {
                gap: 4px;
            }
            .pano-zone-tag {
                padding: 4px 10px;
                font-size: 11px;
            }
        }
    </style>
	
<div class="city-header">
    <div class="container">
        <h1 class="city-title"><?= htmlspecialchars($city_name) ?>区块城市</h1>
        <div class="city-stats">
            <div class="stat-item">
                <span>居民数: <?= number_format($city_info['resident_count'] ?? 0) ?>人</span>
            </div>
            <div class="stat-item">
                <span>已激活区块: <?= number_format($city_info['activated_blocks'] ?? 0) ?>个</span>
            </div>
            <div class="stat-item">
                <span>城市人气: <?= number_format($city_info['popularity'] ?? 0) ?>点</span>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?= $success_message ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>
    
    <!-- 区域选择器 -->
    <div class="zone-selector">
        <div class="zone-tabs">
            <a href="?name=<?= $city_pinyin ?>&view=all" 
               class="zone-tab <?= $view_mode === 'panorama' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> 全城
            </a>
            <?php foreach (['A','B','C','D','E','F','G','H','Z'] as $zone): ?>
                <a href="?name=<?= $city_pinyin ?>&zone=<?= $zone ?>" 
                   class="zone-tab <?= ($view_mode === 'zone' && $current_zone == $zone) ? 'active' : '' ?>">
                    <?= $zone ?>区
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php if ($view_mode === 'panorama'): ?>
    <!-- ========== 九区全景模式 ========== -->
    <div class="pano-container">
        <div class="pano-header">
            <h2 class="pano-title"><?= htmlspecialchars($city_name) ?> · 九区全景</h2>
            <span class="pano-total">已激活: <strong><?= number_format($total_sold_all) ?></strong> / 89,991 个区块</span>
        </div>
        
        <!-- 全城九区合并大网格 -->
        <?php
        $all_blocks_map = [];
        foreach ($all_zones_data as $z => $zd) {
            foreach ($zd['blocks'] as $b) {
                $all_blocks_map[$b['block_number']] = ['status' => $b['status'], 'zone' => $z];
            }
        }
        ?>
        <div class="block-map-container panorama-map-container">
            <div class="pano-zone-bar">
                <?php foreach (['A','B','C','D','E','F','G','H','Z'] as $z): ?>
                    <a href="?name=<?= $city_pinyin ?>&zone=<?= $z ?>" class="pano-zone-tag zone-tag-<?= $z ?>"><?= $z ?>区</a>
                <?php endforeach; ?>
            </div>
            <div class="block-map panorama-map">
                <div class="map-rows">
                    <?php for ($row = 1; $row <= 99; $row++): ?>
                        <div class="map-row">
                            <?php for ($col = 1; $col <= 101; $col++): ?>
                                <?php
                                $block_number = str_pad($col, 2, '0', STR_PAD_LEFT) . str_pad($row, 2, '0', STR_PAD_LEFT);
                                $bz = $col_to_zone[$col] ?? null;
                                $bs = 'available';
                                if (isset($all_blocks_map[$block_number])) {
                                    $bs = $all_blocks_map[$block_number]['status'];
                                }
                                $bc = "block-cell pano-cell";
                                if ($bz) $bc .= " zone-bg-{$bz}";
                                $bc .= " {$bs}";
                                $is_boundary = in_array($col, [12,13,24,25,36,37,48,49,60,61,72,73,84,85,96,97]);
                                if ($is_boundary) $bc .= " zone-boundary";
                                ?>
                                <div class="<?= $bc ?>"
                                     data-block-number="<?= $block_number ?>"
                                     data-zone="<?= $bz ?>"
                                     data-status="<?= $bs ?>"
                                     title="<?= $bz ? $bz.'区 ' : '' ?><?= $block_number ?>">
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <div class="pano-legend">
            <span><span class="legend-dot avail"></span> 可认领</span>
            <span><span class="legend-dot sold"></span> 已认领</span>
            <span><span class="legend-dot reserved"></span> 已预订</span>
            <span>—— 各区域以不同底色区分，点击上方区标签可进入单区</span>
        </div>
        <div class="pano-cross-hint">
            <i class="fas fa-lightbulb"></i> <strong>跨区合并提示：</strong>相邻区域的边界区块可以跨区合并认领，打造横跨多区的大区块！
            点击上方区标签进入对应区地图，使用"相邻多区块"模式选择边界区块即可。
        </div>
    </div>
    <!-- ========== /九区全景模式 ========== -->
    
    <?php else: ?>
    <!-- ========== 单区详细模式 ========== -->
    <?php
    $col_range = $zone_col_ranges[$current_zone] ?? [1, 101];
    $col_start = $col_range[0];
    $col_end = $col_range[1];
    ?>
    <div class="row">
                                <div class="col-md-9">
            <!-- 桌面端：网格地图 -->
            <div class="block-map-container" id="desktopMap">
                <div class="single-zone-title">
                    <i class="fas fa-map-marked-alt"></i> <?= $current_zone ?>区区块地图
                </div>
                <div class="map-controls">
                    <div class="form-group">
                        <label>选择模式:</label>
                        <select id="selection-mode">
                            <option value="single">单个区块</option>
                            <option value="multiple">相邻多区块</option>
                        </select>
                    </div>
                    <div id="multiple-selection-info" style="display: none;">
                        <span>已选择 <span id="selected-count">0</span> 个区块</span>
                        <button id="clear-selection" class="btn btn-sm btn-default">清空选择</button>
                    </div>
                </div>

                <div class="block-map">
                    <div class="map-rows">
                        <?php for ($row = 1; $row <= 99; $row++): ?>
                            <div class="map-row">
                                <?php for ($col = $col_start; $col <= $col_end; $col++): ?>
                                   <?php
										$block_number = str_pad($col, 2, '0', STR_PAD_LEFT) . str_pad($row, 2, '0', STR_PAD_LEFT);
										$block_price = calculateBlockPrice($current_zone, $block_number, $zones);

										$block_status = 'available';
										$block_owner = null;
										$owner_name = null;
										$is_merged = false;
										$merged_size = '1x1';

										foreach ($zone_blocks as $zone_block) {
											if ($zone_block['block_number'] == $block_number) {
												$block_status = $zone_block['status'];
												$block_owner = $zone_block['owner_id'];
												$owner_name = $block_owner ? ($owners_map[$block_owner] ?? '用户'.$block_owner) : null;
												break;
											}
										}

										foreach ($merged_blocks as $merged) {
											if (in_array($block_number, explode(',', $merged['merged_blocks']))) {
												$is_merged = true;
												$merged_size = $merged['merge_size'];
												break;
											}
										}

										$block_class = "block-cell {$block_status}";
										if ($is_merged) {
											$block_class .= " merged {$merged_size}";
										}
										?>
										<div class="<?= $block_class ?>"
											 data-block-id="<?= $block_number ?>"
											 data-block-number="<?= $block_number ?>"
											 data-price="<?= $block_price ?>"
											 data-status="<?= $block_status ?>"
											 data-owner="<?= $block_owner ?>"
											 data-owner-name="<?= htmlspecialchars($owner_name ?? '') ?>"
											 data-row="<?= $row ?>"
											 data-col="<?= $col ?>"
											 title="区块 <?= $block_number ?> - 价格: <?= $block_price ?>元">
											<?php if (!$is_merged): ?>
												<?= $block_number ?>
											<?php endif; ?>
										</div>
                                <?php endfor; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- 移动端：列表视图 -->
            <div class="mobile-block-list" id="mobileList">
                <div class="mobile-list-header">
                    <h4><?= $current_zone ?>区 区块列表</h4>
                    <span class="mobile-list-count">共 <?= count($zone_blocks) ?> 条记录</span>
                </div>
                <div class="mobile-list-filter">
                    <button class="mobile-filter-btn active" data-filter="all">全部</button>
                    <button class="mobile-filter-btn" data-filter="available">可认领</button>
                    <button class="mobile-filter-btn" data-filter="sold">已认领</button>
                </div>
                <div class="mobile-list-items">
                    <?php
                    $listBlocks = [];
                    for ($row = 1; $row <= 99; $row++) {
                        for ($col = 1; $col <= 101; $col++) {
                            $bn = str_pad($col, 2, '0', STR_PAD_LEFT) . str_pad($row, 2, '0', STR_PAD_LEFT);
                            $price = calculateBlockPrice($current_zone, $bn, $zones);
                            $status = 'available';
                            $owner = null;
                            $oname = null;
                            foreach ($zone_blocks as $zb) {
                                if ($zb['block_number'] == $bn) {
                                    $status = $zb['status'];
                                    $owner = $zb['owner_id'];
                                    $oname = $owner ? ($owners_map[$owner] ?? '用户'.$owner) : null;
                                    break;
                                }
                            }
                            $isMerged = false;
                            foreach ($merged_blocks as $m) {
                                if (in_array($bn, explode(',', $m['merged_blocks']))) {
                                    $isMerged = true; break;
                                }
                            }
                            $listBlocks[] = ['n'=>$bn,'s'=>$status,'p'=>$price,'o'=>$oname,'m'=>$isMerged,'r'=>$row,'c'=>$col];
                        }
                    }
                    foreach ($listBlocks as $lb):
                    ?>
                    <div class="mobile-list-item" data-status="<?= $lb['s'] ?>" data-block="<?= $lb['n'] ?>">
                        <div class="mobile-item-main">
                            <span class="mobile-item-number"><?= $lb['n'] ?></span>
                            <span class="mobile-item-status status-<?= $lb['s'] ?>"><?= $lb['s']==='available'?'可认领':($lb['s']==='sold'?'已认领':'已预订') ?></span>
                            <?php if ($lb['m']): ?><span class="mobile-item-merged">合并</span><?php endif; ?>
                        </div>
                        <div class="mobile-item-meta">
                            <span class="mobile-item-price">¥<?= number_format($lb['p']) ?></span>
                            <?php if ($lb['o']): ?><span class="mobile-item-owner"><?= htmlspecialchars($lb['o']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="block-detail-panel">
                <div class="block-info">
                    <h3>区块信息</h3>
                    <div class="block-meta">
                        <div class="meta-item">
                            <span class="meta-label">区块编号:</span>
                            <span class="meta-value" id="detail-block-number">--</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">所属区域:</span>
                            <span class="meta-value"><?= $current_zone ?>区</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">状态:</span>
                            <span class="meta-value" id="detail-block-status">--</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">价格:</span>
                            <span class="meta-value price-highlight" id="detail-block-price">--</span>
                        </div>
                        <div class="meta-item" id="owner-info" style="display: none;">
                            <span class="meta-label">拥有者:</span>
                            <span class="meta-value" id="detail-block-owner">--</span>
                        </div>
                    </div>
                    <div class="block-actions" id="single-block-actions">
                        <button class="btn-buy" id="buy-button" disabled>选择区块查看详情</button>
                    </div>
                    <div class="block-actions" id="multiple-block-actions" style="display: none;">
                        <div class="selected-blocks-list" id="selected-blocks-list"></div>
                        <button class="btn-buy" id="claim-multiple-button">认领选中区块</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- ========== /单区详细模式 ========== -->
    <?php endif; ?>
</div>

<?php
// ========== 热门城市内链网格 ==========
$crossCities = $city->getHotCitiesList(12);
if (!empty($crossCities)):
?>
<div style="max-width:1200px;margin:40px auto;padding:0 15px;">
    <h3 style="font-size:20px;font-weight:bold;color:#333;margin-bottom:16px;">🔥 探索更多城市</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;">
        <?php foreach ($crossCities as $c): ?>
        <a href="<?= SeoHelper::cityUrl($c['pinyin']) ?>" 
           style="display:block;padding:10px 14px;background:#f8f8f8;border-radius:6px;text-decoration:none;color:#333;font-size:14px;text-align:center;transition:all 0.2s;"
           onmouseover="this.style.background='#ff6b00';this.style.color='#fff'"
           onmouseout="this.style.background='#f8f8f8';this.style.color='#333'">
            <?= htmlspecialchars($c['name']) ?>
        </a>
        <?php endforeach; ?>
        <a href="../all-cities.php" style="display:block;padding:10px 14px;background:#ff6b00;border-radius:6px;text-decoration:none;color:#fff;font-size:14px;text-align:center;font-weight:bold;">
            更多城市 →
        </a>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

<?php if ($view_mode === 'zone'): ?>
<script>
// 选中的区块数组
let selectedBlocks = [];
let selectionMode = 'single';

// 切换选择模式
document.getElementById('selection-mode').addEventListener('change', function() {
    selectionMode = this.value;
    const multipleInfo = document.getElementById('multiple-selection-info');
    
    if (selectionMode === 'multiple') {
        multipleInfo.style.display = 'block';
        clearSelection();
    } else {
        multipleInfo.style.display = 'none';
        clearSelection();
    }
});

// 清空选择
document.getElementById('clear-selection').addEventListener('click', clearSelection);

function clearSelection() {
    selectedBlocks = [];
    updateSelectionDisplay();
    document.querySelectorAll('.block-cell.selected').forEach(cell => {
        cell.classList.remove('selected');
    });
}

// 区块点击事件
document.querySelectorAll('.block-cell').forEach(cell => {
    cell.addEventListener('click', function() {
        const blockNumber = this.getAttribute('data-block-number');
        const blockStatus = this.getAttribute('data-status');
        const blockOwner = this.getAttribute('data-owner');
        const row = parseInt(this.getAttribute('data-row'));
        const col = parseInt(this.getAttribute('data-col'));
        
        if (selectionMode === 'single') {
            handleSingleSelection(this, blockNumber, blockStatus, blockOwner);
        } else {
            handleMultipleSelection(this, blockNumber, blockStatus, row, col);
        }
    });
});

// 单个区块选择处理
function handleSingleSelection(cell, blockNumber, blockStatus, blockOwner) {
    // 移除之前选中的样式
    document.querySelectorAll('.block-cell.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // 添加选中样式
    cell.classList.add('selected');
    
    // 更新详情面板
    updateBlockDetail(blockNumber, blockStatus, blockOwner);
    
    // 显示单个区块操作
    document.getElementById('single-block-actions').style.display = 'block';
    document.getElementById('multiple-block-actions').style.display = 'none';
}

// 多区块选择处理
function handleMultipleSelection(cell, blockNumber, blockStatus, row, col) {
    if (blockStatus !== 'available') {
        alert('只能选择可用的区块');
        return;
    }
    
    const index = selectedBlocks.findIndex(b => b.number === blockNumber);
    
    if (index > -1) {
        // 取消选择
        selectedBlocks.splice(index, 1);
        cell.classList.remove('selected');
    } else {
        // 检查是否相邻
        if (selectedBlocks.length > 0) {
            const isAdjacent = selectedBlocks.some(block => {
                const rowDiff = Math.abs(block.row - row);
                const colDiff = Math.abs(block.col - col);
                return (rowDiff <= 1 && colDiff <= 1) && (rowDiff + colDiff > 0);
            });
            
            if (!isAdjacent) {
                alert('只能选择相邻的区块');
                return;
            }
        }
        
        // 检查选择数量限制
        if (selectedBlocks.length >= 16) {
            alert('最多只能选择16个相邻区块');
            return;
        }
        
        // 添加选择
        selectedBlocks.push({
            number: blockNumber,
            row: row,
            col: col,
            status: blockStatus
        });
        cell.classList.add('selected');
    }
    
    updateSelectionDisplay();
}

// 更新选择显示
function updateSelectionDisplay() {
    document.getElementById('selected-count').textContent = selectedBlocks.length;
    document.getElementById('selected-blocks-list').innerHTML = selectedBlocks.map(block => 
        `<div class="selected-block-item">${block.number}</div>`
    ).join('');
    
    // 显示/隐藏多区块操作
    if (selectedBlocks.length > 0) {
        document.getElementById('single-block-actions').style.display = 'none';
        document.getElementById('multiple-block-actions').style.display = 'block';
    } else {
        document.getElementById('single-block-actions').style.display = 'block';
        document.getElementById('multiple-block-actions').style.display = 'none';
    }
}

// 更新区块详情
function updateBlockDetail(blockNumber, blockStatus, blockOwner) {
    const blockCell = document.querySelector(`[data-block-number="${blockNumber}"]`);
    const blockPrice = blockCell ? blockCell.getAttribute('data-price') : '--';
    
    document.getElementById('detail-block-number').textContent = blockNumber;
    document.getElementById('detail-block-price').textContent = blockPrice + '元';
    
    let statusText = '--';
    if (blockStatus === 'available') {
        statusText = '可认领';
    } else if (blockStatus === 'sold') {
        statusText = '已认领';
    } else if (blockStatus === 'reserved') {
        statusText = '已预订';
    }
    document.getElementById('detail-block-status').textContent = statusText;
    
    // 拥有者信息
    const ownerInfo = document.getElementById('owner-info');
    const ownerName = blockCell ? blockCell.getAttribute('data-owner-name') : '';
    if (blockOwner && blockOwner !== 'null') {
        document.getElementById('detail-block-owner').textContent = ownerName || ('用户' + blockOwner);
        ownerInfo.style.display = 'flex';
    } else {
        ownerInfo.style.display = 'none';
    }
    
    // 更新购买按钮
    const buyButton = document.getElementById('buy-button');
    if (blockStatus === 'available') {
        buyButton.textContent = '立即认领';
        buyButton.disabled = false;
        buyButton.onclick = function() {
            claimSingleBlock(blockNumber);
        };
    } else {
        buyButton.textContent = '不可认领';
        buyButton.disabled = true;
        buyButton.onclick = null;
    }
}

// 认领单个区块
function claimSingleBlock(blockNumber) {
    if (!confirm(`确定要认领区块 ${blockNumber} 吗？`)) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.name = 'action';
    actionInput.value = 'claim_block';
    form.appendChild(actionInput);
    
    const blockInput = document.createElement('input');
    blockInput.name = 'block_number';
    blockInput.value = blockNumber;
    form.appendChild(blockInput);
    
    document.body.appendChild(form);
    form.submit();
}

// 认领多个区块
document.getElementById('claim-multiple-button').addEventListener('click', function() {
    if (selectedBlocks.length === 0) return;
    
    const blockNumbers = selectedBlocks.map(block => block.number).join(', ');
    if (!confirm(`确定要认领以下 ${selectedBlocks.length} 个区块吗？\n${blockNumbers}`)) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.name = 'action';
    actionInput.value = 'claim_block';
    form.appendChild(actionInput);
    
    selectedBlocks.forEach((block, index) => {
        const blockInput = document.createElement('input');
        blockInput.name = `selected_blocks[${index}]`;
        blockInput.value = block.number;
        form.appendChild(blockInput);
    });
    
    document.body.appendChild(form);
    form.submit();
});

// 区块悬停效果
document.querySelectorAll('.block-cell').forEach(cell => {
    cell.addEventListener('mouseenter', function() {
        this.style.zIndex = '100';
    });

    cell.addEventListener('mouseleave', function() {
        if (!this.classList.contains('selected')) {
            this.style.zIndex = '';
        }
    });
});

// 移动端列表筛选
document.querySelectorAll('.mobile-filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.mobile-filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.getAttribute('data-filter');
        document.querySelectorAll('.mobile-list-item').forEach(item => {
            item.style.display = (filter === 'all' || item.getAttribute('data-status') === filter) ? 'flex' : 'none';
        });
    });
});
</script>
<?php endif; ?>

<style>
/* 合并区块样式 */
.block-cell.merged {
    background-color: #e3f2fd !important;
    border: 2px solid #2196f3 !important;
    font-weight: bold;
}

.block-cell.merged.2x1, .block-cell.merged.1x2 {
    background-color: #bbdefb !important;
}

.block-cell.merged.2x2 {
    background-color: #90caf9 !important;
}

.block-cell.merged.3x3, .block-cell.merged.4x4 {
    background-color: #64b5f6 !important;
    color: white !important;
}

/* 多选相关样式 */
.map-controls {
    margin-bottom: 15px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.selected-blocks-list {
    max-height: 150px;
    overflow-y: auto;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 5px;
}

.selected-block-item {
    padding: 2px 5px;
    margin: 2px;
    background-color: #e9ecef;
    border-radius: 3px;
    font-size: 12px;
    display: inline-block;
}
</style>

<?php
// 计算区块价格的函数（按行列递减）
function calculateBlockPrice($zone, $block_id, $zones) {
    if ($zone === 'Z') {
        // Z区三段式价格
        $num = intval($block_id);
        if ($num >= 9701 && $num <= 9999) {
            $base = 11429;
            $col = floor($num / 100); // 97-99
            $row = $num % 100;
        } elseif ($num >= 1 && $num <= 99) {
            $base = 34101;
            $col = 0; // 前导零视为第0列
            $row = $num;
        } else {
            $base = 34020;
            $col = floor($num / 100); // 01-98
            $row = $num % 100;
        }
        $price_decrease = $col + ($row - 1);
        return max($base - $price_decrease, 1);
    } else {
        // A-H区处理：按行列递减
        $col = floor($block_id / 100);
        $row = $block_id % 100;

        $base_price = $zones[$zone]['base_price'];

        // 价格递减规则：每增加一行减1元，每增加一列减1元
        $price_decrease = ($col - 1) + ($row - 1);
        $price = $base_price - $price_decrease;

        return max($price, 1); // 最低1元
    }
}
?>