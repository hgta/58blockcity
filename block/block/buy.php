<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';

// 未登录 → 跳转登录并保留返回地址
if (!isLoggedIn()) {
    header("Location: ../../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

require_once '../../classes/Block.php';
require_once '../../classes/BlockListing.php';
require_once '../../classes/UserPopularity.php';

$block = new Block($pdo);
$listing = new BlockListing($pdo);
$userPop = new UserPopularity($pdo);

$userId = $_SESSION['user_id'];
$listingId = intval($_GET['listing'] ?? $_POST['listing_id'] ?? 0);

$detail = $listing->getListingDetail($listingId);
if (!$detail) {
    header("Location: ../sale_list.php");
    exit();
}

$isSeller = $detail['seller_id'] == $userId;
$isBuyer  = !empty($detail['buyer_id']) && $detail['buyer_id'] == $userId;

// 卖家直接进入确认页
if ($isSeller && $detail['status'] === 'pending') {
    header("Location: confirm_sale.php?listing=" . $listingId);
    exit();
}
// 已成交
if ($detail['status'] === 'completed') {
    header("Location: view.php?id=" . $detail['block_id']);
    exit();
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action_order'])) {
            if ($isSeller) throw new Exception('不能购买自己的区块');
            if ($listing->placeOrder($listingId, $userId)) {
                $detail = $listing->getListingDetail($listingId);
                $isBuyer = true;
                $msg = '已提交购买意向，请按下方指引完成支付';
            } else {
                $err = '下单失败，该区块可能已被购买';
            }
        } elseif (isset($_POST['action_paid'])) {
            if ($listing->markPaid($listingId, $userId)) {
                $msg = '已通知卖家，请等待卖家确认完成交易';
            } else {
                $err = '操作失败，请确认订单状态';
            }
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}

// 买家在该城市的人气值（提示）
$buyerPop = $userPop->getUserPopularity($userId, $detail['city_name'] ?? '');
$detail['is_merged'] = !empty($detail['merged_block_id']);
?>
<?php require_once '../includes/header.php'; ?>

<style>
.buy-wrap { max-width:640px; margin:30px auto; padding:0 15px; }
.buy-card { background:#fff; border-radius:12px; padding:28px; box-shadow:0 4px 20px rgba(0,0,0,.08); }
.buy-card h2 { font-size:20px; margin-bottom:18px; color:#222; }
.buy-skin { height:120px; border-radius:10px; overflow:hidden; background:#f3f4f6; display:flex; align-items:center; justify-content:center; margin-bottom:18px; color:#bbb; font-size:30px; }
.buy-skin img { width:100%; height:100%; object-fit:cover; }
.buy-skin.txt { color:#fff; font-weight:bold; font-size:20px; text-align:center; padding:10px; }
.info-row { display:flex; justify-content:space-between; padding:11px 0; border-bottom:1px solid #f2f2f2; font-size:14px; }
.info-label { color:#888; }
.price-big { font-size:30px; font-weight:bold; text-align:center; margin:18px 0; color:<?= $detail['currency']==='popularity' ? '#e74c3c' : '#ff6b00' ?>; }
.contact-box { background:#f8fafc; border-radius:8px; padding:12px 14px; font-size:14px; color:#555; margin:14px 0; }
.btn-buy { width:100%; padding:15px; background:#ff6b00; color:#fff; border:none; border-radius:8px; font-size:17px; cursor:pointer; font-weight:bold; }
.btn-buy:hover { background:#e05d00; }
.btn-pay { width:100%; padding:15px; background:#e74c3c; color:#fff; border:none; border-radius:8px; font-size:17px; cursor:pointer; font-weight:bold; }
.btn-pay:hover { background:#c0392b; }
.guide { background:linear-gradient(135deg,#1e3a5f,#2563eb); color:#fff; border-radius:12px; padding:20px; margin:16px 0; }
.guide h3 { margin:0 0 8px; font-size:16px; }
.guide p { margin:0 0 12px; opacity:.92; font-size:13px; line-height:1.6; }
.guide .row { display:flex; justify-content:space-between; align-items:center; background:rgba(255,255,255,.15); border-radius:8px; padding:12px; }
.guide .amt { font-size:22px; font-weight:bold; color:#f59e0b; }
.steps { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-top:12px; }
.step { text-align:center; background:rgba(255,255,255,.1); border-radius:8px; padding:10px; font-size:12px; }
.alert { padding:12px 14px; border-radius:8px; margin-bottom:14px; font-size:14px; }
.alert-ok { background:#d4edda; color:#155724; }
.alert-err { background:#f8d7da; color:#721c24; }
</style>

<div class="buy-wrap">
    <div class="buy-card">
        <h2><i class="fas fa-shopping-cart"></i> 购买区块</h2>

        <?php if ($msg): ?><div class="alert alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <?php
        $dt = $detail['display_type'] ?? 'none';
        if ($dt === 'image' && !empty($detail['display_image'])):
        ?>
            <div class="buy-skin"><img src="/<?= htmlspecialchars($detail['display_image']) ?>" alt=""></div>
        <?php elseif ($dt === 'text' && !empty($detail['display_text'])):
            $c = ['red'=>'#ff6060','green'=>'#35cc2d','blue'=>'#337be6'][$detail['display_color'] ?? 'blue'] ?? '#337be6';
        ?>
            <div class="buy-skin txt" style="background:<?= $c ?>"><?= htmlspecialchars($detail['display_text']) ?></div>
        <?php else: ?>
            <div class="buy-skin"><i class="fas fa-map-marker-alt"></i></div>
        <?php endif; ?>

        <div class="info-row">
            <span class="info-label">区块</span>
            <span>
                <?= htmlspecialchars($detail['city_name']) ?>
                <?php if (!empty($detail['is_merged'])): ?>
                    <?= $detail['zone'] ?>区 · <?= $detail['merged_size'] ?> 合并区块（编号 <?= $detail['merged_min_number'] ?>）
                <?php else: ?>
                    <?= $detail['zone'] ?>区 #<?= $detail['block_number'] ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">卖家</span>
            <span><?= htmlspecialchars($detail['seller_name'] ?? '匿名') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">计价货币</span>
            <span><?= $detail['currency']==='popularity' ? '人气值 Ⓟ' : '人民币 ¥' ?></span>
        </div>
        <?php if ($detail['currency']==='popularity'): ?>
        <div class="info-row">
            <span class="info-label">我的该城人气值</span>
            <span><?= $buyerPop ?> Ⓟ</span>
        </div>
        <?php endif; ?>
        <?php if (!empty($detail['contact_phone']) || !empty($detail['contact_wechat'])): ?>
        <div class="contact-box">
            卖家联系方式：
            <?= !empty($detail['contact_phone']) ? '电话 ' . htmlspecialchars($detail['contact_phone']) : '' ?>
            <?= !empty($detail['contact_wechat']) ? ' / 微信 ' . htmlspecialchars($detail['contact_wechat']) : '' ?>
        </div>
        <?php endif; ?>

        <div class="price-big">
            <?= $detail['currency']==='popularity' ? 'Ⓟ ' : '¥ ' ?><?= number_format($detail['price'], 2) ?>
        </div>

        <?php if ($detail['status'] === 'listed' && !$isSeller): ?>
            <form method="POST">
                <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                <button type="submit" name="action_order" class="btn-buy" onclick="return confirm('确认以 <?= $detail['currency']==='popularity'?'Ⓟ ':'' ?><?= number_format($detail['price'],2) ?> 购买此区块？下单后需完成支付并经卖家确认')">
                    立即购买
                </button>
            </form>
        <?php elseif ($detail['status'] === 'pending' && $isBuyer): ?>
            <?php if ($detail['currency'] === 'popularity'): ?>
                <!-- 人气值：同商城，离线转账 + 确认 -->
                <div class="guide">
                    <h3><i class="fas fa-info-circle"></i> BCT 人气值支付引导</h3>
                    <p>请在 <strong>blockcity.vip</strong> 用 BCT 人气值向<strong>卖家收款区块</strong>完成转账后，点击下方"我已支付"通知卖家确认。</p>
                    <div class="row">
                        <div>
                            <div style="font-size:12px;opacity:.85;">应付金额</div>
                            <div class="amt">Ⓟ <?= number_format($detail['price'], 0) ?></div>
                        </div>
                        <div style="text-align:right;font-size:12px;opacity:.9;">
                            卖家：<?= htmlspecialchars($detail['seller_name'] ?? '') ?>
                        </div>
                    </div>
                    <div class="steps">
                        <div class="step"><div>1</div><div>登录 blockcity.vip</div></div>
                        <div class="step"><div>2</div><div>向收款区块转账</div></div>
                        <div class="step"><div>3</div><div>返回点"我已支付"</div></div>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                    <button type="submit" name="action_paid" class="btn-pay" onclick="return confirm('请确认您已在 blockcity.vip 完成转账支付？')">
                        我已支付，通知卖家
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-ok">
                    已提交购买意向（人民币 ¥<?= number_format($detail['price'],2) ?>）。请通过卖家联系方式线下完成付款，并等待卖家确认后交易完成。
                </div>
            <?php endif; ?>
        <?php elseif ($detail['status'] === 'pending'): ?>
            <div class="alert alert-ok">该区块已有买家下单，正在交易中。</div>
        <?php endif; ?>

        <div style="text-align:center;margin-top:18px;">
            <a href="../sale_list.php" style="color:#3498db;">← 返回出售市场</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
