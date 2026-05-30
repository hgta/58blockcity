<?php
require_once '../../config/database.php';
require_once '../../classes/NFTRanking.php';

$nftRanking = new NFTRanking($pdo);

// 定义支持的排行榜类型和对应标题
$rankingTypes = [
    'claims' => [
        'title' => '认领最多头像',
        'description' => '按NFT头像被认领次数排序',
        'value_label' => '认领次数'
    ],
    'listings' => [
        'title' => '挂售最多头像',
        'description' => '按NFT头像挂售次数排序',
        'value_label' => '挂售次数'
    ],
    'purchase_requests' => [
        'title' => '求购最多头像',
        'description' => '按NFT头像求购请求数量排序',
        'value_label' => '求购数量'
    ],
    'transactions' => [
        'title' => '成交最多头像',
        'description' => '按NFT头像成交次数排序',
        'value_label' => '成交次数'
    ],
    'volume' => [
        'title' => '交易额最高头像',
        'description' => '按NFT头像交易总额排序',
        'value_label' => '交易总额'
    ],
    'cities' => [
        'title' => '覆盖城市最多头像',
        'description' => '按NFT头像被认领的城市数量排序',
        'value_label' => '覆盖城市'
    ]
];

// 获取请求的排行榜类型，默认为claims
$type = $_GET['type'] ?? 'claims';
if (!array_key_exists($type, $rankingTypes)) {
    $type = 'claims';
}

// 获取当前排行榜配置
$currentRanking = $rankingTypes[$type];

// 根据类型获取数据
switch ($type) {
    case 'claims':
        $nfts = $nftRanking->getTopNftsByClaims(50);
        break;
    case 'listings':
        $nfts = $nftRanking->getTopNftsByListings(50);
        break;
    case 'purchase_requests':
        $nfts = $nftRanking->getTopNftsByPurchaseRequests(50);
        break;
    case 'transactions':
        $nfts = $nftRanking->getTopNftsByTransactions(50);
        break;
    case 'volume':
        $nfts = $nftRanking->getTopNftsByTransactions(50);
        // 按交易额重新排序
        usort($nfts, function($a, $b) {
            return $b['total_volume'] <=> $a['total_volume'];
        });
        break;
    case 'cities':
        $nfts = $nftRanking->getTopNftsByClaims(50);
        // 按城市数量重新排序
        usort($nfts, function($a, $b) {
            return $b['city_count'] <=> $a['city_count'];
        });
        break;
    default:
        $nfts = $nftRanking->getTopNftsByClaims(50);
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-user-circle"></i> <?= htmlspecialchars($currentRanking['title']) ?></h1>
        <p class="text-muted"><?= htmlspecialchars($currentRanking['description']) ?></p>
        
        <div class="ranking-filter">
            <div class="btn-group flex-wrap">
                <?php foreach ($rankingTypes as $key => $ranking): ?>
                    <a href="?type=<?= $key ?>" 
                       class="btn btn-sm <?= $type === $key ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <?= htmlspecialchars($ranking['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="ranking-table">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th width="60">排名</th>
                                <th>NFT头像</th>
                                <th width="150"><?= htmlspecialchars($currentRanking['value_label']) ?></th>
                                <th width="120">覆盖城市</th>
                                <th width="120">认领次数</th>
                                <th width="120">挂售次数</th>
                                <th width="120">成交次数</th>
                                <th width="120">交易总额</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nfts as $index => $nft): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="../avatar/<?= htmlspecialchars($nft['base_image']) ?>" 
                                             class="nft-avatar-md rounded mr-2">
                                        <div>
                                            <a href="../nft/detail.php?id=<?= $nft['id'] ?>" class="font-weight-bold d-block">
                                                <?= htmlspecialchars($nft['code']) ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-primary font-weight-bold">
                                    <?php 
                                    switch ($type) {
                                        case 'volume': 
                                            echo '¥' . number_format($nft['total_volume'] ?? 0, 2);
                                            break;
                                        case 'cities':
                                            echo $nft['city_count'] ?? 0;
                                            break;
                                        case 'claims':
                                            echo $nft['claim_count'] ?? 0;
                                            break;
                                        case 'listings':
                                            echo $nft['listing_count'] ?? 0;
                                            break;
                                        case 'purchase_requests':
                                            echo $nft['request_count'] ?? 0;
                                            break;
                                        case 'transactions':
                                            echo $nft['transaction_count'] ?? 0;
                                            break;
                                        default: 
                                            echo $nft[$type] ?? 0;
                                    }
                                    ?>
                                </td>
                                <td><?= $nft['city_count'] ?? 0 ?></td>
                                <td><?= $nft['claim_count'] ?? 0 ?></td>
                                <td><?= $nft['listing_count'] ?? 0 ?></td>
                                <td><?= $nft['transaction_count'] ?? 0 ?></td>
                                <td>¥<?= number_format($nft['total_volume'] ?? 0, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="nft-stats mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> 头像活跃度分布</h5>
                    </div>
                    <div class="card-body">
                        <div id="nftActivityChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> 交易趋势</h5>
                    </div>
                    <div class="card-body">
                        <div id="nftTransactionChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.nft-avatar-md {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
}
</style>

<!-- 引入图表库 -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
// NFT活跃度分布图
document.addEventListener('DOMContentLoaded', function() {
    const nftChart = echarts.init(document.getElementById('nftActivityChart'));
    nftChart.setOption({
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            }
        },
        xAxis: {
            type: 'category',
            data: <?= json_encode(array_slice(array_column($nfts, 'code'), 0, 10)) ?>,
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {
            type: 'value',
            name: '数量'
        },
        series: [{
            name: '认领次数',
            type: 'bar',
            data: <?= json_encode(array_slice(array_column($nfts, 'claim_count'), 0, 10)) ?>
        }, {
            name: '挂售次数',
            type: 'bar',
            data: <?= json_encode(array_slice(array_column($nfts, 'listing_count'), 0, 10)) ?>
        }, {
            name: '成交次数',
            type: 'line',
            data: <?= json_encode(array_slice(array_column($nfts, 'transaction_count'), 0, 10)) ?>
        }]
    });
    
    // NFT交易趋势图
    const transactionChart = echarts.init(document.getElementById('nftTransactionChart'));
    transactionChart.setOption({
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            }
        },
        xAxis: {
            type: 'category',
            data: <?= json_encode(array_slice(array_column($nfts, 'code'), 0, 10)) ?>,
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {
            type: 'value',
            name: '金额(元)'
        },
        series: [{
            name: '交易总额',
            type: 'bar',
            data: <?= json_encode(array_slice(array_map(function($nft) {
                return $nft['total_volume'] ?? 0;
            }, $nfts), 0, 10)) ?>
        }, {
            name: '平均价格',
            type: 'line',
            data: <?= json_encode(array_slice(array_map(function($nft) {
                return $nft['avg_price'] ?? 0;
            }, $nfts), 0, 10)) ?>
        }]
    });
    
    // 响应窗口大小变化
    window.addEventListener('resize', function() {
        nftChart.resize();
        transactionChart.resize();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>