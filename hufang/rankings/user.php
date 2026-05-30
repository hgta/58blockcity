<?php
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';

// 定义支持的排行榜类型和对应标题
$rankingTypes = [
    'owned_circles' => [
        'title' => '拥有圈子最多用户',
        'description' => '按用户创建的互访圈数量排序',
        'value_label' => '拥有圈子'
    ],
    'total_blocks' => [
        'title' => '区块最多用户',
        'description' => '按用户所有圈子的区块总数排序',
        'value_label' => '区块总数'
    ],
    'visits_made' => [
        'title' => '最活跃访问者',
        'description' => '按用户访问其他圈子的次数排序',
        'value_label' => '访问次数'
    ],
    'visits_received' => [
        'title' => '最受欢迎圈主',
        'description' => '按用户创建的圈子被访问次数排序',
        'value_label' => '被访问次数'
    ],
    'completed_visits' => [
        'title' => '互访完成最多用户',
        'description' => '按用户完成的互访次数排序',
        'value_label' => '完成互访'
    ],
    'new_users' => [
        'title' => '新晋活跃用户',
        'description' => '最近30天注册用户按活跃度排序',
        'value_label' => '活跃度'
    ]
];

// 获取请求的排行榜类型，默认为owned_circles
$type = $_GET['type'] ?? 'owned_circles';
if (!array_key_exists($type, $rankingTypes)) {
    $type = 'owned_circles';
}

// 获取当前排行榜配置
$currentRanking = $rankingTypes[$type];

// 构建SQL查询
switch ($type) {
    case 'completed_visits':
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.avatar,
                    u.city,
                    COUNT(DISTINCT c.id) AS owned_circles,
                    SUM(c.block_count) AS total_blocks,
                    COUNT(DISTINCT v1.id) AS visits_made,
                    COUNT(DISTINCT v2.id) AS visits_received,
                    COUNT(DISTINCT CASE WHEN v1.status = 'completed' THEN v1.id END) AS completed_visits
                FROM users u
                LEFT JOIN circles c ON u.id = c.user_id
                LEFT JOIN visits v1 ON u.id = v1.visitor_id
                LEFT JOIN visits v2 ON c.id = v2.circle_id
                GROUP BY u.id
                ORDER BY completed_visits DESC, visits_made DESC";
        break;
    
    case 'new_users':
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.avatar,
                    u.city,
                    u.created_at,
                    COUNT(DISTINCT c.id) AS owned_circles,
                    SUM(c.block_count) AS total_blocks,
                    COUNT(DISTINCT v1.id) AS visits_made,
                    COUNT(DISTINCT v2.id) AS visits_received,
                    COUNT(DISTINCT CASE WHEN v1.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN v1.id END) AS recent_activity
                FROM users u
                LEFT JOIN circles c ON u.id = c.user_id
                LEFT JOIN visits v1 ON u.id = v1.visitor_id
                LEFT JOIN visits v2 ON c.id = v2.circle_id
                WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY u.id
                ORDER BY recent_activity DESC, visits_made DESC";
        break;
    
    // 修改default情况的SQL查询
	default:
		$sql = "SELECT 
					u.id,
					u.username,
					u.avatar,
					u.city,
					COALESCE(circle_stats.owned_circles, 0) AS owned_circles,
					COALESCE(circle_stats.total_blocks, 0) AS total_blocks,
					COALESCE(visits_made.count, 0) AS visits_made,
					COALESCE(visits_received.count, 0) AS visits_received
				FROM users u
				LEFT JOIN (
					SELECT 
						user_id,
						COUNT(id) AS owned_circles,
						SUM(block_count) AS total_blocks
					FROM circles
					GROUP BY user_id
				) AS circle_stats ON u.id = circle_stats.user_id
				LEFT JOIN (
					SELECT 
						visitor_id,
						COUNT(DISTINCT id) AS count
					FROM visits
					GROUP BY visitor_id
				) AS visits_made ON u.id = visits_made.visitor_id
				LEFT JOIN (
					SELECT 
						c.user_id,
						COUNT(DISTINCT v.id) AS count
					FROM visits v
					JOIN circles c ON v.circle_id = c.id
					GROUP BY c.user_id
				) AS visits_received ON u.id = visits_received.user_id
				ORDER BY $type DESC, visits_made DESC";
		break;
}

// 执行查询
$users = $pdo->query($sql)->fetchAll();
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
                                <th width="120">拥有圈子</th>
                                <th width="120">区块总数</th>
                                <th width="120">访问次数</th>
                                <th width="120">被访问次数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="../assets/images/<?= htmlspecialchars($user['avatar'] ?? 'default.jpg') ?>" 
                                             class="avatar-sm rounded-circle mr-2">
                                        <a href="https://v.58.tl/circles/circles.php?user_id=<?= $user['id'] ?>" class="font-weight-bold">
                                            <?= htmlspecialchars($user['username']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="text-primary font-weight-bold">
                                    <?php 
                                    switch ($type) {
                                        case 'new_users': 
                                            echo $user['recent_activity'] ?? 0;
                                            break;
                                        case 'completed_visits':
                                            echo $user['completed_visits'] ?? 0;
                                            break;
                                        default: 
                                            echo $user[$type] ?? 0;
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($user['city']) ?></td>
                                <td><?= $user['owned_circles'] ?? 0 ?></td>
                                <td><?= $user['total_blocks'] ?? 0 ?></td>
                                <td><?= $user['visits_made'] ?? 0 ?></td>
                                <td><?= $user['visits_received'] ?? 0 ?></td>
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
                return [
                    'value' => $user[$type] ?? 0,
                    'name' => $user['city']
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
            data: <?= json_encode(array_column($users, 'username')) ?>,
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {
            type: 'value',
            name: '数量'
        },
        series: [{
            name: '拥有圈子',
            type: 'bar',
            data: <?= json_encode(array_column($users, 'owned_circles')) ?>
        }, {
            name: '区块总数',
            type: 'bar',
            data: <?= json_encode(array_column($users, 'total_blocks')) ?>
        }, {
            name: '访问次数',
            type: 'line',
            data: <?= json_encode(array_column($users, 'visits_made')) ?>
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