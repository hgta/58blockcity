<?php
/**
 * 58拍卖 · 我的拍卖（卖家看板 / 我的竞价）
 */
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../includes/auth.php';
require_once '../includes/lot_helpers.php';
checkLogin();

$auction  = new Auction($pdo);
$userId   = intval($_SESSION['user_id']);

$auction->tick();

// 卖家操作：取消 / 删除
$opMsg = '';
$opErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $opErr = 'CSRF 令牌验证失败';
    } elseif ($action === 'cancel') {
        $r = $auction->cancelAuction(intval($_POST['id'] ?? 0), $userId);
        if (!empty($r['ok'])) $opMsg = $r['msg'];
        else $opErr = $r['msg'];
    }
}

// 兼容旧链接：tab=created → 卖家看板，tab=bids → 我的竞价
$tabAlias = ['created' => 'seller', 'bids' => 'bidding'];
$tabInput = $_GET['tab'] ?? '';
$tabInput = $tabAlias[$tabInput] ?? $tabInput;
$tab = in_array($tabInput, ['seller', 'bidding'], true) ? $tabInput : 'seller';

$myAuctions = $auction->getMyAuctions($userId);
$myBids     = $auction->getMyBids($userId);
$myWatched  = $auction->getMyWatched($userId);

// 竞价视角状态分组
$groups = ['leading' => [], 'outbid' => [], 'won' => [], 'lost' => []];
$bidIds = [];
foreach ($myBids as $a) {
    $bidIds[intval($a['id'])] = true;
    $counters = $auction->getCounters($a);
    $a['_counters'] = $counters;

    if ($a['status'] === 'sold') {
        if (intval($a['current_bidder_id']) === $userId) $groups['won'][] = $a;
        else $groups['lost'][] = $a;
    } elseif (in_array($a['status'], ['ended', 'canceled'], true)) {
        $groups['lost'][] = $a;
    } elseif (intval($a['current_bidder_id']) === $userId) {
        $groups['leading'][] = $a;
    } else {
        $groups['outbid'][] = $a;
    }
}

$watchedOnly = [];
foreach ($myWatched as $a) {
    if (isset($bidIds[intval($a['id'])])) continue;
    $a['_counters'] = $auction->getCounters($a);
    $watchedOnly[] = $a;
}

$groupMeta = [
    'leading' => ['我正领先', 'ac-badge-lead', 'fa-crown'],
    'outbid'  => ['已被超越', 'ac-badge-live', 'fa-triangle-exclamation'],
    'won'     => ['已得标', 'ac-badge-sold', 'fa-trophy'],
    'lost'    => ['已结束未得标', 'ac-badge-soft', 'fa-flag-checkered'],
];

$site_config['title'] = '我的拍卖 - 58拍卖';
require_once 'includes/header.php';
?>

