</main>
    
    <!-- 底部 -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>关于我们</h3>
                    <ul>
                        <li><a href="https://www.58.tl/">平台介绍</a></li>
                        <li><a href="https://www.58.tl/help/help.html">使用指南</a></li>
                        <li><a href="#">隐私政策</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>区块服务</h3>
                    <ul>
                        <li><a href="#">购买流程</a></li>
                        <li><a href="#">城市匹配</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>帮助中心</h3>
                    <ul>
                        <li><a href="https://www.blockcity.vip/pages/index/help/?iclc=1">常见问题</a></li>
                        <li><a href="#">联系我们</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>关注我们</h3>
                    <ul>
                        <li><a href="#"><i class="fab fa-weixin"></i> 微信公众号</a></li>
                        <li><a href="#"><i class="fab fa-discord"></i> Discord</a></li>
                    </ul>
                </div>
                <!-- 新增二维码模块 -->
                <div class="footer-column qr-column">
                    <h3>专属福利</h3>
                    <div class="qr-wrapper">
                        <div class="qr-pair">
                            <div class="qr-box">
                                <img src="/images/qr-discount.png" alt="7.5折优惠">
                                <span>扫码7.5折购地</span>
                            </div>
                            <div class="qr-box">
                                <img src="/images/qr-customer-service.png" alt="客服微信">
                                <span>官方客服微信</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="copyright">
                © 2025 58头像市场 | 58 BlockCity 版权所有 | 区块城市NFT头像交流平台
            </div>
        </div>
    </footer>

    

    <script>       	
		// 页面加载时获取城市信息
        window.onload = function() {
            getCityInfo();
        };
    </script>
	
	<style> 
/* 紧凑版底部布局 */
.footer-container {
    display: flex !important;
    flex-wrap: nowrap !important;
    justify-content: space-between;
    gap: 15px !important; /* 减少列间距 */
    width: 100%;
    margin-bottom: 20px;
}

.footer-column {
    flex: 1;
    min-width: 0;
    padding: 0 5px !important; /* 减少内边距 */
    box-sizing: border-box;
}

.footer-column:first-child {
    padding-left: 0 !important;
}

.footer-column:last-child {
    padding-right: 0 !important;
}

/* 二维码列样式 */
.qr-column {
    flex: 0 0 220px !important; /* 增加宽度 */
    max-width: 220px;
}

.qr-wrapper {
    width: 100%;
    margin-top: 5px; /* 与标题对齐 */
}

.qr-pair {
    display: flex;
    gap: 12px; /* 两个二维码之间的间距 */
    justify-content: center;
}

.qr-box {
    text-align: center;
    flex: 1;
}

.qr-box img {
    width: 85px; /* 增大二维码尺寸 */
    height: 85px;
    display: block;
    margin: 0 auto 6px;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    background: white;
    padding: 3px;
}

.qr-box span {
    display: block;
    font-size: 12px;
    color: #666;
    line-height: 1.4;
    font-weight: 500;
}

/* 紧凑的文字链接样式 */
.footer-column h3 {
    font-size: 14px;
    margin-bottom: 8px !important; /* 减少标题下边距 */
    
    font-weight: 600;
}

.footer-column ul {
    list-style: none;
    padding: 0 !important;
    margin: 0 !important;
}

.footer-column ul li {
    margin-bottom: 5px !important; /* 减少列表项间距 */
    line-height: 1.3;
}

.footer-column ul li a {
    font-size: 12px;
    color: #666;
    text-decoration: none;
    transition: color 0.2s;
    display: block;
    padding: 1px 0;
}

.footer-column ul li a:hover {
    color: #007bff;
}

.footer-column ul li a i {
    margin-right: 5px;
    width: 16px;
    text-align: center;
}

/* 版权信息样式 */
.copyright {
    text-align: center;
    padding-top: 15px;
    border-top: 1px solid #eee;
    font-size: 12px;
    color: #999;
    margin-top: 15px;
}

/* 响应式设计 */
@media (max-width: 1200px) {
    .footer-container {
        gap: 12px !important;
    }
    
    .qr-column {
        flex: 0 0 210px !important;
        max-width: 210px;
    }
    
    .qr-box img {
        width: 80px;
        height: 80px;
    }
}

@media (max-width: 992px) {
    .footer-container {
        flex-wrap: wrap !important;
        gap: 20px !important;
    }
    
    .footer-column {
        flex: 0 0 calc(33.333% - 14px) !important;
        max-width: calc(33.333% - 14px);
        margin-bottom: 15px;
    }
    
    .qr-column {
        flex: 0 0 calc(33.333% - 14px) !important;
        max-width: calc(33.333% - 14px);
    }
}

@media (max-width: 768px) {
    .footer-column {
        flex: 0 0 calc(50% - 10px) !important;
        max-width: calc(50% - 10px);
    }
    
    .qr-column {
        flex: 0 0 100% !important;
        max-width: 100%;
        order: 5; /* 二维码列放在最后 */
    }
    
    .qr-pair {
        justify-content: center;
        max-width: 300px;
        margin: 0 auto;
    }
}

@media (max-width: 480px) {
    .footer-column {
        flex: 0 0 100% !important;
        max-width: 100%;
        text-align: center;
    }
    
    .qr-pair {
        justify-content: space-around;
        max-width: 250px;
    }
    
    .qr-box img {
        width: 75px;
        height: 75px;
    }
}
</style> 
</body>
</html>