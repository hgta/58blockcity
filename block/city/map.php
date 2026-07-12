<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/City.php';
require_once '../../classes/Block.php';

$cityId = $_GET['city_id'] ?? 1;
$zone = $_GET['zone'] ?? 'A';

$city = new City($pdo);
$block = new Block($pdo);

$cityInfo = $city->getCityById($cityId);
$blocks = $block->getBlocksByCityZone($cityId, $zone);

// 加载统一区域配置和价格查找表
$zoneConfig = require __DIR__ . '/../../config/zones.php';
require_once __DIR__ . '/../../config/block_prices.php';

// 检查是否有活跃的扩容投票
$activeVote = $block->getActiveExpansionVote($cityId, $zone);
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><?= htmlspecialchars($cityInfo['name']) ?>区块地图 <small><?= $zone ?>区</small></h1>
        
        <div class="zone-selector">
            <?php foreach (array_keys($zoneConfig) as $z): ?>
                <a href="map.php?city_id=<?= $cityId ?>&zone=<?= $z ?>" 
                   class="btn <?= $z == $zone ? 'btn-primary' : 'btn-default' ?>"><?= $z ?>区</a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php if ($activeVote): ?>
    <div class="alert alert-info">
        <h4>扩容投票进行中 (第<?= $activeVote['round'] ?>轮)</h4>
        <p>是否扩容<?= $activeVote['zone'] ?>区？投票截止时间: <?= $activeVote['end_time'] ?></p>
        <p>当前票数: 同意 <?= $activeVote['yes_votes'] ?> / 反对 <?= $activeVote['no_votes'] ?></p>
        <?php if (isLoggedIn()): ?>
            <div class="vote-buttons">
                <a href="vote.php?vote_id=<?= $activeVote['id'] ?>&vote=yes" class="btn btn-success">同意</a>
                <a href="vote.php?vote_id=<?= $activeVote['id'] ?>&vote=no" class="btn btn-danger">反对</a>
            </div>
        <?php else: ?>
            <p><a href="../auth/login.php">登录</a>后参与投票</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="block-map">
        <div class="map-header">
            <div class="row-number">行号</div>
            <?php 
            $zoneCols = $zoneConfig[$zone]['col_end'] - $zoneConfig[$zone]['col_start'] + 1;
            for ($c = 0; $c < $zoneCols; $c++): 
                $colNum = $zoneConfig[$zone]['col_start'] + $c;
            ?>
                <div class="col-number"><?= str_pad($colNum, 2, '0', STR_PAD_LEFT) ?></div>
            <?php endfor; ?>
        </div>
        
        <?php for ($row = 1; $row <= 99; $row++): ?>
            <div class="map-row">
                <div class="row-number"><?= str_pad($row, 2, '0', STR_PAD_LEFT) ?></div>
                
                <?php for ($c = 0; $c < $zoneCols; $c++): $colNum = $zoneConfig[$zone]['col_start'] + $c; ?>
                    <?php 
                    $blockNumber = str_pad($colNum, 2, '0', STR_PAD_LEFT) . str_pad($row, 2, '0', STR_PAD_LEFT);
                    $currentBlock = null;
                    
                    foreach ($blocks as $b) {
                        if ($b['block_number'] == $blockNumber) {
                            $currentBlock = $b;
                            break;
                        }
                    }
                    
                    $price = calculateBlockPriceNew($zone, $blockNumber);
                    ?>
                    
                    <div class="block-cell <?= $currentBlock ? 'sold' : 'available' ?>">
                        <?php if ($currentBlock): ?>
                            <a href="../block/view.php?id=<?= $currentBlock['id'] ?>" 
                               class="block-link sold" 
                               title="<?= htmlspecialchars($currentBlock['name'] ?? '区块 '.$blockNumber) ?>">
                                <?= $blockNumber ?>
                            </a>
                        <?php else: ?>
                            <a href="../block/buy.php?city_id=<?= $cityId ?>&zone=<?= $zone ?>&block_number=<?= $blockNumber ?>" 
                               class="block-link available" 
                               title="区块 <?= $blockNumber ?> - 价格: <?= number_format($price, 2) ?>">
                                <?= $blockNumber ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>