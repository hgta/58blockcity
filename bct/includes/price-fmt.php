<?php
/**
 * BCT 单价显示格式化：最多 3 位小数（去尾零），不足 2 位补齐。
 * 例：0.065 -> "0.065"，0.10 -> "0.10"，0.07 -> "0.07"，1 -> "1.00"
 */
if (!function_exists('bct_fmt_price')) {
    function bct_fmt_price($p) {
        $s = number_format((float)$p, 3, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        $dot = strpos($s, '.');
        if ($dot === false) {
            $s .= '.00';
        } elseif (strlen($s) - $dot - 1 === 1) {
            $s .= '0';
        }
        return $s;
    }
}
