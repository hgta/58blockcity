<?php
/**
 * 58拍卖子站 — 管理后台看板
 * 统一后台框架（shared/admin），与其他子站后台共用侧栏/站点切换
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Auction.php';

// 统一后台框架配置
$admin_site_config = [
    'site'       => 'bid',
    'page_title' => '拍卖管理看板',
];
require_once '../../shared/admin/admin-header.php';

$auction = new Auction($pdo);

// 惰性推进状态机（与 api/lot.php 同策略：管理员访问时顺带激活/落槌到点拍品）
$auction->tick();

// ---- 状态统计 ----
$statusRows = $pdo->query("SELECT status, COUNT(*) AS cnt FROM auctions GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$statusCnt = array_fill_keys(['pending', 'active', 'sold', 'ended', 'canceled'], 0);
foreach ($statusRows as $r) {
    $statusCnt[$r['status']] = (int)$r['cnt'];
}
$totalAuctions = array_sum($statusCnt);

// ---- 出价统计 ----
$totalBids = $pdo->query("SELECT COUNT(*) FROM auction_bids")->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM auction_bids WHERE created_at >= ?");
$stmt->execute([date('Y-m-d 00:00:00')]);
$todayBids = $stmt->fetchColumn() ?: 0;

// ---- 成交金额（按货币分列）----
$soldRows = $pdo->query("SELECT currency, COUNT(*) AS cnt, COALESCE(SUM(current_price),0) AS amt FROM auctions WHERE status='sold' GROUP BY currency")->fetchAll(PDO::FETCH_ASSOC);
$soldStats = ['cny' => ['cnt' => 0, 'amt' => 0], 'popularity' => ['cnt' => 0, 'amt' => 0]];
foreach ($soldRows as $r) {
    $soldStats[$r['currency']] = ['cnt' => (int)$r['cnt'], 'amt' => (float)$r['amt']];
}

$statusMap = [
    'pending'  => ['即将开拍', 'warning'],
    'active'   => ['竞拍中',   'success'],
    'sold'     => ['已落槌',   'info'],
    'ended'    => ['已流拍',   'danger'],
    'canceled' => ['已取消',   'default'],
];

// ---- 最近拍卖单 ----
$recentAuctions = $pdo->query("
    SELECT a.id, a.item_type, a.start_price, a.current_price, a.currency,
           a.start_time, a.end_time, a.status, a.seller_id, a.current_bidder_id,
           u.username AS seller_name,
           (SELECT COUNT(*) FROM auction_bids b WHERE b.auction_id = a.id) AS bid_count
    FROM auctions a
    LEFT JOIN users u ON a.seller_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ---- 最近出价 ----
$recentBids = $pdo->query("
    SELECT b.id, b.auction_id, b.amount, b.created_at, b.bidder_id,
           u.username AS bidder_name,
           a.item_type, a.status
    FROM auction_bids b
    LEFT JOIN users u ON b.bidder_id = u.id
    LEFT JOIN auctions a ON b.auction_id = a.id
    ORDER BY b.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

/** 金额展示：¥ / 人气值 */
function bid_money($amount, $currency) {
    return $currency === 'popularity'
        ? number_format((float)$amount) . ' 人气值'
        : '¥' . number_format((float)$amount, 2);
}
?>
<div class="admin-stats-grid" style="margin-bottom:20px;">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-gavel"></i></div>
        <div class="stat-value"><?= number_format($totalAuctions) ?></div>
        <div class="stat-label">拍卖单总数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-broadcast-tower"></i></div>
        <div class="stat-value"><?= number_format($statusCnt['active']) ?></div>
        <div class="stat-label">竞拍中</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-value"><?= number_format($statusCnt['pending']) ?></div>
        <div class="stat-label">即将开拍</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= number_format($statusCnt['sold']) ?></div>
        <div class="stat-label">已落槌</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon danger"><i class="fas fa-times-circle"></i></div>
        <div class="stat-value"><?= number_format($statusCnt['ended'] + $statusCnt['canceled']) ?></div>
        <div class="stat-label">流拍/取消</div>
    </div>
