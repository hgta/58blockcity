<?php
/**
 * 共享底部组件
 * $site_config['footer_name'] = '58区块交易市场';
 */
$footerName = $site_config['footer_name'] ?? '58 BlockCity';
?>
</main>
    
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col footer-about">
                <h4>关于我们</h4>
                <p>58区块城市是基于元宇宙技术的下一代同城生活服务平台，整合 BlockCity DAO 社区治理，为用户提供虚拟城市探索、数字资产交易和社交互访的一站式体验。</p>
            </div>
            <div class="footer-col">
                <h4>快速链接</h4>
                <ul>
                    <li><a href="https://www.58.tl/"><i class="fas fa-home"></i> 平台首页</a></li>
                    <li><a href="https://block.58.tl/"><i class="fas fa-cubes"></i> 区块交易</a></li>
                    <li><a href="https://bct.58.tl/"><i class="fas fa-coins"></i> BCT 交易</a></li>
                    <li><a href="https://mall.58.tl/"><i class="fas fa-shopping-bag"></i> 人气商城</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>帮助支持</h4>
                <ul>
                    <li><a href="https://www.blockcity.vip/pages/index/help/?iclc=1"><i class="fas fa-book"></i> 使用指南</a></li>
                    <li><a href="https://www.blockcity.pub/?iclc"><i class="fas fa-map-marked-alt"></i> 进入城市区块</a></li>
                    <li><a href="https://www.blockcity.pub/?iclc=1"><i class="fas fa-users"></i> 加入 DAO</a></li>
                </ul>
            </div>
            <div class="footer-col footer-qr">
                <h4>专属福利</h4>
                <div class="qr-group">
                    <div class="qr-item">
                        <img src="/images/qr-discount.png" alt="7.5折购地">
                        <span>7.5折购地</span>
                    </div>
                    <div class="qr-item">
                        <img src="/images/qr-customer-service.png" alt="客服微信">
                        <span>客服微信</span>
                    </div>
                </div>
            </div>
            <div class="footer-col footer-contact">
                <h4>联系我们</h4>
                <ul>
                    <li><i class="fas fa-envelope"></i> support@58.tl</li>
                    <li><i class="fas fa-globe"></i> www.58.tl</li>
                    <li><i class="fas fa-map-marker-alt"></i> 元宇宙同城生态</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            © 2026 <?= htmlspecialchars($footerName) ?> | 58 BlockCity 版权所有
        </div>
    </div>
</footer>

<?php
if (!empty($site_config['footer_extra'])) {
    echo $site_config['footer_extra'];
}
?>

<script src="/city/city.js"></script>
<script>getCityInfo();</script>

</body>
</html>
