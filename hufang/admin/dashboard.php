<?php
/**
 * 互访圈 — 管理后台看板
 * (已迁移至统一后台框架)
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/User.php';
require_once '../../classes/Circle.php';
require_once '../../classes/Visit.php';
require_once '../../classes/City.php';

checkLogin();

$userId = $_SESSION['user_id'];
if ($userId != 1) {
    header('Location: ../user/dashboard.php');
    exit();
}

$user = new User($pdo);
$circle = new Circle($pdo);
$visit = new Visit($pdo);
$city = new City($pdo);

$totalUsers = $user->getTotalUsersCount();
$totalCircles = $circle->getTotalCirclesCount();
$totalVisits = $visit->getTotalVisitsCount();
$totalCities = $city->getTotalCitiesCount();

$recentUsers = $user->getRecentUsers(5);
$recentCircles = $circle->getRecentCircles(5);
$recentVisits = $visit->getRecentVisits(5);

// 统一后台框架配置
$admin_site_config = [
    'site'       => 'hufang',
    'page_title' => '互访圈管理看板',
];
require_once '../../shared/admin/admin-header.php';
?>

<!-- 统计卡片 -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">总用户数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-circle-notch"></i></div>
        <div class="stat-value"><?= number_format($totalCircles) ?></div>
        <div class="stat-label">互访圈总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-handshake"></i></div>
        <div class="stat-value"><?= number_format($totalVisits) ?></div>
        <div class="stat-label">互访记录</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-city"></i></div>
        <div class="stat-value"><?= number_format($totalCities) ?></div>
        <div class="stat-label">城市总数</div>
    </div>
</div>

<!-- 快捷操作 -->
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-bolt" style="margin-right:8px;color:var(--admin-accent);"></i>管理操作</span>
    </div>
    <div class="admin-card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;">
            <a href="users.php" class="admin-btn admin-btn-primary admin-btn-sm" style="justify-content:center;">
                <i class="fas fa-users"></i> 用户管理
            </a>
            <a href="circles.php" class="admin-btn admin-btn-secondary admin-btn-sm" style="justify-content:center;">
                <i class="fas fa-circle-notch"></i> 互访圈管理
            </a>
            <a href="visits.php" class="admin-btn admin-btn-secondary admin-btn-sm" style="justify-content:center;">
                <i class="fas fa-handshake"></i> 互访记录
            </a>
            <a href="cities.php" class="admin-btn admin-btn-secondary admin-btn-sm" style="justify-content:center;">
                <i class="fas fa-city"></i> 城市管理
            </a>
            <a href="system_settings.php" class="admin-btn admin-btn-secondary admin-btn-sm" style="justify-content:center;">
                <i class="fas fa-cog"></i> 系统设置
            </a>
        </div>
    </div>
</div>

<!-- 三栏最近活动 -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">
    <!-- 最近用户 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-user-plus" style="margin-right:8px;color:var(--admin-accent);"></i>最近注册用户</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($recentUsers)): ?>
                <div class="admin-empty-state" style="padding:40px 20px;">
                    <i class="fas fa-inbox"></i>
                    <p>暂无数据</p>
                </div>
            <?php else: ?>
                <table class="admin-data-table">
                    <thead>
                        <tr><th>用户</th><th>城市</th><th>注册时间</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['city'] ?? '-') ?></td>
                            <td style="font-size:12px;color:var(--admin-text-muted);"><?= date('m-d H:i', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- 最新互访圈 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-circle-notch" style="margin-right:8px;color:var(--admin-accent);"></i>最新互访圈</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($recentCircles)): ?>
                <div class="admin-empty-state" style="padding:40px 20px;">
                    <i class="fas fa-inbox"></i>
                    <p>暂无数据</p>
                </div>
            <?php else: ?>
                <table class="admin-data-table">
                    <thead>
                        <tr><th>名称</th><th>创建者</th><th>城市</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCircles as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= htmlspecialchars($c['username']) ?></td>
                            <td><?= htmlspecialchars($c['city'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- 最新互访记录 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-handshake" style="margin-right:8px;color:var(--admin-accent);"></i>最新互访记录</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($recentVisits)): ?>
                <div class="admin-empty-state" style="padding:40px 20px;">
                    <i class="fas fa-inbox"></i>
                    <p>暂无数据</p>
                </div>
            <?php else: ?>
                <table class="admin-data-table">
                    <thead>
                        <tr><th>访问者</th><th>互访圈</th><th>状态</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentVisits as $v): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['visitor_username']) ?></td>
                            <td><?= htmlspecialchars($v['circle_name']) ?></td>
                            <td>
                                <?php
                                switch ($v['status']) {
                                    case 'pending':  $statusClass = 'warning'; $statusText = '待审核'; break;
                                    case 'approved': $statusClass = 'success'; $statusText = '已通过'; break;
                                    case 'rejected': $statusClass = 'danger';  $statusText = '已拒绝'; break;
                                    default:         $statusClass = 'default'; $statusText = $v['status']; break;
                                }
                                ?>
                                <span class="admin-badge <?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
