<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once 'includes/auth.php';
require_once '../classes/Visit.php';
require_once '../classes/Circle.php';  	
require_once '../classes/City.php';
	
$circle = new Circle($pdo);
			
// 扩展城市列表
$city = new City($pdo);
// 获取所有城市
$cities = $city->getAllCities();

$selectedCity = $_GET['city'] ?? '北京';
$circles = $circle->getCirclesByCity($selectedCity, 60);


// 获取未读消息数量
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
	$userId = $_SESSION['user_id'];
	$visit = new Visit($pdo);
	$pendingVisits = $visit->getCircleVisits($userId, 'pending');
	$unread_count = count($pendingVisits); 	
	
} 
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>58互访圈 - 城市间互访交流平台 | 58 BlockCity</title>
    <meta name="description" content="58互访圈是基于58区块城市的城市间互访交流平台，记录和管理城市间的互访活动。">
    <meta name="keywords" content="58,互访圈,城市互访,区块城市,BlockCity,DAO,同城交流">
    
    <!-- Favicon -->
    <link rel="icon" href="../assets/images/favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- 主样式 -->
	<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <!-- jQuery + Bootstrap JS -->
	<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
	<script src="../assets/js/main.js"></script>
	
	<script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
	<script>LA.init({id:"JZPLceawkPERKGnB",ck:"JZPLceawkPERKGnB"})</script>

	<style>
	/* 优化后的区块卡片样式 */
	.circle-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
		gap: 15px;
	}

	.circle-card {
		padding: 12px;
		border-radius: 8px;
		box-shadow: 0 2px 6px rgba(0,0,0,0.05);
		transition: transform 0.2s;
		height: 100%;
		display: flex;
		flex-direction: column;
	}

	.circle-card:hover {
		transform: translateY(-3px);
		box-shadow: 0 4px 12px rgba(0,0,0,0.1);
	}

	.circle-header {
		display: flex;
		align-items: center;
		margin-bottom: 1px;
	}

	.circle-body {
		padding: 10px;
	}

	.circle-avatar {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		object-fit: cover;
		margin-right: 10px;
	}

	.circle-title h3 {
		font-size: 16px;
		margin: 0;
		line-height: 1.3;
	}

	.circle-owner {
		font-size: 12px;
		color: #666;
	}

	.circle-body {
		flex-grow: 1;
		margin-bottom: 0px;
	}

	.circle-location {
		font-size: 13px;
		color: #555;
		margin-bottom: 8px;
	}

	.circle-category {
		display: inline-block;
		background: #f0f0f0;
		padding: 2px 6px;
		border-radius: 4px;
		font-size: 11px;
		margin-left: 5px;
	}

	.circle-description {
		font-size: 13px;
		color: #444;
		line-height: 1.4;
		margin-bottom: 8px;
		display: -webkit-box;
		-webkit-line-clamp: 3;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}

	.circle-stats {
		display: flex;
		justify-content: space-between;
		font-size: 12px;
		color: #777;
	}

	.circle-actions {
		display: flex;
		justify-content: space-between;
	}

	.circle-actions .btn {
		padding: 5px 10px;
		font-size: 12px;
	}

	/* 响应式调整 */
	@media (max-width: 768px) {
		.circle-grid {
			grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
		}
	}
	
	/* 头部样式修复 */
header {
    background-color: #ff6b00;
    color: white;
    padding: 15px 0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 100;
    width: 100%;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
    width: 100%;
    box-sizing: border-box;
}

.logo {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.logo-img {
    width: 40px;
    height: 40px;
    margin-right: 10px;
    background-color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 20px;
    color: #ff6b00;
    flex-shrink: 0;
}

.logo-text {
    font-size: 20px;
    font-weight: bold;
    white-space: nowrap;
}

.logo-text span {
    font-size: 14px;
    margin-left: 10px;
    opacity: 0.8;
    display: block;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-shrink: 0;
}

.nav-button {
    background-color: rgba(255,255,255,0.2);
    color: white;
    padding: 8px 15px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: bold;
    transition: all 0.3s;
    white-space: nowrap;
    position: relative;
    display: flex;
    align-items: center;
    text-decoration: none;
}

.nav-button:hover {
    background-color: rgba(255,255,255,0.3);
    transform: translateY(-2px);
    text-decoration: none;
    color: white;
}

.nav-button.home-button {
    background-color: white;
    color: #ff6b00;
}

.nav-button.home-button:hover {
    background-color: #f0f0f0;
    color: #ff6b00;
}

/* 修复通知徽章样式 */
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: #ff4757;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
    line-height: 1;
    min-width: 18px;
}

