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

// 子站详情链接（由 ac_item_cross_url 统一派生：product/block/nft）
$detailUrl = ac_item_cross_url($a);
$typeLabel = ac_item_type_label($a['item_type']);

// 分享信息（弹窗卡片 + 各渠道链接）
$shareUrl   = 'https://bid.58.tl/view.php?id=' . $auctionId;
$shareTitle = trim(($a['item_title'] ?? ('拍品 #' . $auctionId)) . ' ' . ac_lot_no($auctionId));
$shareDesc  = ac_seller_description_text($a, 100);
if ($shareDesc === '') {
    $shareDesc = '58拍卖 · ' . ($a['item_title'] ?? ('拍品 #' . $auctionId)) . '，秒级倒计时，价高者得。';
}

// 商品图集（≥2 张时启用轮播；空图集退化单图）
$galleryImgs = [];
if ($a['item_type'] === 'product' && !empty($a['item_images']) && is_array($a['item_images'])) {
    $galleryImgs = array_values(array_filter($a['item_images'], function ($u) { return is_string($u) && $u !== ''; }));
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
        <?php if ($a['item_type'] === 'product'): ?>
            <span class="ac-badge ac-badge-soft"><i class="fas fa-store"></i> 商品</span>
        <?php endif; ?>
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
                    <?php if (count($galleryImgs) >= 2): ?>
                    <!-- 商品图集轮播（≥2 张图时启用；无 JS 时显示第一张） -->
                    <div class="ac-stage-gallery" id="acGallery"
                         data-count="<?= count($galleryImgs) ?>"
                         data-cur="0"
                         data-imgs="<?= htmlspecialchars(json_encode($galleryImgs, JSON_UNESCAPED_SLASHES)) ?>">
                        <div class="ac-gal-main">
                            <img id="acGalMain" src="<?= htmlspecialchars($galleryImgs[0]) ?>" alt="<?= htmlspecialchars($a['item_title'] ?? '') ?>">
                            <button type="button" class="ac-gal-btn ac-gal-prev" aria-label="上一张" onclick="acGalNav(-1)"><i class="fas fa-chevron-left"></i></button>
                            <button type="button" class="ac-gal-btn ac-gal-next" aria-label="下一张" onclick="acGalNav(1)"><i class="fas fa-chevron-right"></i></button>
                            <span class="ac-gal-idx" id="acGalIdx">1 / <?= count($galleryImgs) ?></span>
                        </div>
                        <div class="ac-gal-thumbs">
                            <?php foreach ($galleryImgs as $i => $g): ?>
                            <img src="<?= htmlspecialchars($g) ?>" data-idx="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>"
                                 onclick="acGalGo(<?= $i ?>)" loading="lazy" alt="<?= htmlspecialchars(($a['item_title'] ?? '') . ' 图' . ($i + 1)) ?>">
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php if ($detailUrl): ?>
                        <a href="<?= htmlspecialchars($detailUrl) ?>" target="_blank" rel="noopener" style="display:block;width:100%;height:100%;">
                    <?php endif; ?>
                    <?php if ($img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($a['item_title'] ?? '') ?>">
                    <?php else: ?>
                        <span class="ph"><i class="fas fa-image"></i></span>
                    <?php endif; ?>
                    <?php if ($detailUrl): ?></a><?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="ac-stage-meta">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <div style="min-width:0;">
                            <h1 style="font-size:20px;font-weight:800;margin:0 0 6px;"><?= htmlspecialchars($a['item_title'] ?? ('拍品 #' . $a['id'])) ?></h1>
                            <div class="ac-muted" style="font-size:12px;">
                                <?= $typeLabel ?> ·
                                卖家 <?= htmlspecialchars($a['seller_name'] ?? ('用户#' . $a['seller_id'])) ?>
                            </div>
                        </div>
                        <?php if ($detailUrl): ?>
                            <a class="ac-btn ac-btn-ghost" style="font-size:13px;padding:8px 14px;" href="<?= htmlspecialchars($detailUrl) ?>" target="_blank" rel="noopener">
                                <i class="fas fa-external-link-alt"></i> <?= $a['item_type'] === 'product' ? '查看商品详情' : ('查看' . ($a['item_type'] === 'nft' ? '头像' : '区块') . '详情') ?>
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
                <div class="ac-hint" data-help-hint="bid-guide">
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
                <button type="button" class="ac-btn ac-btn-ghost ac-btn-block" onclick="openShareModal()" aria-label="分享拍品" title="分享拍品">
                    <i class="fas fa-share-alt"></i> 分享
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
        <?php if ($a['item_type'] === 'product'): ?>
        <div class="ac-section">
            <h3><i class="fas fa-store" style="color:var(--brand);"></i> 商品信息</h3>
            <ul style="padding-left:0;list-style:none;">
                <li>商品原价：Ⓟ <?= number_format(floatval($a['item_price_bct'] ?? 0), 0) ?> / ¥ <?= number_format(floatval($a['item_price_cny'] ?? 0), 2) ?></li>
                <?php if (!empty($a['item_shop_id'])): ?>
                <li>店铺：<a href="https://mall.58.tl/shop/view.php?id=<?= intval($a['item_shop_id']) ?>" target="_blank" rel="noopener" style="color:var(--brand);"><?= htmlspecialchars($a['item_shop_name'] ?: ('店铺#' . $a['item_shop_id'])) ?></a></li>
                <?php endif; ?>
                <li>成交后自动生成商城订单，付款 / 发货在商城完成。</li>
            </ul>
            <?php if ($detailUrl): ?>
            <a class="ac-btn ac-btn-primary" style="width:100%;box-sizing:border-box;" href="<?= htmlspecialchars($detailUrl) ?>" target="_blank" rel="noopener">
                <i class="fas fa-external-link-alt"></i> 查看商品详情 →
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="ac-section">
            <h3><i class="fas fa-cube" style="color:var(--brand);"></i> 拍品信息</h3>
            <ul style="padding-left:0;list-style:none;">
                <li>类型：<?= $typeLabel ?></li>
                <li>编号：<?= ac_lot_no($a['id']) ?></li>
                <li>起拍价：<?= ac_money($a['start_price'], $a['currency']) ?></li>
                <li>加价幅度：<?= ac_money($a['bid_increment'], $a['currency']) ?></li>
                <?php if ($reserve !== null): ?><li>底价：<?= ac_money($reserve, $a['currency']) ?>（未达底价则不成交）</li><?php endif; ?>
                <li>开拍：<?= date('Y-m-d H:i', strtotime($a['start_time'])) ?></li>
                <li>落槌：<?= date('Y-m-d H:i', strtotime($a['end_time'])) ?></li>
            </ul>
        </div>

        <?php if (!empty(trim((string)($a['description'] ?? '')))): ?>
        <div class="ac-section">
            <h3>
                <i class="fas fa-align-left" style="color:var(--brand);"></i> 卖家描述
                <a data-stub="report" href="#" onclick="return false;" style="font-size:12px;color:var(--muted);margin-left:auto;font-weight:400;">举报</a>
            </h3>
            <?= ac_render_seller_description($a) ?>
        </div>
        <?php endif; ?>

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
    poll: <?= ($isActive || $a['status'] === 'pending') ? 'true' : 'false' ?>,
    initialStatus: '<?= $a['status'] ?>',
    apiLot: '/api/lot.php',
    apiBid: '/api/bid.php',
    apiWatch: '/api/watch.php'
};
window.AC_SERVER_NOW = <?= time() ?>;
</script>
<script>
// --- 商品图集轮播（无图集时本段不执行） ---
(function () {
    var gal = document.getElementById('acGallery');
    if (!gal) return;
    var imgs = [];
    try { imgs = JSON.parse(gal.getAttribute('data-imgs') || '[]'); } catch (e) { return; }
    if (imgs.length < 2) return;

    var main = document.getElementById('acGalMain');
    var idxEl = document.getElementById('acGalIdx');
    var thumbs = gal.querySelectorAll('.ac-gal-thumbs img');
    var cur = 0;

    function render() {
        main.src = imgs[cur];
        if (idxEl) idxEl.textContent = (cur + 1) + ' / ' + imgs.length;
        thumbs.forEach(function (t) {
            t.classList.toggle('active', parseInt(t.getAttribute('data-idx'), 10) === cur);
        });
    }
    window.acGalGo = function (i) {
        cur = (i + imgs.length) % imgs.length;
        render();
    };
    window.acGalNav = function (d) {
        acGalGo(cur + d);
    };
    // 键盘左右键切换
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') acGalNav(-1);
        if (e.key === 'ArrowRight') acGalNav(1);
    });
    // 移动端滑动手势
    var startX = null;
    main.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    main.addEventListener('touchend', function (e) {
        if (startX === null) return;
        var dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) acGalNav(dx < 0 ? 1 : -1);
        startX = null;
    }, { passive: true });
})();
</script>

