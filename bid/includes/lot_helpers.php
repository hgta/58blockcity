<?php
/**
 * 拍卖展示辅助函数（拍卖站共用）
 */

if (!function_exists('ac_currency_symbol')) {
    /** 货币符号：人气值 Ⓟ / 人民币 ¥ */
    function ac_currency_symbol($currency) {
        return $currency === 'popularity' ? 'Ⓟ ' : '¥ ';
    }
}

if (!function_exists('ac_money')) {
    /** 金额展示（含符号，千分位） */
    function ac_money($amount, $currency = 'cny') {
        return ac_currency_symbol($currency) . number_format(floatval($amount), 2);
    }
}

if (!function_exists('ac_lot_no')) {
    /** 拍卖行式拍品编号 */
    function ac_lot_no($id) {
        return 'LOT ' . str_pad(intval($id), 3, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('ac_img')) {
    /** 图片地址归一化（支持站内相对路径与跨子站绝对路径） */
    function ac_img($url) {
        if (empty($url)) return '';
        if (preg_match('#^https?://#', $url)) return $url;
        return '/' . ltrim($url, '/');
    }
}

if (!function_exists('ac_thumb')) {
    /** 拍品缩略图（无图时返回空串，由调用方渲染占位） */
    function ac_thumb($auction) {
        return ac_img($auction['item_image'] ?? '');
    }
}

if (!function_exists('ac_cd_class')) {
    /** 倒计时初始紧迫分级（前端会按秒刷新，这里避免首屏闪烁） */
    function ac_cd_class($auction) {
        if ($auction['status'] !== 'active') return 'is-over';
        $left = strtotime($auction['end_time']) - time();
        if ($left <= 0) return 'is-over';
        if ($left <= 300) return 'is-urgent';
        if ($left <= 3600) return 'is-warn';
        return '';
    }
}

if (!function_exists('ac_render_countdown')) {
    /**
     * 输出倒计时结构（前端 auction.js 逐秒刷新）
     * @param array  $auction 拍卖记录（需含 status/end_time/start_time）
     * @param string $label   标签文案，如「距落槌」
     */
    function ac_render_countdown($auction, $label = '距落槌', $id = '') {
        $isPending = $auction['status'] === 'pending';
        $isOver    = in_array($auction['status'], ['sold', 'ended', 'canceled'], true);
        $target    = $isPending ? $auction['start_time'] : $auction['end_time'];
        $ts        = strtotime($target);
        $cls       = ac_cd_class($auction);
        echo '<span class="ac-cd ' . $cls . '"' . ($id ? ' id="' . htmlspecialchars($id) . '"' : '') . ' data-end-ts="' . $ts . '"'
            . ($isPending ? ' data-pending="1"' : '')
            . ($isOver ? ' data-ended="1"' : '') . '>'
            . '<span class="ac-cd-label">' . htmlspecialchars($isOver ? '已结束' : ($isPending ? '距开拍' : $label)) . '</span>'
            . '<span data-cd-out>--:--:--</span>'
            . '</span>';
    }
}

if (!function_exists('ac_status_badge')) {
    /** 拍品状态角标 */
    function ac_status_badge($auction) {
        $left = strtotime($auction['end_time']) - time();
        if ($auction['status'] === 'sold') {
            echo '<span class="ac-badge ac-badge-sold"><i class="fas fa-gavel"></i> 已落槌</span>';
        } elseif ($auction['status'] === 'ended') {
            echo '<span class="ac-badge">已流拍</span>';
        } elseif ($auction['status'] === 'canceled') {
            echo '<span class="ac-badge">已取消</span>';
        } elseif ($auction['status'] === 'pending') {
            echo '<span class="ac-badge ac-badge-soft">即将开拍</span>';
        } elseif ($left <= 300) {
            echo '<span class="ac-badge ac-badge-live"><span class="ac-live-dot"></span> 即将结束</span>';
        } elseif (intval($auction['bid_count'] ?? 0) >= 5) {
            echo '<span class="ac-badge ac-badge-hot"><i class="fas fa-fire"></i> 热拍中</span>';
        } else {
            echo '<span class="ac-badge ac-badge-soft"><span class="ac-live-dot"></span> 竞拍中</span>';
        }
    }
}

if (!function_exists('ac_auction_img')) {
    /** 拍品图片（区块无图时回退默认占位图） */
    function ac_auction_img($auction) {
        $img = ac_thumb($auction);
        if ($img) return $img;
        if (($auction['item_type'] ?? '') === 'block') return '/assets/images/default-block.png';
        return '';
    }
}

if (!function_exists('ac_bidder_label')) {
    /** 竞拍者匿名标签（首字 + ***；本人显示「我」） */
    function ac_bidder_label($bid, $viewerId) {
        if ($viewerId && intval($bid['bidder_id']) === intval($viewerId)) return '我';
        $name = trim($bid['bidder_name'] ?? '');
        if ($name === '') return '匿名藏家';
        return mb_substr($name, 0, 1, 'UTF-8') . '***';
    }
}

if (!function_exists('ac_render_bids')) {
    /**
     * 输出叫价流（首屏服务端渲染，结构与 auction.js 动态渲染保持一致）
     */
    function ac_render_bids(array $bids, $viewerId, $symbol) {
        if (empty($bids)) {
            echo '<div class="ac-hint" style="padding:14px 4px;">等待第一口出价</div>';
            return;
        }
        foreach ($bids as $b) {
            $isMe = $viewerId && intval($b['bidder_id']) === intval($viewerId);
            $avatar = $b['bidder_avatar'] ?? '';
            echo '<div class="ac-bid-row' . ($isMe ? ' is-me' : '') . '">';
            echo '<div class="ac-bid-who">';
            echo '<img class="ac-bid-avatar" alt="" src="' . htmlspecialchars($avatar ?: 'https://58.tl/assets/images/default.jpg') . '">';
            echo '<span>' . htmlspecialchars(ac_bidder_label($b, $viewerId)) . '</span>';
            echo '</div>';
            echo '<div style="display:flex;align-items:center;gap:12px;">';
            echo '<span class="ac-bid-amount">' . htmlspecialchars($symbol . number_format(floatval($b['amount']), 2)) . '</span>';
            echo '<span class="ac-bid-time" data-ts="' . strtotime($b['created_at']) . '">—</span>';
            echo '</div>';
            echo '</div>';
        }
    }
}

if (!function_exists('ac_price_delta')) {
    /** 起拍价 → 当前价涨幅百分比（无出价时返回 null） */
    function ac_price_delta($auction) {
        $start = floatval($auction['start_price'] ?? 0);
        $now   = floatval($auction['current_price'] ?? $start);
        if ($start <= 0 || empty($auction['current_bidder_id'])) return null;
        return ($now - $start) / $start * 100;
    }
}
