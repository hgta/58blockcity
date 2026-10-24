<?php
/**
 * 58拍卖子站 — 拍卖单详情（后台管理）
 *
 * 解决运营侧痛点：列表只有概要，前端出于隐私不展示出价人身份，
 * 因此后台需要一个能查看「具体出价人 + 联系方式 + 完整流水」的管理页。
 *
 * 页面内容：拍品信息 / 拍卖参数 / 卖家 / 得标者 / 竞拍人汇总 / 出价流水 / 关联订单
 * 管理操作：推荐位开关、取消拍卖（复用 Auction 类，保留既有状态校验）
 */

require_once '../../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/lot_helpers.php'; // ac_seller_description_text（描述摘要）
require_once '../../classes/Auction.php';

// 鉴权必须早于任何 HTML 输出 —— POST 处理需要 header() 重定向
require_once '../../shared/admin/admin-auth.php';
$site = 'bid';
checkAdminAccess($site);

$auction = new Auction($pdo);
$auction->tick(); // 惰性推进状态机，保证详情状态/成交信息准确

$auctionId = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if ($auctionId <= 0) {
    header('Location: auctions.php');
    exit;
}

// ---- POST 处理：推荐开关 / 取消拍卖（先完成再重定向，避免刷新重复提交） ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $flash     = '';
    $flashType = 'success';

    if ($action === 'toggle_featured') {
        $on = ($_POST['on'] ?? '') === '1';
        $ok = $auction->setFeatured($auctionId, $on);
        $flash     = $ok ? ($on ? '已推荐到首页顶部' : '已取消推荐') : '操作失败：该拍卖已结束/被取消';
        $flashType = $ok ? 'success' : 'danger';
    } elseif ($action === 'cancel_auction') {
        $tmp = $auction->getAuctionById($auctionId);
        if (!$tmp) {
            $flash = '拍卖不存在';
            $flashType = 'danger';
        } else {
            // 管理员代卖家操作：函数内部仍会校验「已结束 / 已有人出价」等条件
            $r = $auction->cancelAuction($auctionId, intval($tmp['seller_id']));
            $flash     = $r['msg'] ?? '操作完成';
            $flashType = !empty($r['ok']) ? 'success' : 'danger';
        }
    }

    header('Location: auction-detail.php?id=' . $auctionId
        . '&msg=' . urlencode($flash) . '&t=' . ($flashType === 'danger' ? 'danger' : 'success'));
    exit;
}

$flash     = !empty($_GET['msg']) ? (string)$_GET['msg'] : '';
$flashType = ($_GET['t'] ?? '') === 'danger' ? 'danger' : 'success';

$a = $auction->getAuctionById($auctionId);

$admin_site_config = [
    'site'        => 'bid',
    'page_title'  => $a ? ('拍卖单详情 · LOT ' . str_pad((string)$auctionId, 3, '0', STR_PAD_LEFT)) : '拍卖单详情',
    'menu_active' => 'auctions.php', // 详情页非菜单项，保持「拍卖单管理」高亮
];
require_once '../../shared/admin/admin-header.php';

// ---- 数据准备 ----
function bid_admin_money($amount, $currency) {
    return $currency === 'popularity'
        ? number_format((float)$amount) . ' 人气值'
        : '¥' . number_format((float)$amount, 2);
}
function bid_admin_dt($v) {
    return $v ? date('Y-m-d H:i', strtotime($v)) : '—';
}
function bid_admin_phone($phone) {
    $phone = trim((string)$phone);
    return $phone !== '' ? $phone : '<span style="color:var(--admin-text-muted);">未绑定</span>';
}
function bid_admin_user_badge($status) {
    $map = [
        'active'   => ['正常', 'success'],
        'inactive' => ['禁用', 'danger'],
        'unknown'  => ['账号缺失', 'default'],
    ];
    $s = $map[$status] ?? [$status ?: '未知', 'default'];
    return '<span class="admin-badge ' . $s[1] . '">' . htmlspecialchars($s[0]) . '</span>';
}

