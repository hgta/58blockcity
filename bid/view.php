<?php
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../includes/auth.php';

$auction = new Auction($pdo);
$userId = $_SESSION['user_id'] ?? 0;

$auctionId = intval($_GET['id'] ?? 0);
if ($auctionId <= 0) {
    http_response_code(404);
    include '../404.php';
    exit;
}

// 惰性结算
$auction->settleExpired();

// 出价处理
$bidMsg = '';
$bidErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_bid'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $r = $auction->placeBid($auctionId, $userId, $amount);
    if ($r['ok']) {
        $bidMsg = '出价成功';
    } else {
        $bidErr = $r['msg'];
    }
}

$a = $auction->getAuctionById($auctionId);
if (!$a) {
    http_response_code(404);
    include '../404.php';
    exit;
}

$bids = $auction->getBids($auctionId, 50);
$isSeller = intval($a['seller_id']) === $userId;
$isCurrentBidder = intval($a['current_bidder_id'] ?? 0) === $userId;

// 当前最小可出价
$currentPrice = floatval($a['current_price'] ?? $a['start_price']);
$minBid = $a['current_bidder_id'] === null ? floatval($a['start_price']) : $currentPrice + floatval($a['bid_increment']);

$site_config['title'] = ($a['item_title'] ?? '拍卖详情') . ' - 58拍卖';
require_once 'includes/header.php';
?>
<style>
.view-wrap { max-width: 900px; margin: 24px auto; padding: 0 15px; }
.view-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; }
@media (max-width: 700px) { .view-grid { grid-template-columns: 1fr; } }
.view-img { background: #f5f5f5; border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center; aspect-ratio: 1/1; }
.view-img img { width: 100%; height: 100%; object-fit: cover; }
.view-img .ph { color: #ccc; font-size: 60px; }
.view-info { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.view-title { font-size: 22px; font-weight: bold; color: #222; margin-bottom: 8px; }
.view-tag { display: inline-block; font-size: 12px; padding: 3px 10px; border-radius: 4px; background: #eef2ff; color: #4f46e5; margin-bottom: 12px; }
.view-tag.nft { background: #fce7f3; color: #db2777; }
.view-price { font-size: 28px; font-weight: bold; color: #e74c3c; margin-bottom: 12px; }
.view-status { font-size: 13px; color: #666; margin-bottom: 16px; }
.info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f2f2f2; font-size: 14px; }
.info-label { color: #999; }
.bid-form { margin-top: 20px; display: flex; gap: 10px; }
.bid-input { flex: 1; padding: 11px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; }
.btn-primary { background: #ff6b00; color: #fff; border: none; border-radius: 8px; padding: 11px 24px; font-size: 15px; cursor: pointer; font-weight: bold; white-space: nowrap; }
.btn-primary:hover { background: #e05d00; }
.btn-primary:disabled { background: #ccc; cursor: not-allowed; }
.alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 14px; }
.alert-ok { background: #d4edda; color: #155724; }
.alert-err { background: #f8d7da; color: #721c24; }
.bids-section { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-top: 24px; }
.bids-section h2 { font-size: 18px; margin-bottom: 16px; }
.bid-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f2f2f2; font-size: 14px; }
.bid-row .bidder { color: #555; }
.bid-row .amount { font-weight: bold; color: #e74c3c; }
.bid-row .time { color: #999; font-size: 12px; }
.status-tag { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
.st-active { background: #e8f4ff; color: #337be6; }
.st-pending { background: #fff3e0; color: #ff6b00; }
.st-sold { background: #d4edda; color: #155724; }
.st-ended { background: #f0f0f0; color: #999; }
</style>

<div class="view-wrap">
    <div class="view-grid">
        <div class="view-img">
            <?php if (!empty($a['item_image'])): 
                $imgUrl = preg_match('#^https?://#', $a['item_image']) ? $a['item_image'] : '/' . $a['item_image'];
            ?>
                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="">
            <?php else: ?>
                <span class="ph"><i class="fas fa-image"></i></span>
            <?php endif; ?>
        </div>
        <div class="view-info">
            <span class="view-tag <?= $a['item_type'] === 'nft' ? 'nft' : '' ?>"><?= $a['item_type'] === 'nft' ? 'NFT头像' : '区块' ?></span>
            <div class="view-title"><?= htmlspecialchars($a['item_title'] ?? ('拍卖 #' . $a['id'])) ?></div>

            <div class="view-status">
                <?php
                $statusMap = [
                    'pending' => ['未开始', 'st-pending'],
                    'active'  => ['竞拍中', 'st-active'],
                    'sold'    => ['已成交', 'st-sold'],
                    'ended'   => ['已流拍', 'st-ended'],
                    'canceled'=> ['已取消', 'st-ended'],
                ];
                $st = $statusMap[$a['status']] ?? ['未知', 'st-ended'];
                ?>
                <span class="status-tag <?= $st[1] ?>"><?= $st[0] ?></span>
                <?php if ($a['status'] === 'active'): ?>
                <span style="margin-left:8px;">距截止 <?= $a['end_time'] ?></span>
                <?php endif; ?>
            </div>

            <div class="view-price"><?= $a['currency'] === 'popularity' ? 'Ⓟ ' : '¥ ' ?><?= number_format($currentPrice, 2) ?></div>

            <div class="info-row"><span class="info-label">卖家</span><span><?= htmlspecialchars($a['seller_name'] ?? '用户#' . $a['seller_id']) ?></span></div>
            <div class="info-row"><span class="info-label">起拍价</span><span><?= number_format($a['start_price'], 2) ?></span></div>
            <div class="info-row"><span class="info-label">加价幅度</span><span><?= number_format($a['bid_increment'], 2) ?></span></div>
            <div class="info-row"><span class="info-label">开始时间</span><span><?= $a['start_time'] ?></span></div>
            <div class="info-row"><span class="info-label">截止时间</span><span><?= $a['end_time'] ?></span></div>
            <?php if ($a['accept_cities']): ?><div class="info-row"><span class="info-label">接受城市</span><span>已指定</span></div><?php endif; ?>
            <?php if ($a['current_bidder_id']): ?><div class="info-row"><span class="info-label">当前最高出价人</span><span><?= $isCurrentBidder ? '您' : '用户#' . $a['current_bidder_id'] ?></span></div><?php endif; ?>

            <?php if ($bidMsg): ?><div class="alert alert-ok"><?= htmlspecialchars($bidMsg) ?></div><?php endif; ?>
            <?php if ($bidErr): ?><div class="alert alert-err"><?= htmlspecialchars($bidErr) ?></div><?php endif; ?>

            <?php if ($a['status'] === 'active' && $userId && !$isSeller): ?>
            <form method="POST" class="bid-form">
                <input type="hidden" name="action_bid" value="1">
                <input type="number" name="amount" class="bid-input" step="0.01" min="<?= $minBid ?>" value="<?= $minBid ?>" required>
                <button type="submit" class="btn-primary">出价</button>
            </form>
            <?php elseif ($a['status'] === 'active' && !$userId): ?>
            <div style="margin-top:20px;"><a href="auth/login.php?redirect=<?= urlencode('view.php?id=' . $auctionId) ?>" style="color:#ff6b00;">登录</a>后即可出价</div>
            <?php elseif ($a['status'] === 'active' && $isSeller): ?>
            <div style="margin-top:20px;color:#999;">您是卖家，不能出价自己的拍卖</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bids-section">
        <h2>📜 出价记录（<?= count($bids) ?>）</h2>
        <?php if (empty($bids)): ?>
            <div style="color:#999;padding:20px;text-align:center;">暂无出价</div>
        <?php else: ?>
            <?php foreach ($bids as $b): ?>
            <div class="bid-row">
                <span class="bidder"><?= htmlspecialchars($b['bidder_name'] ?? ('用户#' . $b['bidder_id'])) ?></span>
                <span class="amount"><?= number_format($b['amount'], 2) ?></span>
                <span class="time"><?= date('m-d H:i:s', strtotime($b['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
