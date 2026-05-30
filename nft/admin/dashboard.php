<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/NFT.php';
require_once '../../classes/User.php';
require_once '../../classes/Transaction.php';
require_once '../../classes/NFTRanking.php';

// Check admin authentication
checkAdmin();

// Initialize classes
$nft = new NFT($pdo);
$user = new User($pdo);
$transaction = new Transaction($pdo);
$nftRanking = new NFTRanking($pdo);

// Get dashboard statistics
$totalNfts = $nft->getTotalNftCount();
$totalUsers = $user->getTotalUsersCount();
$totalTransactions = $transaction->getTotalTransactionCount();
$recentTransactions = $transaction->getRecentTransactions(10);
$recentlyListedNfts = $nft->getRecentlyListedNfts(5);
$popularCities = $nft->getPopularCities(5);

// Get today's data
$today = date('Y-m-d');
$todayTransactions = $transaction->getTodayTransactionCount($today);
$todayRevenue = $transaction->getTodayRevenue($today);

// Get ranking data
$topCitiesByClaims = $nftRanking->getTopCitiesByClaims(3) ?: [];
$topNftsByTransactions = $nftRanking->getTopNftsByTransactions(3) ?: [];
$topUsersByClaims = $nftRanking->getTopUsersByClaims(3) ?: [];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - NFT头像交易平台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* 侧边栏样式 */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .sidebar-title {
            font-size: 20px;
            font-weight: bold;
            color: #3498db;
        }
        
        .sidebar-subtitle {
            font-size: 12px;
            color: #bdc3c7;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .menu-item {
            margin-bottom: 5px;
        }
        
        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .menu-link:hover, .menu-link.active {
            background: #34495e;
            color: white;
            border-left: 4px solid #3498db;
        }
        
        .menu-icon {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* 主内容区样式 */
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .page-title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* 统计卡片样式 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .stat-card.nfts { border-left-color: #e74c3c; }
        .stat-card.users { border-left-color: #f39c12; }
        .stat-card.transactions { border-left-color: #9b59b6; }
        .stat-card.today-transactions { border-left-color: #27ae60; }
        .stat-card.revenue { border-left-color: #e67e22; }
        .stat-card.cities { border-left-color: #3498db; }
        
        .stat-icon {
            font-size: 24px;
            margin-bottom: 15px;
            opacity: 0.8;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .stat-change {
            font-size: 12px;
            margin-top: 5px;
        }
        
        .stat-change.positive { color: #27ae60; }
        .stat-change.negative { color: #e74c3c; }
        
        /* 图表区域样式 */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        /* 表格样式 */
        .table-card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .view-all {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-canceled { background: #f8d7da; color: #721c24; }
        
        /* 快速操作 */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-btn {
            background: white;
            border: 2px solid #3498db;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: #3498db;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: #3498db;
            color: white;
            transform: translateY(-2px);
        }
        
        .action-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        /* NFT头像样式 */
        .nft-avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
        }
        
        .nft-avatar-md {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        /* 排行榜样式 */
        .ranking-list {
            list-style: none;
        }
        
        .ranking-list li {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .ranking-list li:last-child {
            border-bottom: none;
        }
        
        .rank {
            width: 30px;
            text-align: center;
            font-weight: bold;
            color: #666;
            font-size: 16px;
        }
        
        .rank-1 { color: #ffd700; }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }
        
        .ranking-name {
            flex: 1;
            margin: 0 15px;
            font-weight: 500;
        }
        
        .ranking-value {
            color: #3498db;
            font-weight: bold;
            font-size: 14px;
        }
        
        /* 响应式设计 */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- 侧边栏 -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">NFT头像交易平台</div>
                <div class="sidebar-subtitle">管理后台</div>
            </div>
            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="dashboard.php" class="menu-link active">
                        <span class="menu-icon"><i class="fas fa-tachometer-alt"></i></span>
                        仪表盘
                    </a>
                </li>
                <li class="menu-item">
                    <a href="nfts.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-user-circle"></i></span>
                        NFT管理
                    </a>
                </li>
                <li class="menu-item">
                    <a href="users.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-users"></i></span>
                        用户管理
                    </a>
                </li>
                <li class="menu-item">
                    <a href="transactions.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-exchange-alt"></i></span>
                        交易管理
                    </a>
                </li>
                <li class="menu-item">
                    <a href="cities.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-map-marker-alt"></i></span>
                        城市管理
                    </a>
                </li>
                <li class="menu-item">
                    <a href="ranking.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-trophy"></i></span>
                        排行榜
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../auth/logout.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-sign-out-alt"></i></span>
                        退出登录
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- 主内容区 -->
        <main class="main-content">
            <div class="header">
                <h1 class="page-title">仪表盘</h1>
                <div class="user-info">
                    <div class="user-avatar">A</div>
                    <div>
                        <div style="font-weight: bold;">管理员</div>
                        <div style="font-size: 12px; color: #666;"><?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- 快速操作 -->
            <div class="quick-actions">
                <a href="nfts.php?action=add" class="action-btn">
                    <div class="action-icon"><i class="fas fa-plus"></i></div>
                    添加NFT
                </a>
                <a href="users.php" class="action-btn">
                    <div class="action-icon"><i class="fas fa-user-cog"></i></div>
                    用户管理
                </a>
                <a href="transactions.php" class="action-btn">
                    <div class="action-icon"><i class="fas fa-list"></i></div>
                    交易审核
                </a>
                <a href="ranking.php" class="action-btn">
                    <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                    数据统计
                </a>
            </div>
            
            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card nfts">
                    <div class="stat-icon"><i class="fas fa-user-circle"></i></div>
                    <div class="stat-value"><?php echo number_format($totalNfts); ?></div>
                    <div class="stat-label">NFT总数</div>
                    <div class="stat-change positive">+8% 较上月</div>
                </div>
                
                <div class="stat-card users">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                    <div class="stat-label">注册用户</div>
                    <div class="stat-change positive">+12% 较上月</div>
                </div>
                
                <div class="stat-card transactions">
                    <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="stat-value"><?php echo number_format($totalTransactions); ?></div>
                    <div class="stat-label">总交易数</div>
                    <div class="stat-change positive">+15% 较上月</div>
                </div>
                
                <div class="stat-card today-transactions">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-value"><?php echo number_format($todayTransactions); ?></div>
                    <div class="stat-label">今日交易</div>
                    <div class="stat-change positive">+5% 较昨日</div>
                </div>
                
                <div class="stat-card revenue">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-value">¥<?php echo number_format($todayRevenue, 2); ?></div>
                    <div class="stat-label">今日收入</div>
                    <div class="stat-change positive">+3% 较昨日</div>
                </div>
                
                <div class="stat-card cities">
                    <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="stat-value"><?php echo count($popularCities); ?></div>
                    <div class="stat-label">活跃城市</div>
                    <div class="stat-change positive">+2% 较上月</div>
                </div>
            </div>
            
            <!-- 图表和排行榜区域 -->
            <div class="charts-grid">
                <!-- 最近交易图表 -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">交易趋势</div>
                        <select style="padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            <option>最近7天</option>
                            <option>最近30天</option>
                            <option>最近90天</option>
                        </select>
                    </div>
                    <div style="height: 300px; background: #f8f9fa; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #666;">
                        <i class="fas fa-chart-line" style="font-size: 48px; margin-right: 15px;"></i>
                        交易趋势图表区域
                    </div>
                </div>
                
                <!-- 排行榜 -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">今日排行榜</div>
                    </div>
                    <div style="height: 300px; overflow-y: auto;">
                        <ul class="ranking-list">
                            <li>
                                <span class="rank rank-1">1</span>
                                <span class="ranking-name">认领最多城市</span>
                            </li>
                            <?php foreach ($topCitiesByClaims as $index => $city): ?>
                            <li>
                                <span class="rank rank-<?= $index + 1 ?>"><?= $index + 1 ?></span>
                                <span class="ranking-name"><?= htmlspecialchars($city['name'] ?? '未知城市') ?></span>
                                <span class="ranking-value"><?= $city['claim_count'] ?? 0 ?>次</span>
                            </li>
                            <?php endforeach; ?>
                            
                            <li style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #eee;">
                                <span class="rank rank-1">1</span>
                                <span class="ranking-name">成交最多头像</span>
                            </li>
                            <?php foreach ($topNftsByTransactions as $index => $nft): ?>
                            <li>
                                <span class="rank rank-<?= $index + 1 ?>"><?= $index + 1 ?></span>
                                <img src="<?= htmlspecialchars($nft['base_image'] ?? '../assets/images/default-nft.jpg') ?>" 
                                     class="nft-avatar-sm"
                                     onerror="this.src='../assets/images/default-nft.jpg'">
                                <span class="ranking-name"><?= htmlspecialchars($nft['code'] ?? '未知头像') ?></span>
                                <span class="ranking-value"><?= $nft['transaction_count'] ?? 0 ?>笔</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- 最近交易 -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">最近交易</div>
                    <a href="transactions.php" class="view-all">查看全部</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>交易ID</th>
                            <th>NFT头像</th>
                            <th>买家</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentTransactions)): ?>
                            <?php foreach ($recentTransactions as $tx): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($tx['id']); ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="../avatar/<?php echo htmlspecialchars($tx['nft_image'] ?? 'default.jpg'); ?>" 
                                                 class="nft-avatar-sm"
                                                 onerror="this.src='../assets/images/default-nft.jpg'">
                                            <span><?php echo htmlspecialchars($tx['nft_code'] ?? '未知头像'); ?></span>
                                        </div>
                                    </td>
                                    <td>用户<?php echo $tx['buyer_id'] ?? $tx['seller_id']; ?></td>
                                    <td>
                                        <?php echo number_format($tx['price'], 2); ?>
                                        <?php echo ($tx['currency'] === 'popularity') ? '人气值' : '¥'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $tx['status']; ?>">
                                            <?php 
                                            $statusMap = [
                                                'pending' => '待处理',
                                                'completed' => '已完成', 
                                                'canceled' => '已取消'
                                            ];
                                            echo $statusMap[$tx['status']] ?? $tx['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('m-d H:i', strtotime($tx['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #666; padding: 40px;">
                                    <i class="fas fa-exchange-alt" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                    <div>暂无交易数据</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 最近上架NFT -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">最近上架NFT</div>
                    <a href="nfts.php" class="view-all">查看全部</a>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
                    <?php if (!empty($recentlyListedNfts)): ?>
                        <?php foreach ($recentlyListedNfts as $nftItem): ?>
                            <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; text-align: center;">
                                <img src="../avatar/<?php echo htmlspecialchars($nftItem['base_image']); ?>" 
                                     class="nft-avatar-md"
                                     onerror="this.src='../assets/images/default-nft.jpg'"
                                     style="margin-bottom: 10px;">
                                <div style="font-weight: bold; margin-bottom: 5px;"><?php echo htmlspecialchars($nftItem['code']); ?></div>
                                <div style="color: #e74c3c; font-weight: bold; margin-bottom: 5px;">
                                    <?php echo number_format($nftItem['price'], 2); ?>
                                    <?php echo ($nftItem['currency'] === 'popularity') ? '人气值' : '¥'; ?>
                                </div>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo htmlspecialchars($nftItem['city_name']); ?>
                                </div>
                                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                                    <?php echo time_elapsed_string($nftItem['created_at']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; color: #666; padding: 40px;">
                            <i class="fas fa-user-circle" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                            <div>暂无上架NFT数据</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js"></script>
</body>
</html>

<?php
// Helper function to display time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => '年',
        'm' => '月',
        'w' => '周',
        'd' => '天',
        'h' => '小时',
        'i' => '分钟',
        's' => '秒',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . '前' : '刚刚';
}
?>