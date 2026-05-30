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
	/* 新增：优惠悬浮窗口样式 */
        .promotion-floating {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 300px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            padding: 15px;
            z-index: 999;
            transform: translateY(20px);
            opacity: 0;
            animation: floatIn 0.5s forwards;
            border: 2px solid #ff6b00;
        }

        @keyframes floatIn {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .promotion-header {
            color: #ff6b00;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .promotion-header i {
            margin-right: 8px;
            font-size: 20px;
        }

        .promotion-content {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .promotion-qrcode {
            width: 100%;
            text-align: center;
            margin: 10px 0;
        }

        .promotion-qrcode img {
            width: 150px;
            height: 150px;
            border: 1px solid #eee;
        }

        .promotion-close {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            color: #999;
            font-size: 16px;
            text-align: center;
            line-height: 20px;
        }

        .promotion-close:hover {
            color: #ff6b00;
        }

        @media (max-width: 768px) {
            .promotion-floating {
                width: 250px;
                right: 15px;
                bottom: 15px;
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
                    NFT
                    <span>BlockCity区块城市NFT头像市场</span>
                </div>
            </div>
            <div class="header-actions">
                <a href="../" class="nav-button home-button">
                    <i class="fas fa-home"></i> 返回首页
                </a>
                <!-- 新增的导航按钮 -->
                <a href="../nft/sale_list.php" class="nav-button">
                    <i class="fas fa-tag"></i> 售卖
                </a>
                <a href="../nft/purchase_list.php" class="nav-button">
                    <i class="fas fa-hand-holding-usd"></i> 求购
                </a>
                <a href="../nft/claim_list.php" class="nav-button">
                    <i class="fas fa-hand-holding-heart"></i> 认领
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
        欢迎您,来自于<span id="userCity">未知城市</span>的朋友，<a href="https://www.blockcity.pub/?iclc" id="cityLink">点击进入您所在城市的区块</a>
    </div>
    
    <main class="container">