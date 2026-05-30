<?php
// 检查用户是否登录且是管理员
//if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] !== 1/* $_SESSION['user_role'] !== 'admin' */) {
    //header('Location: ../login.php');
//    exit;
//}
?>

<!-- 管理员侧边栏导航 -->
<div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <!-- 管理员信息区域 -->
        <div class="text-center mb-4">
            <img src="../assets/images/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default-admin.jpg') ?>" 
                 class="rounded-circle mb-2" 
                 width="80" 
                 height="80"
                 alt="管理员头像">
            <h6 class="text-white mb-1"><?= htmlspecialchars($_SESSION['username']) ?></h6>
            <span class="badge bg-primary">系统管理员</span>
        </div>

        <!-- 主导航菜单 -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active text-white" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    控制面板
                </a>
            </li>
            
            <!-- NFT管理 -->
            <li class="nav-item">
                <a class="nav-link text-white" data-bs-toggle="collapse" href="#nftMenu" role="button">
                    <i class="fas fa-user-circle me-2"></i>
                    NFT管理
                    <i class="fas fa-angle-down float-end mt-1"></i>
                </a>
                <div class="collapse show" id="nftMenu">
                    <ul class="nav flex-column ps-4">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="nfts.php">
                                <i class="fas fa-list me-2"></i>
                                所有NFT
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="nft_add.php">
                                <i class="fas fa-plus-circle me-2"></i>
                                添加NFT
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="nft_categories.php">
                                <i class="fas fa-tags me-2"></i>
                                分类管理
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- 用户管理 -->
            <li class="nav-item">
                <a class="nav-link text-white" data-bs-toggle="collapse" href="#userMenu" role="button">
                    <i class="fas fa-users me-2"></i>
                    用户管理
                    <i class="fas fa-angle-down float-end mt-1"></i>
                </a>
                <div class="collapse" id="userMenu">
                    <ul class="nav flex-column ps-4">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="users.php">
                                <i class="fas fa-list me-2"></i>
                                所有用户
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="user_add.php">
                                <i class="fas fa-user-plus me-2"></i>
                                添加用户
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="user_roles.php">
                                <i class="fas fa-user-shield me-2"></i>
                                角色权限
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- 交易管理 -->
            <li class="nav-item">
                <a class="nav-link text-white" href="transactions.php">
                    <i class="fas fa-exchange-alt me-2"></i>
                    交易记录
                </a>
            </li>
            
            <!-- 城市管理 -->
            <li class="nav-item">
                <a class="nav-link text-white" data-bs-toggle="collapse" href="#cityMenu" role="button">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    城市管理
                    <i class="fas fa-angle-down float-end mt-1"></i>
                </a>
                <div class="collapse" id="cityMenu">
                    <ul class="nav flex-column ps-4">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="cities.php">
                                <i class="fas fa-city me-2"></i>
                                所有城市
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="city_add.php">
                                <i class="fas fa-plus me-2"></i>
                                添加城市
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="city_popularity.php">
                                <i class="fas fa-fire me-2"></i>
                                人气排行
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- 系统设置 -->
            <li class="nav-item">
                <a class="nav-link text-white" href="settings.php">
                    <i class="fas fa-cog me-2"></i>
                    系统设置
                </a>
            </li>
            
            <!-- 数据报表 -->
            <li class="nav-item">
                <a class="nav-link text-white" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    数据报表
                </a>
            </li>
        </ul>

        <!-- 底部区域 -->
        <div class="position-absolute bottom-0 start-0 p-3 w-100">
            <div class="border-top pt-3">
                <a href="../index.php" class="btn btn-outline-light btn-sm w-100 mb-2">
                    <i class="fas fa-globe me-2"></i>
                    访问前台
                </a>
                <a href="../logout.php" class="btn btn-danger btn-sm w-100">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    退出登录
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 移动端折叠按钮 -->
<button class="navbar-toggler position-fixed d-md-none collapsed" type="button" 
        data-bs-toggle="collapse" data-bs-target=".sidebar" 
        aria-controls="sidebar" aria-expanded="false" aria-label="切换导航">
    <span class="navbar-toggler-icon"></span>
</button>