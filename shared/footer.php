<?php
/**
 * 共享底部组件
 * $site_config['footer_name'] = '58区块交易市场';
 */
$footerName = $site_config['footer_name'] ?? '58 BlockCity';
?>
</main>
    
<footer style="background:#222;color:#ccc;padding:40px 0 20px;margin-top:40px;">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 15px;">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:30px;margin-bottom:30px;">
            <div>
                <h4 style="color:white;margin-bottom:12px;font-size:16px;">关于我们</h4>
                <p style="font-size:13px;line-height:1.8;color:#999;">
                    58区块城市是基于元宇宙技术的下一代同城生活服务平台，整合BlockCity DAO社区治理，为用户提供虚拟城市探索、数字资产交易和社交互访的一站式体验。
                </p>
            </div>
            <div>
                <h4 style="color:white;margin-bottom:12px;font-size:16px;">快速链接</h4>
                <ul style="list-style:none;padding:0;font-size:13px;line-height:2;color:#999;">
                    <li><a href="https://www.58.tl/" style="color:#999;">平台介绍</a></li>
                    <li><a href="https://www.blockcity.pub/?iclc" style="color:#999;">进入城市区块</a></li>
                    <li><a href="https://www.blockcity.vip/pages/index/help/?iclc=1" style="color:#999;">使用指南</a></li>
                    <li><a href="https://www.blockcity.pub/?iclc=1" style="color:#999;">加入DAO社区</a></li>
                </ul>
            </div>
            <div>
                <h4 style="color:white;margin-bottom:12px;font-size:16px;">关注我们</h4>
                <div style="display:flex;gap:15px;font-size:24px;">
                    <a href="#" style="color:#999;"><i class="fab fa-weixin"></i></a>
                    <a href="#" style="color:#999;"><i class="fab fa-weibo"></i></a>
                    <a href="#" style="color:#999;"><i class="fab fa-discord"></i></a>
                </div>
            </div>
            <div>
                <h4 style="color:white;margin-bottom:12px;font-size:16px;">联系我们</h4>
                <p style="font-size:13px;color:#999;line-height:1.8;">
                    📧 support@58.tl<br>
                    🌐 www.58.tl<br>
                    📱 微信公众号：58区块城市
                </p>
            </div>
        </div>
        <div style="border-top:1px solid #333;padding-top:20px;text-align:center;font-size:12px;color:#666;">
            © 2025 <?= htmlspecialchars($footerName) ?> | 58 BlockCity 版权所有
        </div>
    </div>
</footer>

<?php
if (!empty($site_config['footer_extra'])) {
    echo $site_config['footer_extra'];
}
?>

<script>
// 城市定位：通过IP获取用户所在城市
(function(){
    var cityEl = document.getElementById('userCity');
    if (!cityEl) return;
    
    // 尝试从sessionStorage读取缓存的定位结果
    var cached = sessionStorage.getItem('userCityName');
    if (cached) { cityEl.textContent = cached; return; }
    
    // 调用免费IP定位API
    fetch('https://api.ip.sb/geoip', {mode:'cors'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            var city = d.city || d.region || '';
            if (city) {
                cityEl.textContent = city;
                sessionStorage.setItem('userCityName', city);
            }
        })
        .catch(function(){
            // 备选：使用备用API
            fetch('https://ipapi.co/json/', {mode:'cors'})
                .then(function(r){ return r.json(); })
                .then(function(d){
                    var city = d.city || d.region || '';
                    if (city) {
                        cityEl.textContent = city;
                        sessionStorage.setItem('userCityName', city);
                    }
                })
                .catch(function(){});
        });
})();
</script>

</body>
</html>