<!-- ===== 分享弹窗（与 mall 商品详情同款交互） ===== -->
<div class="ac-share-backdrop" id="shareModal" onclick="if(event.target === this) closeShareModal()" aria-hidden="true">
    <div class="ac-share-modal" role="dialog" aria-modal="true" aria-labelledby="acShareTitle">
        <div class="ac-share-header">
            <div class="ac-share-title" id="acShareTitle"><i class="fas fa-share-alt"></i> 分享拍品</div>
            <button type="button" class="ac-share-close" onclick="closeShareModal()" aria-label="关闭"><i class="fas fa-times"></i></button>
        </div>

        <div class="ac-share-card">
            <?php if ($img): ?>
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($a['item_title'] ?? '') ?>">
            <?php else: ?>
            <span class="ac-share-card-ph"><i class="fas fa-image"></i></span>
            <?php endif; ?>
            <div class="ac-share-card-info">
                <div class="ac-share-card-name"><?= htmlspecialchars($a['item_title'] ?? ('拍品 #' . $auctionId)) ?></div>
                <div class="ac-share-card-price">
                    <?= $isOver && $a['status'] === 'sold'
                        ? '成交 ' . ac_money($a['final_price'] ?? $a['current_price'], $a['currency'])
                        : '当前价 ' . ac_money($a['current_price'] ?? $a['start_price'], $a['currency']) ?>
                </div>
            </div>
        </div>

        <!-- 二维码：服务端端点生成，扫码直达拍品页 -->
        <div class="ac-share-qrcode">
            <img class="ac-share-qr-img" id="shareQrImg"
                 src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><rect width='160' height='160' fill='%23f1f5f9'/><text x='50%25' y='50%25' font-size='12' fill='%2394a3b8' text-anchor='middle' dominant-baseline='middle'>加载中…</text></svg>"
                 alt="拍品分享二维码">
            <div class="ac-share-qr-tip"><i class="fab fa-weixin"></i> 微信扫一扫，分享给好友</div>
        </div>

        <!-- 渠道按钮 -->
        <div class="ac-share-channels">
            <button type="button" class="ac-share-channel" onclick="copyShareLink(this)">
                <span class="ac-share-icon copy"><i class="fas fa-copy"></i></span>
                <span>复制链接</span>
            </button>
            <button type="button" class="ac-share-channel" onclick="shareToWechat(this)" title="微信内可直接发送给朋友，外部请用上方二维码扫码">
                <span class="ac-share-icon wechat"><i class="fab fa-weixin"></i></span>
                <span>微信好友</span>
            </button>
            <a class="ac-share-channel" target="_blank" rel="noopener"
               href="https://service.weibo.com/share/share.php?url=<?= urlencode($shareUrl) ?>&title=<?= urlencode($shareTitle . ' ' . $shareDesc) ?>">
                <span class="ac-share-icon weibo"><i class="fab fa-weibo"></i></span>
                <span>微博</span>
            </a>
            <a class="ac-share-channel" target="_blank" rel="noopener"
               href="https://connect.qq.com/widget/shareqq/index.html?url=<?= urlencode($shareUrl) ?>&title=<?= urlencode($shareTitle) ?>&desc=<?= urlencode($shareDesc) ?>&site=58拍卖">
                <span class="ac-share-icon qq"><i class="fab fa-qq"></i></span>
                <span>QQ</span>
            </a>
            <a class="ac-share-channel" target="_blank" rel="noopener"
               href="https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=<?= urlencode($shareUrl) ?>&title=<?= urlencode($shareTitle) ?>&desc=<?= urlencode($shareDesc) ?>&site=58拍卖">
                <span class="ac-share-icon qzone"><i class="fas fa-star"></i></span>
                <span>QQ空间</span>
            </a>
            <a class="ac-share-channel" target="_blank" rel="noopener"
               href="https://www.douban.com/share/service?href=<?= urlencode($shareUrl) ?>&name=<?= urlencode($shareTitle) ?>&text=<?= urlencode($shareDesc) ?>">
                <span class="ac-share-icon douban"><i class="fas fa-book"></i></span>
                <span>豆瓣</span>
            </a>
            <button type="button" class="ac-share-channel" id="shareNative" onclick="nativeShare()" style="display:none;">
                <span class="ac-share-icon more"><i class="fas fa-ellipsis-h"></i></span>
                <span>更多</span>
            </button>
            <button type="button" class="ac-share-channel" id="shareNative2" onclick="nativeShare()" style="display:none;">
                <span class="ac-share-icon link"><i class="fas fa-share-square"></i></span>
                <span>系统分享</span>
            </button>
        </div>

        <!-- 链接行 -->
        <div class="ac-share-linkrow">
            <input type="text" class="ac-share-linkinput" id="shareLinkInput" value="<?= htmlspecialchars($shareUrl) ?>" readonly>
            <button type="button" class="ac-share-linkcopy" id="shareLinkCopyBtn" onclick="copyShareLinkFromInput(this)">复制</button>
        </div>
    </div>
