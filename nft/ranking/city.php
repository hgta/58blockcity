<?php
require_once '../../config/database.php';
require_once '../../classes/NFTRanking.php';

$nftRanking = new NFTRanking($pdo);

// 定义支持的排行榜类型和对应标题
$rankingTypes = [
    'claims' => [
        'title' => '认领最多城市',
        'description' => '按城市中NFT头像被认领次数排序',
        'value_label' => '认领次数'
    ],
    'listings' => [
        'title' => '挂售最多城市',
        'description' => '按城市中NFT头像挂售数量排序',
        'value_label' => '挂售数量'
    ],
    'purchase_requests' => [
        'title' => '求购最多城市',
        'description' => '按城市中NFT头像求购请求数量排序',
        'value_label' => '求购数量'
    ],
    'transactions' => [
        'title' => '成交最多城市',
        'description' => '按城市中NFT头像成交数量排序',
        'value_label' => '成交数量'
    ],
    'volume' => [
        'title' => '交易额最高城市',
        'description' => '按城市中NFT头像交易总额排序',
        'value_label' => '交易总额'
    ],
    'unique_nfts' => [
        'title' => '独特头像最多城市',
        'description' => '按城市中独特NFT头像数量排序',
        'value_label' => '独特头像'
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
        $cities = $nftRanking->getTopCitiesByClaims(50);
        break;
    case 'listings':
        $cities = $nftRanking->getTopCitiesByListings(50);
        break;
    case 'purchase_requests':
        $cities = $nftRanking->getTopCitiesByPurchaseRequests(50);
        break;
    case 'transactions':
        $cities = $nftRanking->getTopCitiesByTransactions(50);
        break;
    case 'volume':
        $cities = $nftRanking->getTopCitiesByTransactions(50);
        // 按交易额重新排序
        usort($cities, function($a, $b) {
            return $b['total_volume'] <=> $a['total_volume'];
        });
        break;
    case 'unique_nfts':
        $cities = $nftRanking->getTopCitiesByClaims(50);
        // 按独特NFT数量重新排序
        usort($cities, function($a, $b) {
            return $b['unique_nft_count'] <=> $a['unique_nft_count'];
        });
        break;
    default:
        $cities = $nftRanking->getTopCitiesByClaims(50);
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-city"></i> <?= htmlspecialchars($currentRanking['title']) ?></h1>
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
                                <th>城市</th>
                                <th width="150"><?= htmlspecialchars($currentRanking['value_label']) ?></th>
                                <th width="120">独特头像</th>
                                <th width="120">总认领数</th>
                                <th width="120">总挂售</th>
                                <th width="120">总成交</th>
                                <th width="120">交易总额</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cities as $index => $city): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <a href="../market/city.php?city_id=<?= $city['id'] ?>" class="font-weight-bold">
                                        <?= htmlspecialchars($city['name']) ?>
                                    </a>
                                </td>
                                <td class="text-primary font-weight-bold">
                                    <?php 
                                    switch ($type) {
                                        case 'volume': 
                                            echo '¥' . number_format($city['total_volume'] ?? 0, 2);
                                            break;
                                        case 'unique_nfts':
                                            echo $city['unique_nft_count'] ?? 0;
                                            break;
                                        case 'claims':
                                            echo $city['claim_count'] ?? 0;
                                            break;
                                        case 'listings':
                                            echo $city['listing_count'] ?? 0;
                                            break;
                                        case 'purchase_requests':
                                            echo $city['request_count'] ?? 0;
                                            break;
                                        case 'transactions':
                                            echo $city['transaction_count'] ?? 0;
                                            break;
                                        default: 
                                            echo $city[$type] ?? 0;
                                    }
                                    ?>
                                </td>
                                <td><?= $city['unique_nft_count'] ?? 0 ?></td>
                                <td><?= $city['claim_count'] ?? 0 ?></td>
                                <td><?= $city['listing_count'] ?? 0 ?></td>
                                <td><?= $city['transaction_count'] ?? 0 ?></td>
                                <td>¥<?= number_format($city['total_volume'] ?? 0, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="city-stats mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> 城市分布统计</h5>
                    </div>
                    <div class="card-body">
                        <div id="cityDistributionChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> 交易趋势</h5>
                    </div>
                    <div class="card-body">
                        <div id="cityTransactionChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 引入图表库 -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
// 城市分布饼图
document.addEventListener('DOMContentLoaded', function() {
    const cityChart = echarts.init(document.getElementById('cityDistributionChart'));
    cityChart.setOption({
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c} ({d}%)'
        },
        legend: {
            orient: 'vertical',
            right: 10,
            top: 'center',
            data: <?= json_encode(array_column($cities, 'name')) ?>
        },
        series: [{
            name: '<?= htmlspecialchars($currentRanking['title']) ?>',
            type: 'pie',
            radius: ['50%', '70%'],
            avoidLabelOverlap: false,
            itemStyle: {
                borderRadius: 10,
                borderColor: '#fff',
                borderWidth: 2
            },
            label: {
                show: false,
                position: 'center'
            },
            emphasis: {
                label: {
                    show: true,
                    fontSize: '18',
                    fontWeight: 'bold'
                }
            },
            labelLine: {
                show: false
            },
            data: <?= json_encode(array_map(function($city) use ($type) {
                $value = 0;
                switch ($type) {
                    case 'volume': $value = $city['total_volume'] ?? 0; break;
                    case 'unique_nfts': $value = $city['unique_nft_count'] ?? 0; break;
                    case 'claims': $value = $city['claim_count'] ?? 0; break;
                    case 'listings': $value = $city['listing_count'] ?? 0; break;
                    case 'purchase_requests': $value = $city['request_count'] ?? 0; break;
                    case 'transactions': $value = $city['transaction_count'] ?? 0; break;
                }
                return [
                    'value' => $value,
                    'name' => $city['name']
                ];
            }, $cities)) ?>
        }]
    });
    
    // 城市交易趋势图
    const transactionChart = echarts.init(document.getElementById('cityTransactionChart'));
    transactionChart.setOption({
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            }
        },
        xAxis: {
            type: 'category',
            data: <?= json_encode(array_slice(array_column($cities, 'name'), 0, 10)) ?>,
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {
            type: 'value',
            name: '数量'
        },
        series: [{
            name: '认领数量',
            type: 'bar',
            data: <?= json_encode(array_slice(array_column($cities, 'claim_count'), 0, 10)) ?>
        }, {
            name: '挂售数量',
            type: 'bar',
            data: <?= json_encode(array_slice(array_column($cities, 'listing_count'), 0, 10)) ?>
        }, {
            name: '成交数量',
            type: 'line',
            data: <?= json_encode(array_slice(array_column($cities, 'transaction_count'), 0, 10)) ?>
        }]
    });
    
    // 响应窗口大小变化
    window.addEventListener('resize', function() {
        cityChart.resize();
        transactionChart.resize();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>