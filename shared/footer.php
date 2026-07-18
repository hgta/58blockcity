<?php
/**
 * 共享底部组件
 * $site_config['footer_name'] = '58区块交易市场';
 */
$footerName = $site_config['footer_name'] ?? '58 BlockCity';
?>
</main>

<!-- 跨子站引流 -->
<div class="cross-site-links" style="max-width:1200px;margin:30px auto 0;padding:0 15px;">
    <h3 style="font-size:18px;color:#333;margin-bottom:15px;text-align:center;">🌐 探索更多 58 生态</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
        <a href="https://block.58.tl/" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#fff9f0,#fff3e0);border-radius:10px;text-decoration:none;border:1px solid #ffe0b2;transition:transform .2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <span style="font-size:28px;">🏘</span>
            <div><div style="font-size:14px;font-weight:600;color:#333;">区块交易</div><div style="font-size:11px;color:#999;">认领你的虚拟地块</div></div>
        </a>
        <a href="https://bct.58.tl/" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#f0f8ff,#e3f2fd);border-radius:10px;text-decoration:none;border:1px solid #bbdefb;transition:transform .2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <span style="font-size:28px;">💹</span>
            <div><div style="font-size:14px;font-weight:600;color:#333;">BCT 交易</div><div style="font-size:11px;color:#999;">人气值自由买卖</div></div>
        </a>
        <a href="https://mall.58.tl/" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#f5fff5,#e8f5e9);border-radius:10px;text-decoration:none;border:1px solid #c8e6c9;transition:transform .2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <span style="font-size:28px;">🛍</span>
            <div><div style="font-size:14px;font-weight:600;color:#333;">人气商城</div><div style="font-size:11px;color:#999;">BCT支付购物</div></div>
        </a>
        <a href="https://nft.58.tl/" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#fdf0ff,#f3e5f5);border-radius:10px;text-decoration:none;border:1px solid #e1bee7;transition:transform .2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <span style="font-size:28px;">🎨</span>
            <div><div style="font-size:14px;font-weight:600;color:#333;">NFT 头像</div><div style="font-size:11px;color:#999;">数字收藏头像</div></div>
        </a>
        <a href="https://v.58.tl/" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#fff8f0,#fce4ec);border-radius:10px;text-decoration:none;border:1px solid #f8bbd0;transition:transform .2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
            <span style="font-size:28px;">🤝</span>
            <div><div style="font-size:14px;font-weight:600;color:#333;">互访圈</div><div style="font-size:11px;color:#999;">同城社交互访</div></div>
        </a>
    </div>
</div>

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

<!-- 回到顶部 -->
<button id="back-to-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="回到顶部" style="position:fixed;bottom:24px;right:24px;width:44px;height:44px;background:#ff6b00;color:#fff;border:none;border-radius:50%;font-size:20px;cursor:pointer;z-index:999;display:none;box-shadow:0 2px 8px rgba(0,0,0,0.2);transition:opacity 0.3s;">↑</button>
<script>
(function(){
    var btn = document.getElementById('back-to-top');
    window.addEventListener('scroll', function() {
        btn.style.display = window.scrollY > 300 ? 'block' : 'none';
    });
})();
</script>

<script>
(function(){
    var toggle = document.getElementById('menuToggle');
    var actions = document.getElementById('headerActions');
    var overlay = document.getElementById('menuOverlay');
    if (!toggle || !actions) return;

    function openMenu() {
        actions.classList.add('open');
        if (overlay) overlay.classList.add('open');
        toggle.innerHTML = '✕';
        toggle.setAttribute('aria-expanded', 'true');
    }
    function closeMenu() {
        actions.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
        toggle.innerHTML = '☰';
        toggle.setAttribute('aria-expanded', 'false');
    }
    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        actions.classList.contains('open') ? closeMenu() : openMenu();
    });
    if (overlay) {
        overlay.addEventListener('click', closeMenu);
    }
    document.addEventListener('click', function(e) {
        if (actions.classList.contains('open') && !actions.contains(e.target) && e.target !== toggle) {
            closeMenu();
        }
    });
})();
</script>

</body>
</html>
