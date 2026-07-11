<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

$txId = intval($_GET['tx'] ?? 0);
if ($txId <= 0) {
    header('Location: sale_list.php'); exit;
}

// 未登录 → 跳登录页
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user_id'];

// 查询挂售记录
$stmt = $pdo->prepare("
    SELECT
        t.id AS transaction_id, t.price, t.currency, t.transaction_type, t.status, t.seller_id,
        n.id AS nft_id, n.code, n.base_image,
        u.username AS seller_name,
        c.id AS city_id, c.name AS city_name
    FROM nft_transactions t
    JOIN nft_avatars n ON t.nft_id = n.id
    JOIN users u ON t.seller_id = u.id
    JOIN cities c ON t.city_id = c.id
    WHERE t.id = ? AND t.status = 'listed'
");
$stmt->execute([$txId]);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx) {
    $error = '该 NFT 已售出或不存在';
}

// 自买拦截
if ($tx && $tx['seller_id'] == $userId) {
    $error = '不能购买自己的 NFT';
}

// 查询买家余额（该城市的人气值）
$buyerPopularity = 0;
if ($tx) {
    $stmt = $pdo->prepare("
        SELECT popularity FROM user_city_popularity WHERE user_id = ? AND city = ?
    ");
    $stmt->execute([$userId, $tx['city_name']]);
    $buyerPopularity = (int)$stmt->fetchColumn();
}

// POST 处理购买
$result = '';
$resultMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tx && !isset($error)) {
    if ($tx['currency'] === 'popularity') {
        // 人气值购买：检查余额
        if ($buyerPopularity < $tx['price']) {
            $error = '人气值不足，当前余额 Ⓟ' . number_format($buyerPopularity) . '，需要 Ⓟ' . number_format($tx['price']);
        } else {
            try {
                $pdo->beginTransaction();

                // 1. 更新 transaction: status=completed, buyer_id
                $stmt = $pdo->prepare("
                    UPDATE nft_transactions 
                    SET status = 'completed', buyer_id = ?, completed_at = NOW()
                    WHERE id = ? AND status = 'listed'
                ");
                $stmt->execute([$userId, $tx['transaction_id']]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('该 NFT 已被他人购买');
                }

                // 2. 扣买方人气值（按城市）
                $stmt = $pdo->prepare("
                    UPDATE user_city_popularity SET popularity = GREATEST(popularity - ?, 0)
                    WHERE user_id = ? AND city = ?
                ");
                $stmt->execute([$tx['price'], $userId, $tx['city_name']]);

                // 3. 加卖方气值（按城市）
                $stmt = $pdo->prepare("
                    INSERT INTO user_city_popularity (user_id, city, popularity)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE popularity = popularity + VALUES(popularity)
                ");
                $stmt->execute([$tx['seller_id'], $tx['city_name'], $tx['price']]);

                // 4. 转移 NFT 所有权
                $stmt = $pdo->prepare("UPDATE nft_avatars SET owner_id = ? WHERE id = ?");
                $stmt->execute([$userId, $tx['nft_id']]);

                // 5. 更新 nft_city_user 所有权
                $stmt = $pdo->prepare("
                    UPDATE nft_city_user SET user_id = ?, is_current = 1 
                    WHERE nft_id = ? AND city_id = ? AND is_current = 1
                ");
                $stmt->execute([$userId, $tx['nft_id'], $tx['city_id']]);

                $pdo->commit();

                // 刷新余额
                $buyerPopularity -= $tx['price'];

                header('Location: buy_success.php?tx=' . $tx['transaction_id'] . '&result=completed&code=' . urlencode($tx['code']));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    } else {
        // 人民币购买：创建 pending 记录
        $stmt = $pdo->prepare("
            UPDATE nft_transactions 
            SET status = 'pending', buyer_id = ?
            WHERE id = ? AND status = 'listed'
        ");
        $stmt->execute([$userId, $tx['transaction_id']]);

        if ($stmt->rowCount() === 0) {
            $error = '该 NFT 已被他人购买或不存在';
        } else {
            header('Location: buy_success.php?tx=' . $tx['transaction_id'] . '&result=pending&code=' . urlencode($tx['code']));
            exit;
        }
    }
}

require_once '../includes/header.php';
?>

<style>
.buy-container {
    max-width: 480px;
    margin: 40px auto;
    padding: 0 16px;
}
.buy-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.buy-card-top {
    text-align: center;
    padding: 28px 20px 16px;
    background: linear-gradient(135deg, #fff9f0, #fff);
}
.buy-avatar-circle {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    border: 3px solid #ff6b00;
    padding: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    overflow: hidden;
    margin-bottom: 14px;
}
.buy-avatar-circle img {
    max-width: 85%;
    max-height: 85%;
    object-fit: contain;
    border-radius: 50%;
}
.buy-nft-code {
    font-size: 22px;
    font-weight: 800;
    color: #ff6b00;
}
.buy-nft-meta {
    font-size: 13px;
    color: #888;
    margin-top: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}
.buy-card-body {
    padding: 20px 24px 24px;
}
.buy-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    font-size: 15px;
    border-bottom: 1px solid #f0f0f0;
}
.buy-info-row:last-child {
    border-bottom: none;
}
.buy-info-label {
    color: #888;
}
.buy-info-value {
    font-weight: 700;
    color: #333;
}
.buy-info-value.price {
    font-size: 18px;
    color: #ff6b00;
}
.buy-info-value.price-cny {
    color: #22c55e;
}
.buy-warning {
    background: #fff8e1;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    color: #b45309;
    margin: 14px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.buy-error {
    background: #fee2e2;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    color: #dc2626;
    margin-bottom: 14px;
}
.buy-btn {
    display: block;
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    text-decoration: none;
}
.buy-btn-primary {
    background: linear-gradient(135deg, #ff6b00, #f97316);
    color: #fff;
}
.buy-btn-primary:hover {
    opacity: 0.92;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(255,107,0,0.3);
    color: #fff;
}
.buy-btn-outline {
    background: #fff;
    color: #ff6b00;
    border: 2px solid #ff6b00;
    margin-top: 10px;
}
.buy-btn-outline:hover {
    background: #fff9f0;
    color: #ff6b00;
    text-decoration: none;
}
.buy-back-link {
    display: block;
    text-align: center;
    color: #999;
    font-size: 14px;
    margin-top: 16px;
    text-decoration: none;
}
.buy-back-link:hover {
    color: #ff6b00;
}
</style>

<div class="buy-container">
    <h2 style="text-align:center;font-size:20px;margin-bottom:20px;color:#333;">🛒 确认购买</h2>

    <?php if (isset($error)): ?>
    <div class="buy-card">
        <div class="buy-card-body" style="text-align:center;padding:30px;">
            <div class="buy-error"><?= htmlspecialchars($error) ?></div>
            <a href="sale_list.php" class="buy-btn buy-btn-primary">返回售卖市场</a>
        </div>
    </div>
    <?php else: ?>
    <div class="buy-card">
        <div class="buy-card-top">
            <div class="buy-avatar-circle">
                <img src="../avatar/<?= htmlspecialchars($tx['base_image']) ?>" 
                     alt="NFT <?= htmlspecialchars($tx['code']) ?>" loading="lazy">
            </div>
            <div class="buy-nft-code"><?= htmlspecialchars($tx['code']) ?></div>
            <div class="buy-nft-meta">
                <span>📍 <?= htmlspecialchars($tx['city_name']) ?></span>
                <span>@<?= htmlspecialchars($tx['seller_name']) ?></span>
            </div>
        </div>
        <div class="buy-card-body">
            <div class="buy-info-row">
                <span class="buy-info-label">售价</span>
                <span class="buy-info-value price <?= $tx['currency'] === 'cny' ? 'price-cny' : '' ?>">
                    <?= $tx['currency'] === 'cny' ? '¥' : 'Ⓟ ' ?>
                    <?= number_format($tx['price'], $tx['currency'] === 'cny' ? 2 : 0) ?>
                </span>
            </div>

            <?php if ($tx['currency'] === 'popularity'): ?>
            <div class="buy-info-row">
                <span class="buy-info-label">我的余额（<?= htmlspecialchars($tx['city_name']) ?>）</span>
                <span class="buy-info-value">Ⓟ <?= number_format($buyerPopularity) ?></span>
            </div>
            <div class="buy-info-row">
                <span class="buy-info-label">购买后余额</span>
                <span class="buy-info-value" style="color:<?= ($buyerPopularity - $tx['price']) < 0 ? '#dc2626' : '#22c55e' ?>">
                    Ⓟ <?= number_format(max(0, $buyerPopularity - $tx['price'])) ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if ($buyerPopularity < $tx['price'] && $tx['currency'] === 'popularity'): ?>
            <div class="buy-error">人气值不足! 当前余额 Ⓟ<?= number_format($buyerPopularity) ?>，需要 Ⓟ<?= number_format($tx['price']) ?></div>
            <a href="sale_list.php" class="buy-btn buy-btn-outline">← 返回市场</a>
            <?php else: ?>
            <form method="post">
                <button type="submit" class="buy-btn buy-btn-primary">
                    <?php if ($tx['currency'] === 'cny'): ?>
                    提交购买意向（等待卖家确认）
                    <?php else: ?>
                    确认购买 Ⓟ <?= number_format($tx['price']) ?>
                    <?php endif; ?>
                </button>
            </form>

            <?php if ($tx['currency'] === 'cny'): ?>
            <div class="buy-warning">
                <span>ℹ️</span> 卖家确认后该 NFT 将转移到您的收藏
            </div>
            <?php else: ?>
            <div style="text-align:center;font-size:12px;color:#999;margin-top:10px;">
                购买后该 NFT 将出现在您的收藏中
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <a href="/nft/view.php?id=<?= $tx['nft_id'] ?>" class="buy-back-link">← 返回NFT详情</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