if (!$a) {
    echo '<div class="admin-card"><div class="admin-empty-state"><i class="fas fa-search"></i><h4>拍卖单不存在或已被删除</h4>'
        . '<a class="admin-btn admin-btn-sm admin-btn-secondary" href="auctions.php" style="margin-top:12px;">返回列表</a></div></div>';
    require_once '../../shared/admin/admin-footer.php';
    exit;
}

$statusMap = [
    'pending'  => ['即将开拍', 'warning'],
    'active'   => ['竞拍中',   'success'],
    'sold'     => ['已落槌',   'info'],
    'ended'    => ['已流拍',   'danger'],
    'canceled' => ['已取消',   'default'],
];
$s = $statusMap[$a['status']] ?? [$a['status'], 'default'];

$typeLabel = ['block' => '区块', 'nft' => 'NFT头像', 'product' => '商城商品'][$a['item_type']] ?? $a['item_type'];
$lotNo     = 'LOT ' . str_pad((string)$auctionId, 3, '0', STR_PAD_LEFT);
$isFeatured = !empty($a['featured_at']);
$canFeature = in_array($a['status'], ['pending', 'active'], true);
$canCancel  = $a['status'] === 'pending'
    || ($a['status'] === 'active' && empty($a['current_bidder_id']));

// 卖家 / 得标者 / 当前领先者
$userStmt = $pdo->prepare("SELECT id, username, email, phone, city, avatar, status, created_at, last_login FROM users WHERE id = ?");
$userStmt->execute([intval($a['seller_id'])]);
$seller = $userStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$winnerId = intval($a['current_bidder_id'] ?? 0);
$winner   = null;
if ($winnerId > 0) {
    $userStmt->execute([$winnerId]);
    $winner = $userStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// 全部出价流水（含出价人联系方式 —— 后台专属，前端按隐私规则脱敏）
$bidStmt = $pdo->prepare("
    SELECT b.id, b.bidder_id, b.amount, b.created_at, b.prev_bidder_id,
           u.username, u.email, u.phone, u.status AS user_status
    FROM auction_bids b
    LEFT JOIN users u ON b.bidder_id = u.id
    WHERE b.auction_id = ?
    ORDER BY b.created_at ASC, b.id ASC");
$bidStmt->execute([$auctionId]);
$bids = $bidStmt->fetchAll(PDO::FETCH_ASSOC);

// 竞拍人汇总（按最高出价排序）
$bidderStmt = $pdo->prepare("
    SELECT b.bidder_id, u.username, u.email, u.phone, u.status AS user_status,
           COUNT(*) AS bid_cnt, MAX(b.amount) AS max_amount,
           MIN(b.created_at) AS first_at, MAX(b.created_at) AS last_at
    FROM auction_bids b
    LEFT JOIN users u ON b.bidder_id = u.id
    WHERE b.auction_id = ?
    GROUP BY b.bidder_id
    ORDER BY max_amount DESC");
$bidderStmt->execute([$auctionId]);
$bidders = $bidderStmt->fetchAll(PDO::FETCH_ASSOC);

// 商品拍品：补充商品/店铺实时信息
$product = null;
if ($a['item_type'] === 'product') {
    $pStmt = $pdo->prepare("
        SELECT p.id, p.name, p.main_image, p.price_bct, p.price_cny, p.stock, p.sold_count,
               p.status, p.shop_id, s.shop_name, s.user_id AS shop_owner_id
        FROM products p
        LEFT JOIN shops s ON p.shop_id = s.id
        WHERE p.id = ?");
    $pStmt->execute([intval($a['item_id'])]);
    $product = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// 商品拍卖成交后自动建单：订单备注固定为「拍卖 LOT 0xx 成交」
$order = null;
if ($a['status'] === 'sold' && $a['item_type'] === 'product') {
    $oStmt = $pdo->prepare("SELECT * FROM orders WHERE buyer_note = ? ORDER BY id DESC LIMIT 1");
    $oStmt->execute(['拍卖 ' . $lotNo . ' 成交']);
    $order = $oStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$descText = ac_seller_description_text($a, 300); // 复用拍品描述纯文本摘要
$imgUrl   = !empty($a['item_image']) ? $a['item_image'] : '';
?>

<?php if ($flash !== ''): ?>
<div class="admin-alert <?= $flashType === 'danger' ? 'danger' : 'success' ?>" style="margin-bottom:16px;">
    <i class="fas <?= $flashType === 'danger' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<!-- 顶部：返回 / 状态 / 操作 -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <a class="admin-btn admin-btn-sm admin-btn-secondary" href="auctions.php">
        <i class="fas fa-arrow-left"></i> 返回拍卖单列表
    </a>
    <span class="admin-badge <?= $s[1] ?>"><?= $s[0] ?></span>
    <?php if ($isFeatured): ?>
        <span class="admin-badge warning"><i class="fas fa-star"></i> 首页推荐中</span>
    <?php endif; ?>
    <span style="font-size:12.5px;color:var(--admin-text-muted);">创建于 <?= bid_admin_dt($a['created_at']) ?></span>

    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
        <a class="admin-btn admin-btn-sm admin-btn-secondary" href="../view.php?id=<?= $auctionId ?>" target="_blank" rel="noopener">
            <i class="fas fa-external-link-alt"></i> 前台预览
        </a>

        <?php if ($canFeature || $isFeatured): ?>
        <form method="post" style="margin:0;">
            <input type="hidden" name="id" value="<?= $auctionId ?>">
            <input type="hidden" name="action" value="toggle_featured">
            <input type="hidden" name="on" value="<?= $isFeatured ? '0' : '1' ?>">
            <?php if ($isFeatured): ?>
                <button class="admin-btn admin-btn-sm admin-btn-secondary" type="submit"><i class="fas fa-star-half-alt"></i> 取消推荐</button>
            <?php else: ?>
                <button class="admin-btn admin-btn-sm admin-btn-primary" type="submit"><i class="fas fa-star"></i> 设为推荐</button>
            <?php endif; ?>
        </form>
        <?php endif; ?>

        <?php if ($canCancel): ?>
        <form method="post" style="margin:0;" onsubmit="return confirm('确定取消该拍卖？\n\n仅「未开拍」或「已开拍但无人出价」可取消，取消后拍品解锁。');">
            <input type="hidden" name="id" value="<?= $auctionId ?>">
            <input type="hidden" name="action" value="cancel_auction">
            <button class="admin-btn admin-btn-sm admin-btn-danger" type="submit"><i class="fas fa-ban"></i> 取消拍卖</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- 拍品信息 + 拍卖参数 -->
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
    <div class="admin-card" style="flex:1 1 420px;margin:0;">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-box" style="margin-right:8px;color:var(--admin-accent);"></i>拍品信息</span>
        </div>
        <div class="admin-card-body">
            <div style="display:flex;gap:14px;align-items:flex-start;">
                <?php if ($imgUrl !== ''): ?>
                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:10px;border:1px solid var(--admin-border);flex:none;"
                     onerror="this.style.display='none'">
                <?php else: ?>
                <div style="width:96px;height:96px;border-radius:10px;border:1px solid var(--admin-border);display:flex;align-items:center;justify-content:center;color:var(--admin-text-muted);flex:none;">
                    <i class="fas fa-image" style="font-size:24px;"></i>
                </div>
                <?php endif; ?>
                <div style="min-width:0;">
                    <div style="font-size:15px;font-weight:700;line-height:1.4;margin-bottom:6px;">
                        <?= htmlspecialchars($a['item_title'] ?? ($typeLabel . ' #' . $a['item_id'])) ?>
                    </div>
                    <div style="font-size:12.5px;color:var(--admin-text-muted);line-height:1.9;">
                        <div>类型：<span class="admin-badge info"><?= htmlspecialchars($typeLabel) ?></span>
                             <span style="margin-left:6px;"><?= htmlspecialchars(strtoupper($a['item_type'])) ?> #<?= (int)$a['item_id'] ?></span>
                        </div>
                        <?php if ($product): ?>
                        <div>所属店铺：<?= htmlspecialchars($product['shop_name'] ?? ('店铺#' . $product['shop_id'])) ?>
                            <span style="margin-left:6px;">（店主 ID <?= (int)$product['shop_owner_id'] ?>）</span>
                        </div>
                        <div>商品状态：<?= $product['status'] === 'active' ? '上架中' : htmlspecialchars($product['status']) ?>
                             · 库存 <?= (int)$product['stock'] ?> · 已售 <?= (int)$product['sold_count'] ?>
                             · 标价 Ⓟ <?= number_format((float)$product['price_bct'], 0) ?> / ¥ <?= number_format((float)$product['price_cny'], 2) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($descText !== ''): ?>
            <div style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--admin-border);">
                <div style="font-size:12px;color:var(--admin-text-muted);margin-bottom:6px;">卖家描述（摘要）</div>
                <div style="font-size:13px;line-height:1.75;"><?= htmlspecialchars($descText) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card" style="flex:1 1 360px;margin:0;">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-sliders-h" style="margin-right:8px;color:var(--admin-accent);"></i>拍卖参数与时间</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <table class="admin-data-table">
                <tbody>
                    <tr><td style="color:var(--admin-text-muted);width:42%;">起拍价</td><td><?= bid_admin_money($a['start_price'], $a['currency']) ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">底价</td><td><?= $a['reserve_price'] !== null ? bid_admin_money($a['reserve_price'], $a['currency']) : '<span style="color:var(--admin-text-muted);">无底价</span>' ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">加价幅度</td><td><?= bid_admin_money($a['bid_increment'], $a['currency']) ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">当前价</td><td style="font-weight:700;"><?= bid_admin_money($a['current_price'] ?? $a['start_price'], $a['currency']) ?></td></tr>
                    <?php if ($a['status'] === 'sold'): ?>
                    <tr><td style="color:var(--admin-text-muted);">成交价</td><td style="font-weight:700;color:#16a34a;"><?= bid_admin_money($a['final_price'] ?? $a['current_price'], $a['currency']) ?></td></tr>
                    <?php endif; ?>
                    <tr><td style="color:var(--admin-text-muted);">开拍时间</td><td><?= bid_admin_dt($a['start_time']) ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">计划落槌</td><td><?= bid_admin_dt($a['end_time']) ?></td></tr>
                    <?php if (!empty($a['sold_at'])): ?>
                    <tr><td style="color:var(--admin-text-muted);">实际落槌</td><td><?= bid_admin_dt($a['sold_at']) ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td style="color:var(--admin-text-muted);">自动顺延</td>
                        <td>
                            <?php
                            // 旧数据可能为 0/NULL，回落与前端一致的默认值
                            $extendWindow = intval($a['extend_window_seconds'] ?? 0) ?: 120;
                            $extendStep   = intval($a['auto_extend_seconds'] ?? 0) ?: 120;
                            $maxExtend    = intval($a['max_extend_times'] ?? 0) ?: 10;
                            ?>
                            已顺延 <strong><?= (int)$a['extend_count'] ?></strong> / <?= $maxExtend ?> 次
                            <span style="font-size:11.5px;color:var(--admin-text-muted);">（末 <?= intval($extendWindow / 60) ?> 分钟出价 +<?= intval($extendStep / 60) ?> 分钟）</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:var(--admin-text-muted);">统计</td>
                        <td>
                            出价 <strong><?= count($bids) ?></strong> 次 ·
                            竞拍 <strong><?= count($bidders) ?></strong> 人 ·
                            关注 <strong><?= (int)$a['watch_count'] ?></strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 卖家 / 得标者 -->
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
    <div class="admin-card" style="flex:1 1 320px;margin:0;">
        <div class="admin-card-header">
            <span class="admin-card-title"><i class="fas fa-user-tag" style="margin-right:8px;color:var(--admin-accent);"></i>卖家</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <table class="admin-data-table">
                <tbody>
                    <tr><td style="color:var(--admin-text-muted);width:34%;">用户名</td><td><strong><?= htmlspecialchars($seller['username'] ?? ('用户' . $a['seller_id'])) ?></strong> <?= $seller ? bid_admin_user_badge($seller['status']) : '' ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">用户 ID</td><td><?= (int)$a['seller_id'] ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">邮箱</td><td><?= htmlspecialchars($seller['email'] ?? '—') ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">手机</td><td><?= bid_admin_phone($seller['phone'] ?? '') ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">城市</td><td><?= htmlspecialchars($seller['city'] ?? '—') ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">注册 / 登录</td><td style="font-size:12.5px;"><?= bid_admin_dt($seller['created_at'] ?? null) ?> / <?= bid_admin_dt($seller['last_login'] ?? null) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card" style="flex:1 1 320px;margin:0;">
        <div class="admin-card-header">
            <span class="admin-card-title">
                <i class="fas fa-trophy" style="margin-right:8px;color:var(--admin-accent);"></i>
                <?= $a['status'] === 'sold' ? '得标者' : ($winnerId > 0 ? '当前领先者' : '得标者') ?>
            </span>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if ($winnerId <= 0): ?>
                <div class="admin-empty-state" style="padding:28px 16px;">
                    <i class="fas fa-user-slash"></i>
                    <h4>暂无出价人</h4>
                </div>
            <?php else: ?>
            <table class="admin-data-table">
                <tbody>
                    <tr><td style="color:var(--admin-text-muted);width:34%;">用户名</td><td><strong><?= htmlspecialchars($winner['username'] ?? ('用户' . $winnerId)) ?></strong> <?= $winner ? bid_admin_user_badge($winner['status']) : '' ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">用户 ID</td><td><?= $winnerId ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">邮箱</td><td><?= htmlspecialchars($winner['email'] ?? '—') ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">手机</td><td><?= bid_admin_phone($winner['phone'] ?? '') ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">城市</td><td><?= htmlspecialchars($winner['city'] ?? '—') ?></td></tr>
                    <tr><td style="color:var(--admin-text-muted);">注册 / 登录</td><td style="font-size:12.5px;"><?= bid_admin_dt($winner['created_at'] ?? null) ?> / <?= bid_admin_dt($winner['last_login'] ?? null) ?></td></tr>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 竞拍人汇总 -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-users" style="margin-right:8px;color:var(--admin-accent);"></i>竞拍人（<?= count($bidders) ?>）</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <?php if (empty($bidders)): ?>
            <div class="admin-empty-state"><i class="fas fa-user-clock"></i><h4>暂无竞拍人</h4></div>
        <?php else: ?>
        <table class="admin-data-table">
            <thead>
                <tr><th>竞拍人</th><th>用户 ID</th><th>邮箱</th><th>手机</th><th>出价笔数</th><th>最高出价</th><th>首次 / 末次出价</th><th>状态</th></tr>
            </thead>
            <tbody>
                <?php foreach ($bidders as $b): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($b['username'] ?? ('用户' . $b['bidder_id'])) ?></strong>
                        <?php if (intval($b['bidder_id']) === $winnerId): ?>
                            <span class="admin-badge success" style="margin-left:6px;"><i class="fas fa-crown"></i> 领先</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$b['bidder_id'] ?></td>
                    <td style="font-size:12.5px;"><?= htmlspecialchars($b['email'] ?? '—') ?></td>
                    <td style="font-size:12.5px;"><?= bid_admin_phone($b['phone'] ?? '') ?></td>
                    <td><?= (int)$b['bid_cnt'] ?></td>
                    <td style="font-weight:700;"><?= bid_admin_money($b['max_amount'], $a['currency']) ?></td>
                    <td style="font-size:12px;color:var(--admin-text-muted);"><?= bid_admin_dt($b['first_at']) ?><br><?= bid_admin_dt($b['last_at']) ?></td>
                    <td><?= bid_admin_user_badge($b['user_status'] ?? 'unknown') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- 出价流水 -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-gavel" style="margin-right:8px;color:var(--admin-accent);"></i>出价流水（<?= count($bids) ?>）</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <?php if (empty($bids)): ?>
            <div class="admin-empty-state"><i class="fas fa-hourglass-half"></i><h4>暂无出价记录</h4></div>
        <?php else: ?>
        <div style="max-height:520px;overflow:auto;">
        <table class="admin-data-table">
            <thead>
                <tr><th>口次</th><th>出价人</th><th>用户 ID</th><th>手机</th><th>金额</th><th>时间</th><th>状态</th></tr>
            </thead>
            <tbody>
                <?php foreach ($bids as $i => $b): ?>
                <?php $isLast = ($i === count($bids) - 1); ?>
                <tr<?= $isLast && intval($b['bidder_id']) === $winnerId ? ' style="background:rgba(22,163,74,.06);"' : '' ?>>
                    <td style="color:var(--admin-text-muted);"><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($b['username'] ?? ('用户' . $b['bidder_id'])) ?></strong></td>
                    <td><?= (int)$b['bidder_id'] ?></td>
                    <td style="font-size:12.5px;"><?= bid_admin_phone($b['phone'] ?? '') ?></td>
                    <td style="font-weight:700;"><?= bid_admin_money($b['amount'], $a['currency']) ?></td>
                    <td style="font-size:12.5px;"><?= bid_admin_dt($b['created_at']) ?></td>
                    <td>
                        <?php if (intval($b['bidder_id']) === $winnerId && $isLast): ?>
                            <span class="admin-badge success">当前最高</span>
                        <?php else: ?>
                            <span class="admin-badge default">已被超越</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 关联订单（商品拍卖成交自动建单） -->
<?php if ($a['item_type'] === 'product'): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title"><i class="fas fa-receipt" style="margin-right:8px;color:var(--admin-accent);"></i>关联订单</span>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <?php if (!$order): ?>
            <div class="admin-empty-state" style="padding:26px 16px;">
                <i class="fas fa-file-invoice"></i>
                <h4><?= $a['status'] === 'sold' ? '未找到关联订单' : '成交后自动生成订单' ?></h4>
            </div>
        <?php else: ?>
        <?php
        $orderStatusMap = [
            'pending'   => ['待支付', 'warning'],
            'paid'      => ['已支付', 'info'],
            'shipped'   => ['已发货', 'info'],
            'completed' => ['已完成', 'success'],
            'cancelled' => ['已取消', 'default'],
            'refunded'  => ['已退款', 'danger'],
        ];
        $os = $orderStatusMap[$order['status']] ?? [$order['status'], 'default'];
        ?>
        <table class="admin-data-table">
            <tbody>
                <tr><td style="color:var(--admin-text-muted);width:16%;">订单号</td><td><strong><?= htmlspecialchars($order['order_no']) ?></strong></td>
                    <td style="color:var(--admin-text-muted);width:14%;">状态</td><td><span class="admin-badge <?= $os[1] ?>"><?= $os[0] ?></span></td></tr>
                <tr><td style="color:var(--admin-text-muted);">买家</td><td><?= htmlspecialchars($winner['username'] ?? ('用户' . $order['user_id'])) ?>（ID <?= (int)$order['user_id'] ?>）</td>
                    <td style="color:var(--admin-text-muted);">金额</td><td style="font-weight:700;">¥<?= number_format((float)$order['total_amount'], 2) ?></td></tr>
                <tr><td style="color:var(--admin-text-muted);">下单时间</td><td><?= bid_admin_dt($order['created_at']) ?></td>
                    <td style="color:var(--admin-text-muted);">过期时间</td><td><?= bid_admin_dt($order['expire_at']) ?></td></tr>
                <tr><td style="color:var(--admin-text-muted);">收货地址</td><td colspan="3"><?= $order['shipping_address'] ? htmlspecialchars($order['shipping_address']) : '<span style="color:var(--admin-text-muted);">未填写（待买家支付时补全）</span>' ?></td></tr>
                <tr><td style="color:var(--admin-text-muted);">买家留言</td><td colspan="3"><?= htmlspecialchars($order['buyer_note'] ?? '—') ?></td></tr>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../shared/admin/admin-footer.php'; ?>
