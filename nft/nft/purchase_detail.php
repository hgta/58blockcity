<?php
require_once '../../config/database.php';
require_once '../../classes/NFT.php';
require_once '../../classes/PurchaseRequest.php';
require_once '../../classes/User.php';
require_once '../includes/auth.php';
require_once '../../classes/City.php';
require_once '../../classes/Comment.php';

// 验证登录状态
checkLogin();

$userId = $_SESSION['user_id'];
$nftId = $_GET['id'] ?? 0;

$nft = new NFT($pdo);
$purchaseRequest = new PurchaseRequest($pdo);
$user = new User($pdo);
$comment = new Comment($pdo);

// 获取NFT详情
$nftDetails = $nft->getNftById($nftId);
if (!$nftDetails) {
    $_SESSION['error'] = "NFT头像不存在";
    header("Location: purchase_list.php");
    exit;
}

// 获取NFT标签
$tags = $nft->getNftTags($nftId);

// 获取用户已有的求购记录（如果有）
$existingRequest = $purchaseRequest->getUserPurchaseRequestForNft($userId, $nftId);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理评论提交
    if (isset($_POST['comment_text'])) {
        $commentText = trim($_POST['comment_text']);
        if (!empty($commentText)) {
            if ($comment->addComment($userId, $nftId, $commentText)) {
                $_SESSION['message'] = "评论发布成功！";
				//echo "<script>alert('评论发布成功！');</script>";
            } else {
                $_SESSION['error'] = "评论发布失败";
				//echo "<script>alert('评论发布失败');</script>";
            }
        }
        header("Location: purchase_detail.php?id=$nftId");
        exit;
    }
    
    // 处理求购表单提交
    $transactionType = $_POST['transaction_type'] ?? 'platform';
    $currency = $_POST['currency'] ?? 'popularity';
    $price = $_POST['price'] ?? 0;
    $contactPhone = $_POST['contact_phone'] ?? '';
    $contactWechat = $_POST['contact_wechat'] ?? '';
    $contactQQ = $_POST['contact_qq'] ?? '';
    $contactEmail = $_POST['contact_email'] ?? '';
    $blockNumber = $_POST['block_number'] ?? '';
    
    // 验证数据
    if (!is_numeric($price) || $price <= 0) {
        $_SESSION['error'] = "请输入有效的价格";
    } else {
        $price = $currency === 'cny' ? round($price, 2) : intval($price);
        
        $cityId = $_POST['city_id'];
        
        // 保存或更新求购请求
        if ($existingRequest) {
            $success = $purchaseRequest->updatePurchaseRequest(
                $existingRequest['id'],
                $price,
                $currency,
                $transactionType,
                $contactPhone,
                $contactWechat,
                $contactQQ,
                $contactEmail,
                $blockNumber
            );
            $action = "更新";
        } else {
            $success = $purchaseRequest->createPurchaseRequest(
                $userId,
                $nftId, $cityId,
                $price,
                $currency,
                $transactionType,
                $contactPhone,
                $contactWechat,
                $contactQQ,
                $contactEmail,
                $blockNumber
            );
            $action = "提交";
        }
        
        if ($success) {
            $_SESSION['message'] = "求购请求{$action}成功！";
            header("Location: purchase_list.php");
            exit;
        } else {
            $_SESSION['error'] = "求购请求{$action}失败，请稍后再试";
        }
    }
}

// 处理删除请求
if (isset($_GET['delete']) && $existingRequest) {
    if ($purchaseRequest->deletePurchaseRequest($existingRequest['id'])) {
        $_SESSION['message'] = "求购请求已删除";
        header("Location: purchase_list.php");
        exit;
    } else {
        $_SESSION['error'] = "删除求购请求失败";
    }
}

// 获取用户信息填充联系方式
$userInfo = $user->getUserById($userId);

// 获取所有城市列表
$city = new City($pdo);
$cities = $city->getAllCities();

// 获取当前NFT的所有评论
$comments = $comment->getCommentsByNft($nftId);
?>