</div>

<script>
// ===== 分享功能（与 mall 商品详情页同款降级策略） =====
var SHARE_URL       = <?= json_encode($shareUrl) ?>;
var SHARE_TITLE     = <?= json_encode($shareTitle) ?>;
var SHARE_DESC      = <?= json_encode($shareDesc) ?>;
var SHARE_HAS_NATIVE = (typeof navigator !== 'undefined' && !!navigator.share);

function openShareModal() {
    var modal = document.getElementById('shareModal');
    if (!modal) return;

    // 二维码按需懒加载（服务端 PNG 端点）
    var qrImg = document.getElementById('shareQrImg');
    if (qrImg && qrImg.dataset.loaded !== '1') {
        qrImg.src = '/api/qrcode.php?size=200&url=' + encodeURIComponent(SHARE_URL);
        qrImg.dataset.loaded = '1';
    }

    // 渠道 href 已服务端渲染；JS 仅负责显隐原生分享按钮
    document.getElementById('shareNative').style.display  = SHARE_HAS_NATIVE ? 'flex' : 'none';
    document.getElementById('shareNative2').style.display = SHARE_HAS_NATIVE ? 'flex' : 'none';

    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    // 自动选中链接方便复制
    setTimeout(function () {
        var inp = document.getElementById('shareLinkInput');
        if (inp) { inp.focus(); inp.setSelectionRange(0, inp.value.length); }
    }, 50);
}

