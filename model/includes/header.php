<?php
/**
 * 模特子站页头：复用全站共享 header，注入子站亮调主题
 * 使用前请确保已 require includes/bootstrap.php 且 $site_config 已就绪
 */
if (!isset($site_config)) {
    die('缺少 $site_config，请先 require includes/bootstrap.php');
}

$site_config['extra_head'] = ($site_config['extra_head'] ?? '')
    . '<link rel="stylesheet" href="' . htmlspecialchars(model_asset('assets/css/model.css')) . '">'
    . '<meta name="theme-color" content="#E8467C">';

// 子站外壳类名（供 model.css 作用域生效）
$__shellBodyClass = trim(($site_config['body_class'] ?? '') . ' model-shell-body');
$site_config['body_class'] = $__shellBodyClass;

require_once APP_ROOT . '/shared/header.php';
?>
<div class="model-shell model-shell-body">
