<?php
/**
 * 58拍卖 · 竞价大厅（首页）
 */
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/includes/lot_helpers.php';

$auction  = new Auction($pdo);
$viewerId = intval($_SESSION['user_id'] ?? 0);

// 惰性推进状态机：激活到点的 pending 并结算到点的 active
$auction->tick();

$itemType = in_array($_GET['type'] ?? '', ['block', 'nft'], true) ? $_GET['type'] : '';
$currency = in_array($_GET['currency'] ?? '', ['popularity', 'cny'], true) ? $_GET['currency'] : '';
$tab      = in_array($_GET['tab'] ?? '', ['active', 'ended'], true) ? $_GET['tab'] : 'active';
$sort     = in_array($_GET['sort'] ?? '', ['hot', 'ending', 'new', 'price'], true) ? $_GET['sort'] : 'hot';
$page     = max(1, intval($_GET['page'] ?? 1));

/** 构造带当前筛选条件的链接 */
function ac_q(array $overrides = [], $base = 'index.php') {
    return $base . '?' . http_build_query(array_merge($_GET, $overrides));
}

$hero = null;
$rail = [];

if ($tab === 'ended') {
    $result   = $auction->getEndedAuctions($page, 12, $itemType, $currency);
    $list     = $result['list'];
    $total    = $result['total'];
    $pages    = $result['pages'];
} else {
    // 主推：热拍优先，取进行中的第一件
    $feed = $auction->getActiveAuctions(1, 12, $itemType, $currency, 'hot')['list'];
    foreach ($feed as $row) {
        if ($row['status'] === 'active') { $hero = $row; break; }
    }
    if (!$hero && !empty($feed)) $hero = $feed[0];

    // 即将结束滑轨（按剩余时间升序，仅进行中）
    $railPool = $auction->getActiveAuctions(1, 16, $itemType, $currency, 'ending')['list'];
    foreach ($railPool as $row) {
        if ($row['status'] === 'active' && strtotime($row['end_time']) > time()) {
            $rail[] = $row;
        }
        if (count($rail) >= 8) break;
    }

    $result = $auction->getActiveAuctions($page, 12, $itemType, $currency, $sort);
    $list   = $result['list'];
    $total  = $result['total'];
    $pages  = $result['pages'];
}
$soldList = $auction->getRecentlySold(8);

$sortLabels = ['hot' => '热拍中', 'ending' => '即将结束', 'price' => '价格', 'new' => '最新'];

$site_config['title'] = '竞价大厅 - 58拍卖 | 区块 · NFT 在线拍卖';
$site_config['description'] = '58拍卖竞价大厅：秒级倒计时、实时叫价、自动延时防狙击，区块与 NFT 头像价高者得。';
require_once 'includes/header.php';
?>