function closeShareModal() {
    var modal = document.getElementById('shareModal');
    if (!modal) return;
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

// ESC 关闭（图集轮播已占用左右键，仅 ESC）
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeShareModal();
});

// 分享给微信好友：
// - 微信内置浏览器：WeixinJSBridge 唤起「发送给朋友」
// - 移动端支持原生分享：调系统面板（含微信/QQ 候选）
// - 桌面端：聚焦二维码 + 复制链接提示扫码
function shareToWechat(btn) {
    try {
        if (typeof WeixinJSBridge !== 'undefined' && WeixinJSBridge && WeixinJSBridge.invoke) {
            WeixinJSBridge.invoke('sendAppMessage', {
                'appid': '', 'img_url': '', 'img_width': '200', 'img_height': '200',
                'link': SHARE_URL, 'desc': SHARE_DESC, 'title': SHARE_TITLE
            }, function (res) { /* noop */ });
            return;
        }
    } catch (e) { /* 忽略，继续降级 */ }

    if (SHARE_HAS_NATIVE && /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent)) {
        nativeShare();
        return;
    }

    var qrArea = document.querySelector('.ac-share-qrcode');
    var tip    = document.querySelector('.ac-share-qr-tip');
    if (qrArea) {
        qrArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
        qrArea.style.transition = 'box-shadow .25s';
        qrArea.style.boxShadow = '0 0 0 3px #07c160, 0 0 18px rgba(7,193,96,.35)';
        setTimeout(function () { qrArea.style.boxShadow = ''; }, 2200);
    }
    if (tip) {
        tip.innerHTML = '<i class="fab fa-weixin"></i> 请用微信扫一扫上方二维码，分享给好友';
    }
    shareCopyText(SHARE_URL, btn);
}

