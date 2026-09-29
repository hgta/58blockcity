<?php
/**
 * 区块子站页脚：收编到全站统一页脚（shared/footer.php）
 * 原独立 .block-footer 模板内容与共享页脚重复，已废弃。
 * 页面容器为 .block-container（非 <main>），通过 footer_no_main 跳过 </main>。
 */
if (!isset($site_config)) {
    $site_config = [];
}
$site_config['footer_name']  = $site_config['footer_name'] ?? '58区块交易市场 | BlockCity DAO';
$site_config['footer_no_main'] = true;
?>
</div><!-- .block-container -->
<?php require_once __DIR__ . '/../../shared/footer.php';
