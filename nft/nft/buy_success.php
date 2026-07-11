<?php
$txId = intval($_GET['tx'] ?? 0);
$result = $_GET['result'] ?? '';
$code = htmlspecialchars($_GET['code'] ?? '');

require_once '../includes/header.php';
?>

<style>
.success-container {
    max-width: 400px;
    margin: 60px auto;
    padding: 0 20px;
    text-align: center;
}
.success-icon {
    font-size: 64px;
    margin-bottom: 16px;
}
.success-title {
    font-size: 22px;
    font-weight: 800;
    color: #333;
    margin-bottom: 8px;
}
.success-subtitle {
    font-size: 14px;
    color: #888;
    margin-bottom: 28px;
    line-height: 1.6;
}
.success-code {
    display: inline-block;
    background: #fff9f0;
    color: #ff6b00;
    font-weight: 700;
    padding: 6px 18px;
    border-radius: 8px;
    font-size: 16px;
    margin-bottom: 24px;
    border: 1px solid #ffd8b3;
}
.success-btn {
    display: block;
    padding: 14px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
    margin-bottom: 10px;
}
.success-btn-primary {
    background: linear-gradient(135deg, #ff6b00, #f97316);
    color: #fff;
}
.success-btn-primary:hover {
    opacity: 0.92;
    color: #fff;
}
.success-btn-outline {
    background: #fff;
    color: #ff6b00;
    border: 2px solid #ff6b00;
}
.success-btn-outline:hover {
    background: #fff9f0;
    color: #ff6b00;
    text-decoration: none;
}
</style>

<div class="success-container">
    <?php if ($result === 'completed'): ?>
        <div class="success-icon">✅</div>
        <div class="success-title">购买成功！</div>
        <div class="success-subtitle">
            NFT 已添加到您的收藏，可以在"我的收藏"中查看。
        </div>
        <?php if ($code): ?>
        <div class="success-code"><?= $code ?></div>
        <?php endif; ?>
        <a href="/user/collection.php" class="success-btn success-btn-primary">查看我的收藏</a>
        <a href="sale_list.php" class="success-btn success-btn-outline">继续浏览市场</a>

    <?php elseif ($result === 'pending'): ?>
        <div class="success-icon">⏳</div>
        <div class="success-title">购买意向已提交</div>
        <div class="success-subtitle">
            已通知卖家确认交易。卖家确认后，该 NFT 将转移到您的收藏中。
        </div>
        <?php if ($code): ?>
        <div class="success-code"><?= $code ?></div>
        <?php endif; ?>
        <a href="sale_list.php" class="success-btn success-btn-primary">返回市场</a>
        <a href="/user/collection.php" class="success-btn success-btn-outline">查看我的收藏</a>

    <?php else: ?>
        <div class="success-icon">❓</div>
        <div class="success-title">未知状态</div>
        <div class="success-subtitle">请返回市场重新操作</div>
        <a href="sale_list.php" class="success-btn success-btn-primary">返回市场</a>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
