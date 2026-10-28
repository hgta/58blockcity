<?php
/**
 * 58拍卖 · 契约详情
 *
 * 落槌 = 契约成立。钱由买卖双方线下自行结算，平台不碰资金、不做币种折算。
 * 物权交割发生在「卖家确认收款」那一刻——契约作废则物品所有权从未转移。
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Auction.php';
require_once __DIR__ . '/../../classes/City.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lot_helpers.php';
checkLogin();

$auction = new Auction($pdo);
$userId  = intval($_SESSION['user_id']);

$auctionId = intval($_GET['id'] ?? 0);
if ($auctionId <= 0) {
    header('Location: /my.php');
    exit;
}

// POST 处理必须置于任何 HTML 输出之前
$opMsg = '';
$opErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $opErr = 'CSRF 令牌验证失败，请刷新页面重试';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'declare_paid') {
                $auction->declarePaid($auctionId, $userId);
                $opMsg = '已声明付款，等待卖家核实到账并确认。';
            } elseif ($action === 'confirm_paid') {
                $auction->confirmPaid($auctionId, $userId);
                $opMsg = '已确认收款，契约完成，物品所有权已交割给买家。';
            } else {
                $opErr = '未知操作';
            }
        } catch (Exception $e) {
            $opErr = $e->getMessage();
        }
    }
}

$c = $auction->getContract($auctionId);
if (!$c) {
    header('Location: /my.php');
    exit;
}

$isBuyer  = intval($c['current_bidder_id']) === $userId;
$isSeller = intval($c['seller_id']) === $userId;

// 非买卖双方：契约只对当事人可见，其余人回详情页
if (!$isBuyer && !$isSeller) {
    header('Location: /view.php?id=' . $auctionId);
    exit;
}

// 尚未落槌，或落槌发生在本 change 之前的存量记录（payment_status 为 NULL）：
// 都走详情页，不回溯补契约。
$stage = ac_contract_stage($c);
if (!$stage) {
    header('Location: /view.php?id=' . $auctionId);
    exit;
}

$price      = floatval($c['final_price'] ?: $c['current_price']);
$currency   = $c['currency'];
$expireAt   = !empty($c['contract_expire_at']) ? strtotime($c['contract_expire_at']) : 0;
$expired    = $expireAt > 0 && $expireAt < time();
$img        = ac_auction_img($c);
$crossUrl   = ac_item_cross_url($c);
$shopId     = intval($c['item_shop_id'] ?? 0);
$shopName   = $c['item_shop_name'] ?? '';

// 人气值契约的收款城市（落槌时已定，取自 auctions.accept_cities）
$acceptCityNames = [];
if ($currency === 'popularity') {
    $ids = json_decode($c['accept_cities'] ?? '[]', true);
    $ids = is_array($ids) ? array_map('intval', $ids) : [];
    if (!empty($ids)) {
        $city = new City($pdo);
        foreach ($city->getAllCities() as $ct) {
            if (in_array(intval($ct['id']), $ids, true)) $acceptCityNames[] = $ct['name'];
        }
    }
}

$site_config['title'] = '契约 ' . ac_lot_no($auctionId) . ' - 58拍卖';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.ct-wrap { max-width: 760px; margin: 24px auto; padding: 0 15px; }
.ct-card { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px; box-shadow: var(--shadow); margin-bottom: 16px; }
.ct-h { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; font-size: 15px; font-weight: 700; }
.ct-h i { color: var(--brand); }
.ct-item { display: flex; gap: 14px; align-items: center; }
.ct-thumb { width: 72px; height: 72px; border-radius: 10px; overflow: hidden; background: var(--surface-2); flex-shrink: 0; }
.ct-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ct-title { font-size: 15px; font-weight: 600; color: var(--text); text-decoration: none; }
.ct-title:hover { color: var(--brand); }
.ct-meta { font-size: 12px; color: var(--muted); margin-top: 4px; }
.ct-price { font-size: 26px; font-weight: 800; color: var(--brand); font-variant-numeric: tabular-nums; }
.ct-price-lbl { font-size: 12px; color: var(--muted); }
.ct-row { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px dashed var(--line); font-size: 13px; }
.ct-row:last-child { border-bottom: none; }
.ct-row .k { color: var(--muted); }
.ct-row .v { color: var(--text); text-align: right; }

/* 履约进度条 */
.ac-ct-bar { display: flex; align-items: flex-start; gap: 0; }
.ac-ct-node { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 0 0 auto; width: 96px; text-align: center; }
.ac-ct-dot { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--surface-2); border: 1px solid var(--line); color: var(--muted); font-size: 14px; }
.ac-ct-label { font-size: 11px; color: var(--muted); line-height: 1.35; }
.ac-ct-line { flex: 1 1 auto; height: 2px; background: var(--line); margin-top: 17px; }
.ac-ct-line.is-done { background: var(--brand); }
.ac-ct-node.is-done .ac-ct-dot { background: var(--brand); border-color: var(--brand); color: #fff; }
.ac-ct-node.is-done .ac-ct-label { color: var(--text); }
.ac-ct-node.is-active .ac-ct-dot { border-color: var(--brand); color: var(--brand); box-shadow: 0 0 0 3px var(--brand-dim); }
.ac-ct-node.is-active .ac-ct-label { color: var(--text); font-weight: 600; }
.ac-ct-bar.is-void .ac-ct-node.is-void { width: auto; }
.ac-ct-bar.is-void .ac-ct-dot { color: var(--muted); }
.ac-ct-bar.is-void .ac-ct-label { font-size: 13px; }

.ct-guide { font-size: 13px; color: var(--text); line-height: 1.75; }
.ct-guide p { margin: 0 0 10px; }
.ct-cities { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0 10px; }
.ct-city { padding: 3px 10px; border-radius: 999px; background: var(--surface-2); border: 1px solid var(--line); font-size: 12px; }
.ct-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.ct-note { font-size: 12px; color: var(--muted); line-height: 1.7; }
.ct-alert { padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 14px; }
.ct-alert.ok { background: var(--brand-dim); color: var(--brand); }
.ct-alert.err { background: rgba(231,76,60,.12); color: #e74c3c; }
.ct-alert.warn { background: rgba(243,156,18,.12); color: #f39c12; }
@media (max-width: 520px) {
    .ac-ct-node { width: 76px; }
    .ct-price { font-size: 22px; }
}
</style>

<div class="ct-wrap">
    <?php if ($opMsg): ?><div class="ct-alert ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($opMsg) ?></div><?php endif; ?>
    <?php if ($opErr): ?><div class="ct-alert err"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars($opErr) ?></div><?php endif; ?>

    <div class="ct-card">
        <div class="ct-h"><i class="fas fa-file-signature"></i> 契约 <?= ac_lot_no($auctionId) ?></div>

        <div class="ct-item">
            <div class="ct-thumb">
                <?php if ($img): ?><img src="<?= htmlspecialchars($img) ?>" alt="" onerror="this.onerror=null;this.style.display='none'"><?php endif; ?>
            </div>
            <div style="flex:1 1 auto;min-width:0;">
                <?php if ($crossUrl): ?>
                    <a class="ct-title" href="<?= htmlspecialchars($crossUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($c['item_title'] ?? ('拍品 #' . $auctionId)) ?></a>
                <?php else: ?>
                    <div class="ct-title"><?= htmlspecialchars($c['item_title'] ?? ('拍品 #' . $auctionId)) ?></div>
                <?php endif; ?>
                <div class="ct-meta">
                    <?= htmlspecialchars(ac_item_type_label($c['item_type'])) ?>
                    · 落槌 <?= htmlspecialchars(date('Y-m-d H:i', strtotime($c['sold_at'] ?: $c['updated_at']))) ?>
                </div>
            </div>
            <div style="text-align:right;flex:0 0 auto;">
                <div class="ct-price-lbl">成交价</div>
                <div class="ct-price"><?= htmlspecialchars(ac_money($price, $currency)) ?></div>
            </div>
        </div>
    </div>

    <div class="ct-card">
        <div class="ct-h"><i class="fas fa-list-check"></i> 履约进度</div>
        <?php ac_render_contract_progress($c); ?>
    </div>

    <div class="ct-card">
        <div class="ct-h"><i class="fas fa-circle-info"></i> 契约信息</div>
        <div class="ct-row">
            <span class="k">卖家</span>
            <span class="v"><?= htmlspecialchars($c['seller_name'] ?? ('用户' . intval($c['seller_id']))) ?><?= $isSeller ? '（我）' : '' ?></span>
        </div>
        <div class="ct-row">
            <span class="k">买家</span>
            <span class="v"><?= htmlspecialchars($c['winner_name'] ?? ($c['buyer_name'] ?? ('用户' . intval($c['current_bidder_id'])))) ?><?= $isBuyer ? '（我）' : '' ?></span>
        </div>
        <div class="ct-row">
            <span class="k">结算币种</span>
            <span class="v"><?= $currency === 'cny' ? '人民币（¥）' : '城市人气值（Ⓟ）' ?></span>
        </div>
        <?php if ($expireAt > 0): ?>
        <div class="ct-row">
            <span class="k">付款期限</span>
            <span class="v"><?= htmlspecialchars(date('Y-m-d H:i', $expireAt)) ?><?= $expired ? '（已逾期）' : '' ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($c['payment_declared_at'])): ?>
        <div class="ct-row">
            <span class="k">买家声明付款</span>
            <span class="v"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($c['payment_declared_at']))) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($c['settled_at'])): ?>
        <div class="ct-row">
            <span class="k">交割完成</span>
            <span class="v"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($c['settled_at']))) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($c['item_type'] === 'product' && $shopId > 0): ?>
        <div class="ct-row">
            <span class="k">发货</span>
            <span class="v">
                卖家确认收款后由商城订单流转
                <a href="https://mall.58.tl/shop/view.php?id=<?= $shopId ?>" target="_blank" rel="noopener" style="color:var(--brand);">
                    <?= $shopName ? htmlspecialchars($shopName) : '前往店铺' ?> →
                </a>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($stage['key'] === 'unpaid' || $stage['key'] === 'buyer_declared'): ?>
    <div class="ct-card">
        <div class="ct-h"><i class="fas fa-hand-holding-dollar"></i> 付款方式</div>
        <div class="ct-guide">
            <?php if ($currency === 'cny'): ?>
                <p>本契约以<strong>人民币</strong>结算。请直接联系卖家协商转账方式（微信 / 支付宝 / 银行卡等）。</p>
                <p><strong>平台不代收、不托管、不做币种折算</strong>——转账请务必与卖家本人核对账号。</p>
                <div class="ct-actions">
                    <?php if ($c['item_type'] === 'product' && $shopId > 0): ?>
                        <a class="ac-btn ac-btn-ghost" style="padding:9px 16px;font-size:13px;" href="https://mall.58.tl/shop/view.php?id=<?= $shopId ?>" target="_blank" rel="noopener">
                            <i class="fas fa-store"></i> <?= $shopName ? '前往「' . htmlspecialchars($shopName) . '」' : '前往店铺' ?>
                        </a>
                    <?php endif; ?>
                    <a class="ac-btn ac-btn-ghost" style="padding:9px 16px;font-size:13px;" href="/user/messages.php">
                        <i class="fas fa-comment-dots"></i> 联系卖家
                    </a>
                </div>
            <?php else: ?>
                <p>本契约以<strong>城市人气值</strong>结算。请向卖家在<?= empty($acceptCityNames) ? '任意' : '以下' ?>城市的人气值账户转账：</p>
                <?php if (!empty($acceptCityNames)): ?>
                    <div class="ct-cities">
                        <?php foreach ($acceptCityNames as $nm): ?><span class="ct-city"><?= htmlspecialchars($nm) ?></span><?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="ct-note">卖家未限定城市，接受全部城市的人气值。</p>
                <?php endif; ?>
                <p><strong>平台不代收、不托管</strong>——请与卖家核对收款账户后再转。</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($stage['key'] === 'unpaid' && $isBuyer): ?>
        <?php if ($expired): ?>
            <div class="ct-alert warn">
                <i class="fas fa-clock"></i> 已超过付款期限，契约将被作废。若已完成转账，请尽快联系卖家重新确认。
            </div>
        <?php else: ?>
            <div class="ct-card">
                <div class="ct-h"><i class="fas fa-money-bill-wave"></i> 我是买家</div>
                <p class="ct-note" style="margin-bottom:12px;">
                    完成转账后点击下方按钮。卖家会在核实到账后确认收款，届时物品所有权才交割给你。
                </p>
                <form method="POST" onsubmit="return confirm('请确认你已完成转账。虚假声明将导致契约作废。');">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="declare_paid">
                    <button type="submit" class="ac-btn ac-btn-primary" style="padding:11px 22px;">
                        <i class="fas fa-circle-check"></i> 我已付款
                    </button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($stage['key'] === 'buyer_declared' && $isSeller): ?>
        <div class="ct-card">
            <div class="ct-h"><i class="fas fa-handshake"></i> 我是卖家</div>
            <p class="ct-note" style="margin-bottom:12px;">
                买家已声明付款。<strong>请务必先核实款项确实到账</strong>，再点击确认——
                确认后物权立即交割<?= $c['item_type'] === 'product' ? '，商城订单同步转为已付款并进入发货流程' : '给买家' ?>，此操作不可撤销。
            </p>
            <form method="POST" onsubmit="return confirm('请确认款项已到账。确认后物权立即交割，且不可撤销。');">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="confirm_paid">
                <button type="submit" class="ac-btn ac-btn-primary" style="padding:11px 22px;">
                    <i class="fas fa-handshake"></i> 确认收到款项
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($stage['key'] === 'buyer_declared' && $isBuyer): ?>
        <div class="ct-alert ok">
            <i class="fas fa-hourglass-half"></i> 已声明付款，等待卖家核实到账后确认。
        </div>
    <?php endif; ?>

    <?php if ($stage['key'] === 'unpaid' && $isSeller): ?>
        <div class="ct-alert ok">
            <i class="fas fa-hourglass-half"></i> 契约已成立，等待买家付款。请在收到款项后再确认，不要提前确认。
        </div>
    <?php endif; ?>

    <?php if ($stage['key'] === 'settled'): ?>
        <div class="ct-alert ok">
            <i class="fas fa-handshake"></i> 契约已完成，物品所有权已交割给买家。
        </div>
    <?php endif; ?>

    <div class="ct-card">
        <div class="ct-h"><i class="fas fa-scale-balanced"></i> 契约条款</div>
        <div class="ct-note">
            <p style="margin-bottom:8px;">1. 落槌即视为买卖双方之间成立交易契约，成交价与币种以本页记录为准，任何一方不得单方变更。</p>
            <p style="margin-bottom:8px;">2. 付款由买卖双方自行完成，平台不代收、不托管、不提供资金担保，亦不介入币种折算。</p>
            <p style="margin-bottom:8px;">3. 物品所有权在卖家确认收款的那一刻交割。契约作废的，所有权自始未发生转移。</p>
            <p>4. 买家须在落槌后 7 天内声明付款；逾期未声明的契约自动作废，物品解除占用。</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
