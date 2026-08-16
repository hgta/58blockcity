<?php
require_once '../config/database.php';
require_once '../classes/Auction.php';
require_once '../includes/auth.php';

$auction = new Auction($pdo);
$userId = $_SESSION['user_id'] ?? 0;

// 惰性结算：结算所有已到期的拍卖
$auction->settleExpired();

// 筛选
$itemType = in_array($_GET['type'] ?? '', ['block', 'nft'], true) ? $_GET['type'] : '';
$currency = in_array($_GET['currency'] ?? '', ['popularity', 'cny'], true) ? $_GET['currency'] : '';
$page = max(1, intval($_GET['page'] ?? 1));

$result = $auction->getActiveAuctions($page, 20, $itemType, $currency);
$list = $result['list'];
$total = $result['total'];
$pages = $result['pages'];

$site_config['title'] = '拍卖大厅 - 58拍卖';
$site_config['description'] = '区块、NFT头像拍卖大厅，价高者得。';
require_once 'includes/header.php';
?>
<style>
.bid-wrap { max-width: 1100px; margin: 24px auto; padding: 0 15px; }
.bid-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.bid-filter { padding: 7px 16px; border-radius: 20px; border: 1px solid #ddd; background: #fff; color: #555; font-size: 14px; cursor: pointer; text-decoration: none; }
.bid-filter.active { background: #ff6b00; color: #fff; border-color: #ff6b00; }
.bid-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
.bid-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.06); text-decoration: none; color: inherit; transition: transform .2s, box-shadow .2s; display: flex; flex-direction: column; }
.bid-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
.bid-img { aspect-ratio: 1/1; background: #f5f5f5; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.bid-img img { width: 100%; height: 100%; object-fit: cover; }
.bid-img .ph { color: #ccc; font-size: 40px; }
.bid-body { padding: 12px 14px; flex: 1; display: flex; flex-direction: column; }
.bid-title { font-size: 15px; font-weight: bold; color: #222; margin-bottom: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bid-tag { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 4px; background: #eef2ff; color: #4f46e5; margin-bottom: 6px; }
.bid-tag.nft { background: #fce7f3; color: #db2777; }
.bid-price { font-size: 18px; font-weight: bold; color: #e74c3c; margin-bottom: 6px; }
.bid-meta { font-size: 12px; color: #999; display: flex; justify-content: space-between; }
.bid-empty { text-align: center; padding: 60px; color: #999; }
</style>

<div class="bid-wrap">
    <h1 style="font-size: 24px; margin: 0 0 16px;">🔨 拍卖大厅</h1>

    <div class="bid-filters">
        <a class="bid-filter <?= $itemType === '' ? 'active' : '' ?>" href="index.php">全部</a>
        <a class="bid-filter <?= $itemType === 'block' ? 'active' : '' ?>" href="index.php?type=block">区块</a>
        <a class="bid-filter <?= $itemType === 'nft' ? 'active' : '' ?>" href="index.php?type=nft">NFT头像</a>
    </div>

    <?php if (empty($list)): ?>
        <div class="bid-empty"><i class="fas fa-gavel" style="font-size:48px;opacity:.4;"></i><p style="margin-top:12px;">暂无拍卖中的物品</p></div>
    <?php else: ?>
    <div class="bid-grid">
        <?php foreach ($list as $a): 
            $detail = $auction->getAuctionById($a['id']);
            $img = $detail['item_image'] ?? '';
            // 完整 URL 直接用，相对路径加 / 前缀
            $imgUrl = $img ? (preg_match('#^https?://#', $img) ? $img : '/' . $img) : '';
        ?>
        <a class="bid-card" href="view.php?id=<?= $a['id'] ?>">
            <div class="bid-img">
                <?php if ($imgUrl): ?><img src="<?= htmlspecialchars($imgUrl) ?>" alt=""><?php else: ?><span class="ph"><i class="fas fa-image"></i></span><?php endif; ?>
            </div>
            <div class="bid-body">
                <span class="bid-tag <?= $a['item_type'] === 'nft' ? 'nft' : '' ?>"><?= $a['item_type'] === 'nft' ? 'NFT头像' : '区块' ?></span>
                <div class="bid-title"><?= htmlspecialchars($detail['item_title'] ?? ('#' . $a['id'])) ?></div>
                <div class="bid-price"><?= $a['currency'] === 'popularity' ? 'Ⓟ ' : '¥ ' ?><?= number_format($a['current_price'] ?? $a['start_price'], 2) ?></div>
                <div class="bid-meta">
                    <span><?= $a['status'] === 'pending' ? '未开始' : '出价 ' . ($a['current_bidder_id'] ? '1' : '0') . ' 次' ?></span>
                    <span><?= date('m-d H:i', strtotime($a['end_time'])) ?> 截止</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div style="text-align:center;margin-top:20px;">
        <?php if ($page > 1): ?><a class="bid-filter" href="index.php?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">上一页</a><?php endif; ?>
        <span style="margin:0 10px;color:#666;"><?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="bid-filter" href="index.php?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">下一页</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
