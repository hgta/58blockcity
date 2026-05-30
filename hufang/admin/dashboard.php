<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';
require_once '../../classes/City.php'; // 新增City类

// Check if user is logged in and is the super admin (user_id = 1)
checkLogin();

$userId = $_SESSION['user_id'];
if ($userId != 1) {
    header('Location: ../user/dashboard.php');
    exit();
}

// Initialize classes
$user = new User($pdo);
$circle = new Circle($pdo);
$visit = new Visit($pdo);
$city = new City($pdo); // 新增City实例

// Get statistics for the dashboard
$totalUsers = $user->getTotalUsersCount();
$totalCircles = $circle->getTotalCirclesCount();
$totalVisits = $visit->getTotalVisitsCount();
$totalCities = $city->getTotalCitiesCount(); // 新增城市统计
$recentUsers = $user->getRecentUsers(5);
$recentCircles = $circle->getRecentCircles(5);
$recentVisits = $visit->getRecentVisits(5);

?>
<?php require_once '../includes/header.php'; ?>

<div class="container admin-dashboard">
    <h2>系统管理后台</h2>
    <p class="welcome-message">欢迎回来，超级管理员！</p>
    
    <!-- Statistics Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <h3>总用户数</h3>
            <div class="stat-value"><?= $totalUsers ?></div>
            <a href="users.php" class="stat-link">查看所有用户</a>
        </div>
        
        <div class="stat-card">
            <h3>互访圈总数</h3>
            <div class="stat-value"><?= $totalCircles ?></div>
            <a href="circles.php" class="stat-link">管理互访圈</a>
        </div>
        
        <div class="stat-card">
            <h3>互访记录</h3>
            <div class="stat-value"><?= $totalVisits ?></div>
            <a href="visits.php" class="stat-link">查看所有记录</a>
        </div>
        
        <!-- 新增城市统计卡片 -->
        <div class="stat-card">
            <h3>城市总数</h3>
            <div class="stat-value"><?= $totalCities ?></div>
            <a href="cities.php" class="stat-link">管理城市</a>
        </div>
    </div>
    
    <!-- Recent Activity Sections -->
    <div class="activity-sections">
        <!-- Recent Users -->
        <section class="recent-activity">
            <h3>最近注册用户</h3>
            <?php if (empty($recentUsers)): ?>
                <p>暂无最近注册用户</p>
            <?php else: ?>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>用户名</th>
                            <th>注册时间</th>
                            <th>城市</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                            <td><?= htmlspecialchars($user['city']) ?></td>
                            <td>
                                <a href="user_detail.php?id=<?= $user['id'] ?>" class="btn btn-sm">详情</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        
        <!-- Recent Circles -->
        <section class="recent-activity">
            <h3>最新互访圈</h3>
            <?php if (empty($recentCircles)): ?>
                <p>暂无最新互访圈</p>
            <?php else: ?>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>互访圈名称</th>
                            <th>创建者</th>
                            <th>城市</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCircles as $circle): ?>
                        <tr>
                            <td><?= htmlspecialchars($circle['name']) ?></td>
                            <td><?= htmlspecialchars($circle['username']) ?></td>
                            <td><?= htmlspecialchars($circle['city']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($circle['created_at'])) ?></td>
                            <td>
                                <a href="../circles/view.php?id=<?= $circle['id'] ?>" class="btn btn-sm">查看</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        
        <!-- Recent Visits -->
        <section class="recent-activity">
            <h3>最新互访记录</h3>
            <?php if (empty($recentVisits)): ?>
                <p>暂无最新互访记录</p>
            <?php else: ?>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>访问者</th>
                            <th>互访圈</th>
                            <th>状态</th>
                            <th>申请时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentVisits as $visit): ?>
                        <tr>
                            <td><?= htmlspecialchars($visit['visitor_username']) ?></td>
                            <td><?= htmlspecialchars($visit['circle_name']) ?></td>
                            <td>
                                <span class="status-badge <?= $visit['status'] ?>">
                                    <?= $visit['status'] ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($visit['created_at'])) ?></td>
                            <td>
                                <a href="visit_detail.php?id=<?= $visit['id'] ?>" class="btn btn-sm">详情</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
    
    <!-- Admin Actions -->
    <div class="admin-actions">
        <h3>管理操作</h3>
        <div class="action-buttons">
            <a href="users.php" class="btn">用户管理</a>
            <a href="circles.php" class="btn">互访圈管理</a>
            <a href="visits.php" class="btn">互访记录管理</a>
            <a href="cities.php" class="btn">城市管理</a> <!-- 新增城市管理按钮 -->
            <a href="system_settings.php" class="btn">系统设置</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>