</div>

<div class="admin-stats-grid" style="margin-bottom:20px;">
    <div class="admin-stat-card">
        <div class="stat-icon info"><i class="fas fa-hand-point-up"></i></div>
        <div class="stat-value"><?= number_format($totalBids) ?></div>
        <div class="stat-label">总出价次数</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon success"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-value"><?= number_format($todayBids) ?></div>
        <div class="stat-label">今日出价</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon accent"><i class="fas fa-coins"></i></div>
        <div class="stat-value">¥<?= number_format($soldStats['cny']['amt'], 2) ?></div>
        <div class="stat-label">落槌成交总额（人民币，<?= $soldStats['cny']['cnt'] ?> 场）</div>
    </div>
    <div class="admin-stat-card">
        <div class="stat-icon warning"><i class="fas fa-star"></i></div>
        <div class="stat-value"><?= number_format($soldStats['popularity']['amt']) ?></div>
        <div class="stat-label">落槌成交总额（人气值，<?= $soldStats['popularity']['cnt'] ?> 场）</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- 最近拍卖单 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-gavel" style="margin-right:8px;color:var(--admin-accent);"></i>最近拍卖单</span>
            <a href="auctions.php" class="admin-btn admin-btn-sm admin-btn-secondary">查看全部</a>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($recentAuctions)): ?>
                <div class="admin-empty-state"><i class="fas fa-inbox"></i><h4>暂无拍卖单</h4></div>
            <?php else: ?>
            <table class="admin-data-table">
                <thead><tr><th>拍品</th><th>卖家</th><th>当前价</th><th>状态</th></tr></thead>
                <tbody>
                    <?php foreach ($recentAuctions as $a): ?>
                    <?php $s = $statusMap[$a['status']] ?? [$a['status'], 'default']; ?>
                    <tr>
                        <td>
                            <a href="../view.php?id=<?= (int)$a['id'] ?>" style="color:var(--admin-accent);font-weight:600;">LOT <?= str_pad((string)$a['id'], 3, '0', STR_PAD_LEFT) ?></a>
                            <span style="font-size:12px;color:var(--admin-text-muted);"><?= $a['item_type'] === 'block' ? '区块' : 'NFT头像' ?></span>
                        </td>
                        <td style="font-size:13px;"><?= htmlspecialchars($a['seller_name'] ?? ('用户' . $a['seller_id'])) ?></td>
                        <td style="font-weight:600;"><?= bid_money($a['current_price'] ?? $a['start_price'], $a['currency']) ?></td>
                        <td><span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- 最近出价 -->
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-hand-point-up" style="margin-right:8px;color:var(--admin-accent);"></i>最近出价</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($recentBids)): ?>
                <div class="admin-empty-state"><i class="fas fa-inbox"></i><h4>暂无出价</h4></div>
            <?php else: ?>
            <table class="admin-data-table">
                <thead><tr><th>拍品</th><th>出价人</th><th>金额</th><th>时间</th></tr></thead>
                <tbody>
                    <?php foreach ($recentBids as $b): ?>
                    <tr>
                        <td>
                            <a href="../view.php?id=<?= (int)$b['auction_id'] ?>" style="color:var(--admin-accent);font-weight:600;">LOT <?= str_pad((string)$b['auction_id'], 3, '0', STR_PAD_LEFT) ?></a>
                        </td>
                        <td style="font-size:13px;"><?= htmlspecialchars($b['bidder_name'] ?? ('用户' . $b['bidder_id'])) ?></td>
                        <td style="font-weight:600;">¥<?= number_format((float)$b['amount'], 2) ?></td>
                        <td style="font-size:12px;color:var(--admin-text-muted);"><?= date('m-d H:i', strtotime($b['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