<?php require_once '../includes/header.php'; ?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-4">
            <!-- NFT展示 -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="nft-preview-container mb-3">
                        <img src="../avatar/<?= htmlspecialchars($nftDetails['base_image']) ?>" 
                             alt="NFT <?= htmlspecialchars($nftDetails['code']) ?>"
                             class="img-fluid rounded" style="max-width: 200px;">
                    </div>
                    <h3><?= htmlspecialchars($nftDetails['code']) ?></h3>
                    <p class="text-muted">
                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($nftDetails['city']) ?>
                    </p>
                                        
                    <!-- 标签显示 -->
                    <?php if (!empty($tags)): ?>
                        <div class="mb-3">
                            <?php foreach ($tags as $tag): ?>
								<?php if (isset($tag['name'])): ?>
                                <a href="purchase_list.php?search=<?= urlencode($tag['name']) ?>" 
                                   class="badge bg-secondary text-decoration-none me-1">
                                    <?= htmlspecialchars($tag['name']) ?>
                                </a>
								<?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($existingRequest): ?>
                <div class="card border-danger mb-4">
                    <div class="card-header bg-danger text-white">
                        <i class="fas fa-exclamation-triangle"></i> 危险操作
                    </div>
                    <div class="card-body text-center">
                        <p>删除您的求购请求将使其从市场中移除</p>
                        <a href="purchase_detail.php?id=<?= $nftId ?>&delete=1" 
                           class="btn btn-outline-danger"
                           onclick="return confirm('确定要删除您的求购请求吗？')">
                            <i class="fas fa-trash-alt"></i> 删除求购请求
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-8">
            <div class="row">
                <div class="col-12">
                    <!-- 求购表单 -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-hand-holding-usd"></i> 
                            <?= $existingRequest ? '修改求购信息' : '填写求购信息' ?>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <?= htmlspecialchars($_SESSION['error']) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>
                            
                            <form method="post">
                                <div class="mb-3">
                                    <div class="form-group">
                                        <label for="citySelect">选择城市</label>
                                        <select class="form-control" id="citySelect" name="city_id" required>
                                            <option value="">-- 请选择城市 --</option>
                                            <?php foreach ($cities as $city): ?>
                                                <option value="<?= $city['id'] ?>" <?= ($existingRequest['city_id'] ?? '') == $city['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($city['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                
                                    <label class="form-label">交易方式</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="transaction_type" 
                                               id="transactionPlatform" value="platform"
                                               <?= ($existingRequest['transaction_type'] ?? 'platform') === 'platform' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="transactionPlatform">
                                            平台交易（推荐）
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="transaction_type" 
                                               id="transactionIntermediary" value="intermediary"
                                               <?= ($existingRequest['transaction_type'] ?? '') === 'intermediary' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="transactionIntermediary">
                                            中介交易
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="transaction_type" 
                                               id="transactionDirect" value="direct"
                                               <?= ($existingRequest['transaction_type'] ?? '') === 'direct' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="transactionDirect">
                                            直接交易
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">求购价格</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="price" 
                                                   min="0" step="<?= ($existingRequest['currency'] ?? 'popularity') === 'cny' ? '0.01' : '1' ?>"
                                                   value="<?= htmlspecialchars($existingRequest['price'] ?? '') ?>"
                                                   required>
                                            <select class="form-select" name="currency" style="max-width: 120px;">
                                                <option value="popularity" <?= ($existingRequest['currency'] ?? 'popularity') === 'popularity' ? 'selected' : '' ?>>人气值</option>
                                                <option value="cny" <?= ($existingRequest['currency'] ?? '') === 'cny' ? 'selected' : '' ?>>人民币</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">联系方式（至少填写一项）</label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <input type="tel" class="form-control" name="contact_phone" 
                                                   placeholder="电话" 
                                                   value="<?= htmlspecialchars($existingRequest['contact_phone'] ?? $userInfo['phone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" name="contact_wechat" 
                                                   placeholder="微信" 
                                                   value="<?= htmlspecialchars($existingRequest['contact_wechat'] ?? $userInfo['wechat'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" name="contact_qq" 
                                                   placeholder="QQ" 
                                                   value="<?= htmlspecialchars($existingRequest['contact_qq'] ?? $userInfo['qq'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="email" class="form-control" name="contact_email" 
                                                   placeholder="邮箱" 
                                                   value="<?= htmlspecialchars($existingRequest['contact_email'] ?? $userInfo['email'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">区块城市信息（可选）</label>
                                    <input type="text" class="form-control" name="block_number" 
                                           placeholder="输入您所在的区块号" 
                                           value="<?= htmlspecialchars($existingRequest['block_number'] ?? '') ?>">
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?= $existingRequest ? '更新求购信息' : '提交求购请求' ?>
                                    </button>
                                    <a href="purchase_list.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left"></i> 返回求购市场
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- 评论区域 -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-comments"></i> 用户评价
                        </div>
                        <div class="card-body">
                            <!-- 评论表单 -->
                            <form method="post" class="mb-4">
                                <div class="mb-3">
                                    <label for="commentText" class="form-label">发表您的评价</label>
                                    <textarea class="form-control" id="commentText" name="comment_text" rows="3" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-info">提交评价</button>
                            </form>
                            
                            <!-- 评论列表 -->
                            <div class="comments-section">
                                <?php if (!empty($comments)): ?>
                                    <?php foreach ($comments as $commentItem): ?>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="card-title"><?= htmlspecialchars($commentItem['username']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($commentItem['created_at']) ?></small>
                                                </div>
                                                <p class="card-text"><?= htmlspecialchars($commentItem['content']) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">暂无评价，快来发表第一条评论吧！</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// 根据货币类型调整价格输入步长
document.querySelector('select[name="currency"]').addEventListener('change', function() {
    const priceInput = document.querySelector('input[name="price"]');
    if (this.value === 'cny') {
        priceInput.step = '0.01';
    } else {
        priceInput.step = '1';
    }
});
</script>