// 通用复制：clipboard API + execCommand 退化
function shareCopyText(text, btnEl) {
    var onOk = function () {
            showShareToast('链接已复制，快去分享吧～');
            if (btnEl) {
                var orig = btnEl.dataset.origText || btnEl.textContent;
                btnEl.dataset.origText = orig;
                btnEl.textContent = '已复制';
                if (btnEl.classList) btnEl.classList.add('copied');
                setTimeout(function () {
                    btnEl.textContent = orig;
                    if (btnEl.classList) btnEl.classList.remove('copied');
                }, 1800);
            }
        },
        onFail = function () {
            var tmp = document.createElement('input');
            tmp.value = text;
            tmp.style.cssText = 'position:fixed;top:-9999px;left:-9999px;';
            document.body.appendChild(tmp);
            tmp.select();
            try {
                document.execCommand('copy');
                onOk();
            } catch (e) {
                showShareToast('复制失败，请手动复制', true);
            }
            document.body.removeChild(tmp);
        };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(onOk, onFail);
    } else {
        onFail();
    }
}

function copyShareLink(btn) { shareCopyText(SHARE_URL, btn); }
function copyShareLinkFromInput(btn) {
    var inp = document.getElementById('shareLinkInput');
    shareCopyText(inp ? inp.value : SHARE_URL, btn);
}

// Web Share API（移动端原生分享面板）
function nativeShare() {
    if (!navigator.share) {
        showShareToast('当前环境不支持系统分享');
        return;
    }
    navigator.share({ title: SHARE_TITLE, text: SHARE_DESC, url: SHARE_URL })
        .catch(function (err) {
            if (err && err.name !== 'AbortError') console.warn('分享失败:', err);
        });
}

// 简易 toast
function showShareToast(msg, isError) {
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;top:24px;left:50%;transform:translateX(-50%);z-index:99999;padding:10px 18px;border-radius:24px;color:#fff;font-size:14px;box-shadow:0 4px 16px rgba(0,0,0,.2);' +
        (isError ? 'background:#ef4444;' : 'background:#10b981;');
    document.body.appendChild(t);
    setTimeout(function () {
        t.style.transition = 'opacity .3s, top .3s';
        t.style.opacity = '0';
        t.style.top = '12px';
        setTimeout(function () { t.remove(); }, 320);
    }, 1600);
}
</script>
<?php require_once 'includes/footer.php'; ?>
