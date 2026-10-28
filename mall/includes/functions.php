<?php
/**
 * 商城公共函数库
 */

if (!function_exists('order_money')) {
    /**
     * 订单金额展示（含币种符号）
     * cny        → ¥ 1,234.56（两位小数）
     * popularity → Ⓟ 1,234（零位小数）
     *
     * 拍卖成交订单会继承拍卖的 currency（人民币拍卖落槌的金额是人民币），
     * 全站金额展示必须走这里，不能再硬编码「Ⓟ … 人气值」。
     */
    function order_money($amount, $currency = 'popularity') {
        return $currency === 'cny'
            ? '¥ ' . number_format(floatval($amount), 2)
            : 'Ⓟ ' . number_format(floatval($amount), 0);
    }
}

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
