<?php
require_once '../../config/database.php';

// 定义支持的排行榜类型和对应标题
$rankingTypes = [
    'circle_count' => [
        'title' => '圈子最多城市',
        'description' => '按城市中创建的互访圈数量排序',
        'value_label' => '圈子数量'
    ],
    'user_count' => [
        'title' => '用户最多城市',
        'description' => '按城市中注册用户数量排序',
        'value_label' => '用户数量'
    ],
    'visit_count' => [
        'title' => '访问最多城市',
        'description' => '按城市中互访圈被访问总次数排序',
        'value_label' => '访问次数'
    ],
    'completed_visit_count' => [
        'title' => '互访完成最多城市',
        'description' => '按城市中完成的互访次数排序',
        'value_label' => '完成互访'
    ],
    'new_users' => [
        'title' => '新用户增长最快城市',
        'description' => '按最近30天新增用户数量排序',
        'value_label' => '新增用户'
    ],
    'active_ratio' => [
        'title' => '用户活跃度最高城市',
        'description' => '按城市中用户活跃比例排序',
        'value_label' => '活跃比例'
    ]
];

// 获取请求的排行榜类型，默认为circle_count
$type = $_GET['type'] ?? 'circle_count';
if (!array_key_exists($type, $rankingTypes)) {
    $type = 'circle_count';
}

// 获取当前排行榜配置
$currentRanking = $rankingTypes[$type];

// 构建SQL查询
switch ($type) {
    case 'new_users':
        $sql = "SELECT 
                    u.city,
                    COUNT(DISTINCT u.id) AS user_count,
                    COUNT(DISTINCT c.id) AS circle_count,
                    COUNT(DISTINCT v.id) AS visit_count,
                    COUNT(DISTINCT CASE WHEN v.status = 'completed' THEN v.id END) AS completed_visit_count,
                    COUNT(DISTINCT CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id END) AS new_users
                FROM users u
                LEFT JOIN circles c ON u.city = c.city
                LEFT JOIN visits v ON c.id = v.circle_id
                GROUP BY u.city
                ORDER BY new_users DESC";
        break;
    
    case 'active_ratio':
        $sql = "SELECT 
                    u.city,
                    COUNT(DISTINCT u.id) AS user_count,
                    COUNT(DISTINCT c.id) AS circle_count,
                    COUNT(DISTINCT v.id) AS visit_count,
                    COUNT(DISTINCT CASE WHEN v.status = 'completed' THEN v.id END) AS completed_visit_count,
                    COUNT(DISTINCT CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id END) / 
                    GREATEST(COUNT(DISTINCT u.id), 1) AS active_ratio
                FROM users u
                LEFT JOIN circles c ON u.city = c.city
                LEFT JOIN visits v ON c.id = v.circle_id
                GROUP BY u.city
                ORDER BY active_ratio DESC";
        break;
    
    default:
        $sql = "SELECT 
                    u.city,
                    COUNT(DISTINCT u.id) AS user_count,
                    COUNT(DISTINCT c.id) AS circle_count,
                    COUNT(DISTINCT v.id) AS visit_count,
                    COUNT(DISTINCT CASE WHEN v.status = 'completed' THEN v.id END) AS completed_visit_count
                FROM users u
                LEFT JOIN circles c ON u.city = c.city
                LEFT JOIN visits v ON c.id = v.circle_id
                GROUP BY u.city
                ORDER BY $type DESC";
}

// 执行查询
$cities = $pdo->query($sql)->fetchAll();
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
                                <th width="120">用户数量</th>
                                <th width="120">圈子数量</th>
                                <th width="120">总访问量</th>
                                <th width="120">完成互访</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cities as $index => $city): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <a href="../circles/index.php?city=<?= urlencode($city['city']) ?>" class="font-weight-bold">
                                        <?= htmlspecialchars($city['city']) ?>
                                    </a>
                                </td>
                                <td class="text-primary font-weight-bold">
                                    <?php 
                                    switch ($type) {
                                        case 'active_ratio': 
                                            echo round($city['active_ratio'] * 100, 1) . '%';
                                            break;
                                        default: 
                                            echo $city[$type] ?? 0;
                                    }
                                    ?>
                                </td>
                                <td><?= $city['user_count'] ?? 0 ?></td>
                                <td><?= $city['circle_count'] ?? 0 ?></td>
                                <td><?= $city['visit_count'] ?? 0 ?></td>
                                <td><?= $city['completed_visit_count'] ?? 0 ?></td>
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
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> 活跃度趋势</h5>
                    </div>
                    <div class="card-body">
                        <div id="cityActivityChart" style="height: 300px;"></div>
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
            data: <?= json_encode(array_column($cities, 'city')) ?>
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
                return [
                    'value' => $city[$type] ?? 0,
                    'name' => $city['city']
                ];
            }, $cities)) ?>
        }]
    });
    
    // 活跃度趋势图
    const activityChart = echarts.init(document.getElementById('cityActivityChart'));
    activityChart.setOption({
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            }
        },
        xAxis: {
            type: 'category',
            data: <?= json_encode(array_column($cities, 'city')) ?>,
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {
            type: 'value',
            name: '活跃度'
        },
        series: [{
            name: '用户数量',
            type: 'bar',
            data: <?= json_encode(array_column($cities, 'user_count')) ?>
        }, {
            name: '圈子数量',
            type: 'bar',
            data: <?= json_encode(array_column($cities, 'circle_count')) ?>
        }, {
            name: '完成互访',
            type: 'line',
            data: <?= json_encode(array_column($cities, 'completed_visit_count')) ?>
        }]
    });
    
    // 响应窗口大小变化
    window.addEventListener('resize', function() {
        cityChart.resize();
        activityChart.resize();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>