<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>区块城市新闻 - 58区块城市</title>
    <meta name="description" content="58区块城市最新新闻动态，包含综合新闻、城市新闻、元宇宙资讯等内容">
    <meta name="keywords" content="58,区块城市,新闻,元宇宙,BlockCity,DAO,同城资讯">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"Km945dEjfme2S7Eg",ck:"Km945dEjfme2S7Eg"})</script>
    <!-- 百度统计 -->
    <script>
    var _hmt = _hmt || [];
    (function() {
      var hm = document.createElement("script");
      hm.src = "https://hm.baidu.com/hm.js?5949e57aa9d2303fbf9451b06d4df471";
      var s = document.getElementsByTagName("script")[0]; 
      s.parentNode.insertBefore(hm, s);
    })();
    </script>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <style>
        :root {
            --primary-color: #FF6B00;
            --primary-light: #FFEEE5;
            --secondary-color: #333333;
            --text-color: #333333;
            --text-light: #666666;
            --text-lighter: #999999;
            --bg-color: #F8F8F8;
            --card-bg: #FFFFFF;
            --border-radius: 12px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'PingFang SC', 'Microsoft YaHei', 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        a {
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* 头部样式 */
        header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-img {
            width: 48px;
            height: 48px;
            background-color: white;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 22px;
            color: var(--primary-color);
            flex-shrink: 0;
        }
        
        .logo-text {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .logo-text span {
            display: block;
            font-size: 12px;
            opacity: 0.9;
            font-weight: 400;
            margin-top: 2px;
        }
        
        .user-actions {
            display: flex;
            gap: 12px;
        }
        
        .nav-button {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .nav-button:hover {
            background-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        
        /* 主要内容区域 */
        .main-content {
            padding: 40px 0;
        }
        
        .page-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .page-title {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }
        
        .page-title::after {
            content: "🔥 最新动态";
            position: absolute;
            right: -20px;
            top: -10px;
            font-size: 14px;
            background-color: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .page-subtitle {
            color: var(--text-light);
            font-size: 16px;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* 新闻列表 */
        .news-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }
        
        .news-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 107, 0, 0.15);
        }
        
        .news-card.highlight {
            border-left: 4px solid var(--primary-color);
        }
        
        .news-card-content {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .news-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 15px;
            line-height: 1.4;
            color: var(--secondary-color);
        }
        
        .news-card.highlight .news-title {
            color: var(--primary-color);
        }
        
        .news-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .news-category {
            background-color: var(--primary-light);
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .news-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .news-author {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .news-excerpt {
            color: var(--text-light);
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 15px;
            flex: 1;
        }
        
        .news-excerpt a {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .news-tags {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .news-tag {
            display: inline-flex;
            padding: 4px 12px;
            background-color: var(--primary-light);
            color: var(--primary-color);
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            align-items: center;
            gap: 5px;
        }
        
        /* 底部样式 */
        footer {
            background-color: var(--secondary-color);
            color: #CCCCCC;
            padding: 50px 0 30px;
            margin-top: 60px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .footer-column h3 {
            color: white;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-column h3::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 40px;
            height: 2px;
            background-color: var(--primary-color);
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: #AAAAAA;
            font-size: 14px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .footer-links a i {
            width: 16px;
            text-align: center;
        }
        
        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #444444;
            font-size: 13px;
            color: #999999;
        }
        
        /* 响应式设计 */
        @media (max-width: 992px) {
            .page-title {
                font-size: 32px;
            }
            
            .news-title {
                font-size: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .user-actions {
                width: 100%;
                justify-content: center;
            }
            
            .page-title {
                font-size: 28px;
            }
            
            .page-title::after {
                right: -10px;
                top: -15px;
            }
            
            .news-card-content {
                padding: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .logo {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .logo-img {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .logo-text span {
                font-size: 11px;
            }
            
            .user-actions {
                flex-wrap: wrap;
            }
            
            .nav-button {
                padding: 6px 12px;
                font-size: 13px;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .page-title::after {
                font-size: 12px;
                right: -15px;
                top: -18px;
            }
            
            .news-meta {
                gap: 10px;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- 头部区域 -->
    <header>
        <div class="container header-container">
            <a href="../index.php" class="logo">
                <div class="logo-img">58</div>
                <div class="logo-text">
                    区块城市
                    <span>元宇宙同城生活服务平台</span>
                </div>
            </a>
            <div class="user-actions">
                <a href="../index.php" class="nav-button">
                    <i class="fas fa-home"></i>
                    <span>返回首页</span>
                </a>
                <a href="../top200city.php" class="nav-button">
                    <i class="fas fa-city"></i>
                    <span>TOP200城市</span>
                </a>
                <a href="https://www.blockcity.vip/pages/user/user/?iclc=1" class="nav-button">
                    <i class="fas fa-user"></i>
                    <span>我的区块</span>
                </a>
            </div>
        </div>
    </header>
    
    <!-- 主要内容区域 -->
    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">区块城市新闻</h1>
                <p class="page-subtitle">获取最新元宇宙城市动态、区块销售信息和平台更新公告</p>
            </div>
            
            <!-- 新闻网格 -->
            <div class="news-grid">
                <!-- 新增新闻项：杭州A区售罄 -->
                <a href="#" class="news-card highlight">
                    <div class="news-card-content">
                        <h3 class="news-title">🚀 杭州A区创纪录售罄！继人气飙升后，区块城市杭州首区48小时抢购一空</h3>
                        <div class="news-meta">
                            <span class="news-category">杭州新闻</span>
                            <span class="news-date">
                                <i class="far fa-calendar-alt"></i>
                                2025-04-20
                            </span>
                            <span class="news-author">
                                <i class="fas fa-user-edit"></i>
                                58区块同城新闻部
                            </span>
                        </div>
                        <p class="news-excerpt">
                            继上周人气值飙升47%跃居全国第二后，<a href="../index.php">区块城市</a>杭州A区再创奇迹！今日凌晨，杭州A区所有区块在开放48小时内全部售罄，创下平台最快销售纪录。分析指出，这与杭州数字经济发展优势和区块城市近期推出的"数字景观共建计划"密不可分。首批投资者预计将获得不低于上海A区的分红收益...
                        </p>
                        <div class="news-tags">
                            <span class="news-tag">
                                <i class="fas fa-trophy"></i>
                                售罄纪录
                            </span>
                            <span class="news-tag">
                                <i class="fas fa-chart-line"></i>
                                投资热点
                            </span>
                        </div>
                    </div>
                </a>
                
                <!-- 新闻项1 -->
                <a href="/news/blockcity-slogan-upgrade-metaverse-pioneer.html" class="news-card">
                    <div class="news-card-content">
                        <h3 class="news-title">重磅升级！BlockCity品牌Slogan正式变更为"来做元宇宙的先行者"</h3>
                        <div class="news-meta">
                            <span class="news-category">综合新闻</span>
                            <span class="news-date">
                                <i class="far fa-calendar-alt"></i>
                                2025-04-15
                            </span>
                            <span class="news-author">
                                <i class="fas fa-user-edit"></i>
                                58区块同城新闻部
                            </span>
                        </div>
                        <p class="news-excerpt">
                            BlockCity<a href="../index.php">区块城市</a>今日宣布重大品牌升级，原Slogan"务实元宇宙的先行者"正式变更为"来做元宇宙的先行者"。这一变化标志着平台将从"务实建设"阶段迈向"开放共创"新纪元，邀请全球用户共同参与元宇宙建设...
                        </p>
                        <div class="news-tags">
                            <span class="news-tag">
                                <i class="fas fa-star"></i>
                                品牌升级
                            </span>
                        </div>
                    </div>
                </a>
                
                <!-- 新闻项2 -->
                <a href="#" class="news-card">
                    <div class="news-card-content">
                        <h3 class="news-title">🎉 上海A区售罄庆典！首批居民喜获高额分红，收益率超200%</h3>
                        <div class="news-meta">
                            <span class="news-category">上海新闻</span>
                            <span class="news-date">
                                <i class="far fa-calendar-alt"></i>
                                2025-04-15
                            </span>
                            <span class="news-author">
                                <i class="fas fa-user-edit"></i>
                                58区块同城新闻部
                            </span>
                        </div>
                        <p class="news-excerpt">
                            上海<a href="../index.php">区块城市</a>A区今日正式宣布售罄，成为首个完成全部区块销售的核心区域。根据DAO治理协议，首批购买居民将获得超额分红，平均收益率高达218%。庆祝活动将在元宇宙中持续一周...
                        </p>
                        <div class="news-tags">
                            <span class="news-tag">
                                <i class="fas fa-gift"></i>
                                售罄喜报
                            </span>
                        </div>
                    </div>
                </a>
                
                <!-- 新闻项3 -->
                <a href="#" class="news-card">
                    <div class="news-card-content">
                        <h3 class="news-title">🔥 杭州区块异军突起！两天连超深圳上海，跃居全国第二</h3>
                        <div class="news-meta">
                            <span class="news-category">杭州新闻</span>
                            <span class="news-date">
                                <i class="far fa-calendar-alt"></i>
                                2025-04-15
                            </span>
                            <span class="news-author">
                                <i class="fas fa-user-edit"></i>
                                58区块同城新闻部
                            </span>
                        </div>
                        <p class="news-excerpt">
                            惊人逆袭！杭州<a href="../index.php">区块城市</a>人气值两天暴涨47%，连续超越深圳和上海，目前仅次于北京，稳居全国第二。分析认为这与杭州数字经济优势和近期推出的"西湖数字景观"计划密切相关...
                        </p>
                        <div class="news-tags">
                            <span class="news-tag">
                                <i class="fas fa-arrow-up"></i>
                                排名飙升
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </main>
    
    <!-- 底部区域 -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>关于58区块城市</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-angle-right"></i>公司简介</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i>元宇宙愿景</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i>BlockCity技术</a></li>
                        <li><a href="https://www.blockcity.vip/pages/index/book"><i class="fas fa-angle-right"></i>DAO白皮书</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>帮助中心</h3>
                    <ul class="footer-links">
                        <li><a href="https://www.blockcity.vip/pages/index/help"><i class="fas fa-angle-right"></i>新手指南</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i>元宇宙入门</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i>DAO参与指南</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i>常见问题</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>商家服务</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-angle-right"></i>元宇宙店铺</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i>数字资产上架</a></li>
                        <li><a href="https://www.blockcity.vip/pages/index/block"><i class="fas fa-angle-right"></i>9区价格表</a></li>
                        <li><a href="http://blockcity.vip/zc/?iclc"><i class="fas fa-angle-right"></i>营销推广</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>关注我们</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fab fa-weixin"></i>微信公众号</a></li>
                        <li><a href="#"><i class="fab fa-weibo"></i>微博</a></li>
                        <li><a href="#"><i class="fab fa-discord"></i>Discord</a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i>Twitter</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                © 2025 58区块城市 | BlockCity DAO 版权所有 | 基于元宇宙技术的下一代同城服务平台
            </div>
        </div>
    </footer>
</body>
</html>