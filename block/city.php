<?php
// city/[城市拼音].php
require_once '../config/database.php';
require_once '../config/block_prices.php';
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

// 加载统一区域配置
$zoneConfig = require __DIR__ . '/../config/zones.php';

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

// 各区的列范围（从统一配置生成，用于单区网格渲染）
$zone_col_ranges = [];
foreach ($zoneConfig as $z => $cfg) {
    $zone_col_ranges[$z] = [$cfg['col_start'], $cfg['col_end']];
}

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

// 个人在全景（9区）中拥有的区块总数
$user_total_owned = 0;
if ($current_user_id) {
    $utStmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE city_id = ? AND owner_id = ? AND status IN ('sold','reserved')");
    $utStmt->execute([$city_id, $current_user_id]);
    $user_total_owned = (int)$utStmt->fetchColumn();
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

    // 个人在当前区拥有的区块数
    $user_zone_owned = 0;
    if ($current_user_id) {
        $uzStmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE city_id = ? AND zone = ? AND owner_id = ? AND status IN ('sold','reserved')");
        $uzStmt->execute([$city_id, $current_zone, $current_user_id]);
        $user_zone_owned = (int)$uzStmt->fetchColumn();
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
                if (($_POST['ajax'] ?? '') === '1') {
                    $claimedNumbers = array_map(function($bn) { return str_pad($bn, 4, '0', STR_PAD_LEFT); }, $selected_blocks);
                    echo json_encode(['success' => true, 'message' => "成功认领 " . count($selected_blocks) . " 个区块！", 'block_numbers' => $claimedNumbers]); exit;
                }
                $success_message = "成功认领 " . count($selected_blocks) . " 个区块！";
                SeoHelper::baiduPush(SeoHelper::cityUrl($city_info['pinyin'] ?? $city_pinyin));
            } else {
                if (($_POST['ajax'] ?? '') === '1') {
                    echo json_encode(['success' => false, 'message' => "认领失败，请重试"]); exit;
                }
                $error_message = "认领失败，请重试";
            }
        } elseif ($block_number) {
            $result = $block->claimBlock($current_user_id, $city_id, $current_zone, $block_number);
            if ($result) {
                if (($_POST['ajax'] ?? '') === '1') {
                    echo json_encode(['success' => true, 'message' => "成功认领区块 {$block_number}！", 'block_number' => $block_number]); exit;
                }
                $success_message = "成功认领区块 {$block_number}！";
                SeoHelper::baiduPush(SeoHelper::cityUrl($city_info['pinyin'] ?? $city_pinyin));
            } else {
                if (($_POST['ajax'] ?? '') === '1') {
                    echo json_encode(['success' => false, 'message' => "认领失败，区块可能已被认领"]); exit;
                }
                $error_message = "认领失败，区块可能已被认领";
            }
        }
    }
    
    if ($action === 'unclaim_block') {
        $block_number = $_POST['block_number'] ?? '';
        if ($block_number) {
            $result = $block->unclaimBlock($current_user_id, $city_id, $current_zone, $block_number);
            if ($result) {
                if (($_POST['ajax'] ?? '') === '1') {
                    echo json_encode(['success' => true, 'message' => "已取消认领区块 {$block_number}", 'block_number' => $block_number]); exit;
                }
                $success_message = "已取消认领区块 {$block_number}";
            } else {
                if (($_POST['ajax'] ?? '') === '1') {
                    echo json_encode(['success' => false, 'message' => "取消认领失败，请确认该区块属于你"]); exit;
                }
                $error_message = "取消认领失败，请确认该区块属于你";
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
            padding: 20px 0 16px;
            margin-bottom: 16px;
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
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 6px;
            position: relative;
            letter-spacing: -0.5px;
        }

        .city-stats {
            display: flex;
            gap: 20px;
            font-size: 13px;
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
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
        
        /* ======== 区块地图 — 完全照搬 beijing.html 原版 flex 布局 ======== */
        .block-map-container {
            background-color: white;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 16px;
            overflow: auto;
        }

        /* 自定义确认弹窗 */
        .confirm-mask {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .confirm-mask.show { display: flex; }
        .confirm-box {
            background: #fff;
            width: 320px;
            max-width: 86vw;
            border-radius: 12px;
            padding: 20px 20px 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            font-size: 14px;
            color: #333;
        }
        .confirm-msg {
            line-height: 1.6;
            white-space: pre-wrap;
            margin-bottom: 12px;
        }
        .confirm-remember {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #888;
            margin-bottom: 16px;
            cursor: pointer;
            user-select: none;
        }
        .confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .confirm-actions button {
            border: none;
            border-radius: 8px;
            padding: 8px 18px;
            font-size: 14px;
            cursor: pointer;
        }
        .confirm-cancel { background: #f0f0f0; color: #555; }
        .confirm-ok { background: #337be6; color: #fff; }

        /* 区块颜色说明 */
        .block-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 12px 4px 4px;
            font-size: 13px;
            color: #666;
        }
        .block-legend .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .block-legend .legend-dot {
            width: 14px;
            height: 14px;
            border-radius: 2px;
            border: 1px solid #eee;
        }
        .lg-available { background: #fff; }
        .lg-sold-own { background: #d2ffc6; }
        .lg-sold-blue { background: #c6c9ff; }
        .lg-sold-red { background: #ffd5d5; }
        
        /* 原版: .list { display:flex; flex-direction:column; position:relative } */
        .block-list {
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        /* 原版: .row { width:100%; display:flex } */
        .block-row {
            width: 100%;
            display: flex;
        }
        
        /* 原版: .item { width:44px; height:44px; margin-left:1px; margin-top:1px; border-radius:2px; background:#fff; display:block; text-align:center } */
        .block-item {
            position: relative;
            width: 44px;
            height: 44px;
            margin-left: 1px;
            margin-top: 1px;
            border-radius: 2px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            flex-shrink: 0;
        }
        
        /* 原版颜色 (从 beijing.html 内联样式统计提取):
           可用: background:rgb(198,201,255)=#c6c9ff  (406次,最多)
           已售: background:rgb(255,213,213)=#ffd5d5  (358次)
           预留: background:rgb(210,255,198)=#d2ffc6  (366次)
           空白: background:rgb(255,255,255)=#fff      (121次) */
        .block-item.available {
            background: #fff;
            color: #999;
        }
        
        .block-item.sold-own {
            background: #d2ffc6;
            color: #35cc2d;
        }
        
        .block-item.sold-blue {
            background: #c6c9ff;
            color: #337be6;
        }
        
        .block-item.sold-red {
            background: #ffd5d5;
            color: #ff6060;
        }
        
        .block-item.reserved {
            background: #d2ffc6;
            color: #35cc2d;
        }
        
        .block-item.selected {
            outline: 2px solid #2196f3;
            outline-offset: -1px;
            z-index: 10;
        }
        
        .block-row .block-item:first-child {
            margin-left: 0;
        }
        
        /* 合并块边框由 .block-content 统一绘制，避免首格双边框 */
        .block-item.merged {
        }
        
        /* 原版: .blockNo { color:#999; font-size:14px; line-height:44px } 
           (最后一条CSS覆盖了前面的 color:#337be6;font-size:22px) */
        .block-no {
            font-size: 14px;
            font-family: PingFangSC-Regular, 'Microsoft YaHei', sans-serif;
            line-height: 44px;
            pointer-events: none;
        }
        
        .block-item.available .block-no { color: #999; }
        .block-item.sold-own .block-no { color: #35cc2d; }
        .block-item.sold-blue .block-no { color: #337be6; }
        .block-item.sold-red .block-no { color: #ff6060; }
        .block-item.reserved .block-no { color: #35cc2d; }
        
        /* 合并块的内容层 — 绝对定位覆盖相邻格（原版 blockItem 用内联 width/height 溢出） */
        .block-content {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 2px;
            overflow: hidden;
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
            text-align: center;
        }
        
        /* 合并块状态色（和 .block-item 一致） */
        .block-content.available { background: #fff; color: #999; }
        .block-content.sold-own { background: #d2ffc6; color: #35cc2d; }
        .block-content.sold-blue { background: #c6c9ff; color: #337be6; }
        .block-content.sold-red { background: #ffd5d5; color: #ff6060; }
        .block-content.reserved { background: #d2ffc6; color: #35cc2d; }
        .block-content.merged { border: none; }
        
        /* 合并块的非首格占位 — 透明 */
        .block-item.merged-placeholder {
            background: transparent;
            cursor: default;
        }

        /* Z区标准列与特殊列之间的空列分隔 */
        .block-item.blank-cell {
            background: transparent;
            border: none;
            box-shadow: none;
            cursor: default;
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

        .btn-unclaim {
            display: block;
            width: 100%;
            background: #fff;
            color: #e53935;
            border: 2px solid #e53935;
            padding: 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
        }

        .btn-unclaim:hover:not(:disabled) {
            background: #e53935;
            color: #fff;
        }

        .btn-unclaim:disabled {
            display: none;
        }

        /* 价格 + 操作按钮组合行（桌面端：价格在上、按钮在下；移动端：两者同行） */
        .block-buy-row {
            margin-top: 20px;
        }
        .block-buy-row .block-price {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-bottom: 12px;
        }
        .block-buy-row .block-price .meta-label {
            font-weight: 600;
            color: #888;
            font-size: 13px;
        }
        
        /* 响应式设计 */
        @media (max-width: 992px) {
            .block-map-container {
                overflow-x: auto;
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

            /* 移动端：显示真实网格（可缩放），隐藏扁平列表 */
            #desktopMap { display: block !important; }
            #mobileList { display: none !important; }
            /* 区块选择区尽量占满屏幕宽度，仅留极小边距 */
            .container { padding-left: 2px; padding-right: 2px; }
            .block-map-container {
                overflow: hidden;
                padding: 2px 0;
            }
            /* 单区网格在移动端按内容真实宽度排布，便于缩放计算 */
            .block-list { width: max-content; }

            /* 缩放视口：手势完全接管（pan / pinch），尽量放大可操作空间 */
            .zoom-viewport {
                position: relative;
                width: 100%;
                height: 78vh;
                overflow: hidden;
                touch-action: none;
                -webkit-overflow-scrolling: touch;
            }
            .zoom-scaler { position: relative; }
            .zoom-content {
                transform-origin: 0 0;
                will-change: transform;
            }

            /* 缩放按钮（仅移动端显示） */
            .zoom-controls {
                position: fixed;
                right: 12px;
                bottom: calc(44vh + 12px);
                z-index: 210;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .zoom-controls button {
                width: 42px;
                height: 42px;
                border-radius: 50%;
                border: 1px solid #ddd;
                background: #fff;
                color: #333;
                font-size: 22px;
                line-height: 1;
                box-shadow: 0 2px 8px rgba(0,0,0,.15);
                cursor: pointer;
            }

            /* 详情面板 → 底部吸底操作条（点选/多选/认领都在此） */
            .block-detail-panel {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                top: auto;
                z-index: 200;
                border-radius: 14px 14px 0 0;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.10);
                padding: 4px 12px 6px;
                max-height: none;
                overflow-y: auto;
            }
            /* 移动端默认就紧凑：去掉大标题，元信息压成一行小字 */
            .block-detail-panel .block-info h3 { display: none; }
            .block-detail-panel .block-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 1px 12px;
                margin-bottom: 2px;
            }
            .block-detail-panel .meta-item { margin-bottom: 0; font-size: 11px; }
            .block-detail-panel .meta-label { font-size: 11px; color: #999; }
            .block-detail-panel .meta-value { font-size: 12px; }

            /* 价格与“立即领取”按钮放在同一行，并进一步压低高度 */
            .block-detail-panel .block-buy-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                margin-top: 2px;
            }
            .block-detail-panel .block-buy-row .block-price {
                margin-bottom: 0;
                flex-shrink: 0;
            }
            .block-detail-panel .block-buy-row .block-price .price-highlight {
                font-size: 16px;
            }
            .block-detail-panel .block-buy-row .block-actions {
                display: flex;
                flex: 1;
                gap: 8px;
                margin-top: 0;
                justify-content: flex-end;
            }
            .block-detail-panel .block-buy-row .block-actions .btn-buy {
                flex: 1;
                width: auto;
                padding: 8px 10px;
                font-size: 14px;
                border-radius: 8px;
            }
            .block-detail-panel .block-buy-row .block-actions .btn-unclaim {
                flex: 1;
                margin-top: 0;
                padding: 8px 10px;
                font-size: 14px;
                border-radius: 8px;
            }
            /* 操作按钮压小，不再占满整行（多选认领场景） */
            .block-detail-panel .block-actions { display: flex; flex-wrap: wrap; gap: 8px; }
            .block-detail-panel .btn-buy {
                width: auto;
                padding: 9px 18px;
                font-size: 14px;
                border-radius: 8px;
            }
            body { padding-bottom: 76px; }
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
            max-width: 1200px;
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
            border: 1px solid #ddd;
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
            display: grid;
            gap: 1px;
            background: #f2f2f2;
            padding: 1px;
            border-radius: 2px;
            grid-auto-rows: 10px;
            /* grid-template-columns: repeat(101, 10px); set inline */
            min-width: fit-content;
        }
        .panorama-map .block-cell {
            width: 10px;
            height: 10px;
            border: none;
            font-size: 0;
            margin: 0;
            border-radius: 0;
        }
        /* 全景合并块：跨格合并为一个整体大块，显示最小编号（与单区 A 区一致，无额外边框） */
        .pano-cell.merged {
            width: auto !important;
            height: auto !important;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        .pano-cell.merged .pano-merge-no {
            font-size: 6px;
            line-height: 1;
            font-weight: 600;
            pointer-events: none;
            white-space: nowrap;
            overflow: hidden;
            max-width: 100%;
        }
        /* 编号颜色与 A 区 .block-content 状态色保持一致 */
        .pano-cell.available.merged .pano-merge-no { color: #999; }
        .pano-cell.sold-own.merged .pano-merge-no { color: #35cc2d; }
        .pano-cell.sold-blue.merged .pano-merge-no { color: #337be6; }
        .pano-cell.sold-red.merged .pano-merge-no { color: #ff6060; }
        .pano-cell.reserved.merged .pano-merge-no { color: #35cc2d; }
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

        /* 全景网格各区背景色 — 统一白底（去除彩色背景） */
        .pano-cell.zone-bg-A,
        .pano-cell.zone-bg-B,
        .pano-cell.zone-bg-C,
        .pano-cell.zone-bg-D,
        .pano-cell.zone-bg-E,
        .pano-cell.zone-bg-F,
        .pano-cell.zone-bg-G,
        .pano-cell.zone-bg-H,
        .pano-cell.zone-bg-Z {
            background-color: #fff;
        }

        .pano-cell.sold-own { background-color: #d2ffc6 !important; }
        .pano-cell.sold-blue { background-color: #c6c9ff !important; }
        .pano-cell.sold-red { background-color: #ffd5d5 !important; }
        .pano-cell.reserved { background-color: #d2ffc6 !important; }
        .pano-cell.zone-boundary { border-right: 1px solid rgba(0,0,0,0.10) !important; }

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

        /* Z区特殊区块（顶层/左侧边框区块） */
        .special-section {
            margin-top: 18px;
            padding: 14px 16px;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 10px;
        }
        .special-title {
            font-size: 16px;
            font-weight: 700;
            color: #b3700a;
            margin: 0 0 10px;
        }
        .special-subtitle {
            font-size: 13px;
            color: #999;
            margin: 10px 0 4px;
        }
        .special-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
            max-width: 100%;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .special-strip .block-item {
            margin: 1px;
            flex-shrink: 0;
        }

        /* 全景响应式 */
        @media (max-width: 768px) {
            .panorama-map .block-cell {
                width: 8px;
                height: 8px;
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
    
    <!-- 移动端缩放通用逻辑（双指缩放 / 单指平移 / 缩放按钮）；桌面端不生效 -->
    <script>
    window.initPinchZoom = function(containerSel, contentSel) {
        if (!window.matchMedia('(max-width: 768px)').matches) return;
        var container = document.querySelector(containerSel);
        if (!container) return;
        var content = container.querySelector(contentSel);
        if (!content) return;
        if (container.dataset.zoomReady) return;
        container.dataset.zoomReady = '1';

        var viewport = document.createElement('div');
        viewport.className = 'zoom-viewport';
        var scaler = document.createElement('div');
        scaler.className = 'zoom-scaler';

        container.insertBefore(viewport, content);
        viewport.appendChild(scaler);
        scaler.appendChild(content);
        content.classList.add('zoom-content');

        var baseW = content.offsetWidth;
        var baseH = content.offsetHeight;
        var scale = 1, tx = 0, ty = 0;
        var MIN = 0.2, MAX = 4;

        function clamp() {
            var vw = viewport.clientWidth, vh = viewport.clientHeight;
            var sw = baseW * scale, sh = baseH * scale;
            tx = (sw <= vw) ? (vw - sw) / 2 : Math.max(vw - sw, Math.min(0, tx));
            ty = (sh <= vh) ? (vh - sh) / 2 : Math.max(vh - sh, Math.min(0, ty));
        }
        // 仅在初始化时设定一次 scaler 尺寸，后续每帧只改 transform，避免重排导致的卡顿
        scaler.style.width = baseW + 'px';
        scaler.style.height = baseH + 'px';
        function apply() {
            content.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + scale + ')';
        }
        function zoomTo(ns, cx, cy) {
            ns = Math.max(MIN, Math.min(MAX, ns));
            var px = (cx - tx) / scale;
            var py = (cy - ty) / scale;
            scale = ns;
            tx = cx - px * scale;
            ty = cy - py * scale;
            clamp(); apply();
        }

        // 初始：宽度自适应铺满，便于“一屏看全”
        scale = Math.min(1, (viewport.clientWidth / baseW)) || 1;
        clamp(); apply();

        // 缩放按钮
        var ctr = document.createElement('div');
        ctr.className = 'zoom-controls';
        ctr.innerHTML = '<button type="button" data-z="in" aria-label="放大">+</button>' +
                        '<button type="button" data-z="out" aria-label="缩小">−</button>' +
                        '<button type="button" data-z="reset" aria-label="复位">⟲</button>';
        container.appendChild(ctr);
        ctr.querySelector('[data-z="in"]').addEventListener('click', function () {
            zoomTo(scale * 1.3, viewport.clientWidth / 2, viewport.clientHeight / 2);
        });
        ctr.querySelector('[data-z="out"]').addEventListener('click', function () {
            zoomTo(scale / 1.3, viewport.clientWidth / 2, viewport.clientHeight / 2);
        });
        ctr.querySelector('[data-z="reset"]').addEventListener('click', function () {
            scale = Math.min(1, (viewport.clientWidth / baseW)) || 1;
            clamp(); apply();
        });

        // 手势处理
        var mode = 0, lastX = 0, lastY = 0, moved = false;
        var startDist = 0, startScale = 1, startMidX = 0, startMidY = 0, startTx = 0, startTy = 0;
        var suppressClick = false;
        var startPX = 0, startPY = 0;

        // 拖拽后抑制误触发的点击事件（避免平移后误选区块）
        viewport.addEventListener('click', function (e) {
            if (suppressClick) { e.stopPropagation(); e.preventDefault(); suppressClick = false; }
        }, true);

        viewport.addEventListener('touchstart', function (e) {
            suppressClick = false;   // 新的一次触摸：先解除上一次可能残留的抑制，避免“整屏点不了”
            if (e.touches.length === 1) {
                mode = 1; moved = false;
                lastX = e.touches[0].clientX; lastY = e.touches[0].clientY;
                startPX = e.touches[0].clientX; startPY = e.touches[0].clientY;
            } else if (e.touches.length === 2) {
                mode = 2; moved = true;
                var a = e.touches[0], b = e.touches[1];
                startDist = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY) || 1;
                startScale = scale;
                var r = viewport.getBoundingClientRect();
                startMidX = (a.clientX + b.clientX) / 2 - r.left;
                startMidY = (a.clientY + b.clientY) / 2 - r.top;
                startTx = tx; startTy = ty;
                e.preventDefault();
            }
        }, { passive: false });

        viewport.addEventListener('touchmove', function (e) {
            if (mode === 1 && e.touches.length === 1) {
                var dx = e.touches[0].clientX - lastX;
                var dy = e.touches[0].clientY - lastY;
                // 仅当位移明显（>8px）才视为平移，避免手指抖动误判成拖动
                if (Math.hypot(e.touches[0].clientX - startPX, e.touches[0].clientY - startPY) > 8) moved = true;
                tx += dx; ty += dy;
                lastX = e.touches[0].clientX; lastY = e.touches[0].clientY;
                clamp(); apply();
                e.preventDefault();
            } else if (mode === 2 && e.touches.length === 2) {
                var a = e.touches[0], b = e.touches[1];
                var dist = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY) || 1;
                var r = viewport.getBoundingClientRect();
                var midX = (a.clientX + b.clientX) / 2 - r.left;
                var midY = (a.clientY + b.clientY) / 2 - r.top;
                var px = (startMidX - startTx) / startScale;
                var py = (startMidY - startTy) / startScale;
                scale = Math.max(MIN, Math.min(MAX, startScale * (dist / startDist)));
                tx = midX - px * scale;
                ty = midY - py * scale;
                clamp(); apply();
                e.preventDefault();
            }
        }, { passive: false });

        viewport.addEventListener('touchend', function (e) {
            if (e.touches.length === 0) {
                mode = 0;
                suppressClick = moved;   // 仅真正平移/缩放后才吞掉随后的 click
            } else if (e.touches.length === 1) {
                mode = 1; moved = true;
                lastX = e.touches[0].clientX; lastY = e.touches[0].clientY;
            }
        }, { passive: false });
    };
    </script>

    <?php if ($view_mode === 'panorama'): ?>
    <!-- ========== 九区全景模式 ========== -->
    <div class="pano-container">
        <div class="pano-header">
            <h2 class="pano-title"><?= htmlspecialchars($city_name) ?> · 九区全景</h2>
            <span class="pano-total">已激活: <strong><?= number_format($total_sold_all) ?></strong> / 9999 个区块</span>
            <span class="pano-total">我拥有: <strong><?= number_format($user_total_owned) ?></strong> 个区块</span>
        </div>
        
        <!-- 全城九区合并大网格 -->
        <?php
        $all_blocks_map = [];
        foreach ($all_zones_data as $z => $zd) {
            foreach ($zd['blocks'] as $b) {
                $all_blocks_map[$b['block_number']] = ['status' => $b['status'], 'zone' => $z, 'owner' => $b['owner_id'] ?? null];
            }
        }
        ?>
        <?php
        // 构建全景合并块映射：区块号 => [是否首格, 最小编号, 跨列数, 跨行数]
        $pano_merged_map = [];
        foreach ($all_zones_data as $z => $zd) {
            foreach ($zd['merged'] as $m) {
                $nums = array_map('trim', explode(',', $m['merged_blocks']));
                if (empty($nums)) continue;
                $minNumber = min($nums);
                $parts = explode('x', $m['merge_size']);
                $colSpan = (int)($parts[0] ?? 1);
                $rowSpan = (int)($parts[1] ?? 1);
                // 首格 = 行号最小，同行取列号最小（左上角）
                $minC = 999; $minR = 999;
                foreach ($nums as $mn) {
                    $mc = (int)substr($mn, 0, 2);
                    $mr = (int)substr($mn, 2, 2);
                    if ($mr < $minR || ($mr === $minR && $mc < $minC)) { $minR = $mr; $minC = $mc; }
                }
                foreach ($nums as $mn) {
                    $mc = (int)substr($mn, 0, 2);
                    $mr = (int)substr($mn, 2, 2);
                    $pano_merged_map[$mn] = [
                        'is_first' => ($mc === $minC && $mr === $minR),
                        'min' => $minNumber,
                        'cols' => $colSpan,
                        'rows' => $rowSpan,
                    ];
                }
            }
        }
        ?>
        <div class="block-map-container panorama-map-container">
            <div class="pano-zone-bar">
                <?php foreach (['A','B','C','D','E','F','G','H','Z'] as $z): ?>
                    <a href="?name=<?= $city_pinyin ?>&zone=<?= $z ?>" class="pano-zone-tag zone-tag-<?= $z ?>"><?= $z ?>区</a>
                <?php endforeach; ?>
            </div>
            <div class="block-map panorama-map" style="grid-template-columns: repeat(101, 10px);">
                    <?php for ($row = 1; $row <= 99; $row++): ?>
                        <?php for ($col = 1; $col <= 101; $col++):
                            $block_number = str_pad($col, 2, '0', STR_PAD_LEFT) . str_pad($row, 2, '0', STR_PAD_LEFT);
                            $bz = $col_to_zone[$col] ?? null;
                            $bs = 'available';
                            $owner = null;
                            if (isset($all_blocks_map[$block_number])) {
                                $bs = $all_blocks_map[$block_number]['status'];
                                $owner = $all_blocks_map[$block_number]['owner'];
                            }

                            // 合并块：非首格跳过渲染（首格的跨格 span 已占据对应网格位置）
                            $merged_info = $pano_merged_map[$block_number] ?? null;
                            if ($merged_info && !$merged_info['is_first']) {
                                continue;
                            }

                            // 与单区保持一致的状态类：自己认领=绿，别人认领=蓝/红，未认领=白
                            $cell_class = $bs;
                            if ($bs === 'sold') {
                                if ($current_user_id && $owner && (int)$owner === (int)$current_user_id) {
                                    $cell_class = 'sold-own';
                                } else {
                                    $cell_class = (crc32($block_number) % 2 === 0) ? 'sold-blue' : 'sold-red';
                                }
                            }
                            $bc = "block-cell pano-cell";
                            if ($bz) $bc .= " zone-bg-{$bz}";
                            $bc .= " {$cell_class}";

                            $span_style = '';
                            if ($merged_info && $merged_info['is_first']) {
                                // 合并首格：跨列/跨行合并显示为一个整体大块
                                $bc .= " merged";
                                $span_style = "grid-column: span {$merged_info['cols']}; grid-row: span {$merged_info['rows']};";
                            } else {
                                $is_boundary = in_array($col, [12,24,36,48,60,72,84,96]);
                                if ($is_boundary) $bc .= " zone-boundary";
                            }

                            $title_text = $bz ? $bz.'区 ' : '';
                            if ($merged_info) {
                                $title_text .= "合并区块 {$merged_info['cols']}x{$merged_info['rows']} (编号 {$merged_info['min']})";
                            } else {
                                $title_text .= $block_number;
                            }
                        ?>
                        <div class="<?= $bc ?>" style="<?= $span_style ?>"
                             data-block-number="<?= $block_number ?>"
                             data-zone="<?= $bz ?>"
                             data-status="<?= $bs ?>"
                             title="<?= $title_text ?>">
                            <?php if ($merged_info && $merged_info['is_first']): ?><span class="pano-merge-no"><?= $merged_info['min'] ?></span><?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    <?php endfor; ?>
            </div>
        </div>
        
        <div class="pano-legend">
            <span><span class="legend-dot lg-available"></span> 未认领</span>
            <span><span class="legend-dot lg-sold-own"></span> 自己认领</span>
            <span><span class="legend-dot lg-sold-blue"></span> 别人认领</span>
            <span><span class="legend-dot lg-sold-red"></span> 别人认领</span>
            <span>—— 点击上方区标签可进入单区</span>
        </div>
        <div class="pano-cross-hint">
            <i class="fas fa-lightbulb"></i> <strong>跨区合并提示：</strong>相邻区域的边界区块可以跨区合并认领，打造横跨多区的大区块！
            点击上方区标签进入对应区地图，使用"相邻多区块"模式选择边界区块即可。
        </div>
    </div>
    <!-- ========== /九区全景模式 ========== -->

    <script>initPinchZoom('.panorama-map-container', '.panorama-map');</script>

    <?php else: ?>
    <!-- ========== 单区详细模式 ========== -->
    <?php
    $col_range = $zone_col_ranges[$current_zone] ?? [1, 101];
    $col_start = $col_range[0];
    $col_end = $col_range[1];
    ?>
    <div class="row">
                                <div class="col-md-9">
            <div class="single-zone-title"><?= $current_zone ?>区 · 我拥有 <strong style="color:#ff6b00;"><?= number_format($user_zone_owned) ?></strong> 个区块</div>
            <!-- 桌面端：网格地图 -->
            <div class="block-map-container" id="desktopMap">
                <div class="map-controls" style="margin-bottom:8px;">
                    <div id="multiple-selection-info" style="display: none;">
                        <span>已选择 <span id="selected-count">0</span> 个区块</span>
                        <button id="clear-selection" class="btn btn-sm btn-default">清空选择</button>
                    </div>
                </div>

                <div class="block-list">
                    <?php
                    // Z区：标准3列(97-99) + 空2列 + 特殊两列（左列 0001-0099 / 顶列 0100-9900）
                    if ($current_zone === 'Z') {
                        $z_cols = [97, 98, 99, 'blank', 'blank', 'special-left', 'special-top'];
                    } else {
                        $z_cols = range($col_start, $col_end);
                    }
                    ?>
                    <?php for ($row = 1; $row <= 99; $row++): ?>
                    <div class="block-row">
                        <?php foreach ($z_cols as $spec):
                            if ($spec === 'blank') {
                                echo '<div class="block-item blank-cell"></div>';
                                continue;
                            }
                            if ($spec === 'special-left') {
                                $cell_col = 0; $cell_row = $row;
                                $block_number = '00' . str_pad($row, 2, '0', STR_PAD_LEFT);
                            } elseif ($spec === 'special-top') {
                                $cell_col = $row; $cell_row = 0;
                                $block_number = str_pad($row, 2, '0', STR_PAD_LEFT) . '00';
                            } else {
                                $cell_col = $spec; $cell_row = $row;
                                $block_number = str_pad($cell_col, 2, '0', STR_PAD_LEFT) . str_pad($cell_row, 2, '0', STR_PAD_LEFT);
                            }
                            $block_price = calculateBlockPrice($current_zone, $block_number);

                            $block_status = 'available';
                            $block_owner = null;
                            $owner_name = null;
                            $is_merged = false;
                            $merged_size = '1x1';
                            $is_merged_first = false;
                            $merged_min_number = '';
                            $colSpan = 1; $rowSpan = 1;

                            foreach ($zone_blocks as $zone_block) {
                                if ($zone_block['block_number'] == $block_number) {
                                    $block_status = $zone_block['status'];
                                    $block_owner = $zone_block['owner_id'];
                                    $owner_name = $block_owner ? ($owners_map[$block_owner] ?? '用户'.$block_owner) : null;
                                    break;
                                }
                            }

                            foreach ($merged_blocks as $merged) {
                                $mergedNums = explode(',', $merged['merged_blocks']);
                                if (in_array($block_number, $mergedNums)) {
                                    $is_merged = true;
                                    $merged_size = $merged['merge_size'];
                                    // 合并块编号统一取组内最小编号
                                    $merged_min_number = min($mergedNums);
                                    $minR = 999; $minC = 999;
                                    foreach ($mergedNums as $mn) {
                                        $mr = intval(substr($mn, 2, 2));
                                        $mc = intval(substr($mn, 0, 2));
                                        if ($mr < $minR) $minR = $mr;
                                        if ($mc < $minC) $minC = $mc;
                                    }
                                    if ($cell_row == $minR && $cell_col == $minC) {
                                        $is_merged_first = true;
                                        $parts = explode('x', $merged['merge_size']);
                                        $colSpan = intval($parts[0]);
                                        $rowSpan = intval($parts[1]);
                                    }
                                    break;
                                }
                            }

                            // 每个格子都渲染（保持 flex 布局正确），合并块用绝对定位覆盖
                            if ($is_merged && !$is_merged_first) {
                                // 合并块的非首格：透明占位
                                echo '<div class="block-item merged-placeholder"></div>';
                                continue;
                            }

                            // 根据拥有者映射为新的状态类
                            $cell_status_class = $block_status;
                            if ($block_status === 'sold') {
                                if ($current_user_id && $block_owner == $current_user_id) {
                                    $cell_status_class = 'sold-own';
                                } else {
                                    $cell_status_class = (crc32($block_number) % 2 === 0) ? 'sold-blue' : 'sold-red';
                                }
                            }

                            $cell_class = "block-item {$cell_status_class}";
                            if ($is_merged_first) $cell_class .= " merged";

                            // 合并首格：计算 block-content 尺寸（原版用内联 width/height）
                            $content_style = '';
                            $content_class = '';
                            if ($is_merged_first) {
                                $w = $colSpan * 44 + ($colSpan - 1);
                                $h = $rowSpan * 44 + ($rowSpan - 1);
                                $content_style = "width:{$w}px;height:{$h}px;";
                                $content_class = " {$cell_status_class} merged";
                            }
                        ?>
                        <div class="<?= $cell_class ?>"
                             data-block-number="<?= $block_number ?>"
                             data-price="<?= $block_price ?>"
                             data-status="<?= $block_status ?>"
                             data-owner="<?= $block_owner ?>"
                             data-owner-name="<?= htmlspecialchars($owner_name ?? '') ?>"
                             data-row="<?= $cell_row ?>"
                             data-col="<?= $cell_col ?>"
                             title="<?= $is_merged ? "合并区块 {$merged_size}" : "区块 {$block_number}" ?> - 价格: <?= $block_price ?>元">
                            <?php if ($is_merged_first): ?>
                            <div class="block-content<?= $content_class ?>" style="<?= $content_style ?>"><?= $merged_min_number ?></div>
                            <?php else: ?>
                            <span class="block-no"><?= $block_number ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- 区块颜色说明 -->
            <div class="block-legend">
                <span class="legend-item"><span class="legend-dot lg-available"></span> 未认领</span>
                <span class="legend-item"><span class="legend-dot lg-sold-own"></span> 自己认领</span>
                <span class="legend-item"><span class="legend-dot lg-sold-blue"></span> 别人认领</span>
                <span class="legend-item"><span class="legend-dot lg-sold-red"></span> 别人认领</span>
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
                            $price = calculateBlockPrice($current_zone, $bn);
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
                    if ($current_zone === 'Z'):
                        foreach ($zone_blocks as $zb) {
                            $zbn = (string)$zb['block_number'];
                            $zc = intval(substr($zbn, 0, 2)); $zr = intval(substr($zbn, 2, 2));
                            if (($zr === 0 && $zc >= 1) || ($zc === 0 && $zr >= 1)) {
                                $zon = $zb['owner_id'] ? ($owners_map[$zb['owner_id']] ?? '用户'.$zb['owner_id']) : null;
                                $listBlocks[] = ['n'=>$zbn,'s'=>$zb['status'],'p'=>$zb['price'],'o'=>$zon,'m'=>false,'r'=>$zr,'c'=>$zc];
                            }
                        }
                    endif;
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
            <div class="block-detail-panel" id="blockDetailPanel">
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
                        <div class="meta-item" id="owner-info" style="display: none;">
                            <span class="meta-label">拥有者:</span>
                            <span class="meta-value" id="detail-block-owner">--</span>
                        </div>
                    </div>
                    <div class="block-buy-row">
                        <div class="block-price">
                            <span class="meta-label">价格:</span>
                            <span class="price-highlight" id="detail-block-price">--</span>
                        </div>
                        <div class="block-actions" id="single-block-actions">
                            <button class="btn-buy" id="buy-button" disabled>选择区块查看详情</button>
                            <button class="btn-unclaim" id="unclaim-button" style="display:none;" disabled>取消认领</button>
                        </div>
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

    <!-- 自定义确认弹窗（替代原生 confirm，避免被浏览器“阻止对话框”静默取消） -->
    <div class="confirm-mask" id="confirmMask">
        <div class="confirm-box">
            <div class="confirm-msg" id="confirmMsg"></div>
            <label class="confirm-remember">
                <input type="checkbox" id="confirmRemember"> 不再提醒
            </label>
            <div class="confirm-actions">
                <button class="confirm-cancel" id="confirmCancel">取消</button>
                <button class="confirm-ok" id="confirmOk">确定</button>
            </div>
        </div>
    </div>
</div>

<?php
// ========== 该城市热门区块 ==========
if ($view_mode === 'zone' && !empty($current_zone)):
    $hotBlocksStmt = $pdo->prepare("SELECT block_number, zone, status, owner_id FROM blocks WHERE city_id = ? AND zone = ? ORDER BY status = 'sold' DESC, block_number ASC LIMIT 12");
    $hotBlocksStmt->execute([$city_id, $current_zone]);
    $hotBlocks = $hotBlocksStmt->fetchAll();
    if (!empty($hotBlocks)):
?>
<div style="max-width:1200px;margin:30px auto 10px;padding:0 15px;">
    <h3 style="font-size:20px;font-weight:bold;color:#333;margin-bottom:16px;">🏘 <?= htmlspecialchars($city_name) ?> <?= htmlspecialchars($current_zone) ?>区 热门区块</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;">
        <?php foreach ($hotBlocks as $hb): 
            $hbPrice = calculateBlockPriceNew($current_zone, $hb['block_number']);
            $hbStatus = $hb['status'] === 'sold' ? ($hb['owner_id'] == $current_user_id ? '已认领(我)' : '已售') : '可认领';
        ?>
        <a href="city.php?name=<?= urlencode($city_pinyin) ?>&zone=<?= $current_zone ?>" style="display:block;padding:12px;background:#fff;border-radius:8px;border:1px solid #eee;text-decoration:none;text-align:center;transition:all .2s;" onmouseover="this.style.borderColor='#ff6b00'" onmouseout="this.style.borderColor='#eee'">
            <div style="font-size:16px;font-weight:700;color:#333;"><?= htmlspecialchars($hb['block_number']) ?></div>
            <div style="font-size:12px;color:<?= $hbStatus === '可认领' ? '#27ae60' : '#999' ?>;margin-top:2px;"><?= $hbStatus ?></div>
            <div style="font-size:14px;color:#ff6b00;font-weight:600;margin-top:4px;">¥<?= number_format($hbPrice) ?></div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; endif; ?>

<?php
// ========== 热门城市内链网格 ==========
$crossCities = $city->getHotCitiesList(12);
if (!empty($crossCities)):
?>
<div style="max-width:1200px;margin:40px auto;padding:0 15px;">
    <h3 style="font-size:20px;font-weight:bold;color:#333;margin-bottom:16px;">🔥 探索更多城市</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;">
        <?php foreach ($crossCities as $c): ?>
        <a href="city.php?name=<?= urlencode($c['pinyin']) ?>" 
           style="display:block;padding:10px 14px;background:#f8f8f8;border-radius:6px;text-decoration:none;color:#333;font-size:14px;text-align:center;transition:all 0.2s;"
           onmouseover="this.style.background='#ff6b00';this.style.color='#fff'"
           onmouseout="this.style.background='#f8f8f8';this.style.color='#333'">
            <?= htmlspecialchars($c['name']) ?>
        </a>
        <?php endforeach; ?>
        <a href="top200city.php" style="display:block;padding:10px 14px;background:#ff6b00;border-radius:6px;text-decoration:none;color:#fff;font-size:14px;text-align:center;font-weight:bold;">
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
// 当前登录用户 ID（未登录为 null），供认领前做登录校验
const currentUserId = <?= json_encode($current_user_id) ?>;

// 自定义确认弹窗（替代原生 confirm，避免被浏览器“阻止对话框”静默取消）
// key: localStorage 中记录“不再提醒”的键名
function showConfirm(message, key, onYes) {
    const SKIP_KEY = 'skipConfirm_' + key;
    if (localStorage.getItem(SKIP_KEY) === '1') {
        onYes();
        return;
    }
    const mask = document.getElementById('confirmMask');
    const msgEl = document.getElementById('confirmMsg');
    const rememberEl = document.getElementById('confirmRemember');
    const okBtn = document.getElementById('confirmOk');
    const cancelBtn = document.getElementById('confirmCancel');
    msgEl.textContent = message;
    rememberEl.checked = false;
    mask.classList.add('show');
    function cleanup() {
        mask.classList.remove('show');
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
    }
    function onOk() {
        if (rememberEl.checked) localStorage.setItem(SKIP_KEY, '1');
        cleanup();
        onYes();
    }
    function onCancel() { cleanup(); }
    okBtn.addEventListener('click', onOk);
    cancelBtn.addEventListener('click', onCancel);
}

// 清空选择
document.getElementById('clear-selection').addEventListener('click', clearSelection);


function updateMultiSelectUI() {
    var count = selectedBlocks.length;
    var multiInfo = document.getElementById('multiple-selection-info');
    var singleActions = document.getElementById('single-block-actions');
    var multiActions = document.getElementById('multiple-block-actions');
    var btnMulti = document.getElementById('claim-multiple-button');

    if (count === 0) {
        if (multiInfo) multiInfo.style.display = 'none';
        if (singleActions) singleActions.style.display = 'none';
        if (multiActions) multiActions.style.display = 'none';
    } else if (count === 1) {
        if (multiInfo) multiInfo.style.display = 'none';
        if (singleActions) singleActions.style.display = 'block';
        if (multiActions) multiActions.style.display = 'none';
    } else {
        if (multiInfo) {
            multiInfo.style.display = 'block';
            multiInfo.textContent = '已选择 ' + count + ' 个区块';
        }
        if (singleActions) singleActions.style.display = 'none';
        if (multiActions) multiActions.style.display = 'block';
        if (btnMulti) { btnMulti.textContent = '认领选中区块 (' + count + ')'; btnMulti.disabled = false; }
    }
}

function clearSelection() {
    selectedBlocks = [];
    updateSelectionDisplay();
    document.querySelectorAll('.block-item.selected').forEach(cell => {
        cell.classList.remove('selected');
    });
}

// 区块点击事件
document.querySelectorAll('.block-item').forEach(cell => {
    cell.addEventListener('click', function() {
        const blockNumber = this.getAttribute('data-block-number');
        const blockStatus = this.getAttribute('data-status');
        const blockOwner = this.getAttribute('data-owner');
        const row = parseInt(this.getAttribute('data-row'));
        const col = parseInt(this.getAttribute('data-col'));

        // 已选中：取消
        const idx = selectedBlocks.findIndex(b => b.number === blockNumber);
        if (idx >= 0) {
            selectedBlocks.splice(idx, 1);
            this.classList.remove('selected');
            updateMultiSelectUI();
            return;
        }

        // 非 available 区块：只有自己已认领的允许点击查看/取消认领
        if (blockStatus !== 'available') {
            var curUid = <?= json_encode($current_user_id) ?>;
            if (blockStatus === 'sold' && blockOwner && curUid && String(blockOwner) === String(curUid)) {
                // 清空当前选择，切换到单区块模式
                clearSelection();
                updateBlockDetail(blockNumber, blockStatus, blockOwner);
                document.getElementById('single-block-actions').style.display = 'block';
                document.getElementById('multiple-block-actions').style.display = 'none';
            } else {
                alert('该区块不可用');
            }
            return;
        }

        // 相邻检查（首个区块无限制）
        if (selectedBlocks.length > 0) {
            var adj = selectedBlocks.some(function(b) {
                var dr = Math.abs(b.row - row), dc = Math.abs(b.col - col);
                return dr <= 1 && dc <= 1 && (dr + dc) > 0;
            });
            if (!adj) { alert('只能选择相邻区块'); return; }

            // 仅在范围内限制（相邻已校验）；矩形完整性在认领时校验
            var minRow = row, maxRow = row, minCol = col, maxCol = col;
            var allBlocks = selectedBlocks.concat([{row:row, col:col}]);
            allBlocks.forEach(function(b) {
                if (b.row < minRow) minRow = b.row;
                if (b.row > maxRow) maxRow = b.row;
                if (b.col < minCol) minCol = b.col;
                if (b.col > maxCol) maxCol = b.col;
            });
            var rectW = maxCol - minCol + 1, rectH = maxRow - minRow + 1;
            if (rectW > 4 || rectH > 4) { alert('最大支持 4×4 区块'); return; }
        }

        this.classList.add('selected');
        selectedBlocks.push({ number: blockNumber, row: row, col: col });
        updateMultiSelectUI();

        // 更新详情面板
        updateBlockDetail(blockNumber, blockStatus, blockOwner);
    });
});

// 单个区块选择处理
function handleSingleSelection(cell, blockNumber, blockStatus, blockOwner) {
    // 移除之前选中的样式
    document.querySelectorAll('.block-item.selected').forEach(el => {
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
    
    // 更新购买按钮和取消认领按钮
    const buyButton = document.getElementById('buy-button');
    const unclaimButton = document.getElementById('unclaim-button');
    const currentUserId = <?= json_encode($current_user_id) ?>;

    if (blockStatus === 'available') {
        buyButton.textContent = '立即认领';
        buyButton.disabled = false;
        buyButton.onclick = function() { claimSingleBlock(blockNumber); };
        unclaimButton.style.display = 'none';
    } else if (blockStatus === 'sold' && blockOwner && currentUserId && String(blockOwner) === String(currentUserId)) {
        // 当前用户是该区块的拥有者，显示取消认领
        buyButton.style.display = 'none';
        unclaimButton.style.display = 'block';
        unclaimButton.disabled = false;
        unclaimButton.onclick = function() { unclaimSingleBlock(blockNumber); };
    } else {
        buyButton.textContent = '不可认领';
        buyButton.disabled = true;
        buyButton.onclick = null;
        buyButton.style.display = 'block';
        unclaimButton.style.display = 'none';
    }
}

// 认领单个区块
async function claimSingleBlock(blockNumber) {
    // 未登录：直接提示并跳登录，避免“假成功”
    if (!currentUserId) {
        alert('请先登录后再认领区块');
        window.location.href = 'auth/login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
    }
    showConfirm(`确定要认领区块 ${blockNumber} 吗？`, 'claim', async function() {

    const fd = new FormData();
    fd.append('action', 'claim_block');
    fd.append('ajax', '1');
    fd.append('block_number', blockNumber);

    try {
        const resp = await fetch(window.location.href, { method: 'POST', body: fd });
        const text = await resp.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { data = null; }

        if (data && data.success) {
            const cell = document.querySelector(`[data-block-number="${blockNumber}"]`);
        if (cell) {
            cell.classList.remove('available', 'reserved', 'sold-blue', 'sold-red', 'selected');
            cell.classList.add('sold-own');
            cell.setAttribute('data-status', 'sold');
            cell.setAttribute('data-owner', '<?= $current_user_id ?>');
        }
            // 认领成功后清除选择状态，避免已认领区块仍占用选中态，
            // 否则再点其他区块会因“相邻”校验失败而无法选中
            clearSelection();
            updateMultiSelectUI();
            if (data.message) alert(data.message);
        } else {
            // 服务端返回失败或非预期响应：必须如实告知，不能谎报成功
            alert((data && data.message) ? data.message : '认领失败，请重试');
        }
    } catch (e) {
        alert('认领失败，请重试');
    }
    });
}

// 取消认领单个区块
async function unclaimSingleBlock(blockNumber) {
    const cell = document.querySelector(`[data-block-number="${blockNumber}"]`);
    const isMerged = cell && cell.classList.contains('merged');
    let msg = `确定要取消认领区块 ${blockNumber} 吗？\n取消后该区块将恢复为可认领状态。`;
    if (isMerged) {
        msg = `该区块属于合并区块组，取消认领将同时释放改组内所有区块。\n确定要取消认领 ${blockNumber} 吗？`;
    }
    showConfirm(msg, 'unclaim', async function() {


    const fd = new FormData();
    fd.append('action', 'unclaim_block');
    fd.append('ajax', '1');
    fd.append('block_number', blockNumber);

    try {
        const resp = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await resp.json();

        if (data.success) {
            alert(data.message || '取消认领成功！');
            location.reload();
        } else {
            alert(data.message || '取消认领失败，请重试');
        }
    } catch (e) {
        location.reload();
    }
    });
}

// 认领多个区块
document.getElementById('claim-multiple-button').addEventListener('click', async function() {
    if (selectedBlocks.length === 0) return;
    // 未登录：直接提示并跳登录，避免“假成功”
    if (!currentUserId) {
        alert('请先登录后再认领区块');
        window.location.href = 'auth/login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
    }

    // 认领前校验：选中区块必须形成一个完整矩形
    if (selectedBlocks.length > 1) {
        var minRow = Infinity, maxRow = -Infinity, minCol = Infinity, maxCol = -Infinity;
        selectedBlocks.forEach(function(b) {
            if (b.row < minRow) minRow = b.row;
            if (b.row > maxRow) maxRow = b.row;
            if (b.col < minCol) minCol = b.col;
            if (b.col > maxCol) maxCol = b.col;
        });
        var rectW = maxCol - minCol + 1, rectH = maxRow - minRow + 1;
        if (rectW * rectH !== selectedBlocks.length) {
            alert('选中的区块必须形成矩形（如 1x2、2x2、2x3 等）'); return;
        }
    }

    const blockNumbers = selectedBlocks.map(block => block.number).join(', ');
    showConfirm(`确定要认领以下 ${selectedBlocks.length} 个区块吗？\n${blockNumbers}`, 'claim', async function() {


    try {
        const fd = new FormData();
        fd.append('action', 'claim_block');
        fd.append('ajax', '1');
        selectedBlocks.forEach((block, index) => {
            fd.append(`selected_blocks[${index}]`, block.number);
        });

        const resp = await fetch(window.location.href, {
            method: 'POST',
            body: fd
        });
        const data = await resp.json();

        if (data.success) {
            // 合并领取后需整体重渲染（合并块编号、覆盖层、可取消状态等都依赖服务端渲染），
            // 直接刷新页面可保证与数据库一致，避免“取消时只释放单块/另一块点不中”等问题
            alert(data.message || '认领成功！');
            location.reload();
        } else {
            alert(data.message || '认领失败');
        }
    } catch (e) {
        // fallback
        location.reload();
    }
    });
});


// 区块悬停效果
document.querySelectorAll('.block-item').forEach(cell => {
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

// 移动端：将真实网格接入缩放/平移/多点选择
initPinchZoom('#desktopMap', '.block-list');
</script>
<?php endif; ?>

<style>
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
// 计算区块价格的函数（使用正确的价格查找表）
function calculateBlockPrice($zone, $block_id) {
    // 确保 block_id 是4位字符串格式
    $blockNo = str_pad($block_id, 4, '0', STR_PAD_LEFT);
    return calculateBlockPriceNew($zone, $blockNo);
}
?>