.notification-badge.small {
    width: 16px;
    height: 16px;
    font-size: 8px;
}

/* 城市定位提示条 */
.city-location-bar {
    background-color: #ff6b00;
    color: white;
    padding: 8px 0;
    text-align: center;
    font-size: 14px;
    width: 100%;
}

.city-location-bar a {
    color: white;
    text-decoration: underline;
    margin-left: 5px;
}

.city-location-bar a:hover {
    color: #f0f0f0;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        gap: 15px;
        padding: 0 10px;
    }
    
    .logo {
        margin-bottom: 0;
    }
    
    .header-actions {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .logo-img {
        width: 30px;
        height: 30px;
        font-size: 16px;
    }
    
    .logo-text {
        font-size: 18px;
    }
    
    .logo-text span {
        font-size: 12px;
    }
    
    .nav-button {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .notification-badge {
        width: 16px;
        height: 16px;
        font-size: 9px;
        top: -4px;
        right: -4px;
    }
}

@media (max-width: 576px) {
    .header-actions {
        flex-direction: column;
        gap: 8px;
    }
    
    .nav-button {
        width: 100%;
        justify-content: center;
    }
    
    .logo-text span {
        display: none; /* 小屏幕隐藏副标题 */
    }
}
	</style>
    
</head>
<body>
    <!-- 头部区域 -->
    <header>
		<div class="header-container">
            <div class="logo">
                <div class="logo-img">58</div>
                <div class="logo-text">
                    互访圈
                    <span>区块城市间互访交流平台</span>
                </div>
            </div>
			<div class="header-actions">
				
				<a href="../index.php" class="nav-button home-button">
					<i class="fas fa-home"></i> 返回首页
				</a>
				<!-- 添加排行榜链接 -->
				<a href="../rankings/index.php" class="nav-button">
					<i class="fas fa-trophy"></i> 排行榜
				</a>
				<?php if (isset($_SESSION['user_id'])): ?>
					<a href="../user/dashboard.php" class="nav-button">
						<i class="fas fa-user"></i> 个人中心
						<?php if ($unread_count > 0): ?>
							<span class="notification-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
						<?php endif; ?>
					</a>
					<a href="../auth/logout.php" class="nav-button">
						<i class="fas fa-sign-out-alt"></i> 退出
					</a>
				<?php else: ?>
					<a href="../auth/login.php" class="nav-button">
						<i class="fas fa-sign-in-alt"></i> 登录
					</a>
					<a href="../auth/register.php" class="nav-button">
						<i class="fas fa-user-plus"></i> 注册
					</a>
				<?php endif; ?>
			</div>
		</div>
    </header>
    
    <!-- 城市定位提示条 -->
    <div class="city-location-bar" id="cityLocationBar">
        欢迎您,来自于<span id="userCity">未知城市</span>的朋友，<a href="https://www.blockcity.pub/?iclc" id="cityLink">点击进入您所在城市的区块</a>
    </div>
    
    <main class="container">
	
<?php //require_once 'includes/header.php'; ?>


<div class="container">
    <!-- 城市筛选 -->
    <div class="city-filter">
        <h3><i class="fas fa-map-marker-alt"></i> 选择城市：</h3>
        <div class="city-tags">
            <?php foreach ($cities as $city): ?>
                <a href="?city=<?= urlencode($city['name']) ?>" class="city-tag <?= 
                    $city['name'] === $selectedCity ? 'active' : '' ?>">
                    <?= htmlspecialchars($city['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 互访圈列表 -->
    <div class="circle-list">
        <?php if (empty($circles)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-users-slash"></i>
                </div>
                <h3>该城市暂无互访圈</h3>
                <p>尝试选择其他城市或创建您自己的互访圈</p>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="circles/create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> 创建互访圈
                    </a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-primary">
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
								<?php //if ($circle['category']): ?>
									<span class="circle-category"><?= $circle['block_count']//htmlspecialchars($circle['category']) ?>区块</span>
								<?php //endif; ?>
							</div>
							
							<div class="circle-description">
								<?= nl2br(htmlspecialchars(mb_substr($circle['description'] ?? '暂无描述', 0, 60, 'UTF-8') . (mb_strlen($circle['description'] ?? '') > 60 ? '...' : ''))) ?>
							</div>
							
						</div>
						
						<div class="circle-actions">
							<a href="circles/view.php?id=<?= $circle['id'] ?>" class="btn btn-primary">
								<i class="fas fa-eye"></i> 详情
							</a>
							<?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $circle['user_id']): ?>
								<a href="circles/view.php?id=<?= $circle['id'] ?>" class="btn btn-outline-primary">
									<i class="fas fa-handshake"></i> 互访
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>