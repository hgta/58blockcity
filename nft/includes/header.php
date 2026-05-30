<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>区块城市NFT头像市场 - BlockCity区块城市NFT头像交易平台 | BlockCity NFT</title>
    <meta name="description" content="BlockCity区块城市NFT头像交易平台。">
    <meta name="keywords" content="58,NFT,头像,区块城市,BlockCity,DAO,同城交流,Avatar">
    
    <!-- Favicon -->
    <link rel="icon" href="https://58.tl/assets/images/favicon.ico">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- 主样式 -->
    <link rel="stylesheet" href="https://58.tl/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://58.tl/assets/css/main.css">
    <!-- jQuery + Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://58.tl/assets/js/main.js"></script>
    
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"KplyYLrcc6uYhdjv",ck:"KplyYLrcc6uYhdjv"})</script>
    
	<style>
	.avatar-sm {
    width: 32px;
    height: 32px;
    object-fit: cover;
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
                    NFT
                    <span>BlockCity区块城市头像市场</span>
                </div>
            </div>
            <div class="header-actions">
                <a href="../" class="nav-button home-button">
                    <i class="fas fa-home"></i> 返回首页
                </a>
				
				<a href="../ranking/index.php" class="nav-button">
					<i class="fas fa-trophy"></i> 排行榜
				</a>
                
				<a href="../nft/claim_list.php" class="nav-button">
                    <i class="fas fa-hand-holding-heart"></i> 认领
                </a>
                <a href="../nft/sale_list.php" class="nav-button">
                    <i class="fas fa-tag"></i> 售卖
                </a>
                <a href="../nft/purchase_list.php" class="nav-button">
                    <i class="fas fa-hand-holding-usd"></i> 求购
                </a>
                
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../user/dashboard.php" class="nav-button">
                        <i class="fas fa-user"></i> 个人中心
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
        欢迎您,来自于<span id="userCity">未知城市</span>的朋友，<a href="https://www.blockcity.pub/?iclc" id="cityLink">点击进入您的区块城市</a>
    </div>
    
    <main class="container">