<div class="ac-wrap">

    <?php if ($tab === 'active' && $hero):
        $heroCounters = $auction->getCounters($hero);
        $heroDelta = ac_price_delta($hero);
        $heroImg = ac_auction_img($hero);
        $heroMin = floatval($hero['current_bidder_id'] ? floatval($hero['current_price']) + floatval($hero['bid_increment']) : $hero['start_price']);
    ?>
    <!-- 全场主推 -->
    <section class="ac-hero">
        <a class="ac-hero-media" href="view.php?id=<?= intval($hero['id']) ?>">
            <?php if ($heroImg): ?>
                <img src="<?= htmlspecialchars($heroImg) ?>" alt="<?= htmlspecialchars($hero['item_title'] ?? '') ?>">
            <?php else: ?>
                <span class="ph"><i class="fas fa-image"></i></span>
            <?php endif; ?>
        </a>
        <div class="ac-hero-info">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <span class="ac-lotno"><?= ac_lot_no($hero['id']) ?></span>
                <span class="ac-badge ac-badge-live"><span class="ac-live-dot"></span> 正在落槌</span>
            </div>
            <h2 class="ac-hero-title"><?= htmlspecialchars($hero['item_title'] ?? ('拍品 #' . $hero['id'])) ?></h2>

            <div class="ac-panel-row">
                <div>
                    <div class="ac-eyebrow">当前价</div>
                    <div class="ac-price ac-price-lg">
                        <span class="ac-price-unit"><?= ac_currency_symbol($hero['currency']) ?></span><span data-live="price"><?= number_format(floatval($hero['current_price'] ?? $hero['start_price']), 2) ?></span>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div class="ac-eyebrow">起拍价</div>
                    <div class="ac-muted ac-num"><?= ac_money($hero['start_price'], $hero['currency']) ?></div>
                    <?php if ($heroDelta !== null): ?>
                        <div class="ac-delta ac-delta-up"><?= number_format($heroDelta, 1) ?>%</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ac-panel-row" style="border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:12px 0;">
                <?php ac_render_countdown($hero, '距落槌'); ?>
                <span class="ac-muted" style="font-size:12px;"><?= date('m-d H:i', strtotime($hero['end_time'])) ?> 落槌</span>
            </div>

            <div class="ac-stats">
                <div class="ac-stat"><b data-live="bid_count"><?= intval($heroCounters['bid_count']) ?></b><span>出价次数</span></div>
                <div class="ac-stat"><b data-live="bidder_count"><?= intval($heroCounters['bidder_count']) ?></b><span>竞拍人数</span></div>
                <div class="ac-stat"><b data-live="watch_count"><?= intval($heroCounters['watch_count']) ?></b><span>关注</span></div>
            </div>

            <div class="ac-hero-foot">
                <a class="ac-btn ac-btn-primary" href="view.php?id=<?= intval($hero['id']) ?>">
                    <i class="fas fa-gavel"></i> 立即出价 <?= ac_money($heroMin, $hero['currency']) ?>
                </a>
                <a class="ac-btn ac-btn-ghost" href="view.php?id=<?= intval($hero['id']) ?>">查看详情</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($rail)): ?>
    <!-- 即将结束 -->
    <div class="ac-rail-head">
        <h2><i class="fas fa-hourglass-half" style="color:var(--live);"></i> 即将结束</h2>
        <a class="ac-muted" style="font-size:12px;" href="<?= ac_q(['sort' => 'ending', 'page' => 1]) ?>">按剩余时间查看 →</a>
    </div>
    <div class="ac-rail">
        <?php foreach ($rail as $r): ?>
        <a class="ac-rail-item" href="view.php?id=<?= intval($r['id']) ?>">
            <div class="t"><?= htmlspecialchars($r['item_title'] ?? ('拍品 #' . $r['id'])) ?></div>
            <div class="p"><?= ac_money($r['current_price'] ?? $r['start_price'], $r['currency']) ?></div>
            <?php ac_render_countdown($r, ''); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 筛选 -->
    <div class="ac-filters" style="margin-top:26px;">
        <a class="ac-chip <?= $tab === 'active' ? 'active' : '' ?>" href="<?= ac_q(['tab' => 'active', 'page' => 1]) ?>">拍卖中</a>
        <a class="ac-chip <?= $tab === 'ended' ? 'active' : '' ?>" href="<?= ac_q(['tab' => 'ended', 'page' => 1]) ?>">已落槌</a>
        <span style="width:1px;height:20px;background:var(--line);margin:0 4px;"></span>
        <a class="ac-chip <?= $itemType === '' ? 'active' : '' ?>" href="<?= ac_q(['type' => '', 'page' => 1]) ?>">全部</a>
        <a class="ac-chip <?= $itemType === 'block' ? 'active' : '' ?>" href="<?= ac_q(['type' => 'block', 'page' => 1]) ?>">区块</a>
        <a class="ac-chip <?= $itemType === 'nft' ? 'active' : '' ?>" href="<?= ac_q(['type' => 'nft', 'page' => 1]) ?>">NFT 头像</a>
    </div>

    <?php if ($tab === 'active'): ?>
    <div class="ac-filters" style="margin-top:-6px;">
        <span class="ac-muted" style="font-size:12px;margin-right:4px;">排序</span>
        <?php foreach ($sortLabels as $key => $label): ?>
            <a class="ac-chip <?= $sort === $key ? 'active' : '' ?>" href="<?= ac_q(['sort' => $key, 'page' => 1]) ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 拍品列表 -->
    <?php if (empty($list)): ?>
        <div class="ac-empty">
            <i class="fas fa-gavel"></i>
            <div><?= $tab === 'ended' ? '暂无已落槌的拍卖' : '暂无进行中的拍卖' ?></div>
            <?php if ($tab === 'active'): ?>
                <a class="ac-btn ac-btn-primary" href="create.php">发起一场拍卖</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="ac-grid">
            <?php foreach ($list as $a):
                $counters = $auction->getCounters($a);
                $img      = ac_auction_img($a);
                $isEnded  = in_array($a['status'], ['sold', 'ended', 'canceled'], true);
                $delta    = ac_price_delta($a);
                $left     = strtotime($a['end_time']) - time();
                $cardCls  = '';
                if ($a['status'] === 'active' && $left <= 300) $cardCls = 'is-urgent';
                elseif ($a['status'] === 'active' && $counters['bid_count'] >= 5) $cardCls = 'is-hot';
            ?>
            <a class="ac-card <?= $cardCls ?>" href="view.php?id=<?= intval($a['id']) ?>">
                <div class="ac-card-media">
                    <span class="ac-card-corner"><?php ac_status_badge($a); ?></span>
                    <?php if ($img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($a['item_title'] ?? '') ?>" loading="lazy">
                    <?php else: ?>
                        <span class="ph"><i class="fas fa-image"></i></span>
                    <?php endif; ?>
                </div>
                <div class="ac-card-body">
                    <div class="ac-muted" style="font-size:11px;letter-spacing:.12em;"><?= ac_lot_no($a['id']) ?></div>
                    <div class="ac-card-title"><?= htmlspecialchars($a['item_title'] ?? ('拍品 #' . $a['id'])) ?></div>
                    <div class="ac-card-price">
                        <div class="ac-price" style="font-size:19px;"><?= ac_money($a['current_price'] ?? $a['start_price'], $a['currency']) ?></div>
                        <?php if ($delta !== null): ?><span class="ac-delta ac-delta-up"><?= number_format($delta, 0) ?>%</span><?php endif; ?>
                    </div>
                    <?php if ($isEnded): ?>
                        <div class="ac-muted" style="font-size:12px;">
                            <?= $a['status'] === 'sold' ? '成交 ' . ac_money($a['final_price'] ?? $a['current_price'], $a['currency']) : '未成交（流拍）' ?>
                        </div>
                    <?php else: ?>
                        <?php ac_render_countdown($a, ''); ?>
                    <?php endif; ?>
                    <div class="ac-card-foot">
                        <span><b class="ac-num"><?= intval($counters['bid_count']) ?></b> 次出价</span>
                        <span><b class="ac-num"><?= intval($counters['bidder_count']) ?></b> 人竞拍</span>
                        <span><i class="far fa-star"></i> <b class="ac-num"><?= intval($counters['watch_count']) ?></b></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
        <div class="ac-pager">
            <?php if ($page > 1): ?><a class="ac-chip" href="<?= ac_q(['page' => $page - 1]) ?>">上一页</a><?php endif; ?>
            <span><?= $page ?> / <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="ac-chip" href="<?= ac_q(['page' => $page + 1]) ?>">下一页</a><?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- 刚落槌成交榜 -->
    <?php if (!empty($soldList)): ?>
    <section class="ac-sold-strip">
        <div class="ac-rail-head">
            <h2><i class="fas fa-gavel" style="color:var(--brand);"></i> 刚落槌</h2>
            <span class="ac-muted" style="font-size:12px;">成交 <?= intval($total ?? 0) ?> 件拍品，下一件等你出手</span>
        </div>
        <div class="ac-sold-list">
            <?php foreach ($soldList as $s):
                $sImg = ac_auction_img($s);
                $soldTs = strtotime($s['sold_at'] ?? $s['updated_at'] ?? $s['end_time']);
            ?>
            <a class="ac-sold-item" href="view.php?id=<?= intval($s['id']) ?>">
                <div class="ac-sold-thumb">
                    <?php if ($sImg): ?><img src="<?= htmlspecialchars($sImg) ?>" alt="" loading="lazy"><?php endif; ?>
                </div>
                <div style="min-width:0;">
                    <div class="t"><?= htmlspecialchars($s['item_title'] ?? ('拍品 #' . $s['id'])) ?></div>
                    <div class="p"><?= ac_money($s['final_price'] ?? $s['current_price'], $s['currency']) ?></div>
                    <div class="ac-muted" style="font-size:11px;" data-ts="<?= $soldTs ?>">—</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<script>window.AC_SERVER_NOW = <?= time() ?>;</script>
<?php require_once 'includes/footer.php'; ?>
