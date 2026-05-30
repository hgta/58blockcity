<?php
require_once '../../config/database.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';

$circle = new Circle($pdo);
$user = new User($pdo);

// 获取各排行榜数据
$cityRankings = $pdo->query("SELECT * FROM city_rankings ORDER BY circle_count DESC LIMIT 3")->fetchAll();
$popularCityRankings = $pdo->query("SELECT * FROM city_rankings ORDER BY visit_count DESC LIMIT 3")->fetchAll();
$activeCityRankings = $pdo->query("SELECT * FROM city_rankings ORDER BY completed_visit_count DESC LIMIT 3")->fetchAll();

$mostVisitedCircles = $pdo->query("SELECT * FROM circle_rankings ORDER BY total_visits DESC LIMIT 3")->fetchAll();
$mostCompletedCircles = $pdo->query("SELECT * FROM circle_rankings ORDER BY completed_visits DESC LIMIT 3")->fetchAll();
$largestCircles = $pdo->query("SELECT * FROM circle_rankings ORDER BY block_count DESC LIMIT 3")->fetchAll();

$topOwners = $pdo->query("SELECT * FROM user_rankings ORDER BY owned_circles DESC LIMIT 3")->fetchAll();
$topBlockOwners = $pdo->query("SELECT * FROM user_rankings ORDER BY total_blocks DESC LIMIT 3")->fetchAll();
$mostActiveVisitors = $pdo->query("SELECT * FROM user_rankings ORDER BY visits_made DESC LIMIT 3")->fetchAll();
$mostPopularOwners = $pdo->query("SELECT * FROM user_rankings ORDER BY visits_received DESC LIMIT 3")->fetchAll();
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-trophy"></i> 互访圈排行榜</h1>
        <p>发现最活跃的城市、圈子和用户</p>
    </div>

    <div class="ranking-tabs">
        <ul class="nav nav-tabs" id="rankingTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="city-tab" data-toggle="tab" href="#city" role="tab">城市榜</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="circle-tab" data-toggle="tab" href="#circle" role="tab">圈子榜</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="user-tab" data-toggle="tab" href="#user" role="tab">个人榜</a>
            </li>
        </ul>
        
        <div class="tab-content" id="rankingTabsContent">
            <!-- 城市榜 -->
            <div class="tab-pane fade show active" id="city" role="tabpanel">
                <div class="ranking-cards">
                    <div class="ranking-card">
                        <h3><i class="fas fa-city"></i> 圈子最多城市</h3>
                        <ol class="ranking-list">
                            <?php foreach ($cityRankings as $index => $city): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <span class="name"><?= htmlspecialchars($city['city']) ?></span>
                                <span class="value"><?= $city['circle_count'] ?>个圈子</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <a href="city.php?type=circle_count" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-users"></i> 互访最活跃城市</h3>
                        <ol class="ranking-list">
                            <?php foreach ($activeCityRankings as $index => $city): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <span class="name"><?= htmlspecialchars($city['city']) ?></span>
                                <span class="value"><?= $city['completed_visit_count'] ?>次互访</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <a href="city.php?type=completed_visit_count" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-user-friends"></i> 用户最多城市</h3>
                        <ol class="ranking-list">
                            <?php foreach ($cityRankings as $index => $city): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <span class="name"><?= htmlspecialchars($city['city']) ?></span>
                                <span class="value"><?= $city['user_count'] ?>位用户</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <a href="city.php?type=user_count" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                </div>
            </div>
            
            <!-- 圈子榜 -->
            <div class="tab-pane fade" id="circle" role="tabpanel">
                <div class="ranking-cards">
                    <div class="ranking-card">
                        <h3><i class="fas fa-star"></i> 最受欢迎圈子</h3>
                        <ol class="ranking-list">
                            <?php foreach ($mostVisitedCircles as $index => $circle): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <span class="name"><?= htmlspecialchars($circle['name']) ?></span>
                                <span class="value"><?= $circle['total_visits'] ?>次访问</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <a href="circle.php?type=total_visits" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-check-circle"></i> 互访完成最多</h3>
                        <ol class="ranking-list">
                            <?php foreach ($mostCompletedCircles as $index => $circle): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <span class="name"><?= htmlspecialchars($circle['name']) ?></span>
                                <span class="value"><?= $circle['completed_visits'] ?>次完成</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <a href="circle.php?type=completed_visits" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-cubes"></i> 区块最多圈子</h3>
                        <ol class="ranking-list">
                            <?php foreach ($largestCircles as $index => $circle): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <span class="name"><?= htmlspecialchars($circle['name']) ?></span>
                                <span class="value"><?= $circle['block_count'] ?>区块</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <a href="circle.php?type=block_count" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                </div>
            </div>
            
            <!-- 个人榜 -->
            <div class="tab-pane fade" id="user" role="tabpanel">
                <div class="ranking-cards">
                    <div class="ranking-card">
                        <h3><i class="fas fa-crown"></i> 圈子最多用户</h3>
                        <ol class="ranking-list">
                            <?php foreach ($topOwners as $index => $user): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <img src="../../assets/images/<?= htmlspecialchars($user['avatar'] ?? 'default.jpg') ?>" class="avatar-xs">
                                <span class="name"><?= htmlspecialchars($user['username']) ?></span>
                                <span class="value"><?= $user['owned_circles'] ?>个圈子</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <a href="user.php?type=owned_circles" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-shield-alt"></i> 区块最多用户</h3>
                        <ol class="ranking-list">
                            <?php foreach ($topBlockOwners as $index => $user): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <img src="../../assets/images/<?= htmlspecialchars($user['avatar'] ?? 'default.jpg') ?>" class="avatar-xs">
                                <span class="name"><?= htmlspecialchars($user['username']) ?></span>
                                <span class="value"><?= $user['total_blocks'] ?>区块</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <a href="user.php?type=total_blocks" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                    
                    <div class="ranking-card">
                        <h3><i class="fas fa-bolt"></i> 最活跃访问者</h3>
                        <ol class="ranking-list">
                            <?php foreach ($mostActiveVisitors as $index => $user): ?>
                            <li>
                                <span class="rank"><?= $index + 1 ?></span>
                                <img src="../../assets/images/<?= htmlspecialchars($user['avatar'] ?? 'default.jpg') ?>" class="avatar-xs">
                                <span class="name"><?= htmlspecialchars($user['username']) ?></span>
                                <span class="value"><?= $user['visits_made'] ?>次访问</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <a href="user.php?type=visits_made" class="btn btn-sm btn-outline-primary">查看完整榜单</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>