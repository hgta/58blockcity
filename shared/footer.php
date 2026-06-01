<?php
/**
 * 共享底部组件
 * $site_config['footer_name'] = '58区块交易市场';
 */
$footerName = $site_config['footer_name'] ?? '58 BlockCity';
?>
</main>
    
<footer style="background:#1a1a2e;color:#94a3b8;padding:48px 0 20px;margin-top:40px;">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 15px;">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1.2fr 1fr;gap:25px;margin-bottom:30px;">
            <div>
                <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">关于我们</h4>
                <p style="font-size:13px;line-height:1.8;color:#64748b;">
                    58区块城市是基于元宇宙技术的下一代同城生活服务平台，整合BlockCity DAO社区治理，为用户提供虚拟城市探索、数字资产交易和社交互访的一站式体验。
                </p>
            </div>
            <div>
                <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">快速链接</h4>
                <ul style="list-style:none;padding:0;font-size:13px;line-height:2.2;">
                    <li><a href="https://www.58.tl/" style="color:#64748b;">平台首页</a></li>
                    <li><a href="https://block.58.tl/" style="color:#64748b;">区块交易</a></li>
                    <li><a href="https://bct.58.tl/" style="color:#64748b;">BCT交易</a></li>
                    <li><a href="https://mall.58.tl/" style="color:#64748b;">人气商城</a></li>
                </ul>
            </div>
            <div>
                <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">帮助支持</h4>
                <ul style="list-style:none;padding:0;font-size:13px;line-height:2.2;">
                    <li><a href="https://www.blockcity.vip/pages/index/help/?iclc=1" style="color:#64748b;">使用指南</a></li>
                    <li><a href="https://www.blockcity.pub/?iclc" style="color:#64748b;">进入城市区块</a></li>
                    <li><a href="https://www.blockcity.pub/?iclc=1" style="color:#64748b;">加入DAO</a></li>
                </ul>
            </div>
            <div>
                <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">专属福利</h4>
                <div style="display:flex;gap:10px;">
                    <img src="/hufang/images/qr-discount.png" alt="7.5折购地" style="width:75px;height:75px;background:#fff;border-radius:6px;padding:3px;">
                    <img src="/hufang/images/qr-customer-service.png" alt="客服微信" style="width:75px;height:75px;background:#fff;border-radius:6px;padding:3px;">
                </div>
                <div style="font-size:10px;color:#64748b;margin-top:6px;display:flex;gap:10px;">
                    <span style="width:75px;text-align:center;">7.5折购地</span>
                    <span style="width:75px;text-align:center;">客服微信</span>
                </div>
            </div>
            <div>
                <h4 style="color:#fff;margin-bottom:12px;font-size:15px;">联系我们</h4>
                <p style="font-size:13px;color:#64748b;line-height:2;">
                    📧 support@58.tl<br>
                    🌐 www.58.tl<br>
                    📍 元宇宙同城生态
                </p>
            </div>
        </div>
        <div style="border-top:1px solid #1e293b;padding-top:20px;text-align:center;font-size:12px;color:#475569;">
            © 2025 <?= htmlspecialchars($footerName) ?> | 58 BlockCity 版权所有
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