<div class="ac-wrap">
    <h1 style="font-size:22px;font-weight:800;margin:6px 0 18px;">
        <i class="fas fa-user" style="color:var(--brand);"></i> 我的拍卖
    </h1>

    <div class="ac-tabs">
        <a class="ac-tab <?= $tab === 'seller' ? 'active' : '' ?>" href="my.php?tab=seller">
            卖家看板 <span class="ac-muted">(<?= count($myAuctions) ?>)</span>
        </a>
        <a class="ac-tab <?= $tab === 'bidding' ? 'active' : '' ?>" href="my.php?tab=bidding">
            我的竞价 <span class="ac-muted">(<?= count($myBids) ?>)</span>
        </a>
    </div>

    <?php if ($opMsg): ?><div class="ac-alert ac-alert-ok"><?= htmlspecialchars($opMsg) ?></div><?php endif; ?>
    <?php if ($opErr): ?><div class="ac-alert ac-alert-err"><?= htmlspecialchars($opErr) ?></div><?php endif; ?>

    <?php if ($tab === 'seller'): ?>
        <?php if (empty($myAuctions)): ?>
            <div class="ac-empty">
                <i class="fas fa-gavel"></i>
                <div>您还没有发布过拍卖</div>
                <a class="ac-btn ac-btn-primary" href="create.php">发起一场拍卖</a>
            </div>
        <?php else: ?>
            <?php foreach ($myAuctions as $a):
                $img      = ac_auction_img($a);
                $counters = $auction->getCounters($a);
                $isOver   = in_array($a['status'], ['sold', 'ended', 'canceled'], true);
                $canCancel = $a['status'] === 'pending' || ($a['status'] === 'active' && empty($a['current_bidder_id']));
            ?>
            <div class="ac-row">
                <a class="ac-row-thumb" href="view.php?id=<?= intval($a['id']) ?>">
                    <?php if ($img): ?><img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy"><?php endif; ?>
                </a>
                <div class="ac-row-main">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span class="ac-lotno"><?= ac_lot_no($a['id']) ?></span>
                        <?php ac_status_badge($a); ?>
                        <?php if ($a['status'] === 'active' && $counters['bid_count'] > 0): ?>
                            <span class="ac-badge ac-badge-hot"><i class="fas fa-fire"></i> 已收 <?= intval($counters['bid_count']) ?> 口出价</span>
                        <?php endif; ?>
                    </div>
                    <a class="ac-card-title" style="color:var(--text);" href="view.php?id=<?= intval($a['id']) ?>">
                        <?= htmlspecialchars($a['item_title'] ?? ('拍品 #' . $a['id'])) ?>
                    </a>
                    <div class="ac-row-stats">
                        <span>当前价 <b><?= ac_money($a['current_price'] ?? $a['start_price'], $a['currency']) ?></b></span>
                        <span>出价 <b><?= intval($counters['bid_count']) ?></b> 次</span>
                        <span>竞拍 <b><?= intval($counters['bidder_count']) ?></b> 人</span>
                        <span>关注 <b><?= intval($counters['watch_count']) ?></b></span>
                        <span>起拍 <b><?= ac_money($a['start_price'], $a['currency']) ?></b></span>
                    </div>
                    <?php if ($isOver): ?>
                        <div class="ac-muted" style="font-size:12px;">
                            <?= $a['status'] === 'sold' ? '成交 ' . ac_money($a['final_price'] ?? $a['current_price'], $a['currency']) : '未成交（流拍）' ?>
                            · <?= date('m-d H:i', strtotime($a['end_time'])) ?>
                        </div>
                    <?php else: ?>
                        <div><?php ac_render_countdown($a, ''); ?></div>
                    <?php endif; ?>
                </div>
                <div class="ac-row-side">
                    <?php if ($a['status'] === 'pending'): ?>
                        <a class="ac-btn ac-btn-ghost" style="padding:7px 12px;font-size:12px;" href="create.php?edit=<?= intval($a['id']) ?>">
                            <i class="fas fa-pen"></i> 编辑
                        </a>
                    <?php endif; ?>
                    <?php if ($canCancel): ?>
                        <form method="POST" onsubmit="return confirm('确定取消该拍卖吗？取消后物品将解除锁定。');" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= intval($a['id']) ?>">
                            <button type="submit" class="ac-btn ac-btn-ghost" style="padding:7px 12px;font-size:12px;color:var(--live);border-color:rgba(255,77,61,.4);">
                                <i class="fas fa-trash"></i> 取消
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>
        <?php if (empty($myBids) && empty($watchedOnly)): ?>
            <div class="ac-empty">
                <i class="fas fa-hand-holding-usd"></i>
                <div>您还没有参与过竞价</div>
                <a class="ac-btn ac-btn-primary" href="index.php">去竞价大厅看看</a>
            </div>
        <?php endif; ?>

        <?php foreach ($groupMeta as $key => $meta):
            if (empty($groups[$key])) continue; ?>
            <div class="ac-group">
                <div class="ac-group-head">
                    <span class="ac-badge <?= $meta[1] ?>"><i class="fas <?= $meta[2] ?>"></i> <?= $meta[0] ?></span>
                    <span class="cnt"><?= count($groups[$key]) ?> 件</span>
                </div>
                <?php foreach ($groups[$key] as $a):
                    $img      = ac_auction_img($a);
                    $counters = $a['_counters'];
                ?>
                <div class="ac-row <?= $key === 'outbid' ? 'is-outbid' : '' ?>">
                    <a class="ac-row-thumb" href="view.php?id=<?= intval($a['id']) ?>">
                        <?php if ($img): ?><img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy"><?php endif; ?>
                    </a>
                    <div class="ac-row-main">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="ac-lotno"><?= ac_lot_no($a['id']) ?></span>
                            <?php ac_status_badge($a); ?>
                        </div>
                        <a class="ac-card-title" style="color:var(--text);" href="view.php?id=<?= intval($a['id']) ?>">
                            <?= htmlspecialchars($a['item_title'] ?? ('拍品 #' . $a['id'])) ?>
                        </a>
                        <div class="ac-row-stats">
                            <span>我出 <b><?= ac_money($a['my_max_bid'] ?? 0, $a['currency']) ?></b></span>
                            <span>当前价 <b><?= ac_money($a['current_price'] ?? $a['start_price'], $a['currency']) ?></b></span>
                            <span><b><?= intval($counters['bid_count']) ?></b> 次出价</span>
                            <span><b><?= intval($counters['bidder_count']) ?></b> 人竞拍</span>
                        </div>
                        <?php if (!in_array($a['status'], ['sold', 'ended', 'canceled'], true)): ?>
                            <div><?php ac_render_countdown($a, ''); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="ac-row-side">
                        <?php if ($key === 'outbid' && $a['status'] === 'active'): ?>
                            <a class="ac-btn ac-btn-primary" style="padding:8px 14px;font-size:13px;" href="view.php?id=<?= intval($a['id']) ?>">
                                <i class="fas fa-gavel"></i> 再次出价
                            </a>
                        <?php elseif ($key === 'won'): ?>
                            <span class="ac-muted" style="font-size:12px;">成交 <?= ac_money($a['final_price'] ?? $a['current_price'], $a['currency']) ?></span>
                        <?php else: ?>
                            <a class="ac-btn ac-btn-ghost" style="padding:8px 14px;font-size:13px;" href="view.php?id=<?= intval($a['id']) ?>">查看</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($watchedOnly)): ?>
            <div class="ac-group">
                <div class="ac-group-head">
                    <span class="ac-badge ac-badge-soft"><i class="far fa-star"></i> 我关注的</span>
                    <span class="cnt"><?= count($watchedOnly) ?> 件</span>
                </div>
                <?php foreach ($watchedOnly as $a):
                    $img = ac_auction_img($a);
                    $counters = $a['_counters'];
                ?>
                <div class="ac-row">
                    <a class="ac-row-thumb" href="view.php?id=<?= intval($a['id']) ?>">
                        <?php if ($img): ?><img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy"><?php endif; ?>
                    </a>
                    <div class="ac-row-main">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="ac-lotno"><?= ac_lot_no($a['id']) ?></span>
                            <?php ac_status_badge($a); ?>
                        </div>
                        <a class="ac-card-title" style="color:var(--text);" href="view.php?id=<?= intval($a['id']) ?>">
                            <?= htmlspecialchars($a['item_title'] ?? ('拍品 #' . $a['id'])) ?>
                        </a>
                        <div class="ac-row-stats">
                            <span>当前价 <b><?= ac_money($a['current_price'] ?? $a['start_price'], $a['currency']) ?></b></span>
                            <span><b><?= intval($counters['bid_count']) ?></b> 次出价</span>
                            <span>关注 <b><?= intval($counters['watch_count']) ?></b></span>
                        </div>
                        <div><?php ac_render_countdown($a, ''); ?></div>
                    </div>
                    <div class="ac-row-side">
                        <a class="ac-btn ac-btn-primary" style="padding:8px 14px;font-size:13px;" href="view.php?id=<?= intval($a['id']) ?>">
                            <i class="fas fa-gavel"></i> 去出价
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>window.AC_SERVER_NOW = <?= time() ?>;</script>
<?php require_once 'includes/footer.php'; ?>
