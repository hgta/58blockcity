<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 获取未读消息数量
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
	
if (file_exists('config/database.php')) {
	require_once 'config/database.php';
	require_once 'includes/auth.php';
	require_once 'classes/Visit.php';
} elseif (file_exists('../config/database.php')) {
    require_once '../config/database.php';
	require_once '../includes/auth.php';
	require_once '../classes/Visit.php';
} 
    
	//checkLogin();
	
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
    <link rel="icon" href="../../assets/images/favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- 主样式 -->
	<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <!-- jQuery + Bootstrap JS -->
	<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
	<script src="../../assets/js/main.js"></script>
	
	<script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
	<script>LA.init({id:"JZPLceawkPERKGnB",ck:"JZPLceawkPERKGnB"})</script>
 
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