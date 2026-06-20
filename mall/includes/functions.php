<?php
/**
 * 商城公共函数库
 */

if (!function_exists('normalizeImageUrl')) {
    /**
     * 统一处理商品图片路径：相对路径补 ../，绝对路径/完整 URL 保持原样
     */
    function normalizeImageUrl($imageUrl) {
        if (empty($imageUrl)) {
            return '/assets/images/default-product.jpg';
        }
        $imageUrl = trim($imageUrl);
        if (preg_match('#^(https?:)?//#i', $imageUrl) || substr($imageUrl, 0, 1) === '/') {
            return $imageUrl;
        }
        return '/' . ltrim($imageUrl, '/');
    }
}
