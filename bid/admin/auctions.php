<?php
/**
 * 58拍卖子站 — 拍卖单管理列表
 * 状态/类型筛选 + 分页 + 推荐位开关
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

// ---- POST 处理：推荐位开关 ----
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_featured') {
    $aid = intval($_POST['id'] ?? 0);
    $on  = $_POST['on'] === '1';
    if ($aid > 0) {
        $ok = $auction->setFeatured($aid, $on);
        $flash = $ok
            ? ($on ? '已推荐到首页顶部' : '已取消推荐')
            : ($on ? '推荐失败：该拍卖已结束/被取消' : '操作完成');
    }
    // 重定向回原筛选状态（避免刷新重复提交）
    $qs = $_GET;
    $qs['msg'] = $flash;
    header('Location: auctions.php?' . http_build_query($qs));
    exit;
}
if (!empty($_GET['msg'])) {
    $flash = (string)$_GET['msg'];
}

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
           a.status, a.current_bidder_id, a.created_at, a.featured_at,
           u.username AS seller_name,
           (SELECT COUNT(*) FROM auction_bids b WHERE b.auction_id = a.id) AS bid_count
    FROM auctions a
    LEFT JOIN users u ON a.seller_id = u.id
    $whereSql
    ORDER BY (a.featured_at IS NOT NULL) DESC, a.created_at DESC
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
<?php if ($flash !== ''): ?>
<div class="admin-alert admin-alert-success" style="margin-bottom:16px;">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

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
                    <th>出价</th><th>开拍</th><th>落槌</th><th>状态</th><th>推荐</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $a): ?>
                <?php $s = $statusMap[$a['status']] ?? [$a['status'], 'default']; ?>
                <?php $isFeatured = !empty($a['featured_at']); ?>
                <?php $canFeature = in_array($a['status'], ['pending', 'active'], true); ?>
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
                    <td>
                        <?php if ($isFeatured): ?>
                            <span class="admin-badge warning" title="推荐时间：<?= htmlspecialchars($a['featured_at']) ?>"><i class="fas fa-star"></i> 已推荐</span>
                        <?php endif; ?>
                        <form method="post" action="auctions.php<?= $_GET ? '?' . htmlspecialchars(http_build_query($_GET)) : '' ?>" style="display:inline-block;margin:0;">
                            <input type="hidden" name="action" value="toggle_featured">
                            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <input type="hidden" name="on" value="<?= $isFeatured ? '0' : '1' ?>">
                            <?php if ($isFeatured): ?>
                                <button class="admin-btn admin-btn-sm admin-btn-secondary" type="submit" title="取消首页推荐"><i class="fas fa-star-half-alt"></i> 取消</button>
                            <?php elseif ($canFeature): ?>
                                <button class="admin-btn admin-btn-sm admin-btn-primary" type="submit" title="推荐到首页顶部"><i class="fas fa-star"></i> 设为推荐</button>
                            <?php else: ?>
                                <button class="admin-btn admin-btn-sm admin-btn-secondary" disabled title="已结束的拍卖不可推荐">不可推荐</button>
                            <?php endif; ?>
                        </form>
                    </td>
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