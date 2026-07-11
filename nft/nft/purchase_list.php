<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/City.php';
require_once '../../classes/PurchaseRequest.php';

// 可选登录，无需强制
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;

$nft = new NFT($pdo);
$city = new City($pdo);
$purchaseRequest = new PurchaseRequest($pdo);

// 搜索参数
$searchCode = $_GET['code'] ?? '';
$searchTag = $_GET['tag'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 80;

// 数据
$allCities = $city->getAllCities();
$nfts = $nft->getAllNfts($perPage, ($page - 1) * $perPage, $searchCode, $searchTag);
$totalNfts = $nft->getTotalNftCount($searchCode, $searchTag);
$totalPages = ceil($totalNfts / $perPage);

// 批量获取求购统计
$purchaseCounts = $purchaseRequest->getNftPurchaseCounts();
$totalPurchases = array_sum(array_column($purchaseCounts, 'count'));

// 登录用户的求购记录
$userRequestedNfts = [];
$userRequestsMap = [];
if ($isLoggedIn) {
    $userPurchaseRequests = $purchaseRequest->getUserPurchaseRequests($userId);
    $userRequestedNfts = array_column($userPurchaseRequests, 'nft_id');
    foreach ($userPurchaseRequests as $r) {
        $userRequestsMap[$r['nft_id']] = $r;
    }
}

$loginUrl = '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
?>
<?php require_once '../includes/header.php'; ?>

<style>
.purchase-hero{background:linear-gradient(135deg,#ff6b00,#e55a00);color:#fff;padding:32px 0;margin-bottom:24px}
.purchase-hero h1{font-size:26px;margin:0 0 6px;font-weight:bold}
.purchase-hero p{font-size:14px;opacity:0.9;margin:0}
.purchase-stats{display:flex;gap:24px;margin-top:16px;flex-wrap:wrap}
.purchase-stat{font-size:13px;opacity:0.85}
.purchase-stat strong{font-size:18px}

.search-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:14px 0}
.search-bar input,.search-bar select{padding:8px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;outline:none}
.search-bar input:focus,.search-bar select:focus{border-color:#ff6b00}
.search-bar input{flex:1;min-width:180px}
.search-btn{padding:8px 20px;background:#ff6b00;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px}
.reset-btn{padding:8px 16px;background:#f5f5f5;color:#666;border:1px solid #ddd;border-radius:8px;text-decoration:none;font-size:14px}

.nft-buy-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px}
.nft-buy-card{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);transition:transform 0.2s,box-shadow 0.2s;text-align:center;padding-bottom:10px}
.nft-buy-card:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,0.12)}
.nft-buy-img{position:relative;aspect-ratio:1;overflow:hidden;background:#f5f5f5}
.nft-buy-img img{width:100%;height:100%;object-fit:cover}
.nft-buy-code{font-size:13px;color:#333;font-weight:bold;margin:8px 0 4px}
.nft-buy-info{font-size:11px;color:#999;margin-bottom:8px;padding:0 8px}
.nft-buy-btn{display:inline-block;padding:5px 14px;border-radius:16px;font-size:12px;cursor:pointer;border:none;text-decoration:none;margin-bottom:4px;font-weight:bold}
.btn-buy-request{background:#ff6b00;color:#fff}
.btn-buy-edit{background:#3498db;color:#fff}
.btn-buy-login{background:#ddd;color:#666}
.nft-buy-count{position:absolute;top:6px;right:6px;background:rgba(0,0,0,0.7);color:#fff;font-size:11px;padding:2px 8px;border-radius:10px}

.empty-state{text-align:center;padding:60px 20px;color:#999}
.empty-state i{font-size:48px;display:block;margin-bottom:16px}

.pagination-simple{display:flex;justify-content:center;gap:8px;margin:30px 0;align-items:center}
.pagination-simple a,.pagination-simple span{display:inline-block;padding:8px 14px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;font-size:14px}
.pagination-simple a:hover{background:#fff3f0;border-color:#ff6b00;color:#ff6b00}
.pagination-simple .active{background:#ff6b00;color:#fff;border-color:#ff6b00}
.pagination-simple .disabled{color:#ccc;pointer-events:none}
</style>

<div class="purchase-hero">
    <div class="container">
        <h1>🛒 NFT 求购市场</h1>
        <p>发现你喜欢的头像，向持有者发起求购</p>
        <div class="purchase-stats">
            <div class="purchase-stat">📝 <strong><?= number_format($totalPurchases) ?></strong> 个求购</div>
            <div class="purchase-stat">🖼 <strong><?= number_format($totalNfts) ?></strong> 个 NFT</div>
        </div>
    </div>
</div>

<div class="container">
    <!-- 搜索 -->
    <form method="get" class="search-bar">
        <input type="text" name="code" placeholder="🔍 搜索编号..." value="<?= htmlspecialchars($searchCode) ?>">
        <select name="tag">
            <option value="">全部标签</option>
        </select>
        <button type="submit" class="search-btn">搜索</button>
        <?php if ($searchCode): ?>
        <a href="purchase_list.php" class="reset-btn">重置</a>
        <?php endif; ?>
    </form>

    <!-- NFT 网格 -->
    <div class="nft-buy-grid">
        <?php if (empty($nfts)): ?>
        <div class="empty-state" style="grid-column:1/-1">
            <i class="fas fa-search"></i>
            <p>没有找到符合条件的 NFT</p>
        </div>
        <?php else: ?>
        <?php foreach ($nfts as $item):
            $nid = intval($item['id']);
            $pc = $purchaseCounts[$nid] ?? ['count'=>0,'max_price'=>null];
            $isRequested = in_array($nid, $userRequestedNfts);
            $myReq = $userRequestsMap[$nid] ?? null;
        ?>
        <div class="nft-buy-card">
            <div class="nft-buy-img">
                <img src="../avatar/<?= htmlspecialchars($item['base_image']) ?>" alt="<?= htmlspecialchars($item['code']) ?>" loading="lazy">
                <?php if ($pc['count'] > 0): ?>
                <div class="nft-buy-count">📝 <?= $pc['count'] ?>人求购</div>
                <?php endif; ?>
            </div>
            <div class="nft-buy-code"><?= htmlspecialchars($item['code']) ?></div>
            <div class="nft-buy-info">
                <?php if ($pc['count'] > 0 && $pc['max_price']): ?>
                    最高出价 Ⓟ<?= number_format($pc['max_price']) ?>
                <?php else: ?>
                    暂无求购
                <?php endif; ?>
            </div>
            <?php if ($isRequested && $myReq): ?>
            <a href="/nft/purchase_detail.php?id=<?= $nid ?>" class="nft-buy-btn btn-buy-edit">
                ✏️ 修改求购
            </a>
            <?php elseif ($isLoggedIn): ?>
            <a href="/nft/purchase_detail.php?id=<?= $nid ?>" class="nft-buy-btn btn-buy-request">
                💰 我要求购
            </a>
            <?php else: ?>
            <a href="<?= $loginUrl ?>" class="nft-buy-btn btn-buy-login">
                🔒 登录后求购
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 分页 -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-simple">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>1])) ?>">首页</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">上一页</a>
        <?php else: ?>
        <span class="disabled">首页</span>
        <span class="disabled">上一页</span>
        <?php endif; ?>

        <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">下一页</a>
        <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$totalPages])) ?>">末页</a>
        <?php else: ?>
        <span class="disabled">下一页</span>
        <span class="disabled">末页</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
