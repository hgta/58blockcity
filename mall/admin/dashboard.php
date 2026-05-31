<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查管理员权限
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../../config/database.php';

// 加载相关类
require_once '../../classes/Product.php';
require_once '../../classes/Shop.php';
require_once '../../classes/User.php';
require_once '../../classes/Order.php';

$product = new Product($pdo);
$shop = new Shop($pdo);
$user = new User($pdo);
$order = new Order($pdo);

// 获取统计数据
$totalProducts = $product->getProductCount();
$totalShops = $shop->getShopCount();
$totalUsers = $user->getUserCount();
$totalOrders = $order->getOrderCount();

// 获取今日数据
$today = date('Y-m-d');
$todayOrders = $order->getTodayOrderCount($today);
$todayRevenue = $order->getTodayRevenue($today);

// 获取最近订单
$recentOrders = $order->getRecentOrders(10);

// 获取商品统计
$productStats = $product->getProductStats();

// 获取店铺统计
$shopStats = $shop->getShopStats();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - 58人气值商城</title>
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
        
        .stat-card.products { border-left-color: #e74c3c; }
        .stat-card.shops { border-left-color: #f39c12; }
        .stat-card.users { border-left-color: #9b59b6; }
        .stat-card.orders { border-left-color: #27ae60; }
        .stat-card.revenue { border-left-color: #e67e22; }
        .stat-card.today-orders { border-left-color: #3498db; }
        
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
        .status-paid { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-completed { background: #e2e3e5; color: #383d41; }
        
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
                <div class="sidebar-title">58人气值商城</div>
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
                    <a href="products.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-box"></i></span>
                        商品管理
                    </a>
                </li>
                <li class="menu-item">
                    <a href="shops.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-store"></i></span>
                        店铺管理
                    </a>
                </li>
                <li class="menu-item">
                    <a href="orders.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-shopping-cart"></i></span>
                        订单管理
                    </a>
                </li>
                <li class="menu-item">
                    <a href="users.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-users"></i></span>
                        用户管理
                    </a>
                </li>
                <li class="menu-item">
                    <a href="categories.php" class="menu-link">
                        <span class="menu-icon"><i class="fas fa-tags"></i></span>
                        分类管理
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
                <a href="products.php?action=add" class="action-btn">
                    <div class="action-icon"><i class="fas fa-plus"></i></div>
                    添加商品
                </a>
                <a href="shops.php?action=approve" class="action-btn">
                    <div class="action-icon"><i class="fas fa-check"></i></div>
                    审核店铺
                </a>
                <a href="orders.php" class="action-btn">
                    <div class="action-icon"><i class="fas fa-list"></i></div>
                    查看订单
                </a>
                <a href="users.php" class="action-btn">
                    <div class="action-icon"><i class="fas fa-user-cog"></i></div>
                    用户管理
                </a>
            </div>
            
            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card products">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-value"><?php echo number_format($totalProducts); ?></div>
                    <div class="stat-label">商品总数</div>
                    <div class="stat-change positive">+12% 较上月</div>
                </div>
                
                <div class="stat-card shops">
                    <div class="stat-icon"><i class="fas fa-store"></i></div>
                    <div class="stat-value"><?php echo number_format($totalShops); ?></div>
                    <div class="stat-label">店铺总数</div>
                    <div class="stat-change positive">+8% 较上月</div>
                </div>
                
                <div class="stat-card users">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                    <div class="stat-label">注册用户</div>
                    <div class="stat-change positive">+15% 较上月</div>
                </div>
                
                <div class="stat-card orders">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-value"><?php echo number_format($totalOrders); ?></div>
                    <div class="stat-label">总订单数</div>
                    <div class="stat-change positive">+20% 较上月</div>
                </div>
                
                <div class="stat-card revenue">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-value">¥<?php echo number_format($todayRevenue, 2); ?></div>
                    <div class="stat-label">今日收入</div>
                    <div class="stat-change positive">+5% 较昨日</div>
                </div>
                
                <div class="stat-card today-orders">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-value"><?php echo number_format($todayOrders); ?></div>
                    <div class="stat-label">今日订单</div>
                    <div class="stat-change positive">+3% 较昨日</div>
                </div>
            </div>
            
            <!-- 图表区域 -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">销售趋势</div>
                        <select style="padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            <option>最近7天</option>
                            <option>最近30天</option>
                            <option>最近90天</option>
                        </select>
                    </div>
                    <div style="height: 300px; background: #f8f9fa; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #666;">
                        <i class="fas fa-chart-bar" style="font-size: 48px; margin-right: 15px;"></i>
                        销售图表区域
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">商品分类分布</div>
                    </div>
                    <div style="height: 300px; background: #f8f9fa; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #666;">
                        <i class="fas fa-chart-pie" style="font-size: 48px; margin-right: 15px;"></i>
                        分类图表区域
                    </div>
                </div>
            </div>
            
            <!-- 最近订单 -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">最近订单</div>
                    <a href="orders.php" class="view-all">查看全部</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>订单号</th>
                            <th>用户</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_no']); ?></td>
                                    <td>用户<?php echo $order['user_id']; ?></td>
                                    <td>¥<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php 
                                            $statusMap = [
                                                'pending' => '待付款',
                                                'paid' => '已付款', 
                                                'shipped' => '已发货',
                                                'completed' => '已完成'
                                            ];
                                            echo $statusMap[$order['status']] ?? $order['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('m-d H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #666; padding: 40px;">
                                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                                    <div>暂无订单数据</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js"></script>
</body>
</html> 