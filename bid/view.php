<?php
/**
 * 58拍卖 · 拍品详情（竞价台）
 */
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/includes/lot_helpers.php';

$auction  = new Auction($pdo);
$userId   = intval($_SESSION['user_id'] ?? 0);

$auctionId = intval($_GET['id'] ?? 0);
if ($auctionId <= 0) {
    http_response_code(404);
    include '../404.php';
    exit;
}

// 惰性推进状态机：激活到点的 pending 并结算到点的 active
$auction->tick();

$bidMsg = '';
$bidErr = '';

// 无 JS 降级：表单直投
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_bid'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $bidErr = '会话已过期，请刷新页面后重试';
    } else {
        $r = $auction->placeBid($auctionId, $userId, floatval($_POST['amount'] ?? 0));
        if (!empty($r['ok'])) $bidMsg = $r['msg'];
        else $bidErr = $r['msg'];
    }
}

// 卖家取消拍卖
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_cancel'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $bidErr = 'CSRF令牌验证失败';
    } else {
        $r = $auction->cancelAuction($auctionId, $userId);
        if (!empty($r['ok'])) $bidMsg = $r['msg'];
        else $bidErr = $r['msg'];
    }
}

$a = $auction->getAuctionById($auctionId);
if (!$a) {
    http_response_code(404);
    include '../404.php';
    exit;
}

$snap      = $auction->getAuctionSnapshot($auctionId, $userId);
$bids      = $auction->getBids($auctionId, 30, 'time');
$symbol    = ac_currency_symbol($a['currency']);
$isSeller  = intval($a['seller_id']) === $userId;
$isCurrent = intval($a['current_bidder_id'] ?? 0) === $userId;
$myState   = $snap['my_state'] ?? 'none';
$isWatching = $myState === 'watching' || $auction->isWatching($auctionId, $userId);
$minBid    = $snap['next_min'];
$delta     = ac_price_delta($a);
$img       = ac_auction_img($a);
$isActive  = $a['status'] === 'active';
$isOver    = in_array($a['status'], ['sold', 'ended', 'canceled'], true);
$reserve   = $a['reserve_price'] !== null ? floatval($a['reserve_price']) : null;
$reserveRatio = $reserve ? min(100, round(floatval($a['current_price'] ?? $a['start_price']) / $reserve * 100)) : 0;

// 子站详情链接
$detailUrl = '';
if ($a['item_type'] === 'nft' && !empty($a['nft_id'])) {
    $detailUrl = 'https://nft.58.tl/nft/view.php?id=' . intval($a['nft_id']);
} elseif ($a['item_type'] === 'block' && !empty($a['block_id'])) {
    $detailUrl = 'https://block.58.tl/block/view.php?id=' . intval($a['block_id']);
}

$extendWindow  = intval($a['extend_window_seconds'] ?? 0) ?: 120;
$extendStep    = intval($a['auto_extend_seconds'] ?? 0) ?: 120;
$maxExtend     = intval($a['max_extend_times'] ?? 0) ?: 10;

$site_config['title'] = ($a['item_title'] ?? '拍卖详情') . ' - ' . ac_lot_no($a['id']) . ' | 58拍卖';
$site_config['description'] = '正在拍卖：' . ($a['item_title'] ?? '') . '，当前价 ' . $symbol . number_format(floatval($a['current_price'] ?? $a['start_price']), 2) . '，价高者得。';
require_once 'includes/header.php';
?>

