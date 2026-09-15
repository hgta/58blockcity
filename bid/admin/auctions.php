<?php
/**
 * 58拍卖子站 — 拍卖单管理列表
 * 状态/类型筛选 + 分页，只读管理（状态由拍卖引擎自动推进）
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../../classes/Auction.php';

// 统一后台框架配置
$admin_site_config = [
    'site'       => 'bid',
    'page_title' => '拍卖单管理',
];
require_once '../../shared/admin/admin-header.php';

$auction = new Auction($pdo);

// 惰性推进状态机，保证列表状态准确
$auction->tick();

// ---- 筛选参数 ----
$statusList = ['pending', 'active', 'sold', 'ended', 'canceled'];
$typeList   = ['block', 'nft'];

$fStatus = $_GET['status'] ?? '';
$fType   = $_GET['type'] ?? '';
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];
if (in_array($fStatus, $statusList, true)) {
    $where[]  = 'a.status = ?';
    $params[] = $fStatus;
}
if (in_array($fType, $typeList, true)) {
    $where[]  = 'a.item_type = ?';
    $params[] = $fType;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---- 总数 ----
$stmt = $pdo->prepare("SELECT COUNT(*) FROM auctions a $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// ---- 列表 ----
$stmt = $pdo->prepare("
    SELECT a.id, a.item_type, a.item_id, a.seller_id, a.start_price, a.reserve_price,
           a.bid_increment, a.start_time, a.end_time, a.current_price, a.currency,
           a.status, a.current_bidder_id, a.created_at,
           u.username AS seller_name,
           (SELECT COUNT(*) FROM auction_bids b WHERE b.auction_id = a.id) AS bid_count
    FROM auctions a
    LEFT JOIN users u ON a.seller_id = u.id
    $whereSql
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
    'pending'  => ['即将开拍', 'warning'],
    'active'   => ['竞拍中',   'success'],
    'sold'     => ['已落槌',   'info'],
    'ended'    => ['已流拍',   'danger'],
    'canceled' => ['已取消',   'default'],
];

function bid_money($amount, $currency) {
    return $currency === 'popularity'
        ? number_format((float)$amount) . ' 人气值'
        : '¥' . number_format((float)$amount, 2);
}

function filterUrl($status, $type, $page) {
    $qs = [];
    if ($status !== '') $qs['status'] = $status;
    if ($type !== '')   $qs['type'] = $type;
    if ($page > 1)      $qs['page'] = $page;
    return 'auctions.php' . ($qs ? '?' . http_build_query($qs) : '');
}
?>
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-gavel" style="margin-right:8px;color:var(--admin-accent);"></i>拍卖单（<?= number_format($total) ?>）</span>
    </div>
    <div class="admin-card-body" style="padding:0;">

        <!-- 筛选栏 -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid var(--admin-border);align-items:center;">
            <span style="font-size:13px;color:var(--admin-text-muted);">状态：</span>
            <a class="admin-btn admin-btn-sm <?= $fStatus === '' ? 'admin-btn-primary' : 'admin-btn-secondary' ?>" href="<?= htmlspecialchars(filterUrl('', $fType, 1)) ?>">全部</a>
            <?php foreach ($statusMap as $k => $v): ?>
            <a class="admin-btn admin-btn-sm <?= $fStatus === $k ? 'admin-btn-primary' : 'admin-btn-secondary' ?>" href="<?= htmlspecialchars(filterUrl($k, $fType, 1)) ?>"><?= $v[0] ?></a>
            <?php endforeach; ?>
            <span style="font-size:13px;color:var(--admin-text-muted);margin-left:12px;">类型：</span>
            <a class="admin-btn admin-btn-sm <?= $fType === '' ? 'admin-btn-primary' : 'admin-btn-secondary' ?>" href="<?= htmlspecialchars(filterUrl($fStatus, '', 1)) ?>">全部</a>
            <?php foreach ($typeList as $t): ?>
            <a class="admin-btn admin-btn-sm <?= $fType === $t ? 'admin-btn-primary' : 'admin-btn-secondary' ?>" href="<?= htmlspecialchars(filterUrl($fStatus, $t, 1)) ?>"><?= $t === 'block' ? '区块' : 'NFT头像' ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($rows)): ?>
            <div class="admin-empty-state"><i class="fas fa-inbox"></i><h4>暂无符合条件的拍卖单</h4></div>
        <?php else: ?>
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>拍品</th><th>卖家</th><th>起拍价</th><th>当前价</th>
                    <th>出价</th><th>开拍</th><th>落槌</th><th>状态</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $a): ?>
                <?php $s = $statusMap[$a['status']] ?? [$a['status'], 'default']; ?>
                <tr>
                    <td>
                        <a href="../view.php?id=<?= (int)$a['id'] ?>" style="color:var(--admin-accent);font-weight:600;">LOT <?= str_pad((string)$a['id'], 3, '0', STR_PAD_LEFT) ?></a>
                        <span style="font-size:12px;color:var(--admin-text-muted);"><?= $a['item_type'] === 'block' ? '区块 #' . $a['item_id'] : 'NFT #' . $a['item_id'] ?></span>
                    </td>
                    <td style="font-size:13px;"><?= htmlspecialchars($a['seller_name'] ?? ('用户' . $a['seller_id'])) ?></td>
                    <td><?= bid_money($a['start_price'], $a['currency']) ?></td>
                    <td style="font-weight:600;"><?= bid_money($a['current_price'] ?? $a['start_price'], $a['currency']) ?></td>
                    <td><?= (int)$a['bid_count'] ?></td>
                    <td style="font-size:12px;"><?= date('m-d H:i', strtotime($a['start_time'])) ?></td>
                    <td style="font-size:12px;"><?= date('m-d H:i', strtotime($a['end_time'])) ?></td>
                    <td><span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span></td>
                    <td><a class="admin-btn admin-btn-sm admin-btn-secondary" href="../view.php?id=<?= (int)$a['id'] ?>">查看</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;gap:6px;justify-content:center;padding:14px;border-top:1px solid var(--admin-border);">
            <?php if ($page > 1): ?>
                <a class="admin-btn admin-btn-sm admin-btn-secondary" href="<?= htmlspecialchars(filterUrl($fStatus, $fType, $page - 1)) ?>">上一页</a>
            <?php endif; ?>
            <span style="font-size:13px;color:var(--admin-text-muted);padding:6px 10px;"><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="admin-btn admin-btn-sm admin-btn-secondary" href="<?= htmlspecialchars(filterUrl($fStatus, $fType, $page + 1)) ?>">下一页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
