<?php
require_once '../../config/database.php';
require_once '../includes/auth.php';
checkLogin();

require_once '../../classes/Block.php';
require_once '../../classes/BlockListing.php';
require_once '../../classes/User.php';

$block = new Block($pdo);
$listing = new BlockListing($pdo);
$user = new User($pdo);

$userId = $_SESSION['user_id'];
$listingId = intval($_GET['listing'] ?? $_POST['listing_id'] ?? 0);

$detail = $listing->getListingDetail($listingId);
if (!$detail || $detail['seller_id'] != $userId) {
    header("Location: ../user/dashboard.php");
    exit();
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action_confirm'])) {
            if ($listing->confirmSale($listingId, $userId)) {
                $msg = '交易已完成，区块所有权已转移给买家';
                $detail = $listing->getListingDetail($listingId);
            } else {
                $err = '确认失败，请刷新后重试';
            }
        } elseif (isset($_POST['action_cancel'])) {
            if ($listing->cancelListing($listingId, $userId)) {
                $msg = '已取消该交易';
                $detail = $listing->getListingDetail($listingId);
                // 取消后若已无有效挂牌，回到管理页
                if ($detail['status'] === 'canceled') {
                    header("Location: manage.php?id=" . $detail['block_id']);
                    exit();
                }
            } else {
                $err = '取消失败';
            }
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}

$buyerName = '';
if (!empty($detail['buyer_id'])) {
    $bu = $user->getUserById($detail['buyer_id']);
    $buyerName = $bu['username'] ?? '用户' . $detail['buyer_id'];
}
$detail['is_merged'] = !empty($detail['merged_block_id']);
?>
<?php require_once '../includes/header.php'; ?>

<style>
.cs-wrap { max-width:640px; margin:30px auto; padding:0 15px; }
.cs-card { background:#fff; border-radius:12px; padding:28px; box-shadow:0 4px 20px rgba(0,0,0,.08); }
.cs-card h2 { font-size:20px; margin-bottom:18px; color:#222; }
.cs-skin { height:110px; border-radius:10px; overflow:hidden; background:#f3f4f6; display:flex; align-items:center; justify-content:center; margin-bottom:18px; color:#bbb; font-size:28px; }
.cs-skin img { width:100%; height:100%; object-fit:cover; }
.cs-skin.txt { color:#fff; font-weight:bold; font-size:18px; text-align:center; padding:10px; }
.info-row { display:flex; justify-content:space-between; padding:11px 0; border-bottom:1px solid #f2f2f2; font-size:14px; }
.info-label { color:#888; }
.price-big { font-size:28px; font-weight:bold; text-align:center; margin:18px 0; color:<?= $detail['currency']==='popularity' ? '#e74c3c' : '#ff6b00' ?>; }
.alert { padding:12px 14px; border-radius:8px; margin-bottom:14px; font-size:14px; }
.alert-ok { background:#d4edda; color:#155724; }
.alert-err { background:#f8d7da; color:#721c24; }
.alert-info { background:#e8f4ff; color:#1c5d99; }
.btn-confirm { width:100%; padding:15px; background:#27ae60; color:#fff; border:none; border-radius:8px; font-size:17px; cursor:pointer; font-weight:bold; margin-top:8px; }
.btn-confirm:hover { background:#1e8e4f; }
.btn-cancel { width:100%; padding:13px; background:#f2f2f2; color:#555; border:none; border-radius:8px; font-size:15px; cursor:pointer; margin-top:10px; }
</style>

<div class="cs-wrap">
    <div class="cs-card">
        <h2><i class="fas fa-check-double"></i> 确认区块交易</h2>

        <?php if ($msg): ?><div class="alert alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <?php
        $dt = $detail['display_type'] ?? 'none';
        if ($dt === 'image' && !empty($detail['display_image'])):
        ?>
            <div class="cs-skin"><img src="/<?= htmlspecialchars($detail['display_image']) ?>" alt=""></div>
        <?php elseif ($dt === 'text' && !empty($detail['display_text'])):
            $c = ['red'=>'#ff6060','green'=>'#35cc2d','blue'=>'#337be6'][$detail['display_color'] ?? 'blue'] ?? '#337be6';
        ?>
            <div class="cs-skin txt" style="background:<?= $c ?>"><?= htmlspecialchars($detail['display_text']) ?></div>
        <?php else: ?>
            <div class="cs-skin"><i class="fas fa-map-marker-alt"></i></div>
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
            <span class="info-label">计价货币</span>
            <span><?= $detail['currency']==='popularity' ? '人气值 Ⓟ' : '人民币 ¥' ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">买家</span>
            <span><?= htmlspecialchars($buyerName ?: '—') ?></span>
        </div>
        <div class="price-big">
            <?= $detail['currency']==='popularity' ? 'Ⓟ ' : '¥ ' ?><?= number_format($detail['price'], 2) ?>
        </div>

        <?php if ($detail['status'] === 'pending'): ?>
            <?php if ($detail['currency'] === 'popularity'): ?>
                <div class="alert alert-info">买家表示已在 blockcity.vip 用 BCT 人气值向您的收款区块完成转账。请核对无误后确认完成交易（平台不自动扣减，由您确认所有权转移）。</div>
            <?php else: ?>
                <div class="alert alert-info">买家已提交购买意向（人民币 ¥<?= number_format($detail['price'],2) ?>）。请确认已收到线下付款后，点击"确认完成交易"转移所有权。</div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                <button type="submit" name="action_confirm" class="btn-confirm" onclick="return confirm('确认收到款项并完成交易？所有权将转移给买家')">
                    确认完成交易
                </button>
                <button type="submit" name="action_cancel" class="btn-cancel" onclick="return confirm('确认取消该交易？区块将回到可重新上架状态')">
                    取消交易
                </button>
            </form>
        <?php elseif ($detail['status'] === 'completed'): ?>
            <div class="alert alert-ok">该交易已完成，区块所有权已转移。</div>
            <a href="view.php?id=<?= $detail['block_id'] ?>" class="btn-confirm" style="display:block;text-align:center;text-decoration:none;">查看区块</a>
        <?php else: ?>
            <div class="alert alert-info">该挂牌当前状态：<?= $detail['status'] === 'listed' ? '售卖中（暂无买家下单）' : $detail['status'] ?></div>
            <a href="manage.php?id=<?= $detail['block_id'] ?>" class="btn-cancel" style="display:block;text-align:center;text-decoration:none;">返回区块管理</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
