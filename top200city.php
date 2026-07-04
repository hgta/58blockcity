<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOP200热门城市 - 58区块城市</title>
    <meta name="description" content="58区块城市TOP200热门城市排名，基于居民人数和开启区块数量综合排序">
    <meta name="keywords" content="58,区块城市,区块同城,元宇宙,BlockCity,DAO,同城服务,本地生活,区块链城市,top200城市">
    <meta property="og:title" content="58区块城市 - 元宇宙同城生活服务平台">
    <meta property="og:description" content="基于元宇宙技术的下一代同城生活服务平台">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.58.tl">
    <link rel="canonical" href="https://www.58.tl">
    
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <link rel="manifest" href="/site.webmanifest" />
    
    <script charset="UTF-8" id="LA_COLLECT" src="js-sdk-pro.min.js"></script>
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
	<script src="/city/city.js"></script>
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
            padding: 15px 0;
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
            width: 50px;
            height: 50px;
            margin-right: 10px;
            background-color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
            color: #ff6b00;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: bold;
        }
        
        .logo-text span {
            font-size: 16px;
            margin-left: 10px;
            opacity: 0.8;
        }
        
        .user-actions {
            display: flex;
            gap: 15px;
        }
        
        .nav-button {
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .nav-button:hover {
            background-color: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        /* 主内容样式 */
        .top200-container {
            padding: 30px 0;
        }
        
        .page-title {
            font-size: 28px;
            color: #ff6b00;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .page-subtitle {
            font-size: 16px;
            color: #666;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .top200-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .top200-table th {
            background-color: #ff6b00;
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
        }
        
        .top200-table td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        .top200-table tr:nth-child(even) {
            background-color: #fff8f5;
        }
        
        .top200-table tr:hover {
            background-color: #ffefe6;
        }
        
        .rank {
            font-weight: bold;
            color: #ff6b00;
        }
        
        .city-name {
            font-weight: bold;
        }
        
        .city-name a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .city-name a:hover {
            color: #ff6b00;
            text-decoration: underline;
        }

        .top200-table tr:hover .city-name a {
            color: #ff6b00;
        }
        
        .stats {
            color: #666;
        }
        
        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .page-btn {
            padding: 8px 15px;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .page-btn:hover {
            background: #ffefe6;
        }
        
        .page-btn.active {
            background: #ff6b00;
            color: white;
            border-color: #ff6b00;
        }
        
        /* 底部 */
        footer {
            background-color: #333;
            color: #999;
            padding: 30px 0;
            font-size: 14px;
        }
        
        .footer-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
        }
        
        .footer-column h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .footer-column ul li {
            margin-bottom: 10px;
        }
        
        .footer-column a {
            color: #999;
            transition: color 0.3s;
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
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .user-actions {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .footer-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .top200-table {
                font-size: 14px;
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .top200-table th, 
            .top200-table td {
                padding: 8px 10px;
            }
            
            .page-title {
                font-size: 24px;
            }
        }
        
        @media (max-width: 576px) {
            .footer-container {
                grid-template-columns: 1fr;
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
                font-size: 14px;
            }
            
            .nav-button {
                padding: 8px 15px;
                font-size: 14px;
            }
            
            .page-btn {
                padding: 6px 12px;
                font-size: 14px;
            }
        }
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
		
		/* 新增：城市定位提示条 */
        .city-location-bar {
            background-color: #ff6b00;
            color: white;
            padding: 8px 0;
            text-align: center;
            font-size: 14px;
        }
        
        .city-location-bar a {
            color: white;
            text-decoration: underline;
            margin-left: 5px;
        }
    </style>
    <!-- BreadcrumbList 结构化数据 -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "58区块城市", "item": "https://www.58.tl/"},
        {"@type": "ListItem", "position": 2, "name": "TOP200热门城市"}
      ]
    }
    </script>
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
                <a href="index.php" class="nav-button">返回首页</a>
                <a href="news.php" class="nav-button">区块城市新闻</a>
                <a href="https://www.blockcity.vip/pages/user/user/?iclc=1" class="nav-button">我的区块</a>
            </div>
        </div>
    </header>
    
	<!-- 城市定位提示条 -->
    <div class="city-location-bar" id="cityLocationBar">
        欢迎您,来自于<span id="userCity">未知城市</span>的朋友，<a href="https://www.blockcity.pub/?iclc" id="cityLink">点击进入您所在城市的区块</a>
    </div>
	
    <!-- 主内容 -->
    <div class="container top200-container">
        <h1 class="page-title">全国TOP200热门城市</h1>
        <p class="page-subtitle">基于居民人数和开启区块数量综合排序 · 数据实时更新</p>
        
        <div class="pagination">
            <button class="page-btn active" onclick="showPage(1)">1-50</button>
            <button class="page-btn" onclick="showPage(2)">51-100</button>
            <button class="page-btn" onclick="showPage(3)">101-150</button>
            <button class="page-btn" onclick="showPage(4)">151-200</button>
        </div>
        
        <table class="top200-table" id="citiesTable">
            <thead>
                <tr>
                    <th>排名</th>
                    <th>城市名称</th>
                    <th>居民人数</th>
                    <th>开启区块</th>
                </tr>
            </thead>
            <tbody id="citiesTableBody">
                <!-- 数据将通过JavaScript动态加载 -->
            </tbody>
        </table>
    </div>

    <script>
        // 加载JSON数据并渲染表格
        fetch('cities-data.json')
            .then(response => response.json())
            .then(data => {
                const tableBody = document.getElementById('citiesTableBody');
                
                // 生成表格行
                data.cities.forEach(city => {
                    const row = document.createElement('tr');
                    row.className = 'page-'+Math.ceil(city.rank/50);
                    if(city.rank > 50) row.style.display = 'none';
                    /*
                    row.innerHTML = `
                        <td class="rank">${city.rank}</td>
                        <td class="city-name">
                            <a href="https://www.blockcity.pub/?iclc=1&areaNo=${city.areaNo}" title="${city.name}区块城市详情">
                                ${city.name}
                            </a>
                        </td>
                        <td class="stats">${city.population}</td>
                        <td class="stats">${city.blocks}</td>
                    `;*/
					row.innerHTML = '<td class="rank">'+city.rank+'</td><td class="city-name"><a href="https://www.blockcity.pub/'+city.areaNo+'?iclc" title="'+city.name+'区块城市详情">'+city.name+'</a></td><td class="stats">'+city.population+'</td><td class="stats">'+city.blocks+'</td>';
					
                    tableBody.appendChild(row);
                });
            })
            .catch(error => {
                console.error('加载城市数据失败:', error);
                document.getElementById('citiesTableBody').innerHTML = '<tr><td colspan="4" style="color:red;text-align:center;">数据加载失败，请刷新重试</td></tr>';
            });

        // 分页控制函数
        function showPage(pageNum) {
            // 隐藏所有行
            document.querySelectorAll('#citiesTableBody tr').forEach(row => {
                row.style.display = 'none';
            });
            
            // 显示当前页
            document.querySelectorAll(`.page-${pageNum}`).forEach(row => {
                row.style.display = '';
            });
            
            // 更新按钮状态
            document.querySelectorAll('.page-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
    
    <!-- 底部 -->
    <footer>
		<!-- 原有footer内容前添加 -->
        <!--<div class="container">
            <div class="domain-sale" style="margin-bottom: 30px;">
                <div class="domain-sale-text">💎 本网站域名诚意出售 | Domain Name For Sale</div>
                <a href="https://domainbatch.com/name/58.tl" class="domain-sale-link">联系购买 | Contact Now</a>
                <div class="domain-sale-english">Premium domain "58.tl" available for blockchain/metaverse projects</div>
            </div>
        </div>-->
		
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>关于58区块城市</h3>
                    <ul>
                        <li><a href="https://www.blockcity.vip/pages/index/company/?iclc">公司简介</a></li>
                        <li><a href="https://www.blockcity.vip/zt/pages/invest/plan/?iclc">元宇宙愿景</a></li>
                        <li><a href="https://www.blockcity.vip/pages/index/help3?iclc=1&id=72&type=7">产品介绍</a></li>
                        <li><a href="https://www.blockcity.pub/pages/index/book/?iclc=1">元宇宙白皮书</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>帮助中心</h3>
                    <ul>
                        <li><a href="https://www.blockcity.pub/pages/index/help/?iclc">新手指南</a></li>
                        <li><a href="#">元宇宙入门</a></li>
                        <li><a href="https://mp.weixin.qq.com/s/KWoNXzeldh3GxI9uS2O80g">用户答疑</a></li>
                        <li><a href="https://www.blockcity.vip/pages/index/help/?iclc">常见问题</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>商家服务</h3>
                    <ul>
                        <li><a href="news.php">区块新闻</a></li>
                        <li><a href="https://www.blockcity.biz/naquba/">元宇宙店铺</a></li>
                        <li><a href="https://www.blockcity.pub/pages/index/block/?iclc=1">9区价格表</a></li>
                        <li><a href="http://blockcity.pub/zc/?iclc">营销推广</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>关注我们</h3>
                    <ul>
                        <li><a href="#">BlockCity微信公众号</a></li>
                        <li><a href="#">BlockCity微博</a></li>
                        <li><a href="#">BlockCity小红书</a></li>
                        <li><a href="https://work.weixin.qq.com/kfid/kfc5e3b38b343460881">BlockCity在线客服</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                © 2025 58区块城市 | BlockCity DAO 版权所有 | 基于元宇宙技术的下一代同城服务平台
            </div>
        </div>
    </footer>
	
	<!-- 新增：优惠悬浮窗口 -->
    <div class="promotion-floating" id="promotionFloating">
        <div class="promotion-close" onclick="document.getElementById('promotionFloating').style.display='none'">×</div>
        <div class="promotion-header">
            <i>🎉</i> 限时优惠
        </div>
        <div class="promotion-content">
            凡通过本站购买各城市新区块，一律享<strong style="color:#ff6b00;">7.7折优惠</strong>！<br>
            详情请扫描下方二维码添加客服微信咨询。
        </div>
        <div class="promotion-qrcode">
            <img src="qr.jpg" alt="客服微信二维码">
        </div>
        <div style="text-align:center;font-size:12px;color:#999;">扫码添加客服微信</div>
    </div>

    <script>
        // 3秒后显示悬浮窗口
        setTimeout(function() {
            document.getElementById('promotionFloating').style.display = 'block';
        }, 3000);
		
		// 页面加载时获取城市信息
        window.onload = function() {
            getCityInfo();
        };
    </script>
</body>
</html>