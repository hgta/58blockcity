<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>区块城市新闻 - 58区块城市</title>
    <meta name="description" content="58区块城市最新新闻动态，包含综合新闻、城市新闻、元宇宙资讯等内容">
    <meta name="keywords" content="58,区块城市,新闻,元宇宙,BlockCity,DAO,同城资讯">
    
    <script charset="UTF-8" id="LA_COLLECT" src="//sdk.51.la/js-sdk-pro.min.js"></script>
    <script>LA.init({id:"Km945dEjfme2S7Eg",ck:"Km945dEjfme2S7Eg"})</script>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    <style>
        /* 全局样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Microsoft YaHei', Arial, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        a {
            text-decoration: none;
            color: #333;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* 头部样式 */
        header {
            background-color: #ff6b00;
            color: white;
            padding: 12px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo-img {
            width: 45px;
            height: 45px;
            margin-right: 10px;
            background-color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 22px;
            color: #ff6b00;
        }
        
        .logo-text {
            font-size: 22px;
            font-weight: bold;
        }
        
        .logo-text span {
            font-size: 14px;
            margin-left: 8px;
            opacity: 0.8;
        }
        
        .user-actions {
            display: flex;
            gap: 12px;
        }
        
        .nav-button {
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .nav-button:hover {
            background-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        /* 新闻列表内容 */
        .news-container {
            padding: 30px 0;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: bold;
            color: #ff6b00;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ff6b00;
        }
        
        /* 新闻分类导航 */
        .news-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .category-item {
            padding: 8px 16px;
            border-radius: 20px;
            background-color: #fff;
            color: #666;
            font-size: 14px;
            transition: all 0.3s;
            border: 1px solid #ddd;
        }
        
        .category-item:hover, .category-item.active {
            background-color: #ff6b00;
            color: white;
            border-color: #ff6b00;
        }
        
        /* 新闻列表 */
        .news-list {
            display: grid;
            gap: 20px;
        }
        
        .news-item {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .news-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255,107,0,0.1);
        }
        
        .news-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .news-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 13px;
            color: #666;
        }
        
        .news-category {
            background-color: #fff8f5;
            color: #ff6b00;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .news-date {
            display: flex;
            align-items: center;
        }
        
        .news-date::before {
            content: "🕒";
            margin-right: 5px;
        }
        
        .news-author {
            display: flex;
            align-items: center;
        }
        
        .news-author::before {
            content: "👤";
            margin-right: 5px;
        }
        
        .news-excerpt {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 40px;
            gap: 10px;
        }
        
        .page-link {
            display: inline-block;
            padding: 8px 16px;
            background-color: white;
            color: #666;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .page-link:hover, .page-link.active {
            background-color: #ff6b00;
            color: white;
        }
        
        /* 底部 - 优化样式 */
        footer {
            background-color: #333;
            color: #999;
            padding: 30px 0 25px;
            font-size: 13px;
        }
        
        .footer-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            padding: 0 15px;
        }
        
        .footer-column h3 {
            color: white;
            font-size: 15px;
            margin-bottom: 15px;
            padding-left: 0;
        }
        
        .footer-column ul {
            list-style-type: none;
            padding-left: 0;
        }
        
        .footer-column ul li {
            margin-bottom: 10px;
        }
        
        .footer-column a {
            color: #999;
            transition: color 0.3s;
            font-size: 13px;
            display: block;
            padding: 2px 0;
        }
        
        .footer-column a:hover {
            color: white;
        }
        
        .copyright {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #444;
            font-size: 12px;
            color: #bbb;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 12px;
            }
            
            .user-actions {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .footer-container {
                grid-template-columns: repeat(2, 1fr);
                padding: 0 20px;
            }
            
            .news-meta {
                gap: 8px;
            }
        }
        
        @media (max-width: 576px) {
            .footer-container {
                grid-template-columns: 1fr;
                padding: 0 15px;
            }
            
            .logo-img {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .logo-text {
                font-size: 20px;
            }
            
            .logo-text span {
                font-size: 12px;
            }
            
            .nav-button {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .news-title {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- 头部区域 -->
    <header>
        <div class="container header-container">
            <div class="logo">
                <div class="logo-img">58</div>
                <div class="logo-text">
                    区块城市
                    <span>元宇宙同城生活服务平台</span>
                </div>
            </div>
            <div class="user-actions">
                <a href="../index.php" class="nav-button">返回首页</a>
                <a href="../top200city.php" class="nav-button">TOP200城市</a>
                <a href="https://www.blockcity.vip/pages/user/user/?iclc=1" class="nav-button">我的区块</a>
            </div>
        </div>
    </header>
    
    <!-- 新闻列表内容 -->
    <div class="container news-container">
        <h1 class="page-title">区块城市新闻</h1>
        
        <!-- 新闻分类导航 -->
        <div class="news-categories">
            <a href="#" class="category-item active">全部新闻</a>
            <a href="#" class="category-item">综合新闻</a>
            <a href="#" class="category-item">北京新闻</a>
            <a href="#" class="category-item">上海新闻</a>
            <a href="#" class="category-item">深圳新闻</a>
            <a href="#" class="category-item">政策解读</a>
            <a href="#" class="category-item">元宇宙动态</a>
            <a href="#" class="category-item">城市公告</a>
        </div>
        
        <!-- 新闻列表 -->
        <div class="news-list">
            <!-- 新闻项1 -->
            <a href="#" class="news-item">
                <h3 class="news-title">BlockCity区块城市第二届元宇宙开发者大会在京圆满举行</h3>
                <div class="news-meta">
                    <span class="news-category">综合新闻</span>
                    <span class="news-date">2025-10-15</span>
                    <span class="news-author">58区块同城新闻部</span>
                </div>
                <p class="news-excerpt">
                    10月15日，58区块城市第二届元宇宙开发者大会在北京国家会议中心隆重举行。本次大会吸引了来自全国各地的2000余名开发者参与，共同探讨元宇宙技术的最新发展和应用场景...
                </p>
            </a>
            
            <!-- 新闻项2 -->
            <a href="#" class="news-item">
                <h3 class="news-title">上海区块城市推出"数字商圈"计划，首批100家商户入驻</h3>
                <div class="news-meta">
                    <span class="news-category">上海新闻</span>
                    <span class="news-date">2025-10-12</span>
                    <span class="news-author">58区块同城新闻部</span>
                </div>
                <p class="news-excerpt">
                    上海区块城市近日宣布推出"数字商圈"计划，旨在打造元宇宙中的商业生态圈。首批已有南京路步行街的100家知名商户完成数字孪生入驻，用户可通过区块城市APP体验虚拟购物...
                </p>
            </a>
            
            <!-- 新闻项4 -->
            <a href="#" class="news-item">
                <h3 class="news-title">深圳区块城市与腾讯达成战略合作，共建元宇宙基础设施</h3>
                <div class="news-meta">
                    <span class="news-category">深圳新闻</span>
                    <span class="news-date">2025-10-08</span>
                    <span class="news-author">58区块同城新闻部</span>
                </div>
                <p class="news-excerpt">
                    深圳区块城市与腾讯云签署战略合作协议，双方将在元宇宙基础设施建设、数字孪生技术、区块链应用等领域展开深度合作。腾讯将提供云计算支持，助力深圳区块城市打造更流畅的元宇宙体验...
                </p>
            </a>
			
            <!-- 新闻项3 -->
            <a href="#" class="news-item">
                <h3 class="news-title">北京区块城市DAO治理提案投票开启，涉及城市基金使用方案</h3>
                <div class="news-meta">
                    <span class="news-category">北京新闻</span>
                    <span class="news-date">2025-10-10</span>
                    <span class="news-author">58区块同城新闻部</span>
                </div>
                <p class="news-excerpt">
                    北京区块城市DAO治理委员会宣布，即日起开启三项重要提案的社区投票，包括城市基金使用方案、区块扩建计划和市长选举规则修订。所有持有北京区块的居民均可参与投票...
                </p>
            </a>
            
            
            <!-- 新闻项5 -->
            <a href="#" class="news-item">
                <h3 class="news-title">区块城市平台升级3.0版本，新增AR实景导航功能</h3>
                <div class="news-meta">
                    <span class="news-category">综合新闻</span>
                    <span class="news-date">2025-10-05</span>
                    <span class="news-author">58区块同城新闻部</span>
                </div>
                <p class="news-excerpt">
                    58区块城市平台今日正式升级至3.0版本，新增AR实景导航、数字资产交易市场和社交互动系统三大功能。用户现在可以通过手机摄像头实现虚实结合的导航体验，更直观地探索元宇宙城市...
                </p>
            </a>
            
            <!-- 新闻项6 -->
            <a href="#" class="news-item">
                <h3 class="news-title">杭州区块城市人气值突破百万，晋升全国前十</h3>
                <div class="news-meta">
                    <span class="news-category">城市公告</span>
                    <span class="news-date">2025-10-01</span>
                    <span class="news-author">58区块同城新闻部</span>
                </div>
                <p class="news-excerpt">
                    根据最新数据统计，杭州区块城市人气值已突破100万大关，成功跻身全国区块城市排名前十。平台将为此举办特别庆典活动，所有杭州区块持有者可领取限量版数字纪念徽章...
                </p>
            </a>
        </div>
        
        <!-- 分页 -->
        <div class="pagination">
            <a href="#" class="page-link">上一页</a>
            <a href="#" class="page-link active">1</a>
            <a href="#" class="page-link">2</a>
            <a href="#" class="page-link">3</a>
            <a href="#" class="page-link">4</a>
            <a href="#" class="page-link">5</a>
            <a href="#" class="page-link">下一页</a>
        </div>
    </div>
    
    <!-- 底部 - 优化样式 -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>关于58区块城市</h3>
                    <ul>
                        <li><a href="#">公司简介</a></li>
                        <li><a href="#">元宇宙愿景</a></li>
                        <li><a href="#">BlockCity技术</a></li>
                        <li><a href="https://www.blockcity.vip/pages/index/book">DAO白皮书</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>帮助中心</h3>
                    <ul>
                        <li><a href="https://www.blockcity.vip/pages/index/help">新手指南</a></li>
                        <li><a href="#">元宇宙入门</a></li>
                        <li><a href="#">DAO参与指南</a></li>
                        <li><a href="#">常见问题</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>商家服务</h3>
                    <ul>
                        <li><a href="#">元宇宙店铺</a></li>
                        <li><a href="#">数字资产上架</a></li>
                        <li><a href="https://www.blockcity.vip/pages/index/block">9区价格表</a></li>
                        <li><a href="http://blockcity.vip/zc/?iclc">营销推广</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>关注我们</h3>
                    <ul>
                        <li><a href="#">微信公众号</a></li>
                        <li><a href="#">微博</a></li>
                        <li><a href="#">Discord</a></li>
                        <li><a href="#">Twitter</a></li>
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