<div class="ac-wrap">

    <div style="display:flex;align-items:center;gap:12px;margin:6px 0 16px;flex-wrap:wrap;">
        <a class="ac-muted" style="font-size:13px;" href="index.php"><i class="fas fa-chevron-left"></i> 竞价大厅</a>
        <span class="ac-lotno"><?= ac_lot_no($a['id']) ?></span>
        <?php ac_status_badge($a); ?>
        <?php if (!empty($a['extend_count'])): ?>
            <span class="ac-badge ac-badge-hot">已顺延 <?= intval($a['extend_count']) ?> 次</span>
        <?php endif; ?>
    </div>

    <div class="ac-lot">
        <!-- 陈列 -->
        <div>
            <div class="ac-stage">
                <div class="ac-stage-media">
                    <?php if ($detailUrl): ?>
                        <a href="<?= htmlspecialchars($detailUrl) ?>" target="_blank" rel="noopener" style="display:block;width:100%;height:100%;">
                    <?php endif; ?>
                    <?php if ($img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($a['item_title'] ?? '') ?>">
                    <?php else: ?>
                        <span class="ph"><i class="fas fa-image"></i></span>
                    <?php endif; ?>
                    <?php if ($detailUrl): ?></a><?php endif; ?>
                </div>
                <div class="ac-stage-meta">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <div style="min-width:0;">
                            <h1 style="font-size:20px;font-weight:800;margin:0 0 6px;"><?= htmlspecialchars($a['item_title'] ?? ('拍品 #' . $a['id'])) ?></h1>
                            <div class="ac-muted" style="font-size:12px;">
                                <?= $a['item_type'] === 'nft' ? 'NFT 头像' : '区块' ?> ·
                                卖家 <?= htmlspecialchars($a['seller_name'] ?? ('用户#' . $a['seller_id'])) ?>
                            </div>
                        </div>
                        <?php if ($detailUrl): ?>
                            <a class="ac-btn ac-btn-ghost" style="font-size:13px;padding:8px 14px;" href="<?= htmlspecialchars($detailUrl) ?>" target="_blank" rel="noopener">
                                <i class="fas fa-external-link-alt"></i> 查看<?= $a['item_type'] === 'nft' ? '头像' : '区块' ?>详情
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 竞拍数据 -->
            <div class="ac-stats" style="margin-top:18px;border:1px solid var(--line);border-radius:14px;background:var(--surface);padding:14px 18px;">
                <div class="ac-stat"><b data-live="bid_count"><?= intval($snap['bid_count']) ?></b><span>出价次数</span></div>
                <div class="ac-stat"><b data-live="bidder_count"><?= intval($snap['bidder_count']) ?></b><span>竞拍人数</span></div>
                <div class="ac-stat"><b data-live="watch_count"><?= intval($snap['watch_count']) ?></b><span>关注</span></div>
                <div class="ac-stat"><b class="ac-num"><?= number_format(floatval($a['bid_increment']), 0) ?></b><span>加价幅度</span></div>
            </div>
        </div>

        <!-- 竞价台 -->
        <div class="ac-panel">
            <div class="ac-panel-row">
                <span class="ac-eyebrow"><?= $isOver ? '落槌结果' : ($a['status'] === 'pending' ? '距开拍' : '距落槌') ?></span>
                <?php ac_render_countdown($a, '距落槌', 'acCountdown'); ?>
            </div>

            <div class="ac-panel-price">
                <div class="ac-panel-row" style="align-items:flex-end;">
                    <div>
                        <div class="ac-eyebrow"><?= $isOver ? '成交价' : '当前价' ?></div>
                        <div class="ac-price ac-price-lg">
                            <span class="ac-price-unit"><?= $symbol ?></span><span data-live="price"><?= number_format(floatval($a['current_price'] ?? $a['start_price']), 2) ?></span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <?php if ($delta !== null): ?><div class="ac-delta ac-delta-up"><?= number_format($delta, 1) ?>%</div><?php endif; ?>
                        <div class="ac-muted" style="font-size:11px;">起拍 <?= ac_money($a['start_price'], $a['currency']) ?></div>
                    </div>
                </div>
            </div>

            <?php if ($reserve !== null): ?>
            <div id="acReserve">
                <div class="ac-panel-row">
                    <span class="ac-panel-label">底价</span>
                    <span class="ac-muted" style="font-size:12px;" data-reserve-text>
                        <?= floatval($a['current_price'] ?? $a['start_price']) >= $reserve ? '已过底价 · 保证成交' : '底价未达（' . $reserveRatio . '%）' ?>
                    </span>
                </div>
                <div class="ac-reserve-track">
                    <div class="ac-reserve-bar <?= floatval($a['current_price'] ?? $a['start_price']) >= $reserve ? 'passed' : '' ?>" style="width:<?= $reserveRatio ?>%;"></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isActive): ?>
                <div class="ac-verdict ac-verdict-<?= $myState === 'leading' ? 'lead' : ($myState === 'outbid' ? 'outbid' : 'none') ?>" id="acVerdict" data-state="<?= htmlspecialchars($myState) ?>">
                    <?php if ($myState === 'leading'): ?><i class="fas fa-crown"></i> 你正领先
                    <?php elseif ($myState === 'outbid'): ?><i class="fas fa-triangle-exclamation"></i> 你已被超越，快夺回领先
                    <?php elseif ($myState === 'watching'): ?><i class="fas fa-star"></i> 你已关注这场拍卖
                    <?php else: ?><i class="fas fa-eye"></i> 你尚未参与这口竞价<?php endif; ?>
                </div>

                <div class="ac-panel-row">
                    <span class="ac-panel-label">下一口价</span>
                    <span class="ac-price" style="font-size:18px;"><span class="ac-price-unit"><?= $symbol ?></span><span data-live="next_min"><?= number_format($minBid, 2) ?></span></span>
                </div>

                <?php if ($userId && !$isSeller): ?>
                <div class="ac-quick">
                    <button type="button" class="ac-btn ac-btn-ghost" data-quick="1">+1 档</button>
                    <button type="button" class="ac-btn ac-btn-ghost" data-quick="3">+3 档</button>
                    <button type="button" class="ac-btn ac-btn-ghost" data-quick="5">+5 档</button>
                </div>
                <form class="ac-bid-form" id="acBidForm" method="POST"
                      data-price="<?= floatval($a['current_price'] ?? $a['start_price']) ?>"
                      data-increment="<?= floatval($a['bid_increment']) ?>"
                      data-next-min="<?= floatval($minBid) ?>"
                      data-start-price="<?= floatval($a['start_price']) ?>">
                    <input type="hidden" name="action_bid" value="1">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input class="ac-input" type="number" name="amount" step="0.01" min="<?= $minBid ?>" value="<?= $minBid ?>" required>
                    <button type="submit" class="ac-btn ac-btn-primary"><i class="fas fa-gavel"></i> 出价</button>
                </form>
                <div class="ac-hint">
                    出价即代表接受拍卖规则。最后 <?= intval($extendWindow / 60) ?> 分钟内出价将自动顺延 <?= intval($extendStep / 60) ?> 分钟（最多 <?= $maxExtend ?> 次）。
                </div>
                <?php elseif (!$userId): ?>
                    <a class="ac-btn ac-btn-primary ac-btn-block" href="auth/login.php?redirect=<?= urlencode('view.php?id=' . $auctionId) ?>">
                        <i class="fas fa-sign-in-alt"></i> 登录后出价
                    </a>
                <?php elseif ($isSeller): ?>
                    <div class="ac-hint">您是卖家，不能出价自己的拍卖品。</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="ac-verdict ac-verdict-none">
                    <?php if ($a['status'] === 'sold'): ?>
                        <i class="fas fa-gavel"></i> 已落槌成交 <?= ac_money($a['final_price'] ?? $a['current_price'], $a['currency']) ?>
                    <?php elseif ($a['status'] === 'pending'): ?>
                        <i class="fas fa-clock"></i> 拍卖尚未开始，收藏后等待开拍
                    <?php elseif ($a['status'] === 'canceled'): ?>
                        <i class="fas fa-ban"></i> 该拍卖已被卖家取消
                    <?php else: ?>
                        <i class="fas fa-info-circle"></i> 已流拍（未达成交条件）
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;gap:8px;">
                <button type="button" class="ac-btn ac-btn-ghost ac-btn-block" id="acWatchBtn" data-watching="<?= $isWatching ? '1' : '0' ?>">
                    <i class="<?= $isWatching ? 'fas' : 'far' ?> fa-star"></i> <?= $isWatching ? '已关注' : '关注' ?>
                </button>
            </div>

            <?php if ($bidMsg): ?><div class="ac-alert ac-alert-ok"><?= htmlspecialchars($bidMsg) ?></div><?php endif; ?>
            <?php if ($bidErr): ?><div class="ac-alert ac-alert-err"><?= htmlspecialchars($bidErr) ?></div><?php endif; ?>

            <?php if ($isSeller): ?>
            <div style="border-top:1px dashed var(--line);padding-top:12px;">
                <?php if ($a['status'] === 'pending'): ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a class="ac-btn ac-btn-ghost" href="create.php?edit=<?= $auctionId ?>"><i class="fas fa-pen"></i> 编辑拍卖</a>
                        <form method="POST" onsubmit="return confirm('确定取消该拍卖吗？取消后物品将解除锁定。');" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="action_cancel" value="1">
                            <button type="submit" class="ac-btn ac-btn-ghost" style="color:var(--live);border-color:rgba(255,77,61,.4);"><i class="fas fa-trash"></i> 取消拍卖</button>
                        </form>
                    </div>
                <?php elseif ($a['status'] === 'active' && empty($a['current_bidder_id'])): ?>
                    <form method="POST" onsubmit="return confirm('确定取消该拍卖吗？取消后物品将解除锁定。');" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action_cancel" value="1">
                        <button type="submit" class="ac-btn ac-btn-ghost" style="color:var(--live);border-color:rgba(255,77,61,.4);"><i class="fas fa-trash"></i> 取消拍卖</button>
                    </form>
                <?php elseif ($a['status'] === 'active'): ?>
                    <div class="ac-hint"><i class="fas fa-info-circle"></i> 拍卖进行中已有人出价，如需处理请联系管理员。</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 实时叫价 -->
    <div class="ac-section" style="margin-top:22px;">
        <div class="ac-rail-head">
            <h3 style="margin:0;"><i class="fas fa-scroll" style="color:var(--brand);"></i> 实时叫价
                <span class="ac-muted" style="font-weight:500;font-size:12px;">
                    <span data-live="bid_count"><?= intval($snap['bid_count']) ?></span> 次 ·
                    <span data-live="bidder_count"><?= intval($snap['bidder_count']) ?></span> 人竞拍
                </span>
            </h3>
            <span class="ac-muted" style="font-size:11px;">每 3 秒自动刷新</span>
        </div>
        <div class="ac-bids" id="acBids">
            <?php ac_render_bids($bids, $userId, $symbol); ?>
        </div>
    </div>

    <!-- 信息区 -->
    <div class="ac-sections">
        <div class="ac-section">
            <h3><i class="fas fa-cube" style="color:var(--brand);"></i> 拍品信息</h3>
            <ul style="padding-left:0;list-style:none;">
                <li>类型：<?= $a['item_type'] === 'nft' ? 'NFT 头像' : '区块' ?></li>
                <li>编号：<?= ac_lot_no($a['id']) ?></li>
                <li>起拍价：<?= ac_money($a['start_price'], $a['currency']) ?></li>
                <li>加价幅度：<?= ac_money($a['bid_increment'], $a['currency']) ?></li>
                <?php if ($reserve !== null): ?><li>底价：<?= ac_money($reserve, $a['currency']) ?>（未达底价则不成交）</li><?php endif; ?>
                <li>开拍：<?= date('Y-m-d H:i', strtotime($a['start_time'])) ?></li>
                <li>落槌：<?= date('Y-m-d H:i', strtotime($a['end_time'])) ?></li>
            </ul>
        </div>

        <div class="ac-section">
            <h3><i class="fas fa-user" style="color:var(--brand);"></i> 卖家</h3>
            <p style="margin:0 0 6px;"><?= htmlspecialchars($a['seller_name'] ?? ('用户#' . $a['seller_id'])) ?></p>
            <p class="ac-muted" style="margin:0;font-size:12px;">成交后物品所有权将直接转移给得标者。</p>
        </div>

        <div class="ac-section">
            <h3><i class="fas fa-gavel" style="color:var(--brand);"></i> 拍卖规则</h3>
            <ul>
                <li>出价需 ≥ 下一口价，价高者得。</li>
                <li>最后 <?= intval($extendWindow / 60) ?> 分钟内出价将自动顺延 <?= intval($extendStep / 60) ?> 分钟，最多顺延 <?= $maxExtend ?> 次，杜绝最后时刻狙击。</li>
                <li><?= $reserve !== null ? '当前价需达到底价方可成交，未达底价将流拍。' : '无底价拍卖，最高出价者即成交。' ?></li>
                <li>落槌后系统自动转移物品所有权并通知买卖双方。</li>
            </ul>
        </div>
    </div>
</div>

<script>
window.AC_PAGE = {
    auctionId: <?= $auctionId ?>,
    myId: <?= $userId ?>,
    csrf: '<?= generateCsrfToken() ?>',
    poll: <?= $isActive ? 'true' : 'false' ?>,
    apiLot: '/api/lot.php',
    apiBid: '/api/bid.php',
    apiWatch: '/api/watch.php'
};
window.AC_SERVER_NOW = <?= time() ?>;
</script>
<?php require_once 'includes/footer.php'; ?>
