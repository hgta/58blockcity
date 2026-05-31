<?php
// city/[城市拼音].php
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/City.php';
require_once '../classes/Block.php';
require_once '../classes/User.php';

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

// 视图模式：有zone参数=单区模式，无zone参数=全景模式
$view_mode = isset($_GET['zone']) ? 'zone' : 'panorama';
$current_zone = $_GET['zone'] ?? null;

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
if ($view_mode === 'zone') {
    $zone_blocks = $block->getBlocksByCityZone($city_id, $current_zone);
    $merged_blocks = $block->getMergedBlocks($city_id, $current_zone);
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
            } else {
                $error_message = "认领失败，请重试";
            }
        } elseif ($block_number) {
            $result = $block->claimBlock($current_user_id, $city_id, $current_zone, $block_number);
            if ($result) {
                $success_message = "成功认领区块 {$block_number}！";
            } else {
                $error_message = "认领失败，区块可能已被认领";
            }
        }
    }
}
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
            background: linear-gradient(135deg, #ff6b00, #ff9500);
            color: white;
            padding: 20px 0;
            margin-bottom: 20px;
        }
        
        .city-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .city-stats {
            display: flex;
            gap: 20px;
            font-size: 14px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* 区域选择器 */
        .zone-selector {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .zone-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .zone-tab {
            padding: 8px 20px;
            background-color: #f5f5f5;
            border-radius: 20px;
            text-decoration: none;
            color: #666;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .zone-tab:hover, .zone-tab.active {
            background-color: #ff6b00;
            color: white;
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
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        
        .block-info h3 {
            color: #ff6b00;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .block-meta {
            margin-bottom: 15px;
        }
        
        .meta-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .meta-label {
            font-weight: bold;
            color: #666;
        }
        
        .meta-value {
            color: #333;
        }
        
        .price-highlight {
            color: #ff6b00;
            font-weight: bold;
            font-size: 18px;
        }
        
        .block-actions {
            margin-top: 20px;
        }
        
        .btn-buy {
            display: block;
            width: 100%;
            background-color: #ff6b00;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-buy:hover {
            background-color: #e05d00;
        }
        
        .btn-buy:disabled {
            background-color: #ccc;
            cursor: not-allowed;
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
        }
        
        /* ======== 九区全景样式 ======== */
        .pano-container {
            max-width: 1050px;
            margin: 0 auto;
        }
        .pano-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 0 5px;
        }
        .pano-title {
            font-size: 22px;
            font-weight: bold;
            color: #333;
        }
        .pano-total {
            font-size: 14px;
            color: #666;
        }
        .pano-total strong {
            color: #ff6b00;
            font-size: 18px;
        }
        
        .pano-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .pano-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: block;
        }
        .pano-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }
        .pano-card.pano-hot { border-color: #ff6b00; }
        .pano-card.pano-warm { border-color: #ff9800; }
        .pano-card.pano-cool { border-color: #e0e0e0; }
        
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
        
        /* 迷你色块：3x3px */
        .pano-cell {
            display: inline-block;
            width: 3px;
            height: 3px;
            margin: 0;
            padding: 0;
            vertical-align: top;
        }
        .pano-cell.pm-avail { background-color: #e8f5e8; }
        .pano-cell.pm-sold  { background-color: #ff6b00; }
        .pano-cell.pm-merged { background-color: #1976d2; }
        
        .pano-merged-badge {
            font-size: 12px;
            color: #1976d2;
            font-weight: 500;
        }
        
        .pano-legend {
            display: flex;
            gap: 25px;
            justify-content: center;
            padding: 15px;
            font-size: 13px;
            color: #666;
        }
        .legend-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
            margin-right: 4px;
            vertical-align: middle;
        }
        .legend-dot.avail { background: #e8f5e8; border: 1px solid #c8e6c9; }
        .legend-dot.sold { background: #ff6b00; }
        .legend-dot.merged { background: #1976d2; }
        
        /* 全景响应式 */
        @media (max-width: 768px) {
            .pano-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .pano-card {
                padding: 10px;
            }
            .pano-cell {
                width: 2px;
                height: 2px;
            }
        }
        @media (max-width: 480px) {
            .pano-grid {
                grid-template-columns: 1fr;
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
            <a href="?name=<?= $city_pinyin ?>" 
               class="zone-tab <?= $view_mode === 'panorama' ? 'active' : '' ?>">
                🏙️ 全城
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
        
        <div class="pano-grid">
            <?php foreach (['A','B','C','D','E','F','G','H','Z'] as $zone):
                $zd = $all_zones_data[$zone];
                $pct = $zd['sold_count'] > 0 ? round($zd['sold_count'] / 9999 * 100) : 0;
                // 热度等级
                if ($pct >= 30) $heat = 'hot';
                elseif ($pct >= 10) $heat = 'warm';
                else $heat = 'cool';
            ?>
            <a href="?name=<?= $city_pinyin ?>&zone=<?= $zone ?>" class="pano-card pano-<?= $heat ?>">
                <div class="pano-card-header">
                    <span class="pano-zone-label"><?= $zone ?>区</span>
                    <span class="pano-zone-stats">
                        <?= number_format($zd['sold_count']) ?>/9,999
                        <span class="pano-pct">(<?= $pct ?>%)</span>
                    </span>
                </div>
                <div class="pano-mini-map">
                    <?php
                    // 将已售/合并区块号转换为快速查找集合
                    $soldSet = [];
                    foreach ($zd['blocks'] as $zb) {
                        if ($zb['status'] === 'sold' || $zb['status'] === 'reserved') {
                            $soldSet[$zb['block_number']] = true;
                        }
                    }
                    $mergedSet = array_flip($zd['merged_numbers']);
                    
                    for ($row = 1; $row <= 99; $row++):
                        for ($col = 1; $col <= 101; $col++):
                            $bn = str_pad($col, 2, '0', STR_PAD_LEFT) . str_pad($row, 2, '0', STR_PAD_LEFT);
                            if (isset($mergedSet[$bn])) {
                                $cls = 'pm-merged';
                            } elseif (isset($soldSet[$bn])) {
                                $cls = 'pm-sold';
                            } else {
                                $cls = 'pm-avail';
                            }
                            echo "<span class=\"pano-cell {$cls}\"></span>";
                        endfor;
                    endfor;
                    ?>
                </div>
                <?php if ($zd['merged_count'] > 0): ?>
                <div class="pano-merged-badge">🏗️ <?= $zd['merged_count'] ?>个合并区块</div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        
        <div class="pano-legend">
            <span><span class="legend-dot avail"></span> 可认领</span>
            <span><span class="legend-dot sold"></span> 已认领</span>
            <span><span class="legend-dot merged"></span> 合并区块</span>
        </div>
    </div>
    <!-- ========== /九区全景模式 ========== -->
    
    <?php else: ?>
    <!-- ========== 单区详细模式 ========== -->
    <div class="row">
        <div class="col-md-9">
            <div class="block-map-container">
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
                                <?php for ($col = 1; $col <= 101; $col++): ?>
                                   <?php
										$block_number = str_pad($col, 2, '0', STR_PAD_LEFT) . str_pad($row, 2, '0', STR_PAD_LEFT);
										$block_price = calculateBlockPrice($current_zone, $block_number, $zones);
										
										$block_status = 'available';
										$block_owner = null;
										$is_merged = false;
										$merged_size = '1x1';
										
										foreach ($zone_blocks as $zone_block) {
											if ($zone_block['block_number'] == $block_number) {
												$block_status = $zone_block['status'];
												$block_owner = $zone_block['owner_id'];
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
    if (blockOwner && blockOwner !== 'null') {
        document.getElementById('detail-block-owner').textContent = '用户' + blockOwner;
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
        // Z区特殊处理（简化版）
        return 1000;
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