<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/User.php';
require_once '../../classes/Transaction.php';
require_once '../../includes/auth.php';

checkLogin();

$nftId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

$nft = new NFT($pdo);
$user = new User($pdo);
$transaction = new Transaction($pdo);

// 获取NFT详情
$nftDetails = $nft->getNftById($nftId);
if (!$nftDetails) {
    $_SESSION['error'] = "NFT不存在";
	//echo $_SESSION['error']; die();
    header("Location: marketplace.php");
    exit;
}

// 检查NFT是否可购买（是否在售）
if (!$nftDetails['is_for_sale']) {
    $_SESSION['error'] = "该NFT暂不出售";
	//echo $_SESSION['error']; die();
    header("Location: marketplace.php");
    exit;
}

// 检查用户是否在购买自己的NFT
if ($nftDetails['owner_id'] == $userId) {
    $_SESSION['error'] = "不能购买自己的NFT";
	//echo $_SESSION['error']; die();
    header("Location: marketplace.php");
    exit;
}

// 获取用户信息（检查余额）
$userInfo = $user->getUserById($userId);

// 处理购买请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $price = $nftDetails['price'];
    $currency = $nftDetails['currency'];
    
    // 检查用户余额
    if ($currency === 'popularity') {
        if ($userInfo['popularity'] < $price) {
            $_SESSION['error'] = "人气值不足";
            header("Location: buy.php?id=" . $nftId);
            exit;
        }
    } else if ($currency === 'cny') {
        if ($userInfo['balance'] < $price) {
            $_SESSION['error'] = "余额不足";
            header("Location: buy.php?id=" . $nftId);
            exit;
        }
    }
    
    // 执行购买交易
    if ($transaction->purchaseNft($nftId, $userId, $price, $currency)) {
        $_SESSION['success'] = "购买成功！";
        
        // 发送通知给原所有者
        if ($nftDetails['owner_id']) {
            $notificationMessage = "您的NFT " . $nftDetails['code'] . " 已被用户购买";
            // 这里可以调用通知系统
        }
        
        header("Location: /user/collection.php");
        exit;
    } else {
        $_SESSION['error'] = "购买失败，请稍后重试";
        header("Location: buy.php?id=" . $nftId);
        exit;
    }
}

// 获取卖家信息
$sellerInfo = $user->getUserById($nftDetails['owner_id']);
?>

<?php require_once '../includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-shopping-cart"></i> 购买NFT
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <div class="row">
                        <!-- NFT信息 -->
                        <div class="col-md-6">
                            <div class="text-center mb-4">
                                <img src="../avatar/<?= htmlspecialchars($nftDetails['base_image']) ?>" 
                                     alt="NFT <?= htmlspecialchars($nftDetails['code']) ?>"
                                     class="img-fluid rounded" style="max-height: 300px;">
                                <h4 class="mt-3"><?= htmlspecialchars($nftDetails['code']) ?></h4>
                                <div class="badge bg-<?= $nftDetails['rarity'] ?> mb-2">
                                    <?= htmlspecialchars($nftDetails['rarity']) ?>
                                </div>
                                <p class="text-muted">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?= htmlspecialchars($nftDetails['city']) ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- 购买信息 -->
                        <div class="col-md-6">
                            <div class="p-3">
                                <h5>购买详情</h5>
                                
                                <!-- 价格信息 -->
                                <div class="mb-4 p-3 bg-light rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>价格：</span>
                                        <span class="h5 text-primary mb-0">
                                            <?= number_format($nftDetails['price'], $nftDetails['currency'] === 'cny' ? 2 : 0) ?>
                                            <?= $nftDetails['currency'] === 'cny' ? '元' : '人气值' ?>
                                        </span>
                                    </div>
                                    
                                    <!-- 卖家信息 -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>卖家：</span>
                                        <span><?= htmlspecialchars($sellerInfo['username'] ?? '未知用户') ?></span>
                                    </div>
                                    
                                    <!-- 交易费用 -->
                                    <div class="d-flex justify-content-between align-items-center text-muted small">
                                        <span>平台手续费：</span>
                                        <span>
                                            <?php
                                            $feeRate = 0.025; // 2.5% 手续费
                                            $fee = $nftDetails['price'] * $feeRate;
                                            echo number_format($fee, $nftDetails['currency'] === 'cny' ? 2 : 0);
                                            echo $nftDetails['currency'] === 'cny' ? '元' : '人气值';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- 用户余额信息 -->
                                <div class="mb-4 p-3 bg-light rounded">
                                    <h6>您的余额</h6>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>人气值：</span>
                                        <span class="<?= $userInfo['popularity'] < $nftDetails['price'] && $nftDetails['currency'] === 'popularity' ? 'text-danger' : 'text-success' ?>">
                                            <?= number_format($userInfo['popularity']) ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>人民币余额：</span>
                                        <span class="<?= $userInfo['balance'] < $nftDetails['price'] && $nftDetails['currency'] === 'cny' ? 'text-danger' : 'text-success' ?>">
                                            ￥<?= number_format($userInfo['balance'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- 购买确认 -->
                                <div class="alert alert-warning">
                                    <small>
                                        <i class="fas fa-exclamation-triangle"></i>
                                        购买后NFT将转移到您的账户，交易不可撤销。
                                    </small>
                                </div>
                                
                                <!-- 购买表单 -->
                                <form method="post">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="fas fa-check-circle"></i>
                                            确认购买
                                        </button>
                                        <a href="marketplace.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left"></i>
                                            返回市场
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- NFT详细信息 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">NFT详细信息</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">NFT编号：</th>
                                    <td><?= htmlspecialchars($nftDetails['code']) ?></td>
                                </tr>
                                <tr>
                                    <th>稀有度：</th>
                                    <td>
                                        <span class="badge bg-<?= $nftDetails['rarity'] ?>">
                                            <?= htmlspecialchars($nftDetails['rarity']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>所属城市：</th>
                                    <td><?= htmlspecialchars($nftDetails['city']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">创建时间：</th>
                                    <td><?= htmlspecialchars($nftDetails['created_at']) ?></td>
                                </tr>
                                <tr>
                                    <th>上架时间：</th>
                                    <td><?= htmlspecialchars($nftDetails['listed_at'] ?? '未上架') ?></td>
                                </tr>
                                <tr>
                                    <th>交易类型：</th>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= $nftDetails['currency'] === 'cny' ? '人民币交易' : '人气值交易' ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>