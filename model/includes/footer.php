<?php
/**
 * 模特子站页脚：关闭亮调主题外壳
 */
if (!isset($site_config)) {
    $site_config = [];
}
$site_config['footer_name'] = $site_config['footer_name'] ?? '58 模特库 | 58 BlockCity';
?>
</div><!-- /.model-shell -->
<?php require_once APP_ROOT . '/shared/footer.php'; ?>
