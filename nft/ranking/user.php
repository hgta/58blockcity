<?php
require_once '../../config/database.php';
require_once '../../classes/NFTRanking.php';

$nftRanking = new NFTRanking($pdo);

// 定义支持的排行榜类型和对应标题
$rankingTypes = [
    'claims' => [
        'title' => '头像最多用户',
        'description' => '按用户认领NFT头像次数排序',
        'value_label' => '总头像数'
    ],
    'listings' => [
        'title' => '挂售最多用户',
        'description' => '按用户挂售NFT头像次数排序',
        'value_label' => '挂售次数'
    ],
    'purchase_requests' => [
        'title' => '求购最多用户',
        'description' => '按用户求购NFT头像次数排序',
        'value_label' => '求购次数'
    ],
    'transactions' => [
        'title' => '成交最多用户',
        'description' => '按用户成交NFT头像次数排序',
        'value_label' => '成交次数'
    ],
    'volume' => [
        'title' => '交易额最高用户',
        'description' => '按用户NFT头像交易总额排序',
        'value_label' => '交易总额'
    ],
    'unique_nfts' => [
        'title' => '收藏最多用户',
        'description' => '按用户收藏的独特NFT头像数量排序',
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
        $users = $nftRanking->getTopUsersByClaims(50);
        break;
    case 'listings':
        $users = $nftRanking->getTopUsersByListings(50);
        break;
    case 'purchase_requests':
        $users = $nftRanking->getTopUsersByPurchaseRequests(50);
        break;
    case 'transactions':
        $users = $nftRanking->getTopUsersByTransactions(50);
        break;
    case 'volume':
        $users = $nftRanking->getTopUsersByTransactions(50);
        // 按交易额重新排序
        usort($users, function($a, $b) {
            return $b['total_volume'] <=> $a['total_volume'];
        });
        break;
    case 'unique_nfts':
        $users = $nftRanking->getTopUsersByClaims(50);
        // 按独特NFT数量重新排序
        usort($users, function($a, $b) {
            return $b['unique_nft_count'] <=> $a['unique_nft_count'];
        });
        break;
    default:
        $users = $nftRanking->getTopUsersByClaims(50);
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-user-friends"></i> <?= htmlspecialchars($currentRanking['title']) ?></h1>
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
                                <th>用户</th>
                                <th width="150"><?= htmlspecialchars($currentRanking['value_label']) ?></th>
                                <th width="120">所在城市</th>
                                <th width="120">独特头像</th>
                                <th width="120">认领次数</th>
                                <th width="120">挂售次数</th>
                                <th width="120">成交次数</th>
                                <th width="120">交易总额</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center" style="gap:10px;">
                                        <?php 
                                        $avatarFile = $user['avatar'] ?? 'default.jpg';
                                        $avatarUrl = (strpos($avatarFile, '/') !== false) 
                                            ? 'https://v.58.tl/' . $avatarFile 
                                            : 'https://v.58.tl/assets/images/' . $avatarFile;
                                        ?>
                                        <img src="<?= htmlspecialchars($avatarUrl) ?>" 
                                             style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #e2e8f0;"
                                             alt="<?= htmlspecialchars($user['username']) ?>"
                                             onerror="this.src='../assets/images/default.jpg'">
                                        <a href="../profile/index.php?user_id=<?= $user['id'] ?>" class="font-weight-bold">
                                            <?= htmlspecialchars($user['username']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="text-primary font-weight-bold">
                                    <?php 
                                    switch ($type) {
                                        case 'volume': 
                                            echo '¥' . number_format($user['total_volume'] ?? 0, 2);
                                            break;
                                        case 'unique_nfts':
                                            echo $user['unique_nft_count'] ?? 0;
                                            break;
                                        case 'claims':
                                            echo $user['claim_count'] ?? 0;
                                            break;
                                        case 'listings':
                                            echo $user['listing_count'] ?? 0;
                                            break;
                                        case 'purchase_requests':
                                            echo $user['request_count'] ?? 0;
                                            break;
                                        case 'transactions':
                                            echo $user['transaction_count'] ?? 0;
                                            break;
                                        default: 
                                            echo $user[$type] ?? 0;
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($user['city'] ?? '未知') ?></td>
                                <td><?= $user['unique_nft_count'] ?? 0 ?></td>
                                <td><?= $user['claim_count'] ?? 0 ?></td>
                                <td><?= $user['listing_count'] ?? 0 ?></td>
                                <td><?= $user['transaction_count'] ?? 0 ?></td>
                                <td>¥<?= number_format($user['total_volume'] ?? 0, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="user-stats mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> 用户城市分布</h5>
                    </div>
                    <div class="card-body">
                        <div id="userDistributionChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> 用户活跃度趋势</h5>
                    </div>
                    <div class="card-body">
                        <div id="userActivityChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 引入图表库 -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
// 用户分布饼图
document.addEventListener('DOMContentLoaded', function() {
    const userChart = echarts.init(document.getElementById('userDistributionChart'));
    userChart.setOption({
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c} ({d}%)'
        },
        legend: {
            orient: 'vertical',
            right: 10,
            top: 'center',
            data: <?= json_encode(array_column($users, 'city')) ?>
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
            data: <?= json_encode(array_map(function($user) use ($type) {
                $value = 0;
                switch ($type) {
                    case 'volume': $value = $user['total_volume'] ?? 0; break;
                    case 'unique_nfts': $value = $user['unique_nft_count'] ?? 0; break;
                    case 'claims': $value = $user['claim_count'] ?? 0; break;
                    case 'listings': $value = $user['listing_count'] ?? 0; break;
                    case 'purchase_requests': $value = $user['request_count'] ?? 0; break;
                    case 'transactions': $value = $user['transaction_count'] ?? 0; break;
                }
                return [
                    'value' => $value,
                    'name' => $user['city'] || '未知'
                ];
            }, $users)) ?>
        }]
    });
    
    // 用户活跃度趋势图
    const activityChart = echarts.init(document.getElementById('userActivityChart'));
    activityChart.setOption({
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            }
        },
        xAxis: {
            type: 'category',
            data: <?= json_encode(array_slice(array_column($users, 'username'), 0, 10)) ?>,
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
            data: <?= json_encode(array_slice(array_column($users, 'claim_count'), 0, 10)) ?>
        }, {
            name: '挂售次数',
            type: 'bar',
            data: <?= json_encode(array_slice(array_column($users, 'listing_count'), 0, 10)) ?>
        }, {
            name: '成交次数',
            type: 'line',
            data: <?= json_encode(array_slice(array_column($users, 'transaction_count'), 0, 10)) ?>
        }]
    });
    
    // 响应窗口大小变化
    window.addEventListener('resize', function() {
        userChart.resize();
        activityChart.resize();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>