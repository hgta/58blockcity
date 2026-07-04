<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/Transaction.php';
require_once '../../classes/User.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../../classes/SeoHelper.php';

// 验证登录状态
checkLogin();

$userId = $_SESSION['user_id'];
$nftId = $_GET['id'] ?? 0;
$cityId = $_GET['city_id'] ?? 0;

// 验证参数
if (!$nftId || !$cityId) {
    $_SESSION['error'] = "无效的请求参数";
    header("Location: /user/collection.php");
    exit();
}

$nft = new NFT($pdo);
$transaction = new Transaction($pdo);
$user = new User($pdo);

// 验证NFT所有权和城市关联
$ownership = $nft->verifyOwnership($nftId, $cityId, $userId);
if (!$ownership) {
    $_SESSION['error'] = "您不拥有此NFT或无权在该城市出售";
    header("Location: /user/collection.php");
    exit();
}

// 获取NFT详情
$nftDetails = $nft->getNftDetails($nftId);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 验证表单数据
        $price = $_POST['price'] ?? 0;
        $currency = $_POST['currency'] ?? '';
        $transactionType = $_POST['transaction_type'] ?? 'platform';
        
        if (!is_numeric($price) || $price <= 0) {
            throw new Exception("请输入有效的价格");
        }
        
        if (!in_array($currency, ['popularity', 'cny'])) {
            throw new Exception("请选择有效的货币类型");
        }
        
        if (!in_array($transactionType, ['platform', 'intermediary', 'direct'])) {
            throw new Exception("请选择有效的交易方式");
        }
        
        // 创建售卖交易
        $result = $transaction->createSale(
            $nftId,
            $userId,
            $price,
            $currency,
            $transactionType,
            $cityId
        );
        
        if ($result) {
            $_SESSION['message'] = "NFT已成功上架出售";
            // 百度主动推送
            SeoHelper::baiduPush(SeoHelper::nftUrl($nftId, $nftDetails['name'] ?? ''));
            header("Location: /user/collection.php");
            exit();
        } else {
            throw new Exception("出售操作失败，请重试");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// 获取用户在该城市的人气值
$userPopularity = $user->getUserCityPopularity($userId, $ownership['city_name']);
?>

<?php include '../includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-tag me-2"></i>出售NFT头像</h4>
                </div>
                <div class="card-body">
                    <!-- 消息提示 -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <!-- NFT信息展示 -->
                    <div class="nft-sale-preview mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-4 text-center">
                                <div class="avatar-circle-lg mb-3">
                                    <img src="../avatar/<?= htmlspecialchars($nftDetails['base_image']) ?>" 
                                         class="avatar-img" 
                                         alt="NFT <?= htmlspecialchars($nftDetails['code']) ?>">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h5><?= htmlspecialchars($nftDetails['code']) ?></h5>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?= htmlspecialchars($ownership['city_name']) ?>
                                </p>
                                <p class="mb-2">
                                    <span class="badge bg-<?= $nftDetails['rarity'] ?>">
                                        <?= htmlspecialchars($nftDetails['rarity']) ?>
                                    </span>
                                </p>
                                <p class="text-muted small mb-0">
                                    当前持有人: <?= htmlspecialchars($_SESSION['username']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 出售表单 -->
                    <form method="post">
                        <div class="row g-3">
                            <!-- 价格 -->
                            <div class="col-md-6">
                                <label for="price" class="form-label">出售价格</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" id="price" name="price" 
                                           class="form-control" required
                                           placeholder="输入价格">
                                    <span class="input-group-text" id="currencyDisplay">
                                        <?= $currency == 'cny' ? '¥' : '人气值' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- 货币类型 -->
                            <div class="col-md-6">
                                <label for="currency" class="form-label">货币类型</label>
                                <select id="currency" name="currency" class="form-select" required>
                                    <option value="popularity" <?= $currency != 'cny' ? 'selected' : '' ?>>
                                        人气值 (当前: <?= $userPopularity ?>)
                                    </option>
                                    <option value="cny" <?= $currency == 'cny' ? 'selected' : '' ?>>人民币</option>
                                </select>
                            </div>
                            
                            <!-- 交易方式 -->
                            <div class="col-md-6">
                                <label for="transaction_type" class="form-label">交易方式</label>
                                <select id="transaction_type" name="transaction_type" class="form-select" required>
                                    <option value="platform">平台交易</option>
                                    <option value="intermediary">中介交易</option>
                                    <option value="direct">直接交易</option>
                                </select>
                            </div>
                            
                            <!-- 城市信息 (隐藏字段) -->
                            <input type="hidden" name="city_id" value="<?= $cityId ?>">
                            
                            <!-- 提交按钮 -->
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-check-circle me-2"></i>确认上架出售
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 货币类型切换显示
document.getElementById('currency').addEventListener('change', function() {
    const display = this.value === 'cny' ? '¥' : '人气值';
    document.getElementById('currencyDisplay').textContent = display;
});
</script>

<?php include '../includes/footer.php'; ?>