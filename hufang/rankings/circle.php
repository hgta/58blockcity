<?php
require_once '../../config/database.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';

// 定义支持的排行榜类型和对应标题
$rankingTypes = [
    'total_visits' => [
        'title' => '被访问最多圈子',
        'description' => '按互访圈被访问总次数排序',
        'value_label' => '访问次数'
    ],
    'completed_visits' => [
        'title' => '互访完成最多圈子',
        'description' => '按互访圈完成的互访次数排序',
        'value_label' => '完成次数'
    ],
    'block_count' => [
        'title' => '区块最多圈子',
        'description' => '按互访圈的区块数量排序',
        'value_label' => '区块数量'
    ],
    'unique_visitors' => [
        'title' => '独立访客最多圈子',
        'description' => '按互访圈的独立访客数量排序',
        'value_label' => '独立访客'
    ],
    'new_circles' => [
        'title' => '最新热门圈子',
        'description' => '最近30天创建的圈子按访问量排序',
        'value_label' => '近期访问'
    ]
];

// 获取请求的排行榜类型，默认为total_visits
$type = $_GET['type'] ?? 'total_visits';
if (!array_key_exists($type, $rankingTypes)) {
    $type = 'total_visits';
}

// 获取当前排行榜配置
$currentRanking = $rankingTypes[$type];

// 构建SQL查询
switch ($type) {
    case 'new_circles':
        $sql = "SELECT 
                    c.id,
                    c.name,
                    c.city,
                    c.block_count,
                    c.created_at,
                    u.username AS owner_name,
                    u.avatar AS owner_avatar,
                    COUNT(DISTINCT v.id) AS total_visits,
                    COUNT(DISTINCT CASE WHEN v.status = 'completed' THEN v.id END) AS completed_visits,
                    COUNT(DISTINCT v.visitor_id) AS unique_visitors,
                    COUNT(DISTINCT CASE WHEN v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN v.id END) AS new_visits
                FROM circles c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN visits v ON c.id = v.circle_id
                WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY c.id
                ORDER BY new_visits DESC, total_visits DESC";
        break;
    
    default:
        $sql = "SELECT 
                    c.id,
                    c.name,
                    c.city,
                    c.block_count,
                    c.created_at,
                    u.username AS owner_name,
                    u.avatar AS owner_avatar,
                    COUNT(DISTINCT v.id) AS total_visits,
                    COUNT(DISTINCT CASE WHEN v.status = 'completed' THEN v.id END) AS completed_visits,
                    COUNT(DISTINCT v.visitor_id) AS unique_visitors
                FROM circles c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN visits v ON c.id = v.circle_id
                GROUP BY c.id
                ORDER BY $type DESC, total_visits DESC";
}

// 执行查询
$circles = $pdo->query($sql)->fetchAll();
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> <?= htmlspecialchars($currentRanking['title']) ?></h1>
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
                                <th>互访圈</th>
                                <th width="150"><?= htmlspecialchars($currentRanking['value_label']) ?></th>
                                <th width="120">创建者</th>
                                <th width="120">城市</th>
                                <th width="120">区块数</th>
                                <th width="120">总访问</th>
                                <th width="120">完成互访</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($circles as $index => $circle): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <a href="../circles/view.php?id=<?= $circle['id'] ?>" class="font-weight-bold">
                                        <?= htmlspecialchars($circle['name']) ?>
                                    </a>
                                </td>
                                <td class="text-primary font-weight-bold">
                                    <?= $circle[$type] ?? 0 ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="../assets/images/<?= htmlspecialchars($circle['owner_avatar'] ?? 'default.jpg') ?>" 
                                             class="avatar-xs mr-2" style="width:28px;height:28px;object-fit:cover;flex-shrink:0;">
                                        <?= htmlspecialchars($circle['owner_name']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($circle['city']) ?></td>
                                <td><?= $circle['block_count'] ?></td>
                                <td><?= $circle['total_visits'] ?? 0 ?></td>
                                <td><?= $circle['completed_visits'] ?? 0 ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="circle-stats mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> 圈子分布统计</h5>
                    </div>
                    <div class="card-body">
                        <div id="circleDistributionChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> 访问趋势</h5>
                    </div>
                    <div class="card-body">
                        <div id="circleActivityChart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 引入图表库 -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<script>
// 圈子分布饼图
document.addEventListener('DOMContentLoaded', function() {
    const circleChart = echarts.init(document.getElementById('circleDistributionChart'));
    circleChart.setOption({
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c} ({d}%)'
        },
        legend: {
            orient: 'vertical',
            right: 10,
            top: 'center',
            data: <?= json_encode(array_column($circles, 'city')) ?>
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
            data: <?= json_encode(array_map(function($circle) use ($type) {
                return [
                    'value' => $circle[$type] ?? 0,
                    'name' => $circle['city']
                ];
            }, $circles)) ?>
        }]
    });
    
    // 访问趋势图
    const activityChart = echarts.init(document.getElementById('circleActivityChart'));
    activityChart.setOption({
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            }
        },
        xAxis: {
            type: 'category',
            data: <?= json_encode(array_column($circles, 'name')) ?>,
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {
            type: 'value',
            name: '数量'
        },
        series: [{
            name: '总访问量',
            type: 'bar',
            data: <?= json_encode(array_column($circles, 'total_visits')) ?>
        }, {
            name: '完成互访',
            type: 'bar',
            data: <?= json_encode(array_column($circles, 'completed_visits')) ?>
        }, {
            name: '独立访客',
            type: 'line',
            data: <?= json_encode(array_column($circles, 'unique_visitors')) ?>
        }]
    });
    
    // 响应窗口大小变化
    window.addEventListener('resize', function() {
        circleChart.resize();
        activityChart.resize();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>