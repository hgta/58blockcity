<?php
$site_config['footer_name'] = '58拍卖 | BlockCity 拍卖平台';
$site_config['footer_extra'] = ($site_config['footer_extra'] ?? '')
    . '<script src="/assets/js/auction.js?v=20260914"></script>';
?>
<!-- 主题切换（明亮 / 暗场）：随页脚渲染，所有 bid 页面可用 -->
<button id="acThemeToggle" class="ac-theme-toggle" type="button" data-ac-theme="dark"
        aria-label="切换明亮 / 暗场模式" title="切换明亮 / 暗场模式">
    <span class="ac-theme-ico" data-ico="light" aria-hidden="true">&#9728;</span>
    <span class="ac-theme-ico" data-ico="dark" aria-hidden="true">&#9790;</span>
    <span class="ac-theme-label">暗场</span>
</button>
<?php require_once __DIR__ . '/../../shared/footer.php';
