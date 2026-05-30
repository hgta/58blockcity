<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Circle.php';
require_once '../../classes/User.php';

$circle = new Circle($pdo);
$user = new User($pdo);

$selectedCity = $_GET['city'] ?? '北京';
$searchQuery = $_GET['search'] ?? '';

$cities = ['北京', '杭州', '深圳', '上海', '中国数藏', '广州', '成都', '重庆', '天津', '苏州', '西安', '太原', '合肥', 
			'武汉', '南京', '济南', '长沙', '青岛', '宁波', '贵阳', '郑州', '昆明', '沈阳', '金华', '无锡', '厦门', '周口', '东莞', 
			'烟台', '海口', '宁德', '枣庄', '珠海', '惠州', '福州', '湖州', '常州', '南昌', '佛山', '肇庆', '嘉兴', '绍兴', '温州',
			'南宁', '舟山', '哈尔滨', '石家庄', '潮州', '藏南', '中山', '乌鲁木齐', '安顺', '大连', '济宁', '云浮', '长春', '济宁', 
			'徐州', '洛阳', '泉州', '连云港', '临沂', '台州', '蚌埠', '马鞍山', '汕头', '潍坊', '西宁', '沧州', '三亚', '威海', '兰州', 
			'扬州', '衢州', '南平', '淄博', '遵义', '鄂尔多斯', '茂名', '呼和浩特', '拉萨', '芜湖', '景德镇', '泰安', '聊城', '三明', 
			'银川', '营口', '朝阳', '吴忠', '新余', '铁岭', '自贡', '铜仁', '葫芦岛', '芜湖'];
$circles = $circle->getCirclesByCity($selectedCity, 20, $searchQuery);

// 获取当前用户信息（如果已登录）
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $currentUser = $user->getUserById($_SESSION['user_id']);
}
?>

<?php require_once '../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> 互访圈探索</h1>
        <p>发现您所在城市的互访圈，开始互动交流</p>
    </div>

    <div class="circle-filters">
        <div class="city-filter">
            <h3><i class="fas fa-map-marker-alt"></i> 选择城市：</h3>
            <div class="city-tags">
                <?php foreach ($cities as $city): ?>
                    <a href="?city=<?= urlencode($city) ?>" class="city-tag <?= 
                        $city === $selectedCity ? 'active' : '' ?>">
                        <?= htmlspecialchars($city) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="search-filter">
            <form method="get" class="search-form">
                <input type="hidden" name="city" value="<?= htmlspecialchars($selectedCity) ?>">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="搜索互访圈名称或描述..." 
                           value="<?= htmlspecialchars($searchQuery) ?>">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> 搜索
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($circles)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-users-slash"></i>
            </div>
            <h3>当前城市暂无互访圈</h3>
            <p>尝试选择其他城市或创建您自己的互访圈</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> 创建互访圈
                </a>
            <?php else: ?>
                <a href="../auth/login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> 登录后创建互访圈
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="circle-grid">
            <?php foreach ($circles as $circle): ?>
                <div class="circle-card">
                    <div class="circle-header">
                        <img src="../assets/images/<?= htmlspecialchars($circle['avatar'] ?? 'default.jpg') ?>" 
                             class="circle-avatar" alt="<?= htmlspecialchars($circle['username']) ?>">
                        <div class="circle-title">
                            <h3><?= htmlspecialchars($circle['name']) ?></h3>
                            <span class="circle-owner">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($circle['username']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="circle-body">
                        <div class="circle-location">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($circle['city']) ?>
                            <?php if ($circle['category']): ?>
                                <span class="circle-category"><?= htmlspecialchars($circle['category']) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="circle-description">
                            <?= nl2br(htmlspecialchars($circle['description'] ?? '暂无描述')) ?>
                        </div>
                        
                        <div class="circle-stats">
                            <span class="stat-item">
                                <i class="fas fa-exchange-alt"></i> <?= $circle['block_count'] ?> 区块
                            </span>
                            <span class="stat-item">
                                <i class="fas fa-calendar-alt"></i> <?= date('Y-m-d', strtotime($circle['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="circle-actions">
                        <a href="view.php?id=<?= $circle['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> 查看详情
                        </a>
                        <?php if (isset($_SESSION['user_id']) && $currentUser['city'] === $circle['city']): ?>
                            <a href="view.php?id=<?= $circle['id'] ?>#request" class="btn btn-outline-primary">
                                <i class="fas fa-handshake"></i> 申请访问
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>