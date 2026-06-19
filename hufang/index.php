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
$search = trim($_GET['search'] ?? '');
$viewMode = $_GET['view'] ?? 'card'; // card or list

// 首页最多显示100个圈子
$circles = $circle->getCirclesByCity($selectedCity, 100, $search);
$totalCount = $circle->getCircleCountByCity($selectedCity, $search);

// 热门城市（圈子数最多的前20个）
$hotCities = $circle->getHotCities(20);
$hotCityNames = array_column($hotCities, 'city');

// 获取当前用户的访问状态映射
$visitedMap = [];
if (isset($_SESSION['user_id'])) {
	$userId = $_SESSION['user_id'];
	$visit = new Visit($pdo);
	$pendingVisits = $visit->getCircleVisits($userId, 'pending');
	$unread_count = count($pendingVisits); 	
	$visitedMap = $visit->getUserVisitedCircleIds($userId);
} else {
	$unread_count = 0;
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
    <link rel="stylesheet" href="https://58.tl/assets/css/main.css">
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
        <div class="city-tags" id="cityTags">
            <?php 
            // 显示热门城市（有圈子的）
            $displayedCities = [];
            foreach ($hotCities as $hc): 
                $displayedCities[] = $hc['city'];
            ?>
                <a href="?city=<?= urlencode($hc['city']) ?>" class="city-tag <?= $hc['city'] === $selectedCity ? 'active' : '' ?>">
                    <?= htmlspecialchars($hc['city']) ?>
                    <small style="opacity:0.6;font-size:10px;"><?= $hc['cnt'] ?></small>
                </a>
            <?php endforeach; ?>
            
            <?php 
            // 确保当前选中的城市显示
            if (!in_array($selectedCity, $displayedCities)): ?>
                <a href="?city=<?= urlencode($selectedCity) ?>" class="city-tag active"><?= htmlspecialchars($selectedCity) ?></a>
            <?php endif; ?>
            
            <!-- 展开全部城市 -->
            <a href="javascript:void(0)" class="city-tag" style="background:#6366f1;color:#fff;" onclick="toggleAllCities()">
                <i class="fas fa-chevron-down" id="expandIcon"></i> 展开全部
            </a>
        </div>
        <div class="city-tags" id="allCities" style="display:none;margin-top:8px;">
            <?php foreach ($cities as $c): ?>
                <?php if (!in_array($c['name'], $displayedCities)): ?>
                <a href="?city=<?= urlencode($c['name']) ?>" class="city-tag <?= $c['name'] === $selectedCity ? 'active' : '' ?>">
                    <?= htmlspecialchars($c['name']) ?>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 搜索 + 模式切换 -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px;">
            <input type="hidden" name="city" value="<?= htmlspecialchars($selectedCity) ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索圈子名称或描述..." 
                   style="flex:1;padding:8px 14px;border:1px solid #e0e0e0;border-radius:20px;font-size:14px;outline:none;">
            <button type="submit" class="btn btn-primary" style="border-radius:20px;padding:8px 16px;">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="?city=<?= urlencode($selectedCity) ?>" class="btn btn-outline" style="border-radius:20px;padding:8px 16px;">清除</a>
            <?php endif; ?>
        </form>
        <div style="display:flex;gap:6px;">
            <a href="?city=<?= urlencode($selectedCity) ?><?php echo $search ? '&search=' . urlencode($search) : '' ?>&view=card" 
               class="btn <?= $viewMode === 'card' ? 'btn-primary' : 'btn-outline' ?>" style="padding:6px 14px;font-size:13px;">
                <i class="fas fa-th-large"></i> 卡片
            </a>
            <a href="?city=<?= urlencode($selectedCity) ?><?php echo $search ? '&search=' . urlencode($search) : '' ?>&view=list" 
               class="btn <?= $viewMode === 'list' ? 'btn-primary' : 'btn-outline' ?>" style="padding:6px 14px;font-size:13px;">
                <i class="fas fa-list"></i> 列表
            </a>
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
        <?php elseif ($viewMode === 'list'): ?>
            <!-- 列表模式 -->
            <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                <thead>
                    <tr style="background:#f8f9fa;text-align:left;font-size:13px;color:#666;">
                        <th style="padding:12px 16px;">互访圈</th>
                        <th style="padding:12px 16px;">城市</th>
                        <th style="padding:12px 16px;">区块数</th>
                        <th style="padding:12px 16px;">圈主</th>
                        <th style="padding:12px 16px;">状态</th>
                        <th style="padding:12px 16px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($circles as $c): 
                        $visitStatus = $visitedMap[$c['id']] ?? null;
                        $statusBadge = '';
                        if ($visitStatus === 'completed') $statusBadge = '<span style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:10px;font-size:11px;">已互访</span>';
                        elseif (in_array($visitStatus, ['visited','returned'])) $statusBadge = '<span style="background:#d1ecf1;color:#0c5460;padding:2px 8px;border-radius:10px;font-size:11px;">已访</span>';
                        elseif (in_array($visitStatus, ['pending','confirmed'])) $statusBadge = '<span style="background:#fff3cd;color:#856404;padding:2px 8px;border-radius:10px;font-size:11px;">已访问</span>';
                    ?>
                    <tr style="border-bottom:1px solid #f0f0f0;font-size:14px;">
                        <td style="padding:10px 16px;"><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                        <td style="padding:10px 16px;"><?= htmlspecialchars($c['city']) ?></td>
                        <td style="padding:10px 16px;"><?= $c['block_count'] ?></td>
                        <td style="padding:10px 16px;"><?= htmlspecialchars($c['username']) ?></td>
                        <td style="padding:10px 16px;"><?= $statusBadge ?: '<span style="color:#ccc;">-</span>' ?></td>
                        <td style="padding:10px 16px;">
                            <a href="circles/view.php?id=<?= $c['id'] ?>" class="btn btn-primary" style="padding:4px 12px;font-size:12px;">详情</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <!-- 卡片模式 -->
            <div class="circle-grid">
				<?php foreach ($circles as $c): 
					$visitStatus = $visitedMap[$c['id']] ?? null;
					$badgeHtml = '';
					if ($visitStatus === 'completed') {
						$badgeHtml = '<div style="position:absolute;top:8px;right:8px;background:#22c55e;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;z-index:2;">已互访</div>';
					} elseif (in_array($visitStatus, ['visited','returned'])) {
						$badgeHtml = '<div style="position:absolute;top:8px;right:8px;background:#3b82f6;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;z-index:2;">已访</div>';
					} elseif (in_array($visitStatus, ['pending','confirmed'])) {
						$badgeHtml = '<div style="position:absolute;top:8px;right:8px;background:#f59e0b;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;z-index:2;">已访问</div>';
					}
				?>
					<div class="circle-card" style="position:relative;">
						<?= $badgeHtml ?>
						<div class="circle-header">
							<img src="../assets/images/<?= htmlspecialchars($c['avatar'] ?? 'default.jpg') ?>" 
								 class="circle-avatar" alt="<?= htmlspecialchars($c['username']) ?>">
							<div class="circle-title">
								<h3><?= htmlspecialchars($c['name']) ?></h3>
								<span class="circle-owner">
									<i class="fas fa-user"></i> <?= htmlspecialchars($c['username']) ?>
								</span>
							</div>
						</div>
						
						<div class="circle-body">
							<div class="circle-location">
								<i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($c['city']) ?>
								<span class="circle-category"><?= $c['block_count'] ?>区块</span>
							</div>
							
							<div class="circle-description">
								<?= nl2br(htmlspecialchars(mb_substr($c['description'] ?? '暂无描述', 0, 60, 'UTF-8') . (mb_strlen($c['description'] ?? '') > 60 ? '...' : ''))) ?>
							</div>
							
						</div>
						
						<div class="circle-actions">
							<a href="circles/view.php?id=<?= $c['id'] ?>" class="btn btn-primary">
								<i class="fas fa-eye"></i> 详情
							</a>
							<?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $c['user_id']): ?>
								<a href="circles/view.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary">
									<i class="fas fa-handshake"></i> 互访
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
        <?php endif; ?>
        
        <?php if ($totalCount > 100): ?>
        <div style="text-align:center;padding:20px;">
            <p style="color:#666;margin-bottom:10px;">显示了前 100 个圈子，共 <?= $totalCount ?> 个</p>
            <a href="circles/all.php?city=<?= urlencode($selectedCity) ?><?php echo $search ? '&search=' . urlencode($search) : '' ?>" 
               class="btn btn-primary" style="padding:10px 28px;">
                <i class="fas fa-list"></i> 查看全部 <?= $totalCount ?> 个圈子
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script>
function toggleAllCities() {
    var allCities = document.getElementById('allCities');
    var icon = document.getElementById('expandIcon');
    if (allCities.style.display === 'none') {
        allCities.style.display = 'flex';
        allCities.style.flexWrap = 'wrap';
        allCities.style.gap = '6px';
        icon.className = 'fas fa-chevron-up';
        icon.parentElement.lastChild.textContent = ' 收起';
    } else {
        allCities.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
        icon.parentElement.lastChild.textContent = ' 展开全部';
    }
}
</script>