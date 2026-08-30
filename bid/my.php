<?php
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../includes/auth.php';
checkLogin();

$auction = new Auction($pdo);
$userId = $_SESSION['user_id'];

$auction->tick();

// 取消拍卖（卖家操作，POST 优先于列表查询执行）
$opMsg = '';
$opErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $opErr = 'CSRF令牌验证失败';
    } else {
        $r = $auction->cancelAuction(intval($_POST['id'] ?? 0), $userId);
        if ($r['ok']) $opMsg = $r['msg'];
        else $opErr = $r['msg'];
    }
}

$myAuctions = $auction->getMyAuctions($userId);
$myBids = $auction->getMyBids($userId);

$tab = $_GET['tab'] ?? 'created';

$site_config['title'] = '我的拍卖 - 58拍卖';
require_once 'includes/header.php';
?>
<style>
.my-wrap { max-width: 900px; margin: 24px auto; padding: 0 15px; }
.my-tabs { display: flex; gap: 0; background: #fff; border-radius: 10px 10px 0 0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.my-tab { flex: 1; padding: 13px; text-align: center; font-size: 15px; font-weight: 600; color: #999; cursor: pointer; text-decoration: none; border-bottom: 3px solid transparent; }
.my-tab.active { color: #ff6b00; border-bottom-color: #ff6b00; background: #fff9f5; }
.my-body { background: #fff; border-radius: 0 0 10px 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.my-row { display: flex; justify-content: space-between; align-items: center; padding: 14px; border-radius: 8px; margin-bottom: 8px; border: 1px solid #eee; text-decoration: none; color: inherit; }
.my-row:hover { border-color: #ff6b00; }
.my-info { flex: 1; min-width: 0; }
.my-title { font-size: 15px; font-weight: bold; color: #222; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.my-meta { font-size: 12px; color: #999; }
.my-price { text-align: right; flex-shrink: 0; margin-left: 12px; }
.my-price .p { font-size: 16px; font-weight: bold; color: #e74c3c; }
.my-price .s { font-size: 12px; color: #999; }
.empty { text-align: center; padding: 50px; color: #999; }
.my-actions { display: flex; flex-direction: column; gap: 6px; margin-left: 12px; flex-shrink: 0; }
.op-btn { display: inline-block; text-align: center; font-size: 12px; padding: 5px 12px; border-radius: 6px; border: 1px solid #ddd; background: #fff; color: #555; cursor: pointer; text-decoration: none; line-height: 1.4; }
.op-btn:hover { border-color: #4f46e5; color: #4f46e5; }
.op-danger:hover { border-color: #dc2626; color: #dc2626; }
.alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 14px; }
.alert-ok { background: #d4edda; color: #155724; }
.alert-err { background: #f8d7da; color: #721c24; }
</style>

<div class="my-wrap">
    <h1 style="font-size: 24px; margin: 0 0 16px;">👤 我的拍卖</h1>

    <div class="my-tabs">
        <a class="my-tab <?= $tab === 'created' ? 'active' : '' ?>" href="my.php?tab=created">我发布的</a>
        <a class="my-tab <?= $tab === 'bids' ? 'active' : '' ?>" href="my.php?tab=bids">我出价的</a>
    </div>

    <div class="my-body">
        <?php if ($opMsg): ?><div class="alert alert-ok"><?= htmlspecialchars($opMsg) ?></div><?php endif; ?>
        <?php if ($opErr): ?><div class="alert alert-err"><?= htmlspecialchars($opErr) ?></div><?php endif; ?>
        <?php if ($tab === 'created'): ?>
            <?php if (empty($myAuctions)): ?>
                <div class="empty">您还没有发布过拍卖</div>
            <?php else: ?>
                <?php foreach ($myAuctions as $a): 
                    $stMap = ['pending'=>'未开始','active'=>'竞拍中','sold'=>'已成交','ended'=>'已流拍','canceled'=>'已取消'];
                    $st = $stMap[$a['status']] ?? $a['status'];
                    $canCancel = $a['status'] === 'pending' || ($a['status'] === 'active' && empty($a['current_bidder_id']));
                ?>
                <div class="my-row">
                    <a href="view.php?id=<?= $a['id'] ?>" style="display:flex;justify-content:space-between;align-items:center;flex:1;text-decoration:none;color:inherit;">
                        <div class="my-info">
                            <div class="my-title"><?= $a['item_type'] === 'nft' ? 'NFT头像' : '区块' ?> #<?= $a['id'] ?></div>
                            <div class="my-meta"><?= $st ?> · <?= date('m-d H:i', strtotime($a['end_time'])) ?> 截止</div>
                        </div>
                        <div class="my-price">
                            <div class="p"><?= $a['currency'] === 'popularity' ? 'Ⓟ ' : '¥ ' ?><?= number_format($a['current_price'] ?? $a['start_price'], 2) ?></div>
                            <div class="s">起拍 <?= number_format($a['start_price'], 2) ?></div>
                        </div>
                    </a>
                    <div class="my-actions">
                        <?php if ($a['status'] === 'pending'): ?>
                        <a class="op-btn" href="create.php?edit=<?= $a['id'] ?>">✏️ 编辑</a>
                        <?php endif; ?>
                        <?php if ($canCancel): ?>
                        <form method="POST" onsubmit="return confirm('确定取消该拍卖吗？取消后物品将解除锁定。');" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="op-btn op-danger">🗑 取消</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <?php if (empty($myBids)): ?>
                <div class="empty">您还没有参与过出价</div>
            <?php else: ?>
                <?php foreach ($myBids as $a): 
                    $stMap = ['pending'=>'未开始','active'=>'竞拍中','sold'=>'已成交','ended'=>'已流拍','canceled'=>'已取消'];
                    $st = $stMap[$a['status']] ?? $a['status'];
                    $won = $a['status'] === 'sold' && intval($a['current_bidder_id']) === $userId;
                ?>
                <a class="my-row" href="view.php?id=<?= $a['id'] ?>">
                    <div class="my-info">
                        <div class="my-title"><?= $a['item_type'] === 'nft' ? 'NFT头像' : '区块' ?> #<?= $a['id'] ?> <?= $won ? '🏆' : '' ?></div>
                        <div class="my-meta"><?= $st ?> · 我的最高出价 <?= number_format($a['my_max_bid'] ?? 0, 2) ?></div>
                    </div>
                    <div class="my-price">
                        <div class="p"><?= $a['currency'] === 'popularity' ? 'Ⓟ ' : '¥ ' ?><?= number_format($a['current_price'] ?? $a['start_price'], 2) ?></div>
                        <div class="s">当前